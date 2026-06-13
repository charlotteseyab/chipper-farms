<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Storefront Phase 2 — saved items / favourites.
 *
 * A favourite links an authenticated user to a listing. We key on user_id
 * (not counterparty) because the act of saving is a UI affordance tied to the
 * logged-in person; the AR counterparty link is derived via CustomerProfile
 * when needed. The unique (user_id, listing_id) makes "save" idempotent and
 * prevents duplicate rows under double-submit.
 *
 * FK to storefront_listings (new table, safe) with cascade so a deleted
 * listing tidies up its favourites. No FK to users (operational table) — the
 * relationship is resolved in app code, consistent with the additive,
 * deployment-safe convention used throughout the storefront.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('storefront_favourites', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('listing_id')->index();
            $table->timestamps();

            $table->unique(['user_id', 'listing_id'], 'storefront_favourites_user_listing_uq');

            $table->foreign('listing_id')
                ->references('id')->on('storefront_listings')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_favourites');
    }
};
