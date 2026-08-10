<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Checkout holds (Phase 10, blueprint §36 and §37).
 *
 * WHAT THIS PREVENTS
 * ------------------
 * §37: "this prevents a customer paying for stock that another customer bought
 * in the same window". Until now, nothing at all happened to inventory between
 * checkout and staff confirmation. Two buyers could both check out the last
 * boar, both be sent to Paystack, and both pay — and the second one would find
 * out at confirmation, after their money had left. The farm would then be
 * refunding a customer for stock it never had.
 *
 * A TABLE, NOT A `held` LISTING STATE
 * -----------------------------------
 * §36 offers either: "add `held` or a separate `storefront_inventory_holds`
 * table rather than overloading `reserved`". The table is the right half of
 * that choice, and the reason is quantities. A listing with ten bags of feed and
 * a hold on two is not "held" — it is eight sellable and two spoken for. A
 * status column can only say the whole listing is or is not available, so it
 * would either block the other eight or protect neither. Rows carrying a
 * quantity say exactly what is spoken for and by whom.
 *
 * It also keeps §36's listing state machine untouched, which matters because
 * `ListingService` enforces legal transitions and other work depends on that
 * table not growing new edges.
 *
 * A NULL EXPIRY MEANS "UNTIL SOMEONE DECIDES"
 * -------------------------------------------
 * §37 sets expiry at Paystack initialisation — a buyer who wanders off mid-
 * payment must not park stock forever. A cash order is different: it is waiting
 * on a member of staff, and §37's own table says the hold "remains until staff
 * decision". Expiring those after thirty minutes would mean routine COD orders
 * failing confirmation because the farm answered the phone slowly.
 *
 * So `expires_at` is set for orders awaiting payment and left null for orders
 * awaiting a person. Null is not "never expires" by accident; it is "this one is
 * not on a clock", and the expiry sweep skips it by construction.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('storefront_inventory_holds')) {
            return;
        }

        Schema::create('storefront_inventory_holds', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('listing_id');
            $table->unsignedBigInteger('order_id');

            // decimal(18,4) to match storefront_listings.quantity_available and
            // storefront_order_items.quantity. A narrower type here would
            // truncate a fractional weight on the way in and hold the wrong
            // amount.
            $table->decimal('quantity', 18, 4);

            // active → consumed (staff confirmed and stock was decremented)
            //        → released (cancelled, failed, or the buyer walked away)
            //        → expired  (the clock ran out on an unpaid order)
            $table->string('status', 20)->default('active');

            // Null means "not on a clock" — see the class docblock.
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolution_reason', 200)->nullable();

            $table->timestamps();

            // One hold row per listing per order. This is the idempotency
            // anchor: a retried checkout, a double-submitted form or a repeated
            // service call cannot hold the same stock twice for one order.
            $table->unique(['order_id', 'listing_id'], 'storefront_holds_order_listing_unique');

            // The availability query reads exactly this: active holds on a
            // listing that have not expired.
            $table->index(['listing_id', 'status', 'expires_at'], 'storefront_holds_availability_idx');

            // The expiry sweep reads exactly this.
            $table->index(['status', 'expires_at'], 'storefront_holds_expiry_idx');

            $table->foreign('listing_id')
                ->references('id')->on('storefront_listings')
                ->cascadeOnDelete();

            $table->foreign('order_id')
                ->references('id')->on('storefront_orders')
                ->cascadeOnDelete();

            $table->foreign('organization_id')
                ->references('id')->on('organizations')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_inventory_holds');
    }
};
