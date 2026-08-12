<?php

namespace App\Storefront\Services;

use App\Models\PigManagement;
use App\Models\PigSale;
use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontListingPig;
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
    public function eligibleStandardPigsForListing(
        int $organizationId,
        ?StorefrontListing $listing = null,
    ): Collection {
        $alreadyPooledElsewhere = StorefrontListingPig::query()
            ->whereIn('status', StorefrontListingPig::ACTIVE_STATUSES)
            ->when($listing !== null, fn ($q) => $q->where('storefront_listing_id', '!=', $listing->getKey()))
            ->pluck('pig_id')
            ->all();

        $candidates = PigManagement::query()
            ->where('organization_id', $organizationId)
            ->where('stock_type', 'standard')
            ->whereDoesntHave('sales')
            ->whereDoesntHave('deaths')
            ->when($alreadyPooledElsewhere !== [], fn ($q) => $q->whereNotIn('id', $alreadyPooledElsewhere))
            ->orderBy('ear_tag_number')
            ->get();

        // The eligibility service is the authority on whether an individual
        // animal may be offered. Asking it per pig is a few queries each, which
        // is acceptable for a staff-facing picker and is the price of not
        // reimplementing the withdrawal and health rules here.
        return $candidates->filter(function (PigManagement $pig) use ($organizationId) {
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

                if ($pig->stock_type !== 'standard') {
                    throw new InventoryConflictException(
                        "{$this->label($pig)} is not Standard Stock and cannot be sold through the shop."
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
     * How many animals this listing can still offer.
     */
    public function availableCount(StorefrontListing $listing): int
    {
        return StorefrontListingPig::query()
            ->where('storefront_listing_id', $listing->getKey())
            ->whereIn('status', StorefrontListingPig::SELLABLE_STATUSES)
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

        if ($available === 0 && $listing->status === StorefrontListing::STATUS_PUBLISHED) {
            $listing->status = StorefrontListing::STATUS_SOLD_OUT;
        } elseif ($available > 0 && $listing->status === StorefrontListing::STATUS_SOLD_OUT) {
            $listing->status = StorefrontListing::STATUS_PUBLISHED;
        }

        $listing->save();

        return $listing;
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
