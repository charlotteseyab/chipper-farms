<?php

namespace App\Storefront\Services;

use App\Models\PigManagement;
use App\Models\PigSale;
use App\Models\PaymentMethod;
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
        protected StorefrontPigPricingService $pricing,
        protected StorefrontPigletMaterialisationService $piglets,
    ) {
    }

    /**
     * Is this a line that needs animals chosen before it can be confirmed?
     *
     * A pig-backed line whose listing carries a pool. A legacy single-pig line
     * — `source_id` naming one animal, no pool — is NOT one of these and keeps
     * its old path through the finance subscriber untouched.
     */
    /**
     * WHERE A LINE IS IN ITS FULFILMENT.
     *
     *   not_applicable   nothing to choose — a bag of feed
     *   needs_selection  a manager still has to say which animals
     *   ready            the animals are chosen and can be approved
     *   conflict         chosen, but something about them no longer holds
     *   sold             approved; real sales exist in /sales
     *
     * A screen showing a manager ten order lines needs to know which of them
     * are waiting on them and which are waiting on nothing, and "conflict" has
     * to be visible BEFORE they press approve — an animal that died or was sold
     * at the gate since selection is not something to discover mid-transaction.
     */
    public const STATE_NOT_APPLICABLE  = 'not_applicable';
    public const STATE_NEEDS_SELECTION = 'needs_selection';
    public const STATE_READY           = 'ready';
    public const STATE_CONFLICT        = 'conflict';
    public const STATE_SOLD            = 'sold';

    public function requiresPigSelection(StorefrontOrderItem $item): bool
    {
        if ($item->listing_id === null) {
            return false;
        }

        // A manual line — feed, manure, a service — has no animals behind it
        // whatever the listing it came from happens to hold.
        if ($item->purchase_type === StorefrontPigPricingService::PURCHASE_MANUAL) {
            return false;
        }

        return StorefrontListingPig::query()
            ->where('storefront_listing_id', $item->listing_id)
            ->exists();
    }

    /**
     * The state of one line, as a manager needs to see it.
     */
    public function fulfilmentState(StorefrontOrderItem $item): string
    {
        // A piglet line has no animals to choose between — they do not exist
        // yet. It is ready the moment it is ordered, and sold once approval has
        // created and sold them.
        if ($this->piglets->appliesTo($item)) {
            return $this->piglets->outstandingFor($item) === 0
                ? self::STATE_SOLD
                : self::STATE_READY;
        }

        if (! $this->requiresPigSelection($item)) {
            return self::STATE_NOT_APPLICABLE;
        }

        $active = StorefrontOrderItemPigAllocation::query()
            ->where('storefront_order_item_id', $item->getKey())
            ->whereIn('status', StorefrontOrderItemPigAllocation::ACTIVE_STATUSES)
            ->get();

        $soldCount = StorefrontOrderItemPigAllocation::query()
            ->where('storefront_order_item_id', $item->getKey())
            ->where('status', StorefrontOrderItemPigAllocation::STATUS_SOLD)
            ->count();

        if ($active->isEmpty()) {
            return $soldCount > 0 ? self::STATE_SOLD : self::STATE_NEEDS_SELECTION;
        }

        if (! $this->selectionIsComplete($item, $active->count())) {
            return self::STATE_NEEDS_SELECTION;
        }

        // Re-checked now rather than trusted. A selection can be days old.
        $errors = $this->validateSelection($item, $active->pluck('pig_id')->map('intval')->all());

        return $errors === [] ? self::STATE_READY : self::STATE_CONFLICT;
    }

    /**
     * Why a line is in conflict, in the words a manager can act on.
     *
     * @return list<string>
     */
    public function fulfilmentProblems(StorefrontOrderItem $item): array
    {
        $pigIds = StorefrontOrderItemPigAllocation::query()
            ->where('storefront_order_item_id', $item->getKey())
            ->whereIn('status', StorefrontOrderItemPigAllocation::ACTIVE_STATUSES)
            ->pluck('pig_id')->map('intval')->all();

        return $pigIds === [] ? [] : $this->validateSelection($item, $pigIds);
    }

    /**
     * Kilograms this line has asked for, or null if it is not sold by weight.
     */
    public function requestedKgFor(StorefrontOrderItem $item): ?string
    {
        if ($item->purchase_type !== StorefrontPigPricingService::PURCHASE_CARCASS) {
            return null;
        }

        return $item->requested_kg === null ? null : bcadd((string) $item->requested_kg, '0', 4);
    }

    /**
     * Live weight of the animals currently chosen for this line.
     */
    public function selectedKgFor(StorefrontOrderItem $item): string
    {
        $pigIds = StorefrontOrderItemPigAllocation::query()
            ->where('storefront_order_item_id', $item->getKey())
            ->whereIn('status', StorefrontOrderItemPigAllocation::ACTIVE_STATUSES)
            ->pluck('pig_id')->map('intval')->all();

        if ($pigIds === []) {
            return '0.0000';
        }

        $total = '0.0000';

        foreach (PigManagement::withTrashed()->whereKey($pigIds)->with('latestWeight')->get() as $pig) {
            $total = bcadd($total, $this->pricing->latestWeightKg($pig) ?? '0', 4);
        }

        return $total;
    }

    /**
     * The live animals/weights the customer paid for on this line.
     *
     * @return list<array{row_id:int|null,pig_id:int|null,ear_tag:?string,kg:string}>
     */
    public function expectedLiveSelectionsFor(StorefrontOrderItem $item): array
    {
        if ($item->purchase_type !== StorefrontPigPricingService::PURCHASE_LIVE) {
            return [];
        }

        $snapshot = is_array($item->selected_pig_weight_snapshot)
            ? $item->selected_pig_weight_snapshot
            : [];

        $expected = [];

        foreach ($snapshot as $rowId => $row) {
            if (! is_array($row) || ! isset($row['kg'])) {
                continue;
            }

            $expected[] = [
                'row_id'  => is_numeric($rowId) ? (int) $rowId : null,
                'pig_id'  => isset($row['pig_id']) ? (int) $row['pig_id'] : null,
                'ear_tag' => isset($row['ear_tag']) ? (string) $row['ear_tag'] : null,
                'kg'      => bcadd((string) $row['kg'], '0', 4),
            ];
        }

        return $expected;
    }

    /**
     * Is this line's selection finished?
     *
     * A LIVE or quantity line is satisfied by a COUNT: the buyer asked for
     * three animals and three are chosen.
     *
     * A CARCASS line is satisfied by WEIGHT: the buyer asked for 150 kg, and
     * however many animals it takes to cover that is however many it takes.
     * Counting head there would refuse a correct selection of two heavy pigs
     * and accept a wrong one of a single light pig.
     */
    protected function selectionIsComplete(StorefrontOrderItem $item, int $selectedCount): bool
    {
        $requestedKg = $this->requestedKgFor($item);

        if ($requestedKg !== null) {
            return bccomp($this->selectedKgFor($item), $requestedKg, 4) >= 0;
        }

        return $selectedCount >= (int) round((float) $item->quantity);
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

        // A CARCASS line is measured in kilograms, not head. "Select exactly
        // one pig" is the wrong question when the buyer asked for 150 kg: it
        // would refuse two correct animals and accept one that is short.
        $requestedKg = $this->requestedKgFor($item);

        if ($requestedKg !== null) {
            if ($pigIds === []) {
                $errors[] = sprintf(
                    'Choose animals covering %s kg for this line.',
                    rtrim(rtrim($requestedKg, '0'), '.'),
                );
            }
        } elseif (count($pigIds) !== $required) {
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

        $errors = array_merge($errors, $this->validateLiveCustomerPromise($item, $pigIds));

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
    public function selectForOrderItem(StorefrontOrderItem $item, array $pigIds, ?User $actor): Collection
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
                    // Null for a guest: the buyer chose, and there is no staff
                    // account to name. A placeholder here would be a lie in the
                    // audit trail.
                    'selected_by_user_id'       => $actor?->getKey(),
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

            if ($rows->isNotEmpty()) {
                $order = StorefrontOrder::query()->whereKey($item->order_id)->first();
                if ($order !== null) {
                    $this->shareMoneyAcrossAllocations($rows, $order);
                }
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
     * A live line with customer-selected weights cannot be fulfilled with a
     * heavier replacement. The buyer paid for a specific weight/amount.
     *
     * @param  list<int>  $pigIds
     * @return list<string>
     */
    protected function validateLiveCustomerPromise(StorefrontOrderItem $item, array $pigIds): array
    {
        $expected = $this->expectedLiveSelectionsFor($item);

        if ($expected === [] || $pigIds === []) {
            return [];
        }

        $remaining = array_values($expected);
        $errors = [];

        foreach ($pigIds as $pigId) {
            $poolRow = StorefrontListingPig::query()
                ->where('storefront_listing_id', $item->listing_id)
                ->where('pig_id', $pigId)
                ->first();

            $pig = PigManagement::withTrashed()->with('latestWeight')->find($pigId);

            if (! $pig instanceof PigManagement) {
                continue;
            }

            $matched = null;

            foreach ($remaining as $index => $row) {
                if (($row['row_id'] !== null && $poolRow !== null && (int) $poolRow->getKey() === (int) $row['row_id'])
                    || ($row['pig_id'] !== null && (int) $pig->getKey() === (int) $row['pig_id'])) {
                    $matched = $index;
                    break;
                }
            }

            if ($matched === null) {
                $weight = $this->latestWeightKg($pig);

                if ($weight !== null) {
                    foreach ($remaining as $index => $row) {
                        if (bccomp($weight, $row['kg'], 4) === 0) {
                            $matched = $index;
                            break;
                        }
                    }
                }
            }

            if ($matched === null) {
                $weight = $this->latestWeightKg($pig);
                $expectedText = collect($remaining)
                    ->pluck('kg')
                    ->map(fn (string $kg) => rtrim(rtrim($kg, '0'), '.') . ' kg')
                    ->implode(', ');

                $errors[] = sprintf(
                    '%s does not match the customer-paid weight for this line%s.',
                    $this->label($pig) . ($weight !== null ? ' (' . rtrim(rtrim($weight, '0'), '.') . ' kg)' : ''),
                    $expectedText !== '' ? ' (' . $expectedText . ')' : '',
                );
                continue;
            }

            unset($remaining[$matched]);
            $remaining = array_values($remaining);
        }

        if ($remaining !== [] && count($pigIds) === count($expected)) {
            $errors[] = 'The selected pigs do not match all customer-paid live weights for this line.';
        }

        return $errors;
    }

    /**
     * Carry a LIVE line's customer choice onto the order.
     *
     * On a live purchase the buyer already picked the animals, by name, and
     * paid for those animals' weights. There is nothing for a manager to
     * choose, and asking them to choose would let them substitute a different
     * pig than the one that was bought and priced.
     *
     * So the allocation is created at the moment the order is placed, marked
     * `customer_selected` so an approver can see it was not their decision, and
     * the pool rows move to `allocated` — which takes them out of the sellable
     * pool immediately, so a second buyer cannot be shown an animal the first
     * has already paid for.
     *
     * Idempotent: called again for the same order, `selectForOrderItem()`
     * replaces the line's selection with the identical set.
     *
     * @return Collection<int, StorefrontOrderItemPigAllocation>
     */
    public function adoptCustomerSelections(StorefrontOrder $order, ?User $actor = null): Collection
    {
        $order->loadMissing('items');

        $adopted = collect();

        foreach ($order->items as $item) {
            if (! in_array($item->purchase_type, [
                StorefrontPigPricingService::PURCHASE_LIVE,
                StorefrontPigPricingService::PURCHASE_CARCASS,
            ], true)) {
                continue;
            }

            $rowIds = array_map('intval', (array) ($item->selected_listing_pig_ids ?? []));

            if ($rowIds === []) {
                continue;
            }

            $pigIds = StorefrontListingPig::query()
                ->whereKey($rowIds)
                ->where('storefront_listing_id', $item->listing_id)
                ->pluck('pig_id')
                ->map('intval')
                ->all();

            if ($pigIds === []) {
                continue;
            }

            foreach ($this->selectForOrderItem($item, $pigIds, $actor) as $allocation) {
                // The customer chose this, not staff. It changes what a manager
                // may substitute and what the audit trail has to explain.
                $allocation->forceFill([
                    'customer_selected' => true,
                    'purchase_type'     => $item->purchase_type,
                ])->save();

                $adopted->push($allocation);
            }
        }

        return $adopted;
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

            // PIGLETS BECOME ANIMALS HERE.
            //
            // A farm does not keep individual records for suckling piglets, so
            // a piglet line has nothing to allocate until now. The records are
            // created inside this transaction, immediately before being sold
            // through the same service as every other animal — which is the
            // whole point: no second way to sell a pig.
            //
            // Idempotent, so a retried approval does not put a second litter of
            // phantom animals in the herd.
            $lockedOrder->loadMissing('items');

            foreach ($lockedOrder->items as $orderItem) {
                $this->piglets->materialiseFor($orderItem, $actor);
            }

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
                //
                // A MATERIALISED piglet is the exception, and not an excuse: its
                // record was created seconds ago inside THIS transaction, it is
                // in no listing pool by design, and nothing about it can have
                // changed since. Running the pool checks against it would fail
                // on "not part of this listing" — a true statement about a
                // question that does not apply.
                $errors = ($allocation->allocation_snapshot['materialised'] ?? false) === true
                    ? []
                    : $this->validateSelection($item, [(int) $allocation->pig_id]);

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
                    $this->saleDataFor($item, $pig, $lockedOrder, $allocation, $sharePrice, $shareTax),
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
    public function releaseOrderAllocations(StorefrontOrder $order, ?User $actor, string $reason): int
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

    protected function releaseItemAllocations(StorefrontOrderItem $item, ?User $actor, string $reason): void
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
        StorefrontOrderItemPigAllocation $allocation,
        string $sharePrice,
        string $shareOfLineTax,
    ): array {
        $purchaseType = (string) ($allocation->purchase_type ?: $item->purchase_type ?: '');

        $weight = match ($purchaseType) {
            StorefrontPigPricingService::PURCHASE_LIVE => $allocation->allocated_live_weight_kg !== null
                ? (string) $allocation->allocated_live_weight_kg
                : $this->latestWeightKg($pig),
            StorefrontPigPricingService::PURCHASE_CARCASS => $allocation->allocated_carcass_weight_kg !== null
                ? (string) $allocation->allocated_carcass_weight_kg
                : null,
            default => null,
        };

        // Per-kg only when a weight is actually known for the animal.
        $pricePerKg = $item->price_per_kg_snapshot !== null
            ? bcadd((string) $item->price_per_kg_snapshot, '0', 4)
            : (($weight !== null && bccomp($weight, '0', 4) === 1)
                ? bcdiv($sharePrice, $weight, 4)
                : null);

        return [
            'price_source'   => 'explicit',
            'sale_price'     => (float) $sharePrice,
            'sale_weight'    => $weight !== null ? (float) $weight : null,
            'price_per_kg'   => $pricePerKg !== null ? (float) $pricePerKg : null,
            'weight_type'    => $purchaseType === StorefrontPigPricingService::PURCHASE_CARCASS
                ? 'carcass'
                : 'live',
            'sale_date'      => now()->toDateString(),
            // A storefront sale IS a market sale. 'storefront' is the channel,
            // not the purpose, and putting it in sale_purpose breaks the CHECK
            // constraint — the mistake routePigLine documents.
            'sale_purpose'   => 'market',
            'tax_profile_id' => $order->tax_profile_id,
            'tax_amount'     => $shareOfLineTax,
            'tax_snapshot'   => $order->tax_snapshot,
            'payment_status' => in_array($order->payment_status, [
                StorefrontOrder::PAY_PAID,
                StorefrontOrder::PAY_PAYSTACK_VERIF,
            ], true) ? 'paid' : 'pending',
            'payment_method' => $order->payment_method,
            'payment_method_id' => $this->paymentMethodIdForOrder($order),
            'buyer_name'     => $order->ship_to_name ?: ($order->counterparty?->name ?? null),
            'customer_id'    => $order->counterparty_id,
            'notes'          => "Storefront order {$order->reference}.",
            'storefront_allocation_id' => $allocation->getKey(),
        ];
    }

    protected function paymentMethodIdForOrder(StorefrontOrder $order): ?int
    {
        $code = $order->payment_method === StorefrontOrder::METHOD_PAYSTACK ? 'PAYSTACK' : 'CASH';

        return PaymentMethod::query()
            ->where(function ($q) use ($order) {
                $q->where('organization_id', $order->organization_id)->orWhereNull('organization_id');
            })
            ->where('code', $code)
            ->where('is_active', true)
            ->orderByRaw('organization_id is null')
            ->value('id');
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

            $forItem    = $forItem->values();
            $count      = $forItem->count();
            $lineTotal  = bcadd((string) ($item->line_total ?? '0.00'), '0', 2);
            $lineTax    = $this->lineTaxFor($item, $order);
            $taxShares  = $this->splitEvenly($lineTax, $count);

            if ($item->purchase_type === StorefrontPigPricingService::PURCHASE_LIVE) {
                $priceShares = [];
                $rate = $this->pricePerKgFor($item, $lineTotal);

                foreach ($forItem as $allocation) {
                    $liveWeight = $this->liveWeightForAllocation($item, $allocation);
                    $priceShares[] = $liveWeight !== null && $rate !== null
                        ? bcmul($liveWeight, $rate, 2)
                        : bcdiv($lineTotal, (string) max(1, $count), 2);
                }

                $priceShares = $this->withResidual($priceShares, $lineTotal);

                foreach ($forItem as $index => $allocation) {
                    $liveWeight = $this->liveWeightForAllocation($item, $allocation);
                    $this->snapshotAllocationCommercials(
                        $allocation,
                        $item,
                        $priceShares[$index] ?? '0.00',
                        $liveWeight,
                        null,
                    );

                    $shares[$allocation->getKey()] = [
                        $priceShares[$index] ?? '0.00',
                        $taxShares[$index] ?? '0.00',
                    ];
                }

                continue;
            }

            if ($item->purchase_type === StorefrontPigPricingService::PURCHASE_CARCASS) {
                $requestedKg = $this->requestedKgFor($item) ?? bcadd((string) ($item->line_weight_kg ?? '0'), '0', 4);
                $liveWeights = [];

                foreach ($forItem as $allocation) {
                    $liveWeights[] = $this->liveWeightForAllocation($item, $allocation) ?? '0.0000';
                }

                $carcassShares = $this->splitByWeights($requestedKg, $liveWeights, 4);
                $rate = $this->pricePerKgFor($item, $lineTotal, $requestedKg);
                $priceShares = [];

                foreach ($carcassShares as $kg) {
                    $priceShares[] = $rate !== null
                        ? bcmul($kg, $rate, 2)
                        : bcdiv($lineTotal, (string) max(1, $count), 2);
                }

                $priceShares = $this->withResidual($priceShares, $lineTotal);

                foreach ($forItem as $index => $allocation) {
                    $this->snapshotAllocationCommercials(
                        $allocation,
                        $item,
                        $priceShares[$index] ?? '0.00',
                        $liveWeights[$index] ?? null,
                        $carcassShares[$index] ?? null,
                    );

                    $shares[$allocation->getKey()] = [
                        $priceShares[$index] ?? '0.00',
                        $taxShares[$index] ?? '0.00',
                    ];
                }

                continue;
            }

            $priceShares = $this->splitEvenly($lineTotal, $count);

            foreach ($forItem as $index => $allocation) {
                $this->snapshotAllocationCommercials(
                    $allocation,
                    $item,
                    $priceShares[$index] ?? '0.00',
                    null,
                    null,
                );

                $shares[$allocation->getKey()] = [
                    $priceShares[$index] ?? '0.00',
                    $taxShares[$index] ?? '0.00',
                ];
            }
        }

        return $shares;
    }

    protected function liveWeightForAllocation(
        StorefrontOrderItem $item,
        StorefrontOrderItemPigAllocation $allocation,
    ): ?string {
        $snapshot = is_array($item->selected_pig_weight_snapshot)
            ? $item->selected_pig_weight_snapshot
            : [];

        $rowId = $allocation->storefront_listing_pig_id;

        if ($rowId !== null && isset($snapshot[(string) $rowId]['kg'])) {
            return bcadd((string) $snapshot[(string) $rowId]['kg'], '0', 4);
        }

        if ($item->purchase_type === StorefrontPigPricingService::PURCHASE_LIVE
            && $item->line_weight_kg !== null
            && (int) round((float) $item->quantity) === 1) {
            return bcadd((string) $item->line_weight_kg, '0', 4);
        }

        $pig = PigManagement::withTrashed()->with('latestWeight')->find($allocation->pig_id);

        return $pig instanceof PigManagement ? $this->latestWeightKg($pig) : null;
    }

    protected function latestWeightKg(PigManagement $pig): ?string
    {
        return $this->pricing->latestWeightKg($pig);
    }

    protected function pricePerKgFor(StorefrontOrderItem $item, string $lineTotal, ?string $kg = null): ?string
    {
        if ($item->price_per_kg_snapshot !== null) {
            return bcadd((string) $item->price_per_kg_snapshot, '0', 4);
        }

        $weight = $kg ?? ($item->line_weight_kg !== null ? (string) $item->line_weight_kg : null);

        if ($weight === null || bccomp($weight, '0', 4) <= 0) {
            return null;
        }

        return bcdiv($lineTotal, $weight, 4);
    }

    /**
     * @param  list<string>  $weights
     * @return list<string>
     */
    protected function splitByWeights(string $total, array $weights, int $scale = 4): array
    {
        $count = count($weights);

        if ($count === 0) {
            return [];
        }

        $sum = array_reduce(
            $weights,
            fn (string $carry, string $weight) => bcadd($carry, $weight, 4),
            '0.0000',
        );

        if (bccomp($sum, '0', 4) <= 0) {
            return $this->splitEvenly($total, $count, $scale);
        }

        $shares = [];
        $allocated = '0';

        foreach ($weights as $index => $weight) {
            if ($index === $count - 1) {
                $shares[] = bcsub($total, $allocated, $scale);
                break;
            }

            $share = bcdiv(bcmul($total, $weight, $scale + 4), $sum, $scale);
            $shares[] = $share;
            $allocated = bcadd($allocated, $share, $scale);
        }

        return $shares;
    }

    /**
     * @param  list<string>  $shares
     * @return list<string>
     */
    protected function withResidual(array $shares, string $total): array
    {
        if ($shares === []) {
            return [];
        }

        $allocated = '0.00';
        $last = count($shares) - 1;

        foreach ($shares as $index => $share) {
            if ($index === $last) {
                continue;
            }

            $shares[$index] = bcadd((string) $share, '0', 2);
            $allocated = bcadd($allocated, $shares[$index], 2);
        }

        $shares[$last] = bcsub($total, $allocated, 2);

        return $shares;
    }

    protected function snapshotAllocationCommercials(
        StorefrontOrderItemPigAllocation $allocation,
        StorefrontOrderItem $item,
        string $amount,
        ?string $liveWeight,
        ?string $carcassWeight,
    ): void {
        $snapshot = is_array($allocation->allocation_snapshot)
            ? $allocation->allocation_snapshot
            : [];

        $allocation->forceFill([
            'purchase_type'                 => $item->purchase_type ?? StorefrontPigPricingService::PURCHASE_MANUAL,
            'allocated_live_weight_kg'      => $liveWeight,
            'allocated_carcass_weight_kg'   => $carcassWeight,
            'allocated_sale_amount'         => $amount,
            'allocation_snapshot'           => array_merge($snapshot, [
                'purchase_type'      => $item->purchase_type,
                'line_total'         => (string) ($item->line_total ?? '0.00'),
                'price_per_kg'       => $item->price_per_kg_snapshot,
                'live_weight_kg'     => $liveWeight,
                'carcass_weight_kg'  => $carcassWeight,
                'sale_amount'        => $amount,
            ]),
        ])->save();
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
