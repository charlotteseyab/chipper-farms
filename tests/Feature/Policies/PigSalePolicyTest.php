<?php

namespace Tests\Feature\Policies;

use App\Models\Organization;
use App\Models\PigSale;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Locks in the role matrix for PigSalePolicy. Same pattern should be used
 * for the other policies — this file is the template.
 *
 * Per PigSalePolicy:
 *   viewAny / view        admin, manager, dataentry, accountant  → ALLOWED
 *                         superuser                              → ALLOWED (bypass)
 *
 *   create                admin, manager                         → ALLOWED
 *                         dataentry, accountant                  → DENIED
 *
 *   update                admin, manager                         → ALLOWED
 *                         dataentry, accountant                  → DENIED
 *
 *   updatePayment         admin, manager, accountant             → ALLOWED
 *                         dataentry                              → DENIED
 *
 *   delete                admin                                  → ALLOWED
 *                         manager, dataentry, accountant         → DENIED
 *
 *   export                admin, manager, accountant             → ALLOWED
 *                         dataentry                              → DENIED
 */
class PigSalePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'slug' => 'chipper-farms',
            'name' => 'Chipper Farms',
        ]);
    }

    protected function makeUser(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);

        return User::forceCreate([
            'name' => ucfirst($roleName),
            'email' => $roleName . '@example.com',
            'password' => bcrypt('x'),
            'role' => $roleName,
            'role_id' => $role->id,
            'organization_id' => $this->org->id,
        ]);
    }

    /**
     * @dataProvider viewAbilityProvider
     */
    public function test_view_any_role_matrix(string $role, bool $expected): void
    {
        $this->actingAs($this->makeUser($role));
        $this->assertSame($expected, Gate::allows('viewAny', PigSale::class));
    }

    public static function viewAbilityProvider(): array
    {
        return [
            'admin can view'         => ['admin', true],
            'manager can view'       => ['farmmanager', true],
            'dataentry can view'     => ['dataentry', true],
            'accountant can view'    => ['accountant', true],
        ];
    }

    /**
     * @dataProvider createAbilityProvider
     */
    public function test_create_role_matrix(string $role, bool $expected): void
    {
        $this->actingAs($this->makeUser($role));
        $this->assertSame($expected, Gate::allows('create', PigSale::class));
    }

    public static function createAbilityProvider(): array
    {
        return [
            'admin can create'         => ['admin', true],
            'manager can create'       => ['farmmanager', true],
            'dataentry cannot create'  => ['dataentry', false],
            'accountant cannot create' => ['accountant', false],
        ];
    }

    /**
     * @dataProvider updatePaymentProvider
     */
    public function test_update_payment_role_matrix(string $role, bool $expected): void
    {
        $this->actingAs($this->makeUser($role));
        $sale = new PigSale();
        $this->assertSame($expected, Gate::allows('updatePayment', $sale));
    }

    public static function updatePaymentProvider(): array
    {
        return [
            'admin can update payment'         => ['admin', true],
            'manager can update payment'       => ['farmmanager', true],
            'accountant can update payment'    => ['accountant', true],
            'dataentry cannot update payment'  => ['dataentry', false],
        ];
    }

    /**
     * @dataProvider deleteProvider
     */
    public function test_delete_role_matrix(string $role, bool $expected): void
    {
        $this->actingAs($this->makeUser($role));
        $sale = new PigSale();
        $this->assertSame($expected, Gate::allows('delete', $sale));
    }

    public static function deleteProvider(): array
    {
        return [
            'admin can delete'        => ['admin', true],
            'manager cannot delete'   => ['farmmanager', false],
            'dataentry cannot delete' => ['dataentry', false],
            'accountant cannot delete'=> ['accountant', false],
        ];
    }

    /**
     * @dataProvider exportProvider
     */
    public function test_export_role_matrix(string $role, bool $expected): void
    {
        $this->actingAs($this->makeUser($role));
        $this->assertSame($expected, Gate::allows('export', PigSale::class));
    }

    public static function exportProvider(): array
    {
        return [
            'admin can export'       => ['admin', true],
            'manager can export'     => ['farmmanager', true],
            'accountant can export'  => ['accountant', true],
            'dataentry cannot export'=> ['dataentry', false],
        ];
    }
}