<?php

namespace Tests\Feature\Security;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 16 — the corrective migration for databases that already carry the split.
 *
 * Fixing the seeders repairs a fresh install. It does nothing for a database
 * where users already point at the legacy `manager` role row, and those are the
 * databases with real people in them.
 *
 * The migration is exercised directly rather than through `artisan migrate`,
 * because `RefreshDatabase` has already run it against an empty `roles` table
 * (where it correctly does nothing). The interesting state — a populated legacy
 * role with users attached — has to be built first.
 */
class ManagerRoleReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'database/migrations/2028_09_20_100000_reconcile_manager_and_farmmanager_roles.php';

    protected function runReconciliation(): void
    {
        (require base_path(self::MIGRATION))->up();
    }

    /**
     * Build the pre-Phase-16 world: two role rows, grants split across them, and
     * a farm manager attached to the legacy one.
     */
    protected function seedTheSplit(): array
    {
        $legacy = Role::create(['name' => 'manager']);
        $target = Role::create(['name' => 'farmmanager']);

        $onlyOnLegacy = Permission::create([
            'name' => 'view_finance', 'description' => 'View finance', 'module' => 'finance',
        ]);
        $onBoth = Permission::create([
            'name' => 'view_pigs', 'description' => 'View pigs', 'module' => 'operations',
        ]);

        $legacy->permissions()->sync([$onlyOnLegacy->id, $onBoth->id]);
        $target->permissions()->sync([$onBoth->id]);

        $organization = Organization::create(['slug' => 'legacy-farm', 'name' => 'Legacy Farm']);

        $user = User::forceCreate([
            'name'              => 'Kwabena',
            'email'             => 'kwabena@example.com',
            'password'          => bcrypt('secret'),
            'role'              => 'manager',
            'role_id'           => $legacy->id,
            'organization_id'   => $organization->id,
            'active_status'     => true,
            'email_verified_at' => now(),
        ]);

        return [$legacy, $target, $user];
    }

    public function test_a_legacy_manager_becomes_a_farm_manager_and_keeps_their_access(): void
    {
        [$legacy, $target, $user] = $this->seedTheSplit();

        $this->runReconciliation();

        $user->refresh();

        $this->assertSame($target->id, $user->role_id);
        $this->assertSame('farmmanager', $user->role);

        // The point of copying the grants first: the move must not cost them
        // the permission that was only on the row they came from.
        $this->assertTrue($user->fresh()->can('view_finance'));
    }

    public function test_the_surviving_role_gains_what_only_the_legacy_row_held(): void
    {
        [$legacy, $target] = $this->seedTheSplit();

        $this->runReconciliation();

        $this->assertEqualsCanonicalizing(
            ['view_finance', 'view_pigs'],
            $target->fresh()->permissions->pluck('name')->all(),
        );
    }

    /**
     * A user who carries the legacy string but predates the relationship column.
     */
    public function test_a_user_with_the_legacy_role_string_and_no_role_id_is_moved(): void
    {
        [, $target] = $this->seedTheSplit();

        $organization = Organization::first();

        $orphan = User::forceCreate([
            'name'              => 'Ama',
            'email'             => 'ama@example.com',
            'password'          => bcrypt('secret'),
            'role'              => 'manager',
            'role_id'           => null,
            'organization_id'   => $organization->id,
            'active_status'     => true,
            'email_verified_at' => now(),
        ]);

        $this->runReconciliation();

        $orphan->refresh();

        $this->assertSame($target->id, $orphan->role_id);
        $this->assertSame('farmmanager', $orphan->role);
    }

    /**
     * Backfills must be safe to re-run — a migration that half-finished on a
     * large table gets run again.
     */
    public function test_running_it_twice_changes_nothing_the_second_time(): void
    {
        [, $target, $user] = $this->seedTheSplit();

        $this->runReconciliation();

        $afterFirst = [
            'role_id'     => $user->fresh()->role_id,
            'permissions' => $target->fresh()->permissions->pluck('name')->sort()->values()->all(),
            'pivotRows'   => DB::table('role_permissions')->where('role_id', $target->id)->count(),
        ];

        $this->runReconciliation();

        $this->assertSame($afterFirst['role_id'], $user->fresh()->role_id);
        $this->assertSame(
            $afterFirst['permissions'],
            $target->fresh()->permissions->pluck('name')->sort()->values()->all(),
        );
        // Duplicated pivot rows would be caught by the unique index, so a second
        // run inserting anything at all would have thrown before reaching here.
        $this->assertSame(
            $afterFirst['pivotRows'],
            DB::table('role_permissions')->where('role_id', $target->id)->count(),
        );
    }

    /**
     * On a database that never had the legacy row — every new install — the
     * migration must be a no-op rather than inventing anything.
     */
    public function test_it_does_nothing_when_there_is_no_legacy_role(): void
    {
        $target = Role::create(['name' => 'farmmanager']);

        $this->runReconciliation();

        $this->assertNull(Role::where('name', 'manager')->first());
        $this->assertSame(0, $target->fresh()->permissions->count());
    }

    /**
     * Users who were already farm managers must not be disturbed.
     */
    public function test_existing_farm_managers_are_left_alone(): void
    {
        [, $target] = $this->seedTheSplit();

        $incumbent = User::forceCreate([
            'name'              => 'Yaa',
            'email'             => 'yaa@example.com',
            'password'          => bcrypt('secret'),
            'role'              => 'farmmanager',
            'role_id'           => $target->id,
            'organization_id'   => Organization::first()->id,
            'active_status'     => true,
            'email_verified_at' => now(),
        ]);

        $before = $incumbent->updated_at;

        $this->runReconciliation();

        $incumbent->refresh();

        $this->assertSame($target->id, $incumbent->role_id);
        $this->assertSame('farmmanager', $incumbent->role);
        $this->assertEquals($before, $incumbent->updated_at);
    }
}
