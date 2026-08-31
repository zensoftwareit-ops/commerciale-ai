<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Credenziali non valide.'])->onlyInput('email');
        }
        $request->session()->regenerate();

        $user = $request->user();
        if ($user->isPlatformAdmin()) {
            $request->session()->forget('organization_id');
            $request->session()->forget('platform_2fa_verified_at');
            if (! $user->two_factor_confirmed_at && config('commerciale-ai.security.platform_2fa_required')) {
                return redirect()->route('admin.two-factor.enroll')->withErrors(['two_factor' => 'Configura l’autenticazione a due fattori per proteggere il pannello amministrativo.']);
            }
            if ($user->two_factor_confirmed_at) {
                return redirect()->route('admin.two-factor.challenge');
            }
            return redirect()->intended(route('admin.licensing'));
        }

        $organization = $user->organizations()->orderBy('name')->first();
        $request->session()->forget('customer_2fa_verified_at');
        if ($user->two_factor_confirmed_at) {
            return redirect()->route('account.two-factor.challenge');
        }
        $destination = in_array($organization?->status, ['onboarding', 'suspended'], true)
            ? route('onboarding')
            : route('leads.index');

        return redirect()->intended($destination);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
