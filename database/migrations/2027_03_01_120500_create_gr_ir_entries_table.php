<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 — GR/IR accrual entries.
 *
 * Operational mirror of the 2050 GR/IR liability account.
 *
 * Why a separate table when the GL already has the truth?
 *
 *   The GL records "X amount credited to 2050 from supplier S at date D";
 *   it does NOT (at the GL line level) keep a stateful "this accrual was
 *   cleared by VI #Y" relationship. The clearing is implicit (debits +
 *   credits net to zero per supplier over time).
 *
 *   For an ageing report and for matching receipts to invoices we need
 *   per-receipt, per-line state: OUTSTANDING when the GR/IR debit-side
 *   has not been cleared by an AP invoice; CLEARED when it has.
 *
 *   This table is the bookkeeping mirror for that state machine. It is
 *   written by the receipt subscriber (status='outstanding') and
 *   updated by the VI posting subscriber (status='cleared',
 *   cleared_at, cleared_by_vendor_invoice_line_id) when the AP invoice
 *   line for the same receipt posts. The GL stays the source of truth;
 *   this table is operational.
 *
 *   This avoids the alternative — running heuristic queries over journal
 *   lines to figure out what's open. That works but is fragile against
 *   the introduction of new event types and expensive at scale.
 *
 * Lifecycle:
 *   1. Receipt subscriber posts Dr Inventory / Cr GR/IR for PR-linked
 *      receipt. Inserts a gr_ir_entries row with status='outstanding',
 *      receipt_amount = the GR/IR credit at receipt time.
 *   2. VI posting subscriber finds matching gr_ir_entries by
 *      (purchase_request_item_id, status='outstanding'). Posts
 *      Dr GR/IR / Cr AP. Updates the entry to status='cleared',
 *      sets cleared_at, cleared_by_vendor_invoice_line_id, and
 *      cleared_amount.
 *   3. Price variance: if cleared_amount != receipt_amount, the
 *      difference is recorded in variance_amount and the GL posts the
 *      delta to PPV (5310). Status is still 'cleared' — the variance
 *      lives in the entry as an audit trail.
 *
 *   For non-PR direct receipts, NO gr_ir_entries row is written. The
 *   subscriber posts to the Inventory Suspense (2055) account instead,
 *   handled separately.
 *
 * The status column has its own narrow value set:
 *   'outstanding' : created, waiting for AP clearing
 *   'cleared'     : AP invoice posted, GR/IR cleared
 *   'reversed'    : the underlying receipt was reversed; entry inactive
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('gr_ir_entries')) {
            return;
        }

        Schema::create('gr_ir_entries', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')
                  ->references('id')
                  ->on('organizations')
                  ->restrictOnDelete();

            // The source stock log (receipt) that created this entry.
            $table->unsignedBigInteger('stock_log_id');
            $table->foreign('stock_log_id', 'gr_ir_stock_log_fk')
                  ->references('id')
                  ->on('raw_material_stock_logs')
                  ->cascadeOnDelete();

            // The PR item this receipt fulfils — required, because a
            // gr_ir_entries row only exists for PR-linked receipts.
            $table->unsignedBigInteger('purchase_request_item_id');
            $table->foreign('purchase_request_item_id', 'gr_ir_pr_item_fk')
                  ->references('id')
                  ->on('purchase_request_items')
                  ->restrictOnDelete();

            // Supplier (denormalised from the PR for query convenience).
            $table->unsignedBigInteger('supplier_id');
            $table->foreign('supplier_id', 'gr_ir_supplier_fk')
                  ->references('id')
                  ->on('counterparties')
                  ->restrictOnDelete();

            // Raw material (denormalised — receipts can't change item).
            $table->unsignedBigInteger('raw_material_id');
            $table->foreign('raw_material_id', 'gr_ir_raw_material_fk')
                  ->references('id')
                  ->on('raw_materials')
                  ->restrictOnDelete();

            // Quantity received (positive). The signed delta is in the
            // source stock log.
            $table->decimal('quantity', 18, 4);

            // The GR/IR credit at receipt time = quantity × receipt_unit_cost.
            // Decimal(18, 4) per the value precision used in the ledger.
            $table->decimal('receipt_unit_cost', 18, 6);
            $table->decimal('receipt_amount',    18, 4);

            // Status enum.
            $table->string('status', 20)->default('outstanding');
            // 'outstanding' | 'cleared' | 'reversed'

            // The receipt event date — used in ageing reports.
            $table->timestamp('occurred_at');

            // The journal_batch_id of the receipt posting (Dr Inventory /
            // Cr GR/IR). Filled by the receipt subscriber after posting.
            $table->unsignedBigInteger('receipt_journal_batch_id')->nullable();

            // The journal_batch_id of the clearing posting (Dr GR/IR /
            // Cr AP). Filled by the VI posting subscriber.
            $table->unsignedBigInteger('clearing_journal_batch_id')->nullable();

            // The VI line that cleared this entry. Nullable until cleared.
            $table->unsignedBigInteger('cleared_by_vendor_invoice_line_id')->nullable();
            $table->foreign('cleared_by_vendor_invoice_line_id', 'gr_ir_clearing_vi_line_fk')
                  ->references('id')
                  ->on('vendor_invoice_lines')
                  ->nullOnDelete();

            // Cleared timestamp + amount + signed variance.
            $table->timestamp('cleared_at')->nullable();
            $table->decimal('cleared_amount', 18, 4)->nullable();
            $table->decimal('variance_amount', 18, 4)->nullable();
            // variance_amount = cleared_amount - receipt_amount
            // Positive variance = supplier billed MORE than receipt cost (unfavourable)
            // Negative variance = supplier billed LESS (favourable)

            $table->timestamps();

            // One GR/IR entry per receipt. The stock log is the source of
            // truth; one entry exists exactly when there's a PR-linked
            // GR/IR-postable receipt.
            $table->unique('stock_log_id', 'gr_ir_entries_stock_log_unique');

            // Read paths for ageing report.
            $table->index(['organization_id', 'status'],            'gr_ir_entries_status_idx');
            $table->index(['supplier_id', 'status'],                'gr_ir_entries_supplier_status_idx');
            $table->index(['purchase_request_item_id', 'status'],   'gr_ir_entries_pr_item_status_idx');
            $table->index('occurred_at',                            'gr_ir_entries_aged_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gr_ir_entries');
    }
};
