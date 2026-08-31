<?php

namespace App\Services\Privacy;

use App\Models\Lead;
use App\Models\Organization;
use App\Models\OrganizationSetting;
use App\Services\Leads\DeleteLead;
use App\Support\Tenancy\TenantContext;

class PurgeExpiredLeadData
{
    public function __construct(private readonly DeleteLead $deleter) {}

    /** @return array{organizations:int,candidates:int,deleted:int} */
    public function handle(bool $dryRun = false): array
    {
        $stats = ['organizations' => 0, 'candidates' => 0, 'deleted' => 0];
        foreach (Organization::query()->cursor() as $organization) {
            app(TenantContext::class)->set($organization);
            try {
                $settings = OrganizationSetting::query()->first();
                if (! $settings?->privacy_cleanup_enabled) {
                    continue;
                }
                $stats['organizations']++;
                $cutoff = now()->subDays(max(30, (int) $settings->data_retention_days));
                $query = Lead::query()->where('operational_status', 'closed')->where('updated_at', '<', $cutoff);
                $stats['candidates'] += (clone $query)->count();
                if ($dryRun) {
                    continue;
                }
                $query->orderBy('id')->chunkById(100, function ($leads) use (&$stats): void {
                    foreach ($leads as $lead) {
                        $this->deleter->handle($lead);
                        $stats['deleted']++;
                    }
                }, 'id');
            } finally {
                app(TenantContext::class)->clear();
            }
        }

        return $stats;
    }
}
