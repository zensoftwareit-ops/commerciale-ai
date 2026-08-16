<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InboundEmail extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $fillable = [
        'organization_id', 'mailbox_account_id', 'lead_id', 'lead_reply_id', 'status', 'match_confidence',
        'match_reason', 'sender_differs', 'message_hash', 'message_id', 'in_reply_to',
        'imap_uid', 'from_address', 'from_name', 'subject', 'body', 'received_at',
        'linked_by', 'linked_at',
    ];

    protected function casts(): array
    {
        return ['sender_differs' => 'boolean', 'received_at' => 'datetime', 'linked_at' => 'datetime'];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(MailboxAccount::class, 'mailbox_account_id');
    }

    public function reply(): BelongsTo
    {
        return $this->belongsTo(LeadReply::class, 'lead_reply_id');
    }

    public function linkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_by');
    }
}

