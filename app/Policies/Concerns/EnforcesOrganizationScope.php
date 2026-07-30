<?php

namespace App\Policies\Concerns;

use App\Models\Concerns\ResolvesOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared tenant-scope guard for policies.
 *
 * FAIL-CLOSED CONTRACT (CF-P0-005)
 * --------------------------------
 * Every per-instance policy method (view/update/delete) calls
 * sameOrganization($user, $model) BEFORE checking role permissions. If the user
 * and the resource belong to different organisations the action is denied even
 * if the user's role would otherwise allow it.
 *
 * What changed and why
 * --------------------
 * This trait previously returned TRUE for any resource whose organization_id
 * was null or absent, described as a tightening guard that "never regresses"
 * and a branch that would "become unreachable once backfill catches up".
 *
 * Both premises were wrong in practice:
 *
 *  1. 41 of 128 tables have no organization_id column at all — including
 *     farrowing, matings, weanings, health_records, pig_weights,
 *     medication_administrations and piglet_deaths. For those the branch was
 *     not transitional, it was permanent. FarrowingPolicy, MatingPolicy and
 *     WeaningPolicy each call this helper, so each had NO tenant enforcement:
 *     an Organization B user could view and mutate Organization A breeding
 *     records and the policy would allow it.
 *
 *  2. A guard that passes on missing data is not a tightening guard. It is
 *     precisely the case an attacker reaches for, because unscoped legacy rows
 *     and unpopulated columns are exactly where cross-tenant access lives.
 *
 * The contract is now: resolve the resource's organisation; if it cannot be
 * resolved, DENY. Resolution uses the ResolvesOrganization interface where a
 * model implements it, which lets column-less models derive tenancy through
 * their parent pig instead of being exempted.
 *
 * Superuser bypass (Gate::before in AppServiceProvider) runs BEFORE the policy,
 * so superusers skip this check entirely and cannot be locked out.
 */
trait EnforcesOrganizationScope
{
    /**
     * Returns true iff $user and $resource provably belong to the same
     * organisation.
     *
     * $resource === null returns true: class-level abilities such as viewAny
     * and create have no instance to scope, and the caller's role check is the
     * control there.
     */
    protected function sameOrganization(User $user, ?Model $resource): bool
    {
        if (! $resource) {
            return true;
        }

        // An unscoped user can never match a scoped resource.
        if (! $user->organization_id) {
            return false;
        }

        $resourceOrg = $this->resolveResourceOrganizationId($resource);

        // Unresolvable tenancy is a denial, not an exemption. This is the
        // branch that used to return true.
        if ($resourceOrg === null) {
            return false;
        }

        return (int) $user->organization_id === $resourceOrg;
    }

    /**
     * Resolve the organisation of a resource.
     *
     * Order matters: an explicit ResolvesOrganization implementation wins,
     * because that is how a model without the column declares how to derive its
     * tenancy. Otherwise fall back to the column when the model carries one.
     */
    protected function resolveResourceOrganizationId(Model $resource): ?int
    {
        if ($resource instanceof ResolvesOrganization) {
            $resolved = $resource->resolveOrganizationId();

            return $resolved === null ? null : (int) $resolved;
        }

        $value = $resource->getAttribute('organization_id');

        return $value === null ? null : (int) $value;
    }
}
