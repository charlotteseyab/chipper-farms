<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Weaning;
use App\Policies\Concerns\EnforcesOrganizationScope;

/**
 * Policy for Weaning.
 *
 * Weaning does not yet carry organization_id. EnforcesOrganizationScope
 * is applied with graceful pass-through.
 *
 * ROLE MATRIX
 * -----------
 *   viewAny / view       admin, manager, dataentry, accountant
 *   create (record)      admin, manager, dataentry
 *   update               admin, manager, dataentry
 *   delete               admin, manager — affects lineage; data entry blocked
 *
 * SUPERUSER bypasses via Gate::before.
 */
class WeaningPolicy
{
    use EnforcesOrganizationScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_breeding');
    }

    public function view(User $user, Weaning $weaning): bool
    {
        return $this->sameOrganization($user, $weaning)
            && $user->hasPermission('view_breeding');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('record_breeding');
    }

    public function update(User $user, Weaning $weaning): bool
    {
        return $this->sameOrganization($user, $weaning)
            && $user->hasPermission('record_breeding');
    }

    public function delete(User $user, Weaning $weaning): bool
    {
        return $this->sameOrganization($user, $weaning)
            && $user->hasPermission('manage_breeding');
    }
}
