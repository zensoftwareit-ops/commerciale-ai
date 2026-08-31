<?php

namespace App\Services\Operations;

use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Mail\MailIdentity;
use App\Services\Mail\OutboundMailTransport;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class PlatformHealthAlert
{
    public function __construct(
        private readonly SystemHealth $health,
        private readonly OutboundMailTransport $transport,
        private readonly MailIdentity $identities,
    ) {}

    /** @return array{status:string,errors:int,message:string} */
    public function handle(bool $force = false): array
    {
        $snapshot = $this->health->snapshot();
        $errors = collect($snapshot['checks'])->where('status', 'error')->values();
        $settings = PlatformSetting::query()->firstOrCreate(['id' => 1]);

        if ($errors->isEmpty()) {
            $settings->update(['last_health_alert_signature' => null]);

            return ['status' => 'healthy', 'errors' => 0, 'message' => 'Nessun errore operativo.'];
        }

        $signature = hash('sha256', $errors->map(fn (array $check): string => $check['key'].':'.$check['status'])->implode('|'));
        $cooldown = max(15, (int) config('commerciale-ai.operations.health_alert_cooldown_minutes', 360));
        $recentDuplicate = $settings->last_health_alert_signature === $signature
            && $settings->last_health_alerted_at?->greaterThan(now()->subMinutes($cooldown));
        if (! $force && $recentDuplicate) {
            return ['status' => 'cooldown', 'errors' => $errors->count(), 'message' => 'Allarme già inviato durante il periodo di attesa.'];
        }

        $admin = User::query()->where('is_super_admin', true)->whereDoesntHave('organizations')->first();
        if (! $admin) {
            throw new RuntimeException('Nessun Super Admin disponibile per ricevere gli allarmi.');
        }
        $transport = $this->transport->ensureDeliverable();
        $identity = $this->identities->forPlatform();
        $body = "Daria ha rilevato problemi operativi:\n\n".$errors
            ->map(fn (array $check): string => '- '.$check['label'].': '.$check['detail'])
            ->implode("\n")."\n\nApri il pannello Salute per i dettagli.";

        Mail::mailer($transport['mailer'])->raw($body, function ($message) use ($admin, $identity): void {
            $message->to($admin->email)
                ->from($identity->address, $identity->name)
                ->subject('[Daria] Allarme salute piattaforma');
        });
        $settings->update([
            'last_health_alert_signature' => $signature,
            'last_health_alerted_at' => now(),
        ]);

        return ['status' => 'sent', 'errors' => $errors->count(), 'message' => 'Allarme inviato a '.$admin->email.'.'];
    }
}
