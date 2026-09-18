<?php

namespace Tests\Feature;

use App\Models\AiRun;
use App\Models\InboundSource;
use App\Models\KnowledgeDocument;
use App\Models\License;
use App\Models\LicensePlan;
use App\Models\MailboxAccount;
use App\Models\OrganizationSetting;
use App\Models\PipelineStage;
use App\Models\PricingRule;
use App\Models\UsageRecord;
use App\Models\User;
use App\Services\Leads\CreateLead;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

class OrganizationWorkspaceResetTest extends CommercialeAiTestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_reset_a_workspace_without_deleting_owner_or_license(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        [$organization, $owner] = $this->organizationWithUser();
        $member = User::factory()->create();
        $organization->users()->attach($member, ['role' => 'sales']);
        $plan = LicensePlan::create(['name' => 'Starter', 'slug' => 'reset-starter', 'annual_price_cents' => 49000, 'seat_limit' => 2, 'is_active' => true]);
        $license = License::create([
            'license_plan_id' => $plan->id, 'organization_id' => $organization->id,
            'owner_user_id' => $owner->id, 'key' => 'DAR-RESET-TEST', 'status' => 'active',
            'source' => 'manual', 'starts_at' => now(), 'current_period_ends_at' => now()->addYear(),
        ]);

        app(TenantContext::class)->run($organization, function () use ($owner): void {
            OrganizationSetting::create(['commercial_name' => 'Cliente configurato', 'completeness' => 100]);
            KnowledgeDocument::create(['updated_by' => $owner->id, 'title' => 'Servizi', 'type' => 'service', 'content' => str_repeat('Contenuto aziendale. ', 10), 'status' => 'active']);
            PricingRule::create(['name' => 'Servizio', 'keywords' => ['servizio'], 'minimum_price' => 100, 'maximum_price' => 200, 'is_active' => true]);
            InboundSource::create(['name' => 'Sito', 'allowed_domains' => ['example.test'], 'endpoint_token_hash' => hash('sha256', 'reset-token'), 'is_active' => true]);
            MailboxAccount::create(['name' => 'Email Daria', 'from_address' => 'daria@example.test', 'from_name' => 'Daria', 'host' => 'imap.example.test', 'port' => 993, 'username' => 'daria@example.test', 'password' => 'secret', 'is_active' => true]);
            app(CreateLead::class)->handle(['name' => 'Lead da eliminare', 'email' => 'lead@example.test', 'source_label' => 'manual']);
            $run = AiRun::create(['operation' => 'setup_wizard', 'status' => 'completed', 'started_at' => now(), 'completed_at' => now()]);
            UsageRecord::create(['ai_run_id' => $run->id, 'operation' => 'setup_wizard', 'provider' => 'fake', 'model' => 'fake', 'occurred_at' => now()]);
        });
        $testFile = storage_path('app/private/organizations/'.$organization->id.'/quotations/test.pdf');
        File::ensureDirectoryExists(dirname($testFile));
        File::put($testFile, 'pdf');

        $this->actingAs($admin)->delete(route('admin.organizations.reset', $organization), [
            'confirmation' => 'RESET '.$organization->name,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['id' => $owner->id]);
        $this->assertDatabaseHas('organizations', ['id' => $organization->id, 'status' => 'onboarding']);
        $this->assertDatabaseHas('licenses', ['id' => $license->id, 'status' => 'active']);
        $this->assertDatabaseHas('organization_user', ['organization_id' => $organization->id, 'user_id' => $owner->id, 'role' => 'owner']);
        $this->assertDatabaseMissing('organization_user', ['organization_id' => $organization->id, 'user_id' => $member->id]);
        $this->assertSame(0, $organization->leads()->withoutGlobalScopes()->count());
        $this->assertDatabaseMissing('knowledge_documents', ['organization_id' => $organization->id]);
        $this->assertDatabaseMissing('pricing_rules', ['organization_id' => $organization->id]);
        $this->assertDatabaseMissing('inbound_sources', ['organization_id' => $organization->id]);
        $this->assertDatabaseMissing('mailbox_accounts', ['organization_id' => $organization->id]);
        $this->assertDatabaseMissing('ai_runs', ['organization_id' => $organization->id]);
        $this->assertDatabaseMissing('usage_records', ['organization_id' => $organization->id]);
        $this->assertDatabaseHas('organization_settings', ['organization_id' => $organization->id, 'completeness' => 0]);
        $this->assertSame(8, PipelineStage::withoutGlobalScopes()->where('organization_id', $organization->id)->count());
        $this->assertFileDoesNotExist($testFile);
    }

    public function test_reset_requires_the_exact_customer_name(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        [$organization, $owner] = $this->organizationWithUser();

        $this->actingAs($admin)->delete(route('admin.organizations.reset', $organization), [
            'confirmation' => 'RESET cliente sbagliato',
        ])->assertSessionHasErrors('confirmation');

        $this->assertDatabaseHas('organizations', ['id' => $organization->id]);
        $this->assertDatabaseHas('organization_user', ['organization_id' => $organization->id, 'user_id' => $owner->id]);
    }
}
