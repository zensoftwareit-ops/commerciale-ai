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
        'organization_id', 'lead_id', 'lead_reply_id', 'message_hash', 'message_id',
        'in_reply_to', 'imap_uid', 'from_address', 'from_name', 'subject', 'body', 'received_at',
    ];

    protected function casts(): array
    {
        return ['received_at' => 'datetime'];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function reply(): BelongsTo
    {
        return $this->belongsTo(LeadReply::class, 'lead_reply_id');
    }
}
