<?php

namespace App\Storefront\Services;

use App\Models\PigManagement;
use App\Models\Storefront\StorefrontListingPig;
use App\Models\Storefront\StorefrontOrderItem;
use App\Models\Storefront\StorefrontOrderItemPigAllocation;
use App\Models\User;
use App\Services\Stock\PigStockService;
use App\Storefront\Services\Exceptions\InventoryConflictException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Turning "five piglets" into five animals, at the moment they are sold.
 *
 * THE PROBLEM
 * -----------
 * A farm does not keep individual records for suckling piglets. Farrowing and
 * weaning record COUNTS — live births, stillbirths, a litter weaned — and the
 * first time an animal gets its own row in `pigs_management` is when it matters
 * individually. That is the right way round for the farm and an awkward one for
 * a shop, because `RecordPigSaleService` sells a PIG, and the rule this whole
 * feature exists to protect is that every real sale goes through it.
 *
 * THE CHOICE
 * ----------
 * Two ways out. Give piglet sales their own ledger — which is exactly the
 * second sales system this work is meant to prevent. Or create the animal
 * records at the point of sale, and sell them normally.
 *
 * This is the second. At approval, one `pigs_management` row per piglet on the
 * line, created through `PigStockService` so it gets a proper code, dispatches
 * `PigAcquired`, and appears in the herd exactly like any other animal — and
 * then sold through `RecordPigSaleService` like any other animal. Afterwards
 * `/sales` holds one row per piglet, each traceable to the order that bought
 * it, and no part of the system has learned a second way to sell a pig.
 *
 * WHY NOT EARLIER
 * ---------------
 * Materialising at listing time would fill the herd with rows for animals that
 * may never be sold, and a farm looking at its stock count would see piglets it
 * does not manage individually. The records exist because a sale needs them,
 * which is when they start being true.
 *
 * WHAT THESE ROWS ARE HONEST ABOUT
 * --------------------------------
 * Their `acquisition_source` marks them as point-of-sale piglets and names the
 * order, and their ear tag is derived from that order rather than
 * invented to look like a real one. Nobody tagged or sexed these animals;
 * pretending otherwise in the herd record would be worse than saying plainly
 * where they came from.
 */
class StorefrontPigletMaterialisationService
{
    public function __construct(
        protected PigStockService $stock,
    ) {
    }

    /**
     * Does this line sell piglets by the head, with no animals behind it yet?
     */
    public function appliesTo(StorefrontOrderItem $item): bool
    {
        if ($item->source_category !== StorefrontPigPricingService::CATEGORY_PIGLETS) {
            return false;
        }

        // A piglet listing that DOES have a pool — a farm that tags its piglets
        // and pooled them like any other stock — is sold the ordinary way.
        return ! StorefrontListingPig::query()
            ->where('storefront_listing_id', $item->listing_id)
            ->exists();
    }

    /**
     * How many animals this line still needs created.
     */
    public function outstandingFor(StorefrontOrderItem $item): int
    {
        $required = (int) round((float) $item->quantity);

        $existing = StorefrontOrderItemPigAllocation::query()
            ->where('storefront_order_item_id', $item->getKey())
            ->whereIn('status', array_merge(
                StorefrontOrderItemPigAllocation::ACTIVE_STATUSES,
                [StorefrontOrderItemPigAllocation::STATUS_SOLD],
            ))
            ->count();

        return max(0, $required - $existing);
    }

    /**
     * Create the animals and attach them to the line, ready to be sold.
     *
     * IDEMPOTENT. Only the outstanding number is created, so a retried approval
     * — a double-clicked button, a replayed event — finds nothing outstanding
     * and creates nothing. Without that, approving twice would put a second
     * litter of phantom animals in the herd.
     *
     * @return Collection<int, StorefrontOrderItemPigAllocation>
     */
    public function materialiseFor(StorefrontOrderItem $item, User $actor): Collection
    {
        if (! $this->appliesTo($item)) {
            return collect();
        }

        return DB::transaction(function () use ($item, $actor) {
            $outstanding = $this->outstandingFor($item);

            if ($outstanding === 0) {
                return collect();
            }

            $created = collect();

            for ($i = 0; $i < $outstanding; $i++) {
                $pig = $this->createPiglet($item, $i);

                $allocation = new StorefrontOrderItemPigAllocation();

                $allocation->forceFill([
                    'organization_id'          => $item->organization_id,
                    'storefront_order_id'      => $item->order_id,
                    'storefront_order_item_id' => $item->getKey(),
                    'storefront_listing_id'    => $item->listing_id,
                    'pig_id'                   => $pig->getKey(),
                    'selected_by_user_id'      => $actor->getKey(),
                    'selected_at'              => now(),
                    'status'                   => StorefrontOrderItemPigAllocation::STATUS_SELECTED,
                    'purchase_type'            => StorefrontPigPricingService::PURCHASE_MANUAL,
                    'allocation_snapshot'      => [
                        'materialised'   => true,
                        'reason'         => 'Piglet records are created at the point of sale.',
                        'order_item_id'  => $item->getKey(),
                    ],
                ])->save();

                $created->push($allocation);
            }

            return $created;
        });
    }

    /* ------------------------------------------------------------------ */

    protected function createPiglet(StorefrontOrderItem $item, int $index): PigManagement
    {
        $order = $item->order;

        $reference = $order?->reference ?: ('ORDER-' . $item->order_id);

        // Derived from the order, not invented to look like a farm tag. These
        // animals were never physically tagged, and a plausible-looking tag
        // would be indistinguishable from one that was.
        $tag = mb_substr('PGL-' . $reference . '-' . ($index + 1), 0, 60);

        $attempt = 0;

        while (PigManagement::withTrashed()->where('ear_tag_number', $tag)->exists()) {
            // A retried approval that got as far as creating animals, or a
            // reference reused across environments. Suffixing is better than
            // failing the sale, and better than silently reusing the row.
            $attempt++;
            $tag = mb_substr('PGL-' . $reference . '-' . ($index + 1) . '-' . $attempt, 0, 60);

            if ($attempt > 50) {
                throw new InventoryConflictException(
                    'Could not create a record for this piglet; its ear tag is already in use.',
                );
            }
        }

        return $this->stock->create([
            'ear_tag_number' => $tag,
            'breed'          => 'Unrecorded',
            // Nobody sexed these animals. 'unknown' is the honest value and the
            // column is a free string; writing 'female' to satisfy a dropdown
            // would put a fact in the herd record that nobody established.
            'gender'         => 'unknown',
            'stock_type'     => 'standard',
            // The farm records a birth date for the litter where it has one;
            // otherwise today, which is when this animal became a record.
            'date_of_birth'  => now()->toDateString(),
            // `pigs_management` has no notes column; `acquisition_source` is
            // the field that already means "where this animal came from".
            //
            // It is 50 characters, so the sentence this used to hold does not
            // fit. SQLite accepted it silently and MariaDB refused it — the
            // gate caught what every feature test had waved through. What
            // survives is the marker and the order it came from; the full
            // explanation lives in the allocation snapshot, which is JSON.
            'acquisition_source'  => mb_substr("point-of-sale piglet {$reference}", 0, 50),
            'acquisition_date'    => now()->toDateString(),
        ]);
    }
}
