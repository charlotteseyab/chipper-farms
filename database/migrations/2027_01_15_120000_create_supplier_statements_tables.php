<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3d — Supplier statement reconciliation.
 *
 * Two tables:
 *
 *   supplier_statements
 *     A header capturing one supplier's printed/emailed statement covering
 *     a period. Records:
 *       - statement metadata (date, period, supplier-stated balance)
 *       - our computed balance for the same period (snapshot at creation
 *         + on demand)
 *       - reconciliation status: draft → reconciled (no discrepancies) /
 *         flagged (discrepancies remain)
 *
 *   supplier_statement_lines
 *     One row per line item printed on the supplier's statement. Each line
 *     records what the supplier says (date, ref, amount, kind), and the
 *     match outcome — if it pairs with one of our vendor invoices /
 *     payments, the match_status and matched_id link it.
 *
 * Design notes:
 *
 *   - The supplier's PRINTED statement is authoritative input. We don't
 *     mutate it. Lines are added once (manually or via CSV upload) and
 *     never edited — only re-matched. If the supplier sends a corrected
 *     statement, you create a new statement record.
 *
 *   - Matching is mechanical at first (exact doc-number + amount) and
 *     produces a four-way classification:
 *
 *       matched              line maps cleanly to one of our docs
 *       amount_mismatch      doc found by ref, but amount differs
 *       not_in_our_books     supplier's line, no matching doc on our side
 *       duplicate_match      ref hits multiple of our docs (rare; flagged
 *                            for human review)
 *
 *   - The reverse side — our docs not appearing on the supplier's
 *     statement — is a derived view at reconciliation time, computed
 *     by the service. Not stored.
 *
 *   - 'duplicate_match' is the awkward case where a supplier reuses an
 *     invoice number across years, or our team accidentally created two
 *     VIs against the same supplier ref. Service logs both candidates;
 *     human resolves.
 *
 * Storage:
 *   No file uploads in this batch. CSV import (one row per supplier
 *   statement line) is supported. PDF/scan upload + OCR is Phase 3e
 *   alongside bank-statement import which has the same OCR shape.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('supplier_statements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();

            $table->unsignedBigInteger('counterparty_id');
            $table->foreign('counterparty_id')
                ->references('id')->on('counterparties')
                ->restrictOnDelete();

            // Numbering: SSTMT-YYYY-NNNNNN per organisation.
            $table->string('statement_number', 30);

            // The date PRINTED on the supplier's statement.
            $table->date('statement_date');

            // The PERIOD the statement covers. period_to is typically the
            // statement_date itself; period_from defaults to one month
            // earlier but is user-editable.
            $table->date('period_from');
            $table->date('period_to');

            $table->string('currency_code', 3)->default('GHS');

            // The balance the supplier SAYS we owe them at period_to.
            // Allowed negative for credit-balance scenarios (rare —
            // supplier owes us due to over-payment or returned goods).
            $table->decimal('supplier_balance', 15, 2)->default(0);

            // Snapshot of OUR balance for this supplier at period_to.
            // Computed by the reconciliation service from the AP detail
            // and frozen in this column for audit. Updated on each
            // re-reconciliation.
            $table->decimal('our_balance', 15, 2)->default(0);

            // Difference: supplier_balance - our_balance.
            // > 0  : supplier thinks we owe more than we think
            // < 0  : we think we owe more than supplier thinks
            // == 0 : balances agree (but lines may still mismatch)
            $table->decimal('balance_difference', 15, 2)->default(0);

            // Status flow:
            //   draft       — created, lines being added/imported
            //   in_progress — match attempted, may have unresolved items
            //   reconciled  — every supplier line matched, balances agree
            //   flagged     — unresolvable discrepancies remain (treasurer
            //                 has reviewed and chosen to record the mismatch
            //                 rather than chase it; reasoning in notes)
            //   archived    — historical record, no longer actionable
            $table->string('status', 20)->default('draft');

            // Optional supplier-provided statement reference.
            $table->string('supplier_reference', 100)->nullable();

            $table->text('notes')->nullable();
            $table->text('reconciliation_notes')->nullable();

            // Last full reconciliation pass (re-runs the matcher).
            $table->timestamp('last_reconciled_at')->nullable();
            $table->unsignedBigInteger('last_reconciled_by_user_id')->nullable();
            $table->foreign('last_reconciled_by_user_id')
                ->references('id')->on('users')
                ->nullOnDelete();

            // Audit (creation + status changes).
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->foreign('created_by_user_id')
                ->references('id')->on('users')
                ->nullOnDelete();
            $table->unsignedBigInteger('archived_by_user_id')->nullable();
            $table->foreign('archived_by_user_id')
                ->references('id')->on('users')
                ->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->text('archive_reason')->nullable();

            $table->timestamps();

            $table->index(['organization_id', 'counterparty_id', 'period_to'],
                'sstmt_org_cp_period_idx');
            $table->index(['organization_id', 'status'], 'sstmt_org_status_idx');

            // Statement number unique per organisation.
            try {
                $table->unique(['organization_id', 'statement_number'],
                    'sstmt_org_number_unique');
            } catch (\Throwable $e) {
                // Some MySQL versions choke on partial-unique with NULLs.
                // Skip gracefully; defended at the application layer too.
            }
        });

        Schema::create('supplier_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_statement_id');
            $table->foreign('supplier_statement_id')
                ->references('id')->on('supplier_statements')
                ->cascadeOnDelete();

            // Position on the printed statement (display order).
            $table->unsignedSmallInteger('line_number')->default(0);

            // What the supplier's line says.
            $table->date('document_date');

            // Document type as recorded on supplier's statement.
            // 'invoice'   : supplier invoiced us
            // 'credit'    : supplier credited us (return / discount)
            // 'payment'   : supplier acknowledges receiving our payment
            // 'adjustment': any other adjustment (interest, manual)
            $table->string('document_kind', 20);

            // The reference printed on supplier's statement.
            // For an 'invoice' line: their invoice number (matches our
            //   vendor_invoices.supplier_invoice_number)
            // For a 'payment' line: typically a cheque or transfer ref
            //   (matches our payments.external_reference)
            $table->string('document_reference', 100);

            $table->decimal('amount', 15, 2);

            $table->string('description', 255)->nullable();

            // Match outcome.
            //   unmatched           — initial state, before reconcile pass
            //   matched             — paired with one of our docs
            //   amount_mismatch     — found by ref, amounts differ
            //   not_in_our_books    — no doc with this ref on our side
            //   duplicate_match     — multiple of our docs match the ref
            //   manually_resolved   — human marked OK (e.g. amount mismatch
            //                          accepted as forex difference)
            $table->string('match_status', 25)->default('unmatched');

            // Polymorphic link to our matched document.
            //   matched_type: 'vendor_invoice' or 'payment'
            //   matched_id  : id of that row
            $table->string('matched_type', 30)->nullable();
            $table->unsignedBigInteger('matched_id')->nullable();

            // Computed difference when amount_mismatch:
            //   line.amount - matched_doc.amount
            // (signed; non-zero when status is amount_mismatch).
            $table->decimal('amount_difference', 15, 2)->nullable();

            // For 'duplicate_match', candidate IDs as JSON array of ints.
            $table->json('duplicate_candidate_ids')->nullable();

            // Resolution audit.
            $table->text('match_notes')->nullable();
            $table->unsignedBigInteger('resolved_by_user_id')->nullable();
            $table->foreign('resolved_by_user_id')
                ->references('id')->on('users')
                ->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->index(['supplier_statement_id', 'match_status'],
                'sstmt_lines_status_idx');
            $table->index(['matched_type', 'matched_id'], 'sstmt_lines_match_idx');
            $table->index(['document_reference'], 'sstmt_lines_ref_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_statement_lines');
        Schema::dropIfExists('supplier_statements');
    }
};
