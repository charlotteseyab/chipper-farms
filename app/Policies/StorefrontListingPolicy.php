<?php

namespace App\Policies;

use App\Models\Storefront\StorefrontListing;
use App\Models\User;
use App\Policies\Concerns\EnforcesOrganizationScope;

/**
 * Policy for storefront listings (back-office management).
 *
 * Permission-driven (not role-hardcoded) to match the finance-module style:
 * gates rely on the storefront permissions seeded in StorefrontPermissionsSeeder.
 * Same-organization is enforced before any management action.
 *
 * Superuser bypasses via Gate::before (AuthServiceProvider).
 */
class StorefrontListingPolicy
{
    use EnforcesOrganizationScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_storefront_listings');
    }

    public function view(User $user, StorefrontListing $listing): bool
    {
        return $this->sameOrganization($user, $listing)
            && $user->hasPermission('view_storefront_listings');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('push_to_storefront')
            || $user->hasPermission('manage_storefront_listings');
    }

    public function update(User $user, StorefrontListing $listing): bool
    {
        return $this->sameOrganization($user, $listing)
            && $user->hasPermission('manage_storefront_listings');
    }

    public function publish(User $user, StorefrontListing $listing): bool
    {
        return $this->update($user, $listing);
    }

    public function delete(User $user, StorefrontListing $listing): bool
    {
        return $this->sameOrganization($user, $listing)
            && $user->hasPermission('manage_storefront_listings');
    }
}
