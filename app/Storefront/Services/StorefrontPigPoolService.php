<?php

namespace App\Storefront\Services;

use App\Models\PigManagement;
use App\Models\PigSale;
use App\Models\Storefront\StorefrontInventoryHold;
use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontListingPig;
use App\Models\Storefront\StorefrontOrderItemPigAllocation;
use App\Models\User;
use App\Storefront\Services\Exceptions\InventoryConflictException;
use App\Support\CurrentOrganization;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Which Standard Stock pigs a listing may draw from, and how many are left.
 *
 * WHAT THIS OWNS
 * --------------
 * Pool membership only. It never creates a sale — `RecordPigSaleService` does
 * that, and `pig_sales` stays the single record of a sold pig. This service's
 * job is to keep the shop's idea of "how many are available" honest against the
 * herd's idea of "which animals still exist and are unsold".
 *
 * THE INVARIANT THAT NEEDS A LOCK
 * -------------------------------
 * A pig may be ACTIVE in at most one pool. MySQL and MariaDB cannot express
 * that as a partial unique index, so it is enforced here: every write that
 * could create a second active membership takes `lockForUpdate()` on the pig's
 * existing pool rows first, re-reads them inside the transaction, and refuses
 * if another listing already holds the animal. The concurrency suite proves it
 * rather than the schema asserting it.
 *
 * AVAILABILITY IS DERIVED, NEVER TYPED IN
 * ---------------------------------------
 * `quantity_available` on a pooled listing is recomputed from the pool, never
 * set by hand. A farm that sells a pig directly from `/sales` has changed what
 * the shop can offer, and the number must follow the animals rather than the
 * other way round.
 */
class StorefrontPigPoolService
{
    public function __construct(
        protected PigListingEligibilityService $eligibility,
        protected CurrentOrganization $currentOrganization,
        protected StorefrontPigPricingService $pricing,
    ) {
    }

    /**
     * Standard Stock pigs this farm could add to a pool.
     *
     * Excludes anything already active in a pool — including, when a listing is
     * supplied, keeping that listing's own members visible so an editor can see
     * what it already holds. `PigListingEligibilityService` decides the rest
     * (dead, sold, under withdrawal, health-blocked), so there is exactly one
     * definition of "sellable animal" in the system.
     *
     * @return Collection<int, PigManagement>
     */
    /**
     * Candidates for a source category, optionally filtered by a search term.
     *
     * WHY CATEGORY RATHER THAN KIND
     * -----------------------------
     * Staff think in terms of the stock they keep — breeding animals, standard
     * stock, piglets — not in terms of a listing "kind". The category chooses
     * both which animals are offered and what kind of listing results, so the
     * person publishing never has to know the mapping.
     *
     * `manual` returns nothing by design: a manual listing is not backed by any
     * animal, and offering a picker for it would invite staff to link stock the
     * listing will not actually sell.
     *
     * The search is deliberately server-side. The browser filters the rendered
     * rows for feedback, but a farm with hundreds of animals must be able to
     * find one without every row being on the page first.
     *
     * @return Collection<int, PigManagement>
     */
    public function candidatesForCategory(
        int $organizationId,
        string $category,
        ?StorefrontListing $listing = null,
        ?string $search = null,
    ): Collection {
        if ($category === StorefrontPigPricingService::CATEGORY_MANUAL) {
            return collect();
        }

        $stockType = $this->stockTypeForCategory($category);

        if ($stockType === null) {
            return collect();
        }

        $pigs = $this->eligiblePigsForListing($organizationId, $listing, $stockType, $search);

        if ($category === StorefrontPigPricingService::CATEGORY_PIGLETS) {
            return $pigs->filter(function (PigManagement $pig): bool {
                return $pig->date_of_birth !== null
                    && \Carbon\Carbon::parse($pig->date_of_birth)->greaterThanOrEqualTo(now()->subDays(120));
            })->values();
        }

        return $pigs;
    }

    /**
     * The listing kind a category produces.
     */
    public function kindForCategory(string $category): string
    {
        return match ($category) {
            StorefrontPigPricingService::CATEGORY_BREEDING => StorefrontListing::KIND_BREEDING_STOCK,
            StorefrontPigPricingService::CATEGORY_PIGLETS  => StorefrontListing::KIND_PIGLET_LOT,
            StorefrontPigPricingService::CATEGORY_STANDARD => StorefrontListing::KIND_LIVE_PIG,
            default => StorefrontListing::KIND_LIVE_PIG,
        };
    }

    public function stockTypeForCategory(string $category): ?string
    {
        return match ($category) {
            StorefrontPigPricingService::CATEGORY_BREEDING => 'breeding',
            StorefrontPigPricingService::CATEGORY_STANDARD => 'standard',
            StorefrontPigPricingService::CATEGORY_PIGLETS => 'standard',
            default => null,
        };
    }

    public function stockTypeForListing(StorefrontListing $listing): ?string
    {
        $category = (string) ($listing->source_category ?? '');

        if ($category !== '') {
            return $this->stockTypeForCategory($category);
        }

        return match ($listing->kind) {
            StorefrontListing::KIND_BREEDING_STOCK => 'breeding',
            StorefrontListing::KIND_LIVE_PIG => 'standard',
            default => null,
        };
    }

    public function eligibleStandardPigsForListing(
        int $organizationId,
        ?StorefrontListing $listing = null,
    ): Collection {
        return $this->eligiblePigsForListing($organizationId, $listing, 'standard');
    }

    /**
     * @return Collection<int, PigManagement>
     */
    public function eligiblePigsForListing(
        int $organizationId,
        ?StorefrontListing $listing = null,
        string $stockType = 'standard',
        ?string $search = null,
    ): Collection {
        $alreadyPooledElsewhere = StorefrontListingPig::query()
            ->whereIn('status', StorefrontListingPig::ACTIVE_STATUSES)
            ->whereHas('listing', fn ($q) => $q->where('status', '!=', StorefrontListing::STATUS_ARCHIVED))
            ->when($listing !== null, fn ($q) => $q->where('storefront_listing_id', '!=', $listing->getKey()))
            ->pluck('pig_id')
            ->all();

        $candidates = PigManagement::query()
            ->where('organization_id', $organizationId)
            ->where('stock_type', $stockType)
            ->whereDoesntHave('sales')
            ->whereDoesntHave('deaths')
            ->when($alreadyPooledElsewhere !== [], fn ($q) => $q->whereNotIn('id', $alreadyPooledElsewhere))
            // Searched in SQL so a farm with hundreds of animals can find one
            // without every row being rendered first.
            ->when($search !== null && trim($search) !== '', function ($q) use ($search) {
                $term = '%' . trim($search) . '%';

                $q->where(function ($inner) use ($term) {
                    $inner->where('ear_tag_number', 'like', $term)
                        ->orWhere('code', 'like', $term)
                        ->orWhere('local_id', 'like', $term)
                        ->orWhere('breed', 'like', $term)
                        ->orWhere('gender', 'like', $term)
                        ->orWhere('current_weight_kg_cache', 'like', $term)
                        ->orWhereHas('latestWeight', fn ($weights) => $weights->where('weight', 'like', $term));
                });
            })
            // The picker shows each animal's latest weight and its estimated
            // value, so load it here rather than querying per row.
            ->with('latestWeight')
            ->orderBy('ear_tag_number')
            ->get();

        // The eligibility service is the authority on whether an individual
        // animal may be offered. Asking it per pig is a few queries each, which
        // is acceptable for a staff-facing picker and is the price of not
        // reimplementing the withdrawal and health rules here.
        return $candidates->filter(function (PigManagement $pig) use ($organizationId, $listing) {
            $verdict = $this->eligibility->check(
                $pig,
                null,
                $organizationId,
                $listing?->getKey() !== null ? (int) $listing->getKey() : null,
            );

            return ($verdict['eligible'] ?? false) === true;
        })->values();
    }

    /**
     * Add pigs to a listing's pool.
     *
     * Refuses the whole set if any animal is ineligible or already active
     * elsewhere: a partial attach would leave staff believing they had listed
     * five head when they had listed three.
     *
     * @param  list<int>  $pigIds
     * @return Collection<int, StorefrontListingPig>
     */
    public function attachPigs(StorefrontListing $listing, array $pigIds, User $actor): Collection
    {
        $pigIds = array_values(array_unique(array_map('intval', $pigIds)));

        if ($pigIds === []) {
            throw new InventoryConflictException('Select at least one pig for this listing.');
        }

        return DB::transaction(function () use ($listing, $pigIds, $actor) {
            $lockedListing = StorefrontListing::query()
                ->whereKey($listing->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $organizationId = (int) ($lockedListing->organization_id
                ?? $this->currentOrganization->requireId());

            $requiredStockType = $this->stockTypeForListing($lockedListing);

            if ($requiredStockType === null) {
                throw new InventoryConflictException('This listing type cannot be backed by selected pigs.');
            }

            // Lock the pigs themselves, in ascending id order. Ascending is the
            // same order every other locking path in this application uses; two
            // callers grabbing overlapping sets in opposite orders is how a
            // deadlock is manufactured.
            $pigs = PigManagement::query()
                ->whereIn('id', $pigIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($pigIds as $pigId) {
                $pig = $pigs->get($pigId);

                if ($pig === null) {
                    throw new InventoryConflictException("Pig #{$pigId} could not be found.");
                }

                if ((int) $pig->organization_id !== $organizationId) {
                    // Deliberately vague: another farm's animal must not be
                    // confirmed to exist by the error message.
                    throw new InventoryConflictException("Pig #{$pigId} could not be found.");
                }

                if ($pig->stock_type !== $requiredStockType) {
                    $label = $requiredStockType === 'breeding' ? 'Breeding Stock' : 'Standard Stock';
                    throw new InventoryConflictException(
                        "{$this->label($pig)} is not {$label} and cannot be sold through this listing."
                    );
                }

                // Ignore THIS listing when asking whether the animal is
                // already on the shop, so a pig removed from this pool can be
                // put back, and re-submitting the picker is not an error.
                $verdict = $this->eligibility->check($pig, null, $organizationId, (int) $lockedListing->getKey());

                if (($verdict['eligible'] ?? false) !== true) {
                    $why = implode(' ', $verdict['blockers'] ?? ['It is not eligible for sale.']);
                    throw new InventoryConflictException("{$this->label($pig)} cannot be listed. {$why}");
                }

                $this->assertNotActiveElsewhere($pig, $lockedListing);
            }

            $rows = collect();

            foreach ($pigIds as $pigId) {
                // A pig previously removed from THIS listing can be re-added;
                // the unique key is (listing, pig), so the row is revived
                // rather than duplicated.
                $row = StorefrontListingPig::withoutGlobalScopes()
                    ->where('storefront_listing_id', $lockedListing->getKey())
                    ->where('pig_id', $pigId)
                    ->lockForUpdate()
                    ->first();

                if ($row === null) {
                    $row = new StorefrontListingPig();
                    $row->storefront_listing_id = $lockedListing->getKey();
                    $row->pig_id                = $pigId;
                }

                $row->organization_id          = $organizationId;
                $row->status                   = StorefrontListingPig::STATUS_AVAILABLE;
                $row->added_by_user_id         = $actor->getKey();
                $row->added_at                 = now();
                $row->removed_at               = null;
                $row->removed_by_user_id       = null;
                $row->removal_reason           = null;
                $row->allocated_order_id       = null;
                $row->allocated_order_item_id  = null;
                $row->save();

                $rows->push($row);
            }

            $this->syncListingAvailability($lockedListing, $actor);

            return $rows;
        });
    }

    /**
     * Make the listing's editable pool match the checked animals on the form.
     *
     * Only sellable rows are removed here. Held/allocated/sold rows are order
     * and sales history, so an edit screen must not quietly detach them because
     * they were not shown as ordinary selectable stock.
     *
     * @param  list<int>  $pigIds
     */
    public function syncPigs(StorefrontListing $listing, array $pigIds, User $actor): StorefrontListing
    {
        $pigIds = array_values(array_unique(array_map('intval', $pigIds)));

        return DB::transaction(function () use ($listing, $pigIds, $actor) {
            $lockedListing = StorefrontListing::query()
                ->whereKey($listing->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($this->stockTypeForListing($lockedListing) === null) {
                throw new InventoryConflictException('This listing type cannot be backed by selected pigs.');
            }

            $currentSellableRows = StorefrontListingPig::query()
                ->where('storefront_listing_id', $lockedListing->getKey())
                ->whereIn('status', StorefrontListingPig::SELLABLE_STATUSES)
                ->orderBy('pig_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('pig_id');

            $currentIds = $currentSellableRows->keys()
                ->map(fn ($id) => (int) $id)
                ->all();

            $removeIds = array_diff($currentIds, $pigIds);

            foreach ($removeIds as $pigId) {
                $row = $currentSellableRows->get($pigId);
                if ($row === null) {
                    continue;
                }

                $row->status             = StorefrontListingPig::STATUS_REMOVED;
                $row->removed_by_user_id = $actor->getKey();
                $row->removed_at         = now();
                $row->removal_reason     = 'Removed while editing the storefront listing.';
                $row->save();
            }

            if ($pigIds !== []) {
                $this->attachPigs($lockedListing, $pigIds, $actor);
            } else {
                $this->syncListingAvailability($lockedListing, $actor);
            }

            return $lockedListing->refresh();
        });
    }

    /**
     * Take a pig out of a listing's pool.
     *
     * Refuses if the animal is allocated to an order: releasing that is the
     * allocation service's job and needs the order to be dealt with, not a
     * quiet detach that would leave an order line pointing at nothing.
     */
    public function removePig(
        StorefrontListing $listing,
        PigManagement $pig,
        User $actor,
        string $reason,
    ): StorefrontListingPig {
        return DB::transaction(function () use ($listing, $pig, $actor, $reason) {
            $lockedListing = StorefrontListing::query()
                ->whereKey($listing->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $row = StorefrontListingPig::query()
                ->where('storefront_listing_id', $lockedListing->getKey())
                ->where('pig_id', $pig->getKey())
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                throw new InventoryConflictException("{$this->label($pig)} is not part of this listing.");
            }

            if ($row->status === StorefrontListingPig::STATUS_ALLOCATED) {
                throw new InventoryConflictException(
                    "{$this->label($pig)} is committed to an order. Cancel or re-select that order's pigs first."
                );
            }

            if ($row->status === StorefrontListingPig::STATUS_SOLD) {
                throw new InventoryConflictException("{$this->label($pig)} has already been sold.");
            }

            $row->status             = StorefrontListingPig::STATUS_REMOVED;
            $row->removed_by_user_id = $actor->getKey();
            $row->removed_at         = now();
            $row->removal_reason     = $reason;
            $row->save();

            $this->syncListingAvailability($lockedListing, $actor);

            return $row;
        });
    }

    /**
     * Release every unsold animal selected for a listing.
     *
     * Archiving/deleting a listing is different from removing one row by hand:
     * the listing itself is leaving the sales surface, so any unsold selected
     * animals must stop reserving stock. Sold rows stay sold as immutable sales
     * history; active order allocations are released with a clear reason so no
     * pending order silently points at a pig that has returned to the pool.
     */
    public function releaseUnsoldPigsForListing(
        StorefrontListing $listing,
        User $actor,
        string $reason,
    ): int {
        return DB::transaction(function () use ($listing, $actor, $reason): int {
            $lockedListing = StorefrontListing::query()
                ->whereKey($listing->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $rows = StorefrontListingPig::query()
                ->where('storefront_listing_id', $lockedListing->getKey())
                ->whereNotIn('status', [
                    StorefrontListingPig::STATUS_SOLD,
                    StorefrontListingPig::STATUS_REMOVED,
                ])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($rows as $row) {
                StorefrontOrderItemPigAllocation::query()
                    ->where('storefront_listing_pig_id', $row->getKey())
                    ->whereIn('status', StorefrontOrderItemPigAllocation::ACTIVE_STATUSES)
                    ->lockForUpdate()
                    ->get()
                    ->each(function (StorefrontOrderItemPigAllocation $allocation) use ($reason): void {
                        $allocation->status = StorefrontOrderItemPigAllocation::STATUS_RELEASED;
                        $allocation->release_reason = $reason;
                        $allocation->save();
                    });

                $row->status                  = StorefrontListingPig::STATUS_REMOVED;
                $row->removed_by_user_id      = $actor->getKey();
                $row->removed_at              = now();
                $row->removal_reason          = $reason;
                $row->allocated_order_id      = null;
                $row->allocated_order_item_id = null;
                $row->allocated_at            = null;
                $row->save();
            }

            $this->syncListingAvailability($lockedListing, $actor);

            return $rows->count();
        });
    }

    /**
     * How many animals this listing can still offer.
     */
    public function availableCount(StorefrontListing $listing): int
    {
        $organizationId = (int) $listing->organization_id;
        $requiredStockType = $this->stockTypeForListing($listing);

        if ($requiredStockType === null) {
            return 0;
        }

        return StorefrontListingPig::query()
            ->where('storefront_listing_id', $listing->getKey())
            ->whereIn('status', StorefrontListingPig::SELLABLE_STATUSES)
            ->with('pig')
            ->get()
            ->filter(function (StorefrontListingPig $row) use ($listing, $organizationId, $requiredStockType): bool {
                $pig = $row->pig;

                if (! $pig instanceof PigManagement) {
                    return false;
                }

                if ($pig->trashed()
                    || (int) $pig->organization_id !== $organizationId
                    || $pig->stock_type !== $requiredStockType
                    || $pig->sales()->exists()
                    || $pig->deaths()->exists()) {
                    return false;
                }

                $verdict = $this->eligibility->check($pig, null, $organizationId, (int) $listing->getKey());

                return ($verdict['eligible'] ?? false) === true;
            })
            ->count();
    }

    /**
     * Recompute the listing's public quantity from the pool.
     *
     * Only ever called for pooled listings — a single-pig or product listing
     * keeps whatever `quantity_available` it was given, because its number does
     * not come from here and overwriting it would zero out every feed listing
     * in the shop.
     *
     * A pooled listing that runs out becomes `sold_out`, and one that gains
     * stock again comes back to `published`. The second half matters: a farm
     * that adds three more pigs to a sold-out listing expects the shop to start
     * selling again without having to remember to re-publish.
     */
    public function syncListingAvailability(StorefrontListing $listing, ?User $actor = null): StorefrontListing
    {
        $hasPool = StorefrontListingPig::query()
            ->where('storefront_listing_id', $listing->getKey())
            ->exists();

        if (! $hasPool) {
            return $listing;
        }

        $available = $this->availableCount($listing);

        $listing->quantity_available = (string) $available;

        if ($available === 0 && in_array($listing->status, [
            StorefrontListing::STATUS_PUBLISHED,
            StorefrontListing::STATUS_RESERVED,
        ], true)) {
            $listing->status = StorefrontListing::STATUS_SOLD_OUT;
        } elseif ($available > 0 && in_array($listing->status, [
            StorefrontListing::STATUS_SOLD_OUT,
            StorefrontListing::STATUS_RESERVED,
        ], true)) {
            // Pooled listings are commercial stock pools, not single animals.
            // `reserved` is a legacy single-pig state and should not hide a
            // pool while unsold, sellable pigs remain. Deliberate staff states
            // such as draft/unpublished/archived are left alone.
            $listing->status = StorefrontListing::STATUS_PUBLISHED;
        }

        $listing->save();

        // Keep the money in step with the animals. Head count, total weight,
        // price range and carcass capacity are all derived from the pool, so
        // every change to it — attach, remove, sale, release — has to refresh
        // them or the shop starts quoting figures the herd cannot back.
        $this->pricing->refreshListingSnapshot($listing);

        return $listing;
    }

    /**
     * Guard a non-storefront sale before `RecordPigSaleService` writes it.
     *
     * This is the server-side counterpart to the sale-form warning. The shop
     * does not veto ordinary farm-gate sales, but it must not let a direct sale
     * consume stock already promised by checkout holds or exact allocations.
     */
    public function assertPigMayBeSoldDirectly(PigManagement $pig): void
    {
        $allocated = StorefrontOrderItemPigAllocation::query()
            ->where('pig_id', $pig->getKey())
            ->whereIn('status', StorefrontOrderItemPigAllocation::ACTIVE_STATUSES)
            ->lockForUpdate()
            ->with('order')
            ->first();

        if ($allocated !== null) {
            throw new InventoryConflictException(sprintf(
                'This pig is committed to storefront order %s. Release or cancel that order before selling it directly.',
                $allocated->order?->reference ?? ('#' . $allocated->storefront_order_id),
            ));
        }

        $rows = StorefrontListingPig::query()
            ->where('pig_id', $pig->getKey())
            ->whereIn('status', StorefrontListingPig::ACTIVE_STATUSES)
            ->whereHas('listing', fn ($q) => $q->where('status', '!=', StorefrontListing::STATUS_ARCHIVED))
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($rows as $row) {
            if (! in_array($row->status, StorefrontListingPig::SELLABLE_STATUSES, true)) {
                throw new InventoryConflictException("{$this->label($pig)} is committed on the storefront and cannot be sold directly.");
            }

            $listing = StorefrontListing::query()
                ->whereKey($row->storefront_listing_id)
                ->lockForUpdate()
                ->first();

            if ($listing === null) {
                continue;
            }

            $availableAfterThisSale = max(0, $this->availableCount($listing) - 1);
            $liveHolds = StorefrontInventoryHold::query()
                ->where('listing_id', $listing->getKey())
                ->live()
                ->lockForUpdate()
                ->pluck('quantity')
                ->reduce(fn (string $carry, $quantity) => bcadd($carry, (string) $quantity, 4), '0');

            if (bccomp((string) $availableAfterThisSale, $liveHolds, 4) < 0) {
                throw new InventoryConflictException(sprintf(
                    '%s cannot be sold directly because storefront customers already hold %s head from "%s".',
                    $this->label($pig),
                    rtrim(rtrim($liveHolds, '0'), '.') ?: '0',
                    $listing->title,
                ));
            }

            $this->assertCarcassPromisesSurvive($pig, $listing);
        }
    }

    /**
     * KILOGRAMS PROMISED, NOT HEAD.
     *
     * A carcass hold names no animals — that is the point of it. A buyer asked
     * for 150 kg and which pigs cover it is a decision nobody has made yet.
     * That makes it invisible to every check above, which counts animals.
     *
     * So selling a pig at the gate can silently break a promise: two 100 kg
     * animals back a 150 kg order, one walks out of the yard, and the listing
     * can now deliver 100 of the 150 kilograms somebody has already paid for.
     * Nothing would have complained until the order came to be fulfilled.
     *
     * The check is the same shape as the head one, in the other unit: what this
     * listing could still deliver after the sale, against what it has already
     * promised in kilograms.
     */
    protected function assertCarcassPromisesSurvive(PigManagement $pig, StorefrontListing $listing): void
    {
        $promisedKg = StorefrontInventoryHold::query()
            ->where('listing_id', $listing->getKey())
            ->where('hold_type', StorefrontInventoryHold::TYPE_CARCASS_KG)
            ->live()
            ->lockForUpdate()
            ->pluck('requested_kg')
            ->reduce(fn (string $carry, $kg) => bcadd($carry, (string) ($kg ?? '0'), 4), '0.0000');

        if (bccomp($promisedKg, '0', 4) <= 0) {
            return;
        }

        $capacity = $listing->carcass_capacity_kg === null
            ? '0.0000'
            : bcadd((string) $listing->carcass_capacity_kg, '0', 4);

        // What this animal was contributing to that capacity.
        $thisAnimal = app(StorefrontPigPricingService::class)->latestWeightKg($pig) ?? '0';

        $remaining = bcsub($capacity, $thisAnimal, 4);

        if (bccomp($remaining, $promisedKg, 4) < 0) {
            throw new InventoryConflictException(sprintf(
                '%s cannot be sold directly because storefront customers have already paid for %s kg from "%s", ' .
                'and only %s kg would be left. Cancel or reduce that order first.',
                $this->label($pig),
                rtrim(rtrim($promisedKg, '0'), '.'),
                $listing->title,
                rtrim(rtrim(bccomp($remaining, '0', 4) < 0 ? '0' : $remaining, '0'), '.') ?: '0',
            ));
        }
    }

    /**
     * A pig was sold outside the shop — reconcile the pool.
     *
     * Called from the pig-sale path, so a farm that sells an animal at the gate
     * does not have to remember the shop is also offering it. Without this the
     * listing would keep advertising an animal that no longer exists, and the
     * next buyer would reach approval to find nothing to allocate.
     *
     * Deliberately tolerant: a pig with no pool row is the ordinary case and
     * must not raise. This runs inside the sale transaction, and a farm-gate
     * sale must never fail because of shop bookkeeping.
     */
    public function markPigSoldDirectly(PigManagement $pig, PigSale $sale, ?User $actor = null): void
    {
        // If an approver committed this animal to an order, the sale is NOT a
        // direct one — it is that order being approved, and
        // `StorefrontPigAllocationService` is mid-flight and will set the pool
        // row itself with the right order and reason. Standing aside here stops
        // a storefront sale being labelled "sold at the gate" and stops the two
        // services fighting over the same row.
        $allocated = \App\Models\Storefront\StorefrontOrderItemPigAllocation::query()
            ->where('pig_id', $pig->getKey())
            ->whereIn('status', \App\Models\Storefront\StorefrontOrderItemPigAllocation::ACTIVE_STATUSES)
            ->exists();

        if ($allocated) {
            return;
        }

        $rows = StorefrontListingPig::query()
            ->where('pig_id', $pig->getKey())
            ->whereIn('status', StorefrontListingPig::ACTIVE_STATUSES)
            ->lockForUpdate()
            ->get();

        foreach ($rows as $row) {
            $row->status             = StorefrontListingPig::STATUS_SOLD;
            $row->pig_sale_id        = $sale->getKey();
            $row->sold_at            = now();
            $row->removal_reason     = 'Sold directly from /sales, outside the shop.';
            $row->removed_by_user_id = $actor?->getKey();
            $row->save();

            $listing = StorefrontListing::query()
                ->whereKey($row->storefront_listing_id)
                ->lockForUpdate()
                ->first();

            if ($listing !== null) {
                $this->syncListingAvailability($listing, $actor);
            }
        }
    }

    /* ------------------------------------------------------------------ */

    /**
     * Refuse a pig that another listing is already speaking for.
     *
     * This is the read half of the invariant the migration could not express.
     * It must run inside the caller's transaction, after the pig row is locked,
     * or two concurrent attaches could both pass it.
     */
    protected function assertNotActiveElsewhere(PigManagement $pig, StorefrontListing $listing): void
    {
        $conflict = StorefrontListingPig::query()
            ->where('pig_id', $pig->getKey())
            ->where('storefront_listing_id', '!=', $listing->getKey())
            ->whereIn('status', StorefrontListingPig::ACTIVE_STATUSES)
            ->whereHas('listing', fn ($q) => $q->where('status', '!=', StorefrontListing::STATUS_ARCHIVED))
            ->lockForUpdate()
            ->first();

        if ($conflict !== null) {
            throw new InventoryConflictException(
                "{$this->label($pig)} is already on the shop under another listing."
            );
        }
    }

    protected function label(PigManagement $pig): string
    {
        $tag = $pig->ear_tag_number ?: ('#' . $pig->getKey());

        return "Pig {$tag}";
    }
}
