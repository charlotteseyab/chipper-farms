<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2a — extend counterparties with the columns Phase 2 finance needs.
 *
 * Additions:
 *   customer_category_id  Soft FK to customer_categories (only meaningful
 *                         for type='customer' or 'both' rows).
 *   currency_code         Default invoicing currency. GHS for now per Decision 3.
 *                         Foreign-currency customers (export) use 'USD' etc.
 *   discount_percent      Per-customer override of the category default.
 *   on_credit_hold        Manually set when a customer has unpaid invoices
 *                         past terms. Phase 2c receipts code clears it
 *                         when the balance is settled.
 *   credit_hold_reason    Free text — recorded when on_credit_hold flips
 *                         to true.
 *   internal_notes        Operations notes, not customer-facing.
 *   first_sale_at         When was the first pig_sale to this customer?
 *                         Backfilled in a subsequent batch from pig_sales.
 *   last_sale_at          Most recent sale.
 *   total_sales_value     Lifetime sales value (denormalised; recomputed
 *                         by reports, not authoritative).
 *
 * Safe and idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('counterparties')) {
            return;
        }

        Schema::table('counterparties', function (Blueprint $table) {
            if (! Schema::hasColumn('counterparties', 'customer_category_id')) {
                $table->unsignedBigInteger('customer_category_id')->nullable()->after('default_tax_code_id');
                $table->foreign('customer_category_id', 'counterparties_customer_category_fk')
                      ->references('id')
                      ->on('customer_categories')
                      ->nullOnDelete();
            }

            if (! Schema::hasColumn('counterparties', 'currency_code')) {
                $table->string('currency_code', 3)->default('GHS')->after('customer_category_id');
            }

            if (! Schema::hasColumn('counterparties', 'discount_percent')) {
                $table->decimal('discount_percent', 5, 2)->default(0)->after('credit_limit');
            }

            if (! Schema::hasColumn('counterparties', 'on_credit_hold')) {
                $table->boolean('on_credit_hold')->default(false)->after('discount_percent');
            }

            if (! Schema::hasColumn('counterparties', 'credit_hold_reason')) {
                $table->text('credit_hold_reason')->nullable()->after('on_credit_hold');
            }

            if (! Schema::hasColumn('counterparties', 'internal_notes')) {
                $table->text('internal_notes')->nullable()->after('notes');
            }

            if (! Schema::hasColumn('counterparties', 'first_sale_at')) {
                $table->timestamp('first_sale_at')->nullable()->after('internal_notes');
            }

            if (! Schema::hasColumn('counterparties', 'last_sale_at')) {
                $table->timestamp('last_sale_at')->nullable()->after('first_sale_at');
            }

            if (! Schema::hasColumn('counterparties', 'total_sales_value')) {
                $table->decimal('total_sales_value', 15, 2)->default(0)->after('last_sale_at');
            }
        });

        // Indexes — for the customer listing/filter UI.
        Schema::table('counterparties', function (Blueprint $table) {
            try {
                $table->index(['customer_category_id', 'is_active'], 'counterparties_category_active_idx');
            } catch (\Throwable $e) { /* may already exist */ }

            try {
                $table->index(['organization_id', 'on_credit_hold'], 'counterparties_credit_hold_idx');
            } catch (\Throwable $e) { /* may already exist */ }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('counterparties')) {
            return;
        }

        Schema::table('counterparties', function (Blueprint $table) {
            try { $table->dropIndex('counterparties_category_active_idx'); } catch (\Throwable) {}
            try { $table->dropIndex('counterparties_credit_hold_idx'); } catch (\Throwable) {}

            if (Schema::hasColumn('counterparties', 'customer_category_id')) {
                try { $table->dropForeign('counterparties_customer_category_fk'); } catch (\Throwable) {}
                $table->dropColumn('customer_category_id');
            }

            foreach (['currency_code', 'discount_percent', 'on_credit_hold',
                      'credit_hold_reason', 'internal_notes', 'first_sale_at',
                      'last_sale_at', 'total_sales_value'] as $col) {
                if (Schema::hasColumn('counterparties', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
