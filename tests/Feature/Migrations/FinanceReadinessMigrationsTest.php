<?php

namespace Tests\Feature\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase 0.8 — migration verification.
 *
 * Asserts that every new table exists and every new column is present on
 * the source tables. Guards against an incomplete migration run on a
 * production box (which Laravel normally handles by throwing, but we want
 * an explicit test as a deploy gate).
 *
 * Also verifies the pig_deaths enum-fix migration broadened the allowed
 * values: a 'stillbirth' insert must succeed, which was previously rejected
 * by the ENUM before Phase 0.8.
 */
class FinanceReadinessMigrationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_new_reference_tables_exist(): void
    {
        $tables = [
            'document_sequences',
            'counterparties',
            'payment_methods',
            'tax_codes',
            'fiscal_years',
            'fiscal_periods',
            'cost_centres',
            'sales_channels',
            'accounting_dimension_links',
            'attachments',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(
                Schema::hasTable($table),
                "Phase 0.8 reference table missing: {$table}"
            );
        }
    }

    public function test_pig_sales_has_finance_readiness_columns(): void
    {
        $columns = [
            'organization_id', 'document_number', 'is_posted', 'posted_at',
            'customer_id', 'payment_method_id', 'cost_centre_id',
            'sales_channel_id', 'tax_code_id', 'legacy_source_id',
        ];

        foreach ($columns as $col) {
            $this->assertTrue(
                Schema::hasColumn('pig_sales', $col),
                "pig_sales missing finance-readiness column: {$col}"
            );
        }
    }

    public function test_purchase_requests_has_finance_readiness_columns(): void
    {
        $columns = [
            'organization_id', 'document_number', 'is_posted', 'posted_at',
            'supplier_id', 'invoice_number', 'invoice_date', 'tax_code_id',
            'withholding_rate', 'legacy_source_id',
        ];

        foreach ($columns as $col) {
            $this->assertTrue(
                Schema::hasColumn('purchase_requests', $col),
                "purchase_requests missing finance-readiness column: {$col}"
            );
        }
    }

    public function test_raw_material_stock_logs_has_finance_readiness_columns(): void
    {
        $columns = [
            'organization_id', 'document_number', 'is_posted', 'posted_at',
            'supplier_id', 'cost_centre_id', 'legacy_source_id',
        ];

        foreach ($columns as $col) {
            $this->assertTrue(
                Schema::hasColumn('raw_material_stock_logs', $col),
                "raw_material_stock_logs missing finance-readiness column: {$col}"
            );
        }
    }

    public function test_feed_batches_has_finance_readiness_columns(): void
    {
        $columns = [
            'organization_id', 'document_number', 'is_posted', 'posted_at',
            'cost_centre_id', 'legacy_source_id',
        ];

        foreach ($columns as $col) {
            $this->assertTrue(
                Schema::hasColumn('feed_batches', $col),
                "feed_batches missing finance-readiness column: {$col}"
            );
        }
    }

    public function test_pig_deaths_has_finance_readiness_columns(): void
    {
        $columns = [
            'organization_id', 'document_number', 'is_posted', 'posted_at',
            'cost_centre_id', 'legacy_source_id',
        ];

        foreach ($columns as $col) {
            $this->assertTrue(
                Schema::hasColumn('pig_deaths', $col),
                "pig_deaths missing finance-readiness column: {$col}"
            );
        }
    }

    public function test_pig_deaths_accepts_stillbirth_after_enum_fix(): void
    {
        // Prior to the enum-broadening migration, this insert would fail at
        // the DB layer on MySQL with STRICT mode because 'stillbirth' wasn't
        // in the original ENUM. The migration changed the column to varchar
        // with a CHECK constraint listing all valid causes.

        // SQLite doesn't enforce ENUM rigidly, so this test validates the
        // application layer treats 'stillbirth' as a valid value and doesn't
        // choke downstream.

        // Create the minimum prerequisites to insert a pig_death row.
        $pigId = \DB::table('pigs_management')->insertGetId([
            'ear_tag_number' => 'T-STILLBIRTH',
            'code' => 'PIG-STILLBIRTH',
            'breed' => 'LW',
            'gender' => 'F',
            'stock_type' => 'standard',
            'date_of_birth' => now()->subDays(10)->toDateString(),
            'age_in_days' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $userId = \DB::table('users')->insertGetId([
            'name' => 'Tester',
            'email' => 'test_' . uniqid() . '@example.com',
            'password' => bcrypt('secret'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $deathId = \DB::table('pig_deaths')->insertGetId([
            'pig_id' => $pigId,
            'death_date' => now()->toDateString(),
            'death_cause' => 'stillbirth',
            'disposal_method' => 'burial',
            'recorded_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertGreaterThan(0, $deathId);

        $row = \DB::table('pig_deaths')->where('id', $deathId)->first();
        $this->assertSame('stillbirth', $row->death_cause);
    }
}