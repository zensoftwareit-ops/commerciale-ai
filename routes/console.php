<?php

use App\Contracts\InboundMailbox;
use App\Services\Mail\SyncInboundEmailReplies;
use App\Services\Mail\RunConversationAutomation;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('mail:sync {--test : Verifica soltanto la connessione} {--limit= : Numero massimo di messaggi}', function (SyncInboundEmailReplies $sync, InboundMailbox $mailbox): int {
    try {
        if ($this->option('test')) {
            $mailbox->testConnection();
            $this->info('Connessione IMAP riuscita.');

            return Command::SUCCESS;
        }

        $limit = $this->option('limit');
        $stats = $sync->handle($limit !== null ? (int) $limit : null);
        $this->table(['Scansionate', 'Importate', 'Duplicate', 'Non associate', 'Automatiche', 'Bozze', 'Errori bozza'], [[
            $stats['scanned'], $stats['imported'], $stats['duplicates'], $stats['unmatched'],
            $stats['automated'], $stats['drafts'], $stats['draft_errors'],
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
})->purpose('Invia soltanto preventivi che superano tutti i controlli di automazione');

if (config('commerciale-ai.imap.enabled')) {
    Schedule::command('mail:sync')->everyFiveMinutes()->withoutOverlapping();
}
Schedule::command('conversations:automate')->everyFiveMinutes()->withoutOverlapping();
