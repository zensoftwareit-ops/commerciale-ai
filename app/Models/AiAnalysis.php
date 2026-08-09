<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAnalysis extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $fillable = ['organization_id', 'lead_id', 'ai_run_id', 'version', 'summary', 'intent', 'requested_services', 'budget', 'urgency', 'ai_score', 'rule_score', 'final_score', 'priority', 'missing_information', 'risk_flags', 'recommended_next_action', 'qualification_questions', 'confidence', 'corrected_by', 'corrected_at'];

    protected function casts(): array
    {
        return ['requested_services' => 'array', 'budget' => 'array', 'missing_information' => 'array', 'risk_flags' => 'array', 'qualification_questions' => 'array', 'confidence' => 'float', 'corrected_at' => 'datetime'];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AiRun::class, 'ai_run_id');
    }
}
