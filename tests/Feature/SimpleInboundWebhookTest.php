<?php

namespace Tests\Feature;

use App\Models\InboundSource;
use App\Models\Lead;
use App\Models\WebhookReceipt;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SimpleInboundWebhookTest extends CommercialeAiTestCase
{
    use RefreshDatabase;

    public function test_it_adapts_an_italian_flat_payload_and_is_automatically_idempotent(): void
    {
        [$organization] = $this->organizationWithUser();
        [$source, $token] = $this->source($organization, ['preventivositoweb.it']);
        $payload = [
            'id_richiesta' => 'modulo-123',
            'nome_e_cognome' => 'Mario Rossi',
            'email' => 'mario@example.test',
            'telefono' => '+39 333 1234567',
            'tipo_di_sito' => 'Sito professionale',
            'budget' => '2500-5000 EUR',
            'obiettivo' => 'Generare contatti',
            'consenso_privacy' => true,
        ];

        $url = "/api/v1/inbound/leads/{$token}";
        $this->withHeader('Origin', 'https://www.preventivositoweb.it')->postJson($url, $payload)
            ->assertCreated()
            ->assertJson(['status' => 'created', 'domain_validation' => 'origin_header']);
        $this->withHeader('Origin', 'https://preventivositoweb.it')->postJson($url, $payload)
            ->assertOk()
            ->assertJson(['status' => 'already_processed']);

        $lead = Lead::withoutGlobalScopes()->sole();
        $this->assertSame($source->id, $lead->inbound_source_id);
        $this->assertSame('Mario Rossi', $lead->name);
        $this->assertSame('Sito professionale', $lead->requested_service);
        $this->assertSame('2500-5000 EUR', $lead->request_data['budget']);
        $this->assertArrayNotHasKey('email', $lead->request_data);
        $this->assertSame(1, WebhookReceipt::withoutGlobalScopes()->count());
        $receipt = WebhookReceipt::withoutGlobalScopes()->sole();
        $this->assertSame('www.preventivositoweb.it', $receipt->source_domain);
        $this->assertSame('origin_header', $receipt->validation_mode);
    }

    public function test_it_accepts_a_nested_server_payload_using_only_the_secret_endpoint(): void
    {
        [$organization] = $this->organizationWithUser();
        [, $token] = $this->source($organization, ['example.com']);

        $this->postJson("/api/v1/inbound/leads/{$token}", [
            'submission_id' => 'submission-9',
            'contact' => ['full_name' => 'Anna Bianchi', 'email' => 'anna@example.test'],
            'request' => ['project_type' => 'E-commerce', 'notes' => 'Catalogo di 50 prodotti'],
            'consent' => ['privacy_accepted' => true],
        ])->assertCreated()->assertJson(['domain_validation' => 'endpoint_token']);

        $lead = Lead::withoutGlobalScopes()->sole();
        $this->assertSame('Anna Bianchi', $lead->name);
        $this->assertSame('E-commerce', $lead->requested_service);
        $this->assertSame('Catalogo di 50 prodotti', $lead->request_data['message']);
    }

    public function test_it_rejects_domain_evidence_outside_the_allowlist_and_invalid_tokens(): void
    {
        [$organization] = $this->organizationWithUser();
        [, $token] = $this->source($organization, ['example.com']);

        $this->withHeader('Origin', 'https://evil.test')->postJson("/api/v1/inbound/leads/{$token}", ['name' => 'Bad Origin'])
            ->assertForbidden();
        $this->postJson("/api/v1/inbound/leads/{$token}", ['name' => 'Bad Payload', 'source_url' => 'https://evil.test/form'])
            ->assertForbidden();
        $this->postJson('/api/v1/inbound/leads/'.str_repeat('x', 64), ['name' => 'Wrong Token'])
            ->assertNotFound();
        $this->assertSame(0, Lead::withoutGlobalScopes()->count());
    }

    private function source($organization, array $domains): array
    {
        $token = str_repeat('a', 32).bin2hex(random_bytes(16));
        app(TenantContext::class)->set($organization);
        $source = InboundSource::create([
            'name' => 'Test source',
            'key' => 'source-'.bin2hex(random_bytes(5)),
            'secret' => 'legacy-secret',
            'allowed_domains' => $domains,
            'endpoint_token_hash' => hash('sha256', $token),
            'is_active' => true,
        ]);
        app(TenantContext::class)->clear();

        return [$source, $token];
    }
}
