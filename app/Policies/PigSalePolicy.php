<?php

namespace App\Policies;

use App\Models\PigSale;
use App\Models\User;
use App\Policies\Concerns\EnforcesOrganizationScope;

/**
 * Policy for PigSale.
 *
 * Phase 0.8: organization_id now lives on pig_sales (additive Batch D
 * column). Per-instance methods enforce same-org before checking roles.
 *
 * ROLE MATRIX
 * -----------
 *   viewAny          admin, manager, dataentry, accountant
 *   view             admin, manager, dataentry, accountant
 *   create (record)  admin, manager
 *   update           admin, manager
 *   updatePayment    admin, manager, accountant  (accountant posts payments)
 *   delete           admin only
 *   export           admin, manager, accountant
 *
 * SUPERUSER bypasses via Gate::before (registered in AuthServiceProvider).
 */
class PigSalePolicy
{
    use EnforcesOrganizationScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_pig_sales');
    }

    public function view(User $user, PigSale $sale): bool
    {
        return $this->sameOrganization($user, $sale)
            && $user->hasPermission('view_pig_sales');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('record_pig_sales');
    }

    public function update(User $user, PigSale $sale): bool
    {
        return $this->sameOrganization($user, $sale)
            && $user->hasPermission('record_pig_sales');
    }

    public function updatePayment(User $user, PigSale $sale): bool
    {
        return $this->sameOrganization($user, $sale)
            && $user->hasPermission('update_sale_payment');
    }

    public function delete(User $user, PigSale $sale): bool
    {
        return $this->sameOrganization($user, $sale) && $user->hasPermission('delete_pig_sales');
    }

    public function reverse(User $user, PigSale $sale): bool
    {
        return $this->sameOrganization($user, $sale)
            && $user->hasPermission('record_pig_sales');
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('export_pig_sales');
    }
}
