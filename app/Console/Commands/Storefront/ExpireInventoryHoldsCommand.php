<?php

namespace App\Console\Commands\Storefront;

use App\Models\Storefront\StorefrontInventoryHold;
use App\Models\Storefront\StorefrontOrder;
use App\Storefront\Services\HoldService;
use App\Storefront\Services\OrderService;
use Illuminate\Console\Command;

/**
 * Return stock from checkouts that never completed (Phase 10, §37).
 *
 * A buyer who reaches Paystack and closes the tab leaves a hold behind. Without
 * this the last boar stays unsellable until someone notices, which for a farm
 * with one of a thing is the whole shop for that line.
 *
 * WHAT IT DOES NOT DO
 * -------------------
 * It does not cancel the order. An unpaid order whose hold lapsed is still a
 * real enquiry — the buyer may ring up, or their bank may be slow. Cancelling on
 * a timer would destroy that, and the order state machine belongs to
 * `OrderService`, not to a sweep. The hold expires; the order stays where it is
 * and is now competing for stock like anyone else. Staff see the lapse on the
 * order timeline.
 */
class ExpireInventoryHoldsCommand extends Command
{
    protected $signature = 'storefront:expire-holds
                            {--dry-run : Report what would expire without changing anything}';

    protected $description = 'Release checkout holds whose payment window has passed.';

    public function handle(HoldService $holds, OrderService $orders): int
    {
        $due = StorefrontInventoryHold::query()
            ->where('status', StorefrontInventoryHold::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->with('order')
            ->get();

        if ($due->isEmpty()) {
            $this->info('No holds are due to expire.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            foreach ($due as $hold) {
                $this->line(sprintf(
                    'Would expire hold #%d — order %s, listing #%d, qty %s (due %s)',
                    $hold->id,
                    $hold->order?->reference ?? '?',
                    $hold->listing_id,
                    $hold->quantityString(),
                    $hold->expires_at?->toDateTimeString(),
                ));
            }

            $this->info("{$due->count()} hold(s) would be expired.");

            return self::SUCCESS;
        }

        // The sweep itself is one conditional UPDATE inside HoldService, so a
        // hold a confirmation grabbed between the read above and here is left
        // alone — its status is no longer `active`.
        $expired = $holds->expireDue();

        // The timeline entry is written per order afterwards rather than inside
        // the update, so a slow event write cannot hold a lock across the sweep.
        // Orders whose hold was consumed in the meantime are skipped, because
        // they no longer appear as expired.
        foreach ($due->groupBy('order_id') as $orderId => $group) {
            $order = $group->first()->order;

            if (! $order instanceof StorefrontOrder) {
                continue;
            }

            // Re-read: a confirmation may have consumed this hold between the
            // query above and the sweep, in which case it was never expired and
            // the timeline must not claim it was.
            if ($group->first()->fresh()?->status !== StorefrontInventoryHold::STATUS_EXPIRED) {
                continue;
            }

            $orders->recordEvent(
                order: $order,
                type: 'inventory',
                note: 'Checkout hold expired; the items are back on sale. The order has not been cancelled.',
            );
        }

        $this->info("Expired {$expired} hold(s).");

        return self::SUCCESS;
    }
}
