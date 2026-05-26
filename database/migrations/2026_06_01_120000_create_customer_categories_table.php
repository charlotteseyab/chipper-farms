<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2a — customer_categories.
 *
 * Categorises customers for pricing, reporting, and AR ageing strata.
 * A customer belongs to one category (counterparties.customer_category_id
 * — added by a separate migration in this batch).
 *
 * Examples for a pig farm:
 *   - Wholesale (large butchers, restaurants)
 *   - Retail (walk-in customers, market stalls)
 *   - Government / institutional (schools, hospitals — payment delay tolerated)
 *   - Export
 *   - Internal (staff sales)
 *
 * Each category can carry default credit terms, default discount, and a
 * default revenue account override (so wholesale revenue posts to a
 * different GL account than retail revenue). Phase 2b's invoice posting
 * will use these as posting hints.
 *
 * Idempotent and additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_categories')) {
            return;
        }

        Schema::create('customer_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();

            $table->string('code', 30);
            // 'WHOLESALE' | 'RETAIL' | 'INSTITUTIONAL' | etc.

            $table->string('name', 100);
            $table->text('description')->nullable();

            // Posting hints for Phase 2b's invoice subscriber.
            $table->unsignedBigInteger('revenue_account_id')->nullable();
            // Override the default 4010 revenue account when this category sells.
            $table->foreign('revenue_account_id')
                  ->references('id')
                  ->on('chart_of_accounts')
                  ->nullOnDelete();

            $table->unsignedBigInteger('ar_account_id')->nullable();
            // Override the default 1110 AR control account.
            $table->foreign('ar_account_id')
                  ->references('id')
                  ->on('chart_of_accounts')
                  ->nullOnDelete();

            // Default credit terms.
            $table->unsignedSmallInteger('default_payment_terms_days')->nullable();
            // null means "use organization default" or "cash on delivery".

            $table->decimal('default_credit_limit', 15, 2)->nullable();
            $table->decimal('default_discount_percent', 5, 2)->default(0);

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('display_order')->default(0);

            $table->softDeletes();
            $table->timestamps();

            $table->unique(['organization_id', 'code'], 'customer_categories_org_code_unique');
            $table->index(['organization_id', 'is_active', 'display_order'], 'customer_categories_listing_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_categories');
    }
};
