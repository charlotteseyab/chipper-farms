<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Storefront Phase 5 — fulfilment + COD settlement support (additive).
 *
 *  - storefront_order_items gains posted_sale_type / posted_sale_id: the
 *    durable link to the FeedSale / PigSale the Phase-4 orchestrator created
 *    for the line. Completion uses this to settle the exact underlying sale
 *    (COD cash receipt) without re-deriving it.
 *  - storefront_orders gains completed_at / completed_by_user_id for the
 *    fulfilment audit trail.
 *
 * No destructive change; safe on a live table.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('storefront_order_items', function (Blueprint $table) {
            $table->string('posted_sale_type', 191)->nullable()->after('source_id');
            $table->unsignedBigInteger('posted_sale_id')->nullable()->after('posted_sale_type');
            $table->index(['posted_sale_type', 'posted_sale_id'], 'storefront_order_items_posted_sale_idx');
        });

        Schema::table('storefront_orders', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('placed_at');
            $table->unsignedBigInteger('completed_by_user_id')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('storefront_order_items', function (Blueprint $table) {
            $table->dropIndex('storefront_order_items_posted_sale_idx');
            $table->dropColumn(['posted_sale_type', 'posted_sale_id']);
        });

        Schema::table('storefront_orders', function (Blueprint $table) {
            $table->dropColumn(['completed_at', 'completed_by_user_id']);
        });
    }
};
