<?php

namespace Tests\Feature;

use App\Models\InboundSource;
use App\Models\License;
use App\Models\LicensePlan;
use App\Models\OrganizationSetting;
use App\Models\User;
use App\Services\Licensing\OrganizationProvisioner;
use App\Services\Organizations\OrganizationLifecycle;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

class OrganizationLifecycleTest extends CommercialeAiTestCase
{
    use RefreshDatabase;

    public function test_a_new_organization_stays_in_onboarding_until_required_setup_is_complete(): void
    {
        $organization = app(OrganizationProvisioner::class)->create('Nuovo cliente');
        $user = User::factory()->create();
        $organization->users()->attach($user, ['role' => 'owner']);
        $plan = LicensePlan::create(['name' => 'Starter', 'slug' => 'starter', 'annual_price_cents' => 49000, 'seat_limit' => 1, 'is_active' => true]);
        License::create(['license_plan_id' => $plan->id, 'organization_id' => $organization->id, 'owner_user_id' => $user->id, 'key' => 'CAI-ONBOARDING', 'status' => 'active', 'source' => 'manual']);

        $this->assertSame('onboarding', app(OrganizationLifecycle::class)->refresh($organization)->status);

        app(TenantContext::class)->run($organization, function (): void {
            OrganizationSetting::query()->update(['completeness' => 100]);
            InboundSource::create(['name' => 'Sito', 'allowed_domains' => ['example.test'], 'endpoint_token_hash' => hash('sha256', 'token'), 'is_active' => true]);
        });

        $organization = app(OrganizationLifecycle::class)->refresh($organization);
        $this->assertSame('active', $organization->status);
        $this->assertNotNull($organization->onboarding_completed_at);
    }

    public function test_license_suspension_blocks_operations_and_reactivation_restores_the_lifecycle(): void
    {
        [$organization, $owner] = $this->organizationWithUser();
        $plan = LicensePlan::create(['name' => 'Starter', 'slug' => 'starter', 'annual_price_cents' => 49000, 'seat_limit' => 1, 'is_active' => true]);
        $license = License::create(['license_plan_id' => $plan->id, 'organization_id' => $organization->id, 'owner_user_id' => $owner->id, 'key' => 'CAI-SUSPEND', 'status' => 'suspended', 'source' => 'manual']);

        app(OrganizationLifecycle::class)->refresh($organization);
        $this->actingAs($owner)->withSession(['organization_id' => $organization->id])->get(route('leads.index'))->assertRedirect(route('onboarding'));
        $this->actingAs($owner)->withSession(['organization_id' => $organization->id])->get(route('onboarding'))->assertOk()->assertSee('Workspace sospeso');

        $license->update(['status' => 'active']);
        $this->assertNotSame('suspended', app(OrganizationLifecycle::class)->refresh($organization)->status);
    }
}
