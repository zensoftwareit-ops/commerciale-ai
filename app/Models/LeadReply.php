<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LeadReply extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $fillable = [
        'organization_id', 'lead_id', 'ai_analysis_id', 'ai_run_id', 'status', 'channel', 'reply_kind',
        'delivery_mode', 'automation_eligible', 'automation_blockers',
        'automation_attempts', 'automation_next_attempt_at', 'automation_failed_at',
        'outbound_message_id', 'parent_message_id', 'recipient', 'sender_address', 'sender_name', 'reply_to_address', 'subject', 'body',
        'follow_up_at', 'follow_up_cancelled_at', 'approved_by', 'approved_at', 'sent_at', 'last_error',
    ];

    protected function casts(): array
    {
        return [
            'follow_up_at' => 'datetime', 'follow_up_cancelled_at' => 'datetime',
            'approved_at' => 'datetime', 'sent_at' => 'datetime',
            'automation_eligible' => 'boolean', 'automation_blockers' => 'array',
            'automation_attempts' => 'integer', 'automation_next_attempt_at' => 'datetime',
            'automation_failed_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(AiAnalysis::class, 'ai_analysis_id');
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AiRun::class, 'ai_run_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function quotation(): HasOne
    {
        return $this->hasOne(Quotation::class);
    }

    public function ensureOutboundMessageId(): string
    {
        if (filled($this->outbound_message_id)) {
            return $this->outbound_message_id;
        }

        $address = (string) $this->sender_address;
        $domain = str_contains($address, '@') ? str($address)->afterLast('@')->value() : null;
        $domain ??= parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'commerciale-ai.local';
        $domain = preg_replace('/[^a-z0-9.-]/i', '', $domain) ?: 'commerciale-ai.local';
        $this->outbound_message_id = 'commerciale-ai-'.$this->id.'@'.$domain;
        $this->saveQuietly();

        return $this->outbound_message_id;
    }
}
