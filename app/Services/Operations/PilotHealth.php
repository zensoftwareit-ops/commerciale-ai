<?php

namespace App\Services\Operations;

use App\Models\AiRun;
use App\Models\MailboxAccount;
use App\Models\Organization;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Mail\MailIdentity;
use App\Services\Mail\OutboundMailTransport;
use Illuminate\Support\Facades\DB;
use Throwable;

class PilotHealth
{
    public function __construct(
        private readonly OutboundMailTransport $mail,
        private readonly MailIdentity $identities,
    ) {}

    /** @return array{ready:bool,checks:list<array{key:string,label:string,status:string,detail:string}>,settings:PlatformSetting} */
    public function snapshot(): array
    {
        $settings = PlatformSetting::query()->findOrNew(1);
        $checks = [];
        $add = function (string $key, string $label, string $status, string $detail) use (&$checks): void {
            $checks[] = compact('key', 'label', 'status', 'detail');
        };

        try {
            DB::connection()->getPdo();
            $add('database', 'Database', 'ok', 'Connessione riuscita: '.config('database.default').'.');
        } catch (Throwable $exception) {
            $add('database', 'Database', 'error', 'Connessione fallita: '.$exception->getMessage());
        }

        $isProduction = app()->environment('production');
        $add('environment', 'Ambiente', $isProduction ? 'ok' : 'warning', 'APP_ENV='.app()->environment().'.');
        $add('debug', 'Debug', config('app.debug') ? 'error' : 'ok', config('app.debug') ? 'APP_DEBUG è attivo.' : 'APP_DEBUG è disattivato.');
        $https = str_starts_with((string) config('app.url'), 'https://');
        $add('https', 'URL HTTPS', $https ? 'ok' : 'error', (string) config('app.url'));

        $mail = $this->mail->details();
        $add('mail', 'Trasporto email', $mail['deliverable'] ? 'ok' : 'error', $mail['mailer'].' · '.$mail['message']);
        try {
            $identity = $this->identities->forPlatform();
            $add('system_sender', 'Mittente di sistema', 'ok', $identity->name.' <'.$identity->address.'>');
        } catch (Throwable $exception) {
            $add('system_sender', 'Mittente di sistema', 'error', $exception->getMessage());
        }

        $provider = (string) config('commerciale-ai.ai_provider');
        $openAiReady = $provider === 'openai' && filled(config('commerciale-ai.openai.api_key'));
        $add('openai', 'OpenAI', $openAiReady ? 'ok' : 'error', $openAiReady
            ? 'Provider openai · modello '.config('commerciale-ai.openai.model').'.'
            : 'Imposta AI_PROVIDER=openai e OPENAI_API_KEY.');

        $platformAdmin = User::query()
            ->where('is_super_admin', true)
            ->whereDoesntHave('organizations')
            ->first();
        $twoFactorRequired = (bool) config('commerciale-ai.security.platform_2fa_required');
        $twoFactorConfirmed = (bool) $platformAdmin?->two_factor_confirmed_at;
        $twoFactorStatus = ! $twoFactorConfirmed ? 'error' : ($twoFactorRequired ? 'ok' : 'warning');
        $add('platform_2fa', 'Sicurezza amministratore', $twoFactorStatus, match ($twoFactorStatus) {
            'ok' => 'Account amministrativo separato e 2FA obbligatoria.',
            'warning' => '2FA configurata, ma PLATFORM_2FA_REQUIRED non e ancora attivo.',
            default => 'Configura la 2FA sull account amministrativo prima del rilascio.',
        });

        $automationAge = $settings->last_automation_completed_at?->diffInMinutes(now());
        $automationFresh = $automationAge !== null && $automationAge <= 10;
        $automationOk = $automationFresh && $settings->last_automation_status === 'success';
        $add('automation', 'Cron commerciale', $automationOk ? 'ok' : 'error', $settings->last_automation_completed_at
            ? 'Ultimo completamento '.$settings->last_automation_completed_at->diffForHumans().' · stato '.($settings->last_automation_status ?: 'sconosciuto').'.'
            : 'Nessuna esecuzione registrata di commerciale:run.');

        $failedJobs = DB::table('failed_jobs')->count();
        $add('failed_jobs', 'Job falliti', $failedJobs === 0 ? 'ok' : 'warning', $failedJobs.' job nella coda degli errori.');
        $mailboxErrors = MailboxAccount::withoutGlobalScopes()->where('is_active', true)->whereNotNull('last_error')->count();
        $add('imap', 'Caselle IMAP', $mailboxErrors === 0 ? 'ok' : 'warning', $mailboxErrors.' caselle attive con ultimo errore registrato.');
        $recentAiFailures = AiRun::withoutGlobalScopes()->where('status', 'failed')->where('created_at', '>=', now()->subDay())->count();
        $add('ai_failures', 'Errori AI ultime 24 ore', $recentAiFailures === 0 ? 'ok' : 'warning', $recentAiFailures.' esecuzioni fallite.');

        $organizationsWithoutLicense = Organization::query()->get()->filter(fn (Organization $organization): bool => ! $organization->activeLicense())->count();
        $enforcement = (bool) config('commerciale-ai.billing.enforcement_enabled');
        $add('licenses', 'Licenze', $organizationsWithoutLicense === 0 && $enforcement ? 'ok' : 'warning',
            $organizationsWithoutLicense.' clienti senza licenza utilizzabile · enforcement '.($enforcement ? 'attivo' : 'disattivato').'.');

        $backupRecent = $settings->last_backup_verified_at?->greaterThanOrEqualTo(now()->subDays(7)) ?? false;
        $add('backup', 'Backup verificato', $backupRecent ? 'ok' : 'error', $settings->last_backup_verified_at
            ? 'Ultima verifica '.$settings->last_backup_verified_at->diffForHumans().'.'
            : 'Nessun backup verificato registrato.');
        $add('storage', 'Storage scrivibile', is_writable(storage_path()) ? 'ok' : 'error', storage_path());

        return [
            'ready' => collect($checks)->doesntContain(fn (array $check): bool => $check['status'] === 'error'),
            'checks' => $checks,
            'settings' => $settings,
        ];
    }
}
