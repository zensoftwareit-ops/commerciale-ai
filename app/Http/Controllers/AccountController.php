<?php

namespace App\Http\Controllers;

use App\Models\PlatformSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function edit(): View
    {
        if (request()->user()->isPlatformAdmin()) {
            return view('admin.account.edit', [
                'platformSettings' => PlatformSetting::query()->findOrNew(1),
            ]);
        }

        return view('account.edit');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'min:12'],
        ]);
        $request->user()->forceFill([
            'password' => Hash::make($data['password']),
            'remember_token' => Str::random(60),
        ])->save();

        return back()->with('status', 'Password aggiornata.');
    }

    public function updateMailIdentity(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mail_from_address' => ['required', 'email:rfc', 'max:255'],
            'mail_from_name' => ['required', 'string', 'max:255'],
        ]);
        $request->user()->update([
            'mail_from_address' => mb_strtolower(trim($data['mail_from_address'])),
            'mail_from_name' => trim($data['mail_from_name']),
        ]);

        return back()->with('status', 'Mittente email aggiornato.');
    }

    public function updatePlatformMailIdentity(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isPlatformAdmin(), 403);
        $data = $request->validate([
            'system_mail_from_address' => ['required', 'email:rfc', 'max:255'],
            'system_mail_from_name' => ['required', 'string', 'max:255'],
        ]);
        PlatformSetting::query()->updateOrCreate(['id' => 1], [
            'system_mail_from_address' => mb_strtolower(trim($data['system_mail_from_address'])),
            'system_mail_from_name' => trim($data['system_mail_from_name']),
        ]);

        return back()->with('status', 'Mittente delle email di sistema aggiornato.');
    }
}
