<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Organization extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'name', 'slug', 'billing_account_ref', 'timezone', 'locale', 'status',
        'onboarding_completed_at', 'suspended_at', 'suspension_reason',
    ];

    protected function casts(): array
    {
        return ['onboarding_completed_at' => 'datetime', 'suspended_at' => 'datetime'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(OrganizationSetting::class);
    }

    public function mailboxes(): HasMany
    {
        return $this->hasMany(MailboxAccount::class);
    }

    public function licenses(): HasMany
    {
        return $this->hasMany(License::class);
    }

    public function activeLicense(): ?License
    {
        $license = $this->licenses()->with('plan')->latest('created_at')->first();

        return $license?->isUsable() ? $license : null;
    }
}
