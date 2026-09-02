<?php

namespace Tests\Feature;

use App\Models\OrganizationSetting;
use App\Models\WhatsappAccount;
use App\Models\WhatsappMessage;
use App\Services\Mail\RunConversationAutomation;
use App\Services\Whatsapp\ProcessWhatsappMessages;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class WhatsappBetaTest extends CommercialeAiTestCase
{
    use RefreshDatabase;

    public function test_meta_can_verify_the_webhook(): void
    {
        config()->set('services.whatsapp.webhook_verify_token', 'verify-secret');

        $this->get('/api/v1/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=verify-secret&hub.challenge=12345')
            ->assertOk()->assertSeeText('12345');
    }

    public function test_signed_inbound_message_creates_a_lead_and_is_answered_in_internal_beta(): void
    {
        config()->set('services.whatsapp.app_secret', 'app-secret');
        config()->set('services.whatsapp.beta_external_send_enabled', false);
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.outbound-1']]], 200),
        ]);
        [$organization] = $this->organizationWithUser();
        app(TenantContext::class)->set($organization);
        OrganizationSetting::create([
            'commercial_name' => 'Demo', 'industry' => 'Software', 'business_description' => 'Sviluppo software',
            'products_services' => 'Siti e applicazioni', 'ideal_customer' => 'PMI',
            'tone_of_voice' => 'professionale', 'email_signature' => 'Team Demo',
            'conversation_automation_enabled' => true, 'internal_test_only' => true,
            'max_automatic_replies' => 3,
        ]);
        WhatsappAccount::create([
            'name' => 'WhatsApp Demo', 'waba_id' => 'waba-1', 'phone_number_id' => 'phone-id-1',
            'display_phone_number' => '+3902123456', 'access_token' => 'token', 'is_active' => true,
            'auto_reply_enabled' => true, 'internal_test_only' => true, 'allowed_recipients' => ['393331234567'],
        ]);
        app(TenantContext::class)->clear();

        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'waba-1',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'metadata' => ['display_phone_number' => '3902123456', 'phone_number_id' => 'phone-id-1'],
                        'contacts' => [['profile' => ['name' => 'Anna'], 'wa_id' => '393331234567']],
                        'messages' => [[
                            'from' => '393331234567', 'id' => 'wamid.inbound-1',
                            'timestamp' => (string) now()->timestamp, 'type' => 'text',
                            'text' => ['body' => 'Vorrei informazioni sui vostri servizi'],
                        ]],
                    ],
                ]],
            ]],
        ];
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = 'sha256='.hash_hmac('sha256', $json, 'app-secret');

        $this->call('POST', '/api/v1/whatsapp/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => $signature,
        ], $json)->assertOk()->assertJson(['received' => true, 'stored' => 1]);

        $processing = app(ProcessWhatsappMessages::class)->handle();
        $sending = app(RunConversationAutomation::class)->handle();

        $this->assertSame(1, $processing['processed']);
        $this->assertSame(1, $processing['drafts']);
        $this->assertSame(1, $sending['sent']);
        $this->assertDatabaseHas('leads', ['organization_id' => $organization->id, 'name' => 'Anna', 'source_label' => 'whatsapp']);
        $this->assertDatabaseHas('lead_replies', ['organization_id' => $organization->id, 'channel' => 'whatsapp', 'status' => 'sent']);
        $this->assertDatabaseHas('whatsapp_messages', ['external_message_id' => 'wamid.outbound-1', 'direction' => 'outbound']);
        $this->assertSame(2, WhatsappMessage::withoutGlobalScopes()->count());
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/phone-id-1/messages')
            && $request['to'] === '393331234567');
    }
}
