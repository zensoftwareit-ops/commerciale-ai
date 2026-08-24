<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\PipelineStage;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Tests\TestCase;

abstract class CommercialeAiTestCase extends TestCase
{
    protected function organizationWithUser(string $role = 'owner'): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $organization->users()->attach($user, ['role' => $role]);
        app(TenantContext::class)->set($organization);
        PipelineStage::create(['name' => 'Nuovo', 'slug' => 'new', 'system_category' => 'open', 'position' => 1]);
        app(TenantContext::class)->clear();

        return [$organization, $user];
    }
}
