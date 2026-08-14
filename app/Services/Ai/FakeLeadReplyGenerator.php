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
        $quotation = $context['quotation'] ?? null;

        if ($quotation && (empty($quotation['missing_fields']) || ($quotation['indicative'] ?? false))) {
            $minimum = number_format($quotation['minimum_price'], 0, ',', '.');
            $maximum = number_format($quotation['maximum_price'], 0, ',', '.');

            return [
                'subject' => $incomingSubject ? 'Re: '.preg_replace('/^re:\s*/i', '', $incomingSubject) : 'Stima economica per '.$service,
                'body' => "Buongiorno {$lead->name},\n\nper la richiesta descritta stimiamo una fascia indicativa tra {$minimum} e {$maximum} euro + IVA. La stima è valida fino al {$quotation['valid_until']} e sarà confermata dal commerciale dopo la verifica finale dei requisiti.\n\nCordiali saluti,\n{$signature}",
                '_meta' => ['provider' => 'fake', 'model' => 'deterministic-v1', 'policy_version' => 'quotation-draft-v1', 'input_units' => 0, 'output_units' => 0, 'estimated_cost' => 0],
            ];
        }

        if ($quotation && ! empty($quotation['missing_fields'])) {
            $questions = collect($quotation['missing_fields'])->take(2)->map(fn ($field) => '- Può indicare '.str_replace('_', ' ', $field).'?')->implode("\n");

            return [
                'subject' => $incomingSubject ? 'Re: '.preg_replace('/^re:\s*/i', '', $incomingSubject) : 'Dettagli necessari per il preventivo',
                'body' => "Buongiorno {$lead->name},\n\nper preparare una stima attendibile ci servono ancora queste informazioni:\n{$questions}\n\nCordiali saluti,\n{$signature}",
                '_meta' => ['provider' => 'fake', 'model' => 'deterministic-v1', 'policy_version' => 'qualification-draft-v1', 'input_units' => 0, 'output_units' => 0, 'estimated_cost' => 0],
            ];
        }

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

