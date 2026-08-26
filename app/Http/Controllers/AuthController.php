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
            return redirect()->intended(route('admin.licensing'));
        }

        $organization = $user->organizations()->orderBy('name')->first();
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
