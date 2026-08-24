<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Activity extends Model
{
    use BelongsToOrganization, HasUuid;

    public $timestamps = false;

    protected $fillable = ['organization_id', 'lead_id', 'actor_id', 'type', 'title', 'data', 'occurred_at'];

    protected function casts(): array
    {
        return ['data' => 'array', 'occurred_at' => 'datetime'];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
