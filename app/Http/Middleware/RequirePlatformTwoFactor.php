<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePlatformTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user?->isPlatformAdmin()) {
            return $next($request);
        }
        if (! $user->two_factor_confirmed_at) {
            if (config('commerciale-ai.security.platform_2fa_required')) {
                return redirect()->route('admin.two-factor.enroll')->withErrors(['two_factor' => 'Attiva l’autenticazione a due fattori per continuare.']);
            }
            return $next($request);
        }
        if (! $request->session()->has('platform_2fa_verified_at')) {
            return redirect()->route('admin.two-factor.challenge');
        }

        return $next($request);
    }
}
