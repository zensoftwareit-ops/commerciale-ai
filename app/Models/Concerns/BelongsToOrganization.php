<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToOrganization
{
    protected static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope('organization', function (Builder $builder): void {
            if ($organizationId = app(TenantContext::class)->id()) {
                $builder->where($builder->qualifyColumn('organization_id'), $organizationId);
            }
        });

        static::creating(function ($model): void {
            $model->organization_id ??= app(TenantContext::class)->id();
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
