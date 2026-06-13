<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Storefront Phase 3 — order payments + event log.
 *
 * storefront_order_payments records each payment attempt/transaction against an
 * order (a Paystack init+verify, or a COD collection). `provider_reference` is
 * the Paystack reference and is unique per provider so a re-delivered webhook
 * cannot create a duplicate payment. raw_response keeps the verified gateway
 * payload for audit/reconciliation.
 *
 * storefront_order_events is an append-only audit trail of every status change
 * and significant action on an order.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('storefront_order_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->unsignedBigInteger('order_id')->index();

            $table->string('provider', 20)->default('paystack'); // paystack|cod|manual
            $table->string('reference', 80);                     // our internal payment reference
            $table->string('provider_reference', 120)->nullable(); // Paystack transaction reference
            $table->decimal('amount', 18, 2)->default(0);
            $table->string('currency_code', 3)->default('GHS');
            $table->string('status', 20)->default('initiated'); // initiated|submitted|verified|failed|refunded
            $table->json('raw_response')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_reference'], 'storefront_payments_provider_ref_uq');
            $table->index(['order_id', 'status'], 'storefront_payments_order_status_idx');

            $table->foreign('order_id')
                ->references('id')->on('storefront_orders')
                ->cascadeOnDelete();
        });

        Schema::create('storefront_order_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->unsignedBigInteger('order_id')->index();

            $table->string('type', 40);          // status_change|payment|note|system
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->timestamps();

            $table->foreign('order_id')
                ->references('id')->on('storefront_orders')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_order_events');
        Schema::dropIfExists('storefront_order_payments');
    }
};
