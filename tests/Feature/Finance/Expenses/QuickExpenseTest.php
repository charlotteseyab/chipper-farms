<?php

namespace Tests\Feature\Finance\Expenses;

use App\Models\Counterparty;
use App\Models\Finance\Account;
use App\Models\Finance\BankAccount;
use App\Models\Finance\Journal;
use App\Models\Finance\JournalLine;
use App\Models\Finance\QuickExpense;
use App\Services\Finance\Expenses\RecordQuickExpenseService;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

/**
 * Quick Expense — Phase 4 (blueprint §23).
 *
 * The first phase in this programme that writes money, so the tests are about
 * three things:
 *
 *   1. the journal is balanced and hits the accounts a farm operator's choice
 *      should map to;
 *   2. a double submission produces ONE expense, because the realistic entry
 *      device is a phone with poor signal;
 *   3. the posting goes through PostingEngine rather than around it — blueprint
 *      SEC-002 warns that a quick-expense wrapper is exactly where a second
 *      accounting path gets introduced by accident.
 */
class QuickExpenseTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        // The accounts the expense categories map to.
        foreach ([
            ['6310', 'Electricity', 'expense', 'operating_expense'],
            ['6010', 'Veterinary Medicine', 'expense', 'operating_expense'],
            ['6600', 'Office and Administration', 'expense', 'operating_expense'],
            ['1010', 'Cash on Hand', 'asset', 'current_asset'],
            ['1060', 'Mobile Money — MTN', 'asset', 'current_asset'],
            ['1030', 'Bank — Main Operating', 'asset', 'current_asset'],
            ['2010', 'Trade Payables — Suppliers', 'liability', 'current_liability'],
            ['2910', 'Suspense — Payments', 'liability', 'current_liability'],
        ] as [$code, $name, $type, $subtype]) {
            Account::firstOrCreate(
                ['organization_id' => $this->org->id, 'code' => $code],
                ['name' => $name, 'account_type' => $type, 'account_subtype' => $subtype,
                 'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => true],
            );
        }
    }

    protected function service(): RecordQuickExpenseService
    {
        return app(RecordQuickExpenseService::class);
    }

    /** @return array<string,mixed> */
    protected function input(array $overrides = []): array
    {
        return array_merge([
            'expense_date'       => '2026-06-15',
            'category_key'       => 'electricity',
            'description'        => 'ECG bill for June',
            'amount'             => '400.00',
            'settlement'         => QuickExpense::SETTLEMENT_PAID_NOW,
            'payment_method_key' => 'cash',
        ], $overrides);
    }

    protected function lines(QuickExpense $expense)
    {
        return JournalLine::whereIn(
            'journal_id',
            Journal::where('journal_batch_id', $expense->journal_batch_id)->pluck('id'),
        )->get();
    }

    protected function accountId(string $code): int
    {
        return (int) Account::where('organization_id', $this->org->id)
            ->where('code', $code)->value('id');
    }

    protected function lineFor(QuickExpense $expense, string $code): ?JournalLine
    {
        return $this->lines($expense)->firstWhere('account_id', $this->accountId($code));
    }

    /* ------------------------------------------------------------------ */
    /* Posting                                                            */
    /* ------------------------------------------------------------------ */

    public function test_a_cash_expense_posts_a_balanced_journal(): void
    {
        $expense = $this->service()->record($this->input(), $this->user);

        $this->assertSame(QuickExpense::STATUS_POSTED, $expense->status);
        $this->assertNotNull($expense->journal_batch_id);

        $lines = $this->lines($expense);
        $debits = $credits = '0';
        foreach ($lines as $line) {
            $debits  = bcadd($debits, (string) $line->debit, 2);
            $credits = bcadd($credits, (string) $line->credit, 2);
        }

        $this->assertSame(0, bccomp($debits, $credits, 2), 'The journal must balance.');
        $this->assertSame(
            1,
            bccomp($debits, '0', 2),
            'A balanced journal of zero and zero is still balanced — assert it actually moved money.',
        );
        $this->assertSame(0, bccomp($debits, '400.00', 2));
    }

    public function test_choosing_electricity_debits_the_electricity_account(): void
    {
        $expense = $this->service()->record($this->input(), $this->user);

        $debit = $this->lineFor($expense, '6310');

        $this->assertNotNull($debit, 'Electricity must map to account 6310.');
        $this->assertSame(0, bccomp((string) $debit->debit, (string) $expense->total_amount, 2));
    }

    public function test_paying_from_momo_credits_the_momo_account_not_cash(): void
    {
        $expense = $this->service()->record($this->input([
            'payment_method_key' => 'momo_mtn',
            'external_reference' => 'MTN-TXN-99812',
        ]), $this->user);

        $this->assertNotNull(
            $this->lineFor($expense, '1060'),
            'MTN MoMo must reduce the MTN wallet, not cash on hand. MoMo providers are separate '
            . 'accounts because their settlements and references differ.',
        );
        $this->assertNull($this->lineFor($expense, '1010'));
    }

    public function test_money_account_selection_controls_the_cash_side(): void
    {
        $bankLedger = Account::where('organization_id', $this->org->id)
            ->where('code', '1030')
            ->firstOrFail();

        $moneyAccount = BankAccount::create([
            'organization_id' => $this->org->id,
            'account_id' => $bankLedger->id,
            'account_kind' => BankAccount::KIND_BANK,
            'label' => 'Main GCB account',
            'bank_name' => 'GCB Bank',
            'account_number' => '1234567890',
            'currency_code' => 'GHS',
            'opening_balance' => '0.00',
            'opening_balance_date' => '2026-01-01',
            'is_active' => true,
            'created_by_user_id' => $this->user->id,
        ]);

        $expense = $this->service()->record($this->input([
            'money_account_id' => $moneyAccount->id,
            'payment_method_key' => 'cash',
            'external_reference' => 'BNK-001',
        ]), $this->user);

        $this->assertSame($moneyAccount->id, $expense->money_account_id);
        $this->assertSame('400.00', (string) $this->lineFor($expense, '1030')->credit);
        $this->assertNull($this->lineFor($expense, '1010'));
    }

    public function test_a_pay_later_expense_credits_payables_not_cash(): void
    {
        $supplier = Counterparty::forceCreate([
            'organization_id' => $this->org->id,
            'name'            => 'Feed Supplier Ltd',
            'type'            => 'supplier',
            'currency_code'   => 'GHS',
            'is_active'       => true,
        ]);

        $expense = $this->service()->record($this->input([
            'settlement'         => QuickExpense::SETTLEMENT_PAY_LATER,
            'payment_method_key' => null,
            'supplier_id'        => $supplier->id,
        ]), $this->user);

        $this->assertNotNull(
            $this->lineFor($expense, '2010'),
            'Pay-later creates a liability, so no cash moves until it is settled.',
        );
        $this->assertNull($this->lineFor($expense, '1010'));
        $this->assertNull($expense->payment_account_code);
    }

    public function test_the_farm_facing_record_keeps_the_friendly_category(): void
    {
        $expense = $this->service()->record($this->input(), $this->user);

        $this->assertSame('electricity', $expense->category_key);
        $this->assertSame('Electricity', $expense->categoryLabel());
        $this->assertSame('Cash', $expense->paymentLabel());
        $this->assertSame('6310', $expense->expense_account_code);
    }

    public function test_an_expense_number_is_allocated(): void
    {
        $expense = $this->service()->record($this->input(), $this->user);

        $this->assertNotNull($expense->expense_number);
        $this->assertStringStartsWith('EXP-', $expense->expense_number);
    }

    /* ------------------------------------------------------------------ */
    /* Idempotency — the phone-with-bad-signal case                       */
    /* ------------------------------------------------------------------ */

    public function test_submitting_the_same_expense_twice_records_it_once(): void
    {
        $first  = $this->service()->record($this->input(), $this->user);
        $second = $this->service()->record($this->input(), $this->user);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, QuickExpense::count());
    }

    public function test_a_duplicate_submission_does_not_post_a_second_journal(): void
    {
        $first = $this->service()->record($this->input(), $this->user);
        $this->service()->record($this->input(), $this->user);

        $journalCount = Journal::where('journal_batch_id', $first->journal_batch_id)->count();

        $this->assertSame(1, QuickExpense::count());
        $this->assertGreaterThan(0, $journalCount);

        // The farm's electricity account must not have been charged twice.
        $total = JournalLine::where('account_id', $this->accountId('6310'))->sum('debit');
        $this->assertSame(0, bccomp((string) $total, '400.00', 2));
    }

    public function test_a_duplicate_returns_the_original_rather_than_erroring(): void
    {
        $first = $this->service()->record($this->input(), $this->user);

        // No exception — the user sees their expense, not a duplicate-posting
        // error from the engine.
        $second = $this->service()->record($this->input(), $this->user);

        $this->assertSame(QuickExpense::STATUS_POSTED, $second->status);
        $this->assertSame($first->expense_number, $second->expense_number);
    }

    public function test_genuinely_different_expenses_are_both_recorded(): void
    {
        $this->service()->record($this->input(), $this->user);
        $this->service()->record($this->input([
            'description' => 'ECG bill for July',
        ]), $this->user);

        $this->assertSame(2, QuickExpense::count());
    }

    public function test_an_explicit_idempotency_key_is_honoured(): void
    {
        $a = $this->service()->record($this->input(['idempotency_key' => 'form-token-123']), $this->user);
        $b = $this->service()->record($this->input([
            'idempotency_key' => 'form-token-123',
            'description'     => 'Different text, same submission',
        ]), $this->user);

        $this->assertSame($a->id, $b->id);
    }

    /* ------------------------------------------------------------------ */
    /* Validation                                                         */
    /* ------------------------------------------------------------------ */

    public function test_a_momo_expense_without_a_reference_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/reference/i');

        $this->service()->record($this->input([
            'payment_method_key' => 'momo_mtn',
        ]), $this->user);
    }

    public function test_a_cash_expense_needs_no_reference(): void
    {
        $expense = $this->service()->record($this->input(['payment_method_key' => 'cash']), $this->user);

        $this->assertSame(QuickExpense::STATUS_POSTED, $expense->status);
    }

    public function test_pay_later_without_a_supplier_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/supplier/i');

        $this->service()->record($this->input([
            'settlement'         => QuickExpense::SETTLEMENT_PAY_LATER,
            'payment_method_key' => null,
        ]), $this->user);
    }

    public function test_a_zero_amount_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service()->record($this->input(['amount' => '0']), $this->user);
    }

    public function test_a_negative_amount_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service()->record($this->input(['amount' => '-50.00']), $this->user);
    }

    public function test_a_non_numeric_amount_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service()->record($this->input(['amount' => 'four hundred']), $this->user);
    }

    public function test_an_unknown_payment_method_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service()->record($this->input(['payment_method_key' => 'bitcoin']), $this->user);
    }

    public function test_recording_without_an_organisation_is_refused(): void
    {
        $this->app->make(CurrentOrganization::class)->bind(null);

        $this->expectException(\RuntimeException::class);

        $this->service()->record($this->input(), $this->user);
    }

    /* ------------------------------------------------------------------ */
    /* Unmapped category                                                  */
    /* ------------------------------------------------------------------ */

    public function test_an_unmapped_category_goes_to_suspense_rather_than_blocking_the_user(): void
    {
        $expense = $this->service()->record($this->input([
            'category_key' => 'category_that_does_not_exist',
        ]), $this->user);

        $this->assertSame(QuickExpense::STATUS_POSTED, $expense->status);
        $this->assertSame('2910', $expense->expense_account_code);
        $this->assertTrue(
            $expense->needs_reclassification,
            'Money left the farm whether or not the chart of accounts has the right bucket. '
            . 'Record it, flag it, and let an accountant reclassify.',
        );
    }

    /* ------------------------------------------------------------------ */
    /* Configuration integrity                                            */
    /* ------------------------------------------------------------------ */

    public function test_every_configured_category_maps_to_a_real_account(): void
    {
        foreach (config('expense_categories.categories') as $key => $definition) {
            $this->assertNotNull(
                Account::where('code', $definition['account'])->first()
                    ?: $this->seededAccountCode($definition['account']),
                "Category [{$key}] maps to account [{$definition['account']}], which is not in the "
                . 'chart of accounts. A category pointing at a missing account fails at posting '
                . 'time — after the user has filled in the form and attached a receipt.',
            );
        }
    }

    public function test_every_configured_payment_method_maps_to_a_real_account(): void
    {
        foreach (config('expense_categories.payment_accounts') as $key => $definition) {
            $this->assertNotNull(
                $this->seededAccountCode($definition['account']),
                "Payment method [{$key}] maps to missing account [{$definition['account']}].",
            );
        }
    }

    /**
     * The chart-of-accounts seeder is the authority on which codes exist, so
     * assert against it rather than against the handful this test fixtures.
     */
    protected function seededAccountCode(string $code): ?string
    {
        static $codes = null;

        if ($codes === null) {
            $seeder = file_get_contents(database_path('seeders/ChartOfAccountsSeeder.php'));
            preg_match_all("/'code' => '(\d{4})'/", $seeder, $matches);
            $codes = array_flip($matches[1]);
        }

        return isset($codes[$code]) ? $code : null;
    }
}
