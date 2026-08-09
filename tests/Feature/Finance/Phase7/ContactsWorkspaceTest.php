<?php

namespace Tests\Feature\Finance\Phase7;

use App\Models\Counterparty;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Organizations\OrganizationFeatureService;
use App\Support\Features\FeatureCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

/**
 * Phase 7 — People & Businesses (blueprint §27).
 *
 * The tests that matter here are the two boundaries the unified screen could
 * quietly cross: the permission that separates customers from suppliers, and
 * the one that separates "what we are owed" from "what we owe".
 */
class ContactsWorkspaceTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        $this->seed(\Database\Seeders\FinancePermissionsSeeder::class);
        $this->seed(\Database\Seeders\Phase2aFinancePermissionsSeeder::class);
        $this->seed(\Database\Seeders\Phase3aSupplierPermissionsSeeder::class);
    }

    public function test_the_workspace_lists_customers_and_suppliers_on_their_own_tabs(): void
    {
        $this->makeContact('Ama Mensah', Counterparty::TYPE_CUSTOMER);
        $this->makeContact('Kojo Feeds Ltd', Counterparty::TYPE_SUPPLIER);

        $user = $this->userWith(['view_finance']);

        $this->actingAs($user)
            ->get(route('finance.contacts.index', ['tab' => 'customers']))
            ->assertOk()
            ->assertSee('Ama Mensah')
            ->assertDontSee('Kojo Feeds Ltd');

        // Switching actingAs identity mid-test leaves the previous user's
        // session, which survives the next actingAs — a Laravel testing
        // artifact this programme has hit before (PHASE_06 §1). Flush it.
        $this->flushSession();

        $this->actingAs($this->userWith(['view_finance']))
            ->get(route('finance.contacts.index', ['tab' => 'suppliers']))
            ->assertOk()
            ->assertSee('Kojo Feeds Ltd')
            ->assertDontSee('Ama Mensah');
    }

    /**
     * `both` is why customers and suppliers were always one table. Someone the
     * farm buys feed from and sells weaners to belongs on both tabs, not on
     * whichever screen they were created from.
     */
    public function test_someone_who_buys_and_sells_appears_on_both_tabs(): void
    {
        $this->makeContact('Yaw Agro', Counterparty::TYPE_BOTH);

        $this->actingAs($this->userWith(['view_finance']))
            ->get(route('finance.contacts.index', ['tab' => 'customers']))
            ->assertOk()->assertSee('Yaw Agro');

        $this->flushSession();

        $this->actingAs($this->userWith(['view_finance']))
            ->get(route('finance.contacts.index', ['tab' => 'suppliers']))
            ->assertOk()->assertSee('Yaw Agro');
    }

    public function test_search_narrows_the_list(): void
    {
        $this->makeContact('Ama Mensah', Counterparty::TYPE_CUSTOMER);
        $this->makeContact('Kwesi Boateng', Counterparty::TYPE_CUSTOMER);

        $this->actingAs($this->userWith(['view_finance']))
            ->get(route('finance.contacts.index', ['tab' => 'customers', 'q' => 'Kwesi']))
            ->assertOk()
            ->assertSee('Kwesi Boateng')
            ->assertDontSee('Ama Mensah');
    }

    /* ---------------------------------------------------------------- */
    /* Guided creation                                                  */
    /* ---------------------------------------------------------------- */

    public function test_a_customer_can_be_added_with_a_name_and_a_phone_number(): void
    {
        $this->actingAs($this->userWith(['view_finance', 'manage_customers']))
            ->post(route('finance.contacts.store'), [
                'name'  => 'Ama Mensah',
                'role'  => 'customer',
                'phone' => '0244000000',
            ])
            ->assertRedirect();

        $contact = Counterparty::where('name', 'Ama Mensah')->first();

        $this->assertNotNull($contact);
        $this->assertSame(Counterparty::TYPE_CUSTOMER, $contact->type);
        $this->assertSame($this->org->id, $contact->organization_id);
        // The code came from NumberingService via CustomerService, not from
        // this controller writing the row itself.
        $this->assertNotEmpty($contact->code);
    }

    public function test_a_contact_with_no_way_to_reach_them_is_refused(): void
    {
        $this->actingAs($this->userWith(['view_finance', 'manage_customers']))
            ->post(route('finance.contacts.store'), [
                'name' => 'Nobody',
                'role' => 'customer',
            ])
            ->assertSessionHasErrors(['phone', 'email']);

        $this->assertSame(0, Counterparty::where('name', 'Nobody')->count());
    }

    /**
     * The role decides the permission, not the screen. Someone who may add
     * customers must not gain the ability to add suppliers by picking a
     * different radio button — a supplier is who the farm pays.
     */
    public function test_customer_permission_does_not_allow_adding_a_supplier(): void
    {
        $this->actingAs($this->userWith(['view_finance', 'manage_customers']))
            ->post(route('finance.contacts.store'), [
                'name'  => 'Kojo Feeds Ltd',
                'role'  => 'supplier',
                'phone' => '0244000000',
            ])
            ->assertForbidden();

        $this->assertSame(0, Counterparty::where('name', 'Kojo Feeds Ltd')->count());
    }

    public function test_adding_someone_as_both_needs_both_permissions(): void
    {
        $this->actingAs($this->userWith(['view_finance', 'manage_customers']))
            ->post(route('finance.contacts.store'), [
                'name'  => 'Yaw Agro',
                'role'  => 'both',
                'phone' => '0244000000',
            ])
            ->assertForbidden();

        $this->flushSession();

        $this->actingAs($this->userWith(['view_finance', 'manage_customers', 'manage_suppliers']))
            ->post(route('finance.contacts.store'), [
                'name'  => 'Yaw Agro',
                'role'  => 'both',
                'phone' => '0244000000',
            ])
            ->assertRedirect();

        $this->assertSame(Counterparty::TYPE_BOTH, Counterparty::where('name', 'Yaw Agro')->first()?->type);
    }

    /* ---------------------------------------------------------------- */
    /* Balances                                                         */
    /* ---------------------------------------------------------------- */

    /**
     * `finance/ageing/ap` requires `view_ap_ageing` while `finance/ageing/ar`
     * requires only `view_finance` — an existing, deliberate distinction. A
     * friendly summary that showed both to everyone would route around it.
     */
    public function test_what_the_farm_owes_is_hidden_without_the_payables_permission(): void
    {
        $this->actingAs($this->userWith(['view_finance']))
            ->get(route('finance.contacts.index', ['tab' => 'balances']))
            ->assertOk()
            ->assertSee('Owed to us')
            ->assertSee('do not have access to what the farm owes');
    }

    public function test_what_the_farm_owes_is_shown_with_the_payables_permission(): void
    {
        $this->actingAs($this->userWith(['view_finance', 'view_ap_ageing']))
            ->get(route('finance.contacts.index', ['tab' => 'balances']))
            ->assertOk()
            ->assertSee('We owe')
            ->assertDontSee('do not have access to what the farm owes');
    }

    /* ---------------------------------------------------------------- */
    /* Gating                                                           */
    /* ---------------------------------------------------------------- */

    public function test_the_workspace_closes_when_contacts_is_switched_off(): void
    {
        $this->app->make(OrganizationFeatureService::class)
            ->disable(FeatureCatalog::FINANCE_CONTACTS, $this->org);

        $user = $this->userWith(['view_finance', 'manage_customers']);

        $this->actingAs($user)->get(route('finance.contacts.index'))->assertNotFound();
        // And the accountant screens it fronts, which were equally ungated
        // before this phase.
        $this->actingAs($user)->get(route('finance.customers.index'))->assertNotFound();
        $this->actingAs($user)->get(route('finance.suppliers.index'))->assertNotFound();
    }

    /* ---------------------------------------------------------------- */

    protected function makeContact(string $name, string $type): Counterparty
    {
        return Counterparty::create([
            'organization_id' => $this->org->id,
            'name'            => $name,
            'code'            => strtoupper(substr(md5($name), 0, 8)),
            'type'            => $type,
            'currency_code'   => 'GHS',
            'is_active'       => true,
        ]);
    }

    /**
     * A user holding exactly these permissions and no others.
     *
     * @param  list<string>  $permissions
     */
    protected function userWith(array $permissions): User
    {
        static $sequence = 0;
        $role = Role::firstOrCreate(['name' => 'phase7-contacts-' . (++$sequence)]);

        foreach ($permissions as $name) {
            $permission = Permission::firstOrCreate(
                ['name' => $name],
                ['name' => $name, 'description' => $name, 'module' => 'finance'],
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        return User::forceCreate([
            'name'              => 'Tester ' . $sequence,
            'email'             => "contacts-{$sequence}@example.com",
            'password'          => bcrypt('secret'),
            'role'              => $role->name,
            'role_id'           => $role->id,
            'organization_id'   => $this->org->id,
            'active_status'     => true,
            'email_verified_at' => now(),
        ]);
    }
}
