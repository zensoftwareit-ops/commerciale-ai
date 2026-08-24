<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function index(): View
    {
        $organizations = Organization::query()
            ->with([
                'users',
                'licenses.plan',
                'settings' => fn ($query) => $query->withoutGlobalScopes(),
            ])
            ->withCount([
                'leads as leads_count' => fn ($query) => $query->withoutGlobalScopes(),
                'mailboxes as active_mailboxes_count' => fn ($query) => $query->withoutGlobalScopes()->where('is_active', true),
            ])
            ->orderBy('name')
            ->paginate(30);

        return view('admin.organizations.index', compact('organizations'));
    }
}
