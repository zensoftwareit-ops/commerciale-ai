<?php

namespace Tests\Feature;

use App\Models\InboundSource;
use App\Models\Lead;
use App\Models\WebhookReceipt;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

class InboundWebhookTest extends CommercialeAiTestCase
{
    use RefreshDatabase;

    public function test_signed_webhook_is_idempotent(): void
    {
        [$organization] = $this->organizationWithUser();
        app(TenantContext::class)->set($organization);
        $source = InboundSource::create(['name' => 'PreventivoSitoWeb', 'key' => 'psw-test', 'secret' => 'test-secret', 'is_active' => true]);
        app(TenantContext::class)->clear();
        $payload = json_encode(['external_id' => 'psw-123', 'source' => 'preventivositoweb.it', 'received_at' => now()->toIso8601String(), 'contact' => ['name' => 'Mario Rossi', 'email' => 'mario@example.test'], 'request' => ['project_type' => 'rifacimento sito'], 'consent' => ['privacy_accepted' => true, 'marketing_accepted' => false]], JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->timestamp;
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_X_WEBHOOK_SOURCE' => 'psw-test', 'HTTP_X_WEBHOOK_TIMESTAMP' => $timestamp, 'HTTP_X_WEBHOOK_SIGNATURE' => hash_hmac('sha256', $timestamp.'.'.$payload, 'test-secret'), 'HTTP_IDEMPOTENCY_KEY' => 'evt-1'];

        $this->call('POST', '/api/v1/inbound/leads', [], [], [], $headers, $payload)->assertCreated();
        $this->call('POST', '/api/v1/inbound/leads', [], [], [], $headers, $payload)->assertOk()->assertJson(['status' => 'already_processed']);
        $this->assertSame(1, Lead::withoutGlobalScopes()->count());
        $this->assertSame(1, WebhookReceipt::withoutGlobalScopes()->count());
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        [$organization] = $this->organizationWithUser();
        app(TenantContext::class)->set($organization);
        InboundSource::create(['name' => 'Source', 'key' => 'bad-sig-test', 'secret' => 'secret', 'is_active' => true]);
        app(TenantContext::class)->clear();
        $this->withHeaders(['X-Webhook-Source' => 'bad-sig-test', 'X-Webhook-Timestamp' => now()->timestamp, 'X-Webhook-Signature' => 'invalid', 'Idempotency-Key' => 'evt-bad'])->postJson('/api/v1/inbound/leads', [])->assertUnauthorized();
    }
}
