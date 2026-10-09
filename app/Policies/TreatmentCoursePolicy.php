<?php

namespace App\Policies;

use App\Models\TreatmentCourse;
use App\Models\User;
use App\Policies\Concerns\EnforcesOrganizationScope;

/** Treatment and preventive courses. Prescribing, amending and stopping need manage_treatment_courses. */
class TreatmentCoursePolicy
{
    use EnforcesOrganizationScope;

    public function view(User $user, TreatmentCourse $course): bool
    {
        return $this->sameOrganization($user, $course) && $user->hasPermission('view_health_records');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage_treatment_courses');
    }

    public function change(User $user, TreatmentCourse $course): bool
    {
        return $this->sameOrganization($user, $course) && $course->status === 'active' && $user->hasPermission('manage_treatment_courses');
    }
}
