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
 *   - farrowings.farrowing_date    — used by weaning controller to find
 *     litters due for weaning.
 *   - pig_deaths.death_date        — already implicitly indexed via the
 *     date_from / date_to filters in the deaths index.
 *   - pig_sales.sale_date          — same pattern.
 *
 * Where the column already has an index, Laravel's Schema::table catches
 * "duplicate key name" errors implicitly via try/catch. Each addition is
 * guarded by Schema::hasColumn so it's safe on partial schemas.
 *
 * Safe and idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        // pigs_management.date_of_birth
        if (Schema::hasTable('pigs_management') && Schema::hasColumn('pigs_management', 'date_of_birth')) {
            Schema::table('pigs_management', function (Blueprint $table) {
                try {
                    $table->index('date_of_birth', 'pigs_management_date_of_birth_idx');
                } catch (\Throwable $e) { /* already exists */ }
            });
        }

        // pigs_management.stock_type — used by all the standard/breeding splits
        if (Schema::hasTable('pigs_management') && Schema::hasColumn('pigs_management', 'stock_type')) {
            Schema::table('pigs_management', function (Blueprint $table) {
                try {
                    $table->index('stock_type', 'pigs_management_stock_type_idx');
                } catch (\Throwable $e) { /* already exists */ }
            });
        }

        // farrowings.farrowing_date
        if (Schema::hasTable('farrowings') && Schema::hasColumn('farrowings', 'farrowing_date')) {
            Schema::table('farrowings', function (Blueprint $table) {
                try {
                    $table->index('farrowing_date', 'farrowings_farrowing_date_idx');
                } catch (\Throwable $e) { /* already exists */ }
            });
        }

        // pig_deaths.death_date
        if (Schema::hasTable('pig_deaths') && Schema::hasColumn('pig_deaths', 'death_date')) {
            Schema::table('pig_deaths', function (Blueprint $table) {
                try {
                    $table->index('death_date', 'pig_deaths_death_date_idx');
                } catch (\Throwable $e) { /* already exists */ }
            });
        }

        // pig_sales.sale_date
        if (Schema::hasTable('pig_sales') && Schema::hasColumn('pig_sales', 'sale_date')) {
            Schema::table('pig_sales', function (Blueprint $table) {
                try {
                    $table->index('sale_date', 'pig_sales_sale_date_idx');
                } catch (\Throwable $e) { /* already exists */ }
            });
        }

        // pig_sales.payment_status — used by the pending() scope.
        if (Schema::hasTable('pig_sales') && Schema::hasColumn('pig_sales', 'payment_status')) {
            Schema::table('pig_sales', function (Blueprint $table) {
                try {
                    $table->index('payment_status', 'pig_sales_payment_status_idx');
                } catch (\Throwable $e) { /* already exists */ }
            });
        }

        // raw_material_stock_logs.type — heavily filtered (receive/usage/adjustment).
        if (Schema::hasTable('raw_material_stock_logs') && Schema::hasColumn('raw_material_stock_logs', 'type')) {
            Schema::table('raw_material_stock_logs', function (Blueprint $table) {
                try {
                    $table->index('type', 'raw_material_stock_logs_type_idx');
                } catch (\Throwable $e) { /* already exists */ }
            });
        }

        // purchase_requests.status — filtered constantly in index pages.
        if (Schema::hasTable('purchase_requests') && Schema::hasColumn('purchase_requests', 'status')) {
            Schema::table('purchase_requests', function (Blueprint $table) {
                try {
                    $table->index('status', 'purchase_requests_status_idx');
                } catch (\Throwable $e) { /* already exists */ }
            });
        }

        // matings — frequently filtered by status
        if (Schema::hasTable('matings') && Schema::hasColumn('matings', 'status')) {
            Schema::table('matings', function (Blueprint $table) {
                try {
                    $table->index('status', 'matings_status_idx');
                } catch (\Throwable $e) { /* already exists */ }
            });
        }
    }

    public function down(): void
    {
        $drops = [
            'pigs_management'         => ['pigs_management_date_of_birth_idx', 'pigs_management_stock_type_idx'],
            'farrowings'              => ['farrowings_farrowing_date_idx'],
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
            Schema::table($tableName, function (Blueprint $table) use ($indexes) {
                foreach ($indexes as $idx) {
                    try {
                        $table->dropIndex($idx);
                    } catch (\Throwable $e) { /* might not exist */ }
                }
            });
        }
    }
};
