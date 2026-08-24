<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrganizationSwitchController extends Controller
{
    public function __invoke(Request $request, string $organization): RedirectResponse
    {
        $organization = $request->user()->organizations()->whereKey($organization)->firstOrFail();
        $request->session()->put('organization_id', $organization->id);

        $destination = in_array($organization->status, ['onboarding', 'suspended'], true) ? 'onboarding' : 'leads.index';

        return redirect()->route($destination)->with('status', 'Workspace attivo: '.$organization->name.'.');
    }
}
