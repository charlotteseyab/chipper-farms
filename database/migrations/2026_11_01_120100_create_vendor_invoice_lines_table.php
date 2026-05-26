<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3b — vendor_invoice_lines.
 *
 * Each line records a single billable item or service:
 *   - description (free text, what the supplier called it)
 *   - quantity + unit_cost = line_subtotal
 *   - tax_code → tax_amount
 *   - withholding_code → withholding_amount (informational)
 *   - account_id → GL account that gets debited
 *
 * Account resolution at line creation (resolved by FinanceAccountResolver
 * in Phase 3b):
 *
 *   1. Manual override: caller passes account_id explicitly
 *   2. Item-level: raw_materials.inventory_account_id (if stocked)
 *                  or raw_materials.expense_account_id (if consumed)
 *   3. Supplier-level: counterparties.default_expense_account_id
 *   4. Category-level: supplier_categories.default_expense_account_id
 *   5. COA fallback: '6010' Sundry Expense
 *
 * Three-way matching linkage:
 *   - purchase_request_item_id (optional): the PR line this VI line invoices.
 *     When set, MatchingService compares qty + unit_cost against the PR line
 *     and against received raw_material_stock_logs.
 *   - raw_material_id (optional): direct link to a stocked item. Lets the
 *     posting service decide between inventory and expense accounts based on
 *     the item's column values.
 *
 * Withholding at line level:
 *   Withholding tax can vary line-by-line. A vendor might bill us for both
 *   goods (3% WHT) and services (7.5% WHT) on a single invoice. Each line
 *   carries its own withholding_tax_code_id; the header's withholding_total
 *   is the sum. The supplier-level withholding_exempt flag overrides all
 *   line-level withholding to zero.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vendor_invoice_lines')) {
            return;
        }

        Schema::create('vendor_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();

            $table->unsignedBigInteger('vendor_invoice_id');
            $table->foreign('vendor_invoice_id')
                  ->references('id')->on('vendor_invoices')->cascadeOnDelete();

            $table->integer('line_number')->default(1);
            // Display order within the invoice. UI uses this for line ordering.

            $table->text('description');
            // Free text — usually copied from the supplier's invoice line item.

            // Item linkage (optional).
            $table->unsignedBigInteger('raw_material_id')->nullable();
            $table->foreign('raw_material_id')
                  ->references('id')->on('raw_materials')->nullOnDelete();

            // PR line linkage (optional, for three-way matching).
            $table->unsignedBigInteger('purchase_request_item_id')->nullable();
            $table->foreign('purchase_request_item_id')
                  ->references('id')->on('purchase_request_items')->nullOnDelete();

            // Quantities
            $table->decimal('quantity',  15, 4)->default(1);
            $table->string('unit', 30)->nullable();
            // 'kg', 'litre', 'each', 'hour'. Free-text per item.

            $table->decimal('unit_cost', 15, 4);
            // Per-unit price. Higher precision than money columns because some
            // ingredients are priced per gram or per ml.

            // Subtotals — denormalised, set by VendorInvoiceService at line save.
            $table->decimal('line_subtotal',     15, 2)->default(0);
            // = round(quantity * unit_cost, 2). The "what the supplier billed."

            // Tax routing
            $table->unsignedBigInteger('tax_code_id')->nullable();
            $table->foreign('tax_code_id')
                  ->references('id')->on('tax_codes')->nullOnDelete();
            $table->decimal('tax_rate',   8, 4)->default(0);
            // Captured at create time so historical bills survive tax-rate changes.
            $table->decimal('tax_amount', 15, 2)->default(0);

            // Withholding tax routing (line-level — varies per item type)
            $table->unsignedBigInteger('withholding_tax_code_id')->nullable();
            $table->foreign('withholding_tax_code_id')
                  ->references('id')->on('tax_codes')->nullOnDelete();
            $table->decimal('withholding_rate',   8, 4)->default(0);
            $table->decimal('withholding_amount',15, 2)->default(0);

            $table->decimal('line_total', 15, 2)->default(0);
            // = line_subtotal + tax_amount. NOT minus withholding (that's
            // applied at payment time, not on the bill).

            // GL account routing.
            $table->unsignedBigInteger('account_id');
            $table->foreign('account_id')
                  ->references('id')->on('chart_of_accounts')->restrictOnDelete();

            $table->softDeletes();
            $table->timestamps();

            $table->index('vendor_invoice_id', 'vendor_invoice_lines_invoice_idx');
            $table->index('purchase_request_item_id', 'vendor_invoice_lines_pr_item_idx');
            $table->index('raw_material_id', 'vendor_invoice_lines_raw_material_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_invoice_lines');
    }
};
