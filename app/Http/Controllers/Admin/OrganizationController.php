<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\UsageRecord;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function index(): View
    {
        $organizations = Organization::query()
            ->with([
                'users',
                'licenses.plan',
                'settings' => fn ($query) => $query->withoutGlobalScopes(),
            ])
            ->withCount([
                'leads as leads_count' => fn ($query) => $query->withoutGlobalScopes(),
                'mailboxes as active_mailboxes_count' => fn ($query) => $query->withoutGlobalScopes()->where('is_active', true),
            ])
            ->orderBy('name')
            ->paginate(30);

        $usage = UsageRecord::withoutGlobalScopes()
            ->whereIn('organization_id', $organizations->getCollection()->pluck('id'))
            ->where('occurred_at', '>=', now()->startOfMonth())
            ->selectRaw('organization_id, SUM(input_units + output_units) AS total_tokens, SUM(estimated_cost) AS estimated_cost')
            ->groupBy('organization_id')
            ->get()
            ->keyBy('organization_id');

        $organizations->getCollection()->each(function (Organization $organization) use ($usage): void {
            $current = $usage->get($organization->id);
            $organization->setAttribute('current_month_ai_tokens', (int) ($current?->total_tokens ?? 0));
            $organization->setAttribute('current_month_ai_cost', (float) ($current?->estimated_cost ?? 0));
        });

        return view('admin.organizations.index', compact('organizations'));
    }
}
