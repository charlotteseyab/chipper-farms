<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase 0.2 backfill.
 *
 * For every pig_sales row with a non-null legacy `sold_by` string:
 *   - copy the string verbatim into sold_by_name (preserves audit trail)
 *   - if users.name matches exactly, populate sold_by_user_id (unique match only)
 *
 * Idempotent. Safe to re-run. Only touches rows where the target columns are
 * still null.
 *
 * Ambiguous matches (two users with the same name) are deliberately left with
 * a null sold_by_user_id — auditable rather than guessed. Operators can review
 * the leftover rows via the --report option.
 *
 * Usage:
 *   php artisan chipper:backfill-pig-sale-sold-by --dry-run
 *   php artisan chipper:backfill-pig-sale-sold-by
 *   php artisan chipper:backfill-pig-sale-sold-by --report
 */
class BackfillPigSaleSoldBy extends Command
{
    protected $signature = 'chipper:backfill-pig-sale-sold-by
                            {--dry-run : Report what would change without writing.}
                            {--report : Show rows that could not be resolved to a user.}';

    protected $description = 'Backfill pig_sales.sold_by_user_id and sold_by_name from the legacy sold_by string column.';

    public function handle(): int
    {
        // Step 1: copy the legacy string into sold_by_name for any rows
        // that have not yet been touched.
        $nameCopyTargets = DB::table('pig_sales')
            ->whereNull('sold_by_name')
            ->whereNotNull('sold_by')
            ->count();

        // Step 2: resolve sold_by_user_id where a unique user name matches.
        // Two-stage approach because the legacy value may contain extra whitespace
        // or case variance; we normalise before comparing.
        $ambiguous = [];
        $unresolved = [];
        $resolved = 0;

        $salesToResolve = DB::table('pig_sales')
            ->whereNull('sold_by_user_id')
            ->whereNotNull('sold_by')
            ->select('id', 'sold_by')
            ->get();

        $this->info("Rows needing sold_by_name copy: {$nameCopyTargets}");
        $this->info("Rows needing sold_by_user_id resolution: " . $salesToResolve->count());

        if ($this->option('dry-run')) {
            $this->warn('Dry run: no changes written. Re-run without --dry-run to apply.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($salesToResolve, &$resolved, &$ambiguous, &$unresolved) {
            // Copy legacy string into sold_by_name in bulk.
            DB::update('
                UPDATE pig_sales
                SET sold_by_name = sold_by, updated_at = updated_at
                WHERE sold_by_name IS NULL AND sold_by IS NOT NULL
            ');

            // Resolve FK per-row with exact name match only.
            foreach ($salesToResolve as $sale) {
                $trimmed = trim((string) $sale->sold_by);
                if ($trimmed === '') {
                    continue;
                }

                $matches = DB::table('users')
                    ->where('name', $trimmed)
                    ->pluck('id');

                if ($matches->count() === 1) {
                    DB::table('pig_sales')
                        ->where('id', $sale->id)
                        ->update([
                            'sold_by_user_id' => $matches->first(),
                            // updated_at intentionally not touched — this is a
                            // backfill, not a business-logic change.
                        ]);
                    $resolved++;
                } elseif ($matches->count() > 1) {
                    $ambiguous[] = ['sale_id' => $sale->id, 'name' => $trimmed, 'user_count' => $matches->count()];
                } else {
                    $unresolved[] = ['sale_id' => $sale->id, 'name' => $trimmed];
                }
            }
        });

        $this->info("Resolved sold_by_user_id for {$resolved} sale(s).");
        $this->info("Ambiguous (multiple users with same name): " . count($ambiguous));
        $this->info("Unresolved (no matching user): " . count($unresolved));

        if ($this->option('report')) {
            if (! empty($ambiguous)) {
                $this->warn('Ambiguous rows:');
                $this->table(['sale_id', 'name', 'user_count'], $ambiguous);
            }
            if (! empty($unresolved)) {
                $this->warn('Unresolved rows:');
                $this->table(['sale_id', 'name'], $unresolved);
            }
        }

        $this->info('Done. Ambiguous and unresolved rows have sold_by_name populated for audit but sold_by_user_id left null.');
        return self::SUCCESS;
    }
}