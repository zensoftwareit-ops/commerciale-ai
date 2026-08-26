<?php

namespace App\Services\Mail;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Mail\Mailables\Address;
use RuntimeException;

class MailIdentity
{
    public function forUser(User $user): Address
    {
        $address = mb_strtolower(trim((string) ($user->mail_from_address ?: $user->email)));
        $name = trim((string) ($user->mail_from_name ?: $user->name));
        if (! filter_var($address, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Configura un indirizzo mittente valido nella pagina Account.');
        }

        return new Address($address, $name);
    }

    public function forOrganization(string $organizationId, ?int $preferredUserId = null): Address
    {
        $organization = Organization::query()->findOrFail($organizationId);
        $query = $organization->users();
        $user = $preferredUserId
            ? $query->whereKey($preferredUserId)->first()
            : $query->wherePivot('role', 'owner')->first();
        if (! $user) {
            throw new RuntimeException($preferredUserId
                ? 'L\'utente mittente non appartiene a questa organizzazione.'
                : 'L\'organizzazione non ha un owner configurato come mittente.');
        }

        return $this->forUser($user);
    }

    public function forPlatformOr(User $fallback): Address
    {
        $admin = User::query()->where('is_super_admin', true)->whereDoesntHave('organizations')->first();

        return $this->forUser($admin ?: $fallback);
    }
}
