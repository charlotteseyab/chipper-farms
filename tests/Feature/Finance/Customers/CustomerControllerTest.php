<?php

namespace Tests\Feature\Finance\Customers;

use App\Models\Counterparty;
use App\Models\CustomerCategory;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\CurrentOrganization;
use Database\Seeders\FinancePermissionsSeeder;
use Database\Seeders\Phase2aFinancePermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke tests for CustomerController. Permission gates are checked
 * by middleware, so these tests assert HTTP-level behaviour using
 * different authenticated roles.
 *
 * Route registration is required; missing routes are production regressions.
 */
class CustomerControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;
    protected User $admin;
    protected User $accountant;
    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertTrue(
            \Illuminate\Support\Facades\Route::has('finance.customers.index'),
            'Required finance.customers.index route is not registered.'
        );

        $this->org = Organization::create(['slug' => 'cf', 'name' => 'CF']);
        $this->app->make(CurrentOrganization::class)->bind($this->org);

        $this->seed(FinancePermissionsSeeder::class);
        $this->seed(Phase2aFinancePermissionsSeeder::class);

        $this->admin = $this->makeUser('admin');
        $this->accountant = $this->makeUser('accountant');
        $this->manager = $this->makeUser('manager');
    }

    public function test_index_accessible_to_view_finance_holders(): void
    {
        $response = $this->actingAs($this->manager)->get(route('finance.customers.index'));
        $response->assertOk();
    }

    public function test_create_blocked_for_view_only_user(): void
    {
        $response = $this->actingAs($this->manager)->get(route('finance.customers.create'));
        $response->assertForbidden();
    }

    public function test_create_allowed_for_accountant(): void
    {
        $response = $this->actingAs($this->accountant)->get(route('finance.customers.create'));
        $response->assertOk();
    }

    public function test_store_creates_customer(): void
    {
        $response = $this->actingAs($this->accountant)->post(route('finance.customers.store'), [
            'name'          => 'New Customer',
            'currency_code' => 'GHS',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('counterparties', [
            'name' => 'New Customer',
            'organization_id' => $this->org->id,
        ]);
    }

    public function test_store_validates_currency_code_present(): void
    {
        $response = $this->actingAs($this->accountant)
            ->from(route('finance.customers.create'))
            ->post(route('finance.customers.store'), [
                'name' => 'Missing Currency',
                // currency_code intentionally absent
            ]);

        $response->assertSessionHasErrors('currency_code');
    }

    public function test_show_returns_customer_with_relationships(): void
    {
        $cp = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'Existing',
            'type' => 'customer',
            'code' => 'CUST-EXISTING',
            'currency_code' => 'GHS',
        ]);

        $response = $this->actingAs($this->manager)->get(route('finance.customers.show', $cp));
        $response->assertOk();
        $response->assertSee('Existing');
    }

    public function test_place_on_hold_records_reason(): void
    {
        $cp = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'Risky',
            'type' => 'customer',
            'code' => 'CUST-RISKY',
            'currency_code' => 'GHS',
        ]);

        $this->actingAs($this->accountant)->post(route('finance.customers.place-on-hold', $cp), [
            'reason' => 'Past-due invoice',
        ]);

        $this->assertDatabaseHas('counterparties', [
            'id' => $cp->id,
            'on_credit_hold' => true,
            'credit_hold_reason' => 'Past-due invoice',
        ]);
    }

    /* helpers */

    protected function makeUser(string $roleName): User
    {
        // Normalised as the application normalises it: 'manager' and
        // 'farmmanager' are one product role under two row names, and since
        // Phase 16 the permission seeders grant to the row a user can actually
        // hold. A fixture on the raw 'manager' row builds a user the
        // application cannot produce.
        $role = Role::firstOrCreate(['name' => User::normalizeRoleName($roleName) ?? $roleName]);
        return User::forceCreate([
            'name'            => ucfirst($roleName),
            'email'           => $roleName . '_' . uniqid() . '@example.com',
            'password'        => bcrypt('x'),
            'role'            => $role->name,
            'role_id'         => $role->id,
            'organization_id' => $this->org->id,
        ]);
    }
}
