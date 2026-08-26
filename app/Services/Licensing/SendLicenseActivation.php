<?php

namespace App\Services\Licensing;

use App\Models\User;
use App\Notifications\LicenseActivationNotification;
use Illuminate\Support\Facades\Password;

class SendLicenseActivation
{
    public function handle(User $user): void
    {
        $broker = Password::broker();
        $broker->deleteToken($user);
        $user->notify(new LicenseActivationNotification($broker->createToken($user)));
    }
}
