<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use App\Support\OrganizationContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope('organization', function (Builder $query): void {
            if (OrganizationContext::isBound()) {
                $query->where($query->qualifyColumn('organization_id'), OrganizationContext::id());
            }
        });

        static::creating(function (Model $model): void {
            if ($model->getAttribute('organization_id') === null && OrganizationContext::isBound()) {
                $model->setAttribute('organization_id', OrganizationContext::id());
            }
        });
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
