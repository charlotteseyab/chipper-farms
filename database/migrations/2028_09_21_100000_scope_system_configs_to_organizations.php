<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * IMP-001 — settings become organisation-scoped.
 *
 * `system_configs` has carried a globally-unique `key` since it was created, so
 * every setting was shared by every tenant. Most of them are cosmetic, but
 * `sales.pricing.live_weight_price` and `sales.pricing.carcass_weight_price`
 * are read by `RecordPigSaleService` to price a real sale. On a second tenant
 * that is not a display bug — one farm's price per kilo would silently set
 * another farm's revenue.
 *
 * Phase 1 and Phase 8 each dodged this by giving their own concerns dedicated
 * scoped tables (`organization_feature_flags`, `storefront_settings`) rather
 * than extending this one. That was right for them and left this table as the
 * last shared surface.
 *
 * THE SHAPE OF THE FIX
 * --------------------
 * `organization_id` is added as NULLABLE, and a NULL row means "the default for
 * every organisation". Lookups try the organisation's own row first and fall
 * back to the NULL row.
 *
 * That matters for safety: **no existing row is moved or rewritten.** Every
 * current setting stays exactly where it is and keeps applying to every farm, so
 * a single-tenant install behaves identically the moment this runs. A farm only
 * departs from the default when someone saves a value for it, which writes a
 * scoped row and leaves the default intact for everyone else.
 *
 * The alternative — copying every row to every organisation — would have made
 * the backfill the risky part, and would freeze today's values as though each
 * farm had chosen them.
 *
 * THE UNIQUE KEY
 * --------------
 * `key` alone can no longer be unique, or a second farm could never store its
 * own value. It becomes unique on `(organization_id, key)`. Both MariaDB and
 * SQLite permit repeated NULLs in a unique index, which is what keeps one global
 * default per key working.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_configs')) {
            return;
        }

        if (! Schema::hasColumn('system_configs', 'organization_id')) {
            Schema::table('system_configs', function (Blueprint $table) {
                $table->unsignedBigInteger('organization_id')->nullable()->after('id');
            });
        }

        // Drop the single-column unique before adding the composite one. Named
        // explicitly rather than by column list, because Laravel's generated
        // name is what exists on the table.
        $this->dropKeyUniqueIfPresent();

        Schema::table('system_configs', function (Blueprint $table) {
            $table->unique(['organization_id', 'key'], 'system_configs_org_key_unique');

            $table->foreign('organization_id')
                ->references('id')->on('organizations')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('system_configs')) {
            return;
        }

        // A scoped row cannot survive the return to a globally-unique key: two
        // farms holding different values for one key have no single answer.
        // Rolling back therefore drops the scoped rows and keeps the defaults,
        // which is the state this migration found.
        DB::table('system_configs')->whereNotNull('organization_id')->delete();

        Schema::table('system_configs', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropUnique('system_configs_org_key_unique');
        });

        Schema::table('system_configs', function (Blueprint $table) {
            $table->dropColumn('organization_id');
        });

        Schema::table('system_configs', function (Blueprint $table) {
            $table->unique('key');
        });
    }

    protected function dropKeyUniqueIfPresent(): void
    {
        $connection = Schema::getConnection();
        $driver     = $connection->getDriverName();

        try {
            if ($driver === 'sqlite') {
                $indexes = $connection->select("PRAGMA index_list('system_configs')");

                foreach ($indexes as $index) {
                    $name = $index->name ?? '';
                    if (str_contains($name, 'key_unique') || $name === 'system_configs_key_unique') {
                        $connection->statement('DROP INDEX IF EXISTS "' . $name . '"');
                    }
                }

                return;
            }

            Schema::table('system_configs', function (Blueprint $table) {
                $table->dropUnique('system_configs_key_unique');
            });
        } catch (\Throwable $e) {
            // Already absent on a database that never had it. The composite key
            // added by the caller is what actually matters.
        }
    }
};
