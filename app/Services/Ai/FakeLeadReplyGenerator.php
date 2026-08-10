<?php

namespace App\Services\Ai;

use App\Contracts\LeadReplyGenerator;
use App\Models\AiAnalysis;
use App\Models\Lead;

class FakeLeadReplyGenerator implements LeadReplyGenerator
{
    public function generate(Lead $lead, AiAnalysis $analysis, array $context = []): array
    {
        $company = data_get($context, 'organization.commercial_name', config('app.name'));
        $signature = data_get($context, 'organization.email_signature', $company);
        $service = $lead->requested_service ?: 'la sua richiesta';
        $incomingSubject = data_get($context, 'incoming_email.subject');

        if ($incomingSubject) {
            return [
                'subject' => str_starts_with(mb_strtolower($incomingSubject), 're:') ? $incomingSubject : 'Re: '.$incomingSubject,
                'body' => "Buongiorno {$lead->name},\n\ngrazie per la risposta. Abbiamo preso nota delle informazioni inviate e le proponiamo un breve confronto per definire i prossimi passi.\n\nCordiali saluti,\n{$signature}",
                '_meta' => [
                    'provider' => 'fake', 'model' => 'deterministic-v1', 'policy_version' => 'reply-draft-v1',
                    'input_units' => 0, 'output_units' => 0, 'estimated_cost' => 0,
                ],
            ];
        }

        return [
            'subject' => 'La sua richiesta per '.$service,
            'body' => "Buongiorno {$lead->name},\n\ngrazie per averci contattato in merito a {$service}. Abbiamo esaminato le informazioni inviate e vorremmo approfondire alcuni dettagli per proporle la soluzione più adatta.\n\nPossiamo sentirci per un breve confronto?\n\nCordiali saluti,\n{$signature}",
            '_meta' => [
                'provider' => 'fake', 'model' => 'deterministic-v1', 'policy_version' => 'reply-draft-v1',
                'input_units' => 0, 'output_units' => 0, 'estimated_cost' => 0,
            ],
        ];
    }
}
