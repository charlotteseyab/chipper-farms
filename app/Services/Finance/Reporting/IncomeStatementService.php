<?php

namespace App\Services\Finance\Reporting;

use App\Models\Finance\Account;
use App\Services\Finance\Reporting\Concerns\HandlesReportingDecimals;
use App\Support\CurrentOrganization;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Income Statement (Profit & Loss) for a date range.
 *
 * Structure (Ghana SME / IFRS-for-SMEs friendly):
 *
 *   Revenue                         (account_type = revenue, subtype = revenue)
 *   - Cost of Sales                 (expense, subtype = cogs)
 *   = Gross Profit
 *   - Operating Expenses            (expense, subtype = opex)
 *   = Operating Profit
 *   + Other Income                  (revenue, subtype = other_income)
 *   - Finance Costs                 (expense, subtype = finance_cost)
 *   - Other Expenses                (expense, subtype = other_expense)
 *   = Net Profit
 *
 * Revenue is credit-normal: period revenue = credits - debits.
 * Expense is debit-normal:  period expense = debits - credits.
 *
 * Optionally computes a comparison column for a prior window so the
 * statement can show period-over-period movement.
 */
class IncomeStatementService
{
    use HandlesReportingDecimals;

    public function __construct(
        protected CurrentOrganization $currentOrganization,
        protected LedgerBalanceQuery $ledger,
    ) {
    }

    /**
     * @return array{
     *   sections: array<string, array{label:string, lines: array<int, array<string,mixed>>, subtotal:string}>,
     *   gross_profit: string,
     *   operating_profit: string,
     *   net_profit: string,
     *   totals: array{revenue:string, cost_of_sales:string, opex:string, other_income:string, finance_cost:string, other_expense:string},
     *   comparison: array<string,string>|null,
     *   meta: array{from_date:string, to_date:string, organization_id:int|null}
     * }
     */
    public function generate(
        CarbonInterface $fromDate,
        CarbonInterface $toDate,
        ?CarbonInterface $compareFromDate = null,
        ?CarbonInterface $compareToDate = null,
    ): array {
        $result = $this->computeWindow($fromDate, $toDate);

        $comparison = null;
        if ($compareFromDate !== null && $compareToDate !== null) {
            $prior = $this->computeWindow($compareFromDate, $compareToDate);
            $comparison = [
                'revenue'          => $prior['totals']['revenue'],
                'cost_of_sales'    => $prior['totals']['cost_of_sales'],
                'gross_profit'     => $prior['gross_profit'],
                'opex'             => $prior['totals']['opex'],
                'operating_profit' => $prior['operating_profit'],
                'other_income'     => $prior['totals']['other_income'],
                'finance_cost'     => $prior['totals']['finance_cost'],
                'other_expense'    => $prior['totals']['other_expense'],
                'net_profit'       => $prior['net_profit'],
                'from_date'        => $compareFromDate->toDateString(),
                'to_date'          => $compareToDate->toDateString(),
            ];
        }

        $result['comparison'] = $comparison;
        $result['meta'] = [
            'from_date'       => $fromDate->toDateString(),
            'to_date'         => $toDate->toDateString(),
            'organization_id' => $this->currentOrganization->id(),
        ];

        return $result;
    }

    /**
     * Compute the statement for a single window.
     *
     * @return array<string, mixed>
     */
    protected function computeWindow(CarbonInterface $fromDate, CarbonInterface $toDate): array
    {
        $agg = $this->ledger->aggregate($fromDate, $toDate);
        $accounts = $this->ledger->accounts([Account::TYPE_REVENUE, Account::TYPE_EXPENSE]);

        // Bucket definitions: section key => [account_type, subtype(s), credit_normal?]
        $buckets = [
            'revenue'       => ['label' => 'Revenue',            'type' => Account::TYPE_REVENUE, 'subtypes' => ['revenue'],            'credit_normal' => true],
            'cost_of_sales' => ['label' => 'Cost of Sales',      'type' => Account::TYPE_EXPENSE, 'subtypes' => ['cogs'],               'credit_normal' => false],
            'opex'          => ['label' => 'Operating Expenses', 'type' => Account::TYPE_EXPENSE, 'subtypes' => ['opex'],               'credit_normal' => false],
            'other_income'  => ['label' => 'Other Income',       'type' => Account::TYPE_REVENUE, 'subtypes' => ['other_income'],       'credit_normal' => true],
            'finance_cost'  => ['label' => 'Finance Costs',      'type' => Account::TYPE_EXPENSE, 'subtypes' => ['finance_cost'],       'credit_normal' => false],
            'other_expense' => ['label' => 'Other Expenses',     'type' => Account::TYPE_EXPENSE, 'subtypes' => ['other_expense'],      'credit_normal' => false],
        ];

        $sections = [];
        $totals = [];

        foreach ($buckets as $key => $def) {
            [$lines, $subtotal] = $this->buildSection($accounts, $agg, $def);
            $sections[$key] = [
                'label'    => $def['label'],
                'lines'    => $lines,
                'subtotal' => $subtotal,
            ];
            $totals[$key] = $subtotal;
        }

        // Revenue bucket: anything revenue-typed NOT already in other_income.
        // The subtype filter handles this as long as the COA tags subtypes;
        // accounts with a null/unknown subtype fall into their type's
        // primary bucket (revenue -> revenue, expense -> opex) so nothing
        // is silently dropped. See classifyFallback().

        $grossProfit     = $this->decSub($totals['revenue'], $totals['cost_of_sales']);
        $operatingProfit = $this->decSub($grossProfit, $totals['opex']);
        $netProfit = $this->decSub(
            $this->decSub(
                $this->decAdd($operatingProfit, $totals['other_income']),
                $totals['finance_cost'],
            ),
            $totals['other_expense'],
        );

        return [
            'sections'         => $sections,
            'gross_profit'     => $grossProfit,
            'operating_profit' => $operatingProfit,
            'net_profit'       => $netProfit,
            'totals'           => $totals,
        ];
    }

    /**
     * Build one section's lines + subtotal.
     *
     * @param  Collection<int, Account>  $accounts
     * @param  Collection<int, object>   $agg
     * @param  array<string, mixed>      $def
     * @return array{0: array<int, array<string,mixed>>, 1: string}
     */
    protected function buildSection(Collection $accounts, Collection $agg, array $def): array
    {
        $lines = [];
        $subtotal = '0';

        foreach ($accounts as $account) {
            if (! $this->accountInBucket($account, $def)) {
                continue;
            }

            $row = $agg->get($account->id);
            $debit  = $row ? (string) $row->total_debit  : '0';
            $credit = $row ? (string) $row->total_credit : '0';

            $amount = $def['credit_normal']
                ? $this->decSub($credit, $debit)   // revenue: credits positive
                : $this->decSub($debit, $credit);  // expense: debits positive

            // Skip zero-activity leaf accounts to keep the statement tight,
            // but always include accounts that DID have activity.
            if ($row === null && $this->decComp($amount, '0') === 0) {
                continue;
            }

            $lines[] = [
                'account_id'   => $account->id,
                'code'         => $account->code,
                'name'         => $account->name,
                'amount'       => $amount,
            ];
            $subtotal = $this->decAdd($subtotal, $amount);
        }

        return [$lines, $subtotal];
    }

    /**
     * Does this account belong in the bucket?
     *
     * Primary match is (type, subtype). Fallback: an account of the right
     * TYPE but with a subtype that doesn't match ANY defined bucket lands
     * in its type's primary bucket (revenue->revenue, expense->opex) so the
     * P&L never silently omits a posted account.
     *
     * @param  array<string, mixed>  $def
     */
    protected function accountInBucket(Account $account, array $def): bool
    {
        if ($account->account_type !== $def['type']) {
            return false;
        }

        $subtype = $account->account_subtype;

        if (in_array($subtype, $def['subtypes'], true)) {
            return true;
        }

        // Fallback routing for untagged/unknown subtypes.
        $isPrimaryBucket = ($def['type'] === Account::TYPE_REVENUE && in_array('revenue', $def['subtypes'], true))
            || ($def['type'] === Account::TYPE_EXPENSE && in_array('opex', $def['subtypes'], true));

        if (! $isPrimaryBucket) {
            return false;
        }

        $knownSubtypes = ['revenue', 'cogs', 'opex', 'other_income', 'finance_cost', 'other_expense'];
        return ! in_array($subtype, $knownSubtypes, true); // null or unknown -> primary bucket
    }
}
