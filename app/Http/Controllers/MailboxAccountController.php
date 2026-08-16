<?php

namespace App\Http\Controllers;

use App\Models\MailboxAccount;
use App\Services\Mail\WebklexInboundMailbox;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class MailboxAccountController extends Controller
{
    public function index(): View
    {
        $mailboxes = MailboxAccount::query()->orderBy('name')->get();

        return view('settings.mailboxes', compact('mailboxes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, creating: true);
        $data['validate_cert'] = $request->boolean('validate_cert');
        $data['is_active'] = $request->boolean('is_active');
        MailboxAccount::create($data);

        return back()->with('status', 'Casella IMAP salvata. Ora verifica la connessione.');
    }

    public function update(Request $request, string $mailbox): RedirectResponse
    {
        $mailbox = MailboxAccount::query()->findOrFail($mailbox);
        $data = $this->validated($request, creating: false, mailbox: $mailbox);
        if (blank($data['password'] ?? null)) unset($data['password']);
        $data['validate_cert'] = $request->boolean('validate_cert');
        $data['is_active'] = $request->boolean('is_active');
        $mailbox->update([...$data, 'last_error' => null]);

        return back()->with('status', 'Configurazione IMAP aggiornata.');
    }

    public function test(string $mailbox, WebklexInboundMailbox $client): RedirectResponse
    {
        $mailbox = MailboxAccount::query()->findOrFail($mailbox);
        try {
            $client->forAccount($mailbox);
            $client->testConnection();
            $mailbox->update(['last_tested_at' => now(), 'last_error' => null]);

            return back()->with('status', 'Connessione IMAP riuscita per '.$mailbox->name.'.');
        } catch (Throwable $exception) {
            report($exception);
            $mailbox->update(['last_tested_at' => now(), 'last_error' => mb_substr($exception->getMessage(), 0, 2000)]);

            return back()->withErrors(['imap' => 'Connessione non riuscita: '.$exception->getMessage()]);
        } finally {
            $client->close();
        }
    }

    public function destroy(string $mailbox): RedirectResponse
    {
        MailboxAccount::query()->findOrFail($mailbox)->delete();

        return back()->with('status', 'Casella IMAP rimossa. Le email già importate restano disponibili.');
    }

    private function validated(Request $request, bool $creating, ?MailboxAccount $mailbox = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'encryption' => ['nullable', Rule::in(['ssl', 'tls'])],
            'validate_cert' => ['nullable', 'boolean'],
            'username' => ['required', 'string', 'max:255'],
            'password' => [$creating ? 'required' : 'nullable', 'string', 'max:2000'],
            'authentication' => ['nullable', Rule::in(['plain', 'login'])],
            'folder' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}

