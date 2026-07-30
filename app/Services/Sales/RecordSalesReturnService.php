<?php

namespace App\Services\Sales;

use App\Domain\Events\Sales\SalesReturnRecorded;
use App\Domain\Events\Support\SourceReference;
use App\Models\FeedSale;
use App\Models\PigSale;
use App\Models\SalesReturn;
use App\Models\User;
use App\Services\DomainEvents\DispatchDomainEvent;
use App\Services\Numbering\NumberingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Records a sales return and dispatches it for reversal (CF-P0-008).
 *
 * This service creates the anchor row and emits the domain event. It does NOT
 * post anything itself — the reversal happens in subscribers, for the same
 * reason the sale's own posting does: the storefront and the sales module never
 * write to the general ledger directly, and posting inside the request would put
 * a multi-step financial operation inside a web timeout.
 *
 * VALIDATION HAPPENS HERE, POSTING HAPPENS LATER
 * ----------------------------------------------
 * Everything that can be rejected is rejected synchronously, so the caller gets
 * an immediate, actionable error rather than a silently failed background event:
 *
 *   - the sale must exist;
 *   - the quantity must be positive and must not exceed what remains
 *     un-returned. Over-returning is the failure that puts stock into inventory
 *     that never left it, and it is easy to reach through repeated partial
 *     returns;
 *   - the return must belong to the sale's organisation.
 *
 * WHAT IS NOT VALIDATED HERE
 * --------------------------
 * Whether the original COGS was ever posted. A sale can legitimately be returned
 * while its posting is still draining from the outbox, so that is the
 * subscriber's business — it marks the return failed and retryable rather than
 * refusing the customer's return outright.
 */
class RecordSalesReturnService
{
    /** Return types this service understands. */
    protected const SUPPORTED = [FeedSale::class, PigSale::class];

    public function __construct(
        protected DispatchDomainEvent $dispatcher,
        protected NumberingService $numbering,
    ) {
    }

    /**
     * @param  FeedSale|PigSale  $sale
     * @param  string  $quantity  decimal string; never a float — this is money-adjacent
     *
     * @throws \InvalidArgumentException
     */
    public function record(
        object $sale,
        User $actor,
        string $quantity,
        string $reason,
        bool $restock = true,
        ?int $storefrontOrderId = null,
        ?CarbonImmutable $occurredAt = null,
    ): SalesReturn {
        $saleClass = $this->resolveSaleClass($sale);
        $organizationId = (int) $sale->organization_id;

        if ($organizationId <= 0) {
            throw new \InvalidArgumentException(
                'Cannot record a return against a sale with no organisation.'
            );
        }

        $originalQuantity = $this->originalQuantity($sale, $saleClass);

        if (bccomp($quantity, '0', 4) <= 0) {
            throw new \InvalidArgumentException('Return quantity must be greater than zero.');
        }

        // Guard against over-return across MULTIPLE returns, not just this one.
        // Checking only this return against the sale would allow three separate
        // 50% returns to reach 150%.
        $alreadyReturned = $this->alreadyReturnedQuantity($saleClass, (int) $sale->id);
        $wouldTotal      = bcadd($alreadyReturned, $quantity, 4);

        if (bccomp($wouldTotal, $originalQuantity, 4) > 0) {
            throw new \InvalidArgumentException(sprintf(
                'Cannot return %s: the sale was %s and %s has already been returned.',
                rtrim(rtrim($quantity, '0'), '.'),
                rtrim(rtrim($originalQuantity, '0'), '.'),
                rtrim(rtrim($alreadyReturned, '0'), '.') ?: '0',
            ));
        }

        // Proportion of the ORIGINAL sale this return represents. Stored so the
        // posted amounts can always be traced back to the decision, even if the
        // sale is later amended.
        $proportion = bcdiv($quantity, $originalQuantity, 8);

        $occurredAt ??= CarbonImmutable::now();

        return DB::transaction(function () use (
            $sale, $saleClass, $organizationId, $actor, $quantity,
            $proportion, $reason, $restock, $storefrontOrderId, $occurredAt
        ) {
            $return = SalesReturn::create([
                'organization_id'     => $organizationId,
                'return_number'       => $this->allocateNumber($organizationId),
                'returnable_type'     => $saleClass,
                'returnable_id'       => (int) $sale->id,
                'storefront_order_id' => $storefrontOrderId,
                'quantity'            => $quantity,
                'proportion'          => $proportion,
                'restock'             => $restock,
                'reason'              => mb_substr($reason, 0, 500),
                'status'              => SalesReturn::STATUS_POSTING,
                'returned_by_user_id' => $actor->id,
                'returned_at'         => $occurredAt,
            ]);

            // Dispatched INSIDE the transaction so the outbox row rolls back
            // with the return if anything below fails — the same guarantee the
            // storefront's confirmation path relies on.
            $this->dispatcher->dispatch(new SalesReturnRecorded(
                source: new SourceReference(
                    module: 'sales',
                    sourceType: SalesReturn::class,
                    sourceId: (int) $return->id,
                    organizationId: $organizationId,
                ),
                occurredAt: $occurredAt,
                dispatchedByUserId: $actor->id,
                salesReturnId: (int) $return->id,
                returnableType: $saleClass,
                returnableId: (int) $sale->id,
                quantity: $quantity,
                proportion: $proportion,
                restock: $restock,
                storefrontOrderId: $storefrontOrderId,
                reason: $reason,
            ));

            return $return->refresh();
        });
    }

    /**
     * Quantity already returned against this sale, across all prior returns.
     *
     * Failed returns are EXCLUDED: a return that could not post has not put
     * anything back, so counting it would permanently block a retry.
     */
    protected function alreadyReturnedQuantity(string $saleClass, int $saleId): string
    {
        $rows = SalesReturn::query()
            ->where('returnable_type', $saleClass)
            ->where('returnable_id', $saleId)
            ->where('status', '!=', SalesReturn::STATUS_FAILED)
            ->pluck('quantity');

        $total = '0';

        foreach ($rows as $q) {
            $total = bcadd($total, (string) $q, 4);
        }

        return $total;
    }

    protected function originalQuantity(object $sale, string $saleClass): string
    {
        if ($saleClass === FeedSale::class) {
            return (string) $sale->quantity_kg;
        }

        // A pig is one indivisible animal. Partial pig returns are meaningless,
        // and allowing a fractional quantity here would produce a fractional
        // biological-asset entry.
        return '1';
    }

    protected function resolveSaleClass(object $sale): string
    {
        foreach (self::SUPPORTED as $class) {
            if ($sale instanceof $class) {
                return $class;
            }
        }

        throw new \InvalidArgumentException(
            'Unsupported sale type for return: ' . $sale::class
        );
    }

    /**
     * Allocate a human-facing return number through the shared numbering
     * service, so it is concurrency-safe rather than count-derived (CF-P1-014).
     */
    protected function allocateNumber(int $organizationId): string
    {
        return $this->numbering->next(
            sequenceType: 'sales_return',
            prefix: 'SRT',
            year: (int) date('Y'),
            organizationId: $organizationId,
            padding: 5,
        );
    }
}
