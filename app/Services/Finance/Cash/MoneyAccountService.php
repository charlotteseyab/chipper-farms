<?php

namespace App\Services\Finance\Cash;

use App\Models\Finance\Account;
use App\Models\Finance\BankAccount;
use App\Models\Organization;
use App\Models\PaymentMethod;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;

/**
 * The farm's money accounts and what is in them (Phase 5, blueprint §25, §39).
 *
 * BALANCES COME FROM THE LEDGER, NOT FROM A SECOND STORE
 * ------------------------------------------------------
 * Prompt §38 is explicit: "Do not build a separate balance source." So this
 * service holds no balance column of its own and computes nothing incrementally.
 * It sums posted debits and credits per account straight from `journal_lines`
 * and subtracts — the same rows the trial balance reads.
 *
 * That matters more than it sounds. A cached or incrementally-maintained cash
 * balance is a number that can disagree with the ledger, and when it does, the
 * two are equally plausible to whoever is looking. Deriving it every time means
 * the cash screen and the trial balance cannot drift apart, because there is
 * only one set of figures.
 *
 * MOBILE MONEY PROVIDERS STAY SEPARATE
 * ------------------------------------
 * MTN, AT and Telecel are distinct accounts, not one "mobile money" bucket.
 * Blueprint §25 calls this out: settlement timing and reference formats differ
 * per provider, so a merged balance cannot be reconciled against any one
 * statement.
 *
 * ARITHMETIC IS DECIMAL STRINGS THROUGHOUT
 * ----------------------------------------
 * Balances are money.
 */
class MoneyAccountService
{
    public function __construct(
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    /**
     * Money accounts with balances, in the order a treasurer thinks about them.
     *
     * @return list<array{
     *   id:int, code:string, name:string, label:string, kind:string,
     *   balance:string, is_negative:bool
     * }>
     */
    public function accounts(
        \DateTimeInterface|string|null $asOf = null,
        Organization|int|null $organization = null,
    ): array {
        $organizationId = $this->resolveOrganizationId($organization);

        if ($organizationId === null) {
            return [];
        }

        $moneyAccounts = $this->moneyAccountRows($organizationId);
        if ($moneyAccounts->isNotEmpty()) {
            $balances = $this->balances($asOf, $organizationId);

            return $moneyAccounts
                ->filter(fn (BankAccount $moneyAccount): bool => $moneyAccount->account !== null)
                ->map(function (BankAccount $moneyAccount) use ($balances): array {
                    $account = $moneyAccount->account;
                    $balance = $balances[$account->id] ?? '0.00';

                    return [
                        'id'               => (int) $account->id,
                        'money_account_id' => (int) $moneyAccount->id,
                        'code'             => (string) $account->code,
                        'name'             => (string) $account->name,
                        'label'            => $moneyAccount->displayName(),
                        'kind'             => (string) ($moneyAccount->account_kind ?: BankAccount::KIND_BANK),
                        'balance'          => $balance,
                        'is_negative'      => bccomp($balance, '0', 2) < 0,
                    ];
                })
                ->values()
                ->all();
        }

        $codes = $this->configuredCodes();
        $accounts = $this->resolveAccounts(array_keys($codes), $organizationId);
        $balances = $this->balances($asOf, $organizationId);

        $out = [];
        foreach ($codes as $code => $meta) {
            $account = $accounts[$code] ?? null;

            if ($account === null) {
                continue;
            }

            $balance = $balances[$account->id] ?? '0.00';

            $out[] = [
                'id'          => (int) $account->id,
                'code'        => (string) $account->code,
                'name'        => (string) $account->name,
                'label'       => $meta['label'],
                'kind'        => $meta['kind'],
                'balance'     => $balance,
                // A negative cash balance is not merely unusual — it means the
                // farm has recorded paying out more than it ever had, which is
                // a data problem worth flagging on the screen.
                'is_negative' => bccomp($balance, '0', 2) < 0,
            ];
        }

        return $out;
    }

    /**
     * Total money available, as a decimal string.
     *
     * This is the "Cash available" figure blueprint §22 asks the Overview for.
     */
    public function totalAvailable(
        \DateTimeInterface|string|null $asOf = null,
        Organization|int|null $organization = null,
    ): string {
        $total = '0.00';

        foreach ($this->accounts($asOf, $organization) as $account) {
            $total = bcadd($total, $account['balance'], 2);
        }

        return $total;
    }

    /**
     * Accounts whose balance has gone negative.
     *
     * @return list<array<string, mixed>>
     */
    public function overdrawn(
        \DateTimeInterface|string|null $asOf = null,
        Organization|int|null $organization = null,
    ): array {
        return array_values(array_filter(
            $this->accounts($asOf, $organization),
            static fn (array $a): bool => $a['is_negative'],
        ));
    }

    /**
     * Is this account one the farm may move money between?
     *
     * Used by the transfer service. Restricting transfers to configured money
     * accounts is what stops the workflow becoming a general-purpose journal
     * tool that happens to have a friendly form.
     */
    public function isMoneyAccount(Account $account): bool
    {
        $organizationId = $account->organization_id ?? $this->currentOrganization->get()?->id;

        if ($organizationId !== null && $this->moneyAccountRows((int) $organizationId)->isNotEmpty()) {
            return BankAccount::query()
                ->where('organization_id', $organizationId)
                ->where('account_id', $account->id)
                ->where('is_active', true)
                ->exists();
        }

        return array_key_exists((string) $account->code, $this->configuredCodes());
    }

    /**
     * Active practical money accounts for forms.
     *
     * @return \Illuminate\Support\Collection<int, BankAccount>
     */
    public function selectable(Organization|int|null $organization = null)
    {
        $organizationId = $this->resolveOrganizationId($organization);

        if ($organizationId === null) {
            return collect();
        }

        return $this->moneyAccountRows($organizationId);
    }

    public function sourceAccountIdForMoneyAccount(int $moneyAccountId, Organization|int|null $organization = null): int
    {
        $organizationId = $this->resolveOrganizationId($organization);

        $moneyAccount = BankAccount::query()
            ->with('account')
            ->where('id', $moneyAccountId)
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->where('is_active', true)
            ->first();

        if (! $moneyAccount || ! $moneyAccount->account) {
            throw new \InvalidArgumentException('Choose an active money account before posting this transaction.');
        }

        return (int) $moneyAccount->account->id;
    }

    public function accountForMoneyAccount(int $moneyAccountId, Organization|int|null $organization = null): Account
    {
        $accountId = $this->sourceAccountIdForMoneyAccount($moneyAccountId, $organization);

        return Account::query()->findOrFail($accountId);
    }

    public function paymentMethodForMoneyAccount(int $moneyAccountId, Organization|int|null $organization = null): PaymentMethod
    {
        $organizationId = $this->resolveOrganizationId($organization);

        $moneyAccount = BankAccount::query()
            ->where('id', $moneyAccountId)
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->first();

        if (! $moneyAccount) {
            throw new \InvalidArgumentException('Choose an active money account before posting this transaction.');
        }

        $code = $this->paymentMethodCodeFor($moneyAccount);

        $method = PaymentMethod::query()
            ->where(function ($q) use ($organizationId) {
                $q->where('organization_id', $organizationId)->orWhereNull('organization_id');
            })
            ->where('code', $code)
            ->where('is_active', true)
            ->orderByRaw('organization_id is null')
            ->first();

        if ($method === null) {
            throw new \DomainException("Payment method [{$code}] is not configured.");
        }

        return $method;
    }

    /**
     * One account per code, preferring the organisation's own over a global
     * template of the same code.
     *
     * A plain `keyBy('code')` is wrong here and was a real defect: this chart of
     * accounts can hold both a global account and an organisation-scoped account
     * with the same code, and `keyBy` silently keeps whichever the database
     * happened to return last. The cash screen then reported a balance of zero
     * while the ledger had posted to the other row — the two disagreed, and
     * both looked plausible.
     *
     * @param list<string> $codes
     * @return array<string, Account> keyed by code
     */
    protected function resolveAccounts(array $codes, int $organizationId): array
    {
        $rows = Account::query()
            ->whereIn('code', $codes)
            ->where(function ($q) use ($organizationId) {
                $q->where('organization_id', $organizationId)->orWhereNull('organization_id');
            })
            ->get();

        $out = [];
        foreach ($rows as $account) {
            $code = (string) $account->code;

            // Organisation-scoped always wins; a global row is only a fallback.
            if (! isset($out[$code]) || $account->organization_id !== null) {
                $out[$code] = $account;
            }
        }

        return $out;
    }

    /**
     * Posted balances per account, for an explicit organisation.
     *
     * Queries the ledger directly rather than through `LedgerBalanceQuery`,
     * which resolves the tenant from ambient request context. That is correct
     * for a report rendered for the signed-in user, but wrong here: this service
     * accepts an organisation argument, and silently ignoring it would let one
     * farm's balances be reported against another farm's accounts.
     *
     * It is still the same single source — journal lines on posted journals —
     * so the cash screen cannot drift from the trial balance.
     *
     * @return array<int, string> account_id => balance
     */
    protected function balances(\DateTimeInterface|string|null $asOf, int $organizationId): array
    {
        $to = $asOf === null
            ? CarbonImmutable::now()
            : CarbonImmutable::parse(is_string($asOf) ? $asOf : $asOf->format('Y-m-d'));

        $rows = \App\Models\Finance\JournalLine::query()
            ->select([
                'journal_lines.account_id',
                \Illuminate\Support\Facades\DB::raw('SUM(journal_lines.debit) as total_debit'),
                \Illuminate\Support\Facades\DB::raw('SUM(journal_lines.credit) as total_credit'),
            ])
            ->join('journals', 'journal_lines.journal_id', '=', 'journals.id')
            ->where('journals.status', \App\Models\Finance\Journal::STATUS_POSTED)
            ->where('journal_lines.organization_id', $organizationId)
            ->whereDate('journals.posting_date', '<=', $to->toDateString())
            ->groupBy('journal_lines.account_id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            // Money accounts are assets: debit increases, credit decreases.
            $out[(int) $row->account_id] = bcsub(
                (string) $row->total_debit,
                (string) $row->total_credit,
                2,
            );
        }

        return $out;
    }

    /**
     * Money account codes, from the same config Quick Expense pays out of.
     *
     * Sharing the config is deliberate: if an expense can be paid from an
     * account, that account must appear on the cash screen, or the money leaves
     * somewhere the treasurer cannot see.
     *
     * @return array<string, array{label:string, kind:string}>
     */
    protected function configuredCodes(): array
    {
        $out = [];

        foreach ((array) config('expense_categories.payment_accounts', []) as $key => $definition) {
            $out[(string) $definition['account']] = [
                'label' => $definition['label'],
                'kind'  => str_starts_with($key, 'momo_') ? 'momo'
                    : ($key === 'bank' ? 'bank' : 'cash'),
            ];
        }

        return $out;
    }

    protected function moneyAccountRows(int $organizationId)
    {
        return BankAccount::query()
            ->with('account')
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->orderBy('account_kind')
            ->orderBy('label')
            ->get();
    }

    protected function paymentMethodCodeFor(BankAccount $moneyAccount): string
    {
        if ($moneyAccount->account_kind === BankAccount::KIND_CASH) {
            return 'CASH';
        }

        if ($moneyAccount->account_kind === BankAccount::KIND_BANK) {
            return 'BANK_TRANSFER';
        }

        if ($moneyAccount->account_kind === BankAccount::KIND_GATEWAY) {
            return strtoupper((string) $moneyAccount->provider) === 'PAYSTACK'
                ? 'PAYSTACK'
                : 'POS';
        }

        $provider = strtolower((string) ($moneyAccount->provider ?: $moneyAccount->bank_name));

        return match (true) {
            str_contains($provider, 'mtn') => 'MOMO_MTN',
            str_contains($provider, 'airtel') || str_contains($provider, 'tigo') || str_contains($provider, 'at') => 'MOMO_ATGO',
            str_contains($provider, 'telecel') || str_contains($provider, 'voda') => 'MOMO_VODA',
            default => 'MOMO_MTN',
        };
    }

    protected function resolveOrganizationId(Organization|int|null $organization): ?int
    {
        if ($organization instanceof Organization) {
            return (int) $organization->id;
        }

        if (is_int($organization)) {
            return $organization;
        }

        return $this->currentOrganization->get()?->id;
    }
}
