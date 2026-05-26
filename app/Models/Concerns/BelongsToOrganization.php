<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trait for any Eloquent model that belongs to an Organization.
 *
 * Provides:
 *  - organization() relationship
 *  - forOrganization() query scope for explicit filtering
 *
 * Deliberately does NOT install a global scope. Reason: the existing codebase
 * contains many ad-hoc queries and dashboard aggregations written before org
 * scoping existed. Silently scoping all of them in one go risks changing
 * reporting outputs on a live system. Finance tables (built fresh from Phase 1
 * onwards) will apply a global scope in their own traits; operational tables
 * adopt scoping explicitly, controller-by-controller, as they are refactored.
 *
 * Usage:
 *
 *   class PigManagement extends Model
 *   {
 *       use \App\Models\Concerns\BelongsToOrganization;
 *   }
 *
 *   // Explicit scoping in a controller or query:
 *   PigManagement::forOrganization($orgId)->where(...)->get();
 */
trait BelongsToOrganization
{
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Scope a query to a specific organization.
     *
     * Accepts either an Organization model, an id, or null (no-op if null —
     * caller is responsible for handling that case explicitly).
     */
    public function scopeForOrganization(Builder $query, Organization|int|null $organization): Builder
    {
        if ($organization === null) {
            return $query;
        }

        $id = $organization instanceof Organization ? $organization->id : $organization;

        return $query->where($this->qualifyColumn('organization_id'), $id);
    }
}