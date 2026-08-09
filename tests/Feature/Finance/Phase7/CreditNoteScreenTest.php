<?php

namespace Tests\Feature\Finance\Phase7;

use App\Models\Counterparty;
use App\Models\Finance\CreditNote;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Organizations\OrganizationFeatureService;
use App\Support\Features\FeatureCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

/**
 * Phase 7 — the credit note surface (blueprint §29).
 *
 * `CreditNoteService` and the tables have existed since the sales-return work,
 * and `SalesReturnRevenueSubscriber` creates a numbered credit note on every
 * return. Nothing showed one. These tests cover the screens that now do — and
 * the fact that they deliberately cannot create one.
 */
class CreditNoteScreenTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected Counterparty $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        $this->seed(\Database\Seeders\FinancePermissionsSeeder::class);

        $this->customer = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'Ama Mensah', 'code' => 'CUST-AMA',
            'type' => Counterparty::TYPE_CUSTOMER, 'currency_code' => 'GHS',
        ]);
    }

    public function test_the_list_shows_credits_and_what_they_total(): void
    {
        $this->makeCreditNote('120.00');
        $this->makeCreditNote('80.00');

        $this->actingAs($this->userWith(['view_finance']))
            ->get(route('finance.credit-notes.index'))
            ->assertOk()
            ->assertSee('Ama Mensah')
            ->assertSee('200.00');
    }

    /**
     * Draft and cancelled credits are not money credited to anyone. Counting
     * them would overstate what the farm has given back.
     */
    public function test_the_total_counts_only_issued_credits(): void
    {
        $this->makeCreditNote('120.00');
        $this->makeCreditNote('500.00', status: CreditNote::STATUS_DRAFT);
        $this->makeCreditNote('900.00', status: CreditNote::STATUS_CANCELLED);

        $this->actingAs($this->userWith(['view_finance']))
            ->get(route('finance.credit-notes.index'))
            ->assertOk()
            ->assertSee('120.00')
            ->assertDontSee('1,520.00');
    }

    public function test_a_credit_note_can_be_opened(): void
    {
        $note = $this->makeCreditNote('120.00');

        $this->actingAs($this->userWith(['view_finance']))
            ->get(route('finance.credit-notes.show', $note))
            ->assertOk()
            ->assertSee($note->credit_note_number)
            ->assertSee('Two piglets returned');
    }

    public function test_viewing_requires_finance_access(): void
    {
        $note = $this->makeCreditNote('120.00');

        $this->actingAs($this->userWith(['record_expenses']))
            ->get(route('finance.credit-notes.show', $note))
            ->assertForbidden();
    }

    /**
     * §29's guarantees — "credit note created from sales return" and
     * "idempotency key per sales return" — are anchored to the return that
     * caused the credit. A screen that minted them directly would allow revenue
     * reversed with no return to explain it, and a second reversal for the same
     * goods. There is no create route, and this asserts it stays that way.
     */
    public function test_there_is_no_route_to_raise_a_credit_note_directly(): void
    {
        $this->assertFalse(RouteFacade::has('finance.credit-notes.create'));
        $this->assertFalse(RouteFacade::has('finance.credit-notes.store'));

        foreach (RouteFacade::getRoutes() as $route) {
            if (str_starts_with((string) $route->getName(), 'finance.credit-notes.')) {
                $this->assertSame(
                    ['GET', 'HEAD'],
                    array_values(array_diff($route->methods(), ['OPTIONS'])),
                    "finance.credit-notes.* must be read-only; {$route->getName()} is not.",
                );
            }
        }
    }

    public function test_the_surface_closes_when_sales_and_money_in_is_switched_off(): void
    {
        $this->app->make(OrganizationFeatureService::class)
            ->disable(FeatureCatalog::FINANCE_SALES_MONEY_IN, $this->org);

        $this->actingAs($this->userWith(['view_finance']))
            ->get(route('finance.credit-notes.index'))
            ->assertNotFound();
    }

    /* ---------------------------------------------------------------- */

    protected function makeCreditNote(string $total, string $status = CreditNote::STATUS_POSTED): CreditNote
    {
        static $counter = 0;

        return CreditNote::create([
            'organization_id'    => $this->org->id,
            'credit_note_number' => 'CN-2026-' . str_pad((string) (++$counter), 6, '0', STR_PAD_LEFT),
            'customer_id'        => $this->customer->id,
            'currency_code'      => 'GHS',
            'subtotal'           => $total,
            'tax_total'          => '0.00',
            'total'              => $total,
            'issue_date'         => '2026-05-20',
            'accounting_date'    => '2026-05-20',
            'reason'             => 'Two piglets returned',
            'status'             => $status,
            'idempotency_key'    => 'cn-screen-' . $counter,
            'created_by_user_id' => $this->user->id,
        ]);
    }

    /** @param  list<string>  $permissions */
    protected function userWith(array $permissions): User
    {
        static $sequence = 0;
        $role = Role::firstOrCreate(['name' => 'phase7-cn-' . (++$sequence)]);

        foreach ($permissions as $name) {
            $permission = Permission::firstOrCreate(
                ['name' => $name],
                ['name' => $name, 'description' => $name, 'module' => 'finance'],
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        return User::forceCreate([
            'name'              => 'CN tester ' . $sequence,
            'email'             => "cn-{$sequence}@example.com",
            'password'          => bcrypt('secret'),
            'role'              => $role->name,
            'role_id'           => $role->id,
            'organization_id'   => $this->org->id,
            'active_status'     => true,
            'email_verified_at' => now(),
        ]);
    }
}
