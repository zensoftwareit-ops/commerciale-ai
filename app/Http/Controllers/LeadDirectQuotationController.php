<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\PricingRule;
use App\Services\Ai\AnalyzeLead;
use App\Services\Quotations\PrepareDirectQuotation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Throwable;

class LeadDirectQuotationController extends Controller
{
    public function __invoke(
        Request $request,
        string $lead,
        AnalyzeLead $analyzer,
        PrepareDirectQuotation $quotation,
    ): RedirectResponse {
        $lead = Lead::query()->findOrFail($lead);
        $data = $request->validate(['pricing_rule_id' => ['required', 'uuid']]);
        $rule = PricingRule::query()->where('is_active', true)->findOrFail($data['pricing_rule_id']);
        $analysis = $lead->analyses()->first();

        try {
            $analysis ??= $analyzer->handle($lead, $request->user()->id);
            $this->rememberMapping($lead, $rule);
            $result = $quotation->handle($lead, $analysis, $rule->id);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['reply' => 'Preventivo non generato: '.$exception->getMessage()]);
        }

        if ($result['status'] !== 'ready') {
            return back()->withErrors(['reply' => 'Preventivo non generato: '.$result['reason']]);
        }

        return back()->with('status', 'Regola “'.$rule->name.'” associata alla richiesta e preventivo PDF generato. Nessuna email è stata inviata.');
    }

    private function rememberMapping(Lead $lead, PricingRule $rule): void
    {
        $selectedProduct = collect(Arr::dot($lead->request_data ?? []))
            ->first(function ($value, $path): bool {
                $field = Str::of((string) $path)->lower()->ascii()->value();

                return filled($value) && is_scalar($value)
                    && preg_match('/\b(?:mezzo|veicolo|prodotto|modello)\b/', $field) === 1;
            });
        $mapping = trim((string) ($selectedProduct ?: $lead->requested_service));
        if ($mapping === '') return;

        $keywords = collect($rule->keywords ?? [])
            ->push($mapping)
            ->unique(fn ($keyword) => Str::of((string) $keyword)->lower()->ascii()->squish()->value())
            ->values()->all();
        $rule->update(['keywords' => $keywords]);
    }
}
