<?php

namespace Tests\Feature;

use App\Models\AiRun;
use App\Models\CommercialNotification;
use App\Models\License;
use App\Models\LicensePlan;
use App\Models\UsageRecord;
use App\Services\Ai\RecordAiUsage;
use App\Services\Licensing\LicenseUsageGuard;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

class AiUsageControlTest extends CommercialeAiTestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_current_month_ai_usage(): void
    {
        [$organization, $owner] = $this->organizationWithUser();
        $this->license($organization, $owner, 1000);
        app(TenantContext::class)->set($organization);
        $run = AiRun::create(['lead_id' => null, 'operation' => 'lead_analysis', 'status' => 'completed', 'started_at' => now()]);
        UsageRecord::create(['ai_run_id' => $run->id, 'operation' => 'lead_analysis', 'provider' => 'openai', 'model' => 'test', 'input_units' => 400, 'output_units' => 100, 'estimated_cost' => .0123, 'occurred_at' => now()]);
        app(TenantContext::class)->clear();

        $this->actingAs($owner)->withSession(['organization_id' => $organization->id])
            ->get(route('usage.index'))->assertOk()->assertSee('500')->assertSee('1.000');
    }

    public function test_configured_ai_budget_is_enforced_even_during_manual_billing_phase(): void
    {
        config()->set('commerciale-ai.billing.enforcement_enabled', false);
        [$organization, $owner] = $this->organizationWithUser();
        $this->license($organization, $owner, 100);
        app(TenantContext::class)->set($organization);
        $run = AiRun::create(['operation' => 'lead_analysis', 'status' => 'completed', 'started_at' => now()]);
        UsageRecord::create(['ai_run_id' => $run->id, 'operation' => 'lead_analysis', 'provider' => 'openai', 'model' => 'test', 'input_units' => 80, 'output_units' => 20, 'estimated_cost' => .01, 'occurred_at' => now()]);

        $this->expectException(ValidationException::class);
        app(LicenseUsageGuard::class)->assertAiCapacity();
    }

    public function test_owner_is_notified_once_when_usage_crosses_eighty_percent(): void
    {
        [$organization, $owner] = $this->organizationWithUser();
        $this->license($organization, $owner, 1000);
        app(TenantContext::class)->set($organization);
        $run = AiRun::create(['operation' => 'lead_analysis', 'status' => 'running', 'started_at' => now()]);

        app(RecordAiUsage::class)->handle($run, 'lead_analysis', ['provider' => 'openai', 'model' => 'test', 'input_units' => 700, 'output_units' => 150, 'estimated_cost' => .02]);
        app(RecordAiUsage::class)->handle($run, 'lead_analysis', ['provider' => 'openai', 'model' => 'test', 'input_units' => 700, 'output_units' => 150, 'estimated_cost' => .02]);

        $this->assertSame(1, CommercialNotification::query()->where('type', 'ai_budget_80')->count());
    }

    private function license($organization, $owner, int $limit): License
    {
        $plan = LicensePlan::create(['name' => 'Starter', 'slug' => 'starter-'.$limit, 'annual_price_cents' => 49000, 'seat_limit' => 1, 'monthly_ai_token_limit' => $limit, 'is_active' => true]);

        return License::create(['license_plan_id' => $plan->id, 'organization_id' => $organization->id, 'owner_user_id' => $owner->id, 'key' => 'CAI-'.$limit, 'status' => 'active', 'source' => 'manual']);
    }
}
