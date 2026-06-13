<?php

namespace App\Storefront\Services;

use App\Models\Storefront\StorefrontListing;
use App\Models\User;
use App\Storefront\Services\Exceptions\InvalidListingTransitionException;
use Illuminate\Support\Facades\DB;

/**
 * Owns the listing lifecycle state machine. Controllers call these methods
 * rather than flipping `status` directly, so every transition is validated,
 * audited (updated_by_user_id), and — for publish — guarded against an
 * unavailable source.
 *
 * Allowed transitions:
 *
 *   draft        -> published | archived
 *   published    -> unpublished | archived | sold_out | reserved
 *   unpublished  -> published | archived
 *   sold_out     -> unpublished | archived            (re-list after restock)
 *   reserved     -> published | sold_out | unpublished (released/closed by orders)
 *   archived     -> (terminal; restore via un-archive only)
 */
class ListingService
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        StorefrontListing::STATUS_DRAFT => [
            StorefrontListing::STATUS_PUBLISHED,
            StorefrontListing::STATUS_ARCHIVED,
        ],
        StorefrontListing::STATUS_PUBLISHED => [
            StorefrontListing::STATUS_UNPUBLISHED,
            StorefrontListing::STATUS_ARCHIVED,
            StorefrontListing::STATUS_SOLD_OUT,
            StorefrontListing::STATUS_RESERVED,
        ],
        StorefrontListing::STATUS_UNPUBLISHED => [
            StorefrontListing::STATUS_PUBLISHED,
            StorefrontListing::STATUS_ARCHIVED,
        ],
        StorefrontListing::STATUS_SOLD_OUT => [
            StorefrontListing::STATUS_UNPUBLISHED,
            StorefrontListing::STATUS_ARCHIVED,
        ],
        StorefrontListing::STATUS_RESERVED => [
            StorefrontListing::STATUS_PUBLISHED,
            StorefrontListing::STATUS_SOLD_OUT,
            StorefrontListing::STATUS_UNPUBLISHED,
        ],
        StorefrontListing::STATUS_ARCHIVED => [],
    ];

    public function __construct(
        protected ListingAvailabilityService $availability,
    ) {
    }

    public function publish(StorefrontListing $listing, User $actor): StorefrontListing
    {
        $this->assertTransitionAllowed($listing, StorefrontListing::STATUS_PUBLISHED);

        if (! $this->availability->canPublish($listing)) {
            throw new InvalidListingTransitionException(
                "Listing #{$listing->id} cannot be published: its source record is no longer available."
            );
        }

        return DB::transaction(function () use ($listing, $actor) {
            $listing->forceFill([
                'status'             => StorefrontListing::STATUS_PUBLISHED,
                'published_at'       => $listing->published_at ?? now(),
                'updated_by_user_id' => $actor->id,
            ])->save();

            return $listing->refresh();
        });
    }

    public function unpublish(StorefrontListing $listing, User $actor): StorefrontListing
    {
        $this->assertTransitionAllowed($listing, StorefrontListing::STATUS_UNPUBLISHED);

        return $this->applyStatus($listing, StorefrontListing::STATUS_UNPUBLISHED, $actor);
    }

    public function archive(StorefrontListing $listing, User $actor): StorefrontListing
    {
        $this->assertTransitionAllowed($listing, StorefrontListing::STATUS_ARCHIVED);

        return $this->applyStatus($listing, StorefrontListing::STATUS_ARCHIVED, $actor);
    }

    public function markSoldOut(StorefrontListing $listing, User $actor): StorefrontListing
    {
        $this->assertTransitionAllowed($listing, StorefrontListing::STATUS_SOLD_OUT);

        return $this->applyStatus($listing, StorefrontListing::STATUS_SOLD_OUT, $actor);
    }

    /**
     * Drive a listing to a target status from outside the menu helpers — still
     * validated through the same transition table. Used by reconciliation
     * (Phase 2+) and order workflows (Phase 3).
     */
    public function transitionTo(StorefrontListing $listing, string $target, User $actor): StorefrontListing
    {
        $this->assertTransitionAllowed($listing, $target);

        if ($target === StorefrontListing::STATUS_PUBLISHED && ! $this->availability->canPublish($listing)) {
            throw new InvalidListingTransitionException(
                "Listing #{$listing->id} cannot be published: its source record is no longer available."
            );
        }

        return $this->applyStatus($listing, $target, $actor);
    }

    public function canTransition(StorefrontListing $listing, string $target): bool
    {
        return in_array($target, self::TRANSITIONS[$listing->status] ?? [], true);
    }

    private function applyStatus(StorefrontListing $listing, string $target, User $actor): StorefrontListing
    {
        return DB::transaction(function () use ($listing, $target, $actor) {
            $attributes = [
                'status'             => $target,
                'updated_by_user_id' => $actor->id,
            ];

            if ($target === StorefrontListing::STATUS_PUBLISHED && $listing->published_at === null) {
                $attributes['published_at'] = now();
            }

            $listing->forceFill($attributes)->save();

            return $listing->refresh();
        });
    }

    private function assertTransitionAllowed(StorefrontListing $listing, string $target): void
    {
        if ($listing->status === $target) {
            return; // idempotent no-op
        }

        if (! $this->canTransition($listing, $target)) {
            throw new InvalidListingTransitionException(
                "Illegal listing transition: {$listing->status} -> {$target} (listing #{$listing->id})."
            );
        }
    }
}
