<?php

namespace Tests\Feature\Finance\Phase2d;

use App\Models\Counterparty;
use App\Models\Finance\Account;
use App\Models\Finance\Refund;
use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\FinancePermissionsSeeder;
use Database\Seeders\Phase2dPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class RefundControllerTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected User $accountant;
    protected User $admin;
    protected User $manager;
    protected Counterparty $customer;
    protected PaymentMethod $cashMethod;

    protected function setUp(): void
    {
        parent::setUp();

        if (! \Illuminate\Support\Facades\Route::has('finance.refunds.index')) {
            $this->markTestSkipped('Phase 2d routes not registered. Apply WEB_PHP_PHASE2D_ROUTES_PATCH.md.');
        }

        $this->setUpFinanceFixtures();

        if (! Account::where('organization_id', $this->org->id)->where('code', '1910')->exists()) {
            Account::create([
                'organization_id' => $this->org->id, 'code' => '1910',
                'name' => 'Suspense - Receipts', 'account_type' => Account::TYPE_ASSET,
                'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => false,
            ]);
        }

        $this->seed(FinancePermissionsSeeder::class);
        $this->seed(Phase2dPermissionsSeeder::class);

        $this->accountant = $this->makeUser('accountant');
        $this->admin = $this->makeUser('admin');
        $this->manager = $this->makeUser('manager');

        $this->customer = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'Acme', 'code' => 'CUST-A',
            'type' => 'customer', 'currency_code' => 'GHS',
        ]);

        $this->cashMethod = PaymentMethod::create([
            'organization_id' => $this->org->id,
            'code' => 'CASH', 'name' => 'Cash',
            'is_cash' => true, 'gl_account_hint' => 'CASH_ON_HAND', 'is_active' => true,
        ]);
    }

    public function test_index_loads_for_accountant(): void
    {
        $this->actingAs($this->accountant)
            ->get(route('finance.refunds.index'))
            ->assertOk()
            ->assertSee('Refunds');
    }

    public function test_create_blocked_for_manager(): void
    {
        $this->actingAs($this->manager)
            ->get(route('finance.refunds.create'))
            ->assertForbidden();
    }

    public function test_accountant_can_draft_but_cannot_post(): void
    {
        // Step 1: accountant drafts
        $response = $this->actingAs($this->accountant)->post(route('finance.refunds.store'), [
            'counterparty_id'   => $this->customer->id,
            'payment_method_id' => $this->cashMethod->id,
            'refund_date'       => '2026-05-15',
            'amount'            => '500.00',
            'currency_code'     => 'GHS',
            'reason'            => 'customer overpaid',
        ]);
        $response->assertRedirect();

        $refund = Refund::latest('id')->first();
        $this->assertSame(Refund::STATUS_DRAFT, $refund->status);
        $this->assertNull($refund->approved_by_user_id);

        // Step 2: accountant attempts to post
        $this->actingAs($this->accountant)
            ->post(route('finance.refunds.post', $refund))
            ->assertForbidden();

        // Refund still draft
        $refund->refresh();
        $this->assertSame(Refund::STATUS_DRAFT, $refund->status);
    }

    public function test_admin_can_post(): void
    {
        $this->actingAs($this->accountant)->post(route('finance.refunds.store'), [
            'counterparty_id'   => $this->customer->id,
            'payment_method_id' => $this->cashMethod->id,
            'refund_date'       => '2026-05-15',
            'amount'            => '500.00',
            'currency_code'     => 'GHS',
            'reason'            => 'customer overpaid',
        ]);

        $refund = Refund::latest('id')->first();

        $this->actingAs($this->admin)
            ->post(route('finance.refunds.post', $refund))
            ->assertRedirect();

        $refund->refresh();
        $this->assertSame(Refund::STATUS_POSTED, $refund->status);
        $this->assertSame((int) $this->admin->id, (int) $refund->approved_by_user_id);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAs($this->accountant)
            ->from(route('finance.refunds.create'))
            ->post(route('finance.refunds.store'), [])
            ->assertSessionHasErrors(['counterparty_id', 'payment_method_id', 'refund_date', 'amount', 'reason']);
    }

    public function test_show_renders_for_view_permission(): void
    {
        $refund = Refund::create([
            'organization_id'    => $this->org->id,
            'refund_number'      => 'REF-2026-000001',
            'refund_date'        => '2026-05-15',
            'counterparty_id'    => $this->customer->id,
            'currency_code'      => 'GHS', 'exchange_rate' => '1',
            'amount'             => '500.00',
            'payment_method_id'  => $this->cashMethod->id,
            'status'             => Refund::STATUS_DRAFT,
            'created_by_user_id' => $this->user->id,
            'reason'             => 'test',
        ]);

        $this->actingAs($this->accountant)
            ->get(route('finance.refunds.show', $refund))
            ->assertOk()
            ->assertSee('REF-2026-000001');
    }

    /* helper */
    protected function makeUser(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);
        return User::forceCreate([
            'name'            => ucfirst($roleName),
            'email'           => $roleName . '_' . uniqid() . '@example.com',
            'password'        => bcrypt('x'),
            'role'            => $roleName,
            'role_id'         => $role->id,
            'organization_id' => $this->org->id,
        ]);
    }
}
