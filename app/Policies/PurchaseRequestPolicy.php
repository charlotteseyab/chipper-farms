<?php

namespace App\Policies;

use App\Models\PurchaseRequest;
use App\Models\User;
use App\Policies\Concerns\EnforcesOrganizationScope;

/**
 * Policy for PurchaseRequest.
 *
 * Phase 0.8: purchase_requests.organization_id added in Batch D. Per-instance
 * methods enforce same-org before role check.
 *
 * STATE-BASED RESTRICTIONS
 * ------------------------
 * Some actions are gated by the request's status:
 *   - update / delete  : only on PENDING requests
 *   - approve / decline: only on PENDING requests
 *   - markPaid         : only on APPROVED requests
 *
 * These status checks are enforced in the policy rather than repeated in
 * every controller.
 *
 * ROLE MATRIX
 * -----------
 *   viewAny / view  admin, manager, dataentry, accountant
 *   create          admin, manager, dataentry
 *   update          admin, manager (PR must be pending)
 *   approve         admin only (PR must be pending)
 *   decline         admin only (PR must be pending)
 *   markPaid        admin, accountant (PR must be approved; Phase 1 turns this
 *                                       into a proper AP flow)
 *   delete          admin only (PR must be pending)
 *
 * SUPERUSER bypasses via Gate::before.
 */
class PurchaseRequestPolicy
{
    use EnforcesOrganizationScope;

    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isManager() || $user->isDataEntry() || $user->isAccountant();
    }

    public function view(User $user, PurchaseRequest $request): bool
    {
        return $this->sameOrganization($user, $request)
            && ($user->isAdmin() || $user->isManager() || $user->isDataEntry() || $user->isAccountant());
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isManager() || $user->isDataEntry();
    }

    public function update(User $user, PurchaseRequest $request): bool
    {
        if (! $this->sameOrganization($user, $request)) {
            return false;
        }
        if ($request->status !== 'pending') {
            return false;
        }
        return $user->isAdmin() || $user->isManager();
    }

    public function approve(User $user, PurchaseRequest $request): bool
    {
        if (! $this->sameOrganization($user, $request)) {
            return false;
        }
        if ($request->status !== 'pending') {
            return false;
        }
        return $user->isAdmin();
    }

    public function decline(User $user, PurchaseRequest $request): bool
    {
        if (! $this->sameOrganization($user, $request)) {
            return false;
        }
        if ($request->status !== 'pending') {
            return false;
        }
        return $user->isAdmin();
    }

    public function markPaid(User $user, PurchaseRequest $request): bool
    {
        if (! $this->sameOrganization($user, $request)) {
            return false;
        }
        if ($request->status !== 'approved') {
            return false;
        }
        return $user->isAdmin() || $user->isAccountant();
    }

    public function delete(User $user, PurchaseRequest $request): bool
    {
        if (! $this->sameOrganization($user, $request)) {
            return false;
        }
        if ($request->status !== 'pending') {
            return false;
        }
        return $user->isAdmin();
    }
}
