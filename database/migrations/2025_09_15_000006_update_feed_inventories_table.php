<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('feed_inventories', function (Blueprint $table) {
            $table->dropColumn('feed_type');
            $table->foreignId('feed_type_id')->after('id')->constrained('feed_types')->onDelete('cascade');
        });
    }
    public function down(): void {
        Schema::table('feed_inventories', function (Blueprint $table) {
            $table->dropForeign(['feed_type_id']);
            $table->dropColumn('feed_type_id');
            $table->string('feed_type');
        });
    }
};
