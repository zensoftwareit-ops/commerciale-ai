<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $organization = $user?->organizations()
            ->when($request->session()->get('organization_id'), fn ($query, $id) => $query->whereKey($id))
            ->first();

        abort_unless($organization, 403, 'Nessuna organizzazione accessibile.');

        app(TenantContext::class)->set($organization);
        $request->session()->put('organization_id', $organization->getKey());

        try {
            return $next($request);
        } finally {
            app(TenantContext::class)->clear();
        }
    }
}
