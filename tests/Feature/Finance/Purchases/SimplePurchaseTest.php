<?php

namespace Tests\Feature\Finance\Purchases;

use App\Models\Counterparty;
use App\Models\DomainEvent;
use App\Models\Finance\Account;
use App\Models\Finance\Journal;
use App\Models\Finance\JournalLine;
use App\Models\Finance\SimplePurchase;
use App\Models\Finance\SimplePurchaseLine;
use App\Models\RawMaterial;
use App\Services\Finance\Purchases\RecordSimplePurchaseService;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

/**
 * Simple Purchase — Phase 6b (blueprint §24).
 *
 * The governing test in this file is
 * `test_a_stock_purchase_debits_inventory_exactly_once`.
 *
 * Phase 6's investigation established that `RawMaterialReceivedSubscriber`
 * already posts the inventory leg for a receipt. A purchase workflow that also
 * posted `Dr Inventory / Cr Payables` would debit inventory twice per delivery,
 * overstate it by the value of every stock purchase, drift the
 * subledger-to-control reconciliation by that amount, and propagate the error
 * into COGS on every later sale.
 *
 * Everything else here is ordinary. That one test is why the phase was designed
 * the way it was.
 */
class SimplePurchaseTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        foreach ([
            ['1010', 'Cash on Hand', 'asset', 'current_asset'],
            ['1030', 'Bank — Main Operating', 'asset', 'current_asset'],
            ['2010', 'Trade Payables — Suppliers', 'liability', 'current_liability'],
            ['2910', 'Suspense — Payments', 'liability', 'current_liability'],
            ['1210', 'Raw Materials Inventory', 'asset', 'current_asset'],
            ['6730', 'Delivery and Transport', 'expense', 'operating_expense'],
            ['6600', 'Office and Administration', 'expense', 'operating_expense'],
        ] as [$code, $name, $type, $subtype]) {
            Account::firstOrCreate(
                ['organization_id' => $this->org->id, 'code' => $code],
                ['name' => $name, 'account_type' => $type, 'account_subtype' => $subtype,
                 'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => true],
            );
        }
    }

    protected function service(): RecordSimplePurchaseService
    {
        return app(RecordSimplePurchaseService::class);
    }

    protected function supplier(): Counterparty
    {
        return Counterparty::forceCreate([
            'organization_id' => $this->org->id,
            'name'            => 'Maize Supplier Ltd',
            'type'            => 'supplier',
            'currency_code'   => 'GHS',
            'is_active'       => true,
        ]);
    }

    protected function material(): RawMaterial
    {
        // raw_materials has no organization_id — the catalogue is shared
        // across tenants (Phase 0 §6). Tenancy is enforced on the movement.
        return RawMaterial::forceCreate([
            'name'      => 'Maize ' . uniqid(),
            'unit'      => 'kg',
            'unit_cost' => '8.00',
        ]);
    }

    protected function accountId(string $code): int
    {
        return (int) Account::where('organization_id', $this->org->id)
            ->where('code', $code)->value('id');
    }

    protected function expenseLine(array $overrides = []): array
    {
        return array_merge([
            'line_kind'    => SimplePurchaseLine::KIND_EXPENSE,
            'category_key' => 'transport',
            'description'  => 'Delivery charge',
            'quantity'     => '1',
            'unit_cost'    => '150.00',
        ], $overrides);
    }

    protected function stockLine(RawMaterial $material, array $overrides = []): array
    {
        return array_merge([
            'line_kind'       => SimplePurchaseLine::KIND_STOCK,
            'raw_material_id' => $material->id,
            'description'     => 'Maize, 100kg',
            'quantity'        => '100',
            'unit'            => 'kg',
            'unit_cost'       => '8.00',
        ], $overrides);
    }

    /** @return array<string,mixed> */
    protected function input(array $overrides = []): array
    {
        return array_merge([
            'purchase_date'      => '2026-06-15',
            'settlement'         => SimplePurchase::SETTLEMENT_PAID_NOW,
            'payment_method_key' => 'cash',
            'lines'              => [$this->expenseLine()],
        ], $overrides);
    }

    protected function settlementLines(SimplePurchase $purchase)
    {
        return JournalLine::whereIn(
            'journal_id',
            Journal::where('journal_batch_id', $purchase->settlement_batch_id)->pluck('id'),
        )->get();
    }

    /* ------------------------------------------------------------------ */
    /* The governing test                                                 */
    /* ------------------------------------------------------------------ */

    public function test_a_stock_purchase_debits_inventory_exactly_once(): void
    {
        $material = $this->material();

        $purchase = $this->service()->record($this->input([
            'supplier_id' => $this->supplier()->id,
            'lines'       => [$this->stockLine($material)],
        ]), $this->user);

        $settlementAccounts = Account::whereIn(
            'id',
            $this->settlementLines($purchase)->pluck('account_id')->unique()->all(),
        )->get();

        // Inventory control accounts are the 12xx range — 1200 Inventory and its
        // children 1210 Raw Materials, 1220 Finished Feed, 1230 Live Pig, and so
        // on. They are matched by CODE, not by account_subtype: every one of them
        // is subtype 'current_asset', so filtering on a subtype of 'inventory'
        // matches nothing and the assertion passes no matter what the service
        // does. That was this test's first form, and it was vacuous.
        $inventoryTouched = $settlementAccounts
            ->filter(fn (Account $a): bool => str_starts_with((string) $a->code, '12'))
            ->pluck('code')
            ->all();

        $this->assertSame(
            [],
            $inventoryTouched,
            'The purchase settlement journal debited an inventory account. '
            . 'RawMaterialReceivedSubscriber already posts that leg, so this debits inventory '
            . 'twice for one delivery, overstates it by the value of every stock purchase, and '
            . 'propagates the error into COGS on every subsequent sale.',
        );

        // The positive half: the settlement must clear what the subscriber
        // credited. Asserting only the absence of inventory would also pass if
        // the service posted nothing at all.
        $suspenseLine = $this->settlementLines($purchase)
            ->firstWhere('account_id', $this->accountId('2910'));

        $this->assertNotNull(
            $suspenseLine,
            'A stock line must debit the suspense account the receipt subscriber credited, so the '
            . 'clearing account nets to zero once both halves have run.',
        );
        $this->assertSame(0, bccomp((string) $suspenseLine->debit, '800.00', 2));
    }

    public function test_the_receipt_is_handed_to_the_inventory_service_not_reimplemented(): void
    {
        $material = $this->material();

        $purchase = $this->service()->record($this->input([
            'supplier_id' => $this->supplier()->id,
            'lines'       => [$this->stockLine($material)],
        ]), $this->user);

        $line = $purchase->lines()->firstOrFail();

        $this->assertNotNull(
            $line->inventory_movement_id,
            'A stock line must produce an inventory movement through ReceiveRawMaterialService, '
            . 'which is what dispatches the event the GL subscriber reacts to.',
        );
    }

    public function test_a_stock_receipt_raises_the_domain_event_that_posts_the_inventory_leg(): void
    {
        $material = $this->material();

        $this->service()->record($this->input([
            'supplier_id' => $this->supplier()->id,
            'lines'       => [$this->stockLine($material)],
        ]), $this->user);

        $this->assertTrue(
            DomainEvent::where('event_type', \App\Domain\Events\Inventory\RawMaterialReceived::TYPE)
                ->where('organization_id', $this->org->id)
                ->exists(),
            'Without the event there is no inventory GL at all — the subledger would move and the '
            . 'ledger would not.',
        );
    }

    /* ------------------------------------------------------------------ */
    /* Settlement                                                         */
    /* ------------------------------------------------------------------ */

    public function test_an_expense_line_posts_to_its_category_account(): void
    {
        $purchase = $this->service()->record($this->input(), $this->user);

        $line = $this->settlementLines($purchase)
            ->firstWhere('account_id', $this->accountId('6730'));

        $this->assertNotNull($line, 'A transport line must reach the transport account.');
        $this->assertSame(0, bccomp((string) $line->debit, '150.00', 2));
    }

    public function test_a_paid_now_purchase_credits_cash(): void
    {
        $purchase = $this->service()->record($this->input(), $this->user);

        $credit = $this->settlementLines($purchase)
            ->firstWhere('account_id', $this->accountId('1010'));

        $this->assertNotNull($credit);
        $this->assertSame(0, bccomp((string) $credit->credit, '150.00', 2));
    }

    public function test_a_pay_later_purchase_credits_payables_not_cash(): void
    {
        $purchase = $this->service()->record($this->input([
            'settlement'         => SimplePurchase::SETTLEMENT_PAY_LATER,
            'payment_method_key' => null,
            'supplier_id'        => $this->supplier()->id,
        ]), $this->user);

        $lines = $this->settlementLines($purchase);

        $this->assertNotNull($lines->firstWhere('account_id', $this->accountId('2010')));
        $this->assertNull($lines->firstWhere('account_id', $this->accountId('1010')));
    }

    public function test_the_settlement_journal_balances(): void
    {
        $purchase = $this->service()->record($this->input([
            'supplier_id' => $this->supplier()->id,
            'lines'       => [$this->expenseLine(), $this->expenseLine([
                'description' => 'Loading fee', 'unit_cost' => '50.00',
            ])],
        ]), $this->user);

        $debits = $credits = '0';
        foreach ($this->settlementLines($purchase) as $line) {
            $debits  = bcadd($debits, (string) $line->debit, 2);
            $credits = bcadd($credits, (string) $line->credit, 2);
        }

        $this->assertSame(0, bccomp($debits, $credits, 2));
        $this->assertSame(0, bccomp($debits, '200.00', 2));
    }

    public function test_a_mixed_purchase_handles_both_kinds_of_line(): void
    {
        $material = $this->material();

        $purchase = $this->service()->record($this->input([
            'supplier_id' => $this->supplier()->id,
            'lines'       => [$this->stockLine($material), $this->expenseLine()],
        ]), $this->user);

        $this->assertSame(2, $purchase->lines()->count());
        $this->assertSame(
            0,
            bccomp((string) $purchase->total_amount, '950.00', 2),
            '100kg at 8.00 plus a 150.00 delivery charge.',
        );
    }

    /* ------------------------------------------------------------------ */
    /* Idempotency and validation                                         */
    /* ------------------------------------------------------------------ */

    public function test_the_same_purchase_submitted_twice_is_recorded_once(): void
    {
        $first  = $this->service()->record($this->input(), $this->user);
        $second = $this->service()->record($this->input(), $this->user);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, SimplePurchase::count());
        $this->assertSame(1, SimplePurchaseLine::count());
    }

    public function test_a_duplicate_does_not_receive_the_stock_twice(): void
    {
        $material = $this->material();
        $input    = $this->input([
            'supplier_id' => $this->supplier()->id,
            'lines'       => [$this->stockLine($material)],
        ]);

        $this->service()->record($input, $this->user);
        $this->service()->record($input, $this->user);

        $this->assertSame(
            1,
            SimplePurchase::count(),
            'Receiving the same delivery twice would inflate stock on hand with goods that were '
            . 'never delivered.',
        );
    }

    public function test_a_purchase_with_no_lines_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service()->record($this->input(['lines' => []]), $this->user);
    }

    public function test_a_stock_line_without_an_item_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/needs an item/i');

        $this->service()->record($this->input([
            'lines' => [[
                'line_kind'   => SimplePurchaseLine::KIND_STOCK,
                'description' => 'Something',
                'quantity'    => '10',
                'unit_cost'   => '5.00',
            ]],
        ]), $this->user);
    }

    public function test_a_zero_quantity_line_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service()->record($this->input([
            'lines' => [$this->expenseLine(['quantity' => '0'])],
        ]), $this->user);
    }

    public function test_a_negative_unit_cost_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service()->record($this->input([
            'lines' => [$this->expenseLine(['unit_cost' => '-5.00'])],
        ]), $this->user);
    }

    public function test_pay_later_without_a_supplier_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/supplier/i');

        $this->service()->record($this->input([
            'settlement'         => SimplePurchase::SETTLEMENT_PAY_LATER,
            'payment_method_key' => null,
        ]), $this->user);
    }

    public function test_recording_without_an_organisation_is_refused(): void
    {
        $this->app->make(CurrentOrganization::class)->bind(null);

        $this->expectException(\RuntimeException::class);

        $this->service()->record($this->input(), $this->user);
    }
}
