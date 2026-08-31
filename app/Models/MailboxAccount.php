<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MailboxAccount extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $fillable = [
        'organization_id', 'name', 'from_address', 'from_name', 'reply_to_address',
        'host', 'port', 'encryption', 'validate_cert',
        'username', 'password', 'authentication', 'folder', 'is_active',
        'last_tested_at', 'last_outbound_tested_at', 'last_synced_at', 'last_error', 'last_outbound_error',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted', 'validate_cert' => 'boolean', 'is_active' => 'boolean',
            'last_tested_at' => 'datetime', 'last_outbound_tested_at' => 'datetime', 'last_synced_at' => 'datetime',
        ];
    }

    public function inboundEmails(): HasMany
    {
        return $this->hasMany(InboundEmail::class);
    }
}
