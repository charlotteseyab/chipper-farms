<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2a — customer_profiles.
 *
 * Decision 2: unified counterparties + customer_profiles. Storefront-
 * specific data lives here, NOT on counterparties.
 *
 * One profile per counterparty. The relationship is 1:1 because the
 * profile is just a behavioural extension — finance customer data lives
 * on counterparties, marketing/storefront preferences live here.
 *
 * Phase 2a creates the schema. Phase 6 (storefront) populates it during
 * checkout. Until then, profiles are only created by accountants via the
 * customer master UI for customers who explicitly opt into marketing
 * communications (rare for B2B; unused for now).
 *
 * Idempotent and additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_profiles')) {
            return;
        }

        Schema::create('customer_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();

            $table->unsignedBigInteger('counterparty_id');
            $table->foreign('counterparty_id')
                  ->references('id')
                  ->on('counterparties')
                  ->cascadeOnDelete();

            // Storefront authentication.
            // Linked to users.id ONLY when a counterparty has a storefront login.
            // Most counterparties (B2B, walk-in, etc.) won't have one.
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();

            // Marketing preferences (Phase 6 will use these for opt-in flows).
            $table->boolean('email_subscribed')->default(false);
            $table->boolean('sms_subscribed')->default(false);
            $table->boolean('whatsapp_subscribed')->default(false);

            // Storefront preferences.
            $table->unsignedBigInteger('default_shipping_address_id')->nullable();
            $table->foreign('default_shipping_address_id')
                  ->references('id')
                  ->on('counterparty_addresses')
                  ->nullOnDelete();

            // Loyalty / referral (provisional — not used in Phase 2/6 unless
            // explicitly enabled in settings).
            $table->string('referral_code', 50)->nullable();
            $table->unsignedInteger('loyalty_points')->default(0);

            // Behavioural data (populated by storefront orders in Phase 6).
            $table->timestamp('first_order_at')->nullable();
            $table->timestamp('last_order_at')->nullable();
            $table->unsignedInteger('order_count')->default(0);
            $table->decimal('lifetime_value', 15, 2)->default(0);

            $table->softDeletes();
            $table->timestamps();

            $table->unique('counterparty_id', 'customer_profiles_counterparty_unique');
            $table->index(['organization_id', 'email_subscribed'], 'customer_profiles_email_subscribed_idx');
            $table->index('user_id', 'customer_profiles_user_idx');
            $table->index('referral_code', 'customer_profiles_referral_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_profiles');
    }
};
