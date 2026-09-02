<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappMessage extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $fillable = [
        'organization_id', 'whatsapp_account_id', 'lead_id', 'lead_reply_id', 'external_message_id',
        'direction', 'type', 'status', 'from_number', 'to_number', 'body', 'payload',
        'received_at', 'processed_at', 'sent_at', 'failed_at', 'last_error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array', 'received_at' => 'datetime', 'processed_at' => 'datetime',
            'sent_at' => 'datetime', 'failed_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo { return $this->belongsTo(WhatsappAccount::class, 'whatsapp_account_id'); }
    public function lead(): BelongsTo { return $this->belongsTo(Lead::class); }
    public function reply(): BelongsTo { return $this->belongsTo(LeadReply::class, 'lead_reply_id'); }
}
