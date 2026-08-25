<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\LicensePlan;
use App\Models\Organization;
use Illuminate\View\View;

class LicensingDashboardController extends Controller
{
    public function __invoke(): View
    {
        $plans = LicensePlan::query()->withCount('licenses')->orderBy('sort_order')->get();
        $licenses = License::query()->with('plan', 'organization', 'owner')->latest()->paginate(30);
        $organizations = Organization::query()->orderBy('name')->get();
        return view('admin.licensing.index', compact('plans', 'licenses', 'organizations'));
    }
}

