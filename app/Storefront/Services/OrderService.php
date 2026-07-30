<?php

namespace App\Storefront\Services;

use App\Domain\Events\Sales\StorefrontOrderConfirmed;
use App\Domain\Events\Support\SourceReference;
use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontOrder;
use App\Models\Storefront\StorefrontOrderEvent;
use App\Models\Storefront\StorefrontOrderItem;
use App\Models\User;
use App\Services\DomainEvents\DispatchDomainEvent;
use App\Storefront\Services\Exceptions\CodSettlementException;
use App\Storefront\Services\Exceptions\InventoryConflictException;
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
     *
     * CONCURRENCY AND IDEMPOTENCY (CF-P0-006)
     * ---------------------------------------
     * Three defects were fixed here, and only one of them needed concurrency to
     * trigger:
     *
     *  1. DOUBLE SUBMIT. assertTransitionAllowed() returns early WITHOUT
     *     throwing when the order is already at the target status. It used to be
     *     called before the transaction, so a second confirm() on an
     *     already-confirmed order sailed past it and ran applyInventoryEffect()
     *     again — decrementing stock twice for one order. A double-clicked
     *     button was enough; no race required. Re-confirmation is now an
     *     explicit, inventory-neutral no-op.
     *
     *  2. STALE READ. The status check ran against the in-memory $order OUTSIDE
     *     the transaction, so two concurrent confirmations both saw 'pending'
     *     and both proceeded. The order row is now reloaded under
     *     lockForUpdate() inside the transaction, and validated there.
     *
     *  3. UNLOCKED LISTINGS. applyInventoryEffect() read listings with a plain
     *     find(), so two confirmations read the same quantity_available. Listing
     *     rows are now locked too — see that method.
     *
     * Returning the order unchanged on re-confirmation (rather than throwing) is
     * deliberate: staff double-clicks and retried requests are routine, and the
     * caller's intent is already satisfied. What must never happen is a second
     * inventory effect.
     */
    public function confirm(StorefrontOrder $order, User $actor): StorefrontOrder
    {
        return DB::transaction(function () use ($order, $actor) {
            // Reload under lock. Everything after this point reads committed
            // state that no other transaction can change until we commit.
            $locked = StorefrontOrder::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                throw new InvalidOrderTransitionException(
                    "Order #{$order->getKey()} no longer exists and cannot be confirmed."
                );
            }

            // Idempotent short-circuit: already confirmed, so the inventory
            // effect has already been applied exactly once. Do NOT re-apply.
            if ($locked->status === StorefrontOrder::STATUS_CONFIRMED) {
                return $locked;
            }

            // Validated under the lock, against freshly-read state.
            $this->assertTransitionAllowed($locked, StorefrontOrder::STATUS_CONFIRMED);

            $from = $locked->status;

            $this->applyInventoryEffect($locked, reserve: true, actor: $actor);

            $locked->forceFill([
                'status'               => StorefrontOrder::STATUS_CONFIRMED,
                'confirmed_by_user_id' => $actor->id,
                'confirmed_at'         => now(),
            ])->save();

            $this->recordEvent($locked, 'status_change', $from, StorefrontOrder::STATUS_CONFIRMED,
                'Order confirmed by staff.', $actor->id);

            // Finance trigger (Phase 4): dispatch INSIDE this transaction so the
            // outbox row rolls back with the confirmation if anything fails. The
            // StorefrontOrderConfirmedSubscriber routes lines into the existing
            // sale spine; the storefront never posts to the GL directly.
            $this->dispatchConfirmedEvent($locked, $actor);

            return $locked->refresh();
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
    /**
     * Complete fulfilment.
     *
     * COD SETTLEMENT IS NOW FATAL ON FAILURE (CF-P0-009)
     * --------------------------------------------------
     * This used to call settleCodPayments() and then set payment_status = 'paid'
     * unconditionally. settleCodPayments() skipped lines with no posted sale,
     * counted them, wrote a note, and completion proceeded anyway — so an order
     * whose lines had ALL failed to settle still ended up paid and completed.
     *
     * The consequence is a cash discrepancy, not a cosmetic one: the business
     * believes it has been paid, no receipt exists, and the receivable is closed
     * against nothing.
     *
     * Unsettleable lines are now detected BEFORE the transaction opens, so the
     * recorded failure event survives (it would be rolled back if written
     * inside), and completion is refused. Because sales are posted
     * asynchronously by the outbox, "no posted sale yet" is a normal transient
     * state — staff retry once posting catches up, which is why this raises a
     * distinct exception rather than a generic error.
     */
    public function complete(StorefrontOrder $order, User $actor): StorefrontOrder
    {
        $this->assertTransitionAllowed($order, StorefrontOrder::STATUS_COMPLETED);

        $order->loadMissing('items');

        $isCod = $order->payment_method === StorefrontOrder::METHOD_COD;
        $needsSettlement = $isCod && $order->payment_status !== StorefrontOrder::PAY_PAID;

        if ($needsSettlement) {
            $blockers = $this->unsettleableCodLines($order);

            if ($blockers !== []) {
                // Recorded outside the transaction so it is not rolled back with
                // the refusal. This is the "visible settlement failure" the
                // remediation brief requires; the previous note was written and
                // then ignored.
                $this->recordEvent(
                    $order,
                    'system',
                    $order->status,
                    $order->status,
                    'COD settlement BLOCKED — order not marked paid or completed: '
                        . implode('; ', $blockers),
                    $actor->id,
                );

                throw new CodSettlementException(
                    sprintf(
                        'Order %s cannot be completed: %d line(s) cannot be settled. '
                        . 'The order has NOT been marked paid.',
                        $order->reference,
                        count($blockers),
                    ),
                    $blockers,
                );
            }
        }

        return DB::transaction(function () use ($order, $actor, $needsSettlement) {
            $from = $order->status;
            $order->loadMissing('items');

            if ($needsSettlement) {
                $this->settleCodPayments($order, $actor);

                // Verify rather than assume. settleCodPayments() delegates to the
                // sale spine, which can decline; the previous code never checked
                // and simply declared the order paid.
                $stillUnsettled = $this->unpaidCodLines($order->refresh());

                if ($stillUnsettled !== []) {
                    throw new CodSettlementException(
                        sprintf(
                            'Order %s: settlement was attempted but %d line(s) are still unpaid. '
                            . 'Completion rolled back.',
                            $order->reference,
                            count($stillUnsettled),
                        ),
                        $stillUnsettled,
                    );
                }

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
     * Lines that CANNOT be settled at all (CF-P0-009) — the pre-flight gate.
     *
     * Deliberately distinct from unpaidCodLines(). A line whose sale exists but
     * is not yet paid is not a blocker: making it paid is exactly what
     * settlement does. Conflating the two would refuse every COD completion,
     * which is how the first version of this fix broke the existing
     * completion tests.
     *
     * Two genuinely unsettleable cases, both of which previously ended with the
     * order marked paid anyway:
     *
     *   - no posted sale linked at all — the case the register names. Sales are
     *     posted asynchronously by the outbox, so this is usually transient and
     *     recoverable by retrying;
     *   - a posted_sale_id pointing at a sale that no longer exists. The old code
     *     skipped this through `if ($sale && ...)` and did not even count it in
     *     the unlinked tally, so it was invisible in both the note and the
     *     outcome.
     *
     * @return list<string>
     */
    protected function unsettleableCodLines(StorefrontOrder $order): array
    {
        $order->loadMissing('items');

        $blockers = [];

        foreach ($order->items as $item) {
            if ($item->posted_sale_id === null || $item->posted_sale_type === null) {
                $blockers[] = "line #{$item->id} has no posted sale yet (finance posting may still be in flight)";
                continue;
            }

            if ($this->findPostedSale($item) === null) {
                $blockers[] = sprintf(
                    'line #%d references %s#%d which no longer exists',
                    $item->id,
                    class_basename((string) $item->posted_sale_type),
                    $item->posted_sale_id,
                );
            }
        }

        return $blockers;
    }

    /**
     * Lines still unpaid AFTER settlement was attempted — the post-check.
     *
     * The previous implementation had no equivalent: it called settlement and
     * declared the order paid without ever confirming the sale spine agreed.
     *
     * @return list<string>
     */
    protected function unpaidCodLines(StorefrontOrder $order): array
    {
        $order->loadMissing('items');

        $unpaid = [];

        foreach ($order->items as $item) {
            if ($item->posted_sale_id === null || $item->posted_sale_type === null) {
                $unpaid[] = "line #{$item->id} has no posted sale";
                continue;
            }

            $sale = $this->findPostedSale($item);

            if ($sale === null) {
                $unpaid[] = "line #{$item->id} references a sale that no longer exists";
                continue;
            }

            if ($sale->payment_status !== 'paid') {
                $unpaid[] = sprintf(
                    'line #%d: %s#%d is still %s',
                    $item->id,
                    class_basename((string) $item->posted_sale_type),
                    $sale->id,
                    (string) $sale->payment_status,
                );
            }
        }

        return $unpaid;
    }

    protected function findPostedSale(StorefrontOrderItem $item): ?object
    {
        return match ($item->posted_sale_type) {
            \App\Models\PigSale::class  => \App\Models\PigSale::find($item->posted_sale_id),
            \App\Models\FeedSale::class => \App\Models\FeedSale::find($item->posted_sale_id),
            default                     => null,
        };
    }

    /**
     * Flip each linked underlying sale to 'paid' so the spine posts the COD cash
     * receipt.
     *
     * Callers MUST check unsettleableCodLines() before and after. This method
     * deliberately does not decide policy — it only performs the settlement it
     * has been cleared to perform.
     */
    protected function settleCodPayments(StorefrontOrder $order, User $actor): void
    {
        foreach ($order->items as $item) {
            if ($item->posted_sale_id === null || $item->posted_sale_type === null) {
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
    /**
     * Apply (reserve=true) or reverse (reserve=false) the inventory effect for
     * every line in the order.
     *
     * Listings are locked with lockForUpdate() and processed in ASCENDING
     * LISTING ID order (CF-P0-006).
     *
     * The lock is what makes the quantity read-modify-write safe: a plain find()
     * let two confirmations read the same quantity_available and each subtract
     * from the same starting value, losing one decrement entirely.
     *
     * The ORDER matters independently of the lock. Two orders containing the
     * same two listings in opposite sequence would each hold the lock the other
     * needs, and InnoDB would resolve it by killing one with a deadlock error
     * surfaced to a member of staff. Acquiring locks in a globally consistent
     * order makes that cycle impossible rather than merely retryable — worth
     * doing here because Phase 3 already demonstrated this codebase can deadlock
     * under real contention.
     */
    protected function applyInventoryEffect(StorefrontOrder $order, bool $reserve, User $actor): void
    {
        $order->loadMissing('items');

        // Aggregate per listing before touching anything: an order may legitimately
        // contain two lines for the same listing, and locking it twice while
        // applying each line separately would double-apply against a single lock.
        $quantitiesByListing = [];

        foreach ($order->items as $item) {
            if ($item->listing_id === null) {
                continue;
            }

            $listingId = (int) $item->listing_id;
            $quantitiesByListing[$listingId] = bcadd(
                $quantitiesByListing[$listingId] ?? '0',
                (string) $item->quantity,
                4,
            );
        }

        if ($quantitiesByListing === []) {
            return;
        }

        ksort($quantitiesByListing, SORT_NUMERIC);

        foreach ($quantitiesByListing as $listingId => $quantity) {
            $listing = StorefrontListing::query()
                ->whereKey($listingId)
                ->lockForUpdate()
                ->first();

            if ($listing === null) {
                // The listing was deleted after checkout. Reserving against it is
                // impossible, so refuse rather than confirming a line that can
                // never be fulfilled. Releasing is a no-op — there is nothing to
                // give back to.
                if ($reserve) {
                    throw new InventoryConflictException(
                        "Listing #{$listingId} no longer exists; order #{$order->getKey()} cannot be confirmed."
                    );
                }

                continue;
            }

            if ($this->isPigBacked($listing)) {
                $this->reservePigListing($listing, $reserve, $actor);
            } else {
                $this->adjustProductQuantity($listing, $quantity, $reserve, $actor);
            }
        }
    }

    /**
     * Reserve or release a pig-backed listing.
     *
     * A pig is a single physical animal, so the listing status IS the
     * reservation: published means available, reserved means promised to an
     * order.
     *
     * CF-P0-006: the reserve branch previously did nothing at all when the
     * listing was not published — a bare `if` with no else. So when a second
     * order was confirmed for a pig another order had already reserved, this
     * method returned silently, confirm() carried on, and BOTH orders reached
     * 'confirmed' for one animal. Two customers were told they had bought the
     * same pig, and nothing anywhere recorded a problem. Called under
     * lockForUpdate(), so the status read here cannot be stale.
     */
    protected function reservePigListing(StorefrontListing $listing, bool $reserve, User $actor): void
    {
        if ($reserve) {
            if ($listing->status !== StorefrontListing::STATUS_PUBLISHED) {
                throw new InventoryConflictException(sprintf(
                    'Listing #%d is no longer available (status: %s). This animal has most '
                    . 'likely been reserved or sold by another order.',
                    $listing->getKey(),
                    $listing->status,
                ));
            }

            $this->listingService->transitionTo($listing, StorefrontListing::STATUS_RESERVED, $actor);

            return;
        }

        // Release: reserved → published.
        if ($listing->status === StorefrontListing::STATUS_RESERVED) {
            $this->listingService->transitionTo($listing, StorefrontListing::STATUS_PUBLISHED, $actor);
        }
    }

    /**
     * Decrement or restore a quantity-backed listing.
     *
     * CF-P0-006: the reserve branch previously clamped a negative result to
     * zero. That is the definition of silent overselling — the order confirmed,
     * the customer was promised goods that do not exist, and the only trace was
     * a listing sitting at exactly zero. It also technically satisfied the
     * "stock cannot become negative" invariant while violating its intent, which
     * is why the invariant alone is not a sufficient test.
     *
     * Insufficient stock now raises InventoryConflictException, which rolls the
     * whole confirmation back: the order stays unconfirmed and the quantity is
     * untouched. Called under lockForUpdate(), so this read-modify-write cannot
     * interleave with another confirmation.
     */
    protected function adjustProductQuantity(StorefrontListing $listing, string $quantity, bool $reserve, User $actor): void
    {
        if ($reserve) {
            $available = (string) $listing->quantity_available;
            $newQty    = bcsub($available, $quantity, 4);

            if (bccomp($newQty, '0', 4) < 0) {
                throw new InventoryConflictException(sprintf(
                    'Listing #%d has %s available but %s was requested. Stock was consumed '
                    . 'after this order was placed.',
                    $listing->getKey(),
                    rtrim(rtrim($available, '0'), '.') ?: '0',
                    rtrim(rtrim($quantity, '0'), '.') ?: '0',
                ));
            }

            $listing->forceFill(['quantity_available' => $newQty])->save();

            if (bccomp($newQty, '0', 4) <= 0 && $listing->status === StorefrontListing::STATUS_PUBLISHED) {
                $this->listingService->transitionTo($listing, StorefrontListing::STATUS_SOLD_OUT, $actor);
            }

            return;
        }

        // Release: restore quantity and return sold-out stock to sale.
        //
        // CF-P2-023: this transitioned to UNPUBLISHED while its own comment said
        // "re-publish". Stock came back but became invisible to customers until a
        // staff member noticed and republished it by hand, so a routine
        // cancellation quietly withdrew sellable inventory from the shop.
        //
        // The original code was not simply careless: ListingService's state
        // machine does NOT permit sold_out -> published. UNPUBLISHED was the only
        // legal destination, and the comment was aspirational.
        //
        // Resolved by stepping through both LEGAL transitions —
        // sold_out -> unpublished -> published — rather than widening the state
        // machine. Two reasons for that choice:
        //
        //  - The transition table is a deliberate constraint used elsewhere; a
        //    new sold_out -> published edge would relax it for every caller, not
        //    just this one.
        //  - Each hop goes through transitionTo(), so both are audited. The
        //    intermediate state is also honest: for an instant the listing really
        //    has stock but is not yet live.
        //
        // Stock that was sellable before the order is sellable again after it is
        // cancelled; requiring a manual republish after every cancellation is an
        // operational trap, which is what the finding is really about.
        $newQty = bcadd((string) $listing->quantity_available, $quantity, 4);
        $listing->forceFill(['quantity_available' => $newQty])->save();

        if ($listing->status === StorefrontListing::STATUS_SOLD_OUT && bccomp($newQty, '0', 4) > 0) {
            $this->listingService->transitionTo($listing, StorefrontListing::STATUS_UNPUBLISHED, $actor);
            $this->listingService->transitionTo($listing, StorefrontListing::STATUS_PUBLISHED, $actor);
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
