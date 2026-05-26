<?php

namespace App\Policies;

use App\Models\Mating;
use App\Models\User;
use App\Policies\Concerns\EnforcesOrganizationScope;

/**
 * Policy for Mating.
 *
 * Mating does not yet carry organization_id. EnforcesOrganizationScope is
 * applied — sameOrganization() passes through gracefully on resources
 * without organization_id, so retrofitting a column-add migration on
 * matings later activates true tenant scope without policy code changes.
 *
 * ROLE MATRIX
 * -----------
 *   viewAny / view        admin, manager, dataentry, accountant
 *   create (plan)         admin, manager, dataentry
 *   update                admin, manager, dataentry
 *   complete              admin, manager (breeding decision action)
 *   pregnancyCheck        admin, manager, dataentry (data entry captures vet result)
 *   delete                admin only
 *
 * SUPERUSER bypasses via Gate::before.
 */
class MatingPolicy
{
    use EnforcesOrganizationScope;

    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isManager() || $user->isDataEntry() || $user->isAccountant();
    }

    public function view(User $user, Mating $mating): bool
    {
        return $this->sameOrganization($user, $mating)
            && ($user->isAdmin() || $user->isManager() || $user->isDataEntry() || $user->isAccountant());
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isManager() || $user->isDataEntry();
    }

    public function update(User $user, Mating $mating): bool
    {
        return $this->sameOrganization($user, $mating)
            && ($user->isAdmin() || $user->isManager() || $user->isDataEntry());
    }

    public function complete(User $user, Mating $mating): bool
    {
        return $this->sameOrganization($user, $mating)
            && ($user->isAdmin() || $user->isManager());
    }

    public function pregnancyCheck(User $user, Mating $mating): bool
    {
        return $this->sameOrganization($user, $mating)
            && ($user->isAdmin() || $user->isManager() || $user->isDataEntry());
    }

    public function delete(User $user, Mating $mating): bool
    {
        return $this->sameOrganization($user, $mating) && $user->isAdmin();
    }
}
