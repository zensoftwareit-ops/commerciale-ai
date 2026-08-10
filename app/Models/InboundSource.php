<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class InboundSource extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $fillable = ['organization_id', 'name', 'key', 'secret', 'allowed_domains', 'endpoint_token_hash', 'is_active'];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return ['secret' => 'encrypted', 'allowed_domains' => 'array', 'is_active' => 'boolean'];
    }
}
