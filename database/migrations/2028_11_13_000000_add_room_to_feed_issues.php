<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The pen or room a feeding was given in, when the farm records it. Optional:
 * a feeding can still be for a whole stage. The stage's daily ration stays
 * the plan, so several pens fed separately add up against it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feed_issues', function (Blueprint $table) {
            $table->foreignId('room_id')->nullable()->after('feeding_stage')->constrained('rooms')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('feed_issues', function (Blueprint $table) {
            $table->dropConstrainedForeignId('room_id');
        });
    }
};
