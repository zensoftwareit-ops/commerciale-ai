<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $role = $request->user()?->roleFor(app(TenantContext::class)->requireOrganization());
        abort_unless(in_array($role, $roles, true), 403);

        return $next($request);
    }
}
