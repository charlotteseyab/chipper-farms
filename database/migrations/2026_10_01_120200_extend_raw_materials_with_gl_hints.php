<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3a — extend raw_materials with GL account hints.
 *
 * The raw_materials table currently has no link to GL. When a vendor
 * invoice line references a raw material, Phase 3b's posting service
 * needs to know which GL account to debit:
 *
 *   inventory_account_id  When the item is stocked. Vendor invoice line
 *                         posts: Dr [inventory_account_id] / Cr AP control
 *                         Then Phase 4 inventory accounting handles
 *                         consumption journals.
 *
 *   expense_account_id    When the item is consumed-on-receipt rather than
 *                         stocked. Cleaning supplies, lubricants, low-value
 *                         consumables. Vendor invoice line posts:
 *                         Dr [expense_account_id] / Cr AP control directly.
 *                         Operations decides per-item whether to track stock.
 *
 * Both nullable. NULL = no item-level override; resolution falls through
 * to the supplier-level default, then category default, then COA fallback.
 *
 * Why both columns instead of one polymorphic 'gl_account_id':
 *   The two semantics are categorically different. An inventory item's
 *   account is an ASSET (1xxx); an expense item's is an EXPENSE (6xxx).
 *   Having both columns lets the model encode the policy explicitly:
 *   "this item is stocked → inventory_account_id is set, expense_account_id
 *   is NULL". The validation can enforce mutual exclusivity. It also
 *   future-proofs for items that swing both ways (rare but real — an item
 *   normally stocked but received as immediate-consumption for a one-off).
 *
 * Idempotent + additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('raw_materials')) {
            return;
        }

        Schema::table('raw_materials', function (Blueprint $table) {
            if (! Schema::hasColumn('raw_materials', 'inventory_account_id')) {
                $table->unsignedBigInteger('inventory_account_id')->nullable()
                      ->after('unit_cost');
                try {
                    $table->foreign('inventory_account_id', 'raw_materials_inventory_acct_fk')
                          ->references('id')->on('chart_of_accounts')->nullOnDelete();
                } catch (\Throwable) {}
            }

            if (! Schema::hasColumn('raw_materials', 'expense_account_id')) {
                $table->unsignedBigInteger('expense_account_id')->nullable()
                      ->after('inventory_account_id');
                try {
                    $table->foreign('expense_account_id', 'raw_materials_expense_acct_fk')
                          ->references('id')->on('chart_of_accounts')->nullOnDelete();
                } catch (\Throwable) {}
            }

            // Default purchase tax code (e.g. VAT 12.5%, NHIL, GETFL).
            // Phase 3b reads this when creating invoice lines; user can override.
            if (! Schema::hasColumn('raw_materials', 'default_purchase_tax_code_id')) {
                $table->unsignedBigInteger('default_purchase_tax_code_id')->nullable()
                      ->after('expense_account_id');
                // tax_codes table comes in Phase 3b; FK is added there to avoid
                // depending on its existence at this migration time.
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('raw_materials')) {
            return;
        }

        Schema::table('raw_materials', function (Blueprint $table) {
            try { $table->dropForeign('raw_materials_inventory_acct_fk'); } catch (\Throwable) {}
            try { $table->dropForeign('raw_materials_expense_acct_fk'); } catch (\Throwable) {}

            $cols = array_filter([
                Schema::hasColumn('raw_materials', 'inventory_account_id')         ? 'inventory_account_id'         : null,
                Schema::hasColumn('raw_materials', 'expense_account_id')           ? 'expense_account_id'           : null,
                Schema::hasColumn('raw_materials', 'default_purchase_tax_code_id') ? 'default_purchase_tax_code_id' : null,
            ]);
            if ($cols) {
                $table->dropColumn($cols);
            }
        });
    }
};
