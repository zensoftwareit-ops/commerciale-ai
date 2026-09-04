<?php

namespace App\Services\Quotations;

use App\Contracts\QuotationEstimator;

class FakeQuotationEstimator implements QuotationEstimator
{
    public function estimate(array $input): array
    {
        $rule = $input['pricing_rule'];
        $request = trim((string) data_get($input, 'lead.request_summary'));
        $includes = collect(preg_split('/\R|,|;/u', (string) ($rule['includes'] ?? '')))
            ->map(fn ($item) => trim((string) $item))->filter()->take(8)->values()->all();

        return [
            'scope_title' => (string) $rule['name'],
            'scope_description' => $request !== ''
                ? 'Realizzazione richiesta sulla base delle informazioni fornite dal cliente: '.$request
                : 'Realizzazione del servizio descritto nella richiesta e nel listino approvato.',
            'deliverables' => $includes ?: [(string) $rule['name']],
            'assumptions' => [],
            'complexity_score' => 50,
            'confidence' => 0.8,
            'rationale' => 'Stima deterministica intermedia per il provider di test.',
            '_meta' => ['provider' => 'fake', 'model' => 'deterministic-v1', 'policy_version' => 'quotation-estimate-v1', 'input_units' => 0, 'output_units' => 0, 'estimated_cost' => 0],
        ];
    }
}
