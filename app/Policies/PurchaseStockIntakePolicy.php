<?php

namespace App\Policies;

use App\Models\PurchaseStockIntake;
use App\Models\User;
use App\Policies\Concerns\EnforcesOrganizationScope;

/**
 * Policy for purchased stock intake.
 *
 * SUPERUSER bypasses via Gate::before.
 */
class PurchaseStockIntakePolicy
{
    use EnforcesOrganizationScope;

    public function viewAny(User $user): bool
    {
        return $this->canOperateIntake($user);
    }

    public function view(User $user, PurchaseStockIntake $purchaseStockIntake): bool
    {
        return $this->sameOrganization($user, $purchaseStockIntake)
            && $this->canOperateIntake($user);
    }

    public function create(User $user): bool
    {
        return $this->canOperateIntake($user);
    }

    public function receive(User $user, PurchaseStockIntake $purchaseStockIntake): bool
    {
        return $this->sameOrganization($user, $purchaseStockIntake)
            && $this->canOperateIntake($user);
    }

    public function quarantine(User $user, PurchaseStockIntake $purchaseStockIntake): bool
    {
        return $this->sameOrganization($user, $purchaseStockIntake)
            && $this->canOperateIntake($user);
    }

    public function approve(User $user, PurchaseStockIntake $purchaseStockIntake): bool
    {
        return $this->sameOrganization($user, $purchaseStockIntake)
            && $user->hasPermission('approve_purchase_intakes');
    }

    public function reviewQuarantine(User $user, PurchaseStockIntake $purchaseStockIntake): bool
    {
        return $this->sameOrganization($user, $purchaseStockIntake)
            && $user->hasPermission('approve_purchase_intakes');
    }

    public function addToStock(User $user, PurchaseStockIntake $purchaseStockIntake): bool
    {
        return $this->sameOrganization($user, $purchaseStockIntake)
            && $user->hasPermission('approve_purchase_intakes');
    }

    public function cancel(User $user, PurchaseStockIntake $purchaseStockIntake): bool
    {
        return $this->sameOrganization($user, $purchaseStockIntake)
            && $user->hasPermission('approve_purchase_intakes');
    }

    protected function canOperateIntake(User $user): bool
    {
        return $user->hasPermission('operate_purchase_intakes');
    }
}
