<?php

namespace Tests\Feature;

use App\Models\License;
use App\Models\LicenseEvent;
use App\Models\LicensePlan;
use App\Models\Organization;
use App\Models\PipelineStage;
use App\Models\User;
use App\Services\Leads\CreateLead;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class LicensingTest extends CommercialeAiTestCase
{
    use RefreshDatabase;

    public function test_platform_admin_command_creates_a_standalone_account(): void
    {
        $this->assertSame(0, Artisan::call('platform-admin:create', ['email' => 'admin@daria.test']));

        $admin = User::query()->where('email', 'admin@daria.test')->firstOrFail();
        $this->assertTrue($admin->isPlatformAdmin());
        $this->assertSame('admin@daria.test', $admin->mail_from_address);
        $this->assertFalse($admin->organizations()->exists());
        $this->assertFalse($admin->ownedLicenses()->exists());
    }

    public function test_platform_admin_login_goes_directly_to_platform_panel(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true, 'password' => 'PasswordSicura!2026']);

        $this->post('/login', ['email' => $admin->email, 'password' => 'PasswordSicura!2026'])
            ->assertRedirect(route('admin.licensing'));
        $this->get(route('admin.account.edit'))->assertOk()->assertSee('account di piattaforma');
    }

    public function test_billing_api_provisions_an_owner_organization_and_license_idempotently(): void
    {
        Notification::fake();
        config()->set('commerciale-ai.billing.self_service_enabled', true);
        config()->set('commerciale-ai.billing.integration_key', 'test-billing-key');
        LicensePlan::create(['name' => 'Professional', 'slug' => 'professional', 'annual_price_cents' => 99000, 'seat_limit' => 3, 'stripe_price_id' => 'price_test_pro', 'is_active' => true]);
        $payload = ['event_id' => 'evt_test_1', 'event_type' => 'checkout.session.completed', 'external_account_id' => 'wp:site:42', 'email' => 'owner@example.test', 'name' => 'Owner Demo', 'company' => 'Demo Srl', 'plan_slug' => 'professional', 'stripe_price_id' => 'price_test_pro', 'stripe_customer_id' => 'cus_test', 'stripe_subscription_id' => 'sub_test', 'status' => 'active', 'current_period_ends_at' => now()->addYear()->toIso8601String()];

        $this->withToken('test-billing-key')->postJson('/api/v1/billing/provision', $payload)->assertOk()->assertJsonPath('data.plan.slug', 'professional')->assertJsonPath('data.usable', true);
        $this->withToken('test-billing-key')->postJson('/api/v1/billing/provision', $payload)->assertOk();

        $this->assertSame(1, User::query()->where('email', 'owner@example.test')->count());
        $this->assertSame(1, Organization::query()->where('billing_account_ref', 'wp:site:42')->count());
        $this->assertSame(1, License::query()->where('stripe_subscription_id', 'sub_test')->count());
        $this->assertSame(1, LicenseEvent::query()->where('external_event_id', 'evt_test_1')->count());
        $organization = Organization::query()->where('billing_account_ref', 'wp:site:42')->firstOrFail();
        $owner = User::query()->where('email', 'owner@example.test')->firstOrFail();
        $this->assertSame('owner', $owner->roleFor($organization));
    }

    public function test_billing_api_rejects_an_invalid_key(): void
    {
        config()->set('commerciale-ai.billing.self_service_enabled', true);
        config()->set('commerciale-ai.billing.integration_key', 'correct-key');
        $this->withToken('wrong-key')->getJson('/api/v1/billing/plans')->assertUnauthorized();
    }

    public function test_self_service_billing_api_is_hidden_during_manual_phase(): void
    {
        config()->set('commerciale-ai.billing.self_service_enabled', false);
        config()->set('commerciale-ai.billing.integration_key', 'correct-key');

        $this->withToken('correct-key')->getJson('/api/v1/billing/plans')->assertNotFound();
    }

    public function test_super_admin_can_register_a_new_customer_and_activate_a_license(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['is_super_admin' => true]);
        $plan = LicensePlan::create([
            'name' => 'Professional',
            'slug' => 'professional',
            'annual_price_cents' => 99000,
            'seat_limit' => 3,
            'is_active' => true,
        ]);

        $this->actingAs($admin->fresh())->post(route('admin.licenses.store'), [
            'company_name' => 'Nuovo Cliente Srl',
            'owner_name' => 'Mario Rossi',
            'owner_email' => 'mario@nuovo-cliente.test',
            'license_plan_id' => $plan->id,
            'status' => 'active',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $owner = User::query()->where('email', 'mario@nuovo-cliente.test')->firstOrFail();
        $organization = Organization::query()->where('name', 'Nuovo Cliente Srl')->firstOrFail();
        $license = License::query()->where('organization_id', $organization->id)->firstOrFail();

        $this->assertSame('owner', $owner->roleFor($organization));
        $this->assertSame($owner->id, $license->owner_user_id);
        $this->assertSame('manual', $license->source);
        $this->assertSame('active', $license->status);
        $this->assertNotNull($license->current_period_ends_at);
        $this->assertDatabaseHas('organization_settings', ['organization_id' => $organization->id]);
        $this->assertSame(8, PipelineStage::withoutGlobalScopes()->where('organization_id', $organization->id)->count());
    }

    public function test_only_super_admin_can_open_licensing_panel(): void
    {
        [, $user] = $this->organizationWithUser();
        $this->actingAs($user)->get(route('admin.licensing'))->assertForbidden();
        $user->update(['is_super_admin' => true]);
        $this->actingAs($user->fresh())->get(route('admin.licensing'))->assertForbidden();

        $admin = User::factory()->create(['is_super_admin' => true]);
        $this->actingAs($admin)->get(route('admin.licensing'))->assertOk();
    }

    public function test_super_admin_can_open_the_multi_tenant_customer_overview(): void
    {
        [$organization] = $this->organizationWithUser();
        $admin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($admin->fresh())->get(route('admin.organizations.index'))
            ->assertOk()
            ->assertSee($organization->name)
            ->assertSee('Clienti e organizzazioni');
    }

    public function test_platform_admin_cannot_be_added_to_a_customer_organization(): void
    {
        [$organization, $owner] = $this->organizationWithUser();
        $admin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($owner)->withSession(['organization_id' => $organization->id])
            ->post(route('settings.users.store'), [
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => 'sales',
            ])->assertSessionHasErrors('email');

        $this->assertFalse($organization->users()->whereKey($admin->id)->exists());
    }

    public function test_active_plan_limits_new_leads_and_included_users(): void
    {
        config()->set('commerciale-ai.billing.enforcement_enabled', true);
        [$organization, $owner] = $this->organizationWithUser();
        $plan = LicensePlan::create(['name' => 'Starter', 'slug' => 'starter', 'annual_price_cents' => 49000, 'seat_limit' => 1, 'monthly_lead_limit' => 1, 'is_active' => true]);
        License::create(['license_plan_id' => $plan->id, 'organization_id' => $organization->id, 'owner_user_id' => $owner->id, 'key' => 'CAI-TEST-LIMIT', 'status' => 'active', 'source' => 'manual']);
        app(TenantContext::class)->set($organization);
        app(CreateLead::class)->handle(['name' => 'Primo lead', 'source_label' => 'manual']);
        try {
            app(CreateLead::class)->handle(['name' => 'Secondo lead', 'source_label' => 'manual']);
            $this->fail('Il limite lead non è stato applicato.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('license', $exception->errors());
        } finally {
            app(TenantContext::class)->clear();
        }
        $this->actingAs($owner)->withSession(['organization_id' => $organization->id])->post(route('settings.users.store'), ['name' => 'Subutente', 'email' => 'sub@example.test', 'role' => 'sales'])->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('users', ['email' => 'sub@example.test']);
    }
}
