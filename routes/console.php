<?php

use App\Contracts\InboundMailbox;
use App\Models\MailboxAccount;
use App\Models\User;
use App\Services\Mail\WebklexInboundMailbox;
use App\Services\Mail\SyncInboundEmailReplies;
use App\Services\Mail\RunConversationAutomation;
use App\Services\Leads\RunNewLeadAutomation;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('platform-admin:create {email} {--name= : Nome visualizzato} {--reset-password : Genera una nuova password temporanea}', function (string $email): int {
    $email = mb_strtolower(trim($email));
    if (validator(['email' => $email], ['email' => ['required', 'email', 'max:255']])->fails()) {
        $this->error('Indirizzo email non valido.');
        return Command::FAILURE;
    }

    $user = User::query()->where('email', $email)->first();
    if ($user && ($user->organizations()->exists() || $user->ownedLicenses()->exists() || filled($user->external_account_id))) {
        $this->error('Questa email appartiene a un account cliente. Usa un indirizzo esclusivo per l\'amministrazione della piattaforma.');
        return Command::FAILURE;
    }
    if (User::query()->where('is_super_admin', true)->where('email', '!=', $email)->exists()) {
        $this->error('Esiste già un amministratore di piattaforma. Usa la sua email oppure rimuovilo esplicitamente prima di crearne un altro.');
        return Command::FAILURE;
    }

    $created = ! $user;
    $resetPassword = $created || (bool) $this->option('reset-password');
    $password = $resetPassword ? Str::password(24, true, true, true, false) : null;
    $values = ['name' => (string) ($this->option('name') ?: 'Amministratore Daria'), 'is_super_admin' => true, 'external_account_id' => null];
    if ($password) $values['password'] = $password;
    $user = User::query()->updateOrCreate(['email' => $email], $values);

    $this->info($created ? 'Amministratore di piattaforma creato.' : 'Amministratore di piattaforma aggiornato.');
    $this->line('Email: '.$user->email);
    if ($password) {
        $this->warn('Password temporanea: '.$password);
        $this->warn('Copiala ora e cambiala al primo accesso. Non verrà mostrata nuovamente.');
    }
    return Command::SUCCESS;
})->purpose('Crea l\'account amministrativo della piattaforma, separato da tutti i clienti');

Artisan::command('mail:sync {--test : Verifica soltanto la connessione} {--limit= : Numero massimo di messaggi}', function (SyncInboundEmailReplies $sync, InboundMailbox $mailbox): int {
    try {
        if ($this->option('test')) {
            $accounts = MailboxAccount::withoutGlobalScopes()->where('is_active', true)->get();
            if ($accounts->isEmpty()) {
                $this->warn('Nessuna casella IMAP attiva. Configurala dal pannello Caselle email.');
                return Command::SUCCESS;
            }
            $failed = 0;
            foreach ($accounts as $account) {
                try {
                    if ($mailbox instanceof WebklexInboundMailbox) $mailbox->forAccount($account);
                    $mailbox->testConnection();
                    $account->update(['last_tested_at' => now(), 'last_error' => null]);
                    $this->info($account->name.': connessione riuscita.');
                } catch (Throwable $exception) {
                    $failed++;
                    $account->update(['last_tested_at' => now(), 'last_error' => mb_substr($exception->getMessage(), 0, 2000)]);
                    $this->error($account->name.': '.$exception->getMessage());
                } finally {
                    $mailbox->close();
                }
            }
            return $failed ? Command::FAILURE : Command::SUCCESS;
        }

        $limit = $this->option('limit');
        $stats = $sync->handle($limit !== null ? (int) $limit : null);
        $this->table(['Caselle', 'Errori casella', 'Scansionate', 'Importate', 'Duplicate', 'Non associate', 'Automatiche', 'Bozze', 'Passaggi a umano', 'Errori bozza'], [[
            $stats['mailboxes'], $stats['mailbox_errors'], $stats['scanned'], $stats['imported'], $stats['duplicates'], $stats['unmatched'],
            $stats['automated'], $stats['drafts'], $stats['handoffs'], $stats['draft_errors'],
        ]]);

        return Command::SUCCESS;
    } catch (Throwable $exception) {
        report($exception);
        $this->error($exception->getMessage());

        return Command::FAILURE;
    }
})->purpose('Importa le risposte email dalla casella IMAP');

Artisan::command('conversations:automate {--limit=25}', function (RunConversationAutomation $automation): int {
    $stats = $automation->handle((int) $this->option('limit'));
    $this->table(['Organizzazioni', 'Candidate', 'Inviate', 'Fallite'], [array_values($stats)]);
    return $stats['failed'] > 0 ? Command::FAILURE : Command::SUCCESS;
})->purpose('Invia le risposte che superano tutti i controlli di automazione');

Artisan::command('leads:automate-new {--limit=25} {--lead= : UUID di un lead interno da collaudare esplicitamente}', function (RunNewLeadAutomation $automation): int {
    $leadId = $this->option('lead');
    $stats = $automation->handle((int) $this->option('limit'), filled($leadId) ? (string) $leadId : null);
    $this->table(['Organizzazioni', 'Candidate', 'Analizzate', 'Bozze', 'Inviate', 'Fallite'], [array_values($stats)]);
    if ($stats['candidates'] === 0) $this->warn('Nessun lead candidato. Esegui leads:automation-status per conoscere il motivo.');
    return $stats['failed'] > 0 ? Command::FAILURE : Command::SUCCESS;
})->purpose('Analizza i nuovi lead interni e invia la prima email quando autorizzato');

Artisan::command('leads:automation-status', function (RunNewLeadAutomation $automation): int {
    $rows = $automation->diagnose();
    if ($rows === []) {
        $this->warn('Nessuna organizzazione configurata.');
        return Command::SUCCESS;
    }
    $this->table(array_keys($rows[0]), array_map('array_values', $rows));
    return Command::SUCCESS;
})->purpose('Mostra perché i lead vengono inclusi o esclusi dall’automazione');

Artisan::command('commerciale:run {--limit=25} {--mail-limit= : Numero massimo di messaggi per casella}', function (
    RunNewLeadAutomation $newLeads,
    SyncInboundEmailReplies $mailSync,
    RunConversationAutomation $conversations,
): int {
    $lock = Cache::lock('commerciale-ai:direct-cron', 300);
    if (! $lock->get()) {
        $this->warn('Un altro ciclo di automazione è ancora in esecuzione.');
        return Command::SUCCESS;
    }

    $failed = 0;
    try {
        try {
            $leadStats = $newLeads->handle((int) $this->option('limit'));
            $this->info('Nuovi lead');
            $this->table(['Organizzazioni', 'Candidati', 'Analizzati', 'Bozze', 'Inviati', 'Falliti'], [array_values($leadStats)]);
            $failed += $leadStats['failed'];
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Nuovi lead: '.$exception->getMessage());
            $failed++;
        }

        try {
            $mailLimit = $this->option('mail-limit');
            $mailStats = $mailSync->handle($mailLimit !== null ? (int) $mailLimit : null);
            $this->info('Posta in ingresso');
            $this->table(['Caselle', 'Errori casella', 'Scansionate', 'Importate', 'Duplicate', 'Non associate', 'Automatiche', 'Bozze', 'Passaggi a umano', 'Errori bozza'], [[
                $mailStats['mailboxes'], $mailStats['mailbox_errors'], $mailStats['scanned'], $mailStats['imported'], $mailStats['duplicates'],
                $mailStats['unmatched'], $mailStats['automated'], $mailStats['drafts'], $mailStats['handoffs'], $mailStats['draft_errors'],
            ]]);
            $failed += $mailStats['mailbox_errors'] + $mailStats['draft_errors'];
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Posta in ingresso: '.$exception->getMessage());
            $failed++;
        }

        try {
            $conversationStats = $conversations->handle((int) $this->option('limit'));
            $this->info('Conversazioni');
            $this->table(['Organizzazioni', 'Candidate', 'Inviate', 'Fallite'], [array_values($conversationStats)]);
            $failed += $conversationStats['failed'];
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Conversazioni: '.$exception->getMessage());
            $failed++;
        }
    } finally {
        $lock->release();
    }

    return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
})->purpose('Esegue direttamente tutte le automazioni senza usare lo scheduler Laravel');
