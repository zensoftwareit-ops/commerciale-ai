<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class UsageRecord extends Model
{
    use BelongsToOrganization, HasUuid;

    public $timestamps = false;

    protected $fillable = ['organization_id', 'ai_run_id', 'operation', 'provider', 'model', 'input_units', 'output_units', 'estimated_cost', 'occurred_at'];

    protected function casts(): array
    {
        return ['estimated_cost' => 'decimal:6', 'occurred_at' => 'datetime'];
    }
}
