<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 50)->after('role_id')->nullable();
            $table->string('last_name', 50)->after('first_name')->nullable();
            $table->string('phone', 20)->after('email')->nullable();
            $table->string('role', 50)->after('phone')->nullable();
            $table->boolean('active_status')->default(true)->after('role');
        });

        // Keep column length change outside the schema builder to avoid DBAL dependency.
        Schema::getConnection()->statement('ALTER TABLE users MODIFY email VARCHAR(50) UNIQUE');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name', 'phone', 'role', 'active_status']);
        });

        Schema::getConnection()->statement('ALTER TABLE users MODIFY email VARCHAR(255) UNIQUE');
    }
};
