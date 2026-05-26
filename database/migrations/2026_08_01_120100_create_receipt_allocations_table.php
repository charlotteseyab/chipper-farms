<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2c — receipt_allocations.
 *
 * One row per (receipt, invoice) allocation. A receipt with three
 * allocations splits its cash across three invoices. An invoice with
 * three allocations was paid by three separate receipts. The table is
 * a many-to-many junction with an amount column.
 *
 * Allocations have their own state machine:
 *   active → reversed
 *
 * Reversed allocations remain in the table for audit (no hard delete).
 * The 'active' scope filters them out for sum-of-active-allocations
 * computations.
 *
 * No GL impact:
 *   Allocations are sub-ledger detail. The cash already hit GL on
 *   receipt creation (Dr Cash, Cr AR control). Allocations attribute
 *   that AR-control credit to specific invoices for reporting purposes.
 *
 * Atomicity:
 *   AllocationService creates an allocation row + updates the receipt's
 *   denormalised allocated_amount + updates the invoice's denormalised
 *   amount_paid + balance_due — all inside a single DB::transaction()
 *   with row-level locks (lockForUpdate) on receipt and invoice.
 *
 *   Reversing an allocation does the inverse: flips status to reversed,
 *   re-aggregates the receipt and invoice. Same locking rules.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('receipt_allocations')) {
            return;
        }

        Schema::create('receipt_allocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();

            $table->unsignedBigInteger('receipt_id');
            $table->foreign('receipt_id')
                  ->references('id')
                  ->on('receipts')
                  ->cascadeOnDelete();

            $table->unsignedBigInteger('invoice_id');
            $table->foreign('invoice_id')
                  ->references('id')
                  ->on('invoices')
                  ->cascadeOnDelete();

            $table->decimal('amount', 15, 2);
            // Must be > 0. Validated in CHECK + at app layer.

            $table->date('allocation_date');
            // The DATE the allocation was made — distinct from receipt_date
            // (the date money hit the bank) and invoice_date. For period
            // reporting: an allocation made in May for a receipt that hit in April
            // shows under May for sub-ledger movement.

            $table->string('status', 20)->default('active');
            // 'active' | 'reversed'

            // For reversal audit: when an allocation is reversed, this
            // points to the new allocation row that documents the reversal.
            // Null on the original active row, set on the reversed row.
            // (Symmetric design: reverse-of-reverse possible if needed.)
            $table->unsignedBigInteger('reversed_by_allocation_id')->nullable();
            $table->foreign('reversed_by_allocation_id')
                  ->references('id')
                  ->on('receipt_allocations')
                  ->nullOnDelete();

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

            $table->text('notes')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['receipt_id', 'status'], 'receipt_allocations_receipt_status_idx');
            $table->index(['invoice_id', 'status'], 'receipt_allocations_invoice_status_idx');
            $table->index('allocation_date', 'receipt_allocations_date_idx');
        });

        try {
            $driver = Schema::getConnection()->getDriverName();
            if (in_array($driver, ['mysql', 'pgsql'], true)) {
                \DB::statement("ALTER TABLE receipt_allocations ADD CONSTRAINT receipt_allocations_status_check CHECK (status IN ('active', 'reversed'))");
                \DB::statement("ALTER TABLE receipt_allocations ADD CONSTRAINT receipt_allocations_amount_positive CHECK (amount > 0)");
            }
        } catch (\Throwable) {
            // CHECK not critical — application enforces via DomainException
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_allocations');
    }
};
