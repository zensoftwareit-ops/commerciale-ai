<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireCustomerTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user?->isCustomerAccount() || ! $user->two_factor_confirmed_at) {
            return $next($request);
        }
        if (! $request->session()->has('customer_2fa_verified_at')) {
            return redirect()->route('account.two-factor.challenge');
        }

        return $next($request);
    }
}
