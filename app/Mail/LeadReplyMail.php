<?php

namespace App\Mail;

use App\Models\LeadReply;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

class LeadReplyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly LeadReply $reply,
        public readonly ?string $quotationPdfPath = null,
        public readonly ?string $quotationPdfFilename = null,
    ) {}

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

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        if (! $this->quotationPdfPath) return [];

        return [Attachment::fromStorageDisk('local', $this->quotationPdfPath)
            ->as($this->quotationPdfFilename ?: 'Preventivo.pdf')
            ->withMime('application/pdf')];
    }
}
