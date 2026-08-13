<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    protected $fillable = [
        'organization_id', 'inbound_source_id', 'pipeline_stage_id', 'assigned_to',
        'external_id', 'source_label', 'name', 'email', 'email_normalized', 'phone',
        'phone_normalized', 'company', 'requested_service', 'request_data', 'consent_data',
        'operational_status', 'temperature', 'score', 'next_action_at', 'last_activity_at',
        'initial_automation_attempts', 'initial_automation_attempted_at',
        'initial_automation_completed_at', 'initial_automation_error',
    ];

    protected function casts(): array
    {
        return [
            'request_data' => 'array', 'consent_data' => 'array', 'score' => 'integer',
            'next_action_at' => 'datetime', 'last_activity_at' => 'datetime',
            'initial_automation_attempts' => 'integer', 'initial_automation_attempted_at' => 'datetime',
            'initial_automation_completed_at' => 'datetime',
        ];
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'pipeline_stage_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(InboundSource::class, 'inbound_source_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class)->latest('occurred_at');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(LeadContact::class);
    }

    public function analyses(): HasMany
    {
        return $this->hasMany(AiAnalysis::class)->latest('version');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(LeadReply::class)->latest()->orderByDesc('id');
    }

    public function inboundEmails(): HasMany
    {
        return $this->hasMany(InboundEmail::class)->latest('received_at');
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class)->latest('version');
    }
}
