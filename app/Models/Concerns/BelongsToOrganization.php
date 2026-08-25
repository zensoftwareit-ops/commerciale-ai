<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

trait BelongsToOrganization
{
    protected static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope('organization', function (Builder $builder): void {
            $organizationId = app(TenantContext::class)->id();
            if (! $organizationId) {
                $builder->whereRaw('1 = 0');
                return;
            }

            $builder->where($builder->qualifyColumn('organization_id'), $organizationId);
        });

        static::creating(function ($model): void {
            $organizationId = app(TenantContext::class)->id();
            $model->organization_id ??= $organizationId;

            if (! $model->organization_id) {
                throw new LogicException('A tenant-scoped record requires an organization.');
            }
            if ($organizationId && (string) $model->organization_id !== (string) $organizationId) {
                throw new LogicException('A tenant-scoped record cannot be created for another organization.');
            }
        });

        static::saving(function ($model): void {
            // New models receive and validate organization_id in the creating event.
            if (! $model->exists) return;

            if ($model->isDirty('organization_id')) {
                throw new LogicException('The organization of a tenant-scoped record is immutable.');
            }

            $organizationId = app(TenantContext::class)->id();
            if ($organizationId && (string) $model->organization_id !== (string) $organizationId) {
                throw new LogicException('A tenant-scoped record cannot be changed from another organization.');
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
