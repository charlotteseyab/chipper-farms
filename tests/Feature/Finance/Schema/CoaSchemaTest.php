<?php

namespace Tests\Feature\Finance\Schema;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase 1a — verifies the COA + journal schema migrated correctly.
 *
 * These are structural tests, not behaviour tests. They protect against
 * accidental schema drift in future migrations.
 */
class CoaSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_chart_of_accounts_table_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('chart_of_accounts'));

        foreach ([
            'id', 'organization_id', 'code', 'name', 'description',
            'account_type', 'account_subtype', 'parent_id', 'currency_code',
            'is_control', 'is_active', 'is_system', 'allow_manual_journal',
            'display_order', 'deleted_at', 'created_at', 'updated_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('chart_of_accounts', $column),
                "chart_of_accounts is missing column: {$column}"
            );
        }
    }

    public function test_journal_batches_table_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('journal_batches'));

        foreach ([
            'id', 'organization_id', 'batch_number', 'batch_type',
            'posting_date', 'fiscal_period_id', 'status', 'description',
            'source_module', 'source_type', 'source_id', 'domain_event_id',
            'reverses_batch_id', 'reversed_by_batch_id',
            'proposed_by_user_id', 'proposed_at',
            'posted_by_user_id', 'posted_at',
            'reversed_by_user_id', 'reversed_at', 'reversal_reason',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('journal_batches', $column),
                "journal_batches is missing column: {$column}"
            );
        }
    }

    public function test_journals_table_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('journals'));

        foreach ([
            'id', 'organization_id', 'journal_batch_id', 'journal_number',
            'journal_type', 'posting_date', 'fiscal_period_id', 'reference',
            'description', 'currency_code', 'exchange_rate',
            'total_debit', 'total_credit', 'total_debit_tc', 'total_credit_tc',
            'status', 'source_type', 'source_id', 'reverses_journal_id',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('journals', $column),
                "journals is missing column: {$column}"
            );
        }
    }

    public function test_journal_lines_table_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('journal_lines'));

        foreach ([
            'id', 'organization_id', 'journal_id', 'line_number', 'account_id',
            'debit', 'credit', 'debit_tc', 'credit_tc',
            'currency_code', 'exchange_rate', 'description',
            'cost_centre_id', 'sales_channel_id', 'counterparty_id',
            'tax_code_id', 'payment_method_id',
            'structure_id', 'room_id', 'group_id',
            'source_type', 'source_id', 'reference',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('journal_lines', $column),
                "journal_lines is missing column: {$column}"
            );
        }
    }

    public function test_journal_postings_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('journal_postings'));

        foreach ([
            'id', 'organization_id', 'idempotency_key', 'journal_batch_id',
            'domain_event_id', 'posting_purpose', 'subscriber_class',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('journal_postings', $column),
                "journal_postings is missing column: {$column}"
            );
        }
    }

    public function test_period_lock_dates_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('period_lock_dates'));

        foreach ([
            'id', 'organization_id', 'lock_date', 'is_hard', 'is_active',
            'reason', 'locked_by_user_id', 'locked_at', 'superseded_by_id',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('period_lock_dates', $column),
                "period_lock_dates is missing column: {$column}"
            );
        }
    }

    public function test_fiscal_periods_has_phase_1a_audit_columns(): void
    {
        // Hardening migration adds these to the existing fiscal_periods table.
        foreach ([
            'first_journal_posted_at', 'last_journal_posted_at',
            'posting_count', 'reopened_at', 'reopened_by_user_id',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('fiscal_periods', $column),
                "fiscal_periods is missing Phase 1a column: {$column}"
            );
        }
    }
}
