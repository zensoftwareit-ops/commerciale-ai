<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class AiRun extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $fillable = ['organization_id', 'lead_id', 'operation', 'status', 'provider', 'model', 'policy_version', 'input_context', 'output', 'error_code', 'error_message', 'input_units', 'output_units', 'estimated_cost', 'started_at', 'completed_at'];

    protected function casts(): array
    {
        return ['input_context' => 'array', 'output' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime', 'estimated_cost' => 'decimal:6'];
    }
}
