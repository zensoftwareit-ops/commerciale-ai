<?php

namespace App\Services\Licensing;

use App\Models\User;
use App\Notifications\LicenseActivationNotification;
use App\Services\Mail\OutboundMailTransport;
use Illuminate\Support\Facades\Password;

class SendLicenseActivation
{
    public function __construct(private readonly OutboundMailTransport $transport) {}

    public function handle(User $user): void
    {
        $this->transport->ensureDeliverable();
        $broker = Password::broker();
        $broker->deleteToken($user);
        $user->notify(new LicenseActivationNotification($broker->createToken($user)));
    }
}
