<?php

namespace App\Storefront\Services;

use App\Models\PigManagement;
use App\Models\PigSale;
use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontListingPig;
use App\Models\Storefront\StorefrontOrder;
use App\Models\Storefront\StorefrontOrderItem;
use App\Models\Storefront\StorefrontOrderItemPigAllocation;
use App\Models\User;
use App\Services\Sales\RecordPigSaleService;
use App\Storefront\Services\Exceptions\InventoryConflictException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Which exact animals fulfil an order line, and turning that into real sales.
 *
 * THE ONE RULE THAT SHAPES THIS CLASS
 * -----------------------------------
 * It does not write a pig sale. `RecordPigSaleService::execute()` does, once
 * per animal, and that service soft-deletes the pig, audits, and dispatches
 * `PigSaleRecorded` so the finance subscribers post exactly as they do for a
 * farm-gate sale. Reimplementing any of that here would create the second
 * sales ledger this feature exists to avoid.
 *
 * WHY SELECTION AND APPROVAL ARE SEPARATE
 * ---------------------------------------
 * Selecting animals is reversible and happens while staff are still deciding.
 * Approval is not: it sells them. Keeping them apart means a manager can pick
 * three pigs, be interrupted, and come back — and it means the irreversible
 * step re-validates everything rather than trusting a selection that may be
 * minutes or days old. A pig can die, or be sold at the gate, in between.
 *
 * CONCURRENCY
 * -----------
 * Approval locks in a fixed order — order, then listings, then pool rows, then
 * pigs, each ascending by id. Every locking path in this application uses
 * ascending id order for exactly this reason: two approvers with overlapping
 * animals must queue, not deadlock.
 */
class StorefrontPigAllocationService
{
    public function __construct(
        protected RecordPigSaleService $pigSales,
        protected StorefrontPigPoolService $pool,
        protected PigListingEligibilityService $eligibility,
    ) {
    }

    /**
     * Is this a line that needs animals chosen before it can be confirmed?
     *
     * A pig-backed line whose listing carries a pool. A legacy single-pig line
     * — `source_id` naming one animal, no pool — is NOT one of these and keeps
     * its old path through the finance subscriber untouched.
     */
    public function requiresPigSelection(StorefrontOrderItem $item): bool
    {
        if ($item->listing_id === null) {
            return false;
        }

        return StorefrontListingPig::query()
            ->where('storefront_listing_id', $item->listing_id)
            ->exists();
    }

    /**
     * How many animals this line still needs chosen.
     */
    public function outstandingFor(StorefrontOrderItem $item): int
    {
        $required = (int) round((float) $item->quantity);

        $selected = StorefrontOrderItemPigAllocation::query()
            ->where('storefront_order_item_id', $item->getKey())
            ->whereIn('status', StorefrontOrderItemPigAllocation::ACTIVE_STATUSES)
            ->count();

        return max(0, $required - $selected);
    }

    /**
     * Check a proposed selection without committing it.
     *
     * Returns the problems as a list so a form can show all of them at once
     * rather than making staff fix one, resubmit, and discover the next.
     *
     * @param  list<int>  $pigIds
     * @return list<string>
     */
    public function validateSelection(StorefrontOrderItem $item, array $pigIds): array
    {
        $errors  = [];
        $pigIds  = array_values(array_unique(array_map('intval', $pigIds)));
        $required = (int) round((float) $item->quantity);

        if (count($pigIds) !== $required) {
            $errors[] = sprintf(
                'Select exactly %d pig%s for this line — %d selected.',
                $required,
                $required === 1 ? '' : 's',
                count($pigIds),
            );
        }

        $organizationId = (int) $item->organization_id;

        foreach ($pigIds as $pigId) {
            $poolRow = StorefrontListingPig::query()
                ->where('storefront_listing_id', $item->listing_id)
                ->where('pig_id', $pigId)
                ->first();

            if ($poolRow === null) {
                $errors[] = "Pig #{$pigId} is not part of this listing.";
                continue;
            }

            $pig = PigManagement::withTrashed()->find($pigId);

            if ($pig === null) {
                $errors[] = "Pig #{$pigId} could not be found.";
                continue;
            }

            $label = $this->label($pig);

            if ((int) $pig->organization_id !== $organizationId) {
                $errors[] = "Pig #{$pigId} could not be found.";
                continue;
            }

            if ($pig->trashed()) {
                $errors[] = "{$label} has already been sold or removed.";
                continue;
            }

            if ($poolRow->status === StorefrontListingPig::STATUS_SOLD) {
                $errors[] = "{$label} has already been sold.";
                continue;
            }

            if ($poolRow->status === StorefrontListingPig::STATUS_REMOVED) {
                $errors[] = "{$label} is no longer part of this listing.";
                continue;
            }

            // Committed to a DIFFERENT order line.
            $otherAllocation = StorefrontOrderItemPigAllocation::query()
                ->where('pig_id', $pigId)
                ->where('storefront_order_item_id', '!=', $item->getKey())
                ->whereIn('status', StorefrontOrderItemPigAllocation::ACTIVE_STATUSES)
                ->first();

            if ($otherAllocation !== null) {
                $errors[] = "{$label} is already committed to another order.";
                continue;
            }

            // Health, withdrawal, death — the same authority the pool used.
            //
            // This listing is ignored when asking "already on the shop?": every
            // animal being selected is in this pool by design, and counting
            // that as a blocker would make a pool unable to sell its own
            // animals.
            $verdict = $this->eligibility->check($pig, null, $organizationId, (int) $item->listing_id);

            if (($verdict['eligible'] ?? false) !== true) {
                $why = implode(' ', $verdict['blockers'] ?? ['It is not eligible for sale.']);
                $errors[] = "{$label} cannot be sold. {$why}";
            }
        }

        return $errors;
    }

    /**
     * Record which animals an approver chose for a line.
     *
     * Replaces any previous selection for the line, so re-submitting the form
     * is idempotent rather than additive — a manager who changes their mind
     * gets what they chose, not the union of both attempts.
     *
     * @param  list<int>  $pigIds
     * @return Collection<int, StorefrontOrderItemPigAllocation>
     */
    public function selectForOrderItem(StorefrontOrderItem $item, array $pigIds, User $actor): Collection
    {
        $pigIds = array_values(array_unique(array_map('intval', $pigIds)));

        return DB::transaction(function () use ($item, $pigIds, $actor) {
            // Lock the pool rows for the animals in play, ascending.
            StorefrontListingPig::query()
                ->where('storefront_listing_id', $item->listing_id)
                ->whereIn('pig_id', $pigIds ?: [0])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $errors = $this->validateSelection($item, $pigIds);

            if ($errors !== []) {
                throw new InventoryConflictException(implode(' ', $errors));
            }

            $this->releaseItemAllocations($item, $actor, 'Selection replaced.');

            $rows = collect();

            foreach ($pigIds as $pigId) {
                $poolRow = StorefrontListingPig::query()
                    ->where('storefront_listing_id', $item->listing_id)
                    ->where('pig_id', $pigId)
                    ->lockForUpdate()
                    ->first();

                $allocation = StorefrontOrderItemPigAllocation::query()
                    ->where('storefront_order_item_id', $item->getKey())
                    ->where('pig_id', $pigId)
                    ->first() ?? new StorefrontOrderItemPigAllocation();

                $allocation->forceFill([
                    'organization_id'           => $item->organization_id,
                    'storefront_order_id'       => $item->order_id,
                    'storefront_order_item_id'  => $item->getKey(),
                    'storefront_listing_id'     => $item->listing_id,
                    'storefront_listing_pig_id' => $poolRow?->getKey(),
                    'pig_id'                    => $pigId,
                    'selected_by_user_id'       => $actor->getKey(),
                    'selected_at'               => now(),
                    'status'                    => StorefrontOrderItemPigAllocation::STATUS_SELECTED,
                    'release_reason'            => null,
                ])->save();

                if ($poolRow !== null) {
                    $poolRow->status                  = StorefrontListingPig::STATUS_ALLOCATED;
                    $poolRow->allocated_order_id      = $item->order_id;
                    $poolRow->allocated_order_item_id = $item->getKey();
                    $poolRow->allocated_at            = now();
                    $poolRow->save();
                }

                $rows->push($allocation);
            }

            $listing = StorefrontListing::query()
                ->whereKey($item->listing_id)
                ->lockForUpdate()
                ->first();

            if ($listing !== null) {
                $this->pool->syncListingAvailability($listing, $actor);
            }

            return $rows;
        });
    }

    /**
     * Sell the selected animals. This is the irreversible step.
     *
     * IDEMPOTENT BY CONSTRUCTION
     * --------------------------
     * Only allocations still in an ACTIVE state are sold, and each becomes
     * `sold` inside the same transaction that creates its sale. A second call —
     * a double-clicked Confirm, a retried outbox event — finds nothing active
     * and creates nothing. That is what makes "approve twice" safe without a
     * separate idempotency key.
     *
     * @return Collection<int, PigSale>
     */
    public function approveAllocationsForOrder(StorefrontOrder $order, User $actor): Collection
    {
        return DB::transaction(function () use ($order, $actor) {
            $lockedOrder = StorefrontOrder::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $allocations = StorefrontOrderItemPigAllocation::query()
                ->where('storefront_order_id', $lockedOrder->getKey())
                ->whereIn('status', StorefrontOrderItemPigAllocation::ACTIVE_STATUSES)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($allocations->isEmpty()) {
                return collect();
            }

            // Lock every animal, ascending, before selling any of them.
            $pigIds = $allocations->pluck('pig_id')->map(fn ($id) => (int) $id)->sort()->values()->all();

            $pigs = PigManagement::query()
                ->whereIn('id', $pigIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $sales = collect();

            // Money is split per LINE, not per order: each line has its own
            // total and its own tax, and the animals on that line share them.
            // Computed once up front so the residual lands on the last animal
            // of each line and the parts sum to the line exactly.
            $shareByAllocationId = $this->shareMoneyAcrossAllocations($allocations, $lockedOrder);

            foreach ($allocations as $allocation) {
                $item = StorefrontOrderItem::query()->whereKey($allocation->storefront_order_item_id)->first();
                $pig  = $pigs->get((int) $allocation->pig_id);

                if ($pig === null || $item === null) {
                    throw new InventoryConflictException(
                        "Pig #{$allocation->pig_id} is no longer available; re-select the pigs for this order."
                    );
                }

                // Re-validate at the moment of sale. The selection may be old,
                // and an animal can die or be sold at the gate in between.
                $errors = $this->validateSelection($item, [(int) $allocation->pig_id]);

                // The count message is expected here — this checks ONE animal
                // against a line that may need several — so only real
                // per-animal problems matter.
                $errors = array_values(array_filter(
                    $errors,
                    fn (string $e) => ! str_starts_with($e, 'Select exactly')
                ));

                if ($errors !== []) {
                    throw new InventoryConflictException(implode(' ', $errors));
                }

                [$sharePrice, $shareTax] = $shareByAllocationId[$allocation->getKey()] ?? ['0.00', '0.00'];

                $sale = $this->pigSales->execute(
                    $pig,
                    $actor,
                    $this->saleDataFor($item, $pig, $lockedOrder, $sharePrice, $shareTax),
                );

                $allocation->pig_sale_id         = $sale->getKey();
                $allocation->approved_by_user_id = $actor->getKey();
                $allocation->approved_at         = now();
                $allocation->status              = StorefrontOrderItemPigAllocation::STATUS_SOLD;
                $allocation->save();

                if ($allocation->storefront_listing_pig_id !== null) {
                    $poolRow = StorefrontListingPig::query()
                        ->whereKey($allocation->storefront_listing_pig_id)
                        ->lockForUpdate()
                        ->first();

                    if ($poolRow !== null) {
                        $poolRow->status      = StorefrontListingPig::STATUS_SOLD;
                        $poolRow->pig_sale_id = $sale->getKey();
                        $poolRow->sold_at     = now();
                        $poolRow->save();
                    }
                }

                $sales->push($sale);
            }

            // Re-derive every touched listing's public quantity.
            $listingIds = $allocations->pluck('storefront_listing_id')->filter()->unique()->sort()->values();

            foreach ($listingIds as $listingId) {
                $listing = StorefrontListing::query()->whereKey($listingId)->lockForUpdate()->first();

                if ($listing !== null) {
                    $this->pool->syncListingAvailability($listing, $actor);
                }
            }

            return $sales;
        });
    }

    /**
     * Give the animals back — order cancelled, or selection withdrawn.
     *
     * Sold allocations are never touched: a completed sale is not undone by
     * cancelling the order that produced it. That is a credit note's job.
     */
    public function releaseOrderAllocations(StorefrontOrder $order, User $actor, string $reason): int
    {
        return DB::transaction(function () use ($order, $actor, $reason) {
            $allocations = StorefrontOrderItemPigAllocation::query()
                ->where('storefront_order_id', $order->getKey())
                ->whereIn('status', StorefrontOrderItemPigAllocation::ACTIVE_STATUSES)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $listingIds = [];

            foreach ($allocations as $allocation) {
                $allocation->status         = StorefrontOrderItemPigAllocation::STATUS_RELEASED;
                $allocation->release_reason = $reason;
                $allocation->save();

                if ($allocation->storefront_listing_pig_id !== null) {
                    $poolRow = StorefrontListingPig::query()
                        ->whereKey($allocation->storefront_listing_pig_id)
                        ->lockForUpdate()
                        ->first();

                    // Only an ALLOCATED row goes back on offer. One that has
                    // since been sold or removed stays where it is.
                    if ($poolRow !== null && $poolRow->status === StorefrontListingPig::STATUS_ALLOCATED) {
                        $poolRow->status                  = StorefrontListingPig::STATUS_AVAILABLE;
                        $poolRow->allocated_order_id      = null;
                        $poolRow->allocated_order_item_id = null;
                        $poolRow->allocated_at            = null;
                        $poolRow->save();

                        $listingIds[$poolRow->storefront_listing_id] = true;
                    }
                }
            }

            foreach (array_keys($listingIds) as $listingId) {
                $listing = StorefrontListing::query()->whereKey($listingId)->lockForUpdate()->first();

                if ($listing !== null) {
                    $this->pool->syncListingAvailability($listing, $actor);
                }
            }

            return $allocations->count();
        });
    }

    /* ------------------------------------------------------------------ */

    protected function releaseItemAllocations(StorefrontOrderItem $item, User $actor, string $reason): void
    {
        $existing = StorefrontOrderItemPigAllocation::query()
            ->where('storefront_order_item_id', $item->getKey())
            ->whereIn('status', StorefrontOrderItemPigAllocation::ACTIVE_STATUSES)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($existing as $allocation) {
            $allocation->status         = StorefrontOrderItemPigAllocation::STATUS_RELEASED;
            $allocation->release_reason = $reason;
            $allocation->save();

            if ($allocation->storefront_listing_pig_id !== null) {
                $poolRow = StorefrontListingPig::query()
                    ->whereKey($allocation->storefront_listing_pig_id)
                    ->lockForUpdate()
                    ->first();

                if ($poolRow !== null && $poolRow->status === StorefrontListingPig::STATUS_ALLOCATED) {
                    $poolRow->status                  = StorefrontListingPig::STATUS_AVAILABLE;
                    $poolRow->allocated_order_id      = null;
                    $poolRow->allocated_order_item_id = null;
                    $poolRow->allocated_at            = null;
                    $poolRow->save();
                }
            }
        }
    }

    /**
     * The sale payload for ONE animal out of a multi-animal line.
     *
     * Mirrors `StorefrontOrderConfirmedSubscriber::routePigLine()` rather than
     * inventing a second shape, because that payload is the one already proven
     * to satisfy the `pig_sales` CHECK constraints and the finance subscribers.
     * Two payloads for the same kind of sale would drift.
     *
     * WHAT DIFFERS FOR A POOL LINE
     * ----------------------------
     * A single-pig line is priced per kilo and carries a weight. A pool line is
     * priced per HEAD — the buyer took three animals at an agreed price each and
     * nobody weighed them at checkout. So:
     *
     *   - `sale_price` is this animal's share of the line, not the line total;
     *   - `sale_weight` and `price_per_kg` stay null when there is no weight,
     *     which the columns permit;
     *   - `weight_type` still has to be 'live' or 'carcass' because the column
     *     is NOT NULL with a CHECK, and 'live' is its default.
     *
     * `price_source => 'explicit'` stops `RecordPigSaleService` re-pricing from
     * farm settings: the customer already agreed this price in the shop.
     *
     * MONEY IS DECIMAL STRINGS
     * ------------------------
     * bcmath throughout, per the standing rule. An earlier draft of this method
     * divided the line total by the head count in binary floating point, which
     * is exactly what that rule exists to prevent.
     *
     * @param  string  $shareOfLineTax  this animal's share of the line's tax
     * @return array<string, mixed>
     */
    protected function saleDataFor(
        StorefrontOrderItem $item,
        PigManagement $pig,
        StorefrontOrder $order,
        string $sharePrice,
        string $shareOfLineTax,
    ): array {
        $weight = $item->weight_kg !== null ? (string) $item->weight_kg : null;

        // Per-kg only when a weight is actually known for the animal.
        $pricePerKg = ($weight !== null && bccomp($weight, '0', 4) === 1)
            ? bcdiv($sharePrice, $weight, 4)
            : null;

        return [
            'price_source'   => 'explicit',
            'sale_price'     => (float) $sharePrice,
            'sale_weight'    => $weight !== null ? (float) $weight : null,
            'price_per_kg'   => $pricePerKg !== null ? (float) $pricePerKg : null,
            // 'live' is the column default and one of the two values its CHECK
            // permits; a pool line has no basis of its own.
            'weight_type'    => in_array($item->price_basis, ['live', 'carcass'], true)
                ? $item->price_basis
                : 'live',
            'sale_date'      => now()->toDateString(),
            // A storefront sale IS a market sale. 'storefront' is the channel,
            // not the purpose, and putting it in sale_purpose breaks the CHECK
            // constraint — the mistake routePigLine documents.
            'sale_purpose'   => 'market',
            'tax_profile_id' => $order->tax_profile_id,
            'tax_amount'     => $shareOfLineTax,
            'tax_snapshot'   => $order->tax_snapshot,
            'payment_status' => $order->payment_status === StorefrontOrder::PAY_PAID ? 'paid' : 'pending',
            'payment_method' => $order->payment_method,
            'buyer_name'     => $order->ship_to_name ?: ($order->counterparty?->name ?? null),
            'customer_id'    => $order->counterparty_id,
            'notes'          => "Storefront order {$order->reference}.",
        ];
    }

    /**
     * Split a line's money across its animals so the parts sum to the whole.
     *
     * The last animal takes the residual. Dividing GHS 1,000 across three head
     * gives 333.33, 333.33, 333.34 — never three times 333.33, which would lose
     * a pesewa and leave the sales not adding up to the order. Phase 12b
     * established this rule for tax allocation and it applies identically here.
     *
     * @return list<string>  one decimal string per animal, in order
     */
    protected function splitEvenly(string $total, int $parts, int $scale = 2): array
    {
        if ($parts <= 0) {
            return [];
        }

        $each   = bcdiv($total, (string) $parts, $scale);
        $shares = array_fill(0, $parts - 1, $each);

        $allocated = bcmul($each, (string) ($parts - 1), $scale);
        $shares[]  = bcsub($total, $allocated, $scale);

        return $shares;
    }

    /**
     * Work out each animal's share of its line's money.
     *
     * Grouped by line because that is where the totals live. Within a line the
     * shares are even with the residual on the last animal, so three head out
     * of a GHS 1,000 line come to 333.33 + 333.33 + 333.34 and the sales add up
     * to the order rather than losing a pesewa.
     *
     * @param  Collection<int, StorefrontOrderItemPigAllocation>  $allocations
     * @return array<int, array{0:string, 1:string}>  allocation id => [price, tax]
     */
    protected function shareMoneyAcrossAllocations(Collection $allocations, StorefrontOrder $order): array
    {
        $shares = [];

        foreach ($allocations->groupBy('storefront_order_item_id') as $itemId => $forItem) {
            $item = StorefrontOrderItem::query()->whereKey($itemId)->first();

            if ($item === null) {
                continue;
            }

            $count      = $forItem->count();
            $lineTotal  = (string) ($item->line_total ?? '0.00');
            $lineTax    = $this->lineTaxFor($item, $order);

            $priceShares = $this->splitEvenly($lineTotal, $count);
            $taxShares   = $this->splitEvenly($lineTax, $count);

            foreach ($forItem->values() as $index => $allocation) {
                $shares[$allocation->getKey()] = [
                    $priceShares[$index] ?? '0.00',
                    $taxShares[$index] ?? '0.00',
                ];
            }
        }

        return $shares;
    }

    /**
     * This line's share of the order's tax.
     *
     * Delegates to `StorefrontTaxService::allocate()` — the same allocator the
     * finance subscriber uses — so a pooled line and a single-pig line compute
     * the identical figure. Re-deriving it here with a different rounding rule
     * is how two paths end up disagreeing by a pesewa.
     */
    protected function lineTaxFor(StorefrontOrderItem $item, StorefrontOrder $order): string
    {
        $snapshot = $order->tax_snapshot;

        if (empty($snapshot)) {
            return '0.00';
        }

        $items = StorefrontOrderItem::query()
            ->where('order_id', $order->getKey())
            ->orderBy('id')
            ->get();

        $lineTotals = $items->map(fn ($row) => (string) ($row->line_total ?? '0.00'))->all();

        try {
            $calculation = \App\Services\Finance\Tax\TaxCalculation::fromSnapshot($snapshot);

            $allocated = app(StorefrontTaxService::class)->allocate($calculation, $lineTotals);
        } catch (\Throwable $e) {
            // A malformed or unrecognised snapshot must not stop a sale. The
            // tax still reaches the books through the order's own posting.
            return '0.00';
        }

        $index = $items->search(fn ($row) => (int) $row->getKey() === (int) $item->getKey());

        if ($index === false) {
            return '0.00';
        }

        return (string) ($allocated[$index] ?? '0.00');
    }

    protected function label(PigManagement $pig): string
    {
        $tag = $pig->ear_tag_number ?: ('#' . $pig->getKey());

        return "Pig {$tag}";
    }
}
