<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feed_schedules', function (Blueprint $table) {
            $table->foreignId('structure_id')->nullable()->after('group_id')->constrained('structures')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('feed_schedules', function (Blueprint $table) {
            $table->dropForeign(['structure_id']);
            $table->dropColumn('structure_id');
        });
    }
};


