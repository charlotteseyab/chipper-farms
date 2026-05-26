<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3a — supplier_categories.
 *
 * Mirrors customer_categories from Phase 2a. Categorises suppliers
 * for posting-hint defaults and reporting:
 *
 *   FEED_INGREDIENTS  : raw materials → DR Inventory / CR AP
 *   VETERINARY        : services + drugs → DR Veterinary Expense
 *   UTILITIES         : electricity/water → DR Utilities Expense
 *   CAPEX             : equipment, buildings → DR Fixed Assets
 *   PROFESSIONAL      : audit, legal → DR Professional Fees
 *   GENERAL           : catch-all → DR Sundry Expense
 *
 * Each category can override the default expense and AP control accounts
 * the vendor invoice posting will use when a specific override isn't
 * set on the supplier or invoice line.
 *
 * Account-resolution hierarchy (Phase 3b will use this):
 *   line.account_id (manual override)
 *   → raw_material.inventory_account_id / expense_account_id
 *   → counterparty.default_expense_account_id
 *   → supplier_category.default_expense_account_id
 *   → COA fallback ('6010' Sundry Expense)
 *
 * Categories are also used for procurement reporting:
 *   spend by category, vendor concentration, payment-terms compliance.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('supplier_categories')) {
            return;
        }

        Schema::create('supplier_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();

            $table->string('code', 30);
            // 'FEED_INGREDIENTS' | 'VETERINARY' | 'UTILITIES' | 'CAPEX' | etc.

            $table->string('name', 100);
            $table->text('description')->nullable();

            // Posting hints for Phase 3b's vendor invoice posting.

            $table->unsignedBigInteger('default_expense_account_id')->nullable();
            // Default GL account for invoice lines from this category's vendors
            // when no override is set on the line, item, or supplier.
            $table->foreign('default_expense_account_id', 'supplier_categories_default_exp_acct_fk')
                  ->references('id')
                  ->on('chart_of_accounts')
                  ->nullOnDelete();

            $table->unsignedBigInteger('ap_account_id')->nullable();
            // Override of the default '2110' AP control account. Useful for
            // segregating AP ageing by supplier category (e.g. capex suppliers
            // tracked separately from feed suppliers).
            $table->foreign('ap_account_id', 'supplier_categories_ap_acct_fk')
                  ->references('id')
                  ->on('chart_of_accounts')
                  ->nullOnDelete();

            // Default payment terms in days. Vendor invoice creation defaults
            // due_date = invoice_date + payment_terms_days. The supplier's own
            // counterparties.payment_terms_days overrides this if set.
            $table->integer('payment_terms_days')->default(30);

            // Default withholding tax treatment (VAT-relevant in Ghana).
            // Phase 3b's posting service reads this; suppliers can override.
            $table->decimal('default_withholding_percent', 5, 2)->default(0);
            $table->unsignedBigInteger('default_withholding_account_id')->nullable();
            $table->foreign('default_withholding_account_id', 'supplier_categories_wht_acct_fk')
                  ->references('id')
                  ->on('chart_of_accounts')
                  ->nullOnDelete();

            $table->boolean('is_active')->default(true);

            $table->softDeletes();
            $table->timestamps();

            $table->unique(['organization_id', 'code'], 'supplier_categories_org_code_unique');
            $table->index(['organization_id', 'is_active'], 'supplier_categories_org_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_categories');
    }
};
