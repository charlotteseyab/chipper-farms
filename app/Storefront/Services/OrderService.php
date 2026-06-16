<?php

namespace App\Storefront\Services;

use App\Domain\Events\Sales\StorefrontOrderConfirmed;
use App\Domain\Events\Support\SourceReference;
use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontOrder;
use App\Models\Storefront\StorefrontOrderEvent;
use App\Models\User;
use App\Services\DomainEvents\DispatchDomainEvent;
use App\Storefront\Services\Exceptions\InvalidOrderTransitionException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Owns the order status machine and the inventory side effects of order
 * confirmation/cancellation. Posts NOTHING to finance (Phase 4 subscribes to
 * order events for that).
 *
 * THE CONFIRMATION RULE (v1, non-negotiable):
 *   confirm() is the ONLY path to `confirmed`, and it is always an explicit
 *   staff action. A verified Paystack payment moves an order to
 *   payment_confirmed — never to confirmed. COD orders sit at
 *   pending_confirmation. Either way a human confirms before the order is
 *   accepted and inventory is reserved.
 *
 * Inventory effect on confirm:
 *   - pig-backed listing (single head) → listing transitions to `reserved`
 *   - product listing (feed/manure/lot) → quantity_available is decremented;
 *     hitting zero flips it to `sold_out`
 * Cancelling a confirmed order reverses the effect (release reservation /
 * restore quantity). The effect is applied exactly once, guarded by the
 * status transition table.
 */
class OrderService
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        StorefrontOrder::STATUS_PENDING_CONFIRMATION => [
            StorefrontOrder::STATUS_CONFIRMED,
            StorefrontOrder::STATUS_CANCELLED,
        ],
        StorefrontOrder::STATUS_PENDING_PAYMENT => [
            StorefrontOrder::STATUS_PAYMENT_CONFIRMED,
            StorefrontOrder::STATUS_CANCELLED,
            StorefrontOrder::STATUS_FAILED,
        ],
        StorefrontOrder::STATUS_PAYMENT_CONFIRMED => [
            StorefrontOrder::STATUS_CONFIRMED,
            StorefrontOrder::STATUS_CANCELLED,
        ],
        StorefrontOrder::STATUS_CONFIRMED => [
            StorefrontOrder::STATUS_READY_FOR_PICKUP,
            StorefrontOrder::STATUS_OUT_FOR_DELIVERY,
            StorefrontOrder::STATUS_CANCELLED,
        ],
        StorefrontOrder::STATUS_READY_FOR_PICKUP => [
            StorefrontOrder::STATUS_COMPLETED,
            StorefrontOrder::STATUS_CANCELLED,
        ],
        StorefrontOrder::STATUS_OUT_FOR_DELIVERY => [
            StorefrontOrder::STATUS_COMPLETED,
        ],
        StorefrontOrder::STATUS_FAILED    => [],
        StorefrontOrder::STATUS_COMPLETED => [],
        StorefrontOrder::STATUS_CANCELLED => [],
    ];

    public function __construct(
        protected ListingService $listingService,
        protected DispatchDomainEvent $dispatcher,
        protected \App\Services\Sales\UpdatePigSalePaymentStatusService $pigPayment,
        protected \App\Services\Sales\UpdateFeedSalePaymentStatusService $feedPayment,
    ) {
    }

    /**
     * Explicit staff confirmation — the only path to `confirmed`. Reserves /
     * decrements inventory exactly once.
     */
    public function confirm(StorefrontOrder $order, User $actor): StorefrontOrder
    {
        $this->assertTransitionAllowed($order, StorefrontOrder::STATUS_CONFIRMED);

        return DB::transaction(function () use ($order, $actor) {
            $from = $order->status;

            $this->applyInventoryEffect($order, reserve: true, actor: $actor);

            $order->forceFill([
                'status'               => StorefrontOrder::STATUS_CONFIRMED,
                'confirmed_by_user_id' => $actor->id,
                'confirmed_at'         => now(),
            ])->save();

            $this->recordEvent($order, 'status_change', $from, StorefrontOrder::STATUS_CONFIRMED,
                'Order confirmed by staff.', $actor->id);

            // Finance trigger (Phase 4): dispatch INSIDE this transaction so the
            // outbox row rolls back with the confirmation if anything fails. The
            // StorefrontOrderConfirmedSubscriber routes lines into the existing
            // sale spine; the storefront never posts to the GL directly.
            $this->dispatchConfirmedEvent($order, $actor);

            return $order->refresh();
        });
    }

    /**
     * Build + dispatch the StorefrontOrderConfirmed domain event.
     */
    protected function dispatchConfirmedEvent(StorefrontOrder $order, User $actor): void
    {
        $order->loadMissing('items');

        // Settlement state for the underlying sale: a Paystack order is already
        // verified at confirmation (cash sale); COD is recognised as AR now and
        // collected later.
        $paymentStatus = ($order->payment_method === StorefrontOrder::METHOD_PAYSTACK
            && $order->payment_status === StorefrontOrder::PAY_PAYSTACK_VERIF)
            ? 'paid'
            : 'pending';

        $lines = $order->items->map(fn ($item) => [
            'order_item_id' => $item->id,
            'listing_id'    => $item->listing_id,
            'source_type'   => $item->source_type,
            'source_id'     => $item->source_id,
            'kind'          => $item->kind,
            'description'   => $item->description,
            'quantity'      => (string) $item->quantity,
            'quantity_unit' => $item->quantity_unit,
            'price_basis'   => $item->price_basis,
            'unit_price'    => (string) $item->unit_price,
            'line_total'    => (string) $item->line_total,
            'weight_kg'     => $item->weight_kg !== null ? (string) $item->weight_kg : null,
        ])->all();

        $this->dispatcher->dispatch(new StorefrontOrderConfirmed(
            source: new SourceReference(
                module:         'storefront',
                sourceType:     StorefrontOrder::class,
                sourceId:       (int) $order->id,
                organizationId: (int) $order->organization_id,
            ),
            occurredAt:         CarbonImmutable::now(),
            dispatchedByUserId: $actor->id,
            orderId:            (int) $order->id,
            orderReference:     (string) $order->reference,
            counterpartyId:     $order->counterparty_id ? (int) $order->counterparty_id : null,
            paymentStatus:      $paymentStatus,
            paymentMethod:      $order->payment_method,
            fulfilmentMethod:   (string) $order->fulfilment_method,
            total:              (float) $order->total,
            buyerName:          $order->ship_to_name ?: optional($order->counterparty)->name,
            lines:              $lines,
        ));
    }

    /**
     * Apply a verified gateway payment. Moves pending_payment →
     * payment_confirmed and sets payment_status. This is NOT acceptance — a
     * human still confirms. Idempotent: a re-delivered verification on an order
     * already past payment is a no-op.
     */
    public function markPaymentConfirmed(StorefrontOrder $order, ?User $actor = null): StorefrontOrder
    {
        if ($order->status !== StorefrontOrder::STATUS_PENDING_PAYMENT) {
            // Already advanced (re-delivered webhook / double verify) — no-op,
            // but ensure payment_status reflects the verified payment.
            if ($order->payment_status === StorefrontOrder::PAY_PAYSTACK_PEND) {
                $order->forceFill(['payment_status' => StorefrontOrder::PAY_PAYSTACK_VERIF])->save();
            }
            return $order->refresh();
        }

        return DB::transaction(function () use ($order, $actor) {
            $from = $order->status;

            $order->forceFill([
                'status'         => StorefrontOrder::STATUS_PAYMENT_CONFIRMED,
                'payment_status' => StorefrontOrder::PAY_PAYSTACK_VERIF,
            ])->save();

            $this->recordEvent($order, 'payment', $from, StorefrontOrder::STATUS_PAYMENT_CONFIRMED,
                'Paystack payment verified. Awaiting staff confirmation.', $actor?->id);

            return $order->refresh();
        });
    }

    public function markFailed(StorefrontOrder $order, ?string $note = null, ?User $actor = null): StorefrontOrder
    {
        if (! $this->canTransition($order, StorefrontOrder::STATUS_FAILED)) {
            return $order; // not in a failable state; ignore.
        }

        $from = $order->status;
        $order->forceFill([
            'status'         => StorefrontOrder::STATUS_FAILED,
            'payment_status' => StorefrontOrder::PAY_FAILED,
        ])->save();

        $this->recordEvent($order, 'payment', $from, StorefrontOrder::STATUS_FAILED, $note, $actor?->id);

        return $order->refresh();
    }

    public function cancel(StorefrontOrder $order, User $actor, ?string $reason = null): StorefrontOrder
    {
        $this->assertTransitionAllowed($order, StorefrontOrder::STATUS_CANCELLED);

        return DB::transaction(function () use ($order, $actor, $reason) {
            $from = $order->status;

            // Release inventory only if it was actually reserved (order had
            // passed through confirmation).
            if ($this->hadInventoryReserved($from)) {
                $this->applyInventoryEffect($order, reserve: false, actor: $actor);
            }

            $order->forceFill([
                'status'               => StorefrontOrder::STATUS_CANCELLED,
                'cancelled_by_user_id' => $actor->id,
                'cancelled_at'         => now(),
                'cancel_reason'        => $reason,
            ])->save();

            $this->recordEvent($order, 'status_change', $from, StorefrontOrder::STATUS_CANCELLED,
                $reason ? "Cancelled: {$reason}" : 'Order cancelled.', $actor->id);

            return $order->refresh();
        });
    }

    /**
     * Move a confirmed order toward fulfilment. Pickup/delivery selection
     * follows the order's fulfilment_method.
     */
    public function markReadyOrDispatched(StorefrontOrder $order, User $actor): StorefrontOrder
    {
        $target = $order->fulfilment_method === StorefrontOrder::FULFIL_DELIVERY
            ? StorefrontOrder::STATUS_OUT_FOR_DELIVERY
            : StorefrontOrder::STATUS_READY_FOR_PICKUP;

        $this->assertTransitionAllowed($order, $target);
        $from = $order->status;
        $order->forceFill(['status' => $target])->save();
        $this->recordEvent($order, 'status_change', $from, $target, null, $actor->id);

        return $order->refresh();
    }

    public function canTransition(StorefrontOrder $order, string $target): bool
    {
        return in_array($target, self::TRANSITIONS[$order->status] ?? [], true);
    }

    /**
     * Complete fulfilment (pickup collected / delivery received). On
     * completion:
     *   - COD orders settle: each linked underlying sale is flipped to 'paid'
     *     via the sale spine, which posts the cash receipt (Dr Cash / Cr AR).
     *   - Pig-backed listings move to sold_out.
     * Paystack orders are already paid; nothing to settle.
     */
    public function complete(StorefrontOrder $order, User $actor): StorefrontOrder
    {
        $this->assertTransitionAllowed($order, StorefrontOrder::STATUS_COMPLETED);

        return DB::transaction(function () use ($order, $actor) {
            $from = $order->status;
            $order->loadMissing('items');

            $isCod = $order->payment_method === StorefrontOrder::METHOD_COD;

            if ($isCod && $order->payment_status !== StorefrontOrder::PAY_PAID) {
                $this->settleCodPayments($order, $actor);
                $order->forceFill(['payment_status' => StorefrontOrder::PAY_PAID])->save();
            }

            $this->markListingsSoldOut($order, $actor);

            $order->forceFill([
                'status'               => StorefrontOrder::STATUS_COMPLETED,
                'completed_at'         => now(),
                'completed_by_user_id' => $actor->id,
            ])->save();

            $this->recordEvent($order, 'status_change', $from, StorefrontOrder::STATUS_COMPLETED,
                'Order completed/fulfilled.', $actor->id);

            return $order->refresh();
        });
    }

    /**
     * Flip each linked underlying sale to 'paid' so the spine posts the COD
     * cash receipt. Lines without a posted sale (deferred / not-yet-posted) are
     * noted and skipped — never silently dropped.
     */
    protected function settleCodPayments(StorefrontOrder $order, User $actor): void
    {
        $unlinked = 0;

        foreach ($order->items as $item) {
            if ($item->posted_sale_id === null || $item->posted_sale_type === null) {
                $unlinked++;
                continue;
            }

            if ($item->posted_sale_type === \App\Models\PigSale::class) {
                $sale = \App\Models\PigSale::find($item->posted_sale_id);
                if ($sale && $sale->payment_status !== 'paid') {
                    $this->pigPayment->execute($sale, $actor, 'paid');
                }
            } elseif ($item->posted_sale_type === \App\Models\FeedSale::class) {
                $sale = \App\Models\FeedSale::find($item->posted_sale_id);
                if ($sale && $sale->payment_status !== 'paid') {
                    $this->feedPayment->execute($sale, $actor, 'paid');
                }
            }
        }

        if ($unlinked > 0) {
            $this->recordEvent($order, 'system', null, null,
                "COD settlement: {$unlinked} line(s) had no posted sale yet and were not settled automatically.",
                $actor->id);
        }
    }

    protected function markListingsSoldOut(StorefrontOrder $order, User $actor): void
    {
        foreach ($order->items as $item) {
            if ($item->listing_id === null) {
                continue;
            }
            $listing = StorefrontListing::find($item->listing_id);
            if ($listing === null) {
                continue;
            }
            if (in_array($listing->status, [StorefrontListing::STATUS_RESERVED, StorefrontListing::STATUS_PUBLISHED], true)
                && $this->isPigBacked($listing)) {
                $this->listingService->transitionTo($listing, StorefrontListing::STATUS_SOLD_OUT, $actor);
            }
        }
    }

    public function recordEvent(
        StorefrontOrder $order,
        string $type,
        ?string $fromStatus = null,
        ?string $toStatus = null,
        ?string $note = null,
        ?int $actorId = null,
    ): StorefrontOrderEvent {
        return StorefrontOrderEvent::create([
            'organization_id' => $order->organization_id,
            'order_id'        => $order->id,
            'type'            => $type,
            'from_status'     => $fromStatus,
            'to_status'       => $toStatus,
            'note'            => $note,
            'actor_user_id'   => $actorId,
        ]);
    }

    // ------------------------------------------------------------------

    /**
     * Apply (reserve=true) or reverse (reserve=false) the inventory effect for
     * every line in the order.
     */
    protected function applyInventoryEffect(StorefrontOrder $order, bool $reserve, User $actor): void
    {
        $order->loadMissing('items');

        foreach ($order->items as $item) {
            if ($item->listing_id === null) {
                continue;
            }

            $listing = StorefrontListing::find($item->listing_id);
            if ($listing === null) {
                continue;
            }

            if ($this->isPigBacked($listing)) {
                $this->reservePigListing($listing, $reserve, $actor);
            } else {
                $this->adjustProductQuantity($listing, (string) $item->quantity, $reserve, $actor);
            }
        }
    }

    protected function reservePigListing(StorefrontListing $listing, bool $reserve, User $actor): void
    {
        if ($reserve) {
            if ($listing->status === StorefrontListing::STATUS_PUBLISHED) {
                $this->listingService->transitionTo($listing, StorefrontListing::STATUS_RESERVED, $actor);
            }
            return;
        }

        // Release: reserved → published.
        if ($listing->status === StorefrontListing::STATUS_RESERVED) {
            $this->listingService->transitionTo($listing, StorefrontListing::STATUS_PUBLISHED, $actor);
        }
    }

    protected function adjustProductQuantity(StorefrontListing $listing, string $quantity, bool $reserve, User $actor): void
    {
        if ($reserve) {
            $newQty = bcsub((string) $listing->quantity_available, $quantity, 4);
            if (bccomp($newQty, '0', 4) < 0) {
                $newQty = '0';
            }
            $listing->forceFill(['quantity_available' => $newQty])->save();

            if (bccomp($newQty, '0', 4) <= 0 && $listing->status === StorefrontListing::STATUS_PUBLISHED) {
                $this->listingService->transitionTo($listing, StorefrontListing::STATUS_SOLD_OUT, $actor);
            }
            return;
        }

        // Release: restore quantity; if it had been sold_out, re-publish.
        $newQty = bcadd((string) $listing->quantity_available, $quantity, 4);
        $listing->forceFill(['quantity_available' => $newQty])->save();

        if ($listing->status === StorefrontListing::STATUS_SOLD_OUT && bccomp($newQty, '0', 4) > 0) {
            $this->listingService->transitionTo($listing, StorefrontListing::STATUS_UNPUBLISHED, $actor);
        }
    }

    protected function isPigBacked(StorefrontListing $listing): bool
    {
        return in_array($listing->kind, [
            StorefrontListing::KIND_LIVE_PIG,
            StorefrontListing::KIND_BREEDING_STOCK,
        ], true);
    }

    protected function hadInventoryReserved(string $status): bool
    {
        return in_array($status, [
            StorefrontOrder::STATUS_CONFIRMED,
            StorefrontOrder::STATUS_READY_FOR_PICKUP,
            StorefrontOrder::STATUS_OUT_FOR_DELIVERY,
        ], true);
    }

    protected function assertTransitionAllowed(StorefrontOrder $order, string $target): void
    {
        if ($order->status === $target) {
            return;
        }

        if (! $this->canTransition($order, $target)) {
            throw new InvalidOrderTransitionException(
                "Illegal order transition: {$order->status} -> {$target} (order #{$order->id})."
            );
        }
    }
}
