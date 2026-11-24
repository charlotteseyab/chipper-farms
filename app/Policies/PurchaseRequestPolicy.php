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
 *   delete          admin only (PR must be pending)
 *
 * SUPERUSER bypasses via Gate::before.
 */
class PurchaseRequestPolicy
{
    use EnforcesOrganizationScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_purchase_requests');
    }

    public function view(User $user, PurchaseRequest $request): bool
    {
        return $this->sameOrganization($user, $request)
            && $user->hasPermission('view_purchase_requests');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('create_purchase_requests');
    }

    public function update(User $user, PurchaseRequest $request): bool
    {
        if (! $this->sameOrganization($user, $request)) {
            return false;
        }
        if ($request->status !== 'pending') {
            return false;
        }
        return $user->hasPermission('edit_purchase_requests');
    }

    public function approve(User $user, PurchaseRequest $request): bool
    {
        if (! $this->sameOrganization($user, $request)) {
            return false;
        }
        if ($request->status !== 'pending') {
            return false;
        }
        return $user->hasPermission('approve_purchase_requests');
    }

    public function decline(User $user, PurchaseRequest $request): bool
    {
        if (! $this->sameOrganization($user, $request)) {
            return false;
        }
        if ($request->status !== 'pending') {
            return false;
        }
        return $user->hasPermission('approve_purchase_requests');
    }

    public function complete(User $user, PurchaseRequest $request): bool
    {
        return $this->sameOrganization($user, $request)
            && in_array($request->status, ['approved', 'paid'], true)
            && $user->hasPermission('complete_purchase_requests');
    }

    public function delete(User $user, PurchaseRequest $request): bool
    {
        if (! $this->sameOrganization($user, $request)) {
            return false;
        }
        if ($request->status !== 'pending') {
            return false;
        }
        return $user->hasPermission('approve_purchase_requests');
    }
}
