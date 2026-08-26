<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class License extends Model
{
    use HasUuid;

    protected $fillable = ['license_plan_id', 'organization_id', 'owner_user_id', 'key', 'status', 'source', 'external_account_id', 'stripe_customer_id', 'stripe_subscription_id', 'starts_at', 'current_period_ends_at', 'ends_at', 'cancel_at_period_end', 'metadata'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'current_period_ends_at' => 'datetime', 'ends_at' => 'datetime', 'cancel_at_period_end' => 'boolean', 'metadata' => 'array'];
    }

    public function plan(): BelongsTo { return $this->belongsTo(LicensePlan::class, 'license_plan_id'); }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_user_id'); }
    public function events(): HasMany { return $this->hasMany(LicenseEvent::class); }

    public function isUsable(): bool
    {
        if (! in_array($this->status, ['active', 'trialing'], true)) return false;
        if ($this->current_period_ends_at && ! $this->current_period_ends_at->isFuture()) return false;

        return ! $this->ends_at || $this->ends_at->isFuture();
    }
}
