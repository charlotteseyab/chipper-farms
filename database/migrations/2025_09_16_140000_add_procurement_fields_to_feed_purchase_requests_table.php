<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feed_purchase_requests', function (Blueprint $table) {
            $table->string('category', 50)->default('feed')->after('id');
            $table->string('item_name', 120)->nullable()->after('category');
            $table->string('vendor', 120)->nullable()->after('item_name');
            $table->string('priority', 20)->nullable()->after('vendor');
        });
    }

    public function down(): void
    {
        Schema::table('feed_purchase_requests', function (Blueprint $table) {
            $table->dropColumn(['category','item_name','vendor','priority']);
        });
    }
};
