<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase E2 — additive indexes on legacy reporting paths.
 *
 * Identified during the controller decomposition audit. Many controllers
 * use DATEDIFF(NOW(), date_of_birth) or DATEDIFF(death_date, date_of_birth)
 * patterns. These can't use a column index directly (the function on the
 * left-hand side prevents sargability), but having an index on the date
 * column itself helps when range scans are combined with other predicates.
 *
 * Specifically targeted:
 *   - pigs_management.date_of_birth — used by weaning, maturity, dashboard
 *     queries to bucket pigs by age.
 *   - pig_deaths.death_date        — already implicitly indexed via the
 *     date_from / date_to filters in the deaths index.
 *   - pig_sales.sale_date          — same pattern.
 *
 * (farrowing.farrowing_date was originally listed here too; see the note in
 * up() for why it is intentionally not added.)
 *
 * IDEMPOTENCY — corrected (CF-P1-016)
 * -----------------------------------
 * This header previously claimed "Laravel's Schema::table catches 'duplicate
 * key name' errors implicitly via try/catch". It does not, and the try/catch
 * blocks that were here caught nothing:
 *
 *   Blueprint::index() only QUEUES an IndexDefinition. The SQL runs after the
 *   closure returns, when the blueprint is built and executed — outside the
 *   try. So a duplicate index name would have propagated as a QueryException
 *   and failed the migration, exactly the case the comment claimed was handled.
 *
 * Each addition now goes through addIndexIfMissing(), which inspects
 * Schema::getIndexes() and skips work that is already done. That makes the
 * migration genuinely re-runnable, which matters for the partial-failure
 * recovery path the register raises.
 */
return new class extends Migration
{
    public function up(): void
    {
        // pigs_management.date_of_birth
        $this->addIndexIfMissing('pigs_management', 'date_of_birth', 'pigs_management_date_of_birth_idx');

        // pigs_management.stock_type — used by all the standard/breeding splits
        $this->addIndexIfMissing('pigs_management', 'stock_type', 'pigs_management_stock_type_idx');

        // farrowing.farrowing_date — DELIBERATELY NOT ADDED (CF-P1-016).
        //
        // This block previously guarded on Schema::hasTable('farrowings') while
        // the table is named `farrowing` (singular), so the branch never
        // executed. The register records that as "Upgrade/recovery can skip
        // indexes".
        //
        // The dead branch is real, but that impact is disproved: the index
        // already exists. 2025_07_31_164148_create_farrowing_table.php line 43
        // declares $table->index('farrowing_date'), producing
        // farrowing_farrowing_date_index — confirmed present on MariaDB, along
        // with a composite farrowing_sow_id_farrowing_date_index that also leads
        // with sow_id.
        //
        // So correcting the table name would not restore missing coverage; it
        // would add a SECOND index on the same column, paying write cost on
        // every farrowing insert and update for no read benefit. The right
        // remediation is to delete the dead branch and record why, which is what
        // this comment is.

        // pig_deaths.death_date
        $this->addIndexIfMissing('pig_deaths', 'death_date', 'pig_deaths_death_date_idx');

        // pig_sales.sale_date
        $this->addIndexIfMissing('pig_sales', 'sale_date', 'pig_sales_sale_date_idx');

        // pig_sales.payment_status — used by the pending() scope.
        $this->addIndexIfMissing('pig_sales', 'payment_status', 'pig_sales_payment_status_idx');

        // raw_material_stock_logs.type — heavily filtered (receive/usage/adjustment).
        $this->addIndexIfMissing('raw_material_stock_logs', 'type', 'raw_material_stock_logs_type_idx');

        // purchase_requests.status — filtered constantly in index pages.
        $this->addIndexIfMissing('purchase_requests', 'status', 'purchase_requests_status_idx');

        // matings — frequently filtered by status
        $this->addIndexIfMissing('matings', 'status', 'matings_status_idx');
    }

    public function down(): void
    {
        // The 'farrowings' => ['farrowings_farrowing_date_idx'] entry that was
        // here has been removed: neither the table nor the index ever existed
        // under those names, so it was dead in both directions.
        $drops = [
            'pigs_management'         => ['pigs_management_date_of_birth_idx', 'pigs_management_stock_type_idx'],
            'pig_deaths'              => ['pig_deaths_death_date_idx'],
            'pig_sales'               => ['pig_sales_sale_date_idx', 'pig_sales_payment_status_idx'],
            'raw_material_stock_logs' => ['raw_material_stock_logs_type_idx'],
            'purchase_requests'       => ['purchase_requests_status_idx'],
            'matings'                 => ['matings_status_idx'],
        ];

        foreach ($drops as $tableName => $indexes) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            $existing = $this->indexNames($tableName);

            foreach ($indexes as $idx) {
                if (! in_array(strtolower($idx), $existing, true)) {
                    continue;
                }

                // Dropped one per Schema::table call: dropIndex() queues its
                // command the same way index() does, so a failure on one would
                // otherwise abandon the rest of the batch.
                Schema::table($tableName, function (Blueprint $table) use ($idx) {
                    $table->dropIndex($idx);
                });
            }
        }
    }

    /**
     * Create a single-column index only when it is not already present.
     *
     * Replaces the previous try/catch-inside-the-closure pattern, which could
     * not work: Blueprint::index() queues a definition and the SQL executes
     * after the closure returns, so nothing was ever caught.
     */
    protected function addIndexIfMissing(string $table, string $column, string $indexName): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        if (in_array(strtolower($indexName), $this->indexNames($table), true)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $indexName) {
            $blueprint->index($column, $indexName);
        });
    }

    /**
     * Lower-cased index names present on a table.
     *
     * @return list<string>
     */
    protected function indexNames(string $table): array
    {
        return array_map(
            fn ($index) => strtolower((string) ($index['name'] ?? '')),
            Schema::getIndexes($table),
        );
    }
};
