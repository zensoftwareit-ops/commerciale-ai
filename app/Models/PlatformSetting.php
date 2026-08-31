<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    protected $fillable = [
        'system_mail_from_address',
        'system_mail_from_name',
        'last_automation_started_at',
        'last_automation_completed_at',
        'last_automation_status',
        'last_automation_summary',
        'last_automation_error',
        'last_backup_verified_at',
        'last_health_alerted_at',
        'last_health_alert_signature',
    ];

    protected function casts(): array
    {
        return [
            'last_automation_started_at' => 'datetime',
            'last_automation_completed_at' => 'datetime',
            'last_automation_summary' => 'array',
            'last_backup_verified_at' => 'datetime',
            'last_health_alerted_at' => 'datetime',
        ];
    }
}
