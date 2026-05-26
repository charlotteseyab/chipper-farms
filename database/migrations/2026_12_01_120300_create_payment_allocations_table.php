<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3c — payment_allocations.
 *
 * Junction table between payments and vendor_invoices. One payment can
 * be allocated to multiple invoices (paying down several open bills);
 * one invoice can receive allocations from multiple payments (settling
 * a bill in instalments).
 *
 * Per-allocation tracking:
 *   amount               : how much of this payment is applied to this VI
 *   withholding_amount   : the WHT slice for this VI's portion of the
 *                          payment. Computed by WithholdingCalculator
 *                          based on the VI's withholding_total ratio.
 *
 * Allocation produces the WHT-slice journal:
 *   Dr  AP control      withholding_amount
 *       Cr  WHT Payable      withholding_amount
 *
 * (NOT cash — cash already left when the payment was created. The
 * WHT slice journal moves the AP debt into the WHT Payable bucket
 * for later remittance to GRA.)
 *
 * Reversibility:
 *   reversal_of_allocation_id is a self-FK. When an allocation is
 *   reversed, a new row is created with status='reversed' pointing at
 *   the original. This keeps the audit trail intact rather than mutating
 *   the original row's status.
 *   Actually — to keep things simpler and match how Phase 2c receipts
 *   handled allocations, this migration uses status flips on the
 *   original row instead. reversal_of_allocation_id is reserved for a
 *   future enhancement where reversals are recorded as new rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_allocations')) {
            return;
        }

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();

            $table->unsignedBigInteger('payment_id');
            $table->foreign('payment_id')
                  ->references('id')->on('payments')->cascadeOnDelete();

            $table->unsignedBigInteger('vendor_invoice_id');
            $table->foreign('vendor_invoice_id')
                  ->references('id')->on('vendor_invoices')->cascadeOnDelete();

            $table->decimal('amount', 15, 2);
            $table->decimal('withholding_amount', 15, 2)->default(0);

            $table->date('allocation_date');
            // Defaults to payment_date but can be different if accounts
            // wants to apply payment retroactively or future-date.

            $table->string('status', 20)->default('active');
            // active | reversed

            // Self-FK for future reversal-as-new-row pattern.
            $table->unsignedBigInteger('reversal_of_allocation_id')->nullable();
            $table->foreign('reversal_of_allocation_id')
                  ->references('id')->on('payment_allocations')->nullOnDelete();

            // GL linkage for the WHT-slice journal (NULL if withholding=0).
            $table->unsignedBigInteger('withholding_journal_batch_id')->nullable();
            $table->foreign('withholding_journal_batch_id')
                  ->references('id')->on('journal_batches')->nullOnDelete();

            $table->unsignedBigInteger('reversed_by_journal_batch_id')->nullable();
            $table->foreign('reversed_by_journal_batch_id')
                  ->references('id')->on('journal_batches')->nullOnDelete();

            // Audit.
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->foreign('created_by_user_id')
                  ->references('id')->on('users')->nullOnDelete();

            $table->unsignedBigInteger('reversed_by_user_id')->nullable();
            $table->foreign('reversed_by_user_id')
                  ->references('id')->on('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['payment_id', 'status'], 'payment_allocations_payment_status_idx');
            $table->index(['vendor_invoice_id', 'status'], 'payment_allocations_vi_status_idx');
        });

        try {
            $driver = Schema::getConnection()->getDriverName();
            if (in_array($driver, ['mysql', 'pgsql'], true)) {
                \DB::statement(
                    "ALTER TABLE payment_allocations ADD CONSTRAINT payment_allocations_status_check " .
                    "CHECK (status IN ('active', 'reversed'))"
                );
                \DB::statement(
                    "ALTER TABLE payment_allocations ADD CONSTRAINT payment_allocations_amounts_check " .
                    "CHECK (amount > 0 AND withholding_amount >= 0 AND withholding_amount <= amount)"
                );
            }
        } catch (\Throwable) {}
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
    }
};
