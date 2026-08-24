<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Quotation extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $fillable = ['organization_id', 'lead_id', 'pricing_rule_id', 'lead_reply_id', 'version', 'minimum_price', 'maximum_price', 'currency', 'confidence', 'input_snapshot', 'missing_fields', 'auto_send_eligible', 'automation_blockers'];

    protected function casts(): array
    {
        return ['minimum_price' => 'decimal:2', 'maximum_price' => 'decimal:2', 'confidence' => 'integer', 'input_snapshot' => 'array', 'missing_fields' => 'array', 'auto_send_eligible' => 'boolean', 'automation_blockers' => 'array'];
    }

    public function rule(): BelongsTo { return $this->belongsTo(PricingRule::class, 'pricing_rule_id'); }
    public function reply(): BelongsTo { return $this->belongsTo(LeadReply::class, 'lead_reply_id'); }
}
