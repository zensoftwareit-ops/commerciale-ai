<?php

namespace Tests\Unit;

use App\Models\Lead;
use App\Services\Ai\OpenAiLeadAnalyzer;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class OpenAiLeadAnalyzerTest extends TestCase
{
    public function test_it_uses_responses_structured_output_and_redacts_contact_data(): void
    {
        config()->set('commerciale-ai.openai', [
            'api_key' => 'test-key', 'model' => 'gpt-5.6-terra', 'reasoning_effort' => 'low',
            'timeout' => 10, 'input_cost_per_million' => 2, 'output_cost_per_million' => 12,
        ]);
        Http::fake(fn (Request $request) => Http::response([
            'model' => 'gpt-5.6-terra',
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode($this->validOutput(), JSON_THROW_ON_ERROR)]]]],
            'usage' => ['input_tokens' => 1000, 'output_tokens' => 500, 'total_tokens' => 1500],
        ], 200, ['x-request-id' => 'req_test']));
        $lead = new Lead([
            'name' => 'Mario Rossi', 'email' => 'mario@example.test', 'phone' => '+39 333 1234567',
            'company' => 'Rossi Srl', 'source_label' => 'manual', 'requested_service' => 'Sito web',
            'request_data' => ['message' => 'Scrivere a mario@example.test oppure +39 333 1234567'],
        ]);

        $result = app(OpenAiLeadAnalyzer::class)->analyze($lead, ['policy' => ['version' => 'v-test', 'instructions' => 'Usa solo fatti.']]);

        $this->assertSame('openai', $result['_meta']['provider']);
        $this->assertSame('req_test', $result['_meta']['request_id']);
        $this->assertSame(0.008, $result['_meta']['estimated_cost']);
        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();
            $input = json_encode($payload, JSON_THROW_ON_ERROR);

            return $request->url() === 'https://api.openai.com/v1/responses'
                && $payload['store'] === false
                && $payload['text']['format']['type'] === 'json_schema'
                && $payload['text']['format']['strict'] === true
                && ! str_contains($input, 'mario@example.test')
                && ! str_contains($input, '333 1234567')
                && str_contains($input, '[email-redacted]')
                && str_contains($input, '[phone-redacted]');
        });
    }

    public function test_it_refuses_to_call_openai_without_server_key(): void
    {
        config()->set('commerciale-ai.openai.api_key', null);
        Http::fake();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OPENAI_API_KEY');
        app(OpenAiLeadAnalyzer::class)->analyze(new Lead(['name' => 'Demo']));
        Http::assertNothingSent();
    }

    private function validOutput(): array
    {
        return [
            'summary' => 'Lead interessato a un sito web.', 'intent' => 'richiesta_preventivo',
            'requested_services' => ['Sito web'], 'budget' => ['raw' => null, 'min' => null, 'max' => null, 'currency' => 'EUR'],
            'urgency' => 'unknown', 'fit_score' => 70, 'priority' => 'medium', 'missing_information' => ['budget'],
            'risk_flags' => [], 'recommended_next_action' => 'Chiedere budget e tempistiche.',
            'qualification_questions' => ['Qual è il budget?'], 'confidence' => 0.9,
        ];
    }
}
