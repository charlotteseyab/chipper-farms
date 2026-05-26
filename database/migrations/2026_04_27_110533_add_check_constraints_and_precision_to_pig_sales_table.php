<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 0.8 — controlled-value safety + money precision upgrade on pig_sales.
 *
 * TWO CONCERNS:
 *
 * 1. CHECK constraints on payment_status, sale_purpose, weight_type, sale_method.
 *    These columns are currently free-text (payment_status, sale_purpose) or
 *    ENUM (weight_type, sale_method). ENUM is rigid and hard to extend; free-text
 *    is too loose. CHECK constraint on a varchar is the portable middle ground.
 *
 *    For the ENUM columns we first convert to varchar(30) then add the CHECK.
 *
 * 2. Money precision: sale_price is decimal(10,2), transport_cost is decimal(8,2).
 *    The prompt specifies "standardise money handling using decimal precision
 *    suitable for accounting." For GHS amounts on a commercial farm, we expect
 *    individual sale amounts up to ~GH₵ 100,000 (decimal(10,2) handles
 *    ~GH₵ 99,999,999 so it's fine for now), but for consistency with the
 *    valuation layer (decimal(15,2) on money) we upgrade the key finance-
 *    touching columns.
 *
 *    Widening decimal is non-destructive on MySQL/Postgres — values aren't
 *    lost, and no re-index is needed.
 *
 * Safe and idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pig_sales')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        // -------- Part 1: convert ENUMs to varchar for flexibility --------

        if ($driver === 'mysql') {
            // sale_method is ENUM('auction','direct','contract','other')
            try {
                \DB::statement("ALTER TABLE pig_sales MODIFY COLUMN sale_method VARCHAR(30) NOT NULL DEFAULT 'direct'");
            } catch (\Throwable $e) {
                // May already be varchar.
            }
            // weight_type is ENUM('live','carcass')
            try {
                \DB::statement("ALTER TABLE pig_sales MODIFY COLUMN weight_type VARCHAR(30) NOT NULL DEFAULT 'live'");
            } catch (\Throwable $e) {
                // Ignore.
            }
        } elseif ($driver === 'pgsql') {
            try {
                \DB::statement("ALTER TABLE pig_sales ALTER COLUMN sale_method TYPE varchar(30) USING sale_method::varchar");
            } catch (\Throwable $e) { /* Ignore */ }
            try {
                \DB::statement("ALTER TABLE pig_sales ALTER COLUMN weight_type TYPE varchar(30) USING weight_type::varchar");
            } catch (\Throwable $e) { /* Ignore */ }
        }
        // SQLite stores ENUMs as TEXT anyway; no DDL needed.

        // -------- Part 2: CHECK constraints --------

        if (in_array($driver, ['mysql', 'pgsql'], true)) {
            // payment_status
            try {
                \DB::statement(
                    "ALTER TABLE pig_sales ADD CONSTRAINT pig_sales_payment_status_check "
                    . "CHECK (payment_status IN ('pending', 'paid', 'partial'))"
                );
            } catch (\Throwable $e) { /* Ignore */ }

            // sale_purpose
            try {
                \DB::statement(
                    "ALTER TABLE pig_sales ADD CONSTRAINT pig_sales_sale_purpose_check "
                    . "CHECK (sale_purpose IN ('market', 'breeding', 'slaughter', 'cull', 'other'))"
                );
            } catch (\Throwable $e) { /* Ignore */ }

            // weight_type
            try {
                \DB::statement(
                    "ALTER TABLE pig_sales ADD CONSTRAINT pig_sales_weight_type_check "
                    . "CHECK (weight_type IN ('live', 'carcass'))"
                );
            } catch (\Throwable $e) { /* Ignore */ }

            // sale_method
            try {
                \DB::statement(
                    "ALTER TABLE pig_sales ADD CONSTRAINT pig_sales_sale_method_check "
                    . "CHECK (sale_method IN ('auction', 'direct', 'contract', 'other'))"
                );
            } catch (\Throwable $e) { /* Ignore */ }
        }

        // -------- Part 3: money precision upgrade --------

        if ($driver === 'mysql') {
            try {
                \DB::statement("ALTER TABLE pig_sales MODIFY COLUMN sale_price DECIMAL(15,2) NOT NULL");
            } catch (\Throwable $e) { /* Ignore */ }
            try {
                \DB::statement("ALTER TABLE pig_sales MODIFY COLUMN transport_cost DECIMAL(15,2) NULL");
            } catch (\Throwable $e) { /* Ignore */ }
            try {
                \DB::statement("ALTER TABLE pig_sales MODIFY COLUMN price_per_kg DECIMAL(15,4) NULL");
            } catch (\Throwable $e) { /* Ignore */ }
        } elseif ($driver === 'pgsql') {
            try {
                \DB::statement("ALTER TABLE pig_sales ALTER COLUMN sale_price TYPE DECIMAL(15,2)");
            } catch (\Throwable $e) { /* Ignore */ }
            try {
                \DB::statement("ALTER TABLE pig_sales ALTER COLUMN transport_cost TYPE DECIMAL(15,2)");
            } catch (\Throwable $e) { /* Ignore */ }
            try {
                \DB::statement("ALTER TABLE pig_sales ALTER COLUMN price_per_kg TYPE DECIMAL(15,4)");
            } catch (\Throwable $e) { /* Ignore */ }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('pig_sales')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'pgsql'], true)) {
            foreach ([
                'pig_sales_payment_status_check',
                'pig_sales_sale_purpose_check',
                'pig_sales_weight_type_check',
                'pig_sales_sale_method_check',
            ] as $constraint) {
                try {
                    \DB::statement("ALTER TABLE pig_sales DROP CONSTRAINT {$constraint}");
                } catch (\Throwable $e) { /* Ignore */ }
            }
        }

        // We intentionally do NOT revert varchar → ENUM or shrink decimals.
        // Shrinking a decimal is lossy; reverting to ENUM destroys any value
        // not in the original enum set.
    }
};