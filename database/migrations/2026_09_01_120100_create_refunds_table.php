<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2d — refunds.
 *
 * A refund is the inverse of a receipt: money paid back to a customer.
 * Always posts: Dr AR control (or unallocated receipt-side liability)
 *               Cr Cash (or whichever cash account paid the customer)
 *
 * Decision 23: refunds get their own table, NOT modelled as negative
 * receipts. Reasons:
 *   - "Receipts" semantically = "money received". A negative receipt
 *     breaks every aggregate query.
 *   - Refunds need their own approval flow (treasury authorises; customer
 *     service does not).
 *   - Refunds need to link to the originating receipt for audit (which
 *     receipt's overpayment is being returned).
 *   - A refund is a NEW GL posting, not a reversal of the original receipt
 *     (the original receipt was correct — the customer DID pay).
 *
 * Workflow:
 *   draft → posted → reversed
 *   Refund of a refund (rare — typically a clerical mistake) reverses
 *   the GL by posting Dr Cash / Cr AR control.
 *
 * Source linkage:
 *   refund.source_receipt_id is OPTIONAL — refunds can also be standalone
 *   (e.g. compensation, goodwill payment that doesn't trace to a specific
 *   receipt). When set, the audit trail shows which over-receipt was returned.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('refunds')) {
            return;
        }

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();

            $table->string('refund_number', 30);
            // Format: REF-YYYY-NNNNNN — assigned by NumberingService

            $table->date('refund_date');

            $table->unsignedBigInteger('counterparty_id');
            $table->foreign('counterparty_id')
                  ->references('id')->on('counterparties')->restrictOnDelete();

            $table->string('currency_code', 3)->default('GHS');
            $table->decimal('exchange_rate', 20, 10)->default('1');

            $table->decimal('amount', 15, 2);

            $table->unsignedBigInteger('payment_method_id')->nullable();
            $table->foreign('payment_method_id')
                  ->references('id')->on('payment_methods')->nullOnDelete();

            // OPTIONAL link to the receipt being partially or fully refunded.
            // When set, application-level validation enforces:
            //   refund.amount <= source_receipt.unallocatedAmount
            // This prevents refunding cash that's already been allocated to invoices.
            $table->unsignedBigInteger('source_receipt_id')->nullable();
            $table->foreign('source_receipt_id')
                  ->references('id')->on('receipts')->nullOnDelete();

            $table->string('external_reference', 100)->nullable();
            $table->string('reference_type', 30)->nullable();

            $table->string('status', 20)->default('draft');
            // 'draft' | 'posted' | 'reversed'

            $table->unsignedBigInteger('journal_batch_id')->nullable();
            $table->foreign('journal_batch_id')
                  ->references('id')->on('journal_batches')->nullOnDelete();

            $table->unsignedBigInteger('reversed_by_journal_batch_id')->nullable();
            $table->foreign('reversed_by_journal_batch_id')
                  ->references('id')->on('journal_batches')->nullOnDelete();

            // Approval audit
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->foreign('created_by_user_id')
                  ->references('id')->on('users')->nullOnDelete();

            $table->unsignedBigInteger('approved_by_user_id')->nullable();
            $table->foreign('approved_by_user_id')
                  ->references('id')->on('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->unsignedBigInteger('reversed_by_user_id')->nullable();
            $table->foreign('reversed_by_user_id')
                  ->references('id')->on('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();

            $table->text('reason')->nullable();
            // The refund's BUSINESS reason — "customer overpaid", "duplicate
            // payment received", "goodwill compensation", etc. Distinct from
            // 'reversal_reason' which only applies if THIS refund is reversed.

            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['organization_id', 'status', 'refund_date'], 'refunds_status_date_idx');
            $table->index(['counterparty_id', 'status'], 'refunds_counterparty_status_idx');
            $table->index('source_receipt_id', 'refunds_source_receipt_idx');
            $table->unique(['organization_id', 'refund_number'], 'refunds_org_number_unique');
        });

        try {
            $driver = Schema::getConnection()->getDriverName();
            if (in_array($driver, ['mysql', 'pgsql'], true)) {
                \DB::statement("ALTER TABLE refunds ADD CONSTRAINT refunds_status_check CHECK (status IN ('draft', 'posted', 'reversed'))");
                \DB::statement("ALTER TABLE refunds ADD CONSTRAINT refunds_amount_positive CHECK (amount > 0)");
            }
        } catch (\Throwable) {
            // CHECK already exists or unsupported
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
