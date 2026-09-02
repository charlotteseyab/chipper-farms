<?php

namespace Tests\Feature\Finance\Purchases;

use App\Models\Counterparty;
use App\Models\Finance\Account;
use App\Models\Finance\SimplePurchase;
use App\Models\Finance\SimplePurchaseLine;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\PurchaseRequest;
use App\Models\RawMaterial;
use App\Models\Role;
use App\Models\User;
use App\Services\Organizations\OrganizationFeatureService;
use App\Support\Features\FeatureCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

/**
 * Purchases screens — Phase 6b.
 *
 * The service's accounting is proven in SimplePurchaseTest. These cover the
 * seam: that the route is gated on both the feature and the permission, that
 * a stock purchase entered through the form actually reaches inventory, and
 * that the form's idempotency token survives a resubmit.
 */
class SimplePurchaseScreenTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        foreach ([
            ['1010', 'Cash on Hand', 'asset', 'current_asset'],
            ['2010', 'Trade Payables', 'liability', 'current_liability'],
            ['2910', 'Suspense — Payments', 'liability', 'current_liability'],
            ['6730', 'Delivery and Transport', 'expense', 'operating_expense'],
            ['6600', 'Office and Administration', 'expense', 'operating_expense'],
        ] as [$code, $name, $type, $subtype]) {
            Account::firstOrCreate(
                ['organization_id' => $this->org->id, 'code' => $code],
                ['name' => $name, 'account_type' => $type, 'account_subtype' => $subtype,
                 'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => true],
            );
        }

        foreach (['record_purchases', 'view_finance'] as $name) {
            Permission::firstOrCreate(['name' => $name], ['description' => 'T', 'module' => 'finance']);
        }
    }

    protected function userWith(array $permissions): User
    {
        $role = Role::create(['name' => 'r_' . uniqid()]);

        foreach ($permissions as $name) {
            $role->permissions()->attach(Permission::where('name', $name)->value('id'));
        }

        return User::forceCreate([
            'name' => 'U', 'email' => 'u_' . uniqid() . '@example.com',
            'password' => bcrypt('x'), 'role' => $role->name, 'role_id' => $role->id,
            'organization_id' => $this->org->id, 'active_status' => true,
        ]);
    }

    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'purchase_date'      => '2026-06-15',
            'settlement'         => SimplePurchase::SETTLEMENT_PAID_NOW,
            'payment_method_key' => 'cash',
            'idempotency_key'    => (string) Str::uuid(),
            'lines'              => [[
                'line_kind'    => SimplePurchaseLine::KIND_EXPENSE,
                'category_key' => 'transport',
                'description'  => 'Delivery charge',
                'quantity'     => '1',
                'unit_cost'    => '150.00',
            ]],
        ], $overrides);
    }

    /* ------------------------------------------------------------------ */

    public function test_the_list_renders_for_an_authorised_user(): void
    {
        $this->actingAs($this->userWith(['record_purchases', 'view_finance']))
            ->get(route('finance.purchases.index'))
            ->assertOk()
            ->assertSee('Purchases');
    }

    public function test_the_form_renders(): void
    {
        $this->actingAs($this->userWith(['record_purchases']))
            ->get(route('finance.purchases.create'))
            ->assertOk()
            ->assertSee('Record a purchase');
    }

    public function test_the_form_is_refused_without_the_permission(): void
    {
        $this->actingAs($this->userWith(['view_finance']))
            ->get(route('finance.purchases.create'))
            ->assertForbidden();
    }

    public function test_the_surface_disappears_when_the_feature_is_off(): void
    {
        app(OrganizationFeatureService::class)->disable(FeatureCatalog::FINANCE_SIMPLE_PURCHASE);

        $this->actingAs($this->userWith(['record_purchases']))
            ->get(route('finance.purchases.create'))
            ->assertNotFound();
    }

    public function test_a_purchase_submitted_through_the_form_is_recorded(): void
    {
        $this->actingAs($this->userWith(['record_purchases']))
            ->post(route('finance.purchases.store'), $this->payload())
            ->assertRedirect(route('finance.purchases.index'));

        $this->assertSame(1, SimplePurchase::count());
        $this->assertSame(0, bccomp((string) SimplePurchase::first()->total_amount, '150.00', 2));
    }

    public function test_a_stock_purchase_through_the_form_reaches_inventory(): void
    {
        $material = RawMaterial::forceCreate([
            'name' => 'Maize ' . uniqid(), 'unit' => 'kg', 'unit_cost' => '8.00',
        ]);
        $supplier = Counterparty::forceCreate([
            'organization_id' => $this->org->id, 'name' => 'Supplier',
            'type' => 'supplier', 'currency_code' => 'GHS', 'is_active' => true,
        ]);

        $this->actingAs($this->userWith(['record_purchases']))
            ->post(route('finance.purchases.store'), $this->payload([
                'supplier_id' => $supplier->id,
                'lines'       => [[
                    'line_kind'       => SimplePurchaseLine::KIND_STOCK,
                    'raw_material_id' => $material->id,
                    'description'     => 'Maize 100kg',
                    'quantity'        => '100',
                    'unit_cost'       => '8.00',
                ]],
            ]))
            ->assertRedirect();

        $line = SimplePurchaseLine::firstOrFail();

        $this->assertNotNull(
            $line->inventory_movement_id,
            'A stock line entered through the form must reach the inventory subledger, not just '
            . 'the purchase table.',
        );
    }

    public function test_the_forms_token_makes_a_resubmit_safe(): void
    {
        $payload = $this->payload();
        $user    = $this->userWith(['record_purchases']);

        $this->actingAs($user)->post(route('finance.purchases.store'), $payload);
        $this->actingAs($user)->post(route('finance.purchases.store'), $payload);

        $this->assertSame(
            1,
            SimplePurchase::count(),
            'The same form token replayed must not create a second purchase — a browser back-then-'
            . 'resubmit would otherwise receive the delivery twice.',
        );
    }

    public function test_a_service_refusal_becomes_a_form_error_not_a_crash(): void
    {
        $this->actingAs($this->userWith(['record_purchases']))
            ->post(route('finance.purchases.store'), $this->payload([
                'settlement'         => SimplePurchase::SETTLEMENT_PAY_LATER,
                'payment_method_key' => null,
                // No supplier — the service refuses.
            ]))
            ->assertSessionHasErrors('purchase');

        $this->assertSame(0, SimplePurchase::count());
    }

    public function test_a_purchase_with_no_lines_is_rejected_by_validation(): void
    {
        $this->actingAs($this->userWith(['record_purchases']))
            ->post(route('finance.purchases.store'), $this->payload(['lines' => []]))
            ->assertSessionHasErrors('lines');
    }

    public function test_the_list_does_not_show_another_organisations_purchases(): void
    {
        $other = Organization::create([
            'slug' => 'other-' . uniqid(), 'name' => 'Other Farm', 'is_active' => true,
        ]);

        SimplePurchase::forceCreate([
            'organization_id' => $other->id,
            'purchase_number' => 'PUR-2026-99999',
            'purchase_date'   => '2026-06-01',
            'settlement'      => SimplePurchase::SETTLEMENT_PAID_NOW,
            'subtotal'        => '999.00',
            'total_amount'    => '999.00',
            'currency_code'   => 'GHS',
            'status'          => SimplePurchase::STATUS_POSTED,
            'idempotency_key' => 'other-' . uniqid(),
        ]);

        $this->actingAs($this->userWith(['record_purchases', 'view_finance']))
            ->get(route('finance.purchases.index'))
            ->assertOk()
            ->assertDontSee('PUR-2026-99999');
    }

    public function test_purchase_requests_appear_in_the_workspace_without_leaking_other_farms(): void
    {
        $user = $this->userWith(['record_purchases', 'view_finance']);
        $other = Organization::create([
            'slug' => 'other-pr-' . uniqid(), 'name' => 'Other PR Farm', 'is_active' => true,
        ]);

        PurchaseRequest::forceCreate([
            'organization_id' => $this->org->id,
            'request_no' => 'PR-CURRENT-001',
            'request_date' => '2026-06-20',
            'requested_by' => $user->id,
            'status' => 'approved',
            'payment_arrangement' => 'after_delivery',
            'payment_progress' => 'not_paid',
            'total_amount' => '275.00',
            'purpose' => 'Current farm materials',
        ]);
        PurchaseRequest::forceCreate([
            'organization_id' => $other->id,
            'request_no' => 'PR-OTHER-999',
            'request_date' => '2026-06-20',
            'requested_by' => $user->id,
            'status' => 'approved',
            'payment_arrangement' => 'after_delivery',
            'payment_progress' => 'not_paid',
            'total_amount' => '999.00',
            'purpose' => 'Other farm materials',
        ]);

        $this->actingAs($user)
            ->get(route('finance.purchases.index', ['tab' => 'requests']))
            ->assertOk()
            ->assertSee('PR-CURRENT-001')
            ->assertSee('GHS 275.00')
            ->assertDontSee('PR-OTHER-999');
    }
}
