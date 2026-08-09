<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Give the "Other" option somewhere to put its text.
 *
 * THE DEFECT THIS CLOSES
 * ----------------------
 * `pig_deaths.death_cause`, `pig_deaths.disposal_method` and
 * `pig_sales.sale_purpose` all carry CHECK constraints limiting them to fixed
 * value lists that include 'other'.
 *
 * The request classes collapsed the free-text field INTO those columns — so a
 * user selecting "Other" and typing "research donation" caused an INSERT of
 * that literal string, which the constraint rejects. A 500 in production, every
 * time, for every user who used the Other option.
 *
 * They collapsed because there was nowhere else to put the text. These columns
 * are that somewhere.
 *
 * WHY NOT JUST DROP THE CONSTRAINTS
 * ---------------------------------
 * Because then "how did we lose pigs last quarter" stops being a GROUP BY and
 * starts being a text-matching exercise over free-form strings. The constraint
 * is what makes the column reportable, which is the whole reason a farm records
 * a cause of death in a fixed list.
 *
 * NULLABLE, ADDITIVE, NO BACKFILL
 * -------------------------------
 * Existing rows hold either a valid enum value or — on databases where the
 * constraint was never enforced — free text. This migration touches neither.
 * The accessors added alongside it read the free-text column only when the enum
 * column says 'other', so a legacy row holding raw free text still displays
 * exactly as it did before.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pig_deaths')) {
            Schema::table('pig_deaths', function (Blueprint $table) {
                if (! Schema::hasColumn('pig_deaths', 'death_cause_other')) {
                    $table->string('death_cause_other', 255)->nullable()->after('death_cause');
                }
                if (! Schema::hasColumn('pig_deaths', 'disposal_method_other')) {
                    $table->string('disposal_method_other', 255)->nullable()->after('disposal_method');
                }
            });
        }

        if (Schema::hasTable('pig_sales')) {
            Schema::table('pig_sales', function (Blueprint $table) {
                if (! Schema::hasColumn('pig_sales', 'sale_purpose_other')) {
                    $table->string('sale_purpose_other', 255)->nullable()->after('sale_purpose');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pig_deaths')) {
            Schema::table('pig_deaths', function (Blueprint $table) {
                $table->dropColumn(['death_cause_other', 'disposal_method_other']);
            });
        }

        if (Schema::hasTable('pig_sales')) {
            Schema::table('pig_sales', function (Blueprint $table) {
                $table->dropColumn('sale_purpose_other');
            });
        }
    }
};
