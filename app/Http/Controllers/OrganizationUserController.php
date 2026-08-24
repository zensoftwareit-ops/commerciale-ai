<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrganizationUserController extends Controller
{
    public function index(): View
    {
        $organization = app(TenantContext::class)->requireOrganization();
        $members = $organization->users()->orderBy('name')->get();
        $license = $organization->activeLicense();
        return view('settings.users', compact('organization', 'members', 'license'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255'], 'role' => ['required', Rule::in(['sales', 'viewer'])]]);
        $organization = app(TenantContext::class)->requireOrganization();
        $license = $organization->activeLicense();
        if ($license && $organization->users()->count() >= $license->plan->seat_limit) return back()->withErrors(['email' => 'Il pacchetto ha raggiunto il limite di '.$license->plan->seat_limit.' utenti.']);
        $email = mb_strtolower(trim($data['email']));
        $user = User::query()->where('email', $email)->first(); $created = false;
        if (! $user) { $user = User::create(['name' => $data['name'], 'email' => $email, 'password' => Str::password(32)]); $created = true; }
        if ($organization->users()->whereKey($user->id)->exists()) return back()->withErrors(['email' => 'L’utente appartiene già a questa organizzazione.']);
        $organization->users()->attach($user, ['role' => $data['role']]);
        if ($created) Password::sendResetLink(['email' => $email]);
        return back()->with('status', $created ? 'Sottoutente creato. Ha ricevuto il link per impostare la password.' : 'Utente collegato all’organizzazione.');
    }

    public function destroy(string $user): RedirectResponse
    {
        $organization = app(TenantContext::class)->requireOrganization();
        $member = $organization->users()->whereKey($user)->firstOrFail();
        abort_if($member->pivot->role === 'owner', 422, 'L’owner della licenza non può essere rimosso.');
        $organization->users()->detach($member->id);
        return back()->with('status', 'Sottoutente rimosso dall’organizzazione.');
    }
}

