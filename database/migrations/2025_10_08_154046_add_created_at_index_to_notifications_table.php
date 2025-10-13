<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * This adds a database index to the 'created_at' column.
     */
    public function up(): void
    {
        // We are modifying the 'database_notifications' table
        Schema::table('notifications', function (Blueprint $table) {
            // This line creates the index. It tells the database to build a
            // fast lookup structure specifically for this column.
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     * This removes the database index if the migration is rolled back.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // This line removes the index we added in the 'up' method.
            $table->dropIndex(['created_at']);
        });
    }
};
