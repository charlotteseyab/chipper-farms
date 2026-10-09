<?php

namespace App\Policies;

use App\Models\HealthRecord;
use App\Models\User;
use App\Policies\Concerns\EnforcesOrganizationScope;

/**
 * Policy for HealthRecord (one pig's health case).
 *
 * Every instance check confirms the record's pig belongs to the user's
 * organisation before looking at permissions. A voided record is history:
 * it can be read but no longer changed.
 *
 * SUPERUSER bypasses via Gate::before.
 */
class HealthRecordPolicy
{
    use EnforcesOrganizationScope;

    public function view(User $user, HealthRecord $record): bool
    {
        return $this->sameOrganization($user, $record) && $user->hasPermission('view_health_records');
    }

    public function update(User $user, HealthRecord $record): bool
    {
        return $this->sameOrganization($user, $record)
            && ! $record->isVoided()
            && $user->hasPermission('manage_health_records');
    }

    /** Follow-ups, resolving and closing: part of looking after the case. */
    public function followUp(User $user, HealthRecord $record): bool
    {
        return $this->update($user, $record) && $record->case_status !== 'closed';
    }

    public function reopen(User $user, HealthRecord $record): bool
    {
        return $this->sameOrganization($user, $record) && ! $record->isVoided()
            && in_array($record->case_status, ['resolved', 'closed'], true) && $user->hasPermission('manage_health_records');
    }

    /** Replaces deletion: the record stays on the pig's history, marked void. */
    public function void(User $user, HealthRecord $record): bool
    {
        return $this->sameOrganization($user, $record)
            && ! $record->isVoided()
            && $user->hasPermission('correct_health_records');
    }
}
