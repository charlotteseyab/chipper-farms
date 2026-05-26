<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3c — payment_run_lines.
 *
 * One row per (vendor_invoice, planned-payment-amount) in a draft run.
 * counterparty_id is denormalised so the executor can group without an
 * extra join.
 *
 * Per-line method/source overrides:
 *   When NULL, the executor fills from the run's defaults BEFORE
 *   grouping. When non-NULL, this line forms its own group (so two
 *   lines for the same supplier with different methods → two payments).
 *
 * After execute, payment_id and payment_allocation_id are set on the
 * line so the show page can link back to the materialised records.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_run_lines')) {
            return;
        }

        Schema::create('payment_run_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();

            $table->unsignedBigInteger('payment_run_id');
            $table->foreign('payment_run_id')
                  ->references('id')->on('payment_runs')->cascadeOnDelete();

            $table->unsignedBigInteger('vendor_invoice_id');
            // RESTRICT — can't delete a VI that's referenced by a run.
            $table->foreign('vendor_invoice_id')
                  ->references('id')->on('vendor_invoices')->restrictOnDelete();

            // Denormalised for grouping without extra joins.
            $table->unsignedBigInteger('counterparty_id');
            $table->foreign('counterparty_id')
                  ->references('id')->on('counterparties')->restrictOnDelete();

            $table->decimal('amount',             15, 2);
            $table->decimal('withholding_amount', 15, 2)->default(0);

            // Per-line method/source overrides.
            $table->unsignedBigInteger('payment_method_id')->nullable();
            $table->foreign('payment_method_id')
                  ->references('id')->on('payment_methods')->nullOnDelete();

            $table->unsignedBigInteger('source_account_id')->nullable();
            $table->foreign('source_account_id')
                  ->references('id')->on('chart_of_accounts')->nullOnDelete();

            // Set after execute.
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->foreign('payment_id')
                  ->references('id')->on('payments')->nullOnDelete();

            $table->unsignedBigInteger('payment_allocation_id')->nullable();
            $table->foreign('payment_allocation_id')
                  ->references('id')->on('payment_allocations')->nullOnDelete();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('payment_run_id',   'payment_run_lines_run_idx');
            $table->index('vendor_invoice_id','payment_run_lines_vi_idx');
            $table->unique(
                ['payment_run_id', 'vendor_invoice_id'],
                'payment_run_lines_run_vi_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_run_lines');
    }
};
