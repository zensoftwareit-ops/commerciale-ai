<?php

namespace App\Services\Ai;

use App\Models\Organization;
use App\Models\UsageRecord;
use App\Support\Tenancy\TenantContext;

class AiUsageSummary
{
    public function __construct(private readonly TenantContext $tenants) {}

    /** @return array{input_tokens:int,output_tokens:int,total_tokens:int,estimated_cost:float,limit:?int,remaining:?int,percentage:?int,period_start:string,period_end:string} */
    public function forOrganization(Organization $organization): array
    {
        return $this->tenants->run($organization, function () use ($organization): array {
            $usage = UsageRecord::query()
                ->whereBetween('occurred_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->selectRaw('COALESCE(SUM(input_units), 0) AS input_tokens')
                ->selectRaw('COALESCE(SUM(output_units), 0) AS output_tokens')
                ->selectRaw('COALESCE(SUM(estimated_cost), 0) AS estimated_cost')
                ->first();
            $input = (int) $usage->input_tokens;
            $output = (int) $usage->output_tokens;
            $total = $input + $output;
            $limit = $organization->activeLicense()?->plan?->monthly_ai_token_limit;

            return [
                'input_tokens' => $input,
                'output_tokens' => $output,
                'total_tokens' => $total,
                'estimated_cost' => (float) $usage->estimated_cost,
                'limit' => $limit === null ? null : (int) $limit,
                'remaining' => $limit === null ? null : max(0, (int) $limit - $total),
                'percentage' => $limit === null ? null : min(100, (int) floor(($total / max(1, (int) $limit)) * 100)),
                'period_start' => now()->startOfMonth()->toDateString(),
                'period_end' => now()->endOfMonth()->toDateString(),
            ];
        });
    }
}
