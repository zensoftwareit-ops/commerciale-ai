<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LicensePlan extends Model
{
    use HasUuid;

    protected $fillable = ['name', 'slug', 'description', 'annual_price_cents', 'currency', 'seat_limit', 'monthly_lead_limit', 'monthly_ai_token_limit', 'features', 'stripe_price_id', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['features' => 'array', 'is_active' => 'boolean', 'annual_price_cents' => 'integer', 'seat_limit' => 'integer', 'monthly_lead_limit' => 'integer', 'monthly_ai_token_limit' => 'integer'];
    }

    public function licenses(): HasMany { return $this->hasMany(License::class); }
}

