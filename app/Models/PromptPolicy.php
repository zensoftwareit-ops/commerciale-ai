<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class PromptPolicy extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $fillable = ['organization_id', 'operation', 'version', 'instructions', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
