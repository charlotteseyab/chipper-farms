<?php

namespace App\Policies;

use App\Models\PigManagement;
use App\Models\User;
use App\Policies\Concerns\EnforcesOrganizationScope;

/**
 * Policy for PigManagement.
 *
 * TENANT SCOPING STATUS
 * ---------------------
 * The PigManagement table does NOT yet carry organization_id (Batch D
 * deliberately scoped finance-source tables only — pig_sales, pig_deaths,
 * purchase_requests, feed_batches, raw_material_stock_logs).
 *
 * The EnforcesOrganizationScope trait below is wired in nonetheless: its
 * sameOrganization() helper passes-through gracefully for resources that
 * don't carry organization_id, so the policy can be retrofitted to true
 * tenant scoping by simply adding a column-add migration on pigs_management
 * — no policy code change needed.
 *
 * Until that migration lands, tenant isolation for pig records relies on:
 *   - EnsureOrganizationContext middleware (rejects orgless users at the door)
 *   - Single-tenant production reality (one Chipper Farms org)
 *
 * SUPERUSER bypass via Gate::before.
 */
class PigManagementPolicy
{
    use EnforcesOrganizationScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_pigs');
    }

    public function view(User $user, PigManagement $pig): bool
    {
        return $this->sameOrganization($user, $pig)
            && $user->hasPermission('view_pigs');
    }

    /** A pig's health history, including after it was sold or died. */
    public function viewHealth(User $user, PigManagement $pig): bool
    {
        return $this->sameOrganization($user, $pig) && $user->hasPermission('view_health_records');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage_pigs');
    }

    public function update(User $user, PigManagement $pig): bool
    {
        return $this->sameOrganization($user, $pig)
            && $user->hasPermission('manage_pigs');
    }

    public function delete(User $user, PigManagement $pig): bool
    {
        return $this->sameOrganization($user, $pig)
            && $user->hasPermission('delete_pigs');
    }

    public function restore(User $user, PigManagement $pig): bool
    {
        return $this->sameOrganization($user, $pig) && $user->hasPermission('restore_pigs');
    }

    public function promote(User $user, PigManagement $pig): bool
    {
        return $this->sameOrganization($user, $pig)
            && $user->hasPermission('promote_pigs');
    }

    public function demote(User $user, PigManagement $pig): bool
    {
        return $this->promote($user, $pig);
    }
}
