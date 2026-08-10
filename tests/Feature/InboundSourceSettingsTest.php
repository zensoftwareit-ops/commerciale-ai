<?php

namespace Tests\Feature;

use App\Models\InboundSource;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

class InboundSourceSettingsTest extends CommercialeAiTestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_and_rotate_webhook_credentials(): void
    {
        [$organization, $owner] = $this->organizationWithUser();

        $response = $this->actingAs($owner)->post('/settings/sources', ['name' => 'Sito pilota', 'allowed_domains_text' => "example.it\nwww.example.it"]);
        $response->assertRedirect()->assertSessionHas('webhook_credentials');

        app(TenantContext::class)->set($organization);
        $source = InboundSource::query()->sole();
        $oldSecret = $source->secret;
        $oldEndpointHash = $source->endpoint_token_hash;
        $this->assertSame(['example.it', 'www.example.it'], $source->allowed_domains);
        app(TenantContext::class)->clear();

        $this->actingAs($owner)->patch("/settings/sources/{$source->id}/rotate-endpoint")
            ->assertRedirect()
            ->assertSessionHas('webhook_credentials');
        $this->assertNotSame($oldEndpointHash, $source->fresh()->endpoint_token_hash);

        $this->actingAs($owner)->patch("/settings/sources/{$source->id}/rotate")
            ->assertRedirect()
            ->assertSessionHas('webhook_credentials');

        $this->assertNotSame($oldSecret, $source->fresh()->secret);
    }

    public function test_sales_user_cannot_manage_sources(): void
    {
        [, $sales] = $this->organizationWithUser('sales');

        $this->actingAs($sales)->get('/settings/sources')->assertForbidden();
        $this->actingAs($sales)->post('/settings/sources', ['name' => 'Denied'])->assertForbidden();
    }
}
