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
