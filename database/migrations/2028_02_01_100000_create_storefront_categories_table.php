<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Storefront Phase 1 — categories.
 *
 * Top-level commerce taxonomy for the storefront (Live Pigs, Piglets,
 * Breeding Stock, Feed, Manure, Services). Categories are additive and
 * entirely new — no operational table is touched.
 *
 * `kind` constrains which listing source types may attach to a category and
 * drives the public navigation + filter UI. It is a soft string (not an FK
 * to an enum table) to match the existing codebase convention (pig_sales,
 * feed_sales use plain string status/type columns).
 *
 * organization_id is nullable + indexed (not a hard FK) so the table behaves
 * exactly like the existing finance/sales tables: org scoping is applied in
 * application code via BelongsToOrganization, never via a database constraint
 * that could fail on legacy rows.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('storefront_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable()->index();

            $table->string('code', 60);                 // machine code, unique per org
            $table->string('name', 120);
            $table->string('slug', 140);                // URL segment, unique per org
            $table->string('kind', 30)->index();        // live_pig|piglet_lot|breeding_stock|feed|manure|service
            $table->unsignedBigInteger('parent_id')->nullable()->index();

            $table->string('icon', 60)->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true)->index();

            $table->timestamps();

            $table->unique(['organization_id', 'code'], 'storefront_categories_org_code_uq');
            $table->unique(['organization_id', 'slug'], 'storefront_categories_org_slug_uq');

            $table->foreign('parent_id')
                ->references('id')->on('storefront_categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_categories');
    }
};
