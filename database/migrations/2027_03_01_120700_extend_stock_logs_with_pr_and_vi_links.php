<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 — extend raw_material_stock_logs with PR linkage.
 *
 * Adds the missing FKs that the three-way matching service needed but
 * never had:
 *   - purchase_request_item_id    : which PR line this receipt fulfils
 *   - vendor_invoice_line_id      : the VI line that cleared this receipt
 *
 * Plus an idempotency identity column for the inventory subscriber:
 *   - posting_status              : 'pending'|'posted'|'failed'|'skipped'
 *
 * Why posting_status as well as is_posted?
 *   The existing Phase 0.8 is_posted / posted_at columns assume binary
 *   state. Phase 4's GR/IR posting can fail (e.g. missing supplier
 *   mapping) and needs a distinct 'failed' status with retry semantics,
 *   plus 'skipped' for non-PR receipts that intentionally don't post
 *   to GR/IR.
 *
 *   is_posted remains as a denormalised convenience boolean — true when
 *   posting_status='posted'. Kept for backward compat with reports
 *   already reading it.
 *
 *   New code reads posting_status (richer); legacy reports read is_posted
 *   (consistent with previous behaviour). Both are kept in sync by the
 *   subscriber.
 *
 * Multi-tenant + audit:
 *   No new tenant columns — organization_id already exists from Phase 0.8.
 *   No backfill needed: existing stock logs (if any) have NULL
 *   purchase_request_item_id and NULL vendor_invoice_line_id, which is
 *   the correct interpretation ("legacy direct receipts, never linked").
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('raw_material_stock_logs')) {
            return;
        }

        Schema::table('raw_material_stock_logs', function (Blueprint $table) {
            // The PR line this receipt fulfils. Nullable: direct
            // (non-PR) receipts have NULL here.
            if (! Schema::hasColumn('raw_material_stock_logs', 'purchase_request_item_id')) {
                $table->unsignedBigInteger('purchase_request_item_id')
                      ->nullable()
                      ->after('supplier_id');

                $table->foreign('purchase_request_item_id', 'rmsl_pr_item_fk')
                      ->references('id')
                      ->on('purchase_request_items')
                      ->nullOnDelete();

                $table->index('purchase_request_item_id', 'rmsl_pr_item_idx');
            }

            // The VI line that cleared the GR/IR for this receipt.
            // Nullable: not all receipts get invoiced (write-offs,
            // free samples, etc.); even invoiced ones have NULL until
            // the AP invoice posts.
            if (! Schema::hasColumn('raw_material_stock_logs', 'vendor_invoice_line_id')) {
                $table->unsignedBigInteger('vendor_invoice_line_id')
                      ->nullable()
                      ->after('purchase_request_item_id');

                $table->foreign('vendor_invoice_line_id', 'rmsl_vi_line_fk')
                      ->references('id')
                      ->on('vendor_invoice_lines')
                      ->nullOnDelete();

                $table->index('vendor_invoice_line_id', 'rmsl_vi_line_idx');
            }

            // Posting status — richer state machine than is_posted alone.
            if (! Schema::hasColumn('raw_material_stock_logs', 'posting_status')) {
                $table->string('posting_status', 20)
                      ->default('pending')
                      ->after('is_posted');
                $table->index('posting_status', 'rmsl_posting_status_idx');
            }

            // Error capture for failed postings.
            if (! Schema::hasColumn('raw_material_stock_logs', 'posting_error')) {
                $table->text('posting_error')->nullable()->after('posting_status');
            }

            // The journal_batch_id of the GR/IR posting (or the
            // Inventory Suspense posting for non-PR receipts).
            if (! Schema::hasColumn('raw_material_stock_logs', 'journal_batch_id')) {
                $table->unsignedBigInteger('journal_batch_id')->nullable()->after('posted_at');
                $table->index('journal_batch_id', 'rmsl_journal_batch_idx');
            }

            // The receipt event sometimes needs a reason note in addition
            // to the existing free-form 'notes' column — specifically for
            // direct (non-PR) receipts the user must capture WHY there is
            // no PR (donation? sample? returns? bookkeeping correction?).
            // Required by the new ReceiveRawMaterialService validation.
            if (! Schema::hasColumn('raw_material_stock_logs', 'receipt_reason')) {
                $table->string('receipt_reason', 100)->nullable()->after('notes');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('raw_material_stock_logs')) {
            return;
        }

        Schema::table('raw_material_stock_logs', function (Blueprint $table) {
            try { $table->dropForeign('rmsl_pr_item_fk'); } catch (\Throwable) {}
            try { $table->dropForeign('rmsl_vi_line_fk'); } catch (\Throwable) {}

            foreach ([
                'rmsl_pr_item_idx',
                'rmsl_vi_line_idx',
                'rmsl_posting_status_idx',
                'rmsl_journal_batch_idx',
            ] as $idx) {
                try { $table->dropIndex($idx); } catch (\Throwable) {}
            }

            foreach ([
                'purchase_request_item_id',
                'vendor_invoice_line_id',
                'posting_status',
                'posting_error',
                'journal_batch_id',
                'receipt_reason',
            ] as $col) {
                if (Schema::hasColumn('raw_material_stock_logs', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
