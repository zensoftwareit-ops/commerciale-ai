<?php

namespace Tests\Feature;

use App\Models\OrganizationSetting;
use App\Services\Leads\CreateLead;
use App\Services\Privacy\PurgeExpiredLeadData;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PrivacyControlsTest extends CommercialeAiTestCase
{
    use RefreshDatabase;

    public function test_owner_can_export_organization_data_without_mailbox_passwords(): void
    {
        [$organization, $owner] = $this->organizationWithUser();
        $this->mailboxFor($organization);
        app(TenantContext::class)->set($organization);
        app(CreateLead::class)->handle(['name' => 'Mario Rossi', 'email' => 'mario@example.test', 'source_label' => 'test']);
        app(TenantContext::class)->clear();

        $response = $this->actingAs($owner)->withSession(['organization_id' => $organization->id])
            ->get(route('account.data-export'))
            ->assertOk()
            ->assertHeader('content-type', 'application/json; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('Mario Rossi', $content);
        $this->assertStringNotContainsString('"password"', $content);
    }

    public function test_retention_deletes_only_closed_expired_leads_when_enabled(): void
    {
        [$organization] = $this->organizationWithUser();
        app(TenantContext::class)->set($organization);
        OrganizationSetting::create(['data_retention_days' => 30, 'privacy_cleanup_enabled' => true]);
        $expired = app(CreateLead::class)->handle(['name' => 'Chiuso vecchio', 'email' => 'old@example.test', 'source_label' => 'test']);
        $expired->update(['operational_status' => 'closed']);
        $expired->forceFill(['updated_at' => now()->subDays(31)])->saveQuietly();
        $active = app(CreateLead::class)->handle(['name' => 'Attivo vecchio', 'email' => 'active@example.test', 'source_label' => 'test']);
        $active->forceFill(['updated_at' => now()->subDays(31)])->saveQuietly();
        app(TenantContext::class)->clear();

        $stats = app(PurgeExpiredLeadData::class)->handle();

        $this->assertSame(1, $stats['deleted']);
        $this->assertDatabaseMissing('leads', ['id' => $expired->id]);
        $this->assertDatabaseHas('leads', ['id' => $active->id]);
    }
}
