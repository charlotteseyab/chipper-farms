<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('activity_logs')) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        try {
            DB::statement('ALTER TABLE activity_logs DROP FOREIGN KEY activity_logs_user_id_foreign');
        } catch (\Throwable $e) {
            report($e);
        }

        try {
            DB::statement('ALTER TABLE activity_logs MODIFY user_id BIGINT UNSIGNED NULL');
        } catch (\Throwable $e) {
            report($e);
        }

        try {
            DB::statement('ALTER TABLE activity_logs ADD CONSTRAINT activity_logs_user_id_foreign FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL');
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('activity_logs') || DB::getDriverName() !== 'mysql') {
            return;
        }

        try {
            DB::statement('ALTER TABLE activity_logs DROP FOREIGN KEY activity_logs_user_id_foreign');
            DB::statement('ALTER TABLE activity_logs MODIFY user_id BIGINT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE activity_logs ADD CONSTRAINT activity_logs_user_id_foreign FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE');
        } catch (\Throwable $e) {
            report($e);
        }
    }
};