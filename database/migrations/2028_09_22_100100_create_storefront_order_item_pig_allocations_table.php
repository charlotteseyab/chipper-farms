<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The exact pigs an approver chose to fulfil one order line.
 *
 * WHY THIS IS SEPARATE FROM `storefront_listing_pigs`
 * ---------------------------------------------------
 * The pool table answers "which pigs could this listing draw from". This one
 * answers "which pigs did a named person commit to this order, when". They have
 * different lifetimes: a pool row outlives many orders, and an allocation is
 * released when an order is cancelled while the pool row returns to available.
 *
 * WHY NOT `storefront_order_items.posted_sale_id`
 * -----------------------------------------------
 * That column holds ONE sale id. A single order line for three pigs produces
 * THREE `pig_sales` rows, so it cannot express the mapping. It is left as it is
 * for single-pig lines and for every non-pig line; multi-pig mapping lives
 * here, one row per animal.
 *
 * STILL NOT A SALES LEDGER
 * ------------------------
 * `pig_sale_id` is filled in after `RecordPigSaleService` has created the real
 * sale. It is a pointer for tracing an order line back to `/sales`, never the
 * record of the sale itself.
 *
 * UNIQUENESS
 * ----------
 * `(storefront_order_item_id, pig_id)` is unique: selecting the same animal
 * twice for one line is always a bug, and it is the check that stops a
 * double-submitted selection form inflating a line.
 *
 * "One ACTIVE allocation per pig across all orders" is the stronger invariant
 * and is not expressible as an index here for the same reason as the pool
 * table — no partial unique indexes on MySQL/MariaDB. It is enforced in
 * `StorefrontPigAllocationService` under `lockForUpdate()`, and proven by the
 * concurrency tests rather than asserted by the schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('storefront_order_item_pig_allocations')) {
            return;
        }

        Schema::create('storefront_order_item_pig_allocations', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('organization_id')->nullable()->index();

            $table->unsignedBigInteger('storefront_order_id');
            $table->unsignedBigInteger('storefront_order_item_id');
            $table->unsignedBigInteger('storefront_listing_id')->nullable();
            $table->unsignedBigInteger('storefront_listing_pig_id')->nullable();
            $table->unsignedBigInteger('pig_id');

            // Null until approval creates the sale.
            $table->unsignedBigInteger('pig_sale_id')->nullable();

            $table->unsignedBigInteger('selected_by_user_id')->nullable();
            $table->unsignedBigInteger('approved_by_user_id')->nullable();

            $table->timestamp('selected_at')->nullable();
            $table->timestamp('approved_at')->nullable();

            // selected → approved → sold, or released/rejected.
            $table->string('status', 20)->default('selected');

            $table->string('release_reason', 300)->nullable();

            $table->timestamps();

            $table->index('storefront_order_id', 'sf_pig_alloc_order_idx');
            $table->index('storefront_order_item_id', 'sf_pig_alloc_order_item_idx');
            $table->index('storefront_listing_id', 'sf_pig_alloc_listing_idx');
            $table->index('pig_id', 'sf_pig_alloc_pig_idx');
            $table->index(['pig_id', 'status'], 'sf_pig_alloc_pig_status_idx');
            $table->index('pig_sale_id', 'sf_pig_alloc_sale_idx');
            $table->index('status', 'sf_pig_alloc_status_idx');

            $table->unique(
                ['storefront_order_item_id', 'pig_id'],
                'sf_pig_alloc_item_pig_unique'
            );

            $table->foreign('storefront_order_id', 'sf_pig_alloc_order_fk')
                ->references('id')->on('storefront_orders')->cascadeOnDelete();

            $table->foreign('storefront_order_item_id', 'sf_pig_alloc_item_fk')
                ->references('id')->on('storefront_order_items')->cascadeOnDelete();

            $table->foreign('pig_id', 'sf_pig_alloc_pig_fk')
                ->references('id')->on('pigs_management')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_order_item_pig_allocations');
    }
};
