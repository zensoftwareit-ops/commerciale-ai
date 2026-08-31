<?php

namespace App\Services\Mail;

use App\Models\MailboxAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ResendDomainManager
{
    /** @return array{enabled:bool,configured:bool,available:bool} */
    public function configuration(): array
    {
        $enabled = (bool) config('services.resend.domain_automation_enabled', false);
        $configured = filled(config('services.resend.key'));

        return ['enabled' => $enabled, 'configured' => $configured, 'available' => $enabled && $configured];
    }

    public function register(MailboxAccount $mailbox): MailboxAccount
    {
        $this->ensureAvailable();
        $domain = $this->domainFromAddress($mailbox->from_address);

        if ($mailbox->resend_domain_id && $mailbox->resend_domain_name === $domain) {
            return $this->refresh($mailbox);
        }

        $list = $this->successful($this->client()->get('/domains'));
        $existing = collect((array) $list->json('data', []))
            ->first(fn (mixed $item): bool => is_array($item) && mb_strtolower((string) ($item['name'] ?? '')) === $domain);

        if (is_array($existing) && filled($existing['id'] ?? null)) {
            $mailbox->update([
                'resend_domain_id' => (string) $existing['id'],
                'resend_domain_name' => $domain,
                'resend_domain_status' => (string) ($existing['status'] ?? 'not_started'),
                'resend_last_error' => null,
            ]);

            return $this->refresh($mailbox->fresh());
        }

        $response = $this->successful($this->client()->post('/domains', [
            'name' => $domain,
            'region' => (string) config('services.resend.domain_region', 'eu-west-1'),
            'capabilities' => ['sending' => 'enabled', 'receiving' => 'disabled'],
        ]));

        return $this->synchronize($mailbox, (array) $response->json());
    }

    public function verify(MailboxAccount $mailbox): MailboxAccount
    {
        $this->ensureAvailable();
        $this->ensureRegistered($mailbox);
        $this->successful($this->client()->post('/domains/'.rawurlencode((string) $mailbox->resend_domain_id).'/verify'));

        return $this->refresh($mailbox);
    }

    public function refresh(MailboxAccount $mailbox): MailboxAccount
    {
        $this->ensureAvailable();
        $this->ensureRegistered($mailbox);
        $response = $this->successful($this->client()->get('/domains/'.rawurlencode((string) $mailbox->resend_domain_id)));

        return $this->synchronize($mailbox, (array) $response->json());
    }

    public function domainFromAddress(string $address): string
    {
        $position = strrpos($address, '@');
        $domain = $position === false ? '' : mb_strtolower(trim(substr($address, $position + 1)));
        if ($domain === '' || filter_var($domain, FILTER_VALIDATE_IP)) {
            throw new RuntimeException('L’indirizzo mittente non contiene un dominio registrabile su Resend.');
        }

        return $domain;
    }

    private function synchronize(MailboxAccount $mailbox, array $payload): MailboxAccount
    {
        $status = mb_strtolower((string) ($payload['status'] ?? 'pending'));
        $verified = $status === 'verified';
        $records = collect((array) ($payload['records'] ?? []))
            ->filter(fn (mixed $record): bool => is_array($record))
            ->map(fn (array $record): array => array_filter([
                'record' => isset($record['record']) ? (string) $record['record'] : null,
                'name' => isset($record['name']) ? (string) $record['name'] : null,
                'type' => isset($record['type']) ? (string) $record['type'] : null,
                'value' => isset($record['value']) ? (string) $record['value'] : null,
                'ttl' => isset($record['ttl']) ? (string) $record['ttl'] : null,
                'priority' => isset($record['priority']) ? (int) $record['priority'] : null,
                'status' => isset($record['status']) ? (string) $record['status'] : null,
            ], fn (mixed $value): bool => $value !== null))
            ->values()->all();

        $mailbox->update([
            'resend_domain_id' => (string) ($payload['id'] ?? $mailbox->resend_domain_id),
            'resend_domain_name' => mb_strtolower((string) ($payload['name'] ?? $mailbox->resend_domain_name ?? $this->domainFromAddress($mailbox->from_address))),
            'resend_domain_status' => $status,
            'resend_dns_records' => $records ?: $mailbox->resend_dns_records,
            'resend_last_checked_at' => now(),
            'resend_last_error' => null,
            'domain_verification_status' => $verified ? 'verified' : 'pending',
            'domain_verified_at' => $verified ? ($mailbox->domain_verified_at ?: now()) : null,
            'domain_verified_by' => null,
        ]);

        return $mailbox->fresh();
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.resend.api_url', 'https://api.resend.com'), '/'))
            ->withToken((string) config('services.resend.key'))
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('services.resend.api_timeout', 15));
    }

    private function successful(Response $response): Response
    {
        if ($response->successful()) {
            return $response;
        }

        $message = (string) ($response->json('message') ?: $response->json('error.message') ?: $response->body());
        $message = mb_substr(trim(strip_tags($message)), 0, 1000);
        throw new RuntimeException('Resend ha rifiutato la richiesta'.($message !== '' ? ': '.$message : '.').' (HTTP '.$response->status().')');
    }

    private function ensureAvailable(): void
    {
        $configuration = $this->configuration();
        if (! $configuration['enabled']) {
            throw new RuntimeException('La verifica automatica dei domini Resend non è abilitata sul server.');
        }
        if (! $configuration['configured']) {
            throw new RuntimeException('La chiave API Resend non è configurata sul server.');
        }
    }

    private function ensureRegistered(MailboxAccount $mailbox): void
    {
        if (blank($mailbox->resend_domain_id)) {
            throw new RuntimeException('Registra prima il dominio mittente su Resend.');
        }
    }
}
