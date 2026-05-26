<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 — inventory account mappings.
 *
 * Settings table that drives GL routing for inventory movements. Per
 * (organization, item_kind, optional category), maps to:
 *   - inventory_account_id  : asset, the GL debit at receipt
 *   - gr_ir_account_id      : liability, the GL credit at PR-linked receipt
 *   - adjustment_account_id : expense, the GL counter-side at adjustments
 *   - cogs_account_id       : expense, the GL debit on feed-out / sale
 *   - ppv_account_id        : expense, the GL counter-side at PPV
 *
 * Resolution order (per item):
 *   1. raw_material.inventory_account_id (item-level override) — only
 *      applies to KIND_RAW_MATERIAL.
 *   2. inventory_account_mappings where category matches the item's
 *      category (raw_material.category or feed_type.category).
 *   3. inventory_account_mappings where is_default=true AND
 *      category='__default__'.
 *   4. config('finance.inventory.fallback_account_codes.{kind}.{role}')
 *      resolved via FinanceAccountResolver::byCode().
 *
 * Why a settings table instead of hard-coded codes?
 *   Different organisations / cost centres may want different inventory
 *   account routing (e.g. separate inventory accounts per category, or
 *   different PPV accounts per supplier-region). The mapping table
 *   captures this policy declaratively, audited, and visible in the
 *   admin UI. Hard-coded codes are the safe last-resort fallback, never
 *   the routine path.
 *
 * Default row pattern:
 *   A row with category='__default__' (the DEFAULT_CATEGORY_TOKEN
 *   sentinel) and is_default=true represents the org-wide default for
 *   that item_kind. Unique constraint on (org, kind, category) keeps
 *   exactly one row per category per kind, including the default.
 *
 *   Using a sentinel string rather than NULL because NULL participates
 *   awkwardly in unique indexes across DB engines.
 *
 * Why is_default IS a separate column AND part of the unique:
 *   - is_default is the human-meaningful "this is the fallback" flag.
 *   - The DEFAULT_CATEGORY_TOKEN value in 'category' is the technical
 *     enforcement that exactly one row carries the flag per kind.
 *   - Both together let users edit the default row (changing accounts)
 *     without losing its default status.
 *
 * Account FKs reference chart_of_accounts but are nullable per column —
 * not every mapping needs to specify every role. The resolver walks back
 * to the COA-code fallback for missing roles.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('inventory_account_mappings')) {
            return;
        }

        Schema::create('inventory_account_mappings', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')
                  ->references('id')
                  ->on('organizations')
                  ->restrictOnDelete();

            $table->string('item_kind', 30);

            // Sentinel '__default__' for the org-wide default row.
            // For category-specific rows, the actual category string
            // (e.g. 'grain', 'protein'). 100 chars caps category length
            // to a reasonable bound.
            $table->string('category', 100);

            $table->boolean('is_default')->default(false);

            // Account roles — all nullable, resolver falls back to COA
            // codes per role.
            $table->unsignedBigInteger('inventory_account_id')->nullable();
            $table->foreign('inventory_account_id', 'inv_mapping_inv_acct_fk')
                  ->references('id')->on('chart_of_accounts')->nullOnDelete();

            $table->unsignedBigInteger('gr_ir_account_id')->nullable();
            $table->foreign('gr_ir_account_id', 'inv_mapping_grir_acct_fk')
                  ->references('id')->on('chart_of_accounts')->nullOnDelete();

            $table->unsignedBigInteger('adjustment_account_id')->nullable();
            $table->foreign('adjustment_account_id', 'inv_mapping_adj_acct_fk')
                  ->references('id')->on('chart_of_accounts')->nullOnDelete();

            $table->unsignedBigInteger('cogs_account_id')->nullable();
            $table->foreign('cogs_account_id', 'inv_mapping_cogs_acct_fk')
                  ->references('id')->on('chart_of_accounts')->nullOnDelete();

            $table->unsignedBigInteger('ppv_account_id')->nullable();
            $table->foreign('ppv_account_id', 'inv_mapping_ppv_acct_fk')
                  ->references('id')->on('chart_of_accounts')->nullOnDelete();

            // Suspense for non-PR direct receipts.
            $table->unsignedBigInteger('suspense_account_id')->nullable();
            $table->foreign('suspense_account_id', 'inv_mapping_susp_acct_fk')
                  ->references('id')->on('chart_of_accounts')->nullOnDelete();

            // Audit columns.
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->foreign('created_by_user_id', 'inv_mapping_created_by_fk')
                  ->references('id')->on('users')->nullOnDelete();

            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->foreign('updated_by_user_id', 'inv_mapping_updated_by_fk')
                  ->references('id')->on('users')->nullOnDelete();

            $table->text('notes')->nullable();

            $table->timestamps();

            // One row per (org, kind, category). The default row uses
            // category='__default__'.
            $table->unique(
                ['organization_id', 'item_kind', 'category'],
                'inventory_mappings_org_kind_cat_unique',
            );

            $table->index(['organization_id', 'item_kind'], 'inventory_mappings_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_account_mappings');
    }
};
