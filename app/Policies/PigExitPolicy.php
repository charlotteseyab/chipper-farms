<?php

namespace App\Policies;

use App\Models\PigExit;
use App\Models\User;
use App\Policies\Concerns\EnforcesOrganizationScope;

/**
 * Policy for PigExit (transfer out and cull).
 *
 * ROLE MATRIX
 * -----------
 *   viewAny / view  admin, manager, dataentry, accountant
 *   create          admin, manager — removing an animal from the herd is a
 *                   management decision, unlike recording a death
 *
 * SUPERUSER bypasses via Gate::before.
 */
class PigExitPolicy
{
    use EnforcesOrganizationScope;

    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isManager() || $user->hasRoleName('manager') || $user->isDataEntry() || $user->isAccountant();
    }

    public function view(User $user, PigExit $exit): bool
    {
        return $this->sameOrganization($user, $exit) && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isManager() || $user->hasRoleName('manager');
    }
}
