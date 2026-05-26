<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 — extend pig_sales with COGS state.
 *
 * Adds:
 *   - cogs_amount             : decimal(18,4), nullable.
 *                               The allocated COGS at sale time.
 *                               NULL = COGS not yet computed (event in
 *                                      flight or skipped).
 *   - cogs_journal_batch_id   : FK to journal_batches once posted.
 *   - cogs_posting_status     : 'pending'|'posted'|'failed'|'skipped'.
 *   - cogs_posting_error      : capture errors.
 *   - cogs_group_id           : the group from which the pig was sold
 *                               at the time of sale (denormalised
 *                               because pig.group_id may change post-sale
 *                               in edge cases — e.g. correction).
 *
 * Why a separate cogs_journal_batch_id vs reusing journal_batch_id?
 *   The existing journal_batch_id on pig_sales refers to the REVENUE
 *   posting (Dr AR / Cr Revenue), set by PigSaleInvoicingSubscriber.
 *   COGS is a SEPARATE journal batch (Dr COGS / Cr Live Pig Inventory)
 *   so the two postings can succeed or fail independently. Mixing
 *   them in one column would lose this separation.
 *
 * Why skipped status?
 *   If the sold pig has no group_id (was never assigned to a cohort),
 *   there's no pool to draw from. The subscriber writes
 *   cogs_posting_status='skipped' with a clear reason — the sale's
 *   revenue still posts cleanly via the existing flow.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('pig_sales')) {
            return;
        }

        Schema::table('pig_sales', function (Blueprint $table) {
            if (! Schema::hasColumn('pig_sales', 'cogs_amount')) {
                $table->decimal('cogs_amount', 18, 4)->nullable()->after('sale_price');
            }

            if (! Schema::hasColumn('pig_sales', 'cogs_journal_batch_id')) {
                $table->unsignedBigInteger('cogs_journal_batch_id')->nullable()->after('cogs_amount');
                $table->index('cogs_journal_batch_id', 'pig_sales_cogs_journal_idx');
            }

            if (! Schema::hasColumn('pig_sales', 'cogs_posting_status')) {
                $table->string('cogs_posting_status', 20)->default('pending')->after('cogs_journal_batch_id');
                $table->index('cogs_posting_status', 'pig_sales_cogs_status_idx');
            }

            if (! Schema::hasColumn('pig_sales', 'cogs_posting_error')) {
                $table->text('cogs_posting_error')->nullable()->after('cogs_posting_status');
            }

            if (! Schema::hasColumn('pig_sales', 'cogs_group_id')) {
                $table->unsignedBigInteger('cogs_group_id')->nullable()->after('cogs_posting_error');
                $table->foreign('cogs_group_id', 'pig_sales_cogs_group_fk')
                      ->references('id')->on('groups')->nullOnDelete();
                $table->index('cogs_group_id', 'pig_sales_cogs_group_idx');
            }

            // The pig's weight as captured at COGS computation time.
            // Usually mirrors sale_weight, but kept separate for audit
            // (sale_weight is the SALE side; cogs_pig_weight is the
            // COST side; they should match but edge cases exist —
            // e.g. carcass-weight sales where the pre-slaughter live
            // weight is the right COGS basis).
            if (! Schema::hasColumn('pig_sales', 'cogs_pig_weight_kg')) {
                $table->decimal('cogs_pig_weight_kg', 14, 4)->nullable()->after('cogs_group_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pig_sales')) {
            return;
        }

        Schema::table('pig_sales', function (Blueprint $table) {
            try { $table->dropForeign('pig_sales_cogs_group_fk'); } catch (\Throwable) {}
            foreach ([
                'pig_sales_cogs_journal_idx',
                'pig_sales_cogs_status_idx',
                'pig_sales_cogs_group_idx',
            ] as $idx) {
                try { $table->dropIndex($idx); } catch (\Throwable) {}
            }

            foreach ([
                'cogs_amount',
                'cogs_journal_batch_id',
                'cogs_posting_status',
                'cogs_posting_error',
                'cogs_group_id',
                'cogs_pig_weight_kg',
            ] as $col) {
                if (Schema::hasColumn('pig_sales', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
