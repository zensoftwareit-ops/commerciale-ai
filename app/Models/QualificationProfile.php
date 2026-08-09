<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class QualificationProfile extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $fillable = ['organization_id', 'rules', 'ai_weight', 'rule_weight', 'is_active'];

    protected function casts(): array
    {
        return ['rules' => 'array', 'ai_weight' => 'integer', 'rule_weight' => 'integer', 'is_active' => 'boolean'];
    }
}
