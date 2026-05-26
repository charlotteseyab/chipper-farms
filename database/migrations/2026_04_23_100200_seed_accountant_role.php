<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data migration: ensures the 'accountant' role exists in the roles table.
 *
 * Structured as a migration (rather than seeder-only) so that production
 * deployments and fresh test databases both pick it up via the standard
 * migrate pipeline. Seeders are not always run in production.
 *
 * Idempotent: uses updateOrInsert keyed on name so re-running is a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('roles')->updateOrInsert(
            ['name' => 'accountant'],
            [
                'name' => 'accountant',
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        // Do not delete the role on rollback if any user still references it.
        // This avoids cascade-nulling role_id on live users during an accidental
        // rollback. Operators can delete manually once no references remain.
        $referenced = DB::table('users')
            ->join('roles', 'users.role_id', '=', 'roles.id')
            ->where('roles.name', 'accountant')
            ->exists();

        if (! $referenced) {
            DB::table('roles')->where('name', 'accountant')->delete();
        }
    }
};