<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\InboundEmail;
use App\Models\Lead;
use App\Models\LeadContact;
use App\Models\LeadReply;
use App\Services\Ai\GenerateLeadReply;
use App\Services\Leads\LeadData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class InboundEmailController extends Controller
{
    public function index(): View
    {
        $emails = InboundEmail::query()
            ->where('status', 'pending')
            ->latest('received_at')
            ->paginate(25);
        $leads = Lead::query()
            ->whereHas('replies', fn ($query) => $query->where('status', 'sent'))
            ->latest('last_activity_at')
            ->limit(150)
            ->get(['id', 'name', 'company', 'email']);

        return view('inbound-emails.index', compact('emails', 'leads'));
    }

    public function link(Request $request, string $email, GenerateLeadReply $replyGenerator): RedirectResponse
    {
        $data = $request->validate([
            'lead_id' => ['required', 'uuid'],
            'add_secondary_contact' => ['nullable', 'boolean'],
        ]);
        $inbound = InboundEmail::query()->where('status', 'pending')->findOrFail($email);
        $lead = Lead::query()->findOrFail($data['lead_id']);
        $sentReply = LeadReply::query()
            ->where('lead_id', $lead->id)
            ->where('status', 'sent')
            ->latest('sent_at')
            ->first();

        DB::transaction(function () use ($request, $data, $inbound, $lead, $sentReply): void {
            $hadFollowUp = $lead->next_action_at !== null
                || ($sentReply?->follow_up_at !== null && $sentReply?->follow_up_cancelled_at === null);
            $senderDiffers = LeadData::normalizeEmail($inbound->from_address) !== $lead->email_normalized;

            $inbound->update([
                'lead_id' => $lead->id,
                'lead_reply_id' => $sentReply?->id,
                'status' => 'linked',
                'match_confidence' => 'manual',
                'match_reason' => 'manual',
                'sender_differs' => $senderDiffers,
                'linked_by' => $request->user()->id,
                'linked_at' => now(),
            ]);
            if ($sentReply && $hadFollowUp) {
                $sentReply->update(['follow_up_cancelled_at' => now()]);
            }
            $lead->update(['operational_status' => 'needs_action', 'next_action_at' => null, 'last_activity_at' => now()]);
            Activiן}��$z{-���jםa casella che riceva le relative risposte. Il client è installato tramite Composer e non richiede l'estensione PHP `imap`.

Configurazione tipica con IMAP su SSL:

```dotenv
IMAP_ENABLED=true
IMAP_HOST=mail.example.it
IMAP_PORT=993
IMAP_ENCRYPTION=ssl
IMAP_VALIDATE_CERT=true
IMAP_USERNAME=commerciale@example.it
IMAP_PASSWORD="PASSWORD_CASELLA"
IMAP_AUTHENTICATION=null
IMAP_FOLDER=INBOX
IMAP_TIMEOUT=30
IMAP_SYNC_SINCE_DAYS=14
IMAP_MAX_MESSAGES=50
```

Usare host, porta e cifratura indicati dal fornitore della casella. Non disabilitare la validazione del certificato in produzione. Dopo la modifica:

```bash
/opt/plesk/php/8.3/bin/php artisan optimize:clear
/opt/plesk/php/8.3/bin/php artisan config:cache
/opt/plesk/php/8.3/bin/php artisan mail:sync --test
/opt/plesk/php/8.3/bin/php artisan mail:sync
```

Il comando mostra soltanto i conteggi. Le risposte con un riferimento certo a una conversazione inviata vengono associate anche quando il cliente usa una casella diversa; nella scheda del lead compare un avviso e la bozza resta indirizzata all'email principale. Gli indirizzi secondari già confermati vengono riconosciuti. I messaggi senza prove sufficienti vengono conservati nella pagina **Email da associare**, dove un operatore può scegliere il lead e, facoltativamente, salvare il mittente come contatto secondario.

In **Plesk > Siti Web e Domini > Attività pianificate**, creare un'attività ogni cinque minuti eseguita dalla radice del progetto:

```bash
/opt/plesk/php/8.3/bin/php /PERCORSO/ASSOLUTO/DEL/PROGETTO/artisan mail:sync
```

In alternativa, sui server che usano lo scheduler Laravel, eseguire `php artisan schedule:run` ogni minuto.

## 7. Aggiornamenti

Prima di aggiornare eseguire un backup di database e file `.env`, quindi:

```bash
php artisan down
git pull --ff-only
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan up
```

## 8. Controlli finali

```bash
php artisan about
php artisan migrate:status
```

In un ambiente di sviluppo, dove sono presenti anche le dipendenze `require-dev`, eseguire inoltre `php artisan test`.

Dal browser verificare inoltre:

- login e cambio password;
- pagina Azienda;
- creazione di un lead manuale;
- analisi OpenAI;
- generazione, modifica e invio di una bozza email;
- pianificazione di un follow-up;
- ricezione di una risposta nella casella IMAP, annullamento del follow-up e nuova bozza;
- creazione e rotazione di una sorgente webhook;
- reset password via email, se SMTP è attivo.

Il worker delle code non è indispensabile per i flussi attuali. Quando saranno introdotti invii o elaborazioni asincrone, avviare stabilmente `php artisan queue:work --tries=3` tramite il sistema di process management del server.

## 9. Problemi frequenti

- **Errore 500 dopo l'installazione:** controllare `storage/logs/laravel.log`, `APP_KEY` e i permessi di `storage/`.
- **Database non trovato:** verificare `DB_*`, creare il database e rieseguire `php artisan config:clear`.
- **OpenAI non configurato:** valorizzare `OPENAI_API_KEY` sul server e rieseguire `php artisan config:cache`.
- **Pagina iniziale del server invece dell'app:** il document root non punta a `public/`.
- **Email non ricevuta:** verificare che `MAIL_MAILER=smtp`, ricreare la cache di configurazione e controllare `storage/logs/laravel.log`.
- **Connessione IMAP fallita:** controllare host, porta, cifratura, credenziali e certificato con `php artisan mail:sync --test`.
- **Risposta non visibile nel lead:** controllare la pagina **Email da associare**. Se il messaggio non contiene riferimenti alla conversazione e il mittente non è già noto, richiede una verifica manuale.
- **Webhook 401/403:** verificare il token dell'endpoint e che il dominio sorgente sia tra quelli consentiti.
