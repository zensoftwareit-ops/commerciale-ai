<?php

namespace App\Http\Controllers;

use App\Models\AiRun;
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
            'latestSetup' => AiRun::query()->where('operation', 'setup_wizard')->latest('created_at')->limit(20)->get()
                ->first(fn (AiRun $run): bool => ($run->input_context['user_id'] ?? null) === (string) auth()->id()),
        ]);
    }
}
