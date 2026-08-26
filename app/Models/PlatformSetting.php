<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    protected $fillable = [
        'system_mail_from_address',
        'system_mail_from_name',
    ];
}
