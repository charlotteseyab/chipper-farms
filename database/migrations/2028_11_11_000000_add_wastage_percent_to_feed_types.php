<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional wastage allowance per feed type. Blank uses the farm-wide
 * allowance (setting feed.planning.wastage_percent); creep and starter feeds
 * are often wasted more than finisher meal, so a single figure over- or
 * under-orders one of them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feed_types', function (Blueprint $table) {
            $table->decimal('wastage_percent', 5, 2)->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('feed_types', function (Blueprint $table) {
            $table->dropColumn('wastage_percent');
        });
    }
};
