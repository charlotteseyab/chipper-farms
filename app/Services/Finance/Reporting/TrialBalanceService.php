<?php

namespace App\Services\Finance\Reporting;

use App\Models\Finance\Account;
use App\Models\Finance\Journal;
use App\Models\Finance\JournalLine;
use App\Support\CurrentOrganization;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Phase 1d — Trial Balance.
 *
 * Computes the sum of debits and credits per account over a date range.
 * The result is the canonical "is the GL balanced" check: total debits
 * across all accounts MUST equal total credits. If they don't, something
 * was inserted in violation of every safeguard.
 *
 * Inputs:
 *   - asOfDate (inclusive, end-of-day)
 *   - fromDate (inclusive, start-of-day) — null means "since the beginning"
 *
 * For Phase 1d this is a flat (non-hierarchical) view. Phase 7 will add
 * the rolled-up hierarchical version for balance sheet / P&L statements.
 *
 * Posted journals and their reversed originals are included. Drafts and
 * rejected drafts are excluded; each reversal offsets its original on
 * its own posting date.
 *
 * Returns a collection of stdClass with:
 *   account_id
 *   code
 *   name
 *   account_type
 *   account_subtype
 *   total_debit         (sum of debits in the range)
 *   total_credit        (sum of credits in the range)
 *   net                 (debit-normal: total_debit - total_credit;
 *                        credit-normal: total_credit - total_debit)
 *
 * Plus the totals struct.
 *
 * Performance: single SQL query with GROUP BY. JOIN onto chart_of_accounts
 * for the metadata. Indexed on (organization_id, account_id) — Phase 1a
 * built this index.
 */
class TrialBalanceService
{
    public function __construct(protected CurrentOrganization $currentOrganization)
    {
    }

    /**
     * @return array{
     *   rows: Collection,
     *   totals: array{debit: string, credit: string, balanced: bool},
     *   meta:   array{from_date: string|null, as_of_date: string, organization_id: int|null}
     * }
     */
    public function generate(?Carbon $fromDate, Carbon $asOfDate): array
    {
        $organizationId = $this->currentOrganization->id();

        $query = JournalLine::query()
            ->select([
                'journal_lines.account_id',
                DB::raw('SUM(journal_lines.debit) as total_debit'),
                DB::raw('SUM(journal_lines.credit) as total_credit'),
            ])
            ->join('journals', 'journal_lines.journal_id', '=', 'journals.id')
            ->whereIn('journals.id', Journal::query()->affectingLedger()->select('journals.id'))
            ->when(
                $organizationId !== null,
                fn ($q) => $q->where('journal_lines.organization_id', $organizationId),
                fn ($q) => $q->whereNull('journal_lines.organization_id'),
            )
            ->whereDate('journals.posting_date', '<=', $asOfDate->toDateString())
            ->groupBy('journal_lines.account_id');

        if ($fromDate !== null) {
            $query->whereDate('journals.posting_date', '>=', $fromDate->toDateString());
        }

        $aggregates = $query->get()->keyBy('account_id');

        // Pull all accounts (including ones with no activity) so the
        // trial balance can show zero-balance accounts if the caller wants.
        $accounts = Account::query()
            ->when(
                $organizationId !== null,
                fn ($q) => $q->where('organization_id', $organizationId),
                fn ($q) => $q->whereNull('organization_id'),
            )
            ->orderBy('code')
            ->get();

        $rows = $accounts
            ->map(function (Account $account) use ($aggregates) {
                $agg = $aggregates->get($account->id);
                $debit  = $agg ? (string) $agg->total_debit  : '0.00';
                $credit = $agg ? (string) $agg->total_credit : '0.00';

                $isDebitNormal = $account->isDebitNormal();
                $net = $isDebitNormal
                    ? $this->decSub($debit, $credit, 2)
                    : $this->decSub($credit, $debit, 2);

                return (object) [
                    'account_id'      => $account->id,
                    'code'            => $account->code,
                    'name'            => $account->name,
                    'account_type'    => $account->account_type,
                    'account_subtype' => $account->account_subtype,
                    'is_debit_normal' => $isDebitNormal,
                    'total_debit'     => $debit,
                    'total_credit'    => $credit,
                    'net'             => $net,
                    'has_activity'    => $agg !== null,
                ];
            });

        // Aggregate totals across all accounts.
        $totalDebit  = $rows->reduce(fn ($carry, $r) => $this->decAdd((string) $carry, (string) $r->total_debit, 2), '0');
        $totalCredit = $rows->reduce(fn ($carry, $r) => $this->decAdd((string) $carry, (string) $r->total_credit, 2), '0');

        return [
            'rows'   => $rows,
            'totals' => [
                'debit'    => $totalDebit,
                'credit'   => $totalCredit,
                'balanced' => $this->decComp($totalDebit, $totalCredit, 2) === 0,
            ],
            'meta' => [
                'from_date'       => $fromDate?->toDateString(),
                'as_of_date'      => $asOfDate->toDateString(),
                'organization_id' => $organizationId,
            ],
        ];
    }

    private function decAdd(string $left, string $right, int $scale = 2): string
    {
        if (function_exists('bcadd')) {
            return bcadd($left, $right, $scale);
        }

        return number_format((float) $left + (float) $right, $scale, '.', '');
    }

    private function decSub(string $left, string $right, int $scale = 2): string
    {
        if (function_exists('bcsub')) {
            return bcsub($left, $right, $scale);
        }

        return number_format((float) $left - (float) $right, $scale, '.', '');
    }

    private function decComp(string $left, string $right, int $scale = 2): int
    {
        if (function_exists('bccomp')) {
            return bccomp($left, $right, $scale);
        }

        $a = round((float) $left, $scale);
        $b = round((float) $right, $scale);

        return $a <=> $b;
    }
}
