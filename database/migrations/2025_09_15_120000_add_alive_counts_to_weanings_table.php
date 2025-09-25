<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weanings', function (Blueprint $table) {
            $table->integer('alive_males')->nullable()->after('follow_up_date');
            $table->integer('alive_females')->nullable()->after('alive_males');
        });
    }

    public function down(): void
    {
        Schema::table('weanings', function (Blueprint $table) {
            $table->dropColumn(['alive_males', 'alive_females']);
        });
    }
};
