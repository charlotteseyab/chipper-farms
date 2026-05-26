<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1a — harden fiscal_periods for active posting enforcement.
 *
 * The fiscal_periods table was created in Batch D (Phase 0.8) with
 * open/closed/locked status. It already has closed_at, closed_by_user_id,
 * locked_at, locked_by_user_id audit columns.
 *
 * What's added here:
 *   - first_journal_posted_at         When the first journal landed in this period.
 *                                     Used by close logic to detect "empty period".
 *   - last_journal_posted_at          Last posting timestamp. For monitoring.
 *   - posting_count                   Count of journals posted to this period.
 *                                     Used by close logic for "are there pending
 *                                     postings I should know about" checks.
 *   - reopened_at, reopened_by_user_id Audit trail for period reopening.
 *
 * These are non-critical for Phase 1a's posting flow — the existing status
 * column is sufficient — but they make Phase 1d's close/reopen UI defensible
 * without another migration.
 *
 * Safe and idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fiscal_periods')) {
            return;
        }

        Schema::table('fiscal_periods', function (Blueprint $table) {
            if (! Schema::hasColumn('fiscal_periods', 'first_journal_posted_at')) {
                $table->timestamp('first_journal_posted_at')->nullable();
            }

            if (! Schema::hasColumn('fiscal_periods', 'last_journal_posted_at')) {
                $table->timestamp('last_journal_posted_at')->nullable();
            }

            if (! Schema::hasColumn('fiscal_periods', 'posting_count')) {
                $table->unsignedInteger('posting_count')->default(0);
            }

            if (! Schema::hasColumn('fiscal_periods', 'reopened_at')) {
                $table->timestamp('reopened_at')->nullable();
            }

            if (! Schema::hasColumn('fiscal_periods', 'reopened_by_user_id')) {
                $table->unsignedBigInteger('reopened_by_user_id')->nullable();
                $table->foreign('reopened_by_user_id', 'fiscal_periods_reopened_by_fk')
                      ->references('id')
                      ->on('users')
                      ->nullOnDelete();
            }

            // Helpful index for close-workflow queries.
            try {
                $table->index(['status', 'last_journal_posted_at'], 'fiscal_periods_close_workflow_idx');
            } catch (\Throwable $e) { /* may already exist */ }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('fiscal_periods')) {
            return;
        }

        Schema::table('fiscal_periods', function (Blueprint $table) {
            try { $table->dropIndex('fiscal_periods_close_workflow_idx'); } catch (\Throwable $e) { /* */ }

            if (Schema::hasColumn('fiscal_periods', 'reopened_by_user_id')) {
                try { $table->dropForeign('fiscal_periods_reopened_by_fk'); } catch (\Throwable $e) { /* */ }
                $table->dropColumn('reopened_by_user_id');
            }

            foreach ([
                'first_journal_posted_at',
                'last_journal_posted_at',
                'posting_count',
                'reopened_at',
            ] as $col) {
                if (Schema::hasColumn('fiscal_periods', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
