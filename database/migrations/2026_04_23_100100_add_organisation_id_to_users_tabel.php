<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds organization_id to the users table.
 *
 * The column is nullable on this migration to allow safe deployment on a live
 * database. The deploy sequence is:
 *
 *   1. Run this migration (adds nullable column + FK + index).
 *   2. Run `php artisan chipper:backfill-user-organizations` to assign every
 *      existing user to the default organization.
 *   3. Run the follow-on migration `2026_04_23_100300_make_users_organization_id_not_null`
 *      which enforces NOT NULL after backfill.
 *
 * This additive-then-enforce pattern is the production-safe approach for live
 * schema changes on a table that is hot-path for every request.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Nullable on creation; enforced NOT NULL in the follow-on migration.
            $table->unsignedBigInteger('organization_id')->nullable()->after('id');

            // Restrict on delete: you should never be able to delete an organization
            // that still has users attached. Organization retirement goes through
            // soft-delete on the organizations table.
            $table->foreign('organization_id')
                  ->references('id')
                  ->on('organizations')
                  ->restrictOnDelete()
                  ->cascadeOnUpdate();

            $table->index('organization_id', 'users_organization_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop FK first, then index, then column. Order matters on MySQL.
            $table->dropForeign(['organization_id']);
            $table->dropIndex('users_organization_id_index');
            $table->dropColumn('organization_id');
        });
    }
};