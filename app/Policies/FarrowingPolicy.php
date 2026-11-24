<?php

namespace App\Policies;

use App\Models\Farrowing;
use App\Models\User;
use App\Policies\Concerns\EnforcesOrganizationScope;

/**
 * Policy for Farrowing.
 *
 * Farrowing does not yet carry organization_id. EnforcesOrganizationScope
 * is applied with graceful pass-through.
 *
 * ROLE MATRIX
 * -----------
 *   viewAny / view       admin, manager, dataentry, accountant
 *   create (record)      admin, manager, dataentry
 *   update               admin, manager, dataentry
 *   delete               admin, manager — affects piglet lineage; data entry blocked
 *
 * SUPERUSER bypasses via Gate::before.
 */
class FarrowingPolicy
{
    use EnforcesOrganizationScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_breeding');
    }

    public function view(User $user, Farrowing $farrowing): bool
    {
        return $this->sameOrganization($user, $farrowing)
            && $user->hasPermission('view_breeding');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('record_breeding');
    }

    public function update(User $user, Farrowing $farrowing): bool
    {
        return $this->sameOrganization($user, $farrowing)
            && $user->hasPermission('record_breeding');
    }

    public function delete(User $user, Farrowing $farrowing): bool
    {
        return $this->sameOrganization($user, $farrowing)
            && $user->hasPermission('manage_breeding');
    }
}
