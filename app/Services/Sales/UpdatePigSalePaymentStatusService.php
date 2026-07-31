<?php

namespace App\Services\Sales;

use App\Domain\Events\Sales\PigSalePaymentStatusChanged;
use App\Domain\Events\Support\SourceReference;
use App\Models\PigSale;
use App\Models\SalesReturn;
use App\Models\User;
use App\Services\DomainEvents\DispatchDomainEvent;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Updates a pig sale's payment status with proper audit and event emission.
 *
 * Replaces the in-controller updatePaymentStatus method (was line 2441
 * of PigManagementController). That implementation overwrote payment_status
 * with no record of when or by whom — unsuitable for AR in Phase 1.
 *
 * Behaviour:
 *   - No-op if the status is unchanged (no event emitted, no audit write).
 *   - Writes payment_status_updated_at and payment_status_updated_by_user_id.
 *   - Emits PigSalePaymentStatusChanged with from/to status.
 *   - Optionally accepts a notes append — preserves existing notes, adds
 *     a timestamped line.
 */
class UpdatePigSalePaymentStatusService
{
    public function __construct(
        protected DispatchDomainEvent $dispatcher,
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    /**
     * Update the payment status.
     *
     * @param  PigSale  $sale
     * @param  User     $actor
     * @param  string   $toStatus  One of: pending, paid, partial.
     * @param  string|null $notesAppend
     * @return PigSale  The refreshed sale.
     */
    public function execute(PigSale $sale, User $actor, string $toStatus, ?string $notesAppend = null): PigSale
    {
        $organizationId = $this->currentOrganization->requireId();

        $fromStatus = (string) $sale->payment_status;

        $hasActiveReversal = $sale->salesReturns()
            ->where('status', '!=', SalesReturn::STATUS_FAILED)
            ->exists();

        if ($hasActiveReversal) {
            throw new \InvalidArgumentException(
                'Payment status cannot be changed after a sale reversal has been initiated.'
            );
        }

        if ($fromStatus === 'paid' && $toStatus !== 'paid' && blank($notesAppend)) {
            throw new \InvalidArgumentException(
                'A correction reason is required when changing a paid sale to another payment status.'
            );
        }

        if ($fromStatus === $toStatus && $notesAppend === null) {
            // No-op. No event, no audit write.
            return $sale;
        }

        return DB::transaction(function () use ($sale, $actor, $fromStatus, $toStatus, $notesAppend, $organizationId) {
            $updates = [
                'payment_status'                        => $toStatus,
                'payment_status_updated_at'             => now(),
                'payment_status_updated_by_user_id'     => $actor->id,
            ];

            if ($notesAppend !== null && $notesAppend !== '') {
                $existing = (string) ($sale->notes ?? '');
                $stamp    = now()->format('Y-m-d H:i');
                $line     = "[{$stamp} — {$actor->name}] {$notesAppend}";
                $updates['notes'] = trim($existing . ($existing === '' ? '' : "\n") . $line);
            }

            $sale->update($updates);

            if ($fromStatus !== $toStatus) {
                $this->dispatcher->dispatch(new PigSalePaymentStatusChanged(
                    source: new SourceReference(
                        module:         'sales',
                        sourceType:     PigSale::class,
                        sourceId:       (int) $sale->id,
                        organizationId: $organizationId,
                    ),
                    occurredAt: CarbonImmutable::now(),
                    dispatchedByUserId: $actor->id,
                    fromStatus: $fromStatus,
                    toStatus:   $toStatus,
                    saleTotal:  (float) $sale->sale_price,
                ));
            }

            return $sale->fresh();
        });
    }
}
