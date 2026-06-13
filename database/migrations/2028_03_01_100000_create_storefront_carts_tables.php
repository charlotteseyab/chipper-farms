<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Storefront Phase 3 — carts.
 *
 * A DB-backed cart per authenticated customer. One ACTIVE cart per user is
 * enforced by application code (CartService::activeFor) plus an index; on
 * checkout the cart is marked `converted`.
 *
 * Line items snapshot the listing's unit price at add-time (price_snapshot)
 * so a later price change on the listing does not silently re-price an
 * in-progress cart. Money decimal(18,2); quantity decimal(18,4) — finance
 * precision, ready to flow into an order then an invoice.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('storefront_carts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('status', 20)->default('active'); // active|converted|abandoned
            $table->timestamps();

            $table->index(['user_id', 'status'], 'storefront_carts_user_status_idx');
        });

        Schema::create('storefront_cart_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->unsignedBigInteger('cart_id')->index();
            $table->unsignedBigInteger('listing_id')->index();

            $table->decimal('quantity', 18, 4)->default(1);
            $table->decimal('price_snapshot', 18, 2)->default(0); // unit price at add-time
            $table->string('price_basis', 20)->default('fixed');  // fixed|live|carcass
            $table->timestamps();

            $table->unique(['cart_id', 'listing_id'], 'storefront_cart_items_cart_listing_uq');

            $table->foreign('cart_id')
                ->references('id')->on('storefront_carts')
                ->cascadeOnDelete();
            $table->foreign('listing_id')
                ->references('id')->on('storefront_listings')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_cart_items');
        Schema::dropIfExists('storefront_carts');
    }
};
