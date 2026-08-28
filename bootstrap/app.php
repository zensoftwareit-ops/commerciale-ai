<?php

use App\Http\Middleware\RequireRole;
use App\Http\Middleware\RequireSuperAdmin;
use App\Http\Middleware\AuthenticateBillingClient;
use App\Http\Middleware\RequireActiveLicense;
use App\Http\Middleware\RequireOrganizationAccess;
use App\Http\Middleware\RequireBillingSelfService;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\AuditPlatformMutation;
use App\Http\Middleware\RequirePlatformTwoFactor;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Tenant resolution must happen before implicit model binding applies tenant scopes.
        $middleware->prependToPriorityList(SubstituteBindings::class, ResolveTenant::class);
        $middleware->alias([
            'tenant' => ResolveTenant::class,
            'role' => RequireRole::class,
            'superadmin' => RequireSuperAdmin::class,
            'billing.client' => AuthenticateBillingClient::class,
            'billing.selfservice' => RequireBillingSelfService::class,
            'license' => RequireActiveLicense::class,
            'organization.access' => RequireOrganizationAccess::class,
            'audit.platform' => AuditPlatformMutation::class,
            'platform.2fa' => RequirePlatformTwoFactor::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
