<?php

namespace App\Policies;

use App\Models\MedicationDosingEvent;
use App\Models\User;
use App\Policies\Concerns\EnforcesOrganizationScope;

/**
 * A recorded dose (one submission, one stock deduction). Recording needs
 * administer_medication; correcting or voiding puts stock back and changes
 * withdrawal, so it needs correct_health_records.
 */
class MedicationDosingEventPolicy
{
    use EnforcesOrganizationScope;

    public function view(User $user, MedicationDosingEvent $event): bool
    {
        return $this->sameOrganization($user, $event) && $user->hasPermission('view_health_records');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('administer_medication');
    }

    public function correct(User $user, MedicationDosingEvent $event): bool
    {
        return $this->sameOrganization($user, $event)
            && $event->status === 'recorded'
            && $user->hasPermission('correct_health_records');
    }
}
