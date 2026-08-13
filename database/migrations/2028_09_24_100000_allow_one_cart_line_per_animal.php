<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One cart line per ANIMAL, not per listing.
 *
 * WHY THE OLD CONSTRAINT HAD TO MOVE
 * ----------------------------------
 * `storefront_cart_items` was unique on (cart_id, listing_id): a basket could
 * hold a listing once, with a quantity. That is right for a bag of feed and
 * wrong for a pig. When a buyer picks two specific animals from a pool, they
 * have picked two different things at two different prices — a 120 kg pig is
 * not worth what an 80 kg pig is — and every total in the shop is computed as
 * `unit price × quantity`. Forcing both into one line means either rounding the
 * money or quoting a weight neither animal has.
 *
 * WIDENED, NOT DROPPED
 * --------------------
 * The obvious move is to delete the unique index. That would also delete the
 * guarantee it was carrying: nothing would stop a bag of feed appearing in the
 * same basket twice, which is a bug the constraint has been quietly preventing
 * since the carts were built.
 *
 * So it is widened by a discriminator instead. `line_key` is '0' for an
 * ordinary line — one per listing, exactly as before — and the pool row id for
 * an animal a buyer chose. Unique on (cart_id, listing_id, line_key) therefore
 * keeps the old rule for ordinary lines and adds "one line per animal" for
 * pigs. It is strictly stronger than what it replaces.
 *
 * A NULL discriminator would NOT do: both MariaDB and SQLite treat NULLs as
 * distinct in a unique index, so ordinary lines would stop being constrained at
 * all — the exact guarantee this migration exists to keep.
 *
 * The default fills every existing row, so there is no backfill to run and no
 * window in which the table is unconstrained.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storefront_cart_items', function (Blueprint $table) {
            $table->string('line_key', 40)->default('0')->after('listing_id');
        });

        // Belt and braces for any row written between the column landing and
        // this point by a process still running the old code.
        DB::table('storefront_cart_items')->whereNull('line_key')->update(['line_key' => '0']);

        Schema::table('storefront_cart_items', function (Blueprint $table) {
            $table->dropUnique('storefront_cart_items_cart_listing_uq');
            $table->unique(['cart_id', 'listing_id', 'line_key'], 'storefront_cart_items_cart_listing_line_uq');
        });
    }

    public function down(): void
    {
        // Going back means the basket may hold rows the old constraint forbids.
        // Collapsing them by deleting rows would throw away a buyer's basket
        // and, worse, would do it silently — so the duplicates are moved out of
        // the way by detaching them from their cart is not possible either
        // (cart_id is not nullable). The honest reversal is to restore the old
        // index only when the data still satisfies it, and to refuse loudly
        // when it does not, so an operator decides rather than this migration.
        $offenders = DB::table('storefront_cart_items')
            ->select('cart_id', 'listing_id')
            ->groupBy('cart_id', 'listing_id')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        if ($offenders > 0) {
            throw new RuntimeException(
                "Cannot restore the one-line-per-listing constraint: {$offenders} cart(s) hold " .
                'more than one line for the same listing. Resolve those baskets first — ' .
                'deleting them here would silently discard customer carts.',
            );
        }

        Schema::table('storefront_cart_items', function (Blueprint $table) {
            $table->dropUnique('storefront_cart_items_cart_listing_line_uq');
            $table->unique(['cart_id', 'listing_id'], 'storefront_cart_items_cart_listing_uq');
            $table->dropColumn('line_key');
        });
    }
};
