<?php

namespace App\Policies\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared tenant-scope guard for policies.
 *
 * Phase 0.1 introduced organization_id on users; Batch D added
 * organization_id to pig_sales, pig_deaths, purchase_requests,
 * feed_batches, raw_material_stock_logs.
 *
 * Every per-instance policy method (view/update/delete) calls
 * sameOrganization($user, $model) BEFORE checking role permissions.
 * If the user and the resource belong to different organizations the
 * action is denied — even if the user's role would otherwise allow it.
 *
 * Behaviour for resources without organization_id:
 *   - Returns true (no scope to enforce). This keeps the trait safe to
 *     use on legacy resources during the migration period.
 *   - When organization_id later becomes NOT NULL, every row will have
 *     a value and the check becomes effective everywhere.
 *
 * Behaviour for users without organization_id:
 *   - Returns false (defensive: an unscoped user shouldn't access scoped data).
 *   - This shouldn't happen post-Phase-0.1 backfill, but defends against
 *     fresh seed data or test fixtures.
 *
 * Superuser bypass (Gate::before in AuthServiceProvider) runs BEFORE the
 * policy, so superusers skip this check entirely.
 */
trait EnforcesOrganizationScope
{
    /**
     * Returns true iff $user and $resource belong to the same organization.
     *
     * Pass $resource as a Model (any subclass that may carry organization_id),
     * a relation (BelongsTo), or null. Null returns true (no resource = no
     * scope to enforce — used for class-level methods like viewAny/create
     * where there's no specific instance yet).
     */
    protected function sameOrganization(User $user, ?Model $resource): bool
    {
        if (! $resource) {
            return true;
        }

        // Defensive: unscoped user fails closed.
        if (! $user->organization_id) {
            return false;
        }

        // Resource without organization_id (legacy row, or a model that
        // doesn't carry the column): the scope check passes. Role check
        // is the only enforcement until backfill catches up.
        if (! array_key_exists('organization_id', $resource->getAttributes())
            && ! property_exists($resource, 'organization_id')
            && ! method_exists($resource, 'getAttribute')) {
            return true;
        }

        $resourceOrg = $resource->getAttribute('organization_id');

        // If the resource genuinely has no organization_id (legacy unbacked
        // row), pass through. This is intentional — it means the scope
        // check is a tightening guard, never a regression. Once backfill
        // populates every row, this branch becomes unreachable.
        if ($resourceOrg === null) {
            return true;
        }

        return (int) $user->organization_id === (int) $resourceOrg;
    }
}
