<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE users MODIFY organization_id BIGINT UNSIGNED NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN organization_id DROP NOT NULL');
        }

        DB::table('users')->where('role', 'superuser')->update(['organization_id' => null]);
    }

    public function down(): void
    {
        if (DB::table('users')->whereNull('organization_id')->exists()) {
            throw new RuntimeException('Assign all platform users to an organization before reversing this migration.');
        }

        $driver = DB::connection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE users MODIFY organization_id BIGINT UNSIGNED NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN organization_id SET NOT NULL');
        }
    }
};
