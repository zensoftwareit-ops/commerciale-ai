<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateBillingClient
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('commerciale-ai.billing.integration_key');
        $provided = (string) $request->bearerToken();
        abort_unless($expected !== '' && $provided !== '' && hash_equals($expected, $provided), 401, 'Billing API credentials are invalid.');
        return $next($request);
    }
}

