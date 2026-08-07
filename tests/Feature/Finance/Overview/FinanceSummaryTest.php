<?php

namespace Tests\Feature\Finance\Overview;

use App\Models\Finance\Account;
use App\Models\Finance\SimplePurchaseLine;
use App\Models\Organization;
use App\Services\Finance\Expenses\RecordQuickExpenseService;
use App\Services\Finance\Overview\FinanceSummaryService;
use App\Services\Finance\Purchases\RecordSimplePurchaseService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

/**
 * Overview money cards — Phase 7 (blueprint §22), deferred from Phase 3.
 *
 * The cards were held back until the flows that own each figure existed, so
 * these tests drive them through the REAL workflows — a recorded expense, a
 * recorded purchase — rather than by inserting journal rows by hand. A card
 * that only agrees with hand-built fixtures proves nothing about what a farm
 * will actually see.
 */
class FinanceSummaryTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        foreach ([
            ['1010', 'Cash on Hand', 'asset', 'current_asset'],
            ['1120', 'Trade Receivables', 'asset', 'current_asset'],
            ['2010', 'Trade Payables', 'liability', 'current_liability'],
            ['2910', 'Suspense — Payments', 'liability', 'current_liability'],
            ['4110', 'Feed Sales Revenue', 'revenue', 'operating_revenue'],
            ['6310', 'Electricity', 'expense', 'operating_expense'],
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

    protected function summary(): FinanceSummaryService
    {
        return app(FinanceSummaryService::class);
    }

    protected function today(): string
    {
        return CarbonImmutable::now()->toDateString();
    }

    protected function recordExpense(string $amount, ?string $date = null): void
    {
        app(RecordQuickExpenseService::class)->record([
            'expense_date'       => $date ?? $this->today(),
            'category_key'       => 'electricity',
            'description'        => 'ECG bill ' . uniqid(),
            'amount'             => $amount,
            'settlement'         => 'paid_now',
            'payment_method_key' => 'cash',
        ], $this->user);
    }

    protected function recordPurchaseOnCredit(string $amount): void
    {
        $supplier = \App\Models\Counterparty::forceCreate([
            'organization_id' => $this->org->id, 'name' => 'Supplier ' . uniqid(),
            'type' => 'supplier', 'currency_code' => 'GHS', 'is_active' => true,
        ]);

        app(RecordSimplePurchaseService::class)->record([
            'purchase_date' => $this->today(),
            'settlement'    => \App\Models\Finance\SimplePurchase::SETTLEMENT_PAY_LATER,
            'supplier_id'   => $supplier->id,
            'lines'         => [[
                'line_kind'    => SimplePurchaseLine::KIND_EXPENSE,
                'category_key' => 'transport',
                'description'  => 'Haulage',
                'quantity'     => '1',
                'unit_cost'    => $amount,
            ]],
        ], $this->user);
    }

    /* ------------------------------------------------------------------ */

    public function test_a_farm_with_no_activity_shows_zeroes_not_nulls(): void
    {
        $cards = $this->summary()->cards();

        foreach (['cash_available', 'sales_this_month', 'expenses_this_month',
                  'net_this_month', 'customers_owing', 'bills_to_pay'] as $key) {
            $this->assertSame('0.00', $cards[$key], "[{$key}] must be a zero figure, not null.");
        }
    }

    public function test_a_recorded_expense_appears_in_money_out(): void
    {
        $this->recordExpense('400.00');

        $cards = $this->summary()->cards();

        $this->assertSame(0, bccomp($cards['expenses_this_month'], '400.00', 2));
    }

    public function test_expenses_are_reported_as_a_positive_number(): void
    {
        $this->recordExpense('400.00');

        $this->assertSame(
            1,
            bccomp($this->summary()->cards()['expenses_this_month'], '0', 2),
            'Money out must read as a positive figure. Returning raw debit-minus-credit and '
            . 'letting the view call abs() would hide a genuinely negative total.',
        );
    }

    public function test_paying_cash_reduces_cash_available(): void
    {
        $before = $this->summary()->cards()['cash_available'];

        $this->recordExpense('400.00');

        $after = $this->summary()->cards()['cash_available'];

        $this->assertSame(0, bccomp(bcsub($before, $after, 2), '400.00', 2));
    }

    public function test_a_credit_purchase_raises_bills_to_pay_without_touching_cash(): void
    {
        $cashBefore = $this->summary()->cards()['cash_available'];

        $this->recordPurchaseOnCredit('250.00');

        $cards = $this->summary()->cards();

        $this->assertSame(0, bccomp($cards['bills_to_pay'], '250.00', 2));
        $this->assertSame(
            0,
            bccomp($cards['cash_available'], $cashBefore, 2),
            'Buying on account moves no cash until the bill is paid.',
        );
    }

    public function test_bills_to_pay_is_reported_as_a_positive_number(): void
    {
        $this->recordPurchaseOnCredit('250.00');

        $this->assertSame(
            1,
            bccomp($this->summary()->cards()['bills_to_pay'], '0', 2),
            'Payables are a credit-balance account; the card must show what is owed, not a '
            . 'negative.',
        );
    }

    public function test_the_net_figure_is_money_in_minus_money_out(): void
    {
        $this->recordExpense('400.00');

        $cards = $this->summary()->cards();

        $this->assertSame(
            0,
            bccomp(
                $cards['net_this_month'],
                bcsub($cards['sales_this_month'], $cards['expenses_this_month'], 2),
                2,
            ),
        );
    }

    public function test_a_loss_month_shows_a_negative_net_rather_than_hiding_it(): void
    {
        $this->recordExpense('400.00');

        $this->assertSame(
            -1,
            bccomp($this->summary()->cards()['net_this_month'], '0', 2),
            'A farm that spent more than it earned needs to see that, not an absolute value.',
        );
    }

    public function test_last_months_spend_does_not_count_towards_this_month(): void
    {
        $this->recordExpense('400.00', CarbonImmutable::now()->subMonths(2)->toDateString());

        $this->assertSame(
            0,
            bccomp($this->summary()->cards()['expenses_this_month'], '0.00', 2),
            'The month figures are windowed; an older expense must not inflate them.',
        );
    }

    public function test_an_older_expense_still_affects_cash_available(): void
    {
        $this->recordExpense('400.00', CarbonImmutable::now()->subMonths(2)->toDateString());

        $this->assertSame(
            -1,
            bccomp($this->summary()->cards()['cash_available'], '0', 2),
            'Cash available is a balance, not a monthly window — money spent two months ago is '
            . 'still gone.',
        );
    }

    public function test_figures_do_not_leak_across_organisations(): void
    {
        $this->recordExpense('400.00');

        $other = Organization::create([
            'slug' => 'other-' . uniqid(), 'name' => 'Other Farm', 'is_active' => true,
        ]);

        $cards = $this->summary()->cards(null, $other);

        $this->assertSame('0.00', $cards['expenses_this_month']);
        $this->assertSame('0.00', $cards['cash_available']);
    }

    public function test_no_organisation_context_yields_zeroes_not_every_farms_totals(): void
    {
        $this->recordExpense('400.00');
        $this->app->make(CurrentOrganization::class)->bind(null);

        $this->assertSame('0.00', $this->summary()->cards()['expenses_this_month']);
    }

    public function test_every_figure_is_a_decimal_string(): void
    {
        $this->recordExpense('400.00');

        foreach ($this->summary()->cards() as $key => $value) {
            $this->assertIsString($value, "[{$key}] must stay a decimal string.");
        }
    }
}
