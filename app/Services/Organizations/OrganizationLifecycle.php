<?php

namespace App\Services\Organizations;

use App\Models\Organization;
use App\Support\Tenancy\TenantContext;

class OrganizationLifecycle
{
    public function __construct(
        private readonly TenantContext $tenants,
        private readonly SetupReadiness $readiness,
    ) {}

    /** @return array{status:string,progress:int,ready:bool,license:bool,profile:bool,source:bool,mailbox:bool,missing:list<string>} */
    public function snapshot(Organization $organization): array
    {
        return $this->readiness->assess($organization);
    }

    public function refresh(Organization $organization): Organization
    {
        $organization->refresh();
        $latestLicense = $organization->licenses()->latest('created_at')->first();
        $usableLicense = $latestLicense?->isUsable() ? $latestLicense : null;

        if (! $latestLicense) {
            $organization->update([
                'status' => 'suspended',
                'suspended_at' => $organization->suspended_at ?? now(),
                'suspension_reason' => 'license_missing',
            ]);

            return $organization->refresh();
        }

        if ($latestLicense && ! $usableLicense) {
            $organization->update([
                'status' => 'suspended',
                'suspended_at' => $organization->suspended_at ?? now(),
                'suspension_reason' => 'license_'.$latestLicense->status,
            ]);

            return $organization->refresh();
        }

        if ($usableLicense) {
            $ready = $this->snapshot($organization)['ready'];
            $organization->update([
                'status' => $ready ? 'active' : 'onboarding',
                'onboarding_completed_at' => $ready ? ($organization->onboarding_completed_at ?? now()) : $organization->onboarding_completed_at,
                'suspended_at' => null,
                'suspension_reason' => null,
            ]);
        }

        return $organization->refresh();
    }
}
