<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\Feed\FeedModuleReset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Clear one farm's feed module, with its accounting, for a fresh start.
 * Shows what would go unless --force is given, and saves every removed row
 * to storage/app/feed-reset/ first. Never runs in production.
 *
 *   php artisan feed:reset --organization=1            # see what would go
 *   php artisan feed:reset --organization=1 --force    # do it
 */
class ResetFeedModule extends Command
{
    protected $signature = 'feed:reset {--organization= : The farm (organization id) to clear} {--force : Delete; without it nothing changes}';

    protected $description = "Clear one farm's feed records and the accounting they posted (local and test only)";

    public function handle(FeedModuleReset $reset): int
    {
        if (app()->environment('production')) {
            $this->error('feed:reset never runs in production.');

            return self::FAILURE;
        }

        $organization = Organization::find($this->option('organization'));
        if (! $organization) {
            $this->error('Give the farm to clear with --organization=<id>.');

            return self::FAILURE;
        }

        $force = (bool) $this->option('force');
        $rows = $reset->run((int) $organization->id, dryRun: true);

        $this->info(($force ? 'Clearing' : 'Would clear')." the feed module for {$organization->name}:");
        $this->table(['Table', 'Rows'], collect($rows)->filter()->map(fn ($r, $table) => [$table, count($r)])->values()->all());

        if (! $force) {
            $this->line('Nothing was changed. Run again with --force to delete.');

            return self::SUCCESS;
        }

        $path = 'feed-reset/'.$organization->id.'-'.now()->format('Ymd-His').'.json';
        Storage::disk('local')->put($path, json_encode(['organization_id' => $organization->id, 'taken_at' => now()->toIso8601String(), 'rows' => $rows], JSON_PRETTY_PRINT));
        $this->line('Backup of every row: '.Storage::disk('local')->path($path));

        $reset->run((int) $organization->id, dryRun: false);
        $this->info('Feed module cleared.');

        return self::SUCCESS;
    }
}
