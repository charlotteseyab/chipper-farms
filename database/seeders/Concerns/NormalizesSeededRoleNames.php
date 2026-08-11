<?php

namespace Database\Seeders\Concerns;

use App\Models\Role;
use App\Models\User;

/**
 * One convention for resolving the role a permission is granted to (Phase 16, §49).
 *
 * WHAT WENT WRONG WITHOUT IT
 * --------------------------
 * `manager` and `farmmanager` are the same product role. `User::normalizeRoleName()`
 * maps the former to the latter on every write, and `manager` is not in
 * `User::ALLOWED_ROLE_NAMES`, so no user created through the application can hold
 * it. The seeders did not know that, and settled into three different conventions:
 *
 *   - `StorefrontPermissionsSeeder` aliased manager → farmmanager, so its grants
 *     landed only on `farmmanager`.
 *   - Most others granted to BOTH names, so those permissions worked either way.
 *   - `FinancePermissionsSeeder` granted `view_finance` to `manager` ALONE.
 *
 * The result was a farm manager created today getting 403 on the finance overview
 * while a legacy user still carrying `role = 'manager'` got 200 — for a role §49
 * says should see it. The reverse held for eight storefront, expense and purchase
 * permissions the legacy user could not reach.
 *
 * Grants are silent when they miss: nothing errors, a role row exists, the
 * permission attaches to it, and a person is quietly locked out of their job.
 *
 * THE RULE
 * --------
 * Seeded role names go through the same normalisation user records do. A seeder
 * may keep writing `'manager'` in its own list — that is the vocabulary the farm
 * uses — and the grant lands on the row a user can actually hold.
 *
 * Roles outside `ALLOWED_ROLE_NAMES` are passed through unchanged rather than
 * dropped: `mill_operator` is seeded that way and is a real farm job, even though
 * it is not one of §49's seven columns. Silently discarding its grants would be a
 * second, quieter version of the bug this trait exists to fix.
 */
trait NormalizesSeededRoleNames
{
    /**
     * The role row a seeded grant should attach to, or null if it does not exist.
     *
     * Does not create the row. Most seeders run after `DatabaseSeeder` has made
     * the roles, and a missing role there means a typo — inventing the row would
     * hide it.
     */
    protected function seededRole(string $roleName): ?Role
    {
        return Role::where('name', $this->normalizedRoleName($roleName))->first();
    }

    /**
     * As above, but creates the row. For seeders that legitimately introduce a
     * role rather than granting against an established one.
     */
    protected function seededRoleOrCreate(string $roleName): Role
    {
        return Role::firstOrCreate(['name' => $this->normalizedRoleName($roleName)]);
    }

    protected function normalizedRoleName(string $roleName): string
    {
        return User::normalizeRoleName($roleName) ?? $roleName;
    }
}
