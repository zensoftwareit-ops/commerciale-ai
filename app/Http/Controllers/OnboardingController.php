<?php

namespace App\Http\Controllers;

use App\Services\Organizations\OrganizationLifecycle;
use App\Support\Tenancy\TenantContext;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    public function __invoke(TenantContext $tenants, OrganizationLifecycle $lifecycle): View
    {
        $organization = $tenants->requireOrganization();
        $organization = $lifecycle->refresh($organization);

        return view('onboarding.index', [
            'organization' => $organization,
            'onboarding' => $lifecycle->snapshot($organization),
        ]);
    }
}
