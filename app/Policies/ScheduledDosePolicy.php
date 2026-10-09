<?php

namespace App\Policies;

use App\Models\ScheduledDose;
use App\Models\User;
use App\Policies\Concerns\EnforcesOrganizationScope;

/** Whoever records doses can also mark a scheduled one missed or cancelled. */
class ScheduledDosePolicy
{
    use EnforcesOrganizationScope;

    public function close(User $user, ScheduledDose $dose): bool
    {
        return $this->sameOrganization($user, $dose)
            && $dose->status === ScheduledDose::SCHEDULED
            && $user->hasPermission('administer_medication');
    }
}
