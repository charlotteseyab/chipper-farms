<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VetVisit;
use App\Policies\Concerns\EnforcesOrganizationScope;

/**
 * Vet visits. Recording needs manage_vet_visits; finalising, and changing a
 * finalised visit, needs finalise_vet_visits. SUPERUSER bypasses via Gate::before.
 */
class VetVisitPolicy
{
    use EnforcesOrganizationScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_health_records');
    }

    public function view(User $user, VetVisit $visit): bool
    {
        return $this->sameOrganization($user, $visit) && $user->hasPermission('view_health_records');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage_vet_visits');
    }

    public function update(User $user, VetVisit $visit): bool
    {
        return $this->sameOrganization($user, $visit) && ! $visit->voided_at
            && ($visit->isFinalised() ? $user->hasPermission('finalise_vet_visits') : $user->hasPermission('manage_vet_visits'));
    }

    public function finalise(User $user, VetVisit $visit): bool
    {
        return $this->sameOrganization($user, $visit) && ! $visit->isFinalised() && $user->hasPermission('finalise_vet_visits');
    }
}
