<?php

namespace App\Storefront\Services;

use App\Models\Storefront\StorefrontInventoryHold;
use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontOrder;
use App\Storefront\Services\Exceptions\InventoryConflictException;
use App\Storefront\Support\StorefrontSettings;

/**
 * Checkout holds (Phase 10, blueprint §37).
 *
 * THE GUARANTEE
 * -------------
 * Two buyers must not both be able to pay for the last animal. Before this
 * phase nothing happened to inventory between checkout and staff confirmation:
 * both checkouts succeeded, both buyers were sent to Paystack, and the second
 * one discovered the problem after their money had left.
 *
 * A hold is placed inside the checkout transaction, under the same row lock the
 * confirmation path already takes. The second checkout blocks on that lock,
 * then reads the first buyer's hold and is refused before any money moves.
 *
 * HOLDS DO NOT MOVE INVENTORY
 * ---------------------------
 * `quantity_available` is decremented once, by
 * `OrderService::applyInventoryEffect()` at confirmation, exactly as before. A
 * hold that also decremented would take the same stock twice — once softly and
 * once for real — and the release path would then hand back stock that was
 * never taken.
 *
 * What a hold does is subtract from what the shop will *sell*:
 * `availableToSell()` is `quantity_available` minus live holds. Confirmation
 * consumes the hold in the same transaction that decrements the column, so the
 * two never both count.
 *
 * LOCK ORDER IS THE SAME ORDER THE CONFIRMATION PATH USES
 * -------------------------------------------------------
 * `applyInventoryEffect()` aggregates per listing, `ksort`s numerically and
 * locks ascending. This does the same, deliberately and for the same reason: two
 * transactions taking the same two listings in opposite orders deadlock. Every
 * path that locks listings in this codebase now locks them by ascending id.
 *
 * The aggregation is not cosmetic either — an order may legitimately carry two
 * lines for one listing, and locking it twice while applying each line
 * separately would apply two claims against a single lock.
 */
class HoldService
{
    /**
     * Place holds for every line of an order.
     *
     * MUST be called inside the caller's transaction. `CheckoutService` calls it
     * within the transaction that creates the order, so a hold cannot survive a
     * checkout that failed afterwards, and an order cannot exist without its
     * holds.
     *
     * @return list<StorefrontInventoryHold>
     */
    public function placeForOrder(StorefrontOrder $order): array
    {
        $order->loadMissing('items');

        $quantities = $this->quantitiesByListing($order);

        if ($quantities === []) {
            return [];
        }

        $expiresAt = $this->expiryFor($order);
        $holds     = [];

        foreach ($quantities as $listingId => $quantity) {
            // Lock first, read second. Reading availability before taking the
            // lock is the classic check-then-act race: both transactions see
            // "one left" and both proceed.
            $listing = StorefrontListing::query()
                ->whereKey($listingId)
                ->lockForUpdate()
                ->first();

            if ($listing === null) {
                throw new InventoryConflictException(
                    "Listing #{$listingId} is no longer available."
                );
            }

            $available = $this->availableToSell($listing, excludingOrderId: $order->getKey());

            if (bccomp($quantity, $available, 4) > 0) {
                throw new InventoryConflictException(sprintf(
                    '"%s" — only %s left. Someone else is checking out with the rest right now.',
                    $listing->title,
                    $this->trim($available),
                ));
            }

            // updateOrCreate against the unique (order_id, listing_id) key: a
            // retried checkout for the same order updates its own hold rather
            // than claiming the stock a second time.
            $holds[] = StorefrontInventoryHold::updateOrCreate(
                ['order_id' => $order->getKey(), 'listing_id' => $listingId],
                [
                    'organization_id' => $order->organization_id,
                    'quantity'        => $quantity,
                    'status'          => StorefrontInventoryHold::STATUS_ACTIVE,
                    'expires_at'      => $expiresAt,
                    'resolved_at'     => null,
                    'resolution_reason' => null,
                ],
            );
        }

        return $holds;
    }

    /**
     * What the shop may still sell of this listing.
     *
     * `quantity_available` minus every live hold. Call this — never the column
     * — anywhere stock is offered to a buyer.
     *
     * `excludingOrderId` lets an order's own hold be ignored while it is being
     * re-placed, so a retry does not find itself in the way.
     */
    public function availableToSell(StorefrontListing $listing, ?int $excludingOrderId = null): string
    {
        $held = StorefrontInventoryHold::query()
            ->where('listing_id', $listing->getKey())
            ->when($excludingOrderId !== null, fn ($q) => $q->where('order_id', '!=', $excludingOrderId))
            ->live()
            ->pluck('quantity');

        $total = '0';
        foreach ($held as $quantity) {
            $total = bcadd($total, (string) $quantity, 4);
        }

        $available = bcsub((string) $listing->quantity_available, $total, 4);

        // Never negative. Overselling is prevented by the locks; a negative
        // figure here would only mean stock was withdrawn behind an existing
        // hold, and reporting "minus two available" helps nobody.
        return bccomp($available, '0', 4) < 0 ? '0.0000' : $available;
    }

    /**
     * Kilograms of carcass weight already spoken for on this listing.
     *
     * Head and kilograms are counted SEPARATELY and deliberately. A carcass
     * hold reserves weight, not animals: 400 kg might be four pigs or six, and
     * which ones is a fulfilment decision nobody has made yet. Adding that 400
     * to a column that counts head would claim the listing had four hundred
     * animals spoken for, and subtracting head from kilograms is worse.
     */
    public function carcassKgHeld(StorefrontListing $listing, ?int $excludingOrderId = null): string
    {
        $held = StorefrontInventoryHold::query()
            ->where('listing_id', $listing->getKey())
            ->where('hold_type', StorefrontInventoryHold::TYPE_CARCASS_KG)
            ->when($excludingOrderId !== null, fn ($q) => $q->where('order_id', '!=', $excludingOrderId))
            ->live()
            ->pluck('requested_kg');

        $total = '0.0000';

        foreach ($held as $kg) {
            $total = bcadd($total, (string) ($kg ?? '0'), 4);
        }

        return $total;
    }

    /**
     * Consume an order's holds — the stock has actually moved.
     *
     * Called by `OrderService::confirm()` inside the confirmation transaction,
     * after `applyInventoryEffect()` has decremented the column. Consuming
     * means the hold stops suppressing availability, because the column now
     * reflects the sale.
     */
    public function consumeForOrder(StorefrontOrder $order): int
    {
        return $this->resolve($order, StorefrontInventoryHold::STATUS_CONSUMED, 'Order confirmed.');
    }

    /**
     * Give the stock back to the shop.
     *
     * Cancellation, payment failure, or a buyer who never came back.
     */
    public function releaseForOrder(StorefrontOrder $order, string $reason): int
    {
        return $this->resolve($order, StorefrontInventoryHold::STATUS_RELEASED, $reason);
    }

    /**
     * Expire holds whose clock has run out.
     *
     * A single conditional UPDATE, not a read-then-write loop. `where(status =
     * active)` is what makes it safe to run alongside a confirmation: a hold
     * that confirmation has already consumed is no longer active, so this
     * cannot reopen it, and if this wins the race the confirmation simply finds
     * no hold and re-validates the stock under its own lock — which it does
     * anyway.
     *
     * Holds with a null `expires_at` are waiting on a person, not a clock, and
     * are skipped by construction.
     */
    public function expireDue(): int
    {
        return StorefrontInventoryHold::query()
            ->where('status', StorefrontInventoryHold::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update([
                'status'            => StorefrontInventoryHold::STATUS_EXPIRED,
                'resolved_at'       => now(),
                'resolution_reason' => 'Hold expired before payment completed.',
                'updated_at'        => now(),
            ]);
    }

    /**
     * When this order's holds should lapse.
     *
     * An order waiting on the gateway is on a clock — §37 sets it at Paystack
     * initialisation, and a buyer who wanders off mid-payment must not park
     * stock indefinitely. An order waiting on a member of staff is not: §37's
     * own table says the hold "remains until staff decision", and expiring
     * those would fail routine cash orders because the farm answered the phone
     * slowly.
     */
    protected function expiryFor(StorefrontOrder $order): ?\Illuminate\Support\Carbon
    {
        if ($order->status !== StorefrontOrder::STATUS_PENDING_PAYMENT) {
            return null;
        }

        return now()->addMinutes(
            StorefrontSettings::checkoutHoldMinutes($order->organization_id)
        );
    }

    /**
     * Move an order's live holds to a terminal state.
     *
     * Scoped to `status = active` so a second call is a no-op rather than
     * rewriting a hold that confirmation already consumed. Cancelling an order
     * twice must not turn a consumed hold into a released one — that would hand
     * back stock the sale had already taken.
     */
    protected function resolve(StorefrontOrder $order, string $status, string $reason): int
    {
        // One conditional UPDATE. No transaction wrapper: a single statement is
        // already atomic, and wrapping it would put a savepoint inside every
        // confirmation for no gain.
        return StorefrontInventoryHold::query()
            ->where('order_id', $order->getKey())
            ->where('status', StorefrontInventoryHold::STATUS_ACTIVE)
            ->update([
                'status'            => $status,
                'resolved_at'       => now(),
                'resolution_reason' => mb_substr($reason, 0, 200),
                'updated_at'        => now(),
            ]);
    }

    /**
     * Order lines aggregated per listing, ascending by listing id.
     *
     * @return array<int, string>
     */
    protected function quantitiesByListing(StorefrontOrder $order): array
    {
        $quantities = [];

        foreach ($order->items as $item) {
            if ($item->listing_id === null) {
                continue;
            }

            $listingId = (int) $item->listing_id;
            $quantities[$listingId] = bcadd(
                $quantities[$listingId] ?? '0',
                (string) $item->quantity,
                4,
            );
        }

        ksort($quantities, SORT_NUMERIC);

        return $quantities;
    }

    protected function trim(string $decimal): string
    {
        $trimmed = rtrim(rtrim($decimal, '0'), '.');

        return $trimmed === '' ? '0' : $trimmed;
    }
}
