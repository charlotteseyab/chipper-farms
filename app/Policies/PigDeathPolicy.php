<?php

namespace App\Policies;

use App\Models\PigDeath;
use App\Models\User;
use App\Policies\Concerns\EnforcesOrganizationScope;

/**
 * Policy for PigDeath.
 *
 * Phase 0.8: pig_deaths.organization_id added in Batch D. Per-instance
 * methods enforce same-org before role check.
 *
 * ROLE MATRIX
 * -----------
 *   viewAny / view  admin, manager, dataentry, accountant
 *   create          admin, manager, dataentry  — ground staff record deaths
 *   update          admin, manager
 *   delete          admin only — biological asset write-offs are material
 *   export          admin, manager, accountant
 *
 * SUPERUSER bypasses via Gate::before.
 */
class PigDeathPolicy
{
    use EnforcesOrganizationScope;

    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isManager() || $user->isDataEntry() || $user->isAccountant();
    }

    public function view(User $user, PigDeath $death): bool
    {
        return $this->sameOrganization($user, $death)
            && ($user->isAdmin() || $user->isManager() || $user->isDataEntry() || $user->isAccountant());
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isManager() || $user->isDataEntry();
    }

    public function update(User $user, PigDeath $death): bool
    {
        return $this->sameOrganization($user, $death)
            && ($user->isAdmin() || $user->isManager());
    }

    public function delete(User $user, PigDeath $death): bool
    {
        return $this->sameOrganization($user, $death) && $user->isAdmin();
    }

    public function export(User $user): bool
    {
        return $user->isAdmin() || $user->isManager() || $user->isAccountant();
    }
}
