<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class WebhookReceipt extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $fillable = ['organization_id', 'inbound_source_id', 'idempotency_key', 'payload_hash', 'source_domain', 'validation_mode', 'status', 'lead_id', 'processed_at'];

    protected function casts(): array
    {
        return ['processed_at' => 'datetime'];
    }
}
