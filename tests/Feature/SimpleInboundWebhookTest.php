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
        [$source, $token] = $this->source($organization, ['azienda.example']);
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
        $this->withHeader('Origin', 'https://www.azienda.example')->postJson($url, $payload)
            ->assertCreated()
            ->assertJson(['status' => 'created', 'domain_validation' => 'origin_header']);
        $this->withHeader('Origin', 'https://azienda.example')->postJson($url, $payload)
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
        $this->assertSame('www.azienda.example', $receipt->source_domain);
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

    public function test_it_preserves_wpforms_caption_values_from_a_data_object(): void
    {
        [$organization, $owner] = $this->organizationWithUser();
        [, $token] = $this->source($organization, ['example.com']);
        $payload = [
            'form_title' => 'Richiesta mezzo',
            'entry_id' => 'wpforms-72',
            'data' => [
                'Nome e cognome' => 'Federico Ghigna',
                'Email' => 'federico@example.test',
                'Quale mezzo ti occorre?' => 'Ape Lineare',
                'Per quanti giorni ti occorre il mezzo?' => '4',
                'Dal giorno' => '12-10-2026',
                'Quali servizi ti occorrono?' => "Decorazione parziale\nLogistica\nTrasporto",
                'Quale località deve raggiungere il mezzo?' => 'Cremona',
                'Campo condizionale non compilato' => '',
            ],
        ];

        $response = $this->postJson("/api/v1/inbound/leads/{$token}", $payload)->assertCreated();
        $lead = Lead::withoutGlobalScopes()->sole();
        $this->assertSame('Federico Ghigna', $lead->name);
        $this->assertSame('federico@example.test', $lead->email);
        $this->assertSame('Ape Lineare', $lead->requested_service);
        $this->assertSame('Ape Lineare', $lead->request_data['Quale mezzo ti occorre?']);
        $this->assertSame('4', $lead->request_data['Per quanti giorni ti occorre il mezzo?']);
        $this->assertSame('12-10-2026', $lead->request_data['Dal giorno']);
        $this->assertSame("Decorazione parziale\nLogistica\nTrasporto", $lead->request_data['Quali servizi ti occorrono?']);
        $this->assertSame('Cremona', $lead->request_data['Quale località deve raggiungere il mezzo?']);
        $this->assertArrayHasKey('Campo condizionale non compilato', $lead->request_data);
        $this->assertNull($lead->request_data['Campo condizionale non compilato']);
        $this->assertArrayNotHasKey('data', $lead->request_data);

        $this->actingAs($owner)->withSession(['organization_id' => $organization->id])
            ->get(route('leads.show', $response->json('lead_id')))
            ->assertOk()->assertSee('Ape Lineare')->assertSee('Cremona')
            ->assertSee('Decorazione parziale')->assertSee('Logistica')->assertSee('Trasporto')
            ->assertSee('request-field-value', false)
            ->assertSeeInOrder(['Quale Mezzo Ti Occorre?', 'Ape Lineare']);
    }

    public function test_it_preserves_wpforms_label_value_field_lists_and_json_wrappers(): void
    {
        [$organization] = $this->organizationWithUser();
        [, $token] = $this->source($organization, ['example.com']);
        $fields = [
            ['name' => 'Nome e cognome', 'value' => 'Mario Rossi'],
            ['label' => 'Email', 'value' => 'mario@example.test'],
            ['caption' => 'Quale mezzo ti occorre?', 'answer' => 'Truck vela'],
            ['label' => 'Servizi', 'values' => ['Decorazione', 'Trasporto']],
        ];

        $this->postJson("/api/v1/inbound/leads/{$token}", ['fields' => json_encode($fields, JSON_THROW_ON_ERROR)])
            ->assertCreated();
        $lead = Lead::withoutGlobalScopes()->sole();
        $this->assertSame('Mario Rossi', $lead->name);
        $this->assertSame('mario@example.test', $lead->email);
        $this->assertSame('Truck vela', $lead->requested_service);
        $this->assertSame(['Decorazione', 'Trasporto'], $lead->request_data['Servizi']);
        $this->assertArrayNotHasKey('fields', $lead->request_data);
    }

    private function source($organization, array $domains): array
    {
        $token = str_repeat('a', 32).bin2hex(random_bytes(16));
        app(TenantContext::class)->set($organization);
        $source = InboundSource::create([
            'name' => 'Test source',
            'allowed_domains' => $domains,
            'endpoint_token_hash' => hash('sha256', $token),
            'is_active' => true,
        ]);
        app(TenantContext::class)->clear();

        return [$source, $token];
    }
}
