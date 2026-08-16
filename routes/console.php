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
use Illuminate\Support\Facades\Schedule;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('admin:grant {email}', function (string $email): int {
    $user = User::query()->where('email', mb_strtolower(trim($email)))->first();
    if (! $user) { $this->error('Utente non trovato.'); return Command::FAILURE; }
    $user->update(['is_super_admin' => true]);
    $this->info('Accesso Super Admin abilitato per '.$user->email.'.');
    return Command::SUCCESS;
})->purpose('Abilita un utente esistente al pannello licenze');

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

Schedule::command('mail:sync')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('conversations:automate')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('leads:automate-new')->everyFiveMinutes()->withoutOverlapping();

