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
        $organization = app(TenantContext::class)->requireOrganization();
        abort_if($organization->status === 'suspended', 402, 'Il cliente è stato sospeso. Contatta l’amministrazione di Daria.');
        if (! config('commerciale-ai.billing.enforcement_enabled')) return $next($request);
        abort_unless($organization->activeLicense(), 402, 'La licenza non è attiva. Contatta il titolare dell’account.');
        return $next($request);
    }
}
