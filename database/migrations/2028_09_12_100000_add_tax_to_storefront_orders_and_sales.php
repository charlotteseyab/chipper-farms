<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tax on storefront orders, carried through to the invoice (IMP-091).
 *
 * THE DEFECT
 * ----------
 * A storefront order recorded subtotal, delivery fee and total, and no tax at
 * all. `StorefrontOrderConfirmedSubscriber` set no tax on the sale it created,
 * so the invoice that resulted totalled zero tax. A VAT-registered farm selling
 * online collected no VAT and under-declared it.
 *
 * §38 requires the checkout to "show subtotal, delivery, tax, total". It could
 * not, because there was nothing to show.
 *
 * WHY THE SNAPSHOT AND NOT JUST AN AMOUNT
 * ---------------------------------------
 * Phase 2 established that a credit note must reverse the tax that was
 * ORIGINALLY charged, not what today's configuration would charge (IMP-014). A
 * 2024 sale taxed under the old cascading Ghana regime is credited at that
 * regime's figure in 2026, or 1.90 of tax is stranded permanently.
 *
 * `TaxCalculation::toSnapshot()` already exists for exactly this and
 * `fromSnapshot()` reads it back. Storing the snapshot on the order and on the
 * sale means the reversal path has the original component breakdown, not a
 * single number it would have to re-derive from a profile that may since have
 * changed.
 *
 * WHY THE SALE CARRIES IT TOO
 * ---------------------------
 * The order is the commercial record; the sale is what finance posts from. If
 * only the order carried tax, the invoice would still total zero and the buyer
 * would hold a receipt that disagreed with the farm's books. The whole point of
 * this change is that those two numbers are the same, so both rows carry it.
 *
 * `tax_amount` is stored alongside the snapshot rather than derived from it on
 * read, because the invoicing subscribers need one number and reading it should
 * not require decoding JSON on every posting.
 */
return new class extends Migration
{
    /** Tables that record a sale finance posts from. */
    protected const SALE_TABLES = ['pig_sales', 'feed_sales'];

    public function up(): void
    {
        if (Schema::hasTable('storefront_orders') && ! Schema::hasColumn('storefront_orders', 'tax_total')) {
            Schema::table('storefront_orders', function (Blueprint $table) {
                // decimal(18,2) to match subtotal/delivery_fee/total on the
                // same row. A narrower type would truncate on the way in.
                $table->decimal('tax_total', 18, 2)->default(0)->after('delivery_zone_name');

                // Which profile charged it. Nullable: a farm below the VAT
                // threshold configures none, and that is not an error.
                $table->unsignedBigInteger('tax_profile_id')->nullable()->after('tax_total');

                // The component breakdown as charged — see the class docblock.
                $table->json('tax_snapshot')->nullable()->after('tax_profile_id');
            });

            if (Schema::hasTable('tax_profiles')) {
                Schema::table('storefront_orders', function (Blueprint $table) {
                    // Null on delete: removing a profile must not orphan or
                    // destroy the record of what an old order was charged.
                    $table->foreign('tax_profile_id')
                        ->references('id')->on('tax_profiles')
                        ->nullOnDelete();
                });
            }
        }

        foreach (self::SALE_TABLES as $saleTable) {
            if (! Schema::hasTable($saleTable) || Schema::hasColumn($saleTable, 'tax_snapshot')) {
                continue;
            }

            Schema::table($saleTable, function (Blueprint $table) {
                // Additive and nullable. Every existing sale reads as "no
                // profile-based tax", which is exactly what it is — the
                // single-code `tax_code_id` path is untouched and still works.
                $table->unsignedBigInteger('tax_profile_id')->nullable();
                $table->decimal('tax_amount', 18, 2)->nullable();
                $table->json('tax_snapshot')->nullable();
            });

            if (Schema::hasTable('tax_profiles')) {
                Schema::table($saleTable, function (Blueprint $table) {
                    $table->foreign('tax_profile_id')
                        ->references('id')->on('tax_profiles')
                        ->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::SALE_TABLES as $saleTable) {
            if (! Schema::hasTable($saleTable) || ! Schema::hasColumn($saleTable, 'tax_snapshot')) {
                continue;
            }

            // The foreign key must go BEFORE the column, and it must be
            // dropped by COLUMN rather than by a hand-built name — passing the
            // constraint name as if it were a column list produced
            // `<table>_<table>_tax_profile_id_foreign_foreign`, which does not
            // exist, so the drop silently did nothing and SQLite then refused
            // the column drop with "unknown column in foreign key definition".
            // Caught by running the rollback rather than assuming it worked.
            Schema::table($saleTable, function (Blueprint $table) {
                if (Schema::hasTable('tax_profiles')) {
                    $table->dropForeign(['tax_profile_id']);
                }
            });

            Schema::table($saleTable, function (Blueprint $table) {
                $table->dropColumn(['tax_profile_id', 'tax_amount', 'tax_snapshot']);
            });
        }

        if (Schema::hasTable('storefront_orders') && Schema::hasColumn('storefront_orders', 'tax_total')) {
            Schema::table('storefront_orders', function (Blueprint $table) {
                if (Schema::hasTable('tax_profiles')) {
                    $table->dropForeign(['tax_profile_id']);
                }
            });

            Schema::table('storefront_orders', function (Blueprint $table) {
                $table->dropColumn(['tax_total', 'tax_profile_id', 'tax_snapshot']);
            });
        }
    }
};
