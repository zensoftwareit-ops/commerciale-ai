<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\PipelineStage;
use App\Services\Leads\CreateLead;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;

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

    public function test_tenant_models_are_invisible_when_no_tenant_is_active(): void
    {
        $this->organizationWithUser();

        $this->assertSame(0, PipelineStage::query()->count());
        $this->assertSame(1, PipelineStage::withoutGlobalScopes()->count());
    }

    public function test_a_record_cannot_be_created_for_another_active_tenant(): void
    {
        [$first] = $this->organizationWithUser();
        [$second] = $this->organizationWithUser();
        app(TenantContext::class)->set($first);

        try {
            $this->expectException(LogicException::class);
            PipelineStage::create([
                'organization_id' => $second->id,
                'name' => 'Non autorizzato',
                'slug' => 'forbidden',
                'system_category' => 'open',
                'position' => 99,
            ]);
        } finally {
            app(TenantContext::class)->clear();
        }
    }

    public function test_a_user_can_switch_only_to_an_organization_they_belong_to(): void
    {
        [$first, $user] = $this->organizationWithUser();
        [$second] = $this->organizationWithUser();
        $second->users()->attach($user, ['role' => 'owner']);

        $this->actingAs($user)->withSession(['organization_id' => $first->id])
            ->post(route('organizations.switch', $second))
            ->assertRedirect(route('leads.index'))
            ->assertSessionHas('organization_id', $second->id);

        $foreign = \App\Models\Organization::factory()->create();
        $this->actingAs($user)->post(route('organizations.switch', $foreign))->assertNotFound();
    }
}
