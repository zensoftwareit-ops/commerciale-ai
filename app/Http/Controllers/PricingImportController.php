<?php

namespace App\Http\Controllers;

use App\Models\AiRun;
use App\Models\KnowledgeDocument;
use App\Models\PricingRule;
use App\Services\Ai\ImportPricingDocuments;
use App\Services\Quotations\PricingFormulaValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use JsonException;
use Throwable;

class PricingImportController extends Controller
{
    public function create()
    {
        return view('pricing-import.create');
    }

    public function generate(Request $request, ImportPricingDocuments $importer)
    {
        $data = $request->validate([
            'explanation' => 'required|string|min:10|max:10000',
            'attachments' => 'required|array|min:1|max:5',
            'attachments.*' => 'required|file|max:10240|mimes:pdf,png,jpg,jpeg,webp,docx,txt,csv|extensions:pdf,png,jpg,jpeg,webp,docx,txt,csv',
            'consent' => 'accepted',
        ], [
            'required' => 'Compila :attribute.', 'string' => ':attribute deve essere un testo.',
            'array' => 'Il formato degli allegati non è valido.', 'file' => 'Seleziona un file valido.',
            'explanation.max' => 'La spiegazione non può superare 10.000 caratteri.',
            'explanation.required' => 'Scrivi una spiegazione dei documenti e di cosa vuoi ottenere.',
            'explanation.min' => 'La spiegazione deve contenere almeno 10 caratteri.',
            'attachments.required' => 'Seleziona almeno un documento o un’immagine.',
            'attachments.max' => 'Puoi caricare al massimo 5 allegati.',
            'attachments.*.max' => 'Ogni allegato deve essere inferiore a 10 MB.',
            'attachments.*.mimes' => 'Formato non supportato. Usa PDF, Word DOCX, TXT, CSV, JPG, PNG o WEBP.',
            'attachments.*.extensions' => 'L’estensione del file non è supportata.',
            'attachments.*.uploaded' => 'Caricamento fallito: controlla la dimensione del file e i limiti PHP del server.',
            'consent.accepted' => 'Conferma l’invio dei documenti a OpenAI.',
        ]);
        if (array_sum(array_map(fn ($file) => $file->getSize(), $data['attachments'])) > 20 * 1024 * 1024) {
            throw ValidationException::withMessages(['attachments' => 'Gli allegati non possono superare 20 MB complessivi.']);
        }
        try {
            $run = $importer->enqueue($data['explanation'], $data['attachments'], (string) $request->user()->id);
        } catch (Throwable $e) {
            // Do not flash uploaded objects or provider response bodies into the session/logs.
            if (! $e instanceof ValidationException && ! $e instanceof \RuntimeException) report($e);
            $message = $e instanceof ValidationException
                ? ($e->validator->errors()->has('license') ? $e->validator->errors()->first('license')
                    : 'OpenAI ha restituito una proposta incompleta o non valida. Prova con documenti più brevi e una spiegazione più dettagliata.')
                : ($e instanceof \RuntimeException ? $e->getMessage()
                    : 'Analisi non completata. Consulta il log applicativo e riprova.');
            return back()->withInput(['explanation' => $data['explanation']])->withErrors(['import' => $message]);
        }
        return redirect()->route($run->status === 'completed' ? 'pricing-import.preview' : 'pricing-import.status', $run->id);
    }

    public function status(Request $request, string $draft)
    {
        $run = AiRun::query()->where('operation', 'pricing_import')->findOrFail($draft);
        abort_unless(($run->input_context['user_id'] ?? null) === (string) $request->user()->id, 404);
        if ($run->status === 'completed') return redirect()->route('pricing-import.preview', $run->id);
        return view('pricing-import.status', ['run' => $run]);
    }

    private function draft(string $id, Request $request, bool $lock = false): AiRun
    {
        $query = AiRun::query()->where('operation', 'pricing_import')->where('status', 'completed');
        if ($lock) $query->lockForUpdate();
        $run = $query->findOrFail($id);
        abort_unless(($run->input_context['user_id'] ?? null) === (string) $request->user()->id, 404);
        return $run;
    }

    public function preview(Request $request, string $draft)
    {
        $run = $this->draft($draft, $request);
        if (isset($run->output['applied_at'])) return redirect()->route('settings.organization')->with('status', 'Questa importazione è già stata salvata.');
        return view('pricing-import.preview', ['run' => $run, 'draft' => $run->output]);
    }

    public function apply(Request $request, string $draft)
    {
        $run = $this->draft($draft, $request);
        if (isset($run->output['applied_at'])) return redirect()->route('settings.organization')->with('status', 'Importazione già salvata: nessuna voce duplicata.');
        $data = $request->validate([
            'items' => 'nullable|array|max:20', 'items.*' => 'array', 'items.*.selected' => 'nullable|boolean',
            'save_guidance' => 'nullable|boolean', 'guidance' => 'nullable|string|max:20000',
        ], ['array' => 'Il formato delle voci non è valido.', 'boolean' => 'La selezione delle voci non è valida.',
            'max' => 'Troppi dati da salvare: massimo 20 voci e 20.000 caratteri di regole commerciali.', 'string' => 'Le regole commerciali devono essere un testo.']);
        $selected = [];
        // Validate each selected row separately; Laravel strips unvalidated nested keys above.
        foreach ($request->input('items', []) ?? [] as $index => $item) {
            abort_unless(ctype_digit((string) $index) && isset($run->output['items'][$index]), 422);
            if (! ($item['selected'] ?? false)) continue;
            $validated = Validator::make($item, [
                'name' => 'required|string|max:255', 'keywords_text' => 'required|string|max:2000',
                'minimum_price' => 'required|numeric|min:0|max:99999999.99',
                'maximum_price' => 'required|numeric|gte:minimum_price|max:99999999.99',
                'daily_rate_tiers_text' => 'nullable|string|max:5000', 'origin_address' => 'nullable|string|max:500',
                'distance_rate_per_km' => 'nullable|numeric|min:0|max:10000', 'distance_round_trip' => 'nullable|boolean',
                'pricing_formula_text' => 'nullable|string|max:50000',
                'includes' => 'nullable|string|max:5000', 'excludes' => 'nullable|string|max:5000',
                'validity_days' => 'required|integer|min:1|max:365', 'is_active' => 'nullable|boolean',
            ], ['required' => 'Compila :attribute.', 'gte' => 'Il prezzo massimo deve essere almeno pari al minimo.',
                'numeric' => ':attribute deve essere un numero.', 'min' => 'Valore troppo piccolo per :attribute.',
                'max' => 'Valore troppo grande per :attribute.', 'integer' => ':attribute deve essere intero.',
            ], ['name' => 'il nome', 'keywords_text' => 'le parole chiave', 'minimum_price' => 'il prezzo minimo', 'maximum_price' => 'il prezzo massimo', 'validity_days' => 'la validità in giorni']);
            if ($validated->fails()) throw ValidationException::withMessages(['items.'.$index => 'Voce '.($index + 1).': '.implode(' ', $validated->errors()->all())]);
            $values = $validated->validated();
            $values['keywords'] = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $values['keywords_text'])), fn ($word) => $word !== ''));
            if ($values['keywords'] === []) throw ValidationException::withMessages(['items.'.$index => 'Inserisci almeno una parola chiave per la voce '.($index + 1).'.']);
            $values['daily_rate_tiers'] = $this->tiers($values['daily_rate_tiers_text'] ?? '', $index);
            $values['pricing_formula'] = $this->formula($values['pricing_formula_text'] ?? '', $index);
            $values['distance_round_trip'] = (bool) ($values['distance_round_trip'] ?? false);
            unset($values['keywords_text'], $values['daily_rate_tiers_text'], $values['pricing_formula_text']);
            $values['required_fields'] = [];
            $values['is_active'] = (bool) ($values['is_active'] ?? false);
            $selected[] = $values;
        }
        $guidance = ($data['save_guidance'] ?? false) ? trim($data['guidance'] ?? '') : '';
        if ($selected === [] && $guidance === '') throw ValidationException::withMessages(['import' => 'Seleziona almeno una voce oppure le regole commerciali da salvare.']);
        DB::transaction(function () use ($draft, $request, $selected, $guidance) {
            $run = $this->draft($draft, $request, true);
            if (isset($run->output['applied_at'])) return;
            foreach ($selected as $values) PricingRule::create($values);
            if ($guidance !== '') KnowledgeDocument::create([
                'updated_by' => $request->user()->id, 'title' => 'Regole commerciali da documenti · '.now()->format('d/m/Y H:i'),
                'type' => 'text', 'content' => $guidance, 'status' => 'active', 'source' => 'pricing_import', 'source_key' => $run->id,
            ]);
            $run->update(['output' => $run->output + ['applied_at' => now()->toIso8601String()]]);
        });
        return redirect()->route('settings.organization')->with('status', 'Listini e regole selezionati salvati. Puoi modificarli in Azienda e AI e nella Knowledge base.');
    }

    private function tiers(string $text, int|string $index): array
    {
        return collect(preg_split('/\R/u', $text))->map(function ($line) use ($index): ?array {
            if (trim((string) $line) === '') return null;
            if (! preg_match('/^\s*(\d+)\s*(?:-|a)\s*(\d+|\*)\s*[:=]\s*([0-9]+(?:[.,][0-9]+)?)\s*$/iu', (string) $line, $match)) {
                throw ValidationException::withMessages(['items.'.$index => 'Scaglione non valido. Usa ad esempio 1-3: 550.']);
            }
            return ['min_days' => (int) $match[1], 'max_days' => $match[2] === '*' ? null : (int) $match[2], 'rate_per_day' => (float) str_replace(',', '.', $match[3])];
        })->filter()->values()->all();
    }

    private function formula(string $text, int|string $index): ?array
    {
        if (trim($text) === '') return null;
        try {
            $formula = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($formula)) throw new JsonException;
            return app(PricingFormulaValidator::class)->validate($formula);
        } catch (JsonException) {
            throw ValidationException::withMessages(['items.'.$index => 'La ricetta di calcolo non contiene un JSON valido.']);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(['items.'.$index => 'Ricetta di calcolo non valida: '.implode(' ', collect($e->errors())->flatten()->all())]);
        }
    }
}
