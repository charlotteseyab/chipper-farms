<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Storefront Phase 1 — listings.
 *
 * The core commerce listing record. A listing is the PUBLIC, sellable
 * projection of an operational record (a pig, a piglet/grouped lot, a feed
 * type, manure, or a pure service line). It NEVER owns operational truth.
 *
 * Source link
 * -----------
 * `source_type` + `source_id` form a soft polymorphic reference to the origin
 * record (e.g. App\Models\PigManagement#42, App\Models\FeedType#3). There is
 * intentionally NO database FK to those operational tables:
 *   - pigs are SOFT-DELETED when sold; an FK + cascade would corrupt listing
 *     history,
 *   - operational tables predate org scoping and may carry legacy edge cases,
 *   - the brief mandates additive, deployment-safe schema.
 * Availability is reconciled in application code (ListingAvailabilityService),
 * not by the database.
 *
 * Pricing
 * -------
 * Money is decimal(18,2); per-kg rates and quantities decimal(18,4) — matching
 * pig_sales / feed_sales precision so finance math stays bcmath-safe and never
 * drifts when a listing later feeds an order → invoice.
 *
 * Statuses are plain strings with app-level constants on the model, matching
 * the pig_sales/feed_sales convention rather than native DB enums.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('storefront_listings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable()->index();

            // Taxonomy
            $table->unsignedBigInteger('category_id')->nullable()->index();
            $table->string('kind', 30)->index();            // live_pig|piglet_lot|breeding_stock|feed|manure|service

            // Soft source reference (no FK — see class docblock)
            $table->string('source_type', 191)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            // Public presentation
            $table->string('title', 200);
            $table->string('slug', 220);
            $table->string('reference', 60)->nullable();    // human ref e.g. LIST-000123
            $table->text('public_description')->nullable();
            $table->text('seller_note')->nullable();
            $table->json('badges')->nullable();             // ["new_arrival","health_certified"]

            // Commerce config
            $table->string('sale_mode', 20)->default('fixed');   // fixed|quote|hidden
            $table->string('pricing_basis', 20)->default('fixed'); // live|carcass|fixed
            $table->decimal('price_per_kg', 18, 4)->nullable();  // when basis live|carcass
            $table->decimal('fixed_price', 18, 2)->nullable();   // when basis fixed
            $table->string('currency_code', 3)->default('GHS');

            // Quantity / lot
            $table->decimal('quantity_available', 18, 4)->default(1); // head count, kg, or units
            $table->string('quantity_unit', 20)->default('unit');     // unit|head|kg|bag
            $table->decimal('estimated_weight_kg', 18, 4)->nullable(); // for live/carcass display

            // Product attributes for feed/manure/service (FeedType is thin)
            $table->string('sku', 60)->nullable();
            $table->string('pack_size', 60)->nullable();

            // Fulfilment
            $table->boolean('pickup_allowed')->default(true);
            $table->boolean('delivery_allowed')->default(false);
            $table->decimal('delivery_fee', 18, 2)->nullable();
            $table->text('fulfilment_notes')->nullable();

            // Lifecycle
            $table->string('status', 20)->default('draft')->index(); // draft|published|unpublished|archived|sold_out|reserved
            $table->timestamp('available_from')->nullable();
            $table->timestamp('available_until')->nullable();
            $table->timestamp('published_at')->nullable();

            // Metrics + audit
            $table->unsignedBigInteger('view_count')->default(0);
            $table->boolean('is_featured')->default(false)->index();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'slug'], 'storefront_listings_org_slug_uq');
            $table->index(['organization_id', 'status', 'kind'], 'storefront_listings_org_status_kind_idx');
            $table->index(['source_type', 'source_id'], 'storefront_listings_source_idx');

            $table->foreign('category_id')
                ->references('id')->on('storefront_categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_listings');
    }
};
