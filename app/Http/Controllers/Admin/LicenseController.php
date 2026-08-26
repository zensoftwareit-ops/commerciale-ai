<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\LicensePlan;
use App\Models\Organization;
use App\Models\User;
use App\Services\Licensing\CreateManualLicensedCustomer;
use App\Services\Licensing\SendLicenseActivation;
use App\Services\Organizations\OrganizationLifecycle;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class LicenseController extends Controller
{
    public function store(Request $request, CreateManualLicensedCustomer $creator): RedirectResponse
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:160'],
            'owner_name' => ['required', 'string', 'max:160'],
            'owner_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'license_plan_id' => ['required', Rule::exists('license_plans', 'id')],
            'status' => ['required', Rule::in(['active', 'trialing', 'suspended'])],
            'current_period_ends_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
        ]);
        $result = $creator->handle($data);

        $message = 'Cliente registrato e licenza attivata.';
        $message .= $result['reset_link_sent']
            ? ' L\'email per impostare la password è stata affidata al servizio di posta.'
            : ' L\'email di invito non è partita: verifica SMTP oppure usa “Password dimenticata”.';

        return back()->with('status', $message);
    }

    public function storeExisting(Request $request, OrganizationLifecycle $lifecycle): RedirectResponse
    {
        $data = $this->validatedExisting($request);
        $organization = Organization::query()->findOrFail($data['organization_id']);
        $owner = User::query()->where('email', mb_strtolower($data['owner_email']))->firstOrFail();
        abort_if($owner->is_super_admin, 422, 'L\'account amministrativo della piattaforma non può possedere licenze cliente.');
        abort_unless($owner->roleFor($organization) === 'owner', 422, 'L’utente deve essere owner dell’organizzazione.');
        $data['current_period_ends_at'] ??= now()->addYear();
        License::create([
            ...collect($data)->except('owner_email')->all(), 'owner_user_id' => $owner->id,
            'key' => $this->newKey(), 'source' => 'manual', 'starts_at' => now(),
        ]);
        $lifecycle->refresh($organization);
        return back()->with('status', 'Licenza manuale generata.');
    }

    public function update(Request $request, string $license, OrganizationLifecycle $lifecycle): RedirectResponse
    {
        $license = License::query()->findOrFail($license);
        $data = $request->validate([
            'license_plan_id' => ['required', Rule::exists('license_plans', 'id')],
            'status' => ['required', Rule::in(['active', 'trialing', 'past_due', 'unpaid', 'canceled', 'paused', 'suspended'])],
            'current_period_ends_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date'], 'cancel_at_period_end' => ['nullable', 'boolean'],
        ]);
        $data['cancel_at_period_end'] = $request->boolean('cancel_at_period_end');
        $license->update($data);
        $lifecycle->refresh($license->organization);
        return back()->with('status', 'Licenza aggiornata.');
    }

    public function resendActivation(string $license, SendLicenseActivation $activation): RedirectResponse
    {
        $license = License::query()->with('owner')->findOrFail($license);

        try {
            $activation->handle($license->owner);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['mail' => 'Email non inviata: '.$exception->getMessage()]);
        }

        return back()->with('status', 'Email di attivazione reinviata a '.$license->owner->email.'.');
    }

    public function renew(string $license, OrganizationLifecycle $lifecycle): RedirectResponse
    {
        $license = License::query()->findOrFail($license);
        $this->ensureManual($license);
        $renewFrom = $license->current_period_ends_at?->isFuture()
            ? $license->current_period_ends_at->copy()
            : now();
        $license->update([
            'status' => 'active',
            'current_period_ends_at' => $renewFrom->addYear(),
            'ends_at' => null,
            'cancel_at_period_end' => false,
        ]);
        $lifecycle->refresh($license->organization);

        return back()->with('status', 'Licenza rinnovata per 12 mesi.');
    }

    public function suspend(string $license, OrganizationLifecycle $lifecycle): RedirectResponse
    {
        $license = License::query()->findOrFail($license);
        $this->ensureManual($license);
        $license->update(['status' => 'suspended']);
        $lifecycle->refresh($license->organization);

        return back()->with('status', 'Licenza disabilitata. Il cliente non può più usare il software.');
    }

    public function activate(string $license, OrganizationLifecycle $lifecycle): RedirectResponse
    {
        $license = License::query()->findOrFail($license);
        $this->ensureManual($license);
        if ($license->current_period_ends_at && ! $license->current_period_ends_at->isFuture()) {
            throw ValidationException::withMessages([
                'license' => 'La licenza è scaduta: usa “Rinnova 12 mesi” per riattivarla.',
            ]);
        }
        $license->update(['status' => 'active']);
        $lifecycle->refresh($license->organization);

        return back()->with('status', 'Licenza riattivata.');
    }

    public function destroy(string $license, OrganizationLifecycle $lifecycle): RedirectResponse
    {
        $license = License::query()->findOrFail($license);
        $this->ensureManual($license);
        $organization = $license->organization;
        DB::transaction(function () use ($license): void {
            $license->events()->delete();
            $license->delete();
        });
        $organization = $lifecycle->refresh($organization);

        return back()->with('status', $organization->status === 'suspended'
            ? 'Licenza eliminata definitivamente. Il cliente è stato sospeso.'
            : 'Licenza eliminata definitivamente. Il cliente conserva un’altra licenza utilizzabile.');
    }

    private function validatedExisting(Request $request): array
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

    private function ensureManual(License $license): void
    {
        if ($license->source !== 'manual') {
            throw ValidationException::withMessages([
                'license' => 'Le licenze collegate a Stripe devono essere gestite dal sistema di fatturazione.',
            ]);
        }
    }
}
