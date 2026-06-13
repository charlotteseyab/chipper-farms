<?php

namespace App\Storefront\Services;

use App\Models\Storefront\StorefrontListing;
use Illuminate\Database\Eloquent\Model;

/**
 * Reconciles a listing's availability against the operational source record —
 * WITHOUT ever mutating operational truth.
 *
 * The storefront must never write back into pigs_management or feed inventory.
 * Instead it READS the source state and decides whether the listing may remain
 * sellable. A pig that has been sold operationally is soft-deleted; a listing
 * whose pig source is trashed (or missing) is no longer fulfillable and should
 * not be publicly orderable.
 *
 * Phase 1 scope: the read-side check + a publish guard. Full order-driven
 * reservation/locking arrives in Phase 3 (orders) and Phase 4 (finance), where
 * a confirmed order flips the listing to reserved/sold_out atomically.
 */
class ListingAvailabilityService
{
    /**
     * Is the listing's underlying source still fulfillable?
     *
     * Rules:
     *  - Service lines and listings with no source link are always source-OK
     *    (they are not backed by a perishable operational record).
     *  - For a pig-backed kind the source pig must exist and not be trashed.
     *  - For other sourced kinds (feed/manure) the source must simply exist;
     *    quantity availability is tracked on the listing itself.
     */
    public function isSourceAvailable(StorefrontListing $listing): bool
    {
        if ($listing->kind === StorefrontListing::KIND_SERVICE) {
            return true;
        }

        if (empty($listing->source_type) || empty($listing->source_id)) {
            // Manually-created product listing (e.g. a feed bag) with no
            // operational source row — availability is governed by quantity.
            return $this->hasQuantity($listing);
        }

        $source = $this->resolveSource($listing);

        if ($source === null) {
            return false;
        }

        if ($listing->isPigBacked()) {
            // A pig sold operationally is soft-deleted -> not fulfillable.
            return ! $this->isTrashed($source);
        }

        // Feed/manure backed by a still-present source row: governed by qty.
        return ! $this->isTrashed($source) && $this->hasQuantity($listing);
    }

    /**
     * Whether the listing may legally transition to published right now.
     * A listing cannot be published if its source is already gone.
     */
    public function canPublish(StorefrontListing $listing): bool
    {
        return $this->isSourceAvailable($listing);
    }

    /**
     * Resolve the source model defensively. We must include trashed pigs so we
     * can DETECT the sold state, hence withTrashed where supported.
     */
    public function resolveSource(StorefrontListing $listing): ?Model
    {
        $class = $listing->source_type;

        if (! is_string($class) || ! class_exists($class)) {
            return null;
        }

        $query = $class::query();

        // If the source model is soft-deletable, include trashed rows so the
        // caller can observe the sold/removed state rather than getting null.
        if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($class), true)) {
            $query = $query->withTrashed();
        }

        return $query->find($listing->source_id);
    }

    protected function isTrashed(Model $model): bool
    {
        return method_exists($model, 'trashed') && $model->trashed();
    }

    protected function hasQuantity(StorefrontListing $listing): bool
    {
        return bccomp((string) (float) ($listing->quantity_available ?? 0), '0', 4) > 0;
    }
}
