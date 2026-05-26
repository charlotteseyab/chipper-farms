<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3e — Bank Statements (imports).
 *
 * Two tables:
 *   - bank_statements:       one row per imported file (header)
 *   - bank_statement_lines:  one row per bank transaction (item)
 *
 * Imports are append-only at the line level — once a statement is
 * imported, lines are never edited or deleted (only marked matched/
 * unmatched). If a line was wrong on the bank's side, the bank
 * issues a correction line; we don't retroactively rewrite history.
 *
 * If a re-import is needed (e.g. CSV got mangled), the entire
 * statement can be voided (status = void) and a fresh one imported.
 * Voided statements remain in the table for audit.
 *
 * Statement lines have a `match_status`:
 *   - unmatched           : freshly imported, nothing matched yet
 *   - matched             : exactly one of our payments/receipts matches
 *   - matched_to_journal  : matched to a manual GL journal line (bank
 *                           fees, interest, transfers between bank accts)
 *   - manually_resolved   : ignored with a written reason (e.g. test
 *                           transaction the bank reversed instantly)
 *   - duplicate           : matches more than one of our docs;
 *                           candidate IDs recorded
 *
 * Statement lines reference our side via (matched_type, matched_id).
 * matched_type is one of: 'payment' | 'receipt' | 'journal_line'.
 * Resolved at runtime via a typed accessor in the model — we don't use
 * Laravel's morphTo to avoid global morph map dependency.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('bank_statements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedBigInteger('bank_account_id');

            // Statement metadata.
            $table->string('statement_number', 30)->unique();   // BSTMT-2026-NNNNNN
            $table->string('source_format', 50);                 // 'gcb_csv', 'stanbic_csv', 'generic_csv', 'manual'
            $table->date('period_from');
            $table->date('period_to');
            $table->string('currency_code', 3);

            // Bank-stated balances (carried from the statement file).
            // Required for reconciliation arithmetic.
            $table->decimal('opening_balance_per_bank', 18, 2);
            $table->decimal('closing_balance_per_bank', 18, 2);

            // Counts/totals — computed at import, advisory.
            $table->integer('line_count')->default(0);
            $table->decimal('total_credits', 18, 2)->default(0); // money in (deposits)
            $table->decimal('total_debits',  18, 2)->default(0); // money out (cheques, transfers)

            // The original file (kept for audit).
            $table->string('original_filename')->nullable();
            $table->string('file_disk', 50)->nullable();
            $table->string('file_path')->nullable();
            $table->bigInteger('file_size_bytes')->nullable();
            $table->string('file_sha256', 64)->nullable();       // for duplicate-import detection

            // Lifecycle.
            $table->string('status', 20)->default('imported');
            // imported | partially_reconciled | fully_reconciled | void

            // Linked reconciliation (optional - a statement can be reconciled
            // standalone or as part of a multi-statement reconciliation).
            $table->unsignedBigInteger('bank_reconciliation_id')->nullable();

            // Void tracking.
            $table->timestamp('voided_at')->nullable();
            $table->unsignedBigInteger('voided_by_user_id')->nullable();
            $table->text('void_reason')->nullable();

            $table->text('notes')->nullable();

            // Audit.
            $table->unsignedBigInteger('imported_by_user_id')->nullable();
            $table->timestamp('imported_at')->useCurrent();
            $table->timestamps();

            // FKs.
            $table->foreign('organization_id')
                  ->references('id')->on('organizations')
                  ->cascadeOnUpdate()->nullOnDelete();
            $table->foreign('imported_by_user_id')
                  ->references('id')->on('users')
                  ->cascadeOnUpdate()->nullOnDelete();
            $table->foreign('voided_by_user_id')
                  ->references('id')->on('users')
                  ->cascadeOnUpdate()->nullOnDelete();
            // bank_reconciliation_id FK added after that table exists
            // (next migration). Held nullable here on purpose.

            // Indexes.
            $table->index(['organization_id', 'bank_account_id', 'period_to'],
                'bank_statements_org_account_period_idx');
            $table->index(['organization_id', 'status'],
                'bank_statements_org_status_idx');
            $table->index('file_sha256', 'bank_statements_sha_idx');

            // Prevent re-import of the same file content.
            try {
                $table->unique(['organization_id', 'bank_account_id', 'file_sha256'],
                    'bank_statements_dedup_uq');
            } catch (\Throwable $e) {
                // Already exists — fine.
            }
        });

        Schema::create('bank_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedBigInteger('bank_statement_id');

            // Sequence within the statement file.
            $table->integer('line_number');

            // Bank's view of this transaction.
            $table->date('value_date');                           // when it cleared
            $table->date('transaction_date')->nullable();         // when initiated, if reported
            $table->string('description', 500);                   // bank's narrative
            $table->string('bank_reference', 100)->nullable();    // bank's TXN ID
            $table->string('counterparty_reference', 200)->nullable();
            // ^ what the OTHER party put as ref (often our payment number)

            // Amounts. ONE of debit_amount / credit_amount is non-zero.
            $table->decimal('debit_amount', 18, 2)->default(0);   // money out of our acct
            $table->decimal('credit_amount', 18, 2)->default(0);  // money in
            $table->decimal('running_balance', 18, 2)->nullable();

            // Match state.
            $table->string('match_status', 30)->default('unmatched');
            // unmatched | matched | matched_to_journal | manually_resolved | duplicate

            $table->string('matched_type', 30)->nullable();
            // 'payment' | 'receipt' | 'journal_line' (set when matched)
            $table->unsignedBigInteger('matched_id')->nullable();
            $table->decimal('amount_difference', 18, 2)->default(0);
            $table->json('duplicate_candidate_ids')->nullable();

            // Manual resolution audit.
            $table->text('match_notes')->nullable();
            $table->timestamp('matched_at')->nullable();
            $table->unsignedBigInteger('matched_by_user_id')->nullable();

            // Free-form raw row from CSV — keep it for audit/debugging.
            $table->json('raw_row')->nullable();

            $table->timestamps();

            // FKs.
            $table->foreign('organization_id')
                  ->references('id')->on('organizations')
                  ->cascadeOnUpdate()->nullOnDelete();
            $table->foreign('bank_statement_id')
                  ->references('id')->on('bank_statements')
                  ->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('matched_by_user_id')
                  ->references('id')->on('users')
                  ->cascadeOnUpdate()->nullOnDelete();

            // Indexes — heavy use in matching + reporting.
            $table->index(['organization_id', 'bank_statement_id', 'line_number'],
                'bsl_stmt_line_idx');
            $table->index(['organization_id', 'match_status'],
                'bsl_org_status_idx');
            $table->index(['matched_type', 'matched_id'],
                'bsl_matched_idx');
            $table->index('bank_reference', 'bsl_bank_ref_idx');
            $table->index('counterparty_reference', 'bsl_cp_ref_idx');
            $table->index(['value_date', 'debit_amount'], 'bsl_date_debit_idx');
            $table->index(['value_date', 'credit_amount'], 'bsl_date_credit_idx');
        });

        // Add bank_account FK after creation so environments with stricter
        // FK checks or drifted schemas do not abort the whole migration run.
        try {
            Schema::table('bank_statements', function (Blueprint $table) {
                $table->foreign('bank_account_id')
                    ->references('id')->on('bank_accounts')
                    ->cascadeOnUpdate()->restrictOnDelete();
            });
        } catch (\Throwable $e) {
            // Keep migration non-blocking; app-level validation still enforces
            // valid bank account selection.
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statement_lines');
        Schema::dropIfExists('bank_statements');
    }
};
