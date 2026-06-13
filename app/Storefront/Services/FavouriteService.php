<?php

namespace App\Storefront\Services;

use App\Models\Storefront\StorefrontFavourite;
use App\Models\Storefront\StorefrontListing;
use App\Models\User;
use Illuminate\Database\QueryException;

/**
 * Saved items / favourites. Toggling is idempotent and concurrency-safe: the
 * unique (user_id, listing_id) index is the source of truth, so a double-tap
 * cannot create duplicate rows.
 */
class FavouriteService
{
    public function isFavourited(User $user, StorefrontListing $listing): bool
    {
        return StorefrontFavourite::query()
            ->where('user_id', $user->id)
            ->where('listing_id', $listing->id)
            ->exists();
    }

    public function add(User $user, StorefrontListing $listing): StorefrontFavourite
    {
        $existing = StorefrontFavourite::query()
            ->where('user_id', $user->id)
            ->where('listing_id', $listing->id)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        try {
            return StorefrontFavourite::create([
                'organization_id' => $listing->organization_id,
                'user_id'         => $user->id,
                'listing_id'      => $listing->id,
            ]);
        } catch (QueryException $e) {
            // Lost the race to the unique index — return the winner's row.
            $row = StorefrontFavourite::query()
                ->where('user_id', $user->id)
                ->where('listing_id', $listing->id)
                ->first();

            if ($row !== null) {
                return $row;
            }
            throw $e;
        }
    }

    public function remove(User $user, StorefrontListing $listing): void
    {
        StorefrontFavourite::query()
            ->where('user_id', $user->id)
            ->where('listing_id', $listing->id)
            ->delete();
    }

    /**
     * Toggle and return the resulting state (true = now favourited).
     */
    public function toggle(User $user, StorefrontListing $listing): bool
    {
        if ($this->isFavourited($user, $listing)) {
            $this->remove($user, $listing);
            return false;
        }

        $this->add($user, $listing);
        return true;
    }
}
