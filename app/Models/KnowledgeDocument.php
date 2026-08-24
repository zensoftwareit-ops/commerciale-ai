<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeDocument extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $fillable = ['organization_id', 'updated_by', 'title', 'type', 'content', 'structured_data', 'status'];

    protected function casts(): array
    {
        return ['structured_data' => 'array'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
