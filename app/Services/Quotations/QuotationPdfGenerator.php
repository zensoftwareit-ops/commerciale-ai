<?php

namespace App\Services\Quotations;

use App\Models\OrganizationSetting;
use App\Models\Quotation;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class QuotationPdfGenerator
{
    public function __construct(private readonly QuotationNumberGenerator $numbers) {}

    public function ensure(Quotation $quotation): string
    {
        if ($quotation->pdf_path && Storage::disk('local')->exists($quotation->pdf_path)) {
            return $quotation->pdf_path;
        }

        $quotation->loadMissing(['lead', 'rule', 'reply']);
        if (! $quotation->reply || ! str_contains($quotation->reply->reply_kind, 'quotation')) {
            throw new RuntimeException('Il PDF è disponibile soltanto quando la quotazione è pronta per essere presentata al cliente.');
        }
        $settings = OrganizationSetting::query()->first();
        if (! $settings) throw new RuntimeException('Completa il profilo aziendale prima di generare il preventivo PDF.');
        $this->ensureDocumentNumber($quotation);

        $logo = $settings->quotation_logo_path && Storage::disk('local')->exists($settings->quotation_logo_path)
            ? Storage::disk('local')->path($settings->quotation_logo_path)
            : null;
        $pdf = (new SimpleQuotationPdf)->render($quotation, $settings, $logo);
        $path = 'organizations/'.$quotation->organization_id.'/quotations/'.$quotation->id.'/'.strtolower($quotation->document_number).'.pdf';
        if (! Storage::disk('local')->put($path, $pdf)) {
            throw new RuntimeException('Non è stato possibile archiviare il preventivo PDF.');
        }
        $quotation->update(['pdf_path' => $path, 'pdf_generated_at' => now()]);

        return $path;
    }

    public function filename(Quotation $quotation): string
    {
        return 'Preventivo-'.$quotation->document_number.'.pdf';
    }

    private function ensureDocumentNumber(Quotation $quotation): void
    {
        if ($quotation->document_number) return;
        $year = (int) ($quotation->created_at?->format('Y') ?: now()->format('Y'));
        $document = $this->numbers->next($quotation->organization_id, $year);
        $quotation->update([
            'document_year' => $document['year'],
            'document_sequence' => $document['sequence'],
            'document_number' => $document['number'],
            'valid_until' => $quotation->valid_until ?: $quotation->created_at->copy()->addDays($quotation->rule->validity_days),
        ]);
    }
}

class SimpleQuotationPdf
{
    private const PAGE_WIDTH = 595.28;
    private const PAGE_HEIGHT = 841.89;
    private const LEFT = 48.0;
    private const RIGHT = 547.28;

    /** @var array<int, string> */
    private array $pages = [];
    private string $content = '';
    private float $y = 0;
    private ?array $image = null;
    private array $primary = [0.086, 0.608, 0.835];
    private OrganizationSetting $settings;

    public function render(Quotation $quotation, OrganizationSetting $settings, ?string $logoPath): string
    {
        $this->settings = $settings;
        $this->primary = $this->color((string) ($settings->quotation_primary_color ?: '#169BD5'));
        $this->image = $this->readJpeg($logoPath);
        $this->newPage($settings);
        $this->metadata($quotation);
        $this->recipient($quotation, $settings);
        $this->section(
            mb_strtoupper($quotation->scope_title ?: $quotation->rule->name),
            $quotation->scope_description ?: ($quotation->rule->includes ?: 'Servizio descritto nella richiesta del cliente.'),
        );
        if (($quotation->line_items ?? []) !== []) {
            $this->section('Attività comprese', collect($quotation->line_items)->map(fn ($item) => '- '.trim((string) $item))->implode("\n"));
        }
        if (($quotation->assumptions ?? []) !== []) {
            $this->section('Ipotesi della stima', collect($quotation->assumptions)->map(fn ($item) => '- '.trim((string) $item))->implode("\n"));
        }
        $this->priceLine($quotation);
        if (filled($quotation->rule->excludes)) $this->section('Esclusioni', $quotation->rule->excludes);
        $terms = $settings->quotation_payment_terms ?: 'Importi al netto di IVA. Modalita e tempi di pagamento da concordare in fase di conferma.';
        $this->section('Pagamento e condizioni', $terms);
        if ((int) $quotation->confidence < 100 || ($quotation->missing_fields ?? []) !== []) {
            $this->notice('Stima indicativa:', 'la fascia economica si basa sulle informazioni disponibili e sara confermata dal commerciale dopo la verifica finale dei requisiti.');
        }
        if (filled($settings->quotation_footer)) $this->paragraph($settings->quotation_footer, 9, false, 13, [0.35, 0.39, 0.47]);
        $this->paragraph('La presente offerta e valida fino al '.$quotation->valid_until->format('d/m/Y').'.', 10, false, 15);
        $this->signature($settings);
        $this->pages[] = $this->content;

        return $this->build($settings);
    }

    private function newPage(OrganizationSetting $settings): void
    {
        if ($this->content !== '') $this->pages[] = $this->content;
        $this->content = sprintf("q %.3F %.3F %.3F RG 1.6 w 48 805 m 547 805 l S Q\n", ...$this->primary);
        if ($this->image) {
            $ratio = $this->image['width'] / $this->image['height'];
            $height = 76.0;
            $width = min(185.0, $height * $ratio);
            $this->content .= sprintf("q %.2F 0 0 %.2F 48 700 cm /Im1 Do Q\n", $width, $height);
        } else {
            $this->drawText(self::LEFT, 746, $settings->commercial_name ?: $settings->legal_name ?: 'Azienda', 24, true, $this->primary);
        }
        $header = $settings->quotation_header_text ?: collect([$settings->website_url, $settings->service_area])->filter()->implode("\n");
        $headerY = 775.0;
        foreach (preg_split('/\R/u', trim($header)) ?: [] as $line) {
            $this->drawTextRight(self::RIGHT, $headerY, trim($line), 8, false, [0.1, 0.1, 0.12]);
            $headerY -= 11;
        }
        $this->y = 658;
    }

    private function metadata(Quotation $quotation): void
    {
        $label = 'Preventivo n. '.$quotation->document_number.' del '.$quotation->created_at->format('d/m/Y');
        $this->drawText(self::LEFT, $this->y, $label, 11, false, [0.08, 0.08, 0.1]);
        $this->y -= 34;
    }

    private function recipient(Quotation $quotation, OrganizationSetting $settings): void
    {
        $template = $settings->quotation_intro_text ?: 'Spett. le {{cliente}}, in riferimento alla Vostra cortese richiesta, Vi sottoponiamo la nostra migliore offerta per:';
        $intro = str_replace(
            ['{{cliente}}', '{{azienda_cliente}}'],
            [$quotation->lead->name, $quotation->lead->company ?: $quotation->lead->name],
            $template,
        );
        $this->paragraph($intro, 10.5, false, 15.5, [0.08, 0.08, 0.1]);
        $this->y -= 13;
    }

    private function priceLine(Quotation $quotation): void
    {
        $this->ensureSpace(55);
        $amount = number_format((float) ($quotation->estimated_price ?? $quotation->maximum_price), 2, ',', '.');
        $this->drawText(self::LEFT, $this->y, 'Ns. offerta stimata: EUR '.$amount.' + IVA', 12, true, [0.08, 0.08, 0.1]);
        $this->y -= 32;
    }

    private function section(string $title, string $body): void
    {
        $this->ensureSpace(65);
        $this->drawText(self::LEFT, $this->y, $title, 11, true, [0.08, 0.08, 0.1]);
        $this->y -= 21;
        $this->paragraph($body, 10, false, 15, [0.08, 0.08, 0.1]);
        $this->y -= 10;
    }

    private function notice(string $title, string $body): void
    {
        $this->ensureSpace(58);
        $this->drawText(self::LEFT, $this->y, $title, 9, true, $this->primary);
        $this->y -= 15;
        $this->paragraph($body, 9, false, 13, [0.2, 0.22, 0.26]);
        $this->y -= 8;
    }

    private function signature(OrganizationSetting $settings): void
    {
        $this->ensureSpace(95);
        $this->y -= 24;
        $text = $settings->quotation_acceptance_text ?: "Per accettazione\nTIMBRO E FIRMA";
        foreach (preg_split('/\R/u', trim($text)) ?: [] as $index => $line) {
            $this->drawTextRight(520, $this->y, trim($line), $index === 0 ? 9 : 11, $index > 0, [0.08, 0.08, 0.1]);
            $this->y -= 15;
        }
        $this->content .= "q 0.25 0.25 0.25 RG 0.7 w 360 ".($this->y - 12)." m 520 ".($this->y - 12)." l S Q\n";
        $this->y -= 35;
    }

    private function paragraph(string $text, float $size = 10, bool $bold = false, float $leading = 15, array $color = [0.12, 0.15, 0.22], float $x = self::LEFT, float $width = 499): void
    {
        foreach (preg_split('/\R/u', trim($text)) ?: [] as $paragraph) {
            if (trim($paragraph) === '') {
                $this->y -= $leading;
                continue;
            }
            foreach ($this->wrap(trim($paragraph), $width, $size) as $line) {
                $this->ensureSpace($leading + 4);
                $this->drawText($x, $this->y, $line, $size, $bold, $color);
                $this->y -= $leading;
            }
        }
    }

    private function ensureSpace(float $height): void
    {
        if ($this->y - $height >= 70) return;
        $this->newPage($this->settings);
    }

    /** @return array<int, string> */
    private function wrap(string $text, float $width, float $size): array
    {
        $limit = max(12, (int) floor($width / ($size * 0.51)));
        $words = preg_split('/\s+/u', $text) ?: [];
        $lines = [];
        $line = '';
        foreach ($words as $word) {
            $candidate = $line === '' ? $word : $line.' '.$word;
            if (mb_strlen($candidate) <= $limit) {
                $line = $candidate;
            } else {
                if ($line !== '') $lines[] = $line;
                $line = $word;
            }
        }
        if ($line !== '') $lines[] = $line;
        return $lines ?: [''];
    }

    private function drawText(float $x, float $y, string $text, float $size, bool $bold = false, array $color = [0.12, 0.15, 0.22]): void
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT', $text) ?: $text;
        $encoded = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $encoded);
        $this->content .= sprintf("BT /%s %.2F Tf %.3F %.3F %.3F rg %.2F %.2F Td (%s) Tj ET\n", $bold ? 'F2' : 'F1', $size, $color[0], $color[1], $color[2], $x, $y, $encoded);
    }

    private function drawTextRight(float $right, float $y, string $text, float $size, bool $bold = false, array $color = [0.12, 0.15, 0.22]): void
    {
        $estimatedWidth = mb_strlen($text) * $size * 0.49;
        $this->drawText(max(self::LEFT, $right - $estimatedWidth), $y, $text, $size, $bold, $color);
    }

    private function build(OrganizationSetting $settings): string
    {
        $pageCount = count($this->pages);
        foreach ($this->pages as $index => &$page) {
            $left = $settings->quotation_footer_left ?: '';
            $center = $settings->quotation_footer_center ?: ($settings->quotation_company_details ?: ($settings->legal_name ?: $settings->commercial_name));
            $right = $settings->quotation_footer_right ?: ($settings->website_url ?: '');
            $this->content = '';
            $this->drawText(self::LEFT, 35, mb_strimwidth($left, 0, 42, '...'), 7.2, false, [0.16, 0.16, 0.18]);
            $this->drawText(220, 35, mb_strimwidth($center, 0, 60, '...'), 7.2, false, [0.16, 0.16, 0.18]);
            $this->drawTextRight(self::RIGHT, 35, mb_strimwidth($right, 0, 42, '...'), 7.2, false, [0.16, 0.16, 0.18]);
            $this->drawTextRight(self::RIGHT, 22, ($index + 1).' / '.$pageCount, 6.5, false, [0.45, 0.45, 0.48]);
            $page .= sprintf("q %.3F %.3F %.3F RG 1.6 w 48 51 m 547 51 l S Q\n", ...$this->primary).$this->content;
        }
        unset($page);

        $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>', 4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>'];
        $next = 5;
        $imageId = null;
        if ($this->image) {
            $imageId = $next++;
            $objects[$imageId] = '<< /Type /XObject /Subtype /Image /Width '.$this->image['width'].' /Height '.$this->image['height'].' /ColorSpace /'.$this->image['color'].' /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($this->image['data'])." >>\nstream\n".$this->image['data']."\nendstream";
        }
        $kids = [];
        foreach ($this->pages as $pageContent) {
            $pageId = $next++;
            $contentId = $next++;
            $kids[] = $pageId.' 0 R';
            $xObject = $imageId ? ' /XObject << /Im1 '.$imageId.' 0 R >>' : '';
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.self::PAGE_WIDTH.' '.self::PAGE_HEIGHT.'] /Resources << /Font << /F1 3 0 R /F2 4 0 R >>'.$xObject.' >> /Contents '.$contentId.' 0 R >>';
            $objects[$contentId] = '<< /Length '.strlen($pageContent)." >>\nstream\n".$pageContent."endstream";
        }
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.$pageCount.' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $size = max(array_keys($objects)) + 1;
        $pdf .= "xref\n0 ".$size."\n0000000000 65535 f \n";
        for ($id = 1; $id < $size; $id++) $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
        $pdf .= "trailer\n<< /Size ".$size." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";

        return $pdf;
    }

    private function readJpeg(?string $path): ?array
    {
        if (! $path || ! is_file($path)) return null;
        $info = @getimagesize($path);
        if (! $info || ($info['mime'] ?? null) !== 'image/jpeg') return null;
        $data = @file_get_contents($path);
        if ($data === false) return null;
        return ['data' => $data, 'width' => $info[0], 'height' => $info[1], 'color' => ($info['channels'] ?? 3) === 1 ? 'DeviceGray' : 'DeviceRGB'];
    }

    private function color(string $hex): array
    {
        if (! preg_match('/^#([0-9a-f]{6})$/i', $hex, $matches)) return [0.086, 0.608, 0.835];
        return [hexdec(substr($matches[1], 0, 2)) / 255, hexdec(substr($matches[1], 2, 2)) / 255, hexdec(substr($matches[1], 4, 2)) / 255];
    }
}
