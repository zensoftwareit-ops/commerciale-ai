<?php

namespace App\Services\Ai;

use App\Contracts\LeadAnalyzer;
use App\Models\Lead;

class FakeLeadAnalyzer implements LeadAnalyzer
{
    public function analyze(Lead $lead): array
    {
        $missing = array_keys(array_filter([
            'email_o_telefono' => ! $lead->email && ! $lead->phone,
            'servizio_richiesto' => ! $lead->requested_service,
            'budget' => empty($lead->request_data['budget'] ?? null),
        ]));

        return [
            'summary' => "Richiesta demo di {$lead->name}",
            'intent' => 'richiesta_informazioni',
            'requested_services' => array_values(array_filter([$lead->requested_service])),
            'budget' => ['raw' => $lead->request_data['budget'] ?? null, 'min' => null, 'max' => null, 'currency' => 'EUR'],
            'urgency' => 'unknown',
            'fit_score' => 50,
            'priority' => 'medium',
            'missing_information' => $missing,
            'risk_flags' => [],
            'recommended_next_action' => 'Verificare la richiesta e preparare una risposta.',
            'qualification_questions' => [],
            'confidence' => 1.0,
            '_meta' => ['provider' => 'fake', 'model' => 'deterministic-v1', 'policy_version' => 'lead-analysis-v1'],
        ];
    }
}
