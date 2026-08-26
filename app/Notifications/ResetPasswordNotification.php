<?php

namespace App\Notifications;

use App\Services\Mail\MailIdentity;
use Illuminate\Auth\Notifications\ResetPassword;

class ResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable)
    {
        $identity = app(MailIdentity::class)->forPlatformOr($notifiable);

        return parent::toMail($notifiable)->from($identity->address, $identity->name);
    }
}
