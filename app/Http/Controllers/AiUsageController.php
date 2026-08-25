<?php

namespace App\Http\Controllers;

use App\Models\UsageRecord;
use App\Services\Ai\AiUsageSummary;
use App\Support\Tenancy\TenantContext;
use Illuminate\View\View;

class AiUsageController extends Controller
{
    public function __invoke(TenantContext $tenants, AiUsageSummary $summaries): View
    {
        $organization = $tenants->requireOrganization();
        $summary = $summaries->forOrganization($organization);
        $operations = UsageRecord::query()
            ->whereBetween('occurred_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->selectRaw('operation, COUNT(*) AS requests, SUM(input_units) AS input_tokens, SUM(output_units) AS output_tokens, SUM(estimated_cost) AS estimated_cost')
            ->groupBy('operation')
            ->orderByDesc('requests')
            ->get();
        $recent = UsageRecord::query()->latest('occurred_at')->limit(30)->get();

        return view('usage.index', compact('organization', 'summary', 'operations', 'recent'));
    }
}
