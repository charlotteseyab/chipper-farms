<?php

namespace App\Console\Commands\Storefront;

use App\Storefront\Services\ListingAvailabilitySyncService;
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

    public function handle(ListingAvailabilitySyncService $service): int
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

        return self::SUCCESS;
    }
}
