<?php

namespace App\Http\Controllers;

use App\Models\MailboxAccount;
use App\Services\Mail\MailIdentity;
use App\Services\Mail\OutboundMailTransport;
use App\Services\Mail\WebklexInboundMailbox;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class MailboxAccountController extends Controller
{
    public function index(OutboundMailTransport $transport): View
    {
        return view('settings.mailboxes', [
            'mailbox' => MailboxAccount::query()->oldest('created_at')->first(),
            'mailTransport' => $transport->details(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (MailboxAccount::query()->exists()) {
            return back()->withErrors(['mailbox' => 'L’organizzazione dispone già di una configurazione Email Daria.']);
        }
        $data = $this->normalized($this->validated($request, creating: true), $request);
        MailboxAccount::create($data);

        return back()->with('status', 'Email Daria configurata. Verifica ora ricezione IMAP e invio.');
    }

    public function update(Request $request, string $mailbox): RedirectResponse
    {
        $mailbox = MailboxAccount::query()->findOrFail($mailbox);
        $data = $this->validated($request, creating: false);
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }
        $data = $this->normalized($data, $request);
        $mailbox->update([...$data, 'last_error' => null, 'last_outbound_error' => null]);

        return back()->with('status', 'Configurazione Email Daria aggiornata.');
    }

    public function test(string $mailbox, WebklexInboundMailbox $client): RedirectResponse
    {
        $mailbox = MailboxAccount::query()->findOrFail($mailbox);
        try {
            $client->forAccount($mailbox);
            $client->testConnection();
            $mailbox->update(['last_tested_at' => now(), 'last_error' => null]);

            return back()->with('status', 'Connessione IMAP riuscita.');
        } catch (Throwable $exception) {
            report($exception);
            $mailbox->update(['last_tested_at' => now(), 'last_error' => mb_substr($exception->getMessage(), 0, 2000)]);

            return back()->withErrors(['imap' => 'Connessione non riuscita: '.$exception->getMessage()]);
        } finally {
            $client->close();
        }
    }

    public function testOutbound(
        Request $request,
        string $mailbox,
        OutboundMailTransport $transport,
        MailIdentity $identities,
    ): RedirectResponse {
        $mailbox = MailboxAccount::query()->findOrFail($mailbox);
        $data = $request->validate([
            'test_recipient' => ['required', 'email:rfc', 'max:255'],
        ], [
            'test_recipient.required' => 'Inserisci l’indirizzo al quale inviare il test.',
            'test_recipient.email' => 'Inserisci un destinatario di test valido.',
        ]);

        try {
            $details = $transport->ensureDeliverable();
            $identity = $identities->commercialForOrganization($mailbox->organization_id);
            Mail::mailer($details['mailer'])->raw(
                'Questa email conferma che l’identità commerciale di Daria è configurata per l’invio.',
                function ($message) use ($data, $identity): void {
                    $message->to($data['test_recipient'])
                        ->from($identity['from']->address, $identity['from']->name)
                        ->replyTo($identity['reply_to']->address, $identity['reply_to']->name)
                        ->subject('[Daria] Test email commerciale');
                },
            );
            $mailbox->update(['last_outbound_tested_at' => now(), 'last_outbound_error' => null]);

            return back()->with('status', 'Email di test affidata al trasporto '.$details['mailer'].'.');
        } catch (Throwable $exception) {
            report($exception);
            $mailbox->update([
                'last_outbound_tested_at' => now(),
                'last_outbound_error' => mb_substr($exception->getMessage(), 0, 2000),
            ]);

            return back()->withErrors(['outbound' => 'Invio di test non riuscito: '.$exception->getMessage()]);
        }
    }

    public function destroy(string $mailbox): RedirectResponse
    {
        MailboxAccount::query()->findOrFail($mailbox)->delete();

        return back()->with('status', 'Configurazione Email Daria rimossa. Le email già importate restano disponibili.');
    }

    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'from_address' => ['required', 'email:rfc', 'max:255'],
            'from_name' => ['required', 'string', 'max:255'],
            'reply_to_address' => ['nullable', 'email:rfc', 'max:255'],
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

    private function normalized(array $data, Request $request): array
    {
        $data['name'] = 'Email Daria';
        $data['from_address'] = mb_strtolower(trim($data['from_address']));
        $data['from_name'] = trim($data['from_name']);
        $data['reply_to_address'] = mb_strtolower(trim((string) (($data['reply_to_address'] ?? null) ?: $data['from_address'])));
        $data['validate_cert'] = $request->boolean('validate_cert');
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
