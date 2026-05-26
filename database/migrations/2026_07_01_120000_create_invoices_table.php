<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2b — invoices.
 *
 * Sales invoices issued to customers. Replaces the implicit "invoice" that
 * Phase 1c's pig_sale subscriber posted directly to GL — going forward,
 * the subscriber creates an invoice row, and a separate posting subscriber
 * posts the invoice to GL when it transitions to 'posted'.
 *
 * Status state machine:
 *   draft     → posted, cancelled
 *   posted    → reversed
 *   cancelled (terminal)
 *   reversed  (terminal)
 *
 * Source linkage:
 *   source_type / source_id polymorphic — points at the originating
 *   record (PigSale today, StorefrontOrder in Phase 6, manual entry has null).
 *
 * Posting linkage:
 *   journal_batch_id — set when status flips to posted, references the
 *   GL entry. Reversal status sets reversed_by_journal_batch_id (the
 *   reversal posts a separate batch).
 *
 * Decimal scales are 4 for unit prices (price-per-kg has more granularity)
 * and 2 for totals (cash precision).
 *
 * Idempotent and additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoices')) {
            return;
        }

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();

            $table->string('invoice_number', 30);
            // Format: INV-YYYY-NNNNNN — assigned by NumberingService at create time.

            $table->date('invoice_date');
            $table->date('due_date')->nullable();

            // Counterparty (customer).
            $table->unsignedBigInteger('counterparty_id');
            $table->foreign('counterparty_id')
                  ->references('id')
                  ->on('counterparties')
                  ->restrictOnDelete();
            // RESTRICT — never delete a counterparty that has invoices.

            // Currency + (Phase 5+) FX.
            $table->string('currency_code', 3)->default('GHS');
            $table->decimal('exchange_rate', 20, 10)->default('1');

            // Source linkage (polymorphic).
            $table->string('source_type', 100)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            // For PigSale-driven invoices: source_type='App\Models\PigSale'.
            // For manual invoices: NULL.

            // Status + state-machine columns.
            $table->string('status', 20)->default('draft');
            // 'draft' | 'posted' | 'cancelled' | 'reversed'

            // Posting linkage (set when posted).
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

            // Totals — denormalised for performance + invoice-document accuracy.
            // Updated atomically when lines are added/changed.
            $table->decimal('subtotal',     15, 2)->default(0);
            $table->decimal('discount_total', 15, 2)->default(0);
            $table->decimal('tax_total',    15, 2)->default(0);
            $table->decimal('total',        15, 2)->default(0);
            // total = subtotal - discount_total + tax_total

            // AR side (for posting hint resolution).
            $table->boolean('is_cash_invoice')->default(false);
            // Set when payment_method_id resolves to a cash account hint —
            // determines whether posting hits Cash (true) or AR (false).
            $table->unsignedBigInteger('payment_method_id')->nullable();
            $table->foreign('payment_method_id')
                  ->references('id')
                  ->on('payment_methods')
                  ->nullOnDelete();

            // Tax / discount applied at invoice level (per-line tax/discount
            // is captured on invoice_lines).
            $table->unsignedBigInteger('default_tax_code_id')->nullable();
            $table->foreign('default_tax_code_id')
                  ->references('id')
                  ->on('tax_codes')
                  ->nullOnDelete();
            $table->decimal('header_discount_percent', 5, 2)->default(0);

            // Audit
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->foreign('created_by_user_id')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();

            $table->unsignedBigInteger('posted_by_user_id')->nullable();
            $table->foreign('posted_by_user_id')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();
            $table->timestamp('posted_at')->nullable();

            $table->unsignedBigInteger('cancelled_by_user_id')->nullable();
            $table->foreign('cancelled_by_user_id')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            // Customer-facing
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->string('reference', 100)->nullable();
            // Customer's PO number, for example.

            // Internal notes (non-printed)
            $table->text('internal_notes')->nullable();

            // Address snapshots — captured at posting time so the printed
            // invoice doesn't change if the customer's address is later edited.
            $table->text('billing_address_snapshot')->nullable();
            $table->text('shipping_address_snapshot')->nullable();

            $table->softDeletes();
            $table->timestamps();

            // Indexes for the listing UI + AR ageing reports.
            $table->index(['organization_id', 'status', 'invoice_date'], 'invoices_status_date_idx');
            $table->index(['counterparty_id', 'status'], 'invoices_counterparty_status_idx');
            $table->index('due_date', 'invoices_due_date_idx');
            $table->index(['source_type', 'source_id'], 'invoices_source_idx');
            $table->unique(['organization_id', 'invoice_number'], 'invoices_org_number_unique');
        });

        // CHECK constraint on status (where supported).
        try {
            $driver = Schema::getConnection()->getDriverName();
            if (in_array($driver, ['mysql', 'pgsql'], true)) {
                \DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_status_check CHECK (status IN ('draft', 'posted', 'cancelled', 'reversed'))");
            }
        } catch (\Throwable $e) {
            // CHECK not critical — application enforces.
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
