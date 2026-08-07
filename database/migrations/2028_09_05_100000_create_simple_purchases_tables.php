<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Simple Purchase (Phase 6b, blueprint §24).
 *
 * ONE PURCHASE, MANY LINES, TWO KINDS OF LINE
 * -------------------------------------------
 * A farm buying from one supplier on one day fills one form. Some lines are
 * stock (maize, medicine) and some are not (a delivery fee, a service call).
 * They post differently — stock goes through the inventory subledger, non-stock
 * straight to an expense account — so the kind is per line, not per purchase.
 *
 * WHAT THIS TABLE DOES NOT DO
 * ---------------------------
 * It does not hold the inventory posting. Phase 6's investigation established
 * that `RawMaterialReceivedSubscriber` already posts `Dr Inventory / Cr
 * Inventory Suspense` for a direct receipt. A purchase that also posted its own
 * inventory journal would debit inventory twice per delivery and propagate the
 * error into COGS on every later sale.
 *
 * So `settlement_batch_id` is the only journal this workflow owns: the leg that
 * clears the suspense or expense against cash or payables. The inventory leg
 * belongs to the subscriber, and `inventory_movement_id` records the link
 * rather than duplicating the effect.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('simple_purchases')) {
            Schema::create('simple_purchases', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id');

                $table->string('purchase_number', 40)->nullable();
                $table->date('purchase_date');

                $table->unsignedBigInteger('supplier_id')->nullable();
                $table->string('supplier_invoice_reference', 120)->nullable();

                // paid_now | pay_later
                $table->string('settlement', 20)->default('paid_now');
                $table->string('payment_method_key', 40)->nullable();
                $table->string('payment_account_code', 20)->nullable();
                $table->string('external_reference', 120)->nullable();

                $table->decimal('subtotal', 18, 2)->default(0);
                $table->decimal('tax_amount', 18, 2)->default(0);
                $table->decimal('total_amount', 18, 2)->default(0);
                $table->string('currency_code', 3)->default('GHS');

                $table->unsignedBigInteger('tax_profile_id')->nullable();
                $table->json('tax_snapshot')->nullable();

                $table->string('status', 20)->default('draft');
                $table->string('posting_error', 1000)->nullable();

                // The ONLY journal this workflow owns — the settlement leg.
                $table->unsignedBigInteger('settlement_batch_id')->nullable();
                $table->unsignedBigInteger('vendor_invoice_id')->nullable();

                $table->unsignedBigInteger('recorded_by_user_id')->nullable();
                $table->string('idempotency_key', 120);
                $table->text('notes')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->unique(['organization_id', 'idempotency_key'], 'simple_purchases_idem_unique');
                $table->index(['organization_id', 'purchase_date'], 'simple_purchases_date_idx');

                $table->foreign('organization_id')
                    ->references('id')->on('organizations')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('simple_purchase_lines')) {
            Schema::create('simple_purchase_lines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id');
                $table->unsignedBigInteger('simple_purchase_id');

                // stock | expense
                $table->string('line_kind', 20)->default('expense');

                // For stock lines: which raw material.
                $table->unsignedBigInteger('raw_material_id')->nullable();

                // For expense lines: the Quick Expense category key, reusing the
                // same farm-language mapping rather than inventing a second one.
                $table->string('category_key', 60)->nullable();
                $table->string('expense_account_code', 20)->nullable();

                $table->string('description', 500);
                $table->decimal('quantity', 18, 4)->default(1);
                $table->string('unit', 20)->nullable();
                $table->decimal('unit_cost', 18, 4)->default(0);
                $table->decimal('line_total', 18, 2)->default(0);

                // Set when the stock line was received. Records the link to the
                // subledger without duplicating its accounting effect.
                $table->unsignedBigInteger('inventory_movement_id')->nullable();

                $table->timestamps();

                $table->index(['simple_purchase_id'], 'simple_purchase_lines_parent_idx');

                $table->foreign('simple_purchase_id')
                    ->references('id')->on('simple_purchases')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('simple_purchase_lines');
        Schema::dropIfExists('simple_purchases');
    }
};
