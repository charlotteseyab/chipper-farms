<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3a — extend counterparties with supplier-specific columns.
 *
 * The counterparties table has been the shared customer + supplier master
 * since Phase 2a. Phase 2a added customer-side columns. Phase 3a adds the
 * supplier-side columns. They coexist on the same row when type='both'
 * (a vendor that's also a customer — common for cooperatives).
 *
 * Additions:
 *
 *   supplier_category_id              Soft FK to supplier_categories.
 *                                     Only meaningful for type='supplier' or 'both'.
 *
 *   default_expense_account_id        GL account for vendor invoice lines that
 *                                     don't tie to a raw material. e.g. a vet
 *                                     supplier defaulting to '6020' Veterinary
 *                                     Expense. Override priority: line >
 *                                     raw_material > supplier > category > COA.
 *
 *   default_payment_method_for_ap_id  Preferred outgoing payment method.
 *                                     Used as default in Payment creation.
 *                                     Distinct from default_payment_method_id
 *                                     (Phase 0.8) which is for receipts.
 *
 *   bank_account_name                 Vendor's bank account holder name.
 *   bank_account_number               Vendor's bank account number.
 *   bank_name                         Bank name (e.g. 'GCB', 'Ecobank').
 *   bank_branch                       Branch name.
 *   bank_swift                        SWIFT/BIC for international transfers.
 *
 *   momo_provider                     Mobile money provider: 'MTN', 'Vodafone', 'AirtelTigo'.
 *   momo_number                       Mobile money number.
 *   momo_account_name                 Holder name on momo account.
 *
 *   withholding_percent               Withholding tax to apply on payments.
 *                                     Default from supplier_category, override here.
 *                                     Ghana: 7.5% on services, 5% on goods, 0% on
 *                                     statutorily exempt vendors.
 *
 *   withholding_exempt                Boolean override — vendor is registered
 *                                     for VAT and exempt from withholding.
 *
 *   total_purchases_value             Lifetime purchases value (denormalised;
 *                                     recomputed by reports). Mirror of
 *                                     total_sales_value from Phase 2a.
 *   first_purchase_at                 Mirror of first_sale_at.
 *   last_purchase_at                  Mirror of last_sale_at.
 *
 * Idempotent + additive. No data migration risk.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('counterparties')) {
            return;
        }

        Schema::table('counterparties', function (Blueprint $table) {
            if (! Schema::hasColumn('counterparties', 'supplier_category_id')) {
                $table->unsignedBigInteger('supplier_category_id')->nullable()
                      ->after('customer_category_id');
                try {
                    $table->foreign('supplier_category_id', 'counterparties_supplier_category_fk')
                          ->references('id')->on('supplier_categories')->nullOnDelete();
                } catch (\Throwable) {
                    // FK may already exist
                }
            }

            if (! Schema::hasColumn('counterparties', 'default_expense_account_id')) {
                $table->unsignedBigInteger('default_expense_account_id')->nullable()
                      ->after('supplier_category_id');
                try {
                    $table->foreign('default_expense_account_id', 'counterparties_default_exp_acct_fk')
                          ->references('id')->on('chart_of_accounts')->nullOnDelete();
                } catch (\Throwable) {}
            }

            if (! Schema::hasColumn('counterparties', 'default_payment_method_for_ap_id')) {
                $table->unsignedBigInteger('default_payment_method_for_ap_id')->nullable()
                      ->after('default_expense_account_id');
                try {
                    $table->foreign('default_payment_method_for_ap_id', 'counterparties_ap_payment_method_fk')
                          ->references('id')->on('payment_methods')->nullOnDelete();
                } catch (\Throwable) {}
            }

            // Banking details
            if (! Schema::hasColumn('counterparties', 'bank_account_name')) {
                $table->string('bank_account_name', 200)->nullable()
                      ->after('default_payment_method_for_ap_id');
            }
            if (! Schema::hasColumn('counterparties', 'bank_account_number')) {
                $table->string('bank_account_number', 50)->nullable()
                      ->after('bank_account_name');
            }
            if (! Schema::hasColumn('counterparties', 'bank_name')) {
                $table->string('bank_name', 100)->nullable()
                      ->after('bank_account_number');
            }
            if (! Schema::hasColumn('counterparties', 'bank_branch')) {
                $table->string('bank_branch', 100)->nullable()
                      ->after('bank_name');
            }
            if (! Schema::hasColumn('counterparties', 'bank_swift')) {
                $table->string('bank_swift', 20)->nullable()
                      ->after('bank_branch');
            }

            // Mobile money
            if (! Schema::hasColumn('counterparties', 'momo_provider')) {
                $table->string('momo_provider', 30)->nullable()
                      ->after('bank_swift');
            }
            if (! Schema::hasColumn('counterparties', 'momo_number')) {
                $table->string('momo_number', 30)->nullable()
                      ->after('momo_provider');
            }
            if (! Schema::hasColumn('counterparties', 'momo_account_name')) {
                $table->string('momo_account_name', 200)->nullable()
                      ->after('momo_number');
            }

            // Withholding tax
            if (! Schema::hasColumn('counterparties', 'withholding_percent')) {
                $table->decimal('withholding_percent', 5, 2)->nullable()
                      ->after('momo_account_name');
            }
            if (! Schema::hasColumn('counterparties', 'withholding_exempt')) {
                $table->boolean('withholding_exempt')->default(false)
                      ->after('withholding_percent');
            }

            // Purchase tracking aggregates
            if (! Schema::hasColumn('counterparties', 'first_purchase_at')) {
                $table->timestamp('first_purchase_at')->nullable()
                      ->after('total_sales_value');
            }
            if (! Schema::hasColumn('counterparties', 'last_purchase_at')) {
                $table->timestamp('last_purchase_at')->nullable()
                      ->after('first_purchase_at');
            }
            if (! Schema::hasColumn('counterparties', 'total_purchases_value')) {
                $table->decimal('total_purchases_value', 15, 2)->default(0)
                      ->after('last_purchase_at');
            }
        });

        // Indexes for AP listing and reporting paths.
        Schema::table('counterparties', function (Blueprint $table) {
            try {
                $table->index(['organization_id', 'type', 'supplier_category_id'],
                              'counterparties_org_type_supplier_cat_idx');
            } catch (\Throwable) {
                // Index may already exist
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('counterparties')) {
            return;
        }

        Schema::table('counterparties', function (Blueprint $table) {
            try { $table->dropIndex('counterparties_org_type_supplier_cat_idx'); } catch (\Throwable) {}

            foreach ([
                'counterparties_supplier_category_fk',
                'counterparties_default_exp_acct_fk',
                'counterparties_ap_payment_method_fk',
            ] as $fk) {
                try { $table->dropForeign($fk); } catch (\Throwable) {}
            }

            $cols = array_filter([
                Schema::hasColumn('counterparties', 'supplier_category_id')              ? 'supplier_category_id'              : null,
                Schema::hasColumn('counterparties', 'default_expense_account_id')        ? 'default_expense_account_id'        : null,
                Schema::hasColumn('counterparties', 'default_payment_method_for_ap_id')  ? 'default_payment_method_for_ap_id'  : null,
                Schema::hasColumn('counterparties', 'bank_account_name')                 ? 'bank_account_name'                 : null,
                Schema::hasColumn('counterparties', 'bank_account_number')               ? 'bank_account_number'               : null,
                Schema::hasColumn('counterparties', 'bank_name')                         ? 'bank_name'                         : null,
                Schema::hasColumn('counterparties', 'bank_branch')                       ? 'bank_branch'                       : null,
                Schema::hasColumn('counterparties', 'bank_swift')                        ? 'bank_swift'                        : null,
                Schema::hasColumn('counterparties', 'momo_provider')                     ? 'momo_provider'                     : null,
                Schema::hasColumn('counterparties', 'momo_number')                       ? 'momo_number'                       : null,
                Schema::hasColumn('counterparties', 'momo_account_name')                 ? 'momo_account_name'                 : null,
                Schema::hasColumn('counterparties', 'withholding_percent')               ? 'withholding_percent'               : null,
                Schema::hasColumn('counterparties', 'withholding_exempt')                ? 'withholding_exempt'                : null,
                Schema::hasColumn('counterparties', 'first_purchase_at')                 ? 'first_purchase_at'                 : null,
                Schema::hasColumn('counterparties', 'last_purchase_at')                  ? 'last_purchase_at'                  : null,
                Schema::hasColumn('counterparties', 'total_purchases_value')             ? 'total_purchases_value'             : null,
            ]);
            if ($cols) {
                $table->dropColumn($cols);
            }
        });
    }
};
