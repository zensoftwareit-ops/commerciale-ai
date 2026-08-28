<?php

namespace App\Http\Controllers;

use App\Models\PlatformSetting;
use App\Services\Mail\MailIdentity;
use App\Services\Mail\OutboundMailTransport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class AccountController extends Controller
{
    public function edit(OutboundMailTransport $transport): View
    {
        if (request()->user()->isPlatformAdmin()) {
            return view('admin.account.edit', [
                'platformSettings' => PlatformSetting::query()->findOrNew(1),
                'mailTransport' => $transport->details(),
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

    public function testPlatformMail(
        Request $request,
        OutboundMailTransport $transport,
        MailIdentity $identities,
    ): RedirectResponse {
        abort_unless($request->user()->isPlatformAdmin(), 403);

        try {
            $details = $transport->ensureDeliverable();
            $identity = $identities->forPlatform();
            Mail::mailer($details['mailer'])->raw(
                'Questa email conferma che il trasporto di posta di Daria è operativo.',
                function ($message) use ($request, $identity): void {
                    $message->to($request->user()->email)
                        ->from($identity->address, $identity->name)
                        ->subject('[Daria] Test email di sistema');
                },
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['mail' => 'Test email fallito: '.$exception->getMessage()]);
        }

        return back()->with('status', 'Email di test affidata al trasporto '.$details['mailer'].' per '.$request->user()->email.'.');
    }
}
