<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Applies tenant isolation to records whose organization is inherited through
 * an organization-owned parent (for example a weight through its pig).
 */
trait BelongsToOrganizationThroughRelation
{
    abstract protected static function organizationRelation(): string;

    public static function bootBelongsToOrganizationThroughRelation(): void
    {
        static::addGlobalScope('current_organization_relation', function (Builder $query): void {
            try {
                $organization = app(\App\Support\CurrentOrganization::class)->resolve();
            } catch (\Throwable) {
                return;
            }

            if ($organization !== null) {
                $query->whereHas(
                    static::organizationRelation(),
                    fn (Builder $related) => $related->withoutGlobalScope('current_organization')
                        ->where($related->getModel()->qualifyColumn('organization_id'), $organization->id),
                );
            }
        });
    }
}
