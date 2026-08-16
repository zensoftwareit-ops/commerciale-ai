<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenseEvent extends Model
{
    use HasUuid;

    protected $fillable = ['license_id', 'external_event_id', 'source', 'type', 'payload_hash', 'status', 'payload', 'processed_at'];
    protected function casts(): array { return ['payload' => 'array', 'processed_at' => 'datetime']; }
    public function license(): BelongsTo { return $this->belongsTo(License::class); }
}

