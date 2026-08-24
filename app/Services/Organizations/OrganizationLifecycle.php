<?php

namespace App\Services\Organizations;

use App\Models\InboundSource;
use App\Models\MailboxAccount;
use App\Models\Organization;
use App\Models\OrganizationSetting;
use App\Support\Tenancy\TenantContext;

class OrganizationLifecycle
{
    public function __construct(private readonly TenantContext $tenants) {}

    /** @return array{status:string,progress:int,ready:bool,license:bool,profile:bool,source:bool,mailbox:bool,missing:list<string>} */
    public function snapshot(Organization $organization): array
    {
        return $this->tenants->run($organization, function () use ($organization): array {
            $checks = [
                'license' => (bool) $organization->activeLicense(),
                'profile' => (int) (OrganizationSetting::query()->value('completeness') ?? 0) >= 100,
                'source' => InboundSource::query()->where('is_active', true)->exists(),
                'mailbox' => MailboxAccount::query()->where('is_active', true)->exists(),
            ];
            $required = ['license', 'profile', 'source'];
            $labels = ['license' => 'Licenza attiva', 'profile' => 'Profilo aziendale completo', 'source' => 'Sorgente lead attiva'];
            $done = count(array_filter($required, fn (string $key): bool => $checks[$key]));

            return [
                'status' => $organization->status,
                'progress' => (int) round(($done / count($required)) * 100),
                'ready' => $done === count($required),
                ...$checks,
                'missing' => array_values(array_map(
                    fn (string $key): string => $labels[$key],
                    array_filter($required, fn (string $key): bool => ! $checks[$key]),
                )),
            ];
        });
    }

    public function refresh(Organization $organization): Organization
    {
        $organization->refresh();
        $latestLicense = $organization->licenses()->latest('created_at')->first();
        $usableLicense = $latestLicense?->isUsable() ? $latestLicense : null;

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
