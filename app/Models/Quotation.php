<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Quotation extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $fillable = ['organization_id', 'lead_id', 'pricing_rule_id', 'lead_reply_id', 'version', 'minimum_price', 'maximum_price', 'estimated_price', 'currency', 'confidence', 'complexity_score', 'scope_title', 'scope_description', 'line_items', 'assumptions', 'estimate_rationale', 'input_snapshot', 'missing_fields', 'auto_send_eligible', 'automation_blockers', 'document_year', 'document_sequence', 'document_number', 'valid_until', 'pdf_path', 'pdf_generated_at'];

    protected function casts(): array
    {
        return ['minimum_price' => 'decimal:2', 'maximum_price' => 'decimal:2', 'estimated_price' => 'decimal:2', 'confidence' => 'integer', 'complexity_score' => 'integer', 'line_items' => 'array', 'assumptions' => 'array', 'input_snapshot' => 'array', 'missing_fields' => 'array', 'auto_send_eligible' => 'boolean', 'automation_blockers' => 'array', 'document_year' => 'integer', 'document_sequence' => 'integer', 'valid_until' => 'date', 'pdf_generated_at' => 'datetime'];
    }

    public function rule(): BelongsTo { return $this->belongsTo(PricingRule::class, 'pricing_rule_id'); }
    public function lead(): BelongsTo { return $this->belongsTo(Lead::class); }
    public function reply(): BelongsTo { return $this->belongsTo(LeadReply::class, 'lead_reply_id'); }
}
