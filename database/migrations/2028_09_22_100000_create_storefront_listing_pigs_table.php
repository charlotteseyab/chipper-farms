<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The pool of Standard Stock pigs backing a storefront listing.
 *
 * WHY THIS TABLE EXISTS
 * ---------------------
 * `storefront_listings.source_type` / `source_id` can name exactly ONE source
 * row, so a pig-backed listing could only ever be one animal. A farm wants to
 * publish "Market-ready live pigs — 5 head" and let the buyer take three of
 * them, with staff choosing WHICH three at approval. That is a many-to-many
 * between a listing and the pigs it may draw from, which needs its own table.
 *
 * `source_type`/`source_id` is deliberately left in place and still populated
 * for single-pig listings, so every existing listing, order and finance
 * subscriber keeps working unchanged. This table is additive.
 *
 * NOT A SECOND SALES LEDGER
 * -------------------------
 * A row here records that a pig is *available to be sold through this listing*,
 * and later which order took it. The sale itself is a `pig_sales` row created by
 * `RecordPigSaleService`, and `/sales` remains the only place a sold pig is
 * recorded. `pig_sale_id` here is a back-reference for tracing, never a
 * substitute.
 *
 * ACTIVE UNIQUENESS IS ENFORCED IN THE SERVICE, NOT THE INDEX
 * -----------------------------------------------------------
 * The invariant is "a pig may be in at most one ACTIVE pool at a time", where
 * active means available/held/allocated. MySQL and MariaDB have no partial or
 * filtered unique indexes, so that cannot be expressed as a constraint here
 * without a generated column trick that would then behave differently on
 * SQLite. `StorefrontPigPoolService` enforces it inside a transaction with
 * `lockForUpdate()`, and `(pig_id, status)` is indexed so that check is cheap.
 *
 * `(storefront_listing_id, pig_id)` IS unique, because the same pig twice in
 * one pool is meaningless regardless of status and is a straightforward
 * programming error worth catching at the database.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('storefront_listing_pigs')) {
            return;
        }

        Schema::create('storefront_listing_pigs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->unsignedBigInteger('storefront_listing_id');
            $table->unsignedBigInteger('pig_id');

            // available → held → allocated → sold, or removed at any point.
            $table->string('status', 20)->default('available');

            $table->unsignedBigInteger('added_by_user_id')->nullable();
            $table->unsignedBigInteger('removed_by_user_id')->nullable();

            $table->unsignedBigInteger('allocated_order_id')->nullable();
            $table->unsignedBigInteger('allocated_order_item_id')->nullable();
            $table->unsignedBigInteger('pig_sale_id')->nullable();

            $table->timestamp('added_at')->nullable();
            $table->timestamp('allocated_at')->nullable();
            $table->timestamp('sold_at')->nullable();
            $table->timestamp('removed_at')->nullable();

            // Why a pig left the pool: a manual sale, a death, staff pulling it
            // back. Free text because the reasons are not a closed set and the
            // value is read by a human, not branched on.
            $table->string('removal_reason', 300)->nullable();

            $table->timestamps();

            $table->index('storefront_listing_id', 'sf_listing_pigs_listing_idx');
            $table->index('pig_id', 'sf_listing_pigs_pig_idx');
            $table->index('status', 'sf_listing_pigs_status_idx');
            $table->index(['pig_id', 'status'], 'sf_listing_pigs_pig_status_idx');
            $table->index('allocated_order_id', 'sf_listing_pigs_order_idx');
            $table->index('allocated_order_item_id', 'sf_listing_pigs_order_item_idx');
            $table->index('pig_sale_id', 'sf_listing_pigs_sale_idx');

            $table->unique(['storefront_listing_id', 'pig_id'], 'sf_listing_pigs_listing_pig_unique');

            $table->foreign('storefront_listing_id', 'sf_listing_pigs_listing_fk')
                ->references('id')->on('storefront_listings')->cascadeOnDelete();

            $table->foreign('pig_id', 'sf_listing_pigs_pig_fk')
                ->references('id')->on('pigs_management')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_listing_pigs');
    }
};
