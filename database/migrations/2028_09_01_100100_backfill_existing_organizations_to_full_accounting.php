<?php

use App\Support\Features\FeatureCatalog;
use App\Support\Features\ModulePreset;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill every existing organisation to Full Accounting (blueprint §53).
 *
 * WHY EXISTING ORGANISATIONS ARE NOT PUT ON ESSENTIALS
 * ----------------------------------------------------
 * Essentials is the right default for a *new* farm. Applying it to an existing
 * one would remove, at deploy time and without warning, screens a current
 * customer's accountant is using today: chart of accounts, journals, trial
 * balance, period close, WHT, supplier statements. Blueprint §53 and §57 both
 * say the same thing — implementation must be additive and must not
 * unexpectedly hide functionality for current users. The migration path to a
 * simpler preset is an explicit administrator decision, not a side effect of
 * running migrations.
 *
 * So existing organisations get everything, matching what they can already
 * reach. New organisations created after this migration get the catalogue
 * defaults, which are Essentials-shaped.
 *
 * IDEMPOTENT AND RESUMABLE
 * ------------------------
 * The prompt requires backfills to be both. This one:
 *
 *   - skips organisations that already have any flag row, so a re-run cannot
 *     overwrite an administrator's later choices;
 *   - upserts rather than inserts, so a partial run that died halfway can be
 *     resumed without unique-constraint failures;
 *   - chunks, so a large tenant list does not build one enormous statement.
 *
 * NO DOWN() DESTRUCTION
 * ---------------------
 * down() deletes only rows this migration could have written — those whose
 * applied_preset marks them as backfilled. Dropping every row would destroy
 * configuration an administrator made after deploy, which a rollback of *this*
 * migration has no business touching.
 */
return new class extends Migration
{
    private const BACKFILL_MARKER = ModulePreset::FULL_ACCOUNTING;

    public function up(): void
    {
        if (! Schema::hasTable('organization_feature_flags') || ! Schema::hasTable('organizations')) {
            return;
        }

        $now = now();

        // Full Accounting is the top of the ladder, but it is not the whole
        // catalogue — the storefront overlay's optional categories and delivery
        // are not in it. Existing organisations should keep reaching everything
        // they can reach today, so the backfill is the union of Full Accounting
        // and every other catalogue key, rather than one preset.
        $keys = FeatureCatalog::keys();

        DB::table('organizations')->orderBy('id')->chunkById(100, function ($organizations) use ($keys, $now): void {
            foreach ($organizations as $organization) {
                $alreadyConfigured = DB::table('organization_feature_flags')
                    ->where('organization_id', $organization->id)
                    ->exists();

                if ($alreadyConfigured) {
                    continue;
                }

                $rows = [];
                foreach ($keys as $key) {
                    $rows[] = [
                        'organization_id' => $organization->id,
                        'feature_key'     => $key,
                        'enabled'         => true,
                        'configuration'   => null,
                        'applied_preset'  => self::BACKFILL_MARKER,
                        'enabled_by'      => null,
                        'enabled_at'      => $now,
                        'disabled_by'     => null,
                        'disabled_at'     => null,
                        'created_at'      => $now,
                        'updated_at'      => $now,
                    ];
                }

                DB::table('organization_feature_flags')->upsert(
                    $rows,
                    ['organization_id', 'feature_key'],
                    ['enabled', 'applied_preset', 'enabled_at', 'updated_at'],
                );
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('organization_feature_flags')) {
            return;
        }

        DB::table('organization_feature_flags')
            ->where('applied_preset', self::BACKFILL_MARKER)
            ->whereNull('enabled_by')
            ->delete();
    }
};
