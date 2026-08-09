<?php

namespace App\Services\Ai;

use App\Models\Lead;
use App\Models\QualificationProfile;

class RuleScorer
{
    public const DEFAULT_RULES = [
        'contact_available' => 15,
        'service_known' => 25,
        'budget_known' => 20,
        'company_known' => 10,
        'message_present' => 15,
        'privacy_accepted' => 15,
    ];

    public function score(Lead $lead, ?QualificationProfile $profile): int
    {
        $rules = $profile?->rules ?: self::DEFAULT_RULES;
        $checks = [
            'contact_available' => filled($lead->email) || filled($lead->phone),
            'service_known' => filled($lead->requested_service),
            'budget_known' => filled($lead->request_data['budget'] ?? null),
            'company_known' => filled($lead->company),
            'message_present' => filled($lead->request_data['message'] ?? null),
            'privacy_accepted' => (bool) ($lead->consent_data['privacy_accepted'] ?? false),
        ];
        $total = max(1, array_sum($rules));
        $earned = 0;
        foreach ($rules as $rule => $weight) {
            $earned += ($checks[$rule] ?? false) ? (int) $weight : 0;
        }

        return (int) round(($earned / $total) * 100);
    }
}
