<?php

namespace App\Notifications;

use App\Services\Mail\MailIdentity;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class LicenseActivationNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $identity = app(MailIdentity::class)->forPlatform();

        return (new MailMessage)
            ->from($identity->address, $identity->name)
            ->subject('La tua licenza Daria è attiva')
            ->greeting('Ciao '.$notifiable->name.',')
            ->line('La tua licenza Daria è stata attivata e il tuo account è pronto.')
            ->action('Imposta la password', $this->resetUrl($notifiable))
            ->line('Se non hai richiesto questa attivazione, puoi ignorare il messaggio.');
    }
}
