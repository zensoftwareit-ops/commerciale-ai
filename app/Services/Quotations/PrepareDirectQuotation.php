<?php

namespace App\Services\Quotations;

use App\Models\Activity;
use App\Models\AiAnalysis;
use App\Models\CommercialNotification;
use App\Models\Lead;
use App\Models\Quotation;

class PrepareDirectQuotation
{
    public function __construct(
        private readonly BuildQuotation $builder,
        private readonly QuotationPdfGenerator $pdfs,
    ) {}

    /** @return array{status:string,quotation:?Quotation,reason:?string} */
    public function handle(Lead $lead, AiAnalysis $analysis): array
    {
        $result = $this->builder->handle($lead, $analysis);
        $quotation = $result['quotation'];
        if (! $quotation) {
            return $this->handoff($lead, null, $this->reason($result['blockers']));
        }
        if (($quotation->missing_fields ?? []) !== []) {
            return $this->handoff($lead, $quotation, 'Mancano dati obbligatori del listino: '.implode(', ', $quotation->missing_fields).'.');
        }
        if ($quotation->estimated_price === null) {
            return $this->handoff($lead, $quotation, 'Non è stato possibile calcolare un importo dalla regola di listino applicabile.');
        }

        $this->pdfs->ensure($quotation);
        $this->markAnalysisAsQuoted($analysis, $quotation);
        $lead->update(['operational_status' => 'awaiting_approval', 'next_action_at' => now(), 'last_activity_at' => now()]);
        Activity::create([
            'organization_id' => $lead->organization_id, 'lead_id' => $lead->id,
            'type' => 'direct_quotation_ready', 'title' => 'Preventivo PDF pronto per la revisione',
            'data' => ['quotation_id' => $quotation->id], 'occurred_at' => now(),
        ]);
        $this->notify($lead, 'direct_quote_ready', 'Preventivo PDF pronto',
            'Daria ha generato il preventivo per '.$lead->name.'. Il documento è pronto per la verifica e non è stato inviato al cliente.',
            ['quotation_id' => $quotation->id]);

        return ['status' => 'ready', 'quotation' => $quotation->fresh(), 'reason' => null];
    }

    private function markAnalysisAsQuoted(AiAnalysis $analysis, Quotation $quotation): void
    {
        $missingInformation = collect($analysis->missing_information ?? [])
            ->reject(fn ($item) => preg_match('/\b(?:listin|tariff|regol[ae]\s+(?:di\s+)?prezz|pricing)\w*/iu', (string) $item) === 1)
            ->values()->all();

        $analysis->update([
            'missing_information' => $missingInformation,
            'recommended_next_action' => 'Verificare il preventivo PDF '.$quotation->document_number.' generato da Daria prima di qualsiasi invio al cliente.',
        ]);
    }

    private function handoff(Lead $lead, ?Quotation $quotation, string $reason): array
    {
        $lead->update(['operational_status' => 'needs_action', 'next_action_at' => now(), 'last_activity_at' => now()]);
        Activity::create([
            'organization_id' => $lead->organization_id, 'lead_id' => $lead->id,
            'type' => 'direct_quotation_operator', 'title' => 'Preventivo assegnato all’operatore',
            'data' => ['quotation_id' => $quotation?->id, 'reason' => $reason], 'occurred_at' => now(),
        ]);
        $this->notify($lead, 'direct_quote_operator', 'Preventivo da gestire manualmente',
            'Daria non ha generato automaticamente il PDF per '.$lead->name.'. '.$reason,
            ['quotation_id' => $quotation?->id, 'reason' => $reason]);

        return ['status' => 'needs_operator', 'quotation' => $quotation, 'reason' => $reason];
    }

    private function notify(Lead $lead, string $type, string $title, string $message, array $data): void
    {
        foreach ($lead->organization()->firstOrFail()->users()->wherePivotIn('role', ['owner', 'sales'])->get() as $user) {
            CommercialNotification::query()->firstOrCreate([
                'user_id' => $user->id, 'lead_id' => $lead->id, 'type' => $type,
            ], [
                'organization_id' => $lead->organization_id, 'title' => $title,
                'message' => $message, 'data' => $data,
            ]);
        }
    }

    private function reason(array $blockers): string
    {
        if (in_array('ambiguous_pricing_rule', $blockers, true)) return 'Più regole di listino risultano compatibili.';
        if (in_array('no_matching_pricing_rule', $blockers, true)) return 'Non esiste una regola di listino compatibile.';

        return 'La richiesta richiede una valutazione commerciale.';
    }
}
