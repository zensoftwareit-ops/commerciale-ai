<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use App\Models\UsageRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
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

    public function destroy(Request $request, string $organization): RedirectResponse
    {
        $organization = Organization::query()->findOrFail($organization);
        $data = $request->validate(['confirmation' => ['required', 'string']]);
        if (! hash_equals($organization->name, trim($data['confirmation']))) {
            throw ValidationException::withMessages([
                'confirmation' => 'Scrivi esattamente il nome del cliente per confermare l’eliminazione.',
            ]);
        }
        if ($organization->licenses()->where('source', '!=', 'manual')->exists()) {
            throw ValidationException::withMessages([
                'organization' => 'Il cliente ha una licenza Stripe: annulla prima l’abbonamento dal sistema di fatturazione.',
            ]);
        }

        DB::transaction(function () use ($organization): void {
            $members = $organization->users()->get(['users.id', 'users.email']);
            $organization->licenses()->with('events')->get()->each(function ($license): void {
                $license->events()->delete();
            });
            $organization->delete();

            foreach ($members as $member) {
                $user = User::query()->find($member->id);
                if (! $user || $user->is_super_admin || $user->organizations()->exists() || $user->ownedLicenses()->exists()) {
                    continue;
                }
                DB::table('password_reset_tokens')->where('email', $user->email)->delete();
                if (Schema::hasTable('sessions')) {
                    DB::table('sessions')->where('user_id', $user->id)->delete();
                }
                $user->delete();
            }
        });

        return back()->with('status', 'Cliente eliminato definitivamente con tutti i dati associati.');
    }
}
