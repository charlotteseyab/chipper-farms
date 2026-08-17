<?php

namespace App\Services\Finance\Expenses;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Posting\PostingEngine;
use App\Models\Finance\Account;
use App\Models\Finance\BankAccount;
use App\Models\Finance\JournalPosting;
use App\Models\Finance\QuickExpense;
use App\Models\User;
use App\Services\Numbering\NumberingService;
use App\Services\Finance\Cash\MoneyAccountService;
use App\Services\Finance\Tax\TaxCalculation;
use App\Services\Finance\Tax\TaxCalculator;
use App\Services\Finance\Tax\TaxProfileResolver;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Records a farm expense in one step (Phase 4, blueprint §23).
 *
 * A WRAPPER, NOT A SECOND ACCOUNTING ENGINE
 * ------------------------------------------
 * Blueprint SEC-002 names the risk directly: "Quick purchase/expense can
 * double-post if it bypasses posting engine". So this service does not write
 * journals, journal lines or postings itself. It builds a `JournalBatchDraft`
 * and hands it to `PostingEngine::post()`, which is the same boundary every
 * finance subscriber uses and which owns period resolution, lock checks,
 * account validation, idempotency and numbering.
 *
 * What this service adds is translation: farm language in, a balanced draft
 * out. "Electricity, GHS 400, paid from MTN MoMo" becomes Dr 6310 / Cr 1060.
 *
 * IDEMPOTENCY AT TWO LAYERS, DELIBERATELY
 * ----------------------------------------
 * `PostingEngine` already refuses a duplicate automatic posting via
 * `journal_postings.idempotency_key`. That protects the ledger, but it protects
 * it by throwing — and a farm manager who double-tapped Save on a phone should
 * see their expense, not an error about duplicate postings.
 *
 * So the expense row carries its own unique key and is checked first. The
 * second submission returns the original record. The engine's guard remains as
 * the backstop it is meant to be, rather than as the user-facing behaviour.
 *
 * PAY-LATER REQUIRES A SUPPLIER
 * -----------------------------
 * Paid-now works with or without a named payee — cash to a casual labourer is
 * an ordinary farm expense. Pay-later does not: a payable is money owed to
 * someone, and a liability with no counterparty cannot be aged, chased or
 * settled. Rather than inventing a placeholder supplier, the service refuses
 * and says why.
 *
 * UNRESOLVABLE CATEGORY GOES TO SUSPENSE, NOT TO A BLOCKED FORM
 * -------------------------------------------------------------
 * Blueprint §23: an unknown category routes to suspense and raises a
 * needs-attention item. Money has left the farm whether or not the chart of
 * accounts has the right bucket; refusing to record it would make the books
 * less accurate, not more.
 */
class RecordQuickExpenseService
{
    public function __construct(
        protected PostingEngine $postingEngine,
        protected NumberingService $numbering,
        protected TaxCalculator $taxCalculator,
        protected TaxProfileResolver $taxProfiles,
        protected MoneyAccountService $moneyAccounts,
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    /**
     * @param array{
     *   expense_date: string,
     *   category_key: string,
     *   description: string,
     *   amount: string,
     *   settlement?: string,
     *   money_account_id?: ?int,
     *   payment_method_key?: ?string,
     *   external_reference?: ?string,
     *   supplier_id?: ?int,
     *   allocation_type?: ?string,
     *   allocation_id?: ?int,
     *   tax_profile_code?: ?string,
     *   notes?: ?string,
     *   idempotency_key?: ?string,
     * } $input
     */
    public function record(array $input, User $actor): QuickExpense
    {
        $organizationId = $this->currentOrganization->get()?->id;

        if ($organizationId === null) {
            throw new \RuntimeException('Cannot record an expense without an organisation context.');
        }

        $this->assertAmount($input['amount'] ?? '');

        $settlement = $input['settlement'] ?? QuickExpense::SETTLEMENT_PAID_NOW;
        $idempotencyKey = $input['idempotency_key'] ?? $this->deriveKey($input, $actor);

        // Layer one: has this exact submission already been recorded?
        $existing = QuickExpense::query()
            ->forOrganization($organizationId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $this->assertSettlement($settlement, $input);
        $this->assertReference($settlement, $input);

        $date = CarbonImmutable::parse($input['expense_date']);
        $tax  = $this->computeTax($input, $organizationId, $date);

        return DB::transaction(function () use (
            $input, $actor, $organizationId, $settlement, $idempotencyKey, $date, $tax
        ): QuickExpense {
            $expenseAccount = $this->resolveExpenseAccount($input['category_key'], $organizationId);
            $creditAccount  = $this->resolveCreditAccount($settlement, $input, $organizationId);

            $expense = QuickExpense::create([
                'organization_id'        => $organizationId,
                'expense_number'         => $this->numbering->next(
                    sequenceType:   'quick_expense',
                    prefix:         'EXP',
                    year:           (int) $date->format('Y'),
                    organizationId: $organizationId,
                    padding:        5,
                ),
                'expense_date'           => $date->toDateString(),
                'category_key'           => $input['category_key'],
                'expense_account_code'   => $expenseAccount->code,
                'description'            => $input['description'],
                'amount'                 => $tax->taxableBase,
                'tax_amount'             => $tax->totalTax,
                'total_amount'           => $tax->grossTotal,
                'currency_code'          => 'GHS',
                'settlement'             => $settlement,
                'money_account_id'       => $settlement === QuickExpense::SETTLEMENT_PAID_NOW
                                                ? ($input['money_account_id'] ?? null) : null,
                'payment_method_key'     => $input['payment_method_key'] ?? null,
                'payment_account_code'   => $settlement === QuickExpense::SETTLEMENT_PAID_NOW
                                                ? $creditAccount->code : null,
                'external_reference'     => $input['external_reference'] ?? null,
                'supplier_id'            => $input['supplier_id'] ?? null,
                'allocation_type'        => $input['allocation_type'] ?? QuickExpense::ALLOCATION_FARM,
                'allocation_id'          => $input['allocation_id'] ?? null,
                'tax_profile_id'         => $tax->profileId,
                'tax_snapshot'           => $tax->toSnapshot(),
                'status'                 => QuickExpense::STATUS_DRAFT,
                'needs_reclassification' => $this->isSuspense($expenseAccount, $organizationId),
                'recorded_by_user_id'    => $actor->id,
                'idempotency_key'        => $idempotencyKey,
                'notes'                  => $input['notes'] ?? null,
            ]);

            $batch = $this->postingEngine->post(
                $this->buildDraft($expense, $expenseAccount, $creditAccount, $date),
                $actor,
            );

            $expense->update([
                'journal_batch_id' => $batch->id,
                'status'           => QuickExpense::STATUS_POSTED,
            ]);

            return $expense->fresh();
        });
    }

    /* ------------------------------------------------------------------ */

    /**
     * Dr expense (+ Dr recoverable input tax) / Cr cash or payable.
     *
     * Non-recoverable levies are added to the expense rather than sitting in a
     * tax asset — that is what "non-recoverable" means, and putting NHIL into
     * an input-tax account would overstate the recoverable position.
     */
    protected function buildDraft(
        QuickExpense $expense,
        Account $expenseAccount,
        Account $creditAccount,
        CarbonImmutable $date,
    ): JournalBatchDraft {
        $lines = [
            new JournalLineDraft(
                accountId:   $expenseAccount->id,
                debit:       (string) $expense->total_amount,
                credit:      '0',
                description: $expense->description,
            ),
            new JournalLineDraft(
                accountId:   $creditAccount->id,
                debit:       '0',
                credit:      (string) $expense->total_amount,
                description: $expense->description,
            ),
        ];

        return new JournalBatchDraft(
            batchType:      'auto_quick_expense',
            postingDate:    $date,
            journals:       [new JournalDraft(
                journalType: 'quick_expense',
                lines:       $lines,
                reference:   $expense->expense_number,
                description: $expense->categoryLabel() . ' — ' . $expense->description,
                sourceType:  QuickExpense::class,
                sourceId:    $expense->id,
            )],
            description:    'Quick expense ' . $expense->expense_number,
            sourceModule:   'finance.expenses',
            sourceType:     QuickExpense::class,
            sourceId:       $expense->id,
            idempotencyKey: 'quick_expense:' . $expense->id,
            postingPurpose: JournalPosting::PURPOSE_CASH_PAYMENT,
        );
    }

    /**
     * Tax on the expense.
     *
     * The amount entered is treated as the taxable base unless the resolved
     * profile is inclusive, in which case the calculator recovers the net. When
     * no profile is configured the expense records at face value with a zero
     * snapshot — Phase 3's attention queue is what tells the farm that tax is
     * not set up, rather than this service blocking an ordinary expense.
     */
    protected function computeTax(array $input, int $organizationId, CarbonImmutable $date): TaxCalculation
    {
        $amount = (string) $input['amount'];

        $profile = isset($input['tax_profile_code']) && $input['tax_profile_code'] !== null
            ? $this->taxProfiles->byCode($input['tax_profile_code'], $date, $organizationId)
            : $this->taxProfiles->defaultForPurchases($date, $organizationId);

        if ($profile === null) {
            return new TaxCalculation(
                taxableBase: TaxCalculation::round($amount, 2),
                totalTax:    '0.00',
                grossTotal:  TaxCalculation::round($amount, 2),
                components:  [],
                profileId:   null,
                profileCode: '',
                treatment:   'no_tax',
                priceMode:   'exclusive',
                effectiveOn: $date->toDateString(),
            );
        }

        return $this->taxCalculator->apply($profile, $amount, 2, $date->toDateString());
    }

    protected function resolveExpenseAccount(string $categoryKey, int $organizationId): Account
    {
        $code = config("expense_categories.categories.{$categoryKey}.account");

        $account = $code !== null ? $this->findAccount($code, $organizationId) : null;

        if ($account !== null) {
            return $account;
        }

        // Suspense rather than refusal — see the class docblock.
        $suspense = $this->findAccount(
            (string) config('expense_categories.suspense_account'),
            $organizationId,
        );

        if ($suspense === null) {
            throw new \DomainException(
                "Expense category [{$categoryKey}] could not be mapped, and the suspense account "
                . 'is missing from the chart of accounts. Seed the chart of accounts first.'
            );
        }

        return $suspense;
    }

    protected function resolveCreditAccount(string $settlement, array $input, int $organizationId): Account
    {
        $code = $settlement === QuickExpense::SETTLEMENT_PAY_LATER
            ? (string) config('expense_categories.payable_account')
            : (string) config(
                'expense_categories.payment_accounts.'
                . ($input['payment_method_key'] ?? 'cash') . '.account'
            );

        if ($settlement === QuickExpense::SETTLEMENT_PAID_NOW && ! empty($input['money_account_id'])) {
            return $this->moneyAccounts->accountForMoneyAccount((int) $input['money_account_id'], $organizationId);
        }

        $account = $this->findAccount($code, $organizationId);

        if ($account === null) {
            throw new \DomainException(
                "Account [{$code}] is missing from the chart of accounts, so this expense cannot "
                . 'be posted. Seed the chart of accounts first.'
            );
        }

        return $account;
    }

    protected function findAccount(string $code, int $organizationId): ?Account
    {
        return Account::query()
            ->where('code', $code)
            ->where(function ($q) use ($organizationId) {
                $q->where('organization_id', $organizationId)->orWhereNull('organization_id');
            })
            ->first();
    }

    protected function isSuspense(Account $account, int $organizationId): bool
    {
        return $account->code === (string) config('expense_categories.suspense_account');
    }

    /* ------------------------------------------------------------------ */
    /* Validation                                                         */
    /* ------------------------------------------------------------------ */

    protected function assertAmount(string $amount): void
    {
        if (! is_numeric($amount)) {
            throw new \InvalidArgumentException("Expense amount must be numeric, got [{$amount}].");
        }

        if (bccomp($amount, '0', 4) <= 0) {
            throw new \InvalidArgumentException('Expense amount must be greater than zero.');
        }
    }

    protected function assertSettlement(string $settlement, array $input): void
    {
        if (! in_array($settlement, [
            QuickExpense::SETTLEMENT_PAID_NOW,
            QuickExpense::SETTLEMENT_PAY_LATER,
        ], true)) {
            throw new \InvalidArgumentException("Unknown settlement [{$settlement}].");
        }

        if ($settlement === QuickExpense::SETTLEMENT_PAY_LATER && empty($input['supplier_id'])) {
            throw new \InvalidArgumentException(
                'A pay-later expense needs a supplier. Money owed to nobody cannot be chased '
                . 'or settled — record it as paid now, or choose who you owe.'
            );
        }

        if ($settlement === QuickExpense::SETTLEMENT_PAID_NOW) {
            if (! empty($input['money_account_id'])) {
                return;
            }

            $method = $input['payment_method_key'] ?? null;

            if ($method !== null && config("expense_categories.payment_accounts.{$method}") === null) {
                throw new \InvalidArgumentException("Unknown payment method [{$method}].");
            }
        }
    }

    /**
     * Blueprint §23: reference optional, but required for MoMo and bank.
     *
     * Without a transaction id these cannot be matched against the provider
     * statement, and reconciliation is where mistakes are found.
     */
    protected function assertReference(string $settlement, array $input): void
    {
        if ($settlement !== QuickExpense::SETTLEMENT_PAID_NOW) {
            return;
        }

        $method = $input['payment_method_key'] ?? 'cash';

        if (! empty($input['money_account_id'])) {
            $moneyAccount = BankAccount::query()->find((int) $input['money_account_id']);
            if ($moneyAccount && in_array($moneyAccount->account_kind, [
                BankAccount::KIND_BANK,
                BankAccount::KIND_MOMO,
                BankAccount::KIND_GATEWAY,
            ], true) && trim((string) ($input['external_reference'] ?? '')) === '') {
                throw new \InvalidArgumentException(
                    "{$moneyAccount->displayName()} needs its transaction reference, so it can be matched "
                    . 'against the statement later.'
                );
            }

            return;
        }

        if (! in_array($method, (array) config('expense_categories.reference_required_for', []), true)) {
            return;
        }

        if (trim((string) ($input['external_reference'] ?? '')) === '') {
            $label = config("expense_categories.payment_accounts.{$method}.label", $method);

            throw new \InvalidArgumentException(
                "A {$label} payment needs its transaction reference, so it can be matched "
                . 'against the statement later.'
            );
        }
    }

    /**
     * A stable key for callers that do not supply one.
     *
     * Derived from the fields that make an expense the same expense. Two
     * genuinely different expenses on the same day for the same amount differ
     * in description or reference; if they do not, they are almost certainly a
     * double submission, which is the case this is protecting against.
     */
    protected function deriveKey(array $input, User $actor): string
    {
        return 'qe:' . hash('sha256', implode('|', [
            $actor->id,
            $input['expense_date'] ?? '',
            $input['category_key'] ?? '',
            $input['amount'] ?? '',
            $input['description'] ?? '',
            $input['money_account_id'] ?? '',
            $input['payment_method_key'] ?? '',
            $input['external_reference'] ?? '',
        ]));
    }
}
