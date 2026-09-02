<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsappAccount extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $fillable = [
        'organization_id', 'name', 'waba_id', 'phone_number_id', 'display_phone_number',
        'access_token', 'is_active', 'auto_reply_enabled', 'internal_test_only',
        'allowed_recipients', 'last_tested_at', 'last_error',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted', 'is_active' => 'boolean', 'auto_reply_enabled' => 'boolean',
            'internal_test_only' => 'boolean', 'allowed_recipients' => 'array', 'last_tested_at' => 'datetime',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsappMessage::class);
    }
}
