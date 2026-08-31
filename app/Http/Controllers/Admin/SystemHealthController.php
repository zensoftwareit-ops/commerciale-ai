<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Services\Operations\SystemHealth;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SystemHealthController extends Controller
{
    public function index(SystemHealth $health): View
    {
        return view('admin.health.index', $health->snapshot());
    }

    public function confirmBackup(): RedirectResponse
    {
        PlatformSetting::query()->updateOrCreate(['id' => 1], ['last_backup_verified_at' => now()]);

        return back()->with('status', 'Backup segnato come verificato. Conferma questa voce solo dopo aver controllato che il backup sia scaricabile e ripristinabile.');
    }
}
