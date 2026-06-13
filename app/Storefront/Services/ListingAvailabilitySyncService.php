<?php

namespace App\Storefront\Services;

use App\Models\Storefront\StorefrontListing;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Reconciles PUBLISHED listings against their operational source and flips any
 * whose source is no longer available to `sold_out`. This is the safety net
 * that keeps the public shop honest when a pig is sold through the operational
 * (non-storefront) path: the storefront never hears about that sale directly,
 * so a periodic sweep closes the gap.
 *
 * Run by the `storefront:sync-availability` command (scheduled) and invokable
 * ad-hoc. It only ever DEMOTES a published listing — it never publishes —
 * and it never touches operational data.
 */
class ListingAvailabilitySyncService
{
    public function __construct(
        protected ListingAvailabilityService $availability,
        protected ListingService $listingService,
    ) {
    }

    /**
     * @param  int|null  $organizationId  Limit to one org, or null for all.
     * @param  User|null $actor           Actor stamped on transitions; a system
     *                                    user is resolved if null.
     * @return array{checked:int, demoted:int, listing_ids:list<int>}
     */
    public function sync(?int $organizationId = null, ?User $actor = null): array
    {
        $actor ??= $this->systemActor($organizationId);

        $checked = 0;
        $demoted = 0;
        $ids = [];

        StorefrontListing::query()
            ->where('status', StorefrontListing::STATUS_PUBLISHED)
            ->when($organizationId !== null, fn ($q) => $q->where('organization_id', $organizationId))
            ->chunkById(200, function ($listings) use (&$checked, &$demoted, &$ids, $actor) {
                foreach ($listings as $listing) {
                    $checked++;

                    if ($this->availability->isSourceAvailable($listing)) {
                        continue;
                    }

                    try {
                        $this->listingService->markSoldOut($listing, $actor);
                        $demoted++;
                        $ids[] = $listing->id;
                    } catch (\Throwable $e) {
                        Log::warning('storefront:sync-availability failed to demote listing', [
                            'listing_id' => $listing->id,
                            'error'      => $e->getMessage(),
                        ]);
                    }
                }
            });

        return ['checked' => $checked, 'demoted' => $demoted, 'listing_ids' => $ids];
    }

    /**
     * Resolve an actor to stamp on automated transitions. Prefers an admin in
     * the target org; falls back to any admin. The transition only needs a
     * user id for audit (updated_by_user_id).
     */
    protected function systemActor(?int $organizationId): User
    {
        $query = User::query()->whereIn('role', ['superuser', 'admin']);

        if ($organizationId !== null) {
            $scoped = (clone $query)->where('organization_id', $organizationId)->first();
            if ($scoped !== null) {
                return $scoped;
            }
        }

        $any = $query->first();

        if ($any !== null) {
            return $any;
        }

        // Last resort for a bare environment: an unsaved system user. Only its
        // id is read for audit; here it is null, which the schema permits.
        return new User(['name' => 'system']);
    }
}
