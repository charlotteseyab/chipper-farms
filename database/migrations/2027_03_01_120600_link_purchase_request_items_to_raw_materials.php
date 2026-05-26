<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 — link purchase_request_items to raw_materials.
 *
 * Before Phase 4, purchase_request_items had only item_name (free text)
 * and no relationship to the raw_materials catalog. This meant a PR for
 * "Maize 50kg bag" was a string that the receipt clerk had to manually
 * reconcile against the raw materials list — error-prone, and impossible
 * to automate the three-way match.
 *
 * Phase 4 adds an OPTIONAL raw_material_id FK:
 *   - When set: the PR item refers to a catalogued raw material. Receipts
 *     against this PR line automatically post to that material's inventory.
 *   - When NULL: the PR item is a service / equipment / non-catalogued
 *     item (e.g. "Maintenance — feed mill belt replacement"). These don't
 *     go through inventory; their receipts post directly to expense.
 *
 * The OPTIONALITY is deliberate. A PR for veterinary consultancy is a
 * legitimate procurement event with no inventory implication. Forcing
 * every PR line to have a raw material would mean creating placeholder
 * "Vet Service" raw_materials rows, which corrupts the inventory model.
 *
 * The new UI for PR creation will let users pick a raw material from a
 * searchable list OR leave the field blank and write a free-form
 * description. The item_name column is preserved for backward compat
 * and as the display label even when raw_material_id is set.
 *
 * Index: lookups by raw_material_id (to find all PRs for an item)
 * are common in the new "Receive against PR" workflow.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('purchase_request_items')) {
            return;
        }

        Schema::table('purchase_request_items', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_request_items', 'raw_material_id')) {
                $table->unsignedBigInteger('raw_material_id')
                      ->nullable()
                      ->after('purchase_request_id');

                $table->foreign('raw_material_id', 'pr_items_raw_material_fk')
                      ->references('id')
                      ->on('raw_materials')
                      ->nullOnDelete();

                $table->index('raw_material_id', 'pr_items_raw_material_idx');
            }

            // The "unit" column was absent on PR items but is required for
            // displaying expected quantities meaningfully in the receive
            // flow. Defaulting to NULL; the receipt screen uses the raw
            // material's unit when raw_material_id is set, else this
            // column.
            if (! Schema::hasColumn('purchase_request_items', 'unit')) {
                $table->string('unit', 20)
                      ->nullable()
                      ->after('quantity');
            }

            // Running totals of receipts against this PR line. Updated
            // by ReceiveAgainstPurchaseRequestService at commit time.
            // Allows the "Receive Against PR" screen to show "Received
            // 80 of 100" without aggregating stock logs at query time.
            if (! Schema::hasColumn('purchase_request_items', 'quantity_received')) {
                $table->decimal('quantity_received', 12, 4)
                      ->default(0)
                      ->after('quantity');
            }

            // Soft completion flag for the receive workflow. Set when
            // quantity_received >= quantity within tolerance, or when an
            // operator explicitly marks the line closed (over-receipt /
            // partial-with-no-more-coming).
            if (! Schema::hasColumn('purchase_request_items', 'is_receipt_closed')) {
                $table->boolean('is_receipt_closed')
                      ->default(false)
                      ->after('quantity_received');
            }

            // When the receipt was closed — informational.
            if (! Schema::hasColumn('purchase_request_items', 'receipt_closed_at')) {
                $table->timestamp('receipt_closed_at')
                      ->nullable()
                      ->after('is_receipt_closed');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('purchase_request_items')) {
            return;
        }

        Schema::table('purchase_request_items', function (Blueprint $table) {
            // Drop FK before column.
            try { $table->dropForeign('pr_items_raw_material_fk'); } catch (\Throwable) {}
            try { $table->dropIndex('pr_items_raw_material_idx');  } catch (\Throwable) {}

            foreach ([
                'raw_material_id',
                'unit',
                'quantity_received',
                'is_receipt_closed',
                'receipt_closed_at',
            ] as $col) {
                if (Schema::hasColumn('purchase_request_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
