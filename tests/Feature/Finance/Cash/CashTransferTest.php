<?php

namespace Tests\Feature\Finance\Cash;

use App\Models\Finance\Account;
use App\Models\Finance\CashTransfer;
use App\Models\Finance\Journal;
use App\Models\Finance\JournalLine;
use App\Services\Finance\Cash\MoneyAccountService;
use App\Services\Finance\Cash\RecordCashTransferService;
use App\Services\Finance\Expenses\RecordQuickExpenseService;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

/**
 * Cash, Bank & MoMo — Phase 5 (blueprint §25, prompt §38–§44).
 *
 * The transfer tests carry the weight. Moving money between the farm's own
 * accounts is the most commonly mis-recorded farm transaction: entered as a
 * payment it becomes an expense, understating the month's profit by the whole
 * amount while the destination balance rises with no matching income.
 *
 * So the tests do not merely check that a journal exists — they assert that no
 * revenue or expense account appears in it at all.
 */
class CashTransferTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        foreach ([
            ['1010', 'Cash on Hand', 'asset', 'current_asset'],
            ['1020', 'Petty Cash', 'asset', 'current_asset'],
            ['1030', 'Bank — Main Operating', 'asset', 'current_asset'],
            ['1060', 'Mobile Money — MTN', 'asset', 'current_asset'],
            ['1061', 'Mobile Money — AirtelTigo', 'asset', 'current_asset'],
            ['1062', 'Mobile Money — Vodafone', 'asset', 'current_asset'],
            ['6310', 'Electricity', 'expense', 'operating_expense'],
            ['6600', 'Office and Administration', 'expense', 'operating_expense'],
            ['2910', 'Suspense — Payments', 'liability', 'current_liability'],
        ] as [$code, $name, $type, $subtype]) {
            Account::firstOrCreate(
                ['organization_id' => $this->org->id, 'code' => $code],
                ['name' => $name, 'account_type' => $type, 'account_subtype' => $subtype,
                 'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => true],
            );
        }
    }

    protected function transfers(): RecordCashTransferService
    {
        return app(RecordCashTransferService::class);
    }

    protected function money(): MoneyAccountService
    {
        return app(MoneyAccountService::class);
    }

    protected function accountId(string $code): int
    {
        return (int) Account::where('organization_id', $this->org->id)
            ->where('code', $code)->value('id');
    }

    /** @return array<string,mixed> */
    protected function input(array $overrides = []): array
    {
        return array_merge([
            'transfer_date'    => '2026-06-15',
            'from_account_id'  => $this->accountId('1060'),
            'to_account_id'    => $this->accountId('1030'),
            'amount'           => '2000.00',
            'external_reference' => 'MTN-CASHOUT-4471',
        ], $overrides);
    }

    protected function lines(CashTransfer $transfer)
    {
        return JournalLine::whereIn(
            'journal_id',
            Journal::where('journal_batch_id', $transfer->journal_batch_id)->pluck('id'),
        )->get();
    }

    /** Seed a money account with an opening balance via a real expense reversal-free route. */
    protected function fund(string $code, string $amount): void
    {
        // Fund by recording a transfer from a petty-cash float we first debit
        // through a posted expense-free journal is circular; instead use the
        // quick-expense path in reverse is not available. Simplest honest
        // approach: post an opening entry through the same engine.
        $engine = app(\App\Finance\Posting\PostingEngine::class);

        $engine->post(new \App\Finance\Drafts\JournalBatchDraft(
            batchType:   'auto_opening_float',
            postingDate: new \DateTimeImmutable('2026-06-01'),
            journals:    [new \App\Finance\Drafts\JournalDraft(
                journalType: 'opening_float',
                lines: [
                    new \App\Finance\Drafts\JournalLineDraft(
                        accountId: $this->accountId($code), debit: $amount, credit: '0',
                    ),
                    new \App\Finance\Drafts\JournalLineDraft(
                        accountId: $this->accountId('2910'), debit: '0', credit: $amount,
                    ),
                ],
                description: 'Opening float',
            )],
            description:    'Opening float',
            sourceModule:   'test',
            idempotencyKey: 'opening:' . $code . ':' . $amount,
            // JournalBatchDraft pairs these two — an idempotency key with no
            // posting purpose is rejected, because journal_postings stores them
            // together.
            postingPurpose: \App\Models\Finance\JournalPosting::PURPOSE_CASH_RECEIPT,
        ), $this->user);
    }

    /* ------------------------------------------------------------------ */
    /* The transfer must not be income or expense                         */
    /* ------------------------------------------------------------------ */

    public function test_a_transfer_touches_no_revenue_or_expense_account(): void
    {
        $transfer = $this->transfers()->record($this->input(), $this->user);

        $accountIds = $this->lines($transfer)->pluck('account_id')->all();

        $offending = Account::whereIn('id', $accountIds)
            ->whereIn('account_type', [Account::TYPE_REVENUE, Account::TYPE_EXPENSE])
            ->get();

        $this->assertCount(
            0,
            $offending,
            'A transfer that hits an expense account understates the month\'s profit by the whole '
            . 'amount while the destination balance rises with no matching income. This is the '
            . 'single most commonly mis-recorded farm transaction.',
        );
    }

    public function test_a_transfer_debits_the_destination_and_credits_the_source(): void
    {
        $transfer = $this->transfers()->record($this->input(), $this->user);

        $lines = $this->lines($transfer);

        $toLine   = $lines->firstWhere('account_id', $this->accountId('1030'));
        $fromLine = $lines->firstWhere('account_id', $this->accountId('1060'));

        $this->assertSame(0, bccomp((string) $toLine->debit, '2000.00', 2));
        $this->assertSame(0, bccomp((string) $fromLine->credit, '2000.00', 2));
    }

    public function test_a_transfer_balances(): void
    {
        $transfer = $this->transfers()->record($this->input(), $this->user);

        $debits = $credits = '0';
        foreach ($this->lines($transfer) as $line) {
            $debits  = bcadd($debits, (string) $line->debit, 2);
            $credits = bcadd($credits, (string) $line->credit, 2);
        }

        $this->assertSame(0, bccomp($debits, $credits, 2));
        $this->assertSame(0, bccomp($debits, '2000.00', 2));
    }

    public function test_a_transfer_leaves_total_money_unchanged(): void
    {
        $this->fund('1060', '5000.00');

        $before = $this->money()->totalAvailable();
        $this->transfers()->record($this->input(), $this->user);
        $after = $this->money()->totalAvailable();

        $this->assertSame(
            0,
            bccomp($before, $after, 2),
            'Moving money between the farm\'s own accounts cannot change how much it has.',
        );
    }

    /* ------------------------------------------------------------------ */
    /* Validation                                                         */
    /* ------------------------------------------------------------------ */

    public function test_transferring_to_the_same_account_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->transfers()->record($this->input([
            'to_account_id' => $this->accountId('1060'),
        ]), $this->user);
    }

    public function test_transferring_into_an_expense_account_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/money accounts/i');

        $this->transfers()->record($this->input([
            'to_account_id' => $this->accountId('6310'),
        ]), $this->user);
    }

    public function test_transferring_out_of_an_expense_account_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->transfers()->record($this->input([
            'from_account_id' => $this->accountId('6310'),
        ]), $this->user);
    }

    public function test_a_zero_transfer_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->transfers()->record($this->input(['amount' => '0']), $this->user);
    }

    public function test_a_negative_transfer_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->transfers()->record($this->input(['amount' => '-100']), $this->user);
    }

    public function test_a_transfer_without_an_organisation_is_refused(): void
    {
        $this->app->make(CurrentOrganization::class)->bind(null);

        $this->expectException(\RuntimeException::class);

        $this->transfers()->record($this->input(), $this->user);
    }

    /* ------------------------------------------------------------------ */
    /* Idempotency                                                        */
    /* ------------------------------------------------------------------ */

    public function test_the_same_transfer_submitted_twice_moves_the_money_once(): void
    {
        $first  = $this->transfers()->record($this->input(), $this->user);
        $second = $this->transfers()->record($this->input(), $this->user);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, CashTransfer::count());

        $moved = JournalLine::where('account_id', $this->accountId('1030'))->sum('debit');
        $this->assertSame(
            0,
            bccomp((string) $moved, '2000.00', 2),
            'A duplicated transfer moves the money twice, and unlike an expense there is no '
            . 'external invoice to notice the discrepancy against.',
        );
    }

    public function test_two_genuinely_different_transfers_are_both_recorded(): void
    {
        $this->transfers()->record($this->input(), $this->user);
        $this->transfers()->record($this->input([
            'amount' => '500.00', 'external_reference' => 'MTN-CASHOUT-4472',
        ]), $this->user);

        $this->assertSame(2, CashTransfer::count());
    }

    /* ------------------------------------------------------------------ */
    /* Money accounts                                                     */
    /* ------------------------------------------------------------------ */

    public function test_money_accounts_report_balances_from_the_ledger(): void
    {
        $this->fund('1010', '750.00');

        $accounts = collect($this->money()->accounts());
        $cash     = $accounts->firstWhere('code', '1010');

        $this->assertNotNull($cash);
        $this->assertSame(0, bccomp($cash['balance'], '750.00', 2));
    }

    public function test_mobile_money_providers_are_separate_accounts(): void
    {
        $codes = collect($this->money()->accounts())->pluck('code')->all();

        $this->assertContains('1060', $codes);
        $this->assertContains('1061', $codes);
        $this->assertContains('1062', $codes);

        $momo = collect($this->money()->accounts())->where('kind', 'momo');
        $this->assertGreaterThanOrEqual(
            3,
            $momo->count(),
            'Settlement timing and reference formats differ per provider, so a merged mobile '
            . 'money balance cannot be reconciled against any one statement.',
        );
    }

    public function test_an_overdrawn_account_is_flagged(): void
    {
        // Pay out more than the till ever held.
        app(RecordQuickExpenseService::class)->record([
            'expense_date'       => '2026-06-10',
            'category_key'       => 'electricity',
            'description'        => 'ECG bill',
            'amount'             => '300.00',
            'settlement'         => 'paid_now',
            'payment_method_key' => 'cash',
        ], $this->user);

        $overdrawn = collect($this->money()->overdrawn())->pluck('code')->all();

        $this->assertContains(
            '1010',
            $overdrawn,
            'A negative cash balance means the farm recorded paying out more than it ever had, '
            . 'which is a data problem worth showing rather than hiding.',
        );
    }

    public function test_total_available_sums_every_money_account(): void
    {
        $this->fund('1010', '100.00');
        $this->fund('1030', '400.00');

        $this->assertSame(0, bccomp($this->money()->totalAvailable(), '500.00', 2));
    }

    public function test_balances_do_not_leak_across_organisations(): void
    {
        $this->fund('1010', '900.00');

        $other = \App\Models\Organization::create([
            'slug' => 'other-' . uniqid(), 'name' => 'Other Farm', 'is_active' => true,
        ]);

        $this->assertSame(
            0,
            bccomp($this->money()->totalAvailable(null, $other), '0.00', 2),
            'Another organisation must not see this farm\'s cash.',
        );
    }
}
