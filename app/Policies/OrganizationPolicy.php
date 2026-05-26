<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;
use App\Policies\Concerns\EnforcesOrganizationScope;

/**
 * Policy for the Organization model.
 *
 * The Organization itself is the tenant boundary — sameOrganization()
 * compares user.organization_id to the target organization's id.
 *
 * Superuser bypass via Gate::before in AppServiceProvider.
 */
class OrganizationPolicy
{
    use EnforcesOrganizationScope;

    public function view(User $user, Organization $organization): bool
    {
        // Special case: organization is its own tenant identifier.
        if ((int) $user->organization_id !== (int) $organization->id) {
            return false;
        }

        return $user->isAdmin() || $user->isManager() || $user->isAccountant();
    }

    public function update(User $user, Organization $organization): bool
    {
        if ((int) $user->organization_id !== (int) $organization->id) {
            return false;
        }

        return $user->isAdmin();
    }

    public function delete(User $user, Organization $organization): bool
    {
        // No non-superuser role can delete an organization.
        return false;
    }
}
