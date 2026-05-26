<?php

namespace App\Services\Procurement;

use App\Domain\Events\Procurement\PurchaseRequestApproved;
use App\Domain\Events\Procurement\PurchaseRequestDeclined;
use App\Domain\Events\Procurement\PurchaseRequestPaid;
use App\Domain\Events\Support\SourceReference;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Services\DomainEvents\DispatchDomainEvent;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Encapsulates the approve / decline / markAsPaid lifecycle for purchase
 * requests. Each transition writes status fields, persists the audit
 * (approved_by, approved_at, decline_reason), and emits the appropriate
 * domain event for Phase 1 finance to subscribe to.
 *
 * Replaces the inline transaction blocks in PurchaseRequestController, which
 * had:
 *   - Closure use(...) clauses that referenced $dispatcher and
 *     $currentOrganization without those variables existing in the scope
 *     of decline() and markAsPaid() — they were never received as method
 *     parameters. Fatal at runtime.
 *   - Namespace imports instead of class imports, leaving the new
 *     PurchaseRequestApproved/Declined/Paid and SourceReference references
 *     unresolved. Fatal at runtime.
 *
 * Status guards (cannot approve an already-paid request, etc.) live in the
 * controller — the service trusts its caller and just executes.
 */
class PurchaseRequestLifecycleService
{
    public function __construct(
        protected DispatchDomainEvent $dispatcher,
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    public function approve(PurchaseRequest $request, User $actor): PurchaseRequest
    {
        $organizationId = $this->currentOrganization->requireId();

        return DB::transaction(function () use ($request, $actor, $organizationId) {
            $request->update([
                'status'         => 'approved',
                'approved_by'    => $actor->id,
                'approved_at'    => now(),
                'decline_reason' => null,
            ]);

            $request->load('items');

            $this->dispatcher->dispatch(new PurchaseRequestApproved(
                source: new SourceReference(
                    module:         'procurement',
                    sourceType:     PurchaseRequest::class,
                    sourceId:       (int) $request->id,
                    organizationId: $organizationId,
                ),
                occurredAt: CarbonImmutable::now(),
                dispatchedByUserId: $actor->id,
                requestNumber:    (string) $request->request_no,
                totalAmount:      (float) $request->total_amount,
                approvedByUserId: (int) $actor->id,
                purpose:          $request->purpose,
                items:            $request->items->map(fn ($i) => [
                    'item_name'   => $i->item_name,
                    'description' => $i->description,
                    'quantity'    => (float) $i->quantity,
                    'unit_price'  => (float) $i->unit_price,
                    'total_price' => (float) $i->total_price,
                ])->values()->all(),
            ));

            return $request->fresh();
        });
    }

    public function decline(PurchaseRequest $request, User $actor, ?string $reason): PurchaseRequest
    {
        $organizationId = $this->currentOrganization->requireId();

        return DB::transaction(function () use ($request, $actor, $reason, $organizationId) {
            $request->update([
                'status'         => 'declined',
                'approved_by'    => $actor->id,
                'approved_at'    => now(),
                'decline_reason' => $reason,
            ]);

            $this->dispatcher->dispatch(new PurchaseRequestDeclined(
                source: new SourceReference(
                    module:         'procurement',
                    sourceType:     PurchaseRequest::class,
                    sourceId:       (int) $request->id,
                    organizationId: $organizationId,
                ),
                occurredAt: CarbonImmutable::now(),
                dispatchedByUserId: $actor->id,
                requestNumber:    (string) $request->request_no,
                totalAmount:      (float) $request->total_amount,
                declinedByUserId: (int) $actor->id,
                declineReason:    $reason,
            ));

            return $request->fresh();
        });
    }

    public function markAsPaid(PurchaseRequest $request, User $actor): PurchaseRequest
    {
        $organizationId = $this->currentOrganization->requireId();

        return DB::transaction(function () use ($request, $actor, $organizationId) {
            $request->update(['status' => 'paid']);

            $this->dispatcher->dispatch(new PurchaseRequestPaid(
                source: new SourceReference(
                    module:         'procurement',
                    sourceType:     PurchaseRequest::class,
                    sourceId:       (int) $request->id,
                    organizationId: $organizationId,
                ),
                occurredAt: CarbonImmutable::now(),
                dispatchedByUserId: $actor->id,
                requestNumber:       (string) $request->request_no,
                totalAmount:         (float) $request->total_amount,
                markedPaidByUserId:  (int) $actor->id,
            ));

            return $request->fresh();
        });
    }
}
