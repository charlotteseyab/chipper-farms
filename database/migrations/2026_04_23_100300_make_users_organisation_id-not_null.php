<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Enforces NOT NULL on users.organization_id after the backfill has been run.
 *
 * DEPLOYMENT CONTRACT
 * -------------------
 * Do NOT deploy this migration in the same batch as
 * 2026_04_23_100100_add_organization_id_to_users_table. It MUST come after
 * `php artisan chipper:backfill-user-organizations` has been executed against
 * the target database.
 *
 * The up() method defensively checks that no users still have a null
 * organization_id. If any are found it aborts, so a skipped backfill step
 * cannot silently break the live system.
 *
 * We use a raw ALTER rather than Schema::table()->change() to avoid requiring
 * the doctrine/dbal package, consistent with precedent in
 * 2026_01_12_000001_update_users_profile_fields.php.
 *
 * SQLite note: SQLite cannot MODIFY an existing column's NOT NULL constraint
 * without a full table rewrite. Because production targets MySQL and the test
 * suite uses SQLite in-memory, this migration's structural change is a no-op
 * on SQLite — the guard check above still runs (protecting against accidental
 * deploy without backfill) but the column-level constraint enforcement is
 * skipped. Production (MySQL) gets the real NOT NULL enforcement.
 */
return new class extends Migration
{
    public function up(): void
    {
        $missing = DB::table('users')->whereNull('organization_id')->count();

        if ($missing > 0) {
            throw new \RuntimeException(
                "Refusing to enforce NOT NULL on users.organization_id: {$missing} user(s) still have a null organization_id. "
                . "Run `php artisan chipper:backfill-user-organizations` first."
            );
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE users MODIFY organization_id BIGINT UNSIGNED NOT NULL');
        }
        // Other drivers (sqlite, pgsql) fall through intentionally.
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE users MODIFY organization_id BIGINT UNSIGNED NULL');
        }
    }
};