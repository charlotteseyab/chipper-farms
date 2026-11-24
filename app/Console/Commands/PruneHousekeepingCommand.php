<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Removes rows that only grow:
 *
 *   - in-app notifications: every save creates one for the person who made
 *     it, and reminders add one per animal per day. Read ones go after 90
 *     days, unread ones after 180. Each reminder check also reads a person's
 *     notifications, so a never-emptied table slows those checks every hour.
 *   - abandoned guest carts: a cart is made for any visitor who adds to it,
 *     and one never checked out stays forever. Guest carts untouched for 30
 *     days go (their lines with them); signed-in buyers' carts are kept.
 *   - the database cache: expired entries are only removed when that same key
 *     is read again, so one-off keys (rate limits per visitor, task locks)
 *     stay forever.
 *
 * Deletes in small chunks so no long lock is held on a busy table. Records
 * people keep (audit trail, activity log, finance) are never touched.
 */
class PruneHousekeepingCommand extends Command
{
    protected $signature = 'system:prune-housekeeping {--dry-run : Count what would be removed}';

    protected $description = 'Remove old in-app notifications, abandoned guest carts and expired cache entries';

    private const CHUNK = 2000;

    public function handle(): int
    {
        $now = now();
        $targets = [
            'read notifications older than 90 days' => fn () => DB::table('notifications')
                ->whereNotNull('read_at')->where('created_at', '<', $now->copy()->subDays(90)),
            'unread notifications older than 180 days' => fn () => DB::table('notifications')
                ->whereNull('read_at')->where('created_at', '<', $now->copy()->subDays(180)),
            'abandoned guest carts older than 30 days' => fn () => DB::table('storefront_carts')
                ->whereNull('user_id')->where('status', '!=', 'converted')
                ->where('updated_at', '<', $now->copy()->subDays(30)),
            'expired cache entries' => fn () => DB::table(config('cache.stores.database.table', 'cache'))
                ->where('expiration', '<', $now->getTimestamp()),
            'expired cache locks' => fn () => DB::table(config('cache.stores.database.lock_table', 'cache_locks'))
                ->where('expiration', '<', $now->getTimestamp()),
        ];

        foreach ($targets as $label => $query) {
            $table = $query()->from;
            if (! Schema::hasTable($table)) {
                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("{$label}: " . $query()->count());
                continue;
            }

            $removed = 0;
            do {
                $column = in_array($table, ['notifications', 'storefront_carts'], true) ? 'id' : 'key';
                $keys = $query()->limit(self::CHUNK)->pluck($column);
                $removed += $keys->isEmpty() ? 0 : DB::table($table)->whereIn($column, $keys)->delete();
            } while ($keys->count() === self::CHUNK);

            $this->line("{$label}: {$removed} removed");
        }

        return self::SUCCESS;
    }
}
