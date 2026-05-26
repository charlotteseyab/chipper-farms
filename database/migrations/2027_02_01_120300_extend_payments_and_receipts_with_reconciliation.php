<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3e — Wire reconciliation to existing payment/receipt rows.
 *
 * Phase 3c already added reconciled_at + reconciled_by_user_id to
 * payments. This migration:
 *
 *   1. Adds bank_statement_line_id + bank_reconciliation_id FKs to
 *      payments (for forward-linking from a payment back to the
 *      bank statement line that cleared it, and the reconciliation
 *      that closed it).
 *
 *   2. Adds the same trio (reconciled_at, reconciled_by_user_id,
 *      bank_statement_line_id, bank_reconciliation_id) to receipts.
 *
 *   3. Adds bank_statement_line_id + bank_reconciliation_id to
 *      journal_batches — for the rarer case where a bank line is
 *      cleared by a manual GL journal (bank fees, interest, internal
 *      transfers).
 *
 * All columns are nullable. No backfill required — existing rows just
 * stay null until they're reconciled.
 *
 * Why we forward-link from payment.bank_statement_line_id and ALSO
 * have bank_statement_lines.matched_id pointing back at the payment:
 *   - bank_statement_lines.matched_id is the source of truth (set by
 *     the matching engine, can be unmatched and reset).
 *   - payment.bank_statement_line_id is the denormalised forward
 *     pointer for fast lookups ("which bank line cleared this?")
 *     and for enforcing the period-freeze invariant. Kept consistent
 *     by the BankReconciliationService when matches change.
 */
return new class extends Migration {
    public function up(): void
    {
        // Payments: forward-link to bank_statement_line + reconciliation.
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'bank_statement_line_id')) {
                $table->unsignedBigInteger('bank_statement_line_id')->nullable()
                      ->after('reconciled_by_user_id');
                $table->foreign('bank_statement_line_id')
                      ->references('id')->on('bank_statement_lines')
                      ->cascadeOnUpdate()->nullOnDelete();
                $table->index('bank_statement_line_id', 'payments_bsl_idx');
            }
            if (! Schema::hasColumn('payments', 'bank_reconciliation_id')) {
                $table->unsignedBigInteger('bank_reconciliation_id')->nullable()
                      ->after('bank_statement_line_id');
                $table->foreign('bank_reconciliation_id')
                      ->references('id')->on('bank_reconciliations')
                      ->cascadeOnUpdate()->nullOnDelete();
                $table->index('bank_reconciliation_id', 'payments_brec_idx');
            }
        });

        // Receipts: full reconciliation column set.
        Schema::table('receipts', function (Blueprint $table) {
            if (! Schema::hasColumn('receipts', 'reconciled_at')) {
                $table->timestamp('reconciled_at')->nullable();
            }
            if (! Schema::hasColumn('receipts', 'reconciled_by_user_id')) {
                $table->unsignedBigInteger('reconciled_by_user_id')->nullable();
                $table->foreign('reconciled_by_user_id')
                      ->references('id')->on('users')
                      ->cascadeOnUpdate()->nullOnDelete();
            }
            if (! Schema::hasColumn('receipts', 'bank_statement_line_id')) {
                $table->unsignedBigInteger('bank_statement_line_id')->nullable();
                $table->foreign('bank_statement_line_id')
                      ->references('id')->on('bank_statement_lines')
                      ->cascadeOnUpdate()->nullOnDelete();
                $table->index('bank_statement_line_id', 'receipts_bsl_idx');
            }
            if (! Schema::hasColumn('receipts', 'bank_reconciliation_id')) {
                $table->unsignedBigInteger('bank_reconciliation_id')->nullable();
                $table->foreign('bank_reconciliation_id')
                      ->references('id')->on('bank_reconciliations')
                      ->cascadeOnUpdate()->nullOnDelete();
                $table->index('bank_reconciliation_id', 'receipts_brec_idx');
            }
            // Composite index for ageing-style "show me unreconciled receipts"
            try {
                $table->index(['organization_id', 'reconciled_at'],
                    'receipts_org_recon_idx');
            } catch (\Throwable $e) {}
        });

        // Journal batches can also be matched to a bank line (manual
        // entries: bank fees, interest, transfers). We tag the batch
        // not the individual posting because that's the granularity at
        // which the user is matching.
        if (Schema::hasTable('journal_batches')) {
            Schema::table('journal_batches', function (Blueprint $table) {
                if (! Schema::hasColumn('journal_batches', 'bank_statement_line_id')) {
                    $table->unsignedBigInteger('bank_statement_line_id')->nullable();
                    $table->foreign('bank_statement_line_id')
                          ->references('id')->on('bank_statement_lines')
                          ->cascadeOnUpdate()->nullOnDelete();
                    $table->index('bank_statement_line_id', 'jb_bsl_idx');
                }
                if (! Schema::hasColumn('journal_batches', 'bank_reconciliation_id')) {
                    $table->unsignedBigInteger('bank_reconciliation_id')->nullable();
                    $table->foreign('bank_reconciliation_id')
                          ->references('id')->on('bank_reconciliations')
                          ->cascadeOnUpdate()->nullOnDelete();
                    $table->index('bank_reconciliation_id', 'jb_brec_idx');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'bank_reconciliation_id')) {
                try { $table->dropForeign(['bank_reconciliation_id']); } catch (\Throwable $e) {}
                try { $table->dropIndex('payments_brec_idx');           } catch (\Throwable $e) {}
                $table->dropColumn('bank_reconciliation_id');
            }
            if (Schema::hasColumn('payments', 'bank_statement_line_id')) {
                try { $table->dropForeign(['bank_statement_line_id']); } catch (\Throwable $e) {}
                try { $table->dropIndex('payments_bsl_idx');            } catch (\Throwable $e) {}
                $table->dropColumn('bank_statement_line_id');
            }
        });

        Schema::table('receipts', function (Blueprint $table) {
            try { $table->dropIndex('receipts_org_recon_idx'); } catch (\Throwable $e) {}

            foreach (['bank_reconciliation_id', 'bank_statement_line_id', 'reconciled_by_user_id'] as $col) {
                if (Schema::hasColumn('receipts', $col)) {
                    try { $table->dropForeign([$col]); } catch (\Throwable $e) {}
                    if ($col === 'bank_statement_line_id') {
                        try { $table->dropIndex('receipts_bsl_idx');  } catch (\Throwable $e) {}
                    }
                    if ($col === 'bank_reconciliation_id') {
                        try { $table->dropIndex('receipts_brec_idx'); } catch (\Throwable $e) {}
                    }
                    $table->dropColumn($col);
                }
            }

            // reconciled_at is a plain timestamp column (no FK).
            if (Schema::hasColumn('receipts', 'reconciled_at')) {
                $table->dropColumn('reconciled_at');
            }
        });

        if (Schema::hasTable('journal_batches')) {
            Schema::table('journal_batches', function (Blueprint $table) {
                foreach (['bank_reconciliation_id', 'bank_statement_line_id'] as $col) {
                    if (Schema::hasColumn('journal_batches', $col)) {
                        try { $table->dropForeign([$col]); } catch (\Throwable $e) {}
                        if ($col === 'bank_statement_line_id') {
                            try { $table->dropIndex('jb_bsl_idx');  } catch (\Throwable $e) {}
                        }
                        if ($col === 'bank_reconciliation_id') {
                            try { $table->dropIndex('jb_brec_idx'); } catch (\Throwable $e) {}
                        }
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
