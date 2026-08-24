<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireOrganizationAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $organization = app(TenantContext::class)->requireOrganization();

        if ($organization->status === 'suspended') {
            return redirect()->route('onboarding')->with('warning', 'Il workspace è sospeso. Verifica lo stato della licenza.');
        }

        return $next($request);
    }
}
