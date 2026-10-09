<?php

namespace App\Policies;

use App\Models\Medicine;
use App\Models\User;
use App\Policies\Concerns\EnforcesOrganizationScope;

/** The medicine catalogue and its stock: anyone with health access reads it; manage_medicine_catalogue edits it, receives batches and adjusts stock. */
class MedicinePolicy
{
    use EnforcesOrganizationScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_health_records');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage_medicine_catalogue');
    }

    public function update(User $user, Medicine $medicine): bool
    {
        return $this->sameOrganization($user, $medicine) && $user->hasPermission('manage_medicine_catalogue');
    }

    public function view(User $user, Medicine $medicine): bool
    {
        return $this->sameOrganization($user, $medicine) && $user->hasPermission('view_health_records');
    }

    /** Receive batches and adjust or count stock. */
    public function manageStock(User $user, Medicine $medicine): bool
    {
        return $this->update($user, $medicine);
    }
}
