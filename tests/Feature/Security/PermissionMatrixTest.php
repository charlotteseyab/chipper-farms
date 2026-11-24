<?php

namespace Tests\Feature\Security;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Phase 16 — the §49 permission matrix, made executable.
 *
 * §49 is a table of who may do what across seven product roles, followed by one
 * sentence of instruction: "Implementation must reconcile current seeded
 * permissions with these product roles." Until this file existed, that table was
 * prose, and the seeded reality had drifted from it in a way nobody could see:
 * grants that miss are silent. No error is raised, the role row exists, the
 * permission attaches to it, and a person is quietly locked out of their job.
 *
 * WHAT THE DRIFT ACTUALLY WAS
 * ---------------------------
 * `manager` and `farmmanager` are one product role stored under two row names.
 * The seeders had settled into three different conventions about which to grant
 * to — alias one to the other, grant to both, or grant to `manager` alone — and
 * the rows diverged. A farm manager created today was refused the finance
 * overview that §49 grants them; a legacy `manager` user kept it and lost eight
 * storefront, expense and purchase permissions instead.
 *
 * These tests assert the matrix against seeded reality, so the next divergence
 * fails here instead of on a farm.
 */
class PermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Role rows that carry grants but no user can hold.
     *
     * Empty, and it should stay that way. A grant to a role outside
     * `ALLOWED_ROLE_NAMES` reaches nobody.
     *
     * Both former entries are now closed. `manager` was reconciled into
     * `farmmanager` (IMP-115). `mill_operator` was added to the allowed roles
     * (IMP-118) — not as a new product role, but because the seeders, the
     * finance-access middleware and the navigation had all been built around it
     * already and only its name was missing.
     *
     * @var list<string>
     */
    private const KNOWN_UNREACHABLE_ROLES = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    /**
     * §49, one row per capability, expressed as the permission that backs it.
     *
     * Only capabilities that map onto a single named permission are listed. The
     * "Limited" and "Own only" cells in §49 are scope rules rather than grants
     * and are enforced elsewhere (organisation scope, `assertSameOrg`, the
     * customer's own-orders queries), so asserting them here would be asserting
     * the wrong layer.
     *
     * @return array<string, array{0:string, 1:list<string>, 2:list<string>}>
     */
    public static function matrix(): array
    {
        return [
            // capability => [permission, roles that must hold it, roles that must not]
            'view overview' => [
                'view_finance',
                ['admin', 'superuser', 'accountant', 'treasurer', 'farmmanager', 'dataentry'],
                ['salesperson', 'customer'],
            ],
            'quick expense' => [
                'record_expenses',
                ['admin', 'superuser', 'accountant', 'treasurer', 'farmmanager', 'dataentry'],
                ['salesperson', 'customer'],
            ],
            'storefront settings' => [
                'manage_storefront_settings',
                ['admin', 'superuser', 'farmmanager'],
                ['accountant', 'treasurer', 'dataentry', 'salesperson', 'customer'],
            ],
            'refund orders' => [
                'refund_storefront_orders',
                ['admin', 'superuser', 'farmmanager'],
                ['dataentry', 'salesperson', 'customer'],
            ],
            'push listing' => [
                'push_to_storefront',
                ['admin', 'superuser', 'salesperson', 'farmmanager'],
                ['dataentry', 'customer'],
            ],
            'manage orders' => [
                'manage_storefront_orders',
                ['admin', 'superuser', 'salesperson', 'farmmanager'],
                ['dataentry', 'customer'],
            ],
            // §49 names this separation explicitly: confirming an order is not
            // the same right as administering one.
            'confirm orders' => [
                'confirm_storefront_orders',
                ['admin', 'superuser', 'salesperson', 'farmmanager'],
                ['dataentry', 'customer'],
            ],
            'view orders' => [
                'view_storefront_orders',
                ['admin', 'superuser', 'salesperson', 'accountant', 'farmmanager'],
                ['customer'],
            ],
        ];
    }

    #[DataProvider('matrix')]
    public function test_the_matrix_holds(string $permission, array $granted, array $withheld): void
    {
        $this->assertNotNull(
            Permission::where('name', $permission)->first(),
            "§49 names a capability backed by '{$permission}', which no seeder creates."
        );

        foreach ($granted as $roleName) {
            $this->assertTrue(
                $this->userWithRole($roleName)->can($permission),
                "§49 grants '{$permission}' to {$roleName}, and the seeded permissions do not."
            );
        }

        foreach ($withheld as $roleName) {
            $this->assertFalse(
                $this->userWithRole($roleName)->can($permission),
                "§49 withholds '{$permission}' from {$roleName}, and the seeded permissions grant it."
            );
        }
    }

    /**
     * The farm manager who could not open the finance overview.
     *
     * Kept as its own test rather than folded into the matrix because it is the
     * concrete failure this phase found: 403 for a role created through the
     * application, 200 for the legacy row, for the same person doing the same
     * job.
     */
    public function test_a_farm_manager_can_open_the_finance_overview(): void
    {
        // /finance opens the overview, or the ledger dashboard for a farm
        // with Advanced Accounting.
        $this->actingAs($this->userWithRole('farmmanager'))
            ->followingRedirects()
            ->get('/finance')
            ->assertOk();
    }

    /**
     * The class of bug, closed.
     *
     * A permission granted to a role name no user can hold is silently
     * unreachable. This is the assertion that would have caught the finance
     * lockout the day it was seeded.
     */
    public function test_no_permission_is_granted_to_a_role_no_user_can_hold(): void
    {
        $unreachable = Role::query()
            ->has('permissions')
            ->pluck('name')
            ->reject(fn ($name) => in_array($name, User::ALLOWED_ROLE_NAMES, true))
            ->reject(fn ($name) => in_array($name, self::KNOWN_UNREACHABLE_ROLES, true))
            ->values();

        $this->assertEmpty(
            $unreachable->all(),
            'These role rows carry permissions but are not in User::ALLOWED_ROLE_NAMES, '
            . 'so no user created through the application can hold them and the grants '
            . 'reach nobody: ' . $unreachable->implode(', ')
        );
    }

    /**
     * A mill operator can now be created, and served a page (IMP-118).
     *
     * Before this, the seeders granted the role five permissions, the
     * finance-access middleware listed it, and `NavigationHelper` had been
     * hardened because of it — but `syncRoleFields()` rejected the name, so no
     * user could hold it and every grant reached nobody.
     */
    public function test_a_mill_operator_can_be_provisioned_and_served(): void
    {
        $user = $this->userWithRole('mill_operator');

        // The role is assignable through the application's own path, not just
        // by forcing the column.
        $user->syncRoleFields('mill_operator');
        $user->save();

        $this->assertSame('mill_operator', $user->fresh()->role);

        // And the permissions the seeders always intended for it actually apply.
        $this->assertTrue($user->fresh()->can('view_inventory_value'));

        // The layout renders. This is the specific 500 NavigationHelper was
        // hardened against: a role outside ALLOWED_ROLE_NAMES used to make
        // dashboardItem() return null on every page using the app layout.
        $this->actingAs($user->fresh())->get('/dashboard')->assertSuccessful();
    }

    /**
     * The reconciliation itself: the two role rows must not drift again.
     *
     * `manager` may still exist — the migration deliberately leaves the row
     * rather than deleting one another table might reference — but it must not
     * hold anything `farmmanager` lacks.
     */
    public function test_the_legacy_manager_row_grants_nothing_a_farm_manager_lacks(): void
    {
        $legacy = Role::where('name', 'manager')->with('permissions')->first();

        if ($legacy === null) {
            $this->addToAssertionCount(1);

            return;
        }

        $farmManager = Role::where('name', 'farmmanager')->with('permissions')->first();

        $orphaned = $legacy->permissions->pluck('name')
            ->diff($farmManager?->permissions->pluck('name') ?? collect())
            ->values();

        $this->assertEmpty(
            $orphaned->all(),
            "The legacy 'manager' role holds permissions 'farmmanager' does not, so a farm "
            . 'manager created through the application cannot reach them: ' . $orphaned->implode(', ')
        );
    }

    /* ---------------------------------------------------------------- */

    protected function userWithRole(string $roleName): User
    {
        static $sequence = 0;

        $organization = Organization::first()
            ?? Organization::create(['slug' => 'matrix-org', 'name' => 'Matrix Farm']);

        $role = Role::where('name', $roleName)->first();

        $this->assertNotNull($role, "The '{$roleName}' role is not seeded at all.");

        return User::forceCreate([
            'name'              => $roleName,
            'email'             => $roleName . '-' . (++$sequence) . '@example.com',
            'password'          => bcrypt('secret'),
            'role'              => $roleName,
            'role_id'           => $role->id,
            'organization_id'   => $organization->id,
            'active_status'     => true,
            'email_verified_at' => now(),
        ]);
    }
}
