<?php

namespace Tests\Unit;

use App\Models\AiAnalysis;
use App\Models\Lead;
use App\Services\Ai\OpenAiLeadReplyGenerator;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiLeadReplyGeneratorTest extends TestCase
{
    public function test_it_generates_a_structured_email_draft_with_the_responses_api(): void
    {
        config()->set('commerciale-ai.openai', [
            'api_key' => 'test-key', 'model' => 'gpt-5.6-terra', 'reasoning_effort' => 'low',
            'timeout' => 10, 'input_cost_per_million' => 2.5, 'output_cost_per_million' => 15,
        ]);
        Http::fake(fn () => Http::response([
            'model' => 'gpt-5.6-terra',
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode([
                'subject' => 'Il suo progetto web', 'body' => 'Buongiorno Anna, possiamo sentirci domani? Cordiali saluti, Demo.',
            ], JSON_THROW_ON_ERROR)]]]],
            'usage' => ['input_tokens' => 1000, 'output_tokens' => 500],
        ]));
        $lead = new Lead(['name' => 'Anna', 'company' => 'Demo Srl', 'requested_service' => 'Sito web']);
        $analysis = new AiAnalysis([
            'summary' => 'Richiesta sito web.', 'intent' => 'preventivo', 'urgency' => 'medium',
            'recommended_next_action' => 'Proporre una chiamata.', 'qualification_questions' => ['Qual è il budget?'],
        ]);

        $result = app(OpenAiLeadReplyGenerator::class)->generate($lead, $analysis, [
            'organization' => ['commercial_name' => 'Demo', 'tone_of_voice' => 'professionale', 'email_signature' => 'Team Demo'],
        ]);

        $this->assertSame('Il suo progetto web', $result['subject']);
        $this->assertSame(0.01, $result['_meta']['estimated_cost']);
        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return $request->url() === 'https://api.openai.com/v1/responses'
                && $payload['store'] === false
                && $payload['text']['format']['type'] === 'json_schema'
                && $payload['text']['format']['strict'] === true
                && str_contains(json_encode($payload), 'Team Demo');
        });
    }
}
