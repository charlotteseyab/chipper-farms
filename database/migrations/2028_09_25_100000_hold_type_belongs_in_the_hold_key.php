<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One hold per (order, listing, KIND OF HOLD).
 *
 * WHY THE KEY HAD TO GROW
 * -----------------------
 * Holds were unique on (order_id, listing_id) — one order reserves one listing
 * once. That was true when every hold reserved the same thing: a count of head.
 *
 * There are now three kinds, and they reserve different things:
 *
 *   quantity   → N of something
 *   exact_pigs → these specific animals
 *   carcass_kg → this many kilograms of meat
 *
 * A buyer can legitimately want two of them from the same listing in the same
 * order: two named animals to collect live, and fifty kilograms of meat cut
 * from whichever others suit the farm. Under the old key those two reservations
 * would collide on `updateOrCreate` and the second would overwrite the first —
 * silently, and in the direction that loses a reservation rather than one that
 * errors. The order would then be short of stock nobody noticed was gone.
 *
 * WHAT THE OLD KEY WAS PROTECTING
 * -------------------------------
 * The uniqueness matters: `placeForOrder()` uses `updateOrCreate` so a retried
 * checkout updates its own hold instead of claiming the stock a second time.
 * Widening the key by `hold_type` keeps that exactly — a retry still finds its
 * own row, because a retry places the same kinds of hold.
 *
 * `hold_type` is NOT NULL with a default of 'quantity', which every existing
 * row already is, so there is nothing to backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The column arrived defaulted in the pricing migration, but a row
        // written by older code in between could still be NULL, and a NULL in a
        // unique key is treated as distinct — which would quietly switch the
        // protection off for exactly those rows.
        DB::table('storefront_inventory_holds')->whereNull('hold_type')->update(['hold_type' => 'quantity']);

        // ORDER MATTERS ON MySQL/MariaDB.
        //
        // The old index is the one supporting the foreign key on `order_id`,
        // and the server refuses to drop an index a constraint still needs.
        // Creating the wider index first gives the constraint somewhere to go —
        // it leads on the same column — and the old one can then be dropped.
        // The reverse order fails with "needed in a foreign key constraint".
        Schema::table('storefront_inventory_holds', function (Blueprint $table) {
            $table->unique(['order_id', 'listing_id', 'hold_type'], 'storefront_holds_order_listing_type_unique');
        });

        Schema::table('storefront_inventory_holds', function (Blueprint $table) {
            $table->dropUnique('storefront_holds_order_listing_unique');
        });
    }

    public function down(): void
    {
        // Restoring the narrower key is only safe if no order holds a listing
        // in more than one way. Deleting the extras to make room would release
        // stock a customer is checking out with, without telling anyone.
        $offenders = DB::table('storefront_inventory_holds')
            ->select('order_id', 'listing_id')
            ->groupBy('order_id', 'listing_id')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        if ($offenders > 0) {
            throw new RuntimeException(
                "Cannot restore the one-hold-per-listing constraint: {$offenders} order(s) hold " .
                'a listing in more than one way. Resolve those orders first — dropping the extra ' .
                'holds here would release reserved stock silently.',
            );
        }

        // Same reasoning in reverse: restore the narrow index before removing
        // the wide one it is replacing.
        Schema::table('storefront_inventory_holds', function (Blueprint $table) {
            $table->unique(['order_id', 'listing_id'], 'storefront_holds_order_listing_unique');
        });

        Schema::table('storefront_inventory_holds', function (Blueprint $table) {
            $table->dropUnique('storefront_holds_order_listing_type_unique');
        });
    }
};
