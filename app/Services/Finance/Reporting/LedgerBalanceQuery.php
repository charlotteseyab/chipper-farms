<?php

namespace App\Services\Finance\Reporting;

use App\Models\Finance\Account;
use App\Models\Finance\Journal;
use App\Models\Finance\JournalLine;
use App\Support\CurrentOrganization;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Shared posted-ledger aggregation used by every financial statement.
 *
 * Returns, per account, the SUM of debits and credits from POSTED journals
 * within a date window. Statements layer their own presentation (grouping,
 * sign conventions, sub-totals) on top of this.
 *
 * Two windows matter:
 *   - Period activity (from..to): used by the income statement.
 *   - Cumulative to-date (..as_of): used by the balance sheet (a balance
 *     sheet is the cumulative position, not a period flow).
 *
 * The aggregation is intentionally a single grouped query (no N+1). The
 * caller decides which accounts to include.
 */
class LedgerBalanceQuery
{
    public function __construct(protected CurrentOrganization $currentOrganization)
    {
    }

    /**
     * Aggregate posted debits/credits per account in a date window.
     *
     * @return Collection<int, object{account_id:int, total_debit:string, total_credit:string}>
     *         keyed by account_id.
     */
    public function aggregate(?CarbonInterface $fromDate, CarbonInterface $toDate): Collection
    {
        $organizationId = $this->currentOrganization->id();

        $query = JournalLine::query()
            ->select([
                'journal_lines.account_id',
                DB::raw('SUM(journal_lines.debit) as total_debit'),
                DB::raw('SUM(journal_lines.credit) as total_credit'),
            ])
            ->join('journals', 'journal_lines.journal_id', '=', 'journals.id')
            ->where('journals.status', Journal::STATUS_POSTED)
            ->when(
                $organizationId !== null,
                fn ($q) => $q->where('journal_lines.organization_id', $organizationId),
                fn ($q) => $q->whereNull('journal_lines.organization_id'),
            )
            ->whereDate('journals.posting_date', '<=', $toDate->toDateString())
            ->groupBy('journal_lines.account_id');

        if ($fromDate !== null) {
            $query->whereDate('journals.posting_date', '>=', $fromDate->toDateString());
        }

        return $query->get()->keyBy('account_id');
    }

    /**
     * Load accounts for the current organization, optionally restricted to
     * a set of account types, ordered by code.
     *
     * @param  array<int, string>|null  $types
     * @return Collection<int, Account>
     */
    public function accounts(?array $types = null): Collection
    {
        $organizationId = $this->currentOrganization->id();

        return Account::query()
            ->when(
                $organizationId !== null,
                fn ($q) => $q->where('organization_id', $organizationId),
                fn ($q) => $q->whereNull('organization_id'),
            )
            ->when($types !== null, fn ($q) => $q->whereIn('account_type', $types))
            ->orderBy('code')
            ->get();
    }
}
