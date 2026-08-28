<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformAuditLog;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function __invoke(): View
    {
        $logs = PlatformAuditLog::query()->with('actor')->latest()->paginate(50);

        return view('admin.audit.index', compact('logs'));
    }
}
