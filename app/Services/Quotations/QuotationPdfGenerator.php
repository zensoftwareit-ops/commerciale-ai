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

    public function render(Quotation $quotation, OrganizationSetting $settings, ?string $logoPath): string
    {
        $this->image = $this->readJpeg($logoPath);
        $this->newPage($settings);
        $this->metadata($quotation);
        $this->recipient($quotation);
        $this->section('Oggetto', $quotation->rule->name);
        $this->priceBox($quotation);
        $this->section('Attivita incluse', $quotation->rule->includes ?: 'Da definire e confermare con il referente commerciale.');
        if (filled($quotation->rule->excludes)) $this->section('Esclusioni', $quotation->rule->excludes);
        $terms = $settings->quotation_payment_terms ?: 'Importi al netto di IVA. Modalita e tempi di pagamento da concordare in fase di conferma.';
        $this->section('Condizioni', $terms);
        if ((int) $quotation->confidence < 100 || ($quotation->missing_fields ?? []) !== []) {
            $this->notice('STIMA INDICATIVA', 'La fascia economica si basa sulle informazioni disponibili e sara confermata dal commerciale dopo la verifica finale dei requisiti.');
        }
        if (filled($settings->quotation_footer)) $this->paragraph($settings->quotation_footer, 9, false, 13, [0.35, 0.39, 0.47]);
        $this->pages[] = $this->content;

        return $this->build($settings);
    }

    private function newPage(OrganizationSetting $settings): void
    {
        if ($this->content !== '') $this->pages[] = $this->content;
        $this->content = "q 1 0.39 0.35 rg 0 814 595.28 27 re f Q\n";
        if ($this->image) {
            $ratio = $this->image['width'] / $this->image['height'];
            $height = 38.0;
            $width = min(150.0, $height * $ratio);
            $this->content .= sprintf("q %.2F 0 0 %.2F 48 745 cm /Im1 Do Q\n", $width, $height);
        } else {
            $this->drawText(self::LEFT, 773, $settings->commercial_name ?: $settings->legal_name ?: 'Azienda', 17, true, [0.08, 0.12, 0.22]);
        }
        $this->drawText(410, 775, 'PREVENTIVO', 18, true, [1, 0.39, 0.35]);
        $this->content .= "0.9 0.91 0.94 RG 48 730 499 0.8 re S\n";
        $this->y = 704;
    }

    private function metadata(Quotation $quotation): void
    {
        $this->drawText(self::LEFT, $this->y, 'Numero', 8, true, [0.4, 0.44, 0.52]);
        $this->drawText(125, $this->y, $quotation->document_number, 10, true);
        $this->drawText(310, $this->y, 'Data', 8, true, [0.4, 0.44, 0.52]);
        $this->drawText(356, $this->y, $quotation->created_at->format('d/m/Y'), 10);
        $this->drawText(435, $this->y, 'Valido fino al', 8, true, [0.4, 0.44, 0.52]);
        $this->drawText(502, $this->y, $quotation->valid_until->format('d/m/Y'), 10);
        $this->y -= 36;
    }

    private function recipient(Quotation $quotation): void
    {
        $this->content .= sprintf("q 0.97 0.97 0.98 rg %.2F %.2F 499 72 re f Q\n", self::LEFT, $this->y - 56);
        $this->drawText(self::LEFT + 14, $this->y - 17, 'DESTINATARIO', 8, true, [0.4, 0.44, 0.52]);
        $this->drawText(self::LEFT + 14, $this->y - 36, $quotation->lead->name, 12, true);
        $detail = mb_strimwidth(collect([$quotation->lead->company, $quotation->lead->email, $quotation->lead->phone])->filter()->implode('  |  '), 0, 92, '...');
        if ($detail !== '') $this->drawText(self::LEFT + 14, $this->y - 53, $detail, 9, false, [0.3, 0.34, 0.42]);
        $this->y -= 91;
    }

    private function priceBox(Quotation $quotation): void
    {
        $this->ensureSpace(90);
        $this->content .= sprintf("q 1 0.96 0.94 rg %.2F %.2F 499 74 re f Q\n", self::LEFT, $this->y - 58);
        $this->drawText(self::LEFT + 15, $this->y - 19, 'FASCIA ECONOMICA', 8, true, [0.75, 0.24, 0.2]);
        $range = 'EUR '.number_format((float) $quotation->minimum_price, 2, ',', '.').' - '.number_format((float) $quotation->maximum_price, 2, ',', '.').' + IVA';
        $this->drawText(self::LEFT + 15, $this->y - 45, $range, 18, true, [0.08, 0.12, 0.22]);
        $this->drawText(450, $this->y - 41, 'Affidabilita '.$quotation->confidence.'%', 8, true, [0.4, 0.44, 0.52]);
        $this->y -= 93;
    }

    private function section(string $title, string $body): void
    {
        $this->ensureSpace(65);
        $this->drawText(self::LEFT, $this->y, mb_strtoupper($title), 9, true, [1, 0.39, 0.35]);
        $this->y -= 18;
        $this->paragraph($body, 10, false, 15);
        $this->y -= 12;
    }

    private function notice(string $title, string $body): void
    {
        $this->ensureSpace(76);
        $top = $this->y;
        $this->content .= sprintf("q 1 0.98 0.88 rg %.2F %.2F 499 66 re f Q\n", self::LEFT, $top - 52);
        $this->drawText(self::LEFT + 13, $top - 18, $title, 8, true, [0.65, 0.42, 0.02]);
        $this->y = $top - 34;
        $this->paragraph($body, 8.5, false, 12, [0.35, 0.29, 0.12], self::LEFT + 13, 470);
        $this->y = $top - 78;
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
        $settings = OrganizationSetting::query()->firstOrFail();
        $this->newPage($settings);
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

    private function build(OrganizationSetting $settings): string
    {
        $pageCount = count($this->pages);
        foreach ($this->pages as $index => &$page) {
            $footer = $settings->quotation_company_details ?: collect([$settings->legal_name ?: $settings->commercial_name, $settings->website_url])->filter()->implode(' | ');
            $footer = mb_strimwidth($footer, 0, 110, '...');
            $this->content = '';
            $this->drawText(self::LEFT, 37, $footer, 7.5, false, [0.42, 0.46, 0.53]);
            $this->drawText(500, 37, ($index + 1).' / '.$pageCount, 7.5, true, [0.42, 0.46, 0.53]);
            $page .= "0.9 0.91 0.94 RG 48 53 499 0.5 re S\n".$this->content;
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
}
