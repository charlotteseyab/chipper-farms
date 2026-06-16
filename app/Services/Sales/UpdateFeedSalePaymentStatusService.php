<?php

namespace App\Services\Sales;

use App\Domain\Events\Sales\FeedSalePaymentStatusChanged;
use App\Domain\Events\Support\SourceReference;
use App\Models\FeedSale;
use App\Models\User;
use App\Services\DomainEvents\DispatchDomainEvent;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Updates a feed sale's payment status and emits FeedSalePaymentStatusChanged
 * so the receipt subscriber can post the cash receipt. Mirrors
 * UpdatePigSalePaymentStatusService. No-op if status is unchanged.
 */
class UpdateFeedSalePaymentStatusService
{
    public function __construct(
        protected DispatchDomainEvent $dispatcher,
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    public function execute(FeedSale $sale, User $actor, string $toStatus): FeedSale
    {
        $organizationId = $this->currentOrganization->requireId();
        $fromStatus = (string) $sale->payment_status;

        if ($fromStatus === $toStatus) {
            return $sale;
        }

        return DB::transaction(function () use ($sale, $fromStatus, $toStatus, $actor, $organizationId) {
            $sale->update(['payment_status' => $toStatus]);

            $this->dispatcher->dispatch(new FeedSalePaymentStatusChanged(
                source: new SourceReference(
                    module:         'sales',
                    sourceType:     FeedSale::class,
                    sourceId:       (int) $sale->id,
                    organizationId: $organizationId,
                ),
                occurredAt:         CarbonImmutable::now(),
                dispatchedByUserId: $actor->id,
                fromStatus:         $fromStatus,
                toStatus:           $toStatus,
                saleTotal:          (float) $sale->sale_total,
            ));

            return $sale->fresh();
        });
    }
}
