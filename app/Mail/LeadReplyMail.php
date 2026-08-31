<?php

namespace App\Mail;

use App\Models\LeadReply;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class LeadReplyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly LeadReply $reply) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->reply->sender_address, $this->reply->sender_name),
            replyTo: [new Address($this->reply->reply_to_address ?: $this->reply->sender_address, $this->reply->sender_name)],
            subject: $this->reply->subject,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.lead-reply');
    }

    public function headers(): Headers
    {
        $parent = $this->reply->parent_message_id;

        return new Headers(
            messageId: $this->reply->outbound_message_id,
            references: $parent ? [$parent] : [],
            text: $parent ? ['In-Reply-To' => '<'.$parent.'>'] : [],
        );
    }
}
