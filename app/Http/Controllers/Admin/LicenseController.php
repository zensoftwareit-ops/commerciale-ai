<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\LicensePlan;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LicenseController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $organization = Organization::query()->findOrFail($data['organization_id']);
        $owner = User::query()->where('email', mb_strtolower($data['owner_email']))->firstOrFail();
        abort_unless($owner->roleFor($organization) === 'owner', 422, 'L’utente deve essere owner dell’organizzazione.');
        License::create([
            ...collect($data)->except('owner_email')->all(), 'owner_user_id' => $owner->id,
            'key' => $this->newKey(), 'source' => 'manual', 'starts_at' => now(),
        ]);
        return back()->with('status', 'Licenza manuale generata.');
    }

    public function update(Request $request, string $license): RedirectResponse
    {
        $license = License::query()->findOrFail($license);
        $data = $request->validate([
            'license_plan_id' => ['required', Rule::exists('license_plans', 'id')],
            'status' => ['required', Rule::in(['active', 'trialing', 'past_due', 'unpaid', 'canceled', 'paused', 'suspended'])],
            'current_period_ends_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date'], 'cancel_at_period_end' => ['nullable', 'boolean'],
        ]);
        $data['cancel_at_period_end'] = $request->boolean('cancel_at_period_end');
        $license->update($data);
        return back()->with('status', 'Licenza aggiornata.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'license_plan_id' => ['required', Rule::exists('license_plans', 'id')], 'organization_id' => ['required', Rule::exists('organizations', 'id')],
            'owner_email' => ['required', 'email', Rule::exists('users', 'email')], 'status' => ['required', Rule::in(['active', 'trialing', 'suspended'])],
            'current_period_ends_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date'],
        ]);
    }

    private function newKey(): string
    {
        do $key = 'CAI-'.Str::upper(Str::random(8).'-'.Str::random(8).'-'.Str::random(8)); while (License::query()->where('key', $key)->exists());
        return $key;
    }
}

