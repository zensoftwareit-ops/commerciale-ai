<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class PricingRule extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $fillable = ['organization_id', 'name', 'keywords', 'required_fields', 'minimum_price', 'maximum_price', 'includes', 'excludes', 'validity_days', 'is_active'];

    protected function casts(): array
    {
        return ['keywords' => 'array', 'required_fields' => 'array', 'minimum_price' => 'decimal:2', 'maximum_price' => 'decimal:2', 'validity_days' => 'integer', 'is_active' => 'boolean'];
    }
}
