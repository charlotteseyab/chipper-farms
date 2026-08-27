<?php

namespace App\Storefront\Services;

use App\Models\Storefront\StorefrontCart;
use App\Models\Storefront\StorefrontCartItem;
use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontListingPig;
use App\Storefront\Services\Exceptions\CartException;
use App\Storefront\Support\CartOwner;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Buying a pig, the two ways a farm actually sells one.
 *
 * LIVE — the buyer picks animals
 * -----------------------------
 * They choose specific pigs from the listing's pool and pay for the weight
 * those animals actually carry, at the farm's live rate. Two pigs in the same
 * listing are not worth the same amount, so each becomes its OWN cart line:
 * one animal, its own weight, its own price. Representing four animals as
 * "quantity 4 × one unit price" would have to either round the money or claim a
 * weight none of them has, and every total in the shop is `unit × quantity`.
 *
 * CARCASS — the buyer asks for kilograms
 * --------------------------------------
 * They ask for an amount of meat and the FARM decides which animals to
 * slaughter to meet it. There is nothing to pick, so it is one line carrying
 * the kilograms requested, priced at the carcass rate. Which pigs cover it is a
 * fulfilment decision a manager makes at approval.
 *
 * KILOGRAMS ARE NOT HEAD
 * ----------------------
 * A carcass line's `quantity` is 1 — one request — and its weight lives in
 * `requested_kg`, checked against the listing's carcass capacity in kilograms.
 * Letting a 400 kg request consume "400" of a quantity column that counts
 * animals is how a shop sells four hundred pigs it does not have.
 *
 * EVERY NUMBER IS SNAPSHOTTED
 * ---------------------------
 * The rate, the weight and the arithmetic are copied onto the line as they were
 * at the time. A farm that changes its live price next week must not silently
 * reprice a basket filled today, and an invoice has to be able to explain
 * itself years later without a settings lookup that has since moved on.
 */
class StorefrontPurchaseLineService
{
    public function __construct(
        protected StorefrontPigPricingService $pricing,
        protected ListingAvailabilityService $availability,
        protected HoldService $holds,
    ) {
    }

    /**
     * Add a live purchase: one line per animal the buyer chose.
     *
     * @param  array<int, mixed>  $listingPigIds  rows in this listing's pool
     * @return Collection<int, StorefrontCartItem>
     */
    public function addLiveLines(CartOwner $owner, StorefrontListing $listing, array $listingPigIds): Collection
    {
        $this->assertPigListing($listing);

        $ids = array_values(array_unique(array_map('intval', array_filter($listingPigIds))));

        if ($ids === []) {
            throw new CartException('Choose at least one animal to buy.');
        }

        $rate = $this->liveRate($listing);

        return DB::transaction(function () use ($owner, $listing, $ids, $rate) {
            // LISTING FIRST, THEN THE ANIMALS. THE ORDER IS THE POINT.
            //
            // Inserting a cart row takes a shared lock on the listing it
            // references, and checkout locks the listing before it locks pool
            // rows. Taking the pool rows first here therefore inverts the order
            // against a checkout running alongside: that transaction holds the
            // listing and wants an animal, this one holds the animal and wants
            // the listing. MariaDB breaks the cycle by killing one of them, and
            // a buyer sees a deadlock instead of a basket.
            //
            // Found by the concurrency suite, which is the only place it can be
            // found — one process at a time never inverts anything.
            $this->lockListing($listing);

            // Locked, because between reading "available" and writing the cart
            // line another buyer can take the same animal. The cart is not the
            // authoritative reservation — the hold at checkout is — but handing
            // two people a basket containing the same pig is a promise the farm
            // cannot keep, and it costs one lock to avoid.
            $rows = StorefrontListingPig::query()
                ->where('storefront_listing_id', $listing->getKey())
                ->whereKey($ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->with('pig.latestWeight')
                ->get();

            $this->assertEveryPigWasFound($rows, $ids);

            $cart  = $this->cartFor($owner, $listing);
            $lines = collect();

            foreach ($rows as $row) {
                $lines->push($this->liveLineFor($cart, $listing, $row, $rate));
            }

            return $lines;
        });
    }

    /**
     * Add a carcass purchase for animals explicitly chosen by the buyer.
     * Each animal is its own line and is priced from its recorded weight at
     * the listing's snapshotted carcass rate.
     *
     * @param  array<int, mixed>  $listingPigIds
     * @return Collection<int, StorefrontCartItem>
     */
    public function addSelectedCarcassLines(CartOwner $owner, StorefrontListing $listing, array $listingPigIds): Collection
    {
        $this->assertPigListing($listing);

        $ids = array_values(array_unique(array_map('intval', array_filter($listingPigIds))));

        if ($ids === []) {
            throw new CartException('Choose at least one animal to buy.');
        }

        $rate = $this->carcassRate($listing);

        return DB::transaction(function () use ($owner, $listing, $ids, $rate) {
            $this->lockListing($listing);

            $rows = StorefrontListingPig::query()
                ->where('storefront_listing_id', $listing->getKey())
                ->whereKey($ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->with('pig.latestWeight')
                ->get();

            $this->assertEveryPigWasFound($rows, $ids);

            $cart = $this->cartFor($owner, $listing);

            return $rows->map(fn (StorefrontListingPig $row) => $this->carcassLineFor($cart, $listing, $row, $rate));
        });
    }

    /**
     * Add a carcass purchase: one line for the kilograms requested.
     */
    public function addCarcassLine(CartOwner $owner, StorefrontListing $listing, string $requestedKg): StorefrontCartItem
    {
        $this->assertPigListing($listing);

        if (! is_numeric($requestedKg) || bccomp($requestedKg, '0', 4) <= 0) {
            throw new CartException('Enter how many kilograms you would like.');
        }

        $requestedKg = bcadd($requestedKg, '0', 4);
        $rate        = $this->carcassRate($listing);

        return DB::transaction(function () use ($owner, $listing, $requestedKg, $rate) {
            // Same lock order as the live path, for the same reason: this
            // transaction is about to insert a row referencing the listing.
            $this->lockListing($listing);

            $remaining = $this->carcassKgAvailable($listing);

            if (bccomp($requestedKg, $remaining, 4) > 0) {
                throw new CartException(
                    'Only ' . rtrim(rtrim($remaining, '0'), '.') . ' kg is available from this listing.',
                );
            }

            $cart  = $this->cartFor($owner, $listing);
            $total = bcmul($requestedKg, $rate, 2);

            return StorefrontCartItem::create([
                'organization_id'       => $listing->organization_id,
                'cart_id'               => $cart->id,
                'listing_id'            => $listing->id,
                'line_key'              => '0',
                'purchase_type'         => StorefrontPigPricingService::PURCHASE_CARCASS,
                'source_category'       => $listing->source_category,
                // One request, not one animal per kilogram.
                'quantity'              => '1',
                'requested_kg'          => $requestedKg,
                'price_per_kg_snapshot' => $rate,
                'line_weight_kg'        => $requestedKg,
                'price_snapshot'        => $total,
                'price_basis'           => StorefrontListing::BASIS_CARCASS,
                'calculation_snapshot'  => [
                    'basis'         => 'carcass',
                    'requested_kg'  => $requestedKg,
                    'rate_per_kg'   => $rate,
                    'line_total'    => $total,
                    'rate_source'   => $listing->pricing_source ?? StorefrontPigPricingService::SOURCE_SETUP,
                    'explanation'   => $requestedKg . ' kg × ' . $rate . ' = ' . $total,
                ],
            ]);
        });
    }

    /**
     * Kilograms this listing can still promise.
     *
     * Capacity less what other baskets and checkouts are already holding. A
     * listing whose animals total 400 kg cannot sell 400 kg twice.
     */
    public function carcassKgAvailable(StorefrontListing $listing): string
    {
        // Computed, not re-snapshotted. Writing the listing row here would put
        // an exclusive lock inside a transaction that is also inserting a cart
        // row referencing that listing — which deadlocks two buyers against
        // each other, as the concurrency suite demonstrated.
        $capacity = $this->pricing->carcassCapacityKg($listing);

        $spokenFor = $this->holds->carcassKgHeld($listing);

        $remaining = bcsub($capacity, $spokenFor, 4);

        return bccomp($remaining, '0', 4) < 0 ? '0.0000' : $remaining;
    }

    /* ------------------------------------------------------------------ */

    /**
     * Take the listing lock this transaction is going to need anyway.
     *
     * Every path that touches a pool takes the listing first and its animals
     * second. Doing it explicitly at the top — rather than implicitly, later,
     * through a foreign key — is what keeps that order true.
     */
    protected function lockListing(StorefrontListing $listing): void
    {
        StorefrontListing::query()
            ->whereKey($listing->getKey())
            ->lockForUpdate()
            ->first();
    }

    protected function liveLineFor(
        StorefrontCart $cart,
        StorefrontListing $listing,
        StorefrontListingPig $row,
        string $rate,
    ): StorefrontCartItem {
        $pig = $row->pig;

        if ($pig === null) {
            throw new CartException('One of the animals chosen is no longer on the farm.');
        }

        $weight = $this->pricing->latestWeightKg($pig);

        if ($weight === null) {
            // An unweighed animal cannot be priced. Guessing one, or treating it
            // as zero, sells the farm's stock for nothing.
            throw new CartException(
                'Animal ' . ($pig->ear_tag_number ?: ('#' . $pig->getKey())) .
                ' has no recorded weight, so it cannot be priced. Please request a quote.',
            );
        }

        $total = bcmul($weight, $rate, 2);

        // The same animal twice in one basket is one animal. Checked here for
        // a clean result, and enforced by a unique index on
        // (cart_id, listing_id, line_key) so a double-submit cannot slip past.
        $existing = $cart->items()
            ->where('listing_id', $listing->id)
            ->where('line_key', (string) $row->getKey())
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return StorefrontCartItem::create([
            'organization_id'              => $listing->organization_id,
            'cart_id'                      => $cart->id,
            'listing_id'                   => $listing->id,
            // The animal IS the line's identity.
            'line_key'                     => (string) $row->getKey(),
            'purchase_type'                => StorefrontPigPricingService::PURCHASE_LIVE,
            'source_category'              => $listing->source_category,
            'quantity'                     => '1',
            'price_per_kg_snapshot'        => $rate,
            'line_weight_kg'               => $weight,
            'price_snapshot'               => $total,
            'price_basis'                  => StorefrontListing::BASIS_LIVE,
            'selected_listing_pig_ids'     => [$row->getKey()],
            'selected_pig_weight_snapshot' => [
                (string) $row->getKey() => [
                    'pig_id'   => $pig->getKey(),
                    'ear_tag'  => $pig->ear_tag_number,
                    'kg'       => $weight,
                ],
            ],
            'calculation_snapshot'         => [
                'basis'       => 'live',
                'weight_kg'   => $weight,
                'rate_per_kg' => $rate,
                'line_total'  => $total,
                'rate_source' => $listing->pricing_source ?? StorefrontPigPricingService::SOURCE_SETUP,
                'explanation' => $weight . ' kg × ' . $rate . ' = ' . $total,
            ],
        ]);
    }

    protected function carcassLineFor(
        StorefrontCart $cart,
        StorefrontListing $listing,
        StorefrontListingPig $row,
        string $rate,
    ): StorefrontCartItem {
        $pig = $row->pig;

        if ($pig === null) {
            throw new CartException('One of the animals chosen is no longer on the farm.');
        }

        $weight = $this->pricing->latestWeightKg($pig);

        if ($weight === null) {
            throw new CartException('Animal ' . ($pig->ear_tag_number ?: ('#' . $pig->getKey())) . ' has no recorded weight, so it cannot be priced. Please request a quote.');
        }

        $existing = $cart->items()->where('listing_id', $listing->id)->where('line_key', (string) $row->getKey())->first();
        if ($existing !== null) {
            return $existing;
        }

        $total = bcmul($weight, $rate, 2);

        return StorefrontCartItem::create([
            'organization_id'              => $listing->organization_id,
            'cart_id'                      => $cart->id,
            'listing_id'                   => $listing->id,
            'line_key'                     => (string) $row->getKey(),
            'purchase_type'                => StorefrontPigPricingService::PURCHASE_CARCASS,
            'source_category'              => $listing->source_category,
            'quantity'                     => '1',
            'requested_kg'                 => $weight,
            'price_per_kg_snapshot'        => $rate,
            'line_weight_kg'               => $weight,
            'price_snapshot'               => $total,
            'price_basis'                  => StorefrontListing::BASIS_CARCASS,
            'selected_listing_pig_ids'     => [$row->getKey()],
            'selected_pig_weight_snapshot' => [
                (string) $row->getKey() => ['pig_id' => $pig->getKey(), 'ear_tag' => $pig->ear_tag_number, 'kg' => $weight],
            ],
            'calculation_snapshot' => [
                'basis' => 'carcass', 'weight_kg' => $weight, 'rate_per_kg' => $rate,
                'line_total' => $total,
                'rate_source' => $listing->pricing_source ?? StorefrontPigPricingService::SOURCE_SETUP,
                'explanation' => $weight . ' kg × ' . $rate . ' = ' . $total,
            ],
        ]);
    }

    /**
     * @param  EloquentCollection<int, StorefrontListingPig>  $rows
     * @param  array<int, int>  $ids
     */
    protected function assertEveryPigWasFound(EloquentCollection $rows, array $ids): void
    {
        if ($rows->count() !== count($ids)) {
            // Either an animal is not in this listing at all, or it was removed
            // between the page rendering and the click. Both mean the buyer is
            // looking at something that no longer exists, and neither may be
            // silently dropped from the basket.
            throw new CartException('One or more of the animals chosen is no longer available.');
        }

        $unavailable = $rows->reject(fn (StorefrontListingPig $row) => $row->status === StorefrontListingPig::STATUS_AVAILABLE);

        if ($unavailable->isNotEmpty()) {
            throw new CartException('One or more of the animals chosen has already been taken.');
        }
    }

    protected function cartFor(CartOwner $owner, StorefrontListing $listing): StorefrontCart
    {
        $cart = app(CartService::class)->activeFor($owner);

        if (! $this->availability->isSourceAvailable($listing) || ! $listing->isOrderable()) {
            throw new CartException('This listing is not available for purchase.');
        }

        return $cart;
    }

    protected function assertPigListing(StorefrontListing $listing): void
    {
        if (! $listing->usesPigPool()) {
            throw new CartException('This listing is not sold by live or carcass weight.');
        }
    }

    /**
     * The rate on the LISTING, not the one in settings today.
     */
    protected function liveRate(StorefrontListing $listing): string
    {
        $rate = $this->pricing->effectiveLiveRate($listing);

        if ($rate === null || bccomp((string) $rate, '0', 4) <= 0) {
            throw new CartException('This listing has no live price and cannot be bought online yet.');
        }

        return bcadd((string) $rate, '0', 4);
    }

    protected function carcassRate(StorefrontListing $listing): string
    {
        $rate = $this->pricing->effectiveCarcassRate($listing);

        if ($rate === null || bccomp((string) $rate, '0', 4) <= 0) {
            throw new CartException('This listing has no carcass price and cannot be bought by weight yet.');
        }

        return bcadd((string) $rate, '0', 4);
    }
}
