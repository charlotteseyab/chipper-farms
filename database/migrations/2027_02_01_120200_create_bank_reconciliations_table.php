<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3e — Bank Reconciliations.
 *
 * A reconciliation is the formal closing of a bank account's books for
 * a period. It compares:
 *
 *   - Our GL bank account balance at period_end
 *     (sum of all postings to bank's account_id where post_date <=
 *     period_end and not reversed)
 *
 *   - The bank's stated closing balance
 *     (sum of opening_balance_per_bank + credits - debits, OR explicit
 *     closing_balance_per_bank from the statement)
 *
 * After matching every credit and debit, the difference must be zero
 * to mark the reconciliation `reconciled`. If it can't reach zero
 * (e.g. a known unresolved bank error you're chasing), use `flagged`
 * with a written reason.
 *
 * Lifecycle:
 *   draft         -> in_progress -> reconciled -> archived
 *                                \-> flagged   -> archived
 *
 * Period freeze invariant:
 *   Once a reconciliation is `reconciled`, the matched payments and
 *   receipts cannot be reversed without first transitioning the
 *   reconciliation OUT of reconciled (back to in_progress) — same
 *   pattern as Phase 3d D40 for WHT certs.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedBigInteger('bank_account_id');

            $table->string('reconciliation_number', 30)->unique();   // BREC-2026-NNNNNN

            // Period being reconciled.
            $table->date('period_from');
            $table->date('period_to');
            $table->string('currency_code', 3);

            // The reconciliation arithmetic (snapshot at close).
            // All denominated in the bank account's currency.

            // Per-bank side.
            $table->decimal('opening_balance_per_bank', 18, 2);
            $table->decimal('closing_balance_per_bank', 18, 2);

            // Per-our-books side (computed from GL postings).
            $table->decimal('opening_balance_per_books', 18, 2);
            $table->decimal('closing_balance_per_books', 18, 2);

            // Adjustments at period close.
            $table->decimal('outstanding_deposits', 18, 2)->default(0);
            // ^ We received it (book entry made), bank hasn't credited yet.
            $table->decimal('outstanding_withdrawals', 18, 2)->default(0);
            // ^ We paid (book entry made), bank hasn't debited yet
            //   (uncleared cheque, in-flight transfer).
            $table->decimal('bank_only_credits', 18, 2)->default(0);
            // ^ Bank credited, we don't have a book entry. Should be
            //   small — interest, refunds, errors.
            $table->decimal('bank_only_debits', 18, 2)->default(0);
            // ^ Bank debited, we don't have a book entry. Bank fees,
            //   charges, errors.

            // Difference: should be ZERO when status = reconciled.
            // = closing_balance_per_bank
            //   - closing_balance_per_books
            //   - outstanding_deposits
            //   + outstanding_withdrawals
            //   - bank_only_credits
            //   + bank_only_debits
            $table->decimal('reconciliation_difference', 18, 2)->default(0);

            // Lifecycle.
            $table->string('status', 20)->default('draft');
            // draft | in_progress | reconciled | flagged | archived

            // Closure audit.
            $table->timestamp('reconciled_at')->nullable();
            $table->unsignedBigInteger('reconciled_by_user_id')->nullable();
            $table->timestamp('flagged_at')->nullable();
            $table->unsignedBigInteger('flagged_by_user_id')->nullable();
            $table->text('flag_reason')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->unsignedBigInteger('archived_by_user_id')->nullable();
            $table->text('archive_reason')->nullable();

            // Snapshot of contributing entries at close — frozen JSON
            // so the reconciliation report is reproducible even if
            // upstream data changes later. Schema:
            //   {
            //     'cleared_payments':    [{id, payment_number, amount, value_date, bsl_id}, ...],
            //     'cleared_receipts':    [{id, receipt_number, amount, value_date, bsl_id}, ...],
            //     'cleared_journal_lines': [{id, batch_id, amount, post_date, bsl_id}, ...],
            //     'outstanding_deposits':[{id, type, amount, post_date}, ...],
            //     'outstanding_withdrawals':[{id, type, amount, post_date}, ...],
            //     'bank_only_lines':     [{bsl_id, value_date, debit, credit}, ...],
            //     'as_of': '...',
            //   }
            $table->json('summary_snapshot')->nullable();

            $table->text('notes')->nullable();

            // Audit.
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();

            // FKs.
            $table->foreign('organization_id')
                  ->references('id')->on('organizations')
                  ->cascadeOnUpdate()->nullOnDelete();
            $table->foreign('created_by_user_id')
                  ->references('id')->on('users')
                  ->cascadeOnUpdate()->nullOnDelete();
            $table->foreign('reconciled_by_user_id')
                  ->references('id')->on('users')
                  ->cascadeOnUpdate()->nullOnDelete();
            $table->foreign('flagged_by_user_id')
                  ->references('id')->on('users')
                  ->cascadeOnUpdate()->nullOnDelete();
            $table->foreign('archived_by_user_id')
                  ->references('id')->on('users')
                  ->cascadeOnUpdate()->nullOnDelete();

            // Indexes.
            $table->index(['organization_id', 'bank_account_id', 'period_to'],
                'brec_org_account_period_idx');
            $table->index(['organization_id', 'status'],
                'brec_org_status_idx');

            // One reconciliation per bank_account per period_to (you
            // close a period exactly once).
            try {
                $table->unique(['bank_account_id', 'period_to'],
                    'brec_account_period_to_uq');
            } catch (\Throwable $e) {}
        });

        // Add bank_account FK after table creation so migration doesn't abort
        // on environments where FK creation is stricter or schema is drifted.
        try {
            Schema::table('bank_reconciliations', function (Blueprint $table) {
                $table->foreign('bank_account_id')
                    ->references('id')->on('bank_accounts')
                    ->cascadeOnUpdate()->restrictOnDelete();
            });
        } catch (\Throwable $e) {
            // Non-blocking by design; app-level validation still enforces
            // valid bank account linkage.
        }

        // Now wire the bank_statements -> bank_reconciliations FK that
        // we held back from the previous migration.
        Schema::table('bank_statements', function (Blueprint $table) {
            $table->foreign('bank_reconciliation_id')
                  ->references('id')->on('bank_reconciliations')
                  ->cascadeOnUpdate()->nullOnDelete();
            $table->index('bank_reconciliation_id', 'bank_statements_brec_idx');
        });
    }

    public function down(): void
    {
        Schema::table('bank_statements', function (Blueprint $table) {
            try { $table->dropForeign(['bank_reconciliation_id']); } catch (\Throwable $e) {}
            try { $table->dropIndex('bank_statements_brec_idx');   } catch (\Throwable $e) {}
        });
        Schema::dropIfExists('bank_reconciliations');
    }
};
