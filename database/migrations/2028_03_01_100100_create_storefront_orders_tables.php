<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Storefront Phase 3 — orders.
 *
 * The order is the commerce source-of-truth and (from Phase 4) a clean source
 * module for finance. Phase 3 creates and transitions orders but posts NOTHING
 * to finance — the finance-link columns below are written by Phase 4
 * subscribers when the right status is reached.
 *
 * Critical commercial rule encoded by `status` + `payment_status`:
 *   - A successful Paystack payment moves payment_status to paystack_verified
 *     and status to payment_confirmed — NOT confirmed. Staff must still confirm.
 *   - A COD order sits at pending_confirmation until staff confirm; cash is
 *     recorded only when actually collected.
 *
 * Money decimal(18,2); item quantity/weight decimal(18,4).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('storefront_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->string('reference', 60);

            // Buyer (always an authenticated customer in v1 → real counterparty).
            $table->unsignedBigInteger('counterparty_id')->nullable()->index();
            $table->unsignedBigInteger('customer_user_id')->nullable()->index();
            $table->unsignedBigInteger('quote_id')->nullable(); // set when converted from a quote (Phase 5)

            // Lifecycle.
            $table->string('status', 30)->default('pending_confirmation')->index();
            $table->string('payment_status', 30)->default('unpaid')->index();
            $table->string('payment_method', 20)->default('cod'); // cod|paystack
            $table->string('fulfilment_method', 20)->default('pickup'); // pickup|delivery

            // Delivery snapshot (immutable copy; address may change later).
            $table->unsignedBigInteger('shipping_address_id')->nullable();
            $table->string('ship_to_name', 200)->nullable();
            $table->string('ship_to_phone', 60)->nullable();
            $table->string('ship_address_line_1', 200)->nullable();
            $table->string('ship_address_line_2', 200)->nullable();
            $table->string('ship_city', 120)->nullable();
            $table->string('ship_region', 120)->nullable();
            $table->string('ship_ghana_post_gps', 40)->nullable();

            // Money.
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('delivery_fee', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->string('currency_code', 3)->default('GHS');

            // Notes + audit.
            $table->text('customer_note')->nullable();
            $table->text('staff_notes')->nullable();
            $table->unsignedBigInteger('confirmed_by_user_id')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedBigInteger('cancelled_by_user_id')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->timestamp('placed_at')->nullable();

            // Finance links — written by Phase 4, additive now to avoid a later
            // ALTER on a live orders table.
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('receipt_id')->nullable();
            $table->string('finance_posting_status', 20)->default('pending'); // pending|posted|skipped|failed
            $table->string('finance_posting_error', 500)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'reference'], 'storefront_orders_org_ref_uq');
            $table->index(['organization_id', 'status'], 'storefront_orders_org_status_idx');
        });

        Schema::create('storefront_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->unsignedBigInteger('order_id')->index();

            // Soft listing link + immutable source snapshot.
            $table->unsignedBigInteger('listing_id')->nullable()->index();
            $table->string('source_type', 191)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            $table->string('kind', 30)->nullable();
            $table->string('description', 300);
            $table->decimal('quantity', 18, 4)->default(1);
            $table->string('quantity_unit', 20)->default('unit');
            $table->string('price_basis', 20)->default('fixed');
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('line_total', 18, 2)->default(0);
            $table->decimal('weight_kg', 18, 4)->nullable();

            $table->timestamps();

            $table->foreign('order_id')
                ->references('id')->on('storefront_orders')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_order_items');
        Schema::dropIfExists('storefront_orders');
    }
};
