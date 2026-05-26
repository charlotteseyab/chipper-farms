<?php

namespace App\Services\Finance\Treasury;

use App\Models\Finance\Account;
use App\Models\Finance\BankAccount;
use App\Models\Finance\Journal;
use App\Models\Finance\JournalLine;
use App\Models\Finance\Loan;
use App\Models\Finance\LoanScheduleLine;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Read-only treasury cash-position view.
 *
 *   - Cash & bank GL balances (children of 1000 Cash and Bank) as at a date.
 *   - Upcoming loan obligations: the next N unpaid schedule instalments
 *     across all active loans, so the operator sees what's due.
 *
 * No posting. Aggregates posted journals for cash balances and the loan
 * schedule for obligations.
 */
class TreasuryService
{
    protected const CASH_PARENT_CODE = '1000';

    public function __construct(protected CurrentOrganization $currentOrganization)
    {
    }

    /**
     * @return array{
     *   as_of:string,
     *   cash_accounts: array<int, array<string, mixed>>,
     *   total_cash: string,
     *   upcoming: array<int, array<string, mixed>>,
     *   upcoming_total: string
     * }
     */
    public function position(?CarbonInterface $asOf = null, int $upcomingLimit = 10): array
    {
        $asOf = $asOf ? CarbonImmutable::parse($asOf->toDateString()) : CarbonImmutable::now();
        $organizationId = $this->currentOrganization->id();

        $accounts = Account::query()
            ->when($organizationId !== null,
                fn ($q) => $q->where('organization_id', $organizationId),
                fn ($q) => $q->whereNull('organization_id'))
            ->get();

        $parent = $accounts->firstWhere('code', self::CASH_PARENT_CODE);
        $cashAccounts = [];
        $totalCash = '0';

        if ($parent) {
            $cashIds = $accounts->where('parent_id', $parent->id)->pluck('id')->push($parent->id)->all();

            $balances = JournalLine::query()
                ->join('journals', 'journal_lines.journal_id', '=', 'journals.id')
                ->where('journals.status', Journal::STATUS_POSTED)
                ->whereIn('journal_lines.account_id', $cashIds)
                ->when($organizationId !== null,
                    fn ($q) => $q->where('journal_lines.organization_id', $organizationId),
                    fn ($q) => $q->whereNull('journal_lines.organization_id'))
                ->whereDate('journals.posting_date', '<=', $asOf->toDateString())
                ->groupBy('journal_lines.account_id')
                ->selectRaw('journal_lines.account_id, COALESCE(SUM(journal_lines.debit),0) d, COALESCE(SUM(journal_lines.credit),0) c')
                ->get()
                ->keyBy('account_id');

            foreach ($accounts->where('parent_id', $parent->id) as $acct) {
                $row = $balances->get($acct->id);
                $bal = $row ? bcsub((string) $row->d, (string) $row->c, 2) : '0.00';
                if (bccomp($bal, '0', 2) === 0) {
                    continue;
                }
                $cashAccounts[] = [
                    'code'    => $acct->code,
                    'name'    => $acct->name,
                    'balance' => $bal,
                ];
                $totalCash = bcadd($totalCash, $bal, 2);
            }
        }

        // Upcoming loan obligations.
        $upcoming = [];
        $upcomingTotal = '0';

        $activeLoanIds = Loan::query()
            ->when($organizationId !== null,
                fn ($q) => $q->where('organization_id', $organizationId),
                fn ($q) => $q->whereNull('organization_id'))
            ->where('status', Loan::STATUS_ACTIVE)
            ->pluck('id');

        if ($activeLoanIds->isNotEmpty()) {
            $lines = LoanScheduleLine::query()
                ->whereIn('loan_id', $activeLoanIds)
                ->where('status', '!=', LoanScheduleLine::STATUS_PAID)
                ->whereDate('due_date', '>=', $asOf->toDateString())
                ->with('loan')
                ->orderBy('due_date')
                ->limit($upcomingLimit)
                ->get();

            foreach ($lines as $line) {
                $upcoming[] = [
                    'loan_reference' => $line->loan?->reference,
                    'period_number'  => $line->period_number,
                    'due_date'       => $line->due_date->toDateString(),
                    'total_due'      => (string) $line->total_due,
                    'principal_due'  => (string) $line->principal_due,
                    'interest_due'   => (string) $line->interest_due,
                ];
                $upcomingTotal = bcadd($upcomingTotal, (string) $line->total_due, 2);
            }
        }

        return [
            'as_of'          => $asOf->toDateString(),
            'cash_accounts'  => $cashAccounts,
            'total_cash'     => $totalCash,
            'upcoming'       => $upcoming,
            'upcoming_total' => $upcomingTotal,
        ];
    }
}
