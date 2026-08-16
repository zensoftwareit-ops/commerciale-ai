<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireActiveLicense
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('commerciale-ai.billing.enforcement_enabled')) return $next($request);
        $organization = app(TenantContext::class)->requireOrganization();
        abort_unless($organization->activeLicense(), 402, 'La licenza non è attiva. Contatta il titolare dell’account.');
        return $next($request);
    }
}

