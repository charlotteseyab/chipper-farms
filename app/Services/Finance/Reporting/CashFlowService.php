<?php

namespace App\Services\Finance\Reporting;

use App\Models\Finance\Account;
use App\Services\Finance\Reporting\Concerns\HandlesReportingDecimals;
use App\Support\CurrentOrganization;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Cash Flow Statement — INDIRECT method.
 *
 * The indirect method derives operating cash flow by starting from net
 * profit and reversing out non-cash and non-operating movements, then
 * adding the net change in working capital. We avoid the fragile
 * "classify every transaction" requirement of the direct method.
 *
 * Our pragmatic, GL-driven indirect model:
 *
 *   1. Net profit for the period (from the income statement).
 *   2. + Adjustments for non-cash P&L:
 *        - Depreciation (expense accounts whose name/subtype marks them as
 *          depreciation — heuristic: code prefix 65 or name contains
 *          "Depreciation"). Added back.
 *        - Mortality / inventory write-offs are NON-cash reductions of
 *          biological/inventory assets — but in our ledger they reduce an
 *          asset and hit P&L, so they're already captured by the working
 *          capital movement on those asset accounts. We do NOT double-add.
 *   3. +/- Working capital movements (change in non-cash current assets &
 *        current liabilities over the period):
 *        - Increase in a current ASSET (e.g. receivables, inventory) uses
 *          cash  (negative).
 *        - Increase in a current LIABILITY (e.g. payables) frees cash
 *          (positive).
 *   = Net cash from operating activities.
 *
 *   Investing activities: change in non-current assets (PP&E, etc.).
 *   Financing activities: change in equity (excl. current-year earnings)
 *        and change in loans / non-current liabilities.
 *
 * The reconciling check: the sum of the three activity sections must equal
 * the actual movement in cash & bank accounts over the period. The service
 * computes both and exposes `reconciled` + `variance`.
 *
 * Cash & bank accounts are identified as the descendants of the COA parent
 * "1000 Cash and Bank" (cash on hand, petty cash, banks, mobile money).
 */
class CashFlowService
{
    use HandlesReportingDecimals;

    /** COA parent code whose children are the cash & bank accounts. */
    protected const CASH_PARENT_CODE = '1000';

    public function __construct(
        protected CurrentOrganization $currentOrganization,
        protected LedgerBalanceQuery $ledger,
        protected IncomeStatementService $incomeStatement,
    ) {
    }

    /**
     * @return array{
     *   net_profit: string,
     *   operating: array{adjustments: array<int,array<string,string>>, working_capital: array<int,array<string,string>>, subtotal: string},
     *   investing: array{lines: array<int,array<string,string>>, subtotal: string},
     *   financing: array{lines: array<int,array<string,string>>, subtotal: string},
     *   net_change_in_cash: string,
     *   actual_cash_movement: string,
     *   opening_cash: string,
     *   closing_cash: string,
     *   reconciled: bool,
     *   variance: string,
     *   meta: array{from_date:string, to_date:string, organization_id:int|null}
     * }
     */
    public function generate(CarbonInterface $fromDate, CarbonInterface $toDate): array
    {
        $accounts = $this->ledger->accounts();
        $accountsById = $accounts->keyBy('id');

        // Period movement per account (the flow during the window).
        $periodAgg = $this->ledger->aggregate($fromDate, $toDate);

        // Cumulative balances at opening (day before from) and closing (to)
        // — used for the actual cash movement reconciliation.
        $openingAgg = $this->ledger->aggregate(null, (clone $fromDate)->subDay());
        $closingAgg = $this->ledger->aggregate(null, $toDate);

        $cashAccountIds = $this->cashAccountIds($accounts);

        // 1. Net profit for the period.
        $pl = $this->incomeStatement->generate($fromDate, $toDate);
        $netProfit = $pl['net_profit'];

        // 2. Non-cash add-backs (depreciation).
        $adjustments = [];
        $depreciation = '0';
        foreach ($accounts as $account) {
            if ($account->account_type !== Account::TYPE_EXPENSE) {
                continue;
            }
            if (! $this->isDepreciation($account)) {
                continue;
            }
            $movement = $this->periodMovement($periodAgg, $account->id, debitNormal: true);
            if ($this->decComp($movement, '0') !== 0) {
                $depreciation = $this->decAdd($depreciation, $movement);
            }
        }
        if ($this->decComp($depreciation, '0') !== 0) {
            $adjustments[] = ['label' => 'Depreciation', 'amount' => $depreciation];
        }

        // 3. Working capital: non-cash current assets & current liabilities.
        $workingCapital = [];
        $wcSubtotal = '0';
        foreach ($accounts as $account) {
            $isCurrentAsset = $account->account_type === Account::TYPE_ASSET
                && $account->account_subtype === 'current_asset'
                && ! in_array($account->id, $cashAccountIds, true);
            $isCurrentLiab = $account->account_type === Account::TYPE_LIABILITY
                && $account->account_subtype === 'current_liability';

            if (! $isCurrentAsset && ! $isCurrentLiab) {
                continue;
            }

            // Change in balance over the period.
            $delta = $this->balanceDelta($openingAgg, $closingAgg, $account, debitNormal: $isCurrentAsset);
            if ($this->decComp($delta, '0') === 0) {
                continue;
            }

            // Increase in current asset => cash OUT (negative).
            // Increase in current liability => cash IN (positive).
            $cashEffect = $isCurrentAsset ? $this->decSub('0', $delta) : $delta;

            $workingCapital[] = [
                'account_id' => $account->id,
                'code'       => $account->code,
                'name'       => $account->name,
                'amount'     => $cashEffect,
            ];
            $wcSubtotal = $this->decAdd($wcSubtotal, $cashEffect);
        }

        $adjSubtotal = $depreciation;
        $operatingSubtotal = $this->decAdd($this->decAdd($netProfit, $adjSubtotal), $wcSubtotal);

        // Investing: change in non-current assets.
        $investingLines = [];
        $investingSubtotal = '0';
        foreach ($accounts as $account) {
            if ($account->account_type !== Account::TYPE_ASSET || $account->account_subtype !== 'non_current_asset') {
                continue;
            }
            $delta = $this->balanceDelta($openingAgg, $closingAgg, $account, debitNormal: true);
            if ($this->decComp($delta, '0') === 0) {
                continue;
            }
            // Increase in a non-current asset => cash OUT.
            $cashEffect = $this->decSub('0', $delta);
            $investingLines[] = [
                'account_id' => $account->id,
                'code'       => $account->code,
                'name'       => $account->name,
                'amount'     => $cashEffect,
            ];
            $investingSubtotal = $this->decAdd($investingSubtotal, $cashEffect);
        }

        // Financing: change in equity (excl. live earnings) + non-current liabilities.
        $financingLines = [];
        $financingSubtotal = '0';
        foreach ($accounts as $account) {
            $isEquity = $account->account_type === Account::TYPE_EQUITY;
            $isNonCurrentLiab = $account->account_type === Account::TYPE_LIABILITY
                && $account->account_subtype === 'non_current_liability';

            if (! $isEquity && ! $isNonCurrentLiab) {
                continue;
            }

            // Equity & liabilities are credit-normal; an increase frees cash.
            $delta = $this->balanceDelta($openingAgg, $closingAgg, $account, debitNormal: false);
            if ($this->decComp($delta, '0') === 0) {
                continue;
            }
            $financingLines[] = [
                'account_id' => $account->id,
                'code'       => $account->code,
                'name'       => $account->name,
                'amount'     => $delta,
            ];
            $financingSubtotal = $this->decAdd($financingSubtotal, $delta);
        }

        $netChange = $this->decAdd($this->decAdd($operatingSubtotal, $investingSubtotal), $financingSubtotal);

        // Actual cash movement (reconciliation target).
        $openingCash = '0';
        $closingCash = '0';
        foreach ($cashAccountIds as $cashId) {
            $account = $accountsById->get($cashId);
            if (! $account) {
                continue;
            }
            $openingCash = $this->decAdd($openingCash, $this->cumulativeBalance($openingAgg, $cashId, debitNormal: true));
            $closingCash = $this->decAdd($closingCash, $this->cumulativeBalance($closingAgg, $cashId, debitNormal: true));
        }
        $actualCashMovement = $this->decSub($closingCash, $openingCash);

        $variance = $this->decSub($netChange, $actualCashMovement);
        $reconciled = $this->decComp($variance, '0') === 0;

        return [
            'net_profit' => $netProfit,
            'operating'  => [
                'adjustments'     => $adjustments,
                'working_capital' => $workingCapital,
                'subtotal'        => $operatingSubtotal,
            ],
            'investing'  => ['lines' => $investingLines, 'subtotal' => $investingSubtotal],
            'financing'  => ['lines' => $financingLines, 'subtotal' => $financingSubtotal],
            'net_change_in_cash'   => $netChange,
            'actual_cash_movement' => $actualCashMovement,
            'opening_cash'         => $openingCash,
            'closing_cash'         => $closingCash,
            'reconciled'           => $reconciled,
            'variance'             => $variance,
            'meta' => [
                'from_date'       => $fromDate->toDateString(),
                'to_date'         => $toDate->toDateString(),
                'organization_id' => $this->currentOrganization->id(),
            ],
        ];
    }

    /**
     * Cash & bank account ids: children of the CASH_PARENT_CODE account,
     * plus the parent itself if it somehow carries postings.
     *
     * @param  Collection<int, Account>  $accounts
     * @return array<int, int>
     */
    protected function cashAccountIds(Collection $accounts): array
    {
        $parent = $accounts->first(fn (Account $a) => $a->code === self::CASH_PARENT_CODE);
        if (! $parent) {
            return [];
        }

        $ids = [$parent->id];
        foreach ($accounts as $account) {
            if ($account->parent_id === $parent->id) {
                $ids[] = $account->id;
            }
        }
        return $ids;
    }

    /**
     * Heuristic: is this a depreciation expense account?
     */
    protected function isDepreciation(Account $account): bool
    {
        if (str_starts_with((string) $account->code, '65')) {
            return true;
        }
        return stripos((string) $account->name, 'depreciation') !== false;
    }

    /**
     * Net movement of one account over the period (the flow).
     *
     * @param  Collection<int, object>  $agg
     */
    protected function periodMovement(Collection $agg, int $accountId, bool $debitNormal): string
    {
        $row = $agg->get($accountId);
        $debit  = $row ? (string) $row->total_debit  : '0';
        $credit = $row ? (string) $row->total_credit : '0';
        return $debitNormal ? $this->decSub($debit, $credit) : $this->decSub($credit, $debit);
    }

    /**
     * Change in an account's cumulative balance between opening and closing.
     *
     * @param  Collection<int, object>  $openingAgg
     * @param  Collection<int, object>  $closingAgg
     */
    protected function balanceDelta(Collection $openingAgg, Collection $closingAgg, Account $account, bool $debitNormal): string
    {
        $open  = $this->cumulativeBalance($openingAgg, $account->id, $debitNormal);
        $close = $this->cumulativeBalance($closingAgg, $account->id, $debitNormal);
        return $this->decSub($close, $open);
    }

    /**
     * Cumulative balance of one account in its normal-balance sign.
     *
     * @param  Collection<int, object>  $agg
     */
    protected function cumulativeBalance(Collection $agg, int $accountId, bool $debitNormal): string
    {
        $row = $agg->get($accountId);
        $debit  = $row ? (string) $row->total_debit  : '0';
        $credit = $row ? (string) $row->total_credit : '0';
        return $debitNormal ? $this->decSub($debit, $credit) : $this->decSub($credit, $debit);
    }
}
