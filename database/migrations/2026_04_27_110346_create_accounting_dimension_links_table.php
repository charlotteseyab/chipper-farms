<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 0.8 — accounting_dimensions polymorphic table.
 *
 * The prompt calls for "accounting dimension foundation for farm / site /
 * structure / room / group / channel / cost centre". These dimensions fall
 * into two groups:
 *
 *   A. Already-existing operational entities used AS dimensions:
 *      - structures   (farm buildings)
 *      - rooms        (pens / bays within structures)
 *      - groups       (logical groupings of pigs)
 *
 *   B. Purpose-built dimensions that have no operational twin:
 *      - cost_centres  (created in previous migration)
 *      - sales_channels (created in previous migration)
 *
 * This table provides polymorphic links from any finance source document
 * (pig_sale, raw_material_stock_log, feed_batch, pig_death, purchase_request,
 * and later journal_line) to any dimension instance. Multiple dimensions
 * per document are supported (one source row can be tagged with a cost centre
 * AND a sales channel AND a structure).
 *
 * Why polymorphic rather than dedicated FK columns per dimension on each
 * source table?
 *
 *   - The set of dimensions grows over time. Adding a new dimension type
 *     becomes a data migration, not a schema migration.
 *   - Not every document uses every dimension. Polymorphic keeps the
 *     source tables clean.
 *   - Phase 1 reports can build cross-dimension pivots without multiple JOINs.
 *
 * BUT — we also add common direct FKs (cost_centre_id, sales_channel_id)
 * on source tables because the most common dimensions benefit from being
 * indexed directly. Those are additive columns in the next migration set.
 *
 * Safe and idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('accounting_dimension_links')) {
            return;
        }

        Schema::create('accounting_dimension_links', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('organization_id')->nullable();

            // Source document polymorphic reference.
            $table->string('documentable_type', 120);   // 'App\Models\PigSale', 'App\Models\PurchaseRequest', etc.
            $table->unsignedBigInteger('documentable_id');

            // Dimension polymorphic reference.
            // dimension_type: short code — 'cost_centre', 'sales_channel',
            // 'structure', 'room', 'group'. Kept as a string rather than a
            // morph alias because the dimension entities already exist and
            // shouldn't be forced into a shared interface.
            $table->string('dimension_type', 30);
            $table->unsignedBigInteger('dimension_id');

            // Optional: allocation percentage or amount for split-allocations.
            // 100.0 means the entire source row is attributed to this dimension.
            // Two rows with 60.0 + 40.0 for cost_centre type would split the row.
            $table->decimal('allocation_percentage', 8, 4)->default(100);

            // Optional notes at the link level.
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['documentable_type', 'documentable_id'], 'acc_dim_links_documentable_idx');
            $table->index(['dimension_type', 'dimension_id'], 'acc_dim_links_dimension_idx');
            $table->index(['organization_id', 'dimension_type'], 'acc_dim_links_org_type_idx');

            // Same document + same dimension + same dimension_id should only
            // exist once unless the link is a partial allocation. We don't
            // enforce uniqueness here because split-allocation may legitimately
            // produce multiple rows of the same dimension_type pointing at
            // different dimension_ids on the same source.
        });

        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'sqlite', 'pgsql'], true)) {
            try {
                \DB::statement(
                    "ALTER TABLE accounting_dimension_links "
                    . "ADD CONSTRAINT acc_dim_links_dimension_type_check "
                    . "CHECK (dimension_type IN ('cost_centre', 'sales_channel', 'structure', 'room', 'group'))"
                );
            } catch (\Throwable $e) {
                // Ignore on older versions.
            }
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'sqlite', 'pgsql'], true)) {
            try {
                \DB::statement(
                    'ALTER TABLE accounting_dimension_links DROP CONSTRAINT acc_dim_links_dimension_type_check'
                );
            } catch (\Throwable $e) {
                // Ignore.
            }
        }

        Schema::dropIfExists('accounting_dimension_links');
    }
};