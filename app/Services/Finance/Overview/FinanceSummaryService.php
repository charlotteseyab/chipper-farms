<?php

namespace App\Services\Finance\Overview;

use App\Models\Finance\Account;
use App\Models\Finance\Journal;
use App\Models\Finance\JournalLine;
use App\Models\Organization;
use App\Services\Finance\Cash\MoneyAccountService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The money figures on the Overview (Phase 7, blueprint §22).
 *
 * Deferred from Phase 3 on purpose: each figure is owned by a later phase's data
 * — cash by the Cash workspace, spend by Quick Expense and Simple Purchase,
 * receivables by sales. Building them before those flows existed would have
 * produced numbers that were correct that week and wrong the next.
 *
 * EVERY FIGURE IS DERIVED FROM POSTED JOURNAL LINES
 * -------------------------------------------------
 * Same rule as `MoneyAccountService`, for the same reason. There is no summary
 * table, no nightly rollup and no cached total. A dashboard figure that can
 * disagree with the ledger is worse than no figure, because the person reading
 * it has no way to tell which one is right — and dashboards are precisely where
 * people stop checking.
 *
 * The cost is a handful of grouped queries per page load. That is the correct
 * trade at farm scale, and if it ever stops being so, the fix is a materialised
 * view over the same rows rather than a second set of numbers maintained by
 * hand.
 *
 * SIGN CONVENTIONS ARE APPLIED HERE, NOT LEFT TO THE VIEW
 * -------------------------------------------------------
 * Revenue and liabilities are credit-balance accounts; assets and expenses are
 * debit-balance. Returning raw debit-minus-credit would make "sales this month"
 * negative and invite a `abs()` in a Blade template, which would then quietly
 * hide a genuinely negative figure. Each accessor returns the number a farmer
 * would say out loud.
 */
class FinanceSummaryService
{
    /** Accounts receivable control range. */
    private const AR_PREFIX = '11';

    /** Accounts payable control range. */
    private const AP_PREFIX = '20';

    /** Revenue range. */
    private const REVENUE_PREFIX = '4';

    public function __construct(
        protected MoneyAccountService $moneyAccounts,
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    /**
     * @return array{
     *   cash_available:string, sales_this_month:string, expenses_this_month:string,
     *   net_this_month:string, customers_owing:string, bills_to_pay:string,
     *   month_label:string
     * }
     */
    public function cards(
        \DateTimeInterface|string|null $asOf = null,
        Organization|int|null $organization = null,
    ): array {
        $organizationId = $this->resolveOrganizationId($organization);
        $now            = $this->normalise($asOf);

        if ($organizationId === null) {
            return $this->empty($now);
        }

        $monthStart = $now->startOfMonth();

        $sales    = $this->creditBalance(self::REVENUE_PREFIX, $organizationId, $monthStart, $now);
        $expenses = $this->debitBalance('5', $organizationId, $monthStart, $now);
        $expenses = bcadd($expenses, $this->debitBalance('6', $organizationId, $monthStart, $now), 2);

        return [
            'cash_available'      => $this->moneyAccounts->totalAvailable($now, $organizationId),
            'sales_this_month'    => $sales,
            'expenses_this_month' => $expenses,
            'net_this_month'      => bcsub($sales, $expenses, 2),
            'customers_owing'     => $this->debitBalance(self::AR_PREFIX, $organizationId, null, $now),
            'bills_to_pay'        => $this->creditBalance(self::AP_PREFIX, $organizationId, null, $now),
            'month_label'         => $now->format('F Y'),
        ];
    }

    /**
     * Net debit balance across a code range — the natural direction for assets
     * and expenses.
     */
    protected function debitBalance(
        string $codePrefix,
        int $organizationId,
        ?CarbonImmutable $from,
        CarbonImmutable $to,
    ): string {
        $row = $this->aggregate($codePrefix, $organizationId, $from, $to);

        return bcsub((string) $row->total_debit, (string) $row->total_credit, 2);
    }

    /**
     * Net credit balance — the natural direction for revenue and liabilities.
     */
    protected function creditBalance(
        string $codePrefix,
        int $organizationId,
        ?CarbonImmutable $from,
        CarbonImmutable $to,
    ): string {
        $row = $this->aggregate($codePrefix, $organizationId, $from, $to);

        return bcsub((string) $row->total_credit, (string) $row->total_debit, 2);
    }

    /**
     * Posted debits and credits for accounts whose code starts with a prefix.
     *
     * Matching by code prefix rather than by `account_subtype` is deliberate and
     * was learned the hard way in Phase 6b: every inventory control account in
     * this chart is subtype `current_asset`, so subtype filters silently match
     * the wrong set — or nothing at all, which looks like a clean result.
     */
    protected function aggregate(
        string $codePrefix,
        int $organizationId,
        ?CarbonImmutable $from,
        CarbonImmutable $to,
    ): object {
        $accountIds = Account::query()
            ->where('code', 'like', $codePrefix . '%')
            ->where(function ($q) use ($organizationId) {
                $q->where('organization_id', $organizationId)->orWhereNull('organization_id');
            })
            ->pluck('id');

        if ($accountIds->isEmpty()) {
            return (object) ['total_debit' => '0', 'total_credit' => '0'];
        }

        $row = JournalLine::query()
            ->selectRaw('COALESCE(SUM(journal_lines.debit), 0) as total_debit')
            ->selectRaw('COALESCE(SUM(journal_lines.credit), 0) as total_credit')
            ->join('journals', 'journal_lines.journal_id', '=', 'journals.id')
            ->where('journals.status', Journal::STATUS_POSTED)
            ->where('journal_lines.organization_id', $organizationId)
            ->whereIn('journal_lines.account_id', $accountIds)
            ->when($from !== null, fn ($q) => $q->whereDate('journals.posting_date', '>=', $from->toDateString()))
            ->whereDate('journals.posting_date', '<=', $to->toDateString())
            ->first();

        return $row ?? (object) ['total_debit' => '0', 'total_credit' => '0'];
    }

    /** @return array<string, string> */
    protected function empty(CarbonImmutable $now): array
    {
        return [
            'cash_available'      => '0.00',
            'sales_this_month'    => '0.00',
            'expenses_this_month' => '0.00',
            'net_this_month'      => '0.00',
            'customers_owing'     => '0.00',
            'bills_to_pay'        => '0.00',
            'month_label'         => $now->format('F Y'),
        ];
    }

    protected function normalise(\DateTimeInterface|string|null $asOf): CarbonImmutable
    {
        if ($asOf === null) {
            return CarbonImmutable::now();
        }

        return $asOf instanceof \DateTimeInterface
            ? CarbonImmutable::instance($asOf)
            : CarbonImmutable::parse($asOf);
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
