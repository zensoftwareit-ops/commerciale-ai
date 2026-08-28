<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Auth\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TwoFactorController extends Controller
{
    public function enroll(Request $request, Totp $totp): View
    {
        $user = $request->user();

        return view('admin.auth.two-factor-enroll', [
            'secret' => $user->two_factor_secret,
            'provisioningUri' => $user->two_factor_secret ? $totp->provisioningUri($user, $user->two_factor_secret) : null,
            'required' => (bool) config('commerciale-ai.security.platform_2fa_required'),
        ]);
    }

    public function setup(Request $request, Totp $totp): RedirectResponse
    {
        if ($request->user()->two_factor_confirmed_at) {
            throw ValidationException::withMessages([
                'two_factor' => 'La 2FA e gia attiva. Disattivala con password e codice prima di configurarla nuovamente.',
            ]);
        }

        $request->user()->forceFill([
            'two_factor_secret' => $totp->generateSecret(),
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
        $request->session()->forget('platform_2fa_verified_at');

        return back()->with('status', 'Segreto generato. Aggiungilo all’app Authenticator e conferma il codice.');
    }

    public function confirm(Request $request, Totp $totp): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:20']]);
        $user = $request->user();
        if (! $user->two_factor_secret || ! $totp->verify($user->two_factor_secret, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'Codice di autenticazione non valido.']);
        }
        $codes = $totp->recoveryCodes();
        $user->forceFill([
            'two_factor_recovery_codes' => array_map(fn (string $code): string => $totp->hashRecoveryCode($code), $codes),
            'two_factor_confirmed_at' => now(),
        ])->save();
        $request->session()->put('platform_2fa_verified_at', now()->timestamp);

        return back()->with('status', 'Autenticazione a due fattori attivata.')->with('two_factor_recovery_codes', $codes);
    }

    public function disable(Request $request, Totp $totp): RedirectResponse
    {
        if (config('commerciale-ai.security.platform_2fa_required')) {
            throw ValidationException::withMessages(['two_factor' => 'La 2FA è obbligatoria sul server e non può essere disattivata.']);
        }
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'code' => ['required', 'string', 'max:30'],
        ]);
        $user = $request->user();
        $valid = $user->two_factor_secret && $totp->verify($user->two_factor_secret, $data['code']);
        if (! $valid) {
            throw ValidationException::withMessages(['code' => 'Codice di autenticazione non valido.']);
        }
        $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
        $request->session()->forget('platform_2fa_verified_at');

        return back()->with('status', 'Autenticazione a due fattori disattivata.');
    }

    public function challenge(Request $request): View|RedirectResponse
    {
        if (! $request->user()->two_factor_confirmed_at) {
            return redirect()->route('admin.two-factor.enroll');
        }

        return view('admin.auth.two-factor-challenge');
    }

    public function verify(Request $request, Totp $totp): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:30']]);
        $user = $request->user();
        $valid = $user->two_factor_secret && $totp->verify($user->two_factor_secret, $data['code']);
        if (! $valid) {
            $valid = $totp->consumeRecoveryCode($user, $data['code']);
        }
        if (! $valid) {
            throw ValidationException::withMessages(['code' => 'Codice o codice di recupero non valido.']);
        }
        $request->session()->put('platform_2fa_verified_at', now()->timestamp);

        return redirect()->intended(route('admin.licensing'));
    }
}
