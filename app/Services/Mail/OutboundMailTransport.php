<?php

namespace App\Services\Mail;

use RuntimeException;

class OutboundMailTransport
{
    /** @return array{mailer:string,transport:string,scheme:?string,host:?string,port:int|string|null,username:?string,uses_url:bool,deliverable:bool,message:string} */
    public function details(): array
    {
        $mailer = (string) config('mail.default', 'log');
        $definition = (array) config('mail.mailers.'.$mailer, []);
        $transport = (string) ($definition['transport'] ?? $mailer);
        $nested = (array) ($definition['mailers'] ?? []);
        $blocked = in_array($transport, ['log', 'array'], true)
            || ($transport === 'failover' && array_intersect($nested, ['log', 'array']) !== []);
        $message = match (true) {
            $transport === 'log' => 'Il mailer attivo è “log”: le email vengono scritte nei log e nessun server SMTP viene contattato.',
            $transport === 'array' => 'Il mailer attivo è “array”: le email restano in memoria e non vengono consegnate.',
            $transport === 'failover' && $blocked => 'Il failover contiene un mailer non consegnante e può segnalare successo senza contattare l’SMTP.',
            default => 'Il trasporto è configurato per una consegna reale.',
        };

        return [
            'mailer' => $mailer,
            'transport' => $transport,
            'scheme' => isset($definition['scheme']) ? (string) $definition['scheme'] : null,
            'host' => isset($definition['host']) ? (string) $definition['host'] : null,
            'port' => $definition['port'] ?? null,
            'username' => isset($definition['username']) ? (string) $definition['username'] : null,
            'uses_url' => filled($definition['url'] ?? null),
            'deliverable' => ! $blocked,
            'message' => $message,
        ];
    }

    public function ensureDeliverable(): array
    {
        $details = $this->details();
        if (! $details['deliverable']) {
            throw new RuntimeException($details['message'].' Imposta MAIL_MAILER=smtp oppure MAIL_MAILER=resend_smtp e ricrea la cache di configurazione.');
        }

        return $details;
    }
}
