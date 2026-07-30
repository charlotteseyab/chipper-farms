<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sales returns — the anchor for reversing a sale (CF-P0-008).
 *
 * WHY A TABLE RATHER THAN A FLAG ON THE SALE
 * ------------------------------------------
 * A return is a transaction in its own right, not an attribute of the sale:
 *
 *  - it happens at a different TIME, in a different accounting period. The
 *    reversal posts today, not on the sale date, so it cannot be modelled as
 *    "the sale un-happened";
 *  - there can be MORE THAN ONE against a single sale (partial returns);
 *  - it needs its own idempotency anchor. InventoryService keys movements on
 *    (source_type, source_id) with a unique constraint, so putting stock back
 *    needs a stable id that is not the sale's — otherwise a second return
 *    against the same sale would be rejected as a duplicate movement.
 *
 * That last point is the practical reason this table exists first: without it
 * there is no safe way to make the subledger write idempotent.
 *
 * ORGANIZATION_ID IS NOT NULL
 * ---------------------------
 * Deliberate, and a departure from most existing tables. CF-P0-005 found 41 of
 * 128 tables carrying no organisation column and 74 more where it is nullable,
 * which forced tenancy to be derived or to fail closed. A new table has no
 * legacy rows to accommodate, so the constraint is enforced from the start
 * rather than backfilled later.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales_returns')) {
            return;
        }

        Schema::create('sales_returns', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('organization_id');

            // Human-facing reference, allocated through NumberingService so it
            // is concurrency-safe (CF-P1-014). Nullable only so the row can be
            // created before numbering in a failure path; the service always
            // sets it.
            $table->string('return_number', 40)->nullable();

            // Polymorphic: FeedSale or PigSale. Kept polymorphic because the two
            // reverse through completely different subledgers — inventory
            // movements vs biological assets — and a shared foreign key would
            // imply an interchangeability that does not exist.
            $table->string('returnable_type', 191);
            $table->unsignedBigInteger('returnable_id');

            // The storefront order that triggered this, when there is one. A
            // return can also be raised directly against a sale with no order.
            $table->unsignedBigInteger('storefront_order_id')->nullable();

            // Quantity returned. For a pig this is always 1 — an animal is not
            // divisible — but the column is shared so partial feed returns and
            // whole-pig returns use one code path.
            $table->decimal('quantity', 18, 4);

            // Proportion of the original sale being returned, 0 < p <= 1.
            // Stored rather than recomputed so the posted amounts can always be
            // reconciled back to the decision that produced them, even if the
            // sale is later amended.
            $table->decimal('proportion', 9, 8)->default('1.00000000');

            /*
             * RESTOCK — the business decision the accounting depends on.
             *
             * true:  the goods physically come back and re-enter inventory at
             *        their original cost. Dr Inventory / Cr COGS.
             * false: the goods do NOT come back (spoiled feed, a pig that died
             *        in transit, a customer keeping the item as goodwill). The
             *        cost stays consumed, so COGS is NOT reversed — only revenue
             *        is. Treating these identically would understate cost of
             *        sales and overstate inventory.
             */
            $table->boolean('restock')->default(true);

            $table->string('reason', 500)->nullable();

            // draft → posting → posted, or failed. Mirrors the posting-status
            // vocabulary already used on feed_sales and pig_sales.
            $table->string('status', 20)->default('draft');
            $table->string('posting_error', 1000)->nullable();

            // Results of the reversal, for traceability and to make the whole
            // operation idempotent on retry.
            $table->unsignedBigInteger('cogs_reversal_batch_id')->nullable();
            $table->unsignedBigInteger('revenue_reversal_batch_id')->nullable();
            $table->unsignedBigInteger('inventory_movement_id')->nullable();
            $table->unsignedBigInteger('biological_transfer_id')->nullable();

            // Money reversed, recorded so a return can be reconciled without
            // recomputing from a sale that may since have changed.
            $table->decimal('cogs_reversed_amount', 18, 4)->nullable();
            $table->decimal('revenue_reversed_amount', 18, 4)->nullable();

            // Whether the pig was restored to the herd (pig returns only).
            $table->boolean('pig_restored')->default(false);

            $table->unsignedBigInteger('returned_by_user_id')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamp('posted_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status'], 'sales_returns_org_status_idx');
            $table->index(['returnable_type', 'returnable_id'], 'sales_returns_returnable_idx');
            $table->index(['storefront_order_id'], 'sales_returns_order_idx');
            $table->unique(['organization_id', 'return_number'], 'sales_returns_org_number_unique');

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('returned_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_returns');
    }
};
