<?php

namespace Tests\Unit;

use App\Services\Ai\OpenAiSetupWizardGenerator;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class OpenAiSetupWizardGeneratorTest extends TestCase
{
    public function test_it_uses_responses_structured_output_for_the_setup_draft(): void
    {
        config()->set('commerciale-ai.openai', [
            'api_key' => 'test-key', 'model' => 'gpt-5.6-terra', 'reasoning_effort' => 'low',
            'timeout' => 10, 'input_cost_per_million' => 2, 'output_cost_per_million' => 12,
        ]);
        Http::fake(fn () => Http::response([
            'model' => 'gpt-5.6-terra',
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode($this->validOutput(), JSON_THROW_ON_ERROR)]]]],
            'usage' => ['input_tokens' => 1000, 'output_tokens' => 500],
        ], 200, ['x-request-id' => 'req_setup_test']));

        $website = ['url' => 'https://example.com', 'pages' => [[
            'url' => 'https://example.com/servizi', 'title' => 'Servizi', 'text' => 'Consulenza per PMI.',
        ]]];
        $result = app(OpenAiSetupWizardGenerator::class)->generate('Descrizione completa dell attivita di test.', [], $website);

        $this->assertSame('openai', $result['_meta']['provider']);
        $this->assertSame('req_setup_test', $result['_meta']['request_id']);
        $this->assertSame(0.008, $result['_meta']['estimated_cost']);
        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();
            $input = json_decode($payload['input'][1]['content'], true, 512, JSON_THROW_ON_ERROR);

            return $request->url() === 'https://api.openai.com/v1/responses'
                && $payload['store'] === false
                && $payload['text']['format']['type'] === 'json_schema'
                && $payload['text']['format']['strict'] === true
                && $payload['text']['format']['name'] === 'organization_setup_draft'
                && $input['website_snapshot']['pages'][0]['text'] === 'Consulenza per PMI.';
        });
    }

    public function test_it_does_not_call_openai_without_a_server_key(): void
    {
        config()->set('commerciale-ai.openai.api_key', null);
        Http::fake();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OPENAI_API_KEY');
        app(OpenAiSetupWizardGenerator::class)->generate('Descrizione di test', []);
        Http::assertNothingSent();
    }

    private function validOutput(): array
    {
        return [
            'profile' => [
                'legal_name' => '', 'commercial_name' => 'Demo', 'industry' => 'Software',
                'business_description' => 'Software per PMI.', 'products_services' => 'Applicazioni web.',
                'service_area' => 'Italia', 'ideal_customer' => 'PMI italiane.', 'pricing_rules' => '',
                'differentiators' => 'Supporto diretto.', 'qualification_questions' => ['Quale obiettivo?'],
                'exclusion_criteria' => 'Richieste fuori ambito.', 'tone_of_voice' => 'professionale',
                'email_signature' => 'Team Demo', 'appointment_details' => 'Call conoscitiva.',
                'promised_response_minutes' => 240,
            ],
            'knowledge' => [
                'services' => 'Servizi.', 'faq' => 'FAQ.', 'request_management' => 'Processo.',
                'pricing_guidance' => 'Richiedere un listino approvato.',
            ],
            'assumptions' => ['Area geografica da confermare.'],
        ];
    }
}
