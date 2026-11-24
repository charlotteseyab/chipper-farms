<?php

namespace App\Console\Commands\Storefront;

use App\Storefront\Services\ListingAvailabilitySyncService;
use App\Storefront\Services\StorefrontPigPoolService;
use Illuminate\Console\Command;

/**
 * storefront:sync-availability
 *
 * Sweeps published listings and demotes any whose operational source is no
 * longer available (e.g. the pig was sold through operations) to sold_out.
 * Scheduled hourly by StorefrontServiceProvider; also runnable on demand.
 */
class SyncListingAvailabilityCommand extends Command
{
    protected $signature = 'storefront:sync-availability {--organization= : Limit to a single organization id}';

    protected $description = 'Demote published storefront listings whose operational source is no longer available.';

    public function handle(ListingAvailabilitySyncService $service, StorefrontPigPoolService $pools): int
    {
        $organizationId = $this->option('organization') !== null
            ? (int) $this->option('organization')
            : null;

        $result = $service->sync($organizationId);

        $this->info(sprintf(
            'Storefront availability sync: checked %d, demoted %d to sold_out.',
            $result['checked'],
            $result['demoted'],
        ));

        if ($result['demoted'] > 0) {
            $this->line('Demoted listing ids: ' . implode(', ', $result['listing_ids']));
        }

        // Pooled listings are recounted against today's date: a pig whose
        // medicine withdrawal began drops out, one whose withdrawal has ended
        // comes back. Nothing is saved when a withdrawal runs out, so only a
        // sweep can notice.
        $pooled = $pools->resyncAllPools($organizationId);

        $this->info("Storefront pig pools recounted: {$pooled}.");

        return self::SUCCESS;
    }
}
