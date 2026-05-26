<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feed-sale finance path — feed_sales table.
 *
 * One row per feed sale. Mirrors the structure/role of pig_sales: holds the
 * sale terms + the finance-readiness columns (revenue + COGS posting status
 * and the journal-batch links), so postings are idempotent and auditable.
 *
 * Revenue/AR is posted by FeedSaleRevenueSubscriber; COGS + finished-feed
 * inventory decrement by FeedSaleCogsSubscriber. The cogs_amount is stamped at
 * the WAC value the inventory ledger removed (value_delta), so GL COGS and the
 * inventory decrement never drift.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('feed_sales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable()->index();

            $table->unsignedBigInteger('feed_type_id')->index();
            $table->decimal('quantity_kg', 18, 4);
            $table->decimal('unit_price', 18, 4);          // price per kg
            $table->decimal('sale_total', 18, 2);          // quantity × unit_price
            $table->date('sale_date');

            $table->string('channel', 20)->default('direct'); // direct | storefront
            $table->string('payment_status', 20)->default('pending'); // pending | paid | partial
            $table->string('payment_method', 50)->nullable();

            $table->unsignedBigInteger('counterparty_id')->nullable();
            $table->string('buyer_name', 200)->nullable();
            $table->string('buyer_contact', 200)->nullable();

            // Revenue posting readiness.
            $table->unsignedBigInteger('revenue_journal_batch_id')->nullable();
            $table->string('revenue_posting_status', 20)->default('pending'); // pending|posted|skipped|failed
            $table->string('revenue_posting_error', 500)->nullable();

            // COGS posting readiness.
            $table->decimal('cogs_amount', 18, 2)->nullable();
            $table->unsignedBigInteger('cogs_journal_batch_id')->nullable();
            $table->unsignedBigInteger('inventory_movement_id')->nullable();
            $table->string('cogs_posting_status', 20)->default('pending'); // pending|posted|skipped|failed
            $table->string('cogs_posting_error', 500)->nullable();

            $table->unsignedBigInteger('sold_by_user_id')->nullable();
            $table->string('notes', 2000)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'sale_date'], 'feed_sales_org_date_idx');
            $table->index('channel', 'feed_sales_channel_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_sales');
    }
};
