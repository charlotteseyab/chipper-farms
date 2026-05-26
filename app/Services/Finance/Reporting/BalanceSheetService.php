<?php

namespace App\Services\Finance\Reporting;

use App\Models\Finance\Account;
use App\Services\Finance\Reporting\Concerns\HandlesReportingDecimals;
use App\Support\CurrentOrganization;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Balance Sheet (Statement of Financial Position) as at a date.
 *
 * Cumulative position (all posted activity up to as_of), grouped:
 *
 *   ASSETS
 *     Current Assets        (subtype current_asset)
 *     Non-Current Assets    (subtype non_current_asset)
 *   LIABILITIES
 *     Current Liabilities   (subtype current_liability)
 *     Non-Current Liab.     (subtype non_current_liability)
 *   EQUITY
 *     Contributed + reserves (equity accounts)
 *     + Retained earnings b/f (posted balance of RE account)
 *     + Current-period earnings (net profit not yet closed, i.e. the live
 *       P&L balance — revenue less expense — that hasn't been swept to RE)
 *
 * The fundamental check: Assets == Liabilities + Equity (including the
 * current-year earnings plug). Because every posted journal balances, this
 * MUST hold; the statement asserts it and exposes `balanced` so the close
 * checklist and tests can verify.
 *
 * Sign conventions (cumulative):
 *   asset (debit-normal):     balance = debits - credits
 *   liability (credit-normal): balance = credits - debits
 *   equity (credit-normal):    balance = credits - debits
 *   For the equity section we ADD live net profit (revenue - expense)
 *   because P&L accounts haven't been closed to equity yet intra-year.
 */
class BalanceSheetService
{
    use HandlesReportingDecimals;

    public function __construct(
        protected CurrentOrganization $currentOrganization,
        protected LedgerBalanceQuery $ledger,
    ) {
    }

    /**
     * @return array{
     *   assets: array{sections: array<string,mixed>, total: string},
     *   liabilities: array{sections: array<string,mixed>, total: string},
     *   equity: array{sections: array<string,mixed>, total: string, current_year_earnings: string},
     *   balanced: bool,
     *   variance: string,
     *   meta: array{as_of_date:string, organization_id:int|null}
     * }
     */
    public function generate(CarbonInterface $asOfDate): array
    {
        // Cumulative aggregation: from the beginning of time to as_of.
        $agg = $this->ledger->aggregate(null, $asOfDate);
        $accounts = $this->ledger->accounts();

        // --- Assets ---
        $assetCurrent    = $this->section($accounts, $agg, Account::TYPE_ASSET, ['current_asset'], debitNormal: true);
        $assetNonCurrent = $this->section($accounts, $agg, Account::TYPE_ASSET, ['non_current_asset'], debitNormal: true);
        $assetOther      = $this->sectionFallback($accounts, $agg, Account::TYPE_ASSET, ['current_asset', 'non_current_asset'], debitNormal: true);

        $assetTotal = $this->decAdd($this->decAdd($assetCurrent['subtotal'], $assetNonCurrent['subtotal']), $assetOther['subtotal']);

        // --- Liabilities ---
        $liabCurrent    = $this->section($accounts, $agg, Account::TYPE_LIABILITY, ['current_liability'], debitNormal: false);
        $liabNonCurrent = $this->section($accounts, $agg, Account::TYPE_LIABILITY, ['non_current_liability'], debitNormal: false);
        $liabOther      = $this->sectionFallback($accounts, $agg, Account::TYPE_LIABILITY, ['current_liability', 'non_current_liability'], debitNormal: false);

        $liabTotal = $this->decAdd($this->decAdd($liabCurrent['subtotal'], $liabNonCurrent['subtotal']), $liabOther['subtotal']);

        // --- Equity (posted equity accounts) ---
        $equityPosted = $this->section($accounts, $agg, Account::TYPE_EQUITY, [], debitNormal: false, matchAll: true);

        // --- Current-year earnings plug: live P&L not yet closed ---
        // revenue (credit-normal) - expense (debit-normal)
        $revenue = $this->typeTotal($accounts, $agg, Account::TYPE_REVENUE, creditNormal: true);
        $expense = $this->typeTotal($accounts, $agg, Account::TYPE_EXPENSE, creditNormal: false);
        $currentYearEarnings = $this->decSub($revenue, $expense);

        $equityTotal = $this->decAdd($equityPosted['subtotal'], $currentYearEarnings);

        // --- Balance check ---
        $rhs = $this->decAdd($liabTotal, $equityTotal);
        $variance = $this->decSub($assetTotal, $rhs);
        $balanced = $this->decComp($variance, '0') === 0;

        return [
            'assets' => [
                'sections' => [
                    'current'     => ['label' => 'Current Assets',      'lines' => $assetCurrent['lines'],    'subtotal' => $assetCurrent['subtotal']],
                    'non_current' => ['label' => 'Non-Current Assets',  'lines' => $assetNonCurrent['lines'], 'subtotal' => $assetNonCurrent['subtotal']],
                    'other'       => ['label' => 'Other Assets',        'lines' => $assetOther['lines'],      'subtotal' => $assetOther['subtotal']],
                ],
                'total' => $assetTotal,
            ],
            'liabilities' => [
                'sections' => [
                    'current'     => ['label' => 'Current Liabilities',     'lines' => $liabCurrent['lines'],    'subtotal' => $liabCurrent['subtotal']],
                    'non_current' => ['label' => 'Non-Current Liabilities', 'lines' => $liabNonCurrent['lines'], 'subtotal' => $liabNonCurrent['subtotal']],
                    'other'       => ['label' => 'Other Liabilities',       'lines' => $liabOther['lines'],      'subtotal' => $liabOther['subtotal']],
                ],
                'total' => $liabTotal,
            ],
            'equity' => [
                'sections' => [
                    'contributed' => ['label' => 'Equity', 'lines' => $equityPosted['lines'], 'subtotal' => $equityPosted['subtotal']],
                ],
                'current_year_earnings' => $currentYearEarnings,
                'total' => $equityTotal,
            ],
            'balanced' => $balanced,
            'variance' => $variance,
            'meta' => [
                'as_of_date'      => $asOfDate->toDateString(),
                'organization_id' => $this->currentOrganization->id(),
            ],
        ];
    }

    /**
     * Build a section for a type + subtype set.
     *
     * @param  Collection<int, Account>  $accounts
     * @param  Collection<int, object>   $agg
     * @param  array<int, string>        $subtypes
     * @return array{lines: array<int, array<string,mixed>>, subtotal: string}
     */
    protected function section(
        Collection $accounts,
        Collection $agg,
        string $type,
        array $subtypes,
        bool $debitNormal,
        bool $matchAll = false,
    ): array {
        $lines = [];
        $subtotal = '0';

        foreach ($accounts as $account) {
            if ($account->account_type !== $type) {
                continue;
            }
            if (! $matchAll && ! in_array($account->account_subtype, $subtypes, true)) {
                continue;
            }

            $balance = $this->accountBalance($agg, $account->id, $debitNormal);

            if ($this->decComp($balance, '0') === 0) {
                continue; // hide zero-balance lines
            }

            $lines[] = [
                'account_id' => $account->id,
                'code'       => $account->code,
                'name'       => $account->name,
                'amount'     => $balance,
            ];
            $subtotal = $this->decAdd($subtotal, $balance);
        }

        return ['lines' => $lines, 'subtotal' => $subtotal];
    }

    /**
     * Fallback section: accounts of TYPE whose subtype is none of the known
     * ones — so nothing posted is silently omitted from the balance sheet.
     *
     * @param  Collection<int, Account>  $accounts
     * @param  Collection<int, object>   $agg
     * @param  array<int, string>        $knownSubtypes
     * @return array{lines: array<int, array<string,mixed>>, subtotal: string}
     */
    protected function sectionFallback(
        Collection $accounts,
        Collection $agg,
        string $type,
        array $knownSubtypes,
        bool $debitNormal,
    ): array {
        $lines = [];
        $subtotal = '0';

        foreach ($accounts as $account) {
            if ($account->account_type !== $type) {
                continue;
            }
            if (in_array($account->account_subtype, $knownSubtypes, true)) {
                continue; // already handled by a named section
            }

            $balance = $this->accountBalance($agg, $account->id, $debitNormal);
            if ($this->decComp($balance, '0') === 0) {
                continue;
            }

            $lines[] = [
                'account_id' => $account->id,
                'code'       => $account->code,
                'name'       => $account->name,
                'amount'     => $balance,
            ];
            $subtotal = $this->decAdd($subtotal, $balance);
        }

        return ['lines' => $lines, 'subtotal' => $subtotal];
    }

    /**
     * Sum the net balance of all accounts of a type.
     *
     * @param  Collection<int, Account>  $accounts
     * @param  Collection<int, object>   $agg
     */
    protected function typeTotal(Collection $accounts, Collection $agg, string $type, bool $creditNormal): string
    {
        $total = '0';
        foreach ($accounts as $account) {
            if ($account->account_type !== $type) {
                continue;
            }
            $total = $this->decAdd($total, $this->accountBalance($agg, $account->id, ! $creditNormal));
        }
        return $total;
    }

    /**
     * Net balance of one account in the cumulative aggregation, in its
     * normal-balance sign.
     *
     * @param  Collection<int, object>  $agg
     */
    protected function accountBalance(Collection $agg, int $accountId, bool $debitNormal): string
    {
        $row = $agg->get($accountId);
        $debit  = $row ? (string) $row->total_debit  : '0';
        $credit = $row ? (string) $row->total_credit : '0';

        return $debitNormal
            ? $this->decSub($debit, $credit)
            : $this->decSub($credit, $debit);
    }
}
