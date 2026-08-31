<?php

namespace App\Services\Mail;

use App\Models\Organization;
use App\Models\MailboxAccount;
use App\Models\PlatformSetting;
use Illuminate\Mail\Mailables\Address;
use RuntimeException;

class MailIdentity
{
    /** @return array{from: Address, reply_to: Address, mailbox: MailboxAccount} */
    public function commercialForOrganization(string $organizationId): array
    {
        Organization::query()->findOrFail($organizationId);
        $mailbox = MailboxAccount::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->oldest('created_at')
            ->first();
        if (! $mailbox) {
            throw new RuntimeException('Configura e attiva Email Daria nelle impostazioni dell’organizzazione.');
        }
        $address = mb_strtolower(trim((string) $mailbox->from_address));
        $name = trim((string) $mailbox->from_name);
        $replyTo = mb_strtolower(trim((string) ($mailbox->reply_to_address ?: $address)));
        if (! filter_var($address, FILTER_VALIDATE_EMAIL) || $name === '') {
            throw new RuntimeException('Configura mittente e nome nella sezione Email Daria.');
        }
        if (! filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Configura un indirizzo Reply-To valido nella sezione Email Daria.');
        }

        return [
            'from' => new Address($address, $name),
            'reply_to' => new Address($replyTo, $name),
            'mailbox' => $mailbox,
        ];
    }

    public function forOrganization(string $organizationId): Address
    {
        return $this->commercialForOrganization($organizationId)['from'];
    }

    public function forPlatform(): Address
    {
        $settings = PlatformSetting::query()->find(1);
        $address = mb_strtolower(trim((string) $settings?->system_mail_from_address));
        $name = trim((string) $settings?->system_mail_from_name);
        if (! filter_var($address, FILTER_VALIDATE_EMAIL) || $name === '') {
            throw new RuntimeException('Configura il mittente delle email di sistema nel pannello amministrativo.');
        }

        return new Address($address, $name);
    }
}
