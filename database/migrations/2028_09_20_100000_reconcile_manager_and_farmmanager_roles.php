<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 16 (§49) — one product role, one role row.
 *
 * `manager` and `farmmanager` are the same person. `User::normalizeRoleName()`
 * maps the former onto the latter on every write and `manager` is absent from
 * `User::ALLOWED_ROLE_NAMES`, so nothing the application creates can hold it —
 * but the seeders granted against both names inconsistently, and the two rows
 * drifted apart. A farm manager created today was refused the finance overview
 * (§49 grants it) while a legacy `manager` user kept it and lost eight
 * storefront, expense and purchase permissions instead.
 *
 * Re-running the corrected seeders fixes new installs. It does nothing for a
 * database that already has users pointing at the `manager` row, which is what
 * this migration is for.
 *
 * WHAT IT DOES
 * ------------
 *  1. Copies every permission held by `manager` onto `farmmanager`, so nothing
 *     a working farm relies on disappears at the moment users are moved across.
 *  2. Repoints users from `manager` to `farmmanager`, setting both the
 *     `role_id` and the denormalised `role` string, because the application
 *     reads either depending on the path.
 *
 * The `manager` row itself is LEFT IN PLACE. Deleting it would break any
 * foreign key still referencing it and cannot be undone by `down()`; an
 * unreferenced role row costs nothing, and the Phase 16 guard test fails if a
 * seeder ever grants against it again.
 *
 * IDEMPOTENT AND RESUMABLE
 * ------------------------
 * Permission copying is an insert-if-absent. The user move is a conditional
 * update. Running this twice, or after a partial failure, changes nothing the
 * second time. It reads the role rows by name each run rather than caching ids,
 * so it is safe on a database where the rows were created in a different order.
 */
return new class extends Migration
{
    public function up(): void
    {
        $legacyId = DB::table('roles')->where('name', 'manager')->value('id');
        $targetId = DB::table('roles')->where('name', 'farmmanager')->value('id');

        if ($legacyId === null) {
            return;                       // Nothing to reconcile.
        }

        if ($targetId === null) {
            // A database seeded before `farmmanager` existed. Create it rather
            // than skipping, or the users below would have nowhere to go.
            $targetId = DB::table('roles')->insertGetId([
                'name'       => 'farmmanager',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 1. Union the grants onto the surviving row.
        $legacyPermissionIds = DB::table('role_permissions')
            ->where('role_id', $legacyId)
            ->pluck('permission_id');

        $alreadyHeld = DB::table('role_permissions')
            ->where('role_id', $targetId)
            ->pluck('permission_id')
            ->all();

        $missing = $legacyPermissionIds->reject(
            fn ($id) => in_array($id, $alreadyHeld, true)
        )->values();

        foreach ($missing->chunk(200) as $chunk) {
            DB::table('role_permissions')->insert(
                $chunk->map(fn ($permissionId) => [
                    'role_id'       => $targetId,
                    'permission_id' => $permissionId,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ])->all()
            );
        }

        // 2. Move the people.
        DB::table('users')->where('role_id', $legacyId)->update([
            'role_id'    => $targetId,
            'role'       => 'farmmanager',
            'updated_at' => now(),
        ]);

        // Users carrying the legacy string with no role_id at all — early rows
        // predate the relationship column.
        DB::table('users')
            ->where('role', 'manager')
            ->whereNull('role_id')
            ->update([
                'role_id'    => $targetId,
                'role'       => 'farmmanager',
                'updated_at' => now(),
            ]);
    }

    /**
     * Deliberately does not move users back.
     *
     * `farmmanager` is the correct row and holds users who were always there;
     * this migration cannot tell them apart from the ones it moved, so
     * reversing would sweep innocent records onto a role no user can hold.
     * Down is a no-op, and the up direction is safe to re-run.
     */
    public function down(): void
    {
    }
};
