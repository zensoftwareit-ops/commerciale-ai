<?php

namespace App\Services\Ai;

use App\Contracts\SetupWizardGenerator;

class FakeSetupWizardGenerator implements SetupWizardGenerator
{
    public function generate(string $description, array $existingProfile = []): array
    {
        $name = (string) ($existingProfile['commercial_name'] ?? 'Azienda Demo');

        return [
            'profile' => [
                'legal_name' => (string) ($existingProfile['legal_name'] ?? ''),
                'commercial_name' => $name,
                'industry' => (string) ($existingProfile['industry'] ?? 'Servizi professionali'),
                'business_description' => $description,
                'products_services' => 'Servizi descritti dal cliente durante la configurazione.',
                'service_area' => (string) ($existingProfile['service_area'] ?? 'Italia'),
                'ideal_customer' => 'Clienti interessati ai servizi descritti.',
                'pricing_rules' => '',
                'differentiators' => 'Approccio consulenziale e comunicazione trasparente.',
                'qualification_questions' => ['Quale risultato desidera ottenere?', 'Quali sono tempi e budget indicativi?'],
                'exclusion_criteria' => 'Richieste fuori ambito o prive delle informazioni minime dopo i tentativi di qualificazione.',
                'tone_of_voice' => 'professionale, chiaro e concreto',
                'email_signature' => 'Il team '.$name,
                'appointment_details' => 'Proporre un confronto con un commerciale quando la richiesta e qualificata.',
                'promised_response_minutes' => 240,
            ],
            'knowledge' => [
                'services' => 'Descrizione iniziale: '.$description,
                'faq' => "Domanda: Come viene gestita una nuova richiesta?\nRisposta: Viene analizzata e, se necessario, approfondita con poche domande mirate.",
                'request_management' => 'Raccogliere obiettivo, tempistiche e budget. Evitare domande ripetute e passare a un commerciale quando i dati restano insufficienti.',
                'pricing_guidance' => 'Non comunicare prezzi non presenti nel listino approvato. Se manca una regola applicabile, coinvolgere un commerciale.',
            ],
            'assumptions' => ['Bozza dimostrativa generata dal provider fake.'],
            '_meta' => [
                'provider' => 'fake', 'model' => 'deterministic-setup-v1', 'policy_version' => 'setup-wizard-v1',
                'input_units' => 0, 'output_units' => 0, 'estimated_cost' => 0,
            ],
        ];
    }
}
