<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the organizations table.
 *
 * This is the anchor for multi-tenant-ready architecture. Today Chipper Farms
 * is a single-organization deployment; the existence of this table allows every
 * subsequent business/finance table to reference an organization_id from day one
 * without any backfill work when a second tenant is onboarded.
 *
 * The organization currency defaults to GHS. base_currency is stored on the
 * organization (not a global config) so a future second tenant can have a
 * different home currency without code changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();

            // Human-readable unique slug, e.g. 'chipper-farms'. Used for URL routing
            // later if/when we introduce subdomain or path-based tenant routing.
            $table->string('slug', 60)->unique();

            $table->string('name', 150);
            $table->string('legal_name', 200)->nullable();

            // ISO 4217 currency code. GHS for Chipper Farms.
            // decimal_places captured so reporting layers do not assume 2.
            $table->char('base_currency', 3)->default('GHS');
            $table->unsignedTinyInteger('currency_decimals')->default(2);

            // ISO country code (2-letter). Not strictly needed today, but useful for
            // tax rules, VAT/GST differentiation, and reporting localisation later.
            $table->char('country_code', 2)->default('GH');

            // Timezone captured explicitly so scheduled jobs, period cutoffs and
            // reports respect tenant local time rather than server time.
            $table->string('timezone', 64)->default('Africa/Accra');

            // Contact / profile fields (optional at org creation time).
            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->string('tax_identifier', 50)->nullable();

            // Soft-deletable so an organization can be retired without hard-deleting
            // any historical data. Critical for audit and finance retention.
            $table->boolean('is_active')->default(true);
            $table->softDeletes();

            $table->timestamps();

            $table->index(['is_active', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};