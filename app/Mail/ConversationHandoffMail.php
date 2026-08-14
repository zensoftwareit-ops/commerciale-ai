<?php

namespace App\Mail;

use App\Models\CommercialNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class ConversationHandoffMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly CommercialNotification $commercialNotification) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[Commerciale AI] Intervento richiesto: '.$this->commercialNotification->lead?->name);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.conversation-handoff');
    }

    public function headers(): Headers
    {
        return new Headers(text: [
            'Auto-Submitted' => 'auto-generated',
            'X-Auto-Response-Suppress' => 'All',
        ]);
    }
}

