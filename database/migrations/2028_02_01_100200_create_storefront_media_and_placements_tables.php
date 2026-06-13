<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Storefront Phase 1 — listing media + homepage placements.
 *
 * Two new, fully-owned storefront tables. Because both reference only the
 * brand-new storefront_listings table (no legacy data), they use real FKs
 * with cascade — safe and desirable here.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('storefront_listing_media', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->unsignedBigInteger('listing_id')->index();

            $table->string('type', 10)->default('image');   // image|video
            $table->string('disk', 30)->default('public');
            $table->string('path', 500);
            $table->string('alt_text', 200)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);

            $table->timestamps();

            $table->index(['listing_id', 'sort_order'], 'storefront_media_listing_sort_idx');

            $table->foreign('listing_id')
                ->references('id')->on('storefront_listings')
                ->cascadeOnDelete();
        });

        Schema::create('storefront_placements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable()->index();

            // Placement slot code, e.g. featured_home_hero, featured_live_pigs.
            $table->string('code', 60)->index();
            $table->unsignedBigInteger('listing_id')->nullable()->index();

            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('note', 200)->nullable();

            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'code', 'position'], 'storefront_placements_org_code_pos_idx');

            $table->foreign('listing_id')
                ->references('id')->on('storefront_listings')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_placements');
        Schema::dropIfExists('storefront_listing_media');
    }
};
