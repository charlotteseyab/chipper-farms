<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Live/carcass pricing and purchase types across the storefront.
 *
 * WHAT THIS ENABLES
 * -----------------
 * A pig listing stops being "one price" and becomes a pool with two ways to buy
 * it: LIVE (the buyer picks specific animals and pays for their actual weight)
 * or CARCASS (the buyer asks for a number of kilograms and the farm decides
 * which animals to slaughter). Those are different transactions with different
 * fulfilment, and the schema has to hold both.
 *
 * HEAD COUNT AND KILOGRAMS ARE KEPT APART
 * ---------------------------------------
 * `quantity_available` stays what it always was — a count of things. Carcass
 * capacity is a separate column in kilograms. Overloading one number to mean
 * "5 pigs" in one listing and "400 kg" in another is how a shop ends up
 * comparing head to weight and overselling both.
 *
 * SNAPSHOTS, NOT LOOKUPS
 * ----------------------
 * Prices per kilo are copied onto the listing, the cart line and the order line
 * as they were at the time. A farm that changes its live price next week must
 * not silently reprice an order placed today, and an invoice must be able to
 * explain itself years later. Same reasoning as the tax snapshots in Phase 12b.
 *
 * ALL ADDITIVE
 * ------------
 * Every column is nullable or defaulted. Nothing existing is dropped or
 * repurposed, so listings, carts and orders created before this migration keep
 * working untouched — `purchase_type` defaults to the behaviour they already
 * had.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storefront_listings', function (Blueprint $table) {
            // Which operational stock backs this listing. 'manual' means none —
            // staff typed the details and no animal is linked.
            $table->string('source_category', 30)->nullable()->after('kind');

            // The farm's setup prices as they stood when the listing was built.
            $table->decimal('live_price_per_kg_snapshot', 15, 4)->nullable()->after('fixed_price');
            $table->decimal('carcass_price_per_kg_snapshot', 15, 4)->nullable()->after('live_price_per_kg_snapshot');

            // Derived from the linked animals' LATEST recorded weights, so the
            // shop can show a range rather than one misleading figure.
            $table->decimal('total_live_weight_kg_snapshot', 15, 4)->nullable()->after('carcass_price_per_kg_snapshot');
            $table->decimal('min_pig_weight_kg_snapshot', 15, 4)->nullable()->after('total_live_weight_kg_snapshot');
            $table->decimal('max_pig_weight_kg_snapshot', 15, 4)->nullable()->after('min_pig_weight_kg_snapshot');
            $table->decimal('min_estimated_pig_price', 15, 2)->nullable()->after('max_pig_weight_kg_snapshot');
            $table->decimal('max_estimated_pig_price', 15, 2)->nullable()->after('min_estimated_pig_price');
            $table->decimal('estimated_total_amount', 15, 2)->nullable()->after('max_estimated_pig_price');

            // Carcass capacity in KILOGRAMS, deliberately not quantity_available.
            $table->decimal('carcass_capacity_kg', 15, 4)->nullable()->after('estimated_total_amount');

            // Did staff override the setup price for this listing?
            $table->boolean('pricing_override')->default(false)->after('carcass_capacity_kg');
            $table->string('pricing_source', 20)->nullable()->after('pricing_override');

            $table->index('source_category', 'sf_listings_source_category_idx');
        });

        Schema::table('storefront_cart_items', function (Blueprint $table) {
            $table->string('purchase_type', 20)->nullable()->after('listing_id');
            $table->string('source_category', 30)->nullable()->after('purchase_type');
            $table->decimal('requested_kg', 15, 4)->nullable()->after('source_category');
            $table->decimal('price_per_kg_snapshot', 15, 4)->nullable()->after('requested_kg');
            $table->decimal('line_weight_kg', 15, 4)->nullable()->after('price_per_kg_snapshot');
            $table->json('selected_listing_pig_ids')->nullable()->after('line_weight_kg');
            $table->json('selected_pig_weight_snapshot')->nullable()->after('selected_listing_pig_ids');
            $table->json('calculation_snapshot')->nullable()->after('selected_pig_weight_snapshot');
        });

        Schema::table('storefront_order_items', function (Blueprint $table) {
            $table->string('purchase_type', 20)->nullable()->after('kind');
            $table->string('source_category', 30)->nullable()->after('purchase_type');
            $table->decimal('requested_kg', 15, 4)->nullable()->after('source_category');
            $table->decimal('price_per_kg_snapshot', 15, 4)->nullable()->after('requested_kg');
            $table->decimal('line_weight_kg', 15, 4)->nullable()->after('price_per_kg_snapshot');
            $table->json('selected_listing_pig_ids')->nullable()->after('line_weight_kg');
            $table->json('selected_pig_weight_snapshot')->nullable()->after('selected_listing_pig_ids');
            $table->json('calculation_snapshot')->nullable()->after('selected_pig_weight_snapshot');
        });

        Schema::table('storefront_inventory_holds', function (Blueprint $table) {
            // 'quantity' is what every existing hold is, so that is the default
            // and no backfill is needed.
            $table->string('hold_type', 20)->default('quantity')->after('quantity');
            $table->decimal('requested_kg', 15, 4)->nullable()->after('hold_type');
            $table->json('selected_listing_pig_ids')->nullable()->after('requested_kg');

            $table->index('hold_type', 'sf_holds_hold_type_idx');
        });

        Schema::table('storefront_order_item_pig_allocations', function (Blueprint $table) {
            $table->string('purchase_type', 20)->nullable()->after('pig_id');
            $table->decimal('allocated_live_weight_kg', 15, 4)->nullable()->after('purchase_type');
            $table->decimal('allocated_carcass_weight_kg', 15, 4)->nullable()->after('allocated_live_weight_kg');
            $table->decimal('allocated_sale_amount', 15, 2)->nullable()->after('allocated_carcass_weight_kg');

            // Did the CUSTOMER pick this animal (live), or the farm (carcass)?
            // It changes what a manager may substitute and what the audit trail
            // has to explain.
            $table->boolean('customer_selected')->default(false)->after('allocated_sale_amount');
            $table->json('allocation_snapshot')->nullable()->after('customer_selected');
        });
    }

    public function down(): void
    {
        Schema::table('storefront_order_item_pig_allocations', function (Blueprint $table) {
            $table->dropColumn([
                'purchase_type',
                'allocated_live_weight_kg',
                'allocated_carcass_weight_kg',
                'allocated_sale_amount',
                'customer_selected',
                'allocation_snapshot',
            ]);
        });

        Schema::table('storefront_inventory_holds', function (Blueprint $table) {
            $table->dropIndex('sf_holds_hold_type_idx');
            $table->dropColumn(['hold_type', 'requested_kg', 'selected_listing_pig_ids']);
        });

        Schema::table('storefront_order_items', function (Blueprint $table) {
            $table->dropColumn([
                'purchase_type', 'source_category', 'requested_kg', 'price_per_kg_snapshot',
                'line_weight_kg', 'selected_listing_pig_ids', 'selected_pig_weight_snapshot',
                'calculation_snapshot',
            ]);
        });

        Schema::table('storefront_cart_items', function (Blueprint $table) {
            $table->dropColumn([
                'purchase_type', 'source_category', 'requested_kg', 'price_per_kg_snapshot',
                'line_weight_kg', 'selected_listing_pig_ids', 'selected_pig_weight_snapshot',
                'calculation_snapshot',
            ]);
        });

        Schema::table('storefront_listings', function (Blueprint $table) {
            $table->dropIndex('sf_listings_source_category_idx');
            $table->dropColumn([
                'source_category',
                'live_price_per_kg_snapshot', 'carcass_price_per_kg_snapshot',
                'total_live_weight_kg_snapshot', 'min_pig_weight_kg_snapshot',
                'max_pig_weight_kg_snapshot', 'min_estimated_pig_price',
                'max_estimated_pig_price', 'estimated_total_amount',
                'carcass_capacity_kg', 'pricing_override', 'pricing_source',
            ]);
        });
    }
};
