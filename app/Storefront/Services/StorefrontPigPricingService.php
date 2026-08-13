<?php

namespace App\Storefront\Services;

use App\Models\PigManagement;
use App\Models\Setting;
use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontListingPig;
use Illuminate\Support\Collection;

/**
 * What a pig is worth, from the farm's own pricing configuration.
 *
 * ONE PLACE READS THE SETUP PRICES
 * --------------------------------
 * `sales.pricing.live_weight_price` and `sales.pricing.carcass_weight_price`
 * are configured at /settings/setup and are what `RecordPigSaleService` already
 * uses to price a farm-gate sale. The shop must charge from the same numbers or
 * a farm would quote one price at the gate and another online.
 *
 * WEIGHT COMES FROM THE WEIGHT RECORDS, NOT THE CACHE
 * --------------------------------------------------
 * `pigs_management.current_weight_kg_cache` looks like the obvious source and is
 * the wrong one. It is written at intake by `PurchaseStockIntakeService` and by
 * `PigStockService`, and **nothing updates it when a weight is recorded** — so
 * on any animal weighed since arrival it is stale, sometimes by months.
 *
 * The push page was reading it, which meant listings were priced off an intake
 * weight rather than the pig's real one. This service reads `latestWeight()` —
 * the `pig_weights` row with the most recent `weigh_date` — and falls back to
 * the cache only when an animal has never been weighed.
 *
 * MONEY IS DECIMAL STRINGS
 * ------------------------
 * bcmath throughout. Weight × rate is money, and the standing rule is that
 * money never touches binary floating point.
 */
class StorefrontPigPricingService
{
    public const SOURCE_SETUP    = 'setup';
    public const SOURCE_OVERRIDE = 'override';

    public const CATEGORY_BREEDING = 'breeding_stock';
    public const CATEGORY_STANDARD = 'standard_stock';
    public const CATEGORY_PIGLETS  = 'piglets';
    public const CATEGORY_MANUAL   = 'manual';

    public const PURCHASE_LIVE    = 'live';
    public const PURCHASE_CARCASS = 'carcass';
    public const PURCHASE_MANUAL  = 'manual';

    /**
     * The farm's live rate per kilogram, as a decimal string.
     *
     * `Setting::get()` is organisation-scoped, so two farms get their own
     * prices and neither inherits the other's.
     */
    public function livePricePerKg(): string
    {
        return $this->decimal(Setting::get('sales.pricing.live_weight_price', 0));
    }

    public function carcassPricePerKg(): string
    {
        return $this->decimal(Setting::get('sales.pricing.carcass_weight_price', 0));
    }

    /**
     * The weight to price this animal at.
     *
     * The latest recorded weight; the intake cache only if it has never been
     * weighed; null if neither exists, so a caller can say "not weighed" rather
     * than quietly pricing at zero.
     */
    public function latestWeightKg(PigManagement $pig): ?string
    {
        $latest = $pig->relationLoaded('latestWeight')
            ? $pig->latestWeight
            : $pig->latestWeight()->first();

        if ($latest !== null && $latest->weight !== null) {
            return $this->decimal($latest->weight, 4);
        }

        if ($pig->current_weight_kg_cache !== null) {
            return $this->decimal($pig->current_weight_kg_cache, 4);
        }

        return null;
    }

    /**
     * What one animal is worth live.
     */
    public function liveValueOf(PigManagement $pig, ?string $ratePerKg = null): ?string
    {
        $weight = $this->latestWeightKg($pig);

        if ($weight === null) {
            return null;
        }

        return bcmul($weight, $ratePerKg ?? $this->livePricePerKg(), 2);
    }

    /**
     * Summarise a set of animals for the picker and for the listing snapshot.
     *
     * Everything the staff-facing selector shows — count, total weight, min,
     * max, average, estimated value and the per-animal price range — comes from
     * here, computed on the SERVER. The browser may mirror it for feedback, but
     * the numbers that get stored are these.
     *
     * @param  Collection<int, PigManagement>|iterable<PigManagement>  $pigs
     * @return array<string, mixed>
     */
    public function summarise(iterable $pigs, ?string $liveRate = null): array
    {
        $liveRate = $liveRate ?? $this->livePricePerKg();

        $count     = 0;
        $weighed   = 0;
        $total     = '0.0000';
        $min       = null;
        $max       = null;
        $unweighed = [];

        foreach ($pigs as $pig) {
            $count++;

            $weight = $this->latestWeightKg($pig);

            if ($weight === null) {
                // Named rather than counted as zero: an animal with no weight
                // cannot be priced, and staff need to know which one.
                $unweighed[] = $pig->ear_tag_number ?: ('#' . $pig->getKey());
                continue;
            }

            $weighed++;
            $total = bcadd($total, $weight, 4);

            if ($min === null || bccomp($weight, $min, 4) < 0) {
                $min = $weight;
            }

            if ($max === null || bccomp($weight, $max, 4) > 0) {
                $max = $weight;
            }
        }

        $average = $weighed > 0 ? bcdiv($total, (string) $weighed, 4) : null;

        return [
            'count'                => $count,
            'weighed_count'        => $weighed,
            'unweighed'            => $unweighed,
            'total_weight_kg'      => $total,
            'min_weight_kg'        => $min,
            'max_weight_kg'        => $max,
            'average_weight_kg'    => $average,
            'live_price_per_kg'    => $liveRate,
            'estimated_total'      => bcmul($total, $liveRate, 2),
            'min_estimated_price'  => $min !== null ? bcmul($min, $liveRate, 2) : null,
            'max_estimated_price'  => $max !== null ? bcmul($max, $liveRate, 2) : null,
        ];
    }

    /**
     * Carcass capacity, WITHOUT writing anything.
     *
     * `refreshListingSnapshot()` recomputes this and saves it, which is right
     * when the pool changes and wrong when somebody is merely asking what is
     * available. A read that writes takes an exclusive lock on the listing row,
     * and doing that inside a cart transaction — which also inserts a row
     * referencing that listing, taking a shared lock on it — deadlocks two
     * buyers against each other. A real one, found by the concurrency suite.
     *
     * So this computes the same number and saves nothing. The stored snapshot
     * stays the thing the shop DISPLAYS; this is the thing it decides on.
     */
    public function carcassCapacityKg(StorefrontListing $listing): string
    {
        $pigs = StorefrontListingPig::query()
            ->where('storefront_listing_id', $listing->getKey())
            ->whereIn('status', StorefrontListingPig::SELLABLE_STATUSES)
            ->with('pig.latestWeight')
            ->get()
            ->map(fn ($row) => $row->pig)
            ->filter()
            ->values();

        return $this->summarise($pigs)['total_weight_kg'];
    }

    /**
     * Write the current picture onto the listing.
     *
     * Called whenever the pool changes — attach, remove, sale, release — so the
     * shop's headline figures never drift from the animals behind them.
     *
     * Snapshots the RATES too. A farm that changes its live price next month
     * must not silently reprice a listing a customer is already looking at;
     * re-publishing is how a price changes, which is a decision rather than a
     * side effect.
     */
    public function refreshListingSnapshot(StorefrontListing $listing): StorefrontListing
    {
        $pigs = StorefrontListingPig::query()
            ->where('storefront_listing_id', $listing->getKey())
            ->whereIn('status', StorefrontListingPig::SELLABLE_STATUSES)
            ->with('pig.latestWeight')
            ->get()
            ->map(fn ($row) => $row->pig)
            ->filter()
            ->values();

        if ($pigs->isEmpty() && $listing->source_category === self::CATEGORY_MANUAL) {
            return $listing;
        }

        $liveRate    = $listing->live_price_per_kg_snapshot !== null && $listing->pricing_override
            ? $this->decimal($listing->live_price_per_kg_snapshot)
            : $this->livePricePerKg();

        $carcassRate = $listing->carcass_price_per_kg_snapshot !== null && $listing->pricing_override
            ? $this->decimal($listing->carcass_price_per_kg_snapshot)
            : $this->carcassPricePerKg();

        $summary = $this->summarise($pigs, $liveRate);

        $listing->forceFill([
            'live_price_per_kg_snapshot'    => $liveRate,
            'carcass_price_per_kg_snapshot' => $carcassRate,
            'total_live_weight_kg_snapshot' => $summary['total_weight_kg'],
            'min_pig_weight_kg_snapshot'    => $summary['min_weight_kg'],
            'max_pig_weight_kg_snapshot'    => $summary['max_weight_kg'],
            'min_estimated_pig_price'       => $summary['min_estimated_price'],
            'max_estimated_pig_price'       => $summary['max_estimated_price'],
            'estimated_total_amount'        => $summary['estimated_total'],
            // Carcass capacity is the live weight of what is still on offer.
            // Dressing-out yield is a farm-specific factor and is NOT applied
            // here — inventing one would misstate what the farm can deliver.
            'carcass_capacity_kg'           => $summary['total_weight_kg'],
            'pricing_source'                => $listing->pricing_override ? self::SOURCE_OVERRIDE : self::SOURCE_SETUP,
        ])->save();

        return $listing;
    }

    /* ------------------------------------------------------------------ */

    protected function decimal(mixed $value, int $scale = 4): string
    {
        if ($value === null || $value === '') {
            return $scale === 2 ? '0.00' : '0.0000';
        }

        return bcadd((string) $value, '0', $scale);
    }
}
