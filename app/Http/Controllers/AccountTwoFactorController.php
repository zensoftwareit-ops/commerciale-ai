<?php

namespace App\Http\Controllers;

use App\Services\Auth\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccountTwoFactorController extends Controller
{
    public function enroll(Request $request, Totp $totp): View
    {
        $this->customer($request);
        $user = $request->user();

        return view('account.two-factor-enroll', [
            'secret' => $user->two_factor_secret,
            'provisioningUri' => $user->two_factor_secret ? $totp->provisioningUri($user, $user->two_factor_secret) : null,
        ]);
    }

    public function setup(Request $request, Totp $totp): RedirectResponse
    {
        $this->customer($request);
        if ($request->user()->two_factor_confirmed_at) {
            throw ValidationException::withMessages(['two_factor' => 'La 2FA è già attiva.']);
        }
        $request->user()->forceFill([
            'two_factor_secret' => $totp->generateSecret(),
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
        $request->session()->forget('customer_2fa_verified_at');

        return back()->with('status', 'Segreto generato. Conferma ora il codice dell’app Authenticator.');
    }

    public function confirm(Request $request, Totp $totp): RedirectResponse
    {
        $this->customer($request);
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
        $request->session()->put('customer_2fa_verified_at', now()->timestamp);

        return back()->with('status', 'Autenticazione a due fattori attivata.')
            ->with('two_factor_recovery_codes', $codes);
    }

    public function disable(Request $request, Totp $totp): RedirectResponse
    {
        $this->customer($request);
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'code' => ['required', 'string', 'max:30'],
        ]);
        $user = $request->user();
        if (! $user->two_factor_secret || ! $totp->verify($user->two_factor_secret, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'Codice di autenticazione non valido.']);
        }
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
        $request->session()->forget('customer_2fa_verified_at');

        return redirect()->route('account.edit')->with('status', 'Autenticazione a due fattori disattivata.');
    }

    public function challenge(Request $request): View|RedirectResponse
    {
        $this->customer($request);
        if (! $request->user()->two_factor_confirmed_at) {
            return redirect()->route('account.two-factor.enroll');
        }

        return view('account.two-factor-challenge');
    }

    public function verify(Request $request, Totp $totp): RedirectResponse
    {
        $this->customer($request);
        $data = $request->validate(['code' => ['required', 'string', 'max:30']]);
        $user = $request->user();
        $valid = $user->two_factor_secret && $totp->verify($user->two_factor_secret, $data['code']);
        $valid = $valid || $totp->consumeRecoveryCode($user, $data['code']);
        if (! $valid) {
            throw ValidationException::withMessages(['code' => 'Codice o codice di recupero non valido.']);
        }
        $request->session()->put('customer_2fa_verified_at', now()->timestamp);

        return redirect()->intended(route('leads.index'));
    }

    private function customer(Request $request): void
    {
        abort_unless($request->user()?->isCustomerAccount(), 403);
    }
}
