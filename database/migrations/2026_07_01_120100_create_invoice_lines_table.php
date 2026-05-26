<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2b — invoice_lines.
 *
 * One line per item invoiced. For pig sales: typically one line per
 * invoice (one pig sold). For storefront orders (Phase 6): multiple
 * lines per invoice. For manual invoices: arbitrary lines.
 *
 * Each line carries its own:
 *   - quantity + unit_price (decimal:4 for unit price granularity)
 *   - line_subtotal = quantity * unit_price
 *   - line_discount (amount, not percent — derived from header or per-line)
 *   - line_tax + tax_code_id (computed at posting time from quantity)
 *   - line_total = line_subtotal - line_discount + line_tax
 *
 * Source linkage on the line:
 *   For PigSale-driven: pig_id captures the specific animal so the
 *   invoice ties back to the pig record.
 *
 * Revenue account override:
 *   Each line can specify a revenue_account_id. When null, the posting
 *   subscriber falls back to:
 *     1. customer_category.revenue_account_id (Phase 2a)
 *     2. The default '4010' Pig Sales Revenue
 *   This lets a single invoice mix line types (e.g. live pig + transport
 *   service fee) hitting different revenue accounts.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoice_lines')) {
            return;
        }

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();

            $table->unsignedBigInteger('invoice_id');
            $table->foreign('invoice_id')
                  ->references('id')
                  ->on('invoices')
                  ->cascadeOnDelete();

            $table->unsignedSmallInteger('line_number');
            // Display order within the invoice. 1-based.

            // What is being sold.
            $table->string('description', 500);
            $table->string('item_code', 50)->nullable();
            // Optional SKU or pig ear-tag

            // Pig linkage (only set when this line is selling a specific pig).
            $table->unsignedBigInteger('pig_id')->nullable();
            $table->foreign('pig_id')
                  ->references('id')
                  ->on('pigs_management')
                  ->nullOnDelete();

            // Quantity + unit pricing.
            $table->decimal('quantity', 15, 4);
            // Quantity unit semantics depend on item: kg for pig sales, units for ad-hoc.
            $table->string('unit_of_measure', 20)->default('unit');
            // 'kg', 'unit', 'litre', 'bag'

            $table->decimal('unit_price', 15, 4);

            // Computed totals (denormalised, written by InvoiceService).
            $table->decimal('line_subtotal', 15, 2);
            // = quantity * unit_price (rounded to 2 decimal places)

            $table->decimal('line_discount_percent', 5, 2)->default(0);
            $table->decimal('line_discount_amount', 15, 2)->default(0);

            $table->unsignedBigInteger('tax_code_id')->nullable();
            $table->foreign('tax_code_id')
                  ->references('id')
                  ->on('tax_codes')
                  ->nullOnDelete();
            $table->decimal('line_tax_amount', 15, 2)->default(0);

            $table->decimal('line_total', 15, 2);
            // = line_subtotal - line_discount_amount + line_tax_amount

            // Revenue posting hint (per-line override).
            $table->unsignedBigInteger('revenue_account_id')->nullable();
            $table->foreign('revenue_account_id')
                  ->references('id')
                  ->on('chart_of_accounts')
                  ->nullOnDelete();

            // Cost dimensions (replicated to journal_lines at posting time).
            $table->unsignedBigInteger('cost_centre_id')->nullable();
            $table->foreign('cost_centre_id')
                  ->references('id')
                  ->on('cost_centres')
                  ->nullOnDelete();

            $table->unsignedBigInteger('sales_channel_id')->nullable();
            $table->foreign('sales_channel_id')
                  ->references('id')
                  ->on('sales_channels')
                  ->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['invoice_id', 'line_number'], 'invoice_lines_invoice_line_idx');
            $table->index('pig_id', 'invoice_lines_pig_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
    }
};
