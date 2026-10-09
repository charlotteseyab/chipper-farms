<?php

namespace App\Policies;

use App\Models\MedicationAdministration;
use App\Models\User;
use App\Policies\Concerns\EnforcesOrganizationScope;

/**
 * Policy for MedicationAdministration (one dose given to one pig).
 *
 * Doses are never edited in place or deleted: a mistake is corrected (the
 * original is voided and a linked replacement saved) or voided. Both need
 * correct_health_records, because they change withdrawal traceability.
 *
 * SUPERUSER bypasses via Gate::before.
 */
class MedicationAdministrationPolicy
{
    use EnforcesOrganizationScope;

    public function view(User $user, MedicationAdministration $dose): bool
    {
        return $this->sameOrganization($user, $dose) && $user->hasPermission('view_health_records');
    }

    public function correct(User $user, MedicationAdministration $dose): bool
    {
        return $this->sameOrganization($user, $dose)
            && ! $dose->isVoided()
            // Doses that took stock are corrected on their dose record, so the stock goes back too.
            && $dose->medication_dosing_event_id === null
            && $user->hasPermission('correct_health_records');
    }

    public function void(User $user, MedicationAdministration $dose): bool
    {
        return $this->correct($user, $dose);
    }
}
