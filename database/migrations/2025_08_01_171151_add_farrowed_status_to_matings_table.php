<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        // SQLite (used in tests) does not support MODIFY COLUMN nor native ENUM alters.
        // This migration is only meant to expand MySQL/MariaDB ENUM value sets.
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        // Add 'farrowed' to the status ENUM
        DB::statement("ALTER TABLE matings MODIFY COLUMN status ENUM('planned','completed','cancelled','failed','farrowed') NOT NULL DEFAULT 'planned'");

        // Add 'farrowed' to the pregnancy_status ENUM
        DB::statement("ALTER TABLE matings MODIFY COLUMN pregnancy_status ENUM('pending','pregnant','not_pregnant','uncertain','farrowed') NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        // Remove 'farrowed' from the status ENUM
        DB::statement("ALTER TABLE matings MODIFY COLUMN status ENUM('planned','completed','cancelled','failed') NOT NULL DEFAULT 'planned'");

        // Remove 'farrowed' from the pregnancy_status ENUM
        DB::statement("ALTER TABLE matings MODIFY COLUMN pregnancy_status ENUM('pending','pregnant','not_pregnant','uncertain') NOT NULL DEFAULT 'pending'");
    }
};
