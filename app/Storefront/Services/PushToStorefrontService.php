<?php

namespace App\Storefront\Services;

use App\Models\Storefront\StorefrontListing;
use App\Models\User;
use App\Storefront\Services\Exceptions\DuplicateListingException;
use App\Storefront\Support\PushToStorefrontData;
use App\Storefront\Support\StorefrontSettings;
use App\Support\CurrentOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "Push to Storefront" — the write path that turns an operational record (a
 * pig, breeding-stock pig, feed type, manure batch) OR a manually-described
 * product into a public storefront listing.
 *
 * Invariants:
 *   1. Operational truth is NEVER mutated here. We only read the source row
 *      to snapshot presentation defaults (tag, breed, weight). The pig/feed
 *      record is untouched.
 *   2. Org-scoped. organization_id comes from CurrentOrganization, bound by
 *      EnsureOrganizationContext (HTTP) or explicitly (jobs/tests).
 *   3. Duplicate-safe. A given pig source may have at most one ACTIVE
 *      (draft|published|unpublished|reserved) listing at a time — pushing the
 *      same pig twice throws rather than creating a second sellable record.
 *   4. Pricing default. When the wizard leaves a per-kg price blank for a
 *      live/carcass basis, we fall back to the SAME system settings the
 *      operational sale path uses, so storefront price == recorded-sale price.
 *   5. Audited. created_by_user_id / updated_by_user_id are stamped.
 *
 * Returns the freshly created listing (in draft unless publishNow was set and
 * the source is available).
 */
class PushToStorefrontService
{
    public function __construct(
        protected PigListingEligibilityService $eligibility,
        protected CurrentOrganization $currentOrganization,
        protected ListingService $listingService,
        protected ListingAvailabilityService $availability,
    ) {
    }

    /**
     * @param  Model|null  $source  Operational source model, or null for a
     *                              manually-described product listing.
     */
    public function execute(?Model $source, User $actor, PushToStorefrontData $data): StorefrontListing
    {
        $organizationId = $this->currentOrganization->requireId();

        return DB::transaction(function () use ($source, $actor, $data, $organizationId) {
            $sourceType = $source !== null ? $source::class : null;
            $sourceId   = $source !== null ? (int) $source->getKey() : null;

            if ($sourceType !== null && $sourceId !== null) {
                $this->guardAgainstDuplicate($organizationId, $sourceType, $sourceId);
            }

            // Phase 11 (§35 step 2) — "alive, unsold, not already actively
            // listed, not under withdrawal/health hold, has latest weight if
            // price-by-kg". Two of those were enforced, one late, and two could
            // not be enforced at all until this phase added the data.
            //
            // Every blocker is reported at once: a staff member standing in a
            // barn should learn everything that is wrong in one go, not one
            // thing per attempt.
            if ($source instanceof \App\Models\PigManagement) {
                $eligibility = $this->eligibility->check($source, $data->pricingBasis, $organizationId);

                if (! $eligibility['eligible']) {
                    throw new \App\Storefront\Services\Exceptions\ListingNotEligibleException(
                        $eligibility['blockers'],
                    );
                }
            }

            $listing = new StorefrontListing();
            $listing->organization_id    = $organizationId;
            $listing->category_id        = $data->categoryId;
            $listing->kind               = $data->kind;
            $listing->source_type        = $sourceType;
            $listing->source_id          = $sourceId;
            $listing->title              = $data->title;
            $listing->slug               = $this->uniqueSlug($organizationId, $data->title);
            $listing->reference          = $this->nextReference($organizationId);
            $listing->public_description = $data->publicDescription;
            $listing->seller_note        = $data->sellerNote;
            $listing->badges             = $data->badges;

            // Sale mode + pricing. A quote-only listing carries no price; a
            // hidden listing is staff-visible only.
            $listing->sale_mode     = $data->saleMode;
            $listing->pricing_basis = $data->pricingBasis;
            $listing->currency_code = $data->currencyCode ?: StorefrontSettings::defaultCurrency();

            $this->applyPricing($listing, $data, $source);

            // Quantity / weight.
            $listing->quantity_available  = $data->quantityAvailable;
            $listing->quantity_unit       = $data->quantityUnit;
            $listing->estimated_weight_kg = $data->estimatedWeightKg
                ?? $this->snapshotWeight($source);

            // Product attributes (feed/manure/service).
            $listing->sku       = $data->sku;
            $listing->pack_size = $data->packSize;

            // Fulfilment.
            $listing->pickup_allowed   = $data->pickupAllowed;
            $listing->delivery_allowed = $data->deliveryAllowed;
            $listing->delivery_fee     = $data->deliveryFee;
            $listing->fulfilment_notes = $data->fulfilmentNotes;

            // Availability window.
            $listing->available_from  = $data->availableFrom;
            $listing->available_until = $data->availableUntil;

            // Lifecycle starts in draft; audit.
            $listing->status             = StorefrontListing::STATUS_DRAFT;
            $listing->created_by_user_id = $actor->id;
            $listing->updated_by_user_id = $actor->id;

            $listing->save();

            // Optional immediate publish — routed through ListingService so the
            // same transition guards + source-availability check apply.
            if ($data->publishNow
                && $data->saleMode !== StorefrontListing::MODE_HIDDEN
                && $this->availability->canPublish($listing)) {
                $listing = $this->listingService->publish($listing, $actor);
            }

            return $listing;
        });
    }

    /**
     * A pig source must not be double-listed. We treat draft/published/
     * unpublished/reserved as "active"; archived/sold_out free the source for
     * a fresh listing.
     */
    protected function guardAgainstDuplicate(int $organizationId, string $sourceType, int $sourceId): void
    {
        $exists = StorefrontListing::query()
            ->where('organization_id', $organizationId)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->whereIn('status', [
                StorefrontListing::STATUS_DRAFT,
                StorefrontListing::STATUS_PUBLISHED,
                StorefrontListing::STATUS_UNPUBLISHED,
                StorefrontListing::STATUS_RESERVED,
            ])
            ->exists();

        if ($exists) {
            throw new DuplicateListingException(
                "An active storefront listing already exists for {$sourceType}#{$sourceId}."
            );
        }
    }

    protected function applyPricing(StorefrontListing $listing, PushToStorefrontData $data, ?Model $source): void
    {
        if ($data->isQuoteMode()) {
            // Quote-only listings advertise no price.
            $listing->price_per_kg = null;
            $listing->fixed_price  = null;
            return;
        }

        if ($data->isFixedPricing()) {
            $listing->price_per_kg = null;
            $listing->fixed_price  = $data->fixedPrice;
            return;
        }

        // Live/carcass per-kg basis: explicit price, else system default.
        $listing->fixed_price  = null;
        $listing->price_per_kg = $data->pricePerKg
            ?? StorefrontSettings::defaultPriceForBasis($data->pricingBasis);
    }

    /**
     * Read-only weight snapshot from a pig source (if present). Never writes.
     */
    protected function snapshotWeight(?Model $source): ?string
    {
        if ($source === null) {
            return null;
        }

        $weight = $source->current_weight_kg_cache ?? null;

        return $weight !== null ? (string) $weight : null;
    }

    protected function uniqueSlug(int $organizationId, string $title): string
    {
        $base = Str::slug($title) ?: 'listing';
        $slug = $base;
        $i = 1;

        while (StorefrontListing::query()
            ->where('organization_id', $organizationId)
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base . '-' . (++$i);
        }

        return $slug;
    }

    /**
     * Allocate a listing reference (CF-P1-014). Was count()+1.
     *
     * Not named in the register, found by sweeping the repository for
     * count-based identifiers as the remediation brief requires.
     */
    protected function nextReference(int $organizationId): string
    {
        return app(\App\Storefront\Services\StorefrontReferenceAllocator::class)
            ->listingReference($organizationId);
    }
}
