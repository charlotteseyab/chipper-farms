<?php

namespace App\Storefront\Services;

use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontListingMedia;
use App\Models\User;
use App\Storefront\Services\Exceptions\InventoryConflictException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Back-office editing of listing content + media. Content edits are restricted
 * to presentation/commerce fields — the source link, kind and audit columns
 * are never editable here (re-pushing is the way to re-source a listing).
 *
 * Media supports both uploaded files (stored on the public disk) and external
 * URLs (e.g. a video link). Exactly one item is primary at a time.
 */
class ListingEditorService
{
    /** Fields a back-office editor may change. */
    private const EDITABLE = [
        'title', 'public_description', 'seller_note', 'category_id',
        'price_per_kg', 'fixed_price', 'currency_code',
        'quantity_available', 'quantity_unit', 'estimated_weight_kg',
        'sku', 'pack_size',
        'pickup_allowed', 'delivery_allowed', 'delivery_fee', 'fulfilment_notes',
        'available_from', 'available_until', 'badges', 'sale_mode', 'pricing_basis',
    ];

    /**
     * @param  array<string,mixed>  $data  Validated subset of EDITABLE, plus
     *                                     optional pool_pig_ids for stock-backed listings.
     *
     * @throws InventoryConflictException
     */
    public function update(StorefrontListing $listing, array $data, User $actor): StorefrontListing
    {
        $payload = array_intersect_key($data, array_flip(self::EDITABLE));
        if (array_key_exists('currency_code', $payload) && blank($payload['currency_code'])) {
            $payload['currency_code'] = $listing->currency_code ?: 'GHS';
        }
        $payload['updated_by_user_id'] = $actor->id;

        return DB::transaction(function () use ($listing, $payload, $data, $actor) {
            $listing->fill($payload)->save();

            if (($data['pool_pig_ids_present'] ?? false) === true) {
                app(StorefrontPigPoolService::class)->syncPigs(
                    $listing,
                    array_filter(array_map('intval', (array) ($data['pool_pig_ids'] ?? []))),
                    $actor,
                );
            }

            return $listing->refresh();
        });
    }

    public function addUploadedMedia(StorefrontListing $listing, UploadedFile $file, ?string $alt = null): StorefrontListingMedia
    {
        $path = $file->store('storefront/listings/' . $listing->id, 'public');

        return $this->createMedia($listing, [
            'type'     => StorefrontListingMedia::TYPE_IMAGE,
            'disk'     => 'public',
            'path'     => $path,
            'alt_text' => $alt,
        ]);
    }

    public function addUrlMedia(StorefrontListing $listing, string $url, string $type = StorefrontListingMedia::TYPE_IMAGE, ?string $alt = null): StorefrontListingMedia
    {
        return $this->createMedia($listing, [
            'type'     => $type,
            'disk'     => 'public',
            'path'     => $url,
            'alt_text' => $alt,
        ]);
    }

    public function removeMedia(StorefrontListingMedia $media): void
    {
        DB::transaction(function () use ($media) {
            $wasPrimary = $media->is_primary;
            $listingId  = $media->listing_id;

            // Delete the underlying file if it is a stored (non-URL) asset.
            if ($media->path && ! str_starts_with($media->path, 'http')) {
                Storage::disk($media->disk ?: 'public')->delete($media->path);
            }

            $media->delete();

            // Promote a new primary if we removed the primary one.
            if ($wasPrimary) {
                $next = StorefrontListingMedia::query()
                    ->where('listing_id', $listingId)
                    ->orderBy('sort_order')->first();
                $next?->forceFill(['is_primary' => true])->save();
            }
        });
    }

    public function setPrimary(StorefrontListingMedia $media): void
    {
        DB::transaction(function () use ($media) {
            StorefrontListingMedia::query()
                ->where('listing_id', $media->listing_id)
                ->update(['is_primary' => false]);

            $media->forceFill(['is_primary' => true])->save();
        });
    }

    /**
     * @param  array<string,mixed>  $attrs
     */
    protected function createMedia(StorefrontListing $listing, array $attrs): StorefrontListingMedia
    {
        return DB::transaction(function () use ($listing, $attrs) {
            $existingCount = StorefrontListingMedia::query()
                ->where('listing_id', $listing->id)->count();

            $media = StorefrontListingMedia::create(array_merge($attrs, [
                'organization_id' => $listing->organization_id,
                'listing_id'      => $listing->id,
                'sort_order'      => $existingCount,
                'is_primary'      => $existingCount === 0, // first image becomes primary
            ]));

            return $media;
        });
    }
}
