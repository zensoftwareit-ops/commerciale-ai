<?php

namespace App\Http\Controllers;

use App\Models\WhatsappAccount;
use App\Services\Whatsapp\WhatsappCloudApi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class WhatsappAccountController extends Controller
{
    public function edit(): View
    {
        return view('settings.whatsapp', ['account' => WhatsappAccount::query()->first()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $existing = WhatsappAccount::query()->first();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'waba_id' => ['required', 'string', 'max:100'],
            'phone_number_id' => ['required', 'string', 'max:100'], 'display_phone_number' => ['required', 'string', 'max:40'],
            'access_token' => [$existing ? 'nullable' : 'required', 'string', 'max:5000'],
            'allowed_recipients_text' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['nullable', 'boolean'], 'auto_reply_enabled' => ['nullable', 'boolean'],
            'internal_test_only' => ['nullable', 'boolean'],
        ]);
        $data['allowed_recipients'] = collect(preg_split('/[\r\n,]+/', $data['allowed_recipients_text'] ?? ''))
            ->map(fn ($number) => preg_replace('/\D+/', '', (string) $number))->filter()->unique()->values()->all();
        unset($data['allowed_recipients_text']);
        foreach (['is_active', 'auto_reply_enabled', 'internal_test_only'] as $boolean) $data[$boolean] = (bool) ($data[$boolean] ?? false);
        if ($existing && blank($data['access_token'] ?? null)) unset($data['access_token']);

        WhatsappAccount::query()->updateOrCreate([], $data + ['last_error' => null]);

        return back()->with('status', 'Configurazione WhatsApp aggiornata.');
    }

    public function test(WhatsappCloudApi $api): RedirectResponse
    {
        $account = WhatsappAccount::query()->firstOrFail();
        try {
            $details = $api->inspect($account);
            $account->update([
                'display_phone_number' => (string) ($details['display_phone_number'] ?? $account->display_phone_number),
                'last_tested_at' => now(), 'last_error' => null,
            ]);
        } catch (Throwable $exception) {
            report($exception);
            $account->update(['last_tested_at' => now(), 'last_error' => mb_substr($exception->getMessage(), 0, 2000)]);
            return back()->withErrors(['whatsapp' => $exception->getMessage()]);
        }

        return back()->with('status', 'Connessione alla WhatsApp Cloud API riuscita.');
    }
}
