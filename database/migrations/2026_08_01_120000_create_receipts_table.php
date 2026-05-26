<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2c — receipts.
 *
 * A receipt represents money received from a customer. Posted to GL
 * IMMEDIATELY on creation (Dr Cash, Cr AR control) — separately from
 * its allocations to specific invoices. Reasons:
 *   - The cash is in the bank now; trial balance must reflect it
 *   - Customer service teams allocate later when they identify which
 *     invoices the receipt covers
 *
 * The 'unallocated_amount' column (computed = amount - SUM(active allocations))
 * captures over-receipts and prepayments. It is the customer's credit
 * balance held against future invoices.
 *
 * Invariant: receipt.amount = receipt.allocated_amount + receipt.unallocated_amount
 *
 *   For audit:
 *     AR sub-ledger total (sum of open invoices balance_due)
 *       = AR control GL balance
 *           + sum of receipts.unallocated_amount across all customers
 *
 *   This identity must hold at all times. The reconciliation command
 *   from this batch verifies it.
 *
 * Reversal:
 *   A posted receipt can be reversed (e.g. bounced cheque, mobile-money
 *   reversal, customer demand for refund). Reversal CASCADES to active
 *   allocations: each allocation reverses, restoring invoice.balance_due,
 *   then the journal batch reverses (Dr AR, Cr Cash).
 *
 *   Backfilled receipts (is_backfilled=true) DO NOT have a journal_batch_id
 *   — the legacy GL already debited Cash. Reversal of a backfilled receipt
 *   reverses only the allocations, not GL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('receipts')) {
            return;
        }

        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();

            $table->string('receipt_number', 30);
            // Format: RCT-YYYY-NNNNNN — assigned by NumberingService

            $table->date('receipt_date');

            // Counterparty (customer paying us).
            $table->unsignedBigInteger('counterparty_id');
            $table->foreign('counterparty_id')
                  ->references('id')
                  ->on('counterparties')
                  ->restrictOnDelete();

            // Currency + (Phase 5+) FX
            $table->string('currency_code', 3)->default('GHS');
            $table->decimal('exchange_rate', 20, 10)->default('1');

            // Amount details.
            $table->decimal('amount',           15, 2);
            $table->decimal('allocated_amount', 15, 2)->default(0);
            // unallocated_amount is implicit: amount - allocated_amount
            // We expose it as a method on the model rather than a stored column
            // to avoid a third source of truth that could drift.

            // Payment instrument (cash, bank transfer, momo, cheque).
            $table->unsignedBigInteger('payment_method_id')->nullable();
            $table->foreign('payment_method_id')
                  ->references('id')
                  ->on('payment_methods')
                  ->nullOnDelete();
            // NULL allowed because legacy backfill cases may have no method recorded.

            // External reference (bank statement, momo TXID, cheque number).
            $table->string('external_reference', 100)->nullable();
            $table->string('reference_type', 30)->nullable();
            // 'momo_transaction_id' | 'cheque' | 'bank_transfer' | 'cash_receipt' | 'other' | 'backfill'

            // State machine.
            $table->string('status', 20)->default('posted');
            // 'draft' | 'posted' | 'reversed'
            // Default 'posted' because the create flow auto-posts. Manual draft
            // workflow not needed in 2c — added in 2d if treasury wants approval flow.

            // Posting linkage.
            $table->unsignedBigInteger('journal_batch_id')->nullable();
            $table->foreign('journal_batch_id')
                  ->references('id')
                  ->on('journal_batches')
                  ->nullOnDelete();

            $table->unsignedBigInteger('reversed_by_journal_batch_id')->nullable();
            $table->foreign('reversed_by_journal_batch_id')
                  ->references('id')
                  ->on('journal_batches')
                  ->nullOnDelete();

            // Backfill marker — see migration 2026_07_01_120200 strategy doc.
            // When true: the journal_batch_id is NULL because the legacy GL
            // entries (from Phase 1c direct posting) already debited Cash.
            // The receipt + its allocations exist purely as sub-ledger detail
            // for the customer ledger view.
            $table->boolean('is_backfilled')->default(false);

            // Audit
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->foreign('created_by_user_id')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();

            $table->unsignedBigInteger('reversed_by_user_id')->nullable();
            $table->foreign('reversed_by_user_id')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();

            // Description
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['organization_id', 'status', 'receipt_date'], 'receipts_status_date_idx');
            $table->index(['counterparty_id', 'status'], 'receipts_counterparty_status_idx');
            $table->index('external_reference', 'receipts_external_ref_idx');
            $table->unique(['organization_id', 'receipt_number'], 'receipts_org_number_unique');
        });

        try {
            $driver = Schema::getConnection()->getDriverName();
            if (in_array($driver, ['mysql', 'pgsql'], true)) {
                \DB::statement("ALTER TABLE receipts ADD CONSTRAINT receipts_status_check CHECK (status IN ('draft', 'posted', 'reversed'))");
            }
        } catch (\Throwable) {
            // CHECK not critical
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
