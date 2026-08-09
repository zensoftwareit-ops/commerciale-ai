<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Services\Leads\CreateLead;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TenantIsolationTest extends CommercialeAiTestCase
{
    use RefreshDatabase;

    public function test_a_user_cannot_see_another_tenants_lead(): void
    {
        [$first, $firstUser] = $this->organizationWithUser();
        [$second] = $this->organizationWithUser();
        app(TenantContext::class)->set($second);
        $foreignLead = app(CreateLead::class)->handle(['name' => 'Lead riservato', 'source_label' => 'manual']);
        app(TenantContext::class)->clear();

        $this->actingAs($firstUser)->withSession(['organization_id' => $first->id])->get(route('leads.show', $foreignLead->id))->assertNotFound();
        app(TenantContext::class)->set($first);
        $this->assertSame(0, Lead::query()->whereKey($foreignLead->id)->count());
    }
}
