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
    /**
     * Stamp the current organisation on create when the caller has not set one
     * (CF-P0-005).
     *
     * Without this, every new row was a potential unscoped row, and the policy
     * layer's fail-closed check would deny access to records the application
     * itself had just created. Backfilling existing rows is only durable if new
     * ones stop arriving unscoped.
     *
     * Deliberately non-destructive: an explicitly supplied organization_id is
     * never overwritten (seeders, imports and cross-tenant admin tooling set it
     * directly), and when no organisation is bound — console commands, some
     * test fixtures — the attribute is left alone rather than guessed. The
     * policy layer still fails closed in that case, so this is a tightening of
     * the write path, not a substitute for the read guard.
     */
    public static function bootBelongsToOrganization(): void
    {
        static::creating(function ($model) {
            if (! empty($model->organization_id)) {
                return;
            }

            // Resolved lazily and defensively: this boots for every model using
            // the trait, including during migrations and console commands where
            // no organisation context exists.
            try {
                $organization = app(\App\Support\CurrentOrganization::class)->resolve();
            } catch (\Throwable) {
                return;
            }

            if ($organization !== null) {
                $model->organization_id = $organization->id;
            }
        });
    }

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