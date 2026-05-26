<?php

namespace App\Services\Finance\Close;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Posting\PostingEngine;
use App\Models\Finance\Account;
use App\Models\Finance\Journal;
use App\Models\Finance\JournalLine;
use App\Models\Finance\YearEndClosing;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Models\User;
use App\Services\Finance\Close\Exceptions\PeriodCloseException;
use App\Services\Finance\JournalReversalService;
use App\Services\Finance\Reporting\Concerns\HandlesReportingDecimals;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Year-end close: sweep all P&L accounts (revenue + expense) to zero,
 * routing the net result to Retained Earnings via the Current Year
 * Earnings clearing account.
 *
 * Per the seeder's design intent:
 *   3300 Current Year Earnings (Clearing) — closing entries land here
 *   3200 Retained Earnings                — period-end roll-forward sweeps here
 *
 * The closing journal (a single balanced batch dated on the fiscal year's
 * last day):
 *
 *   For each revenue account with a credit balance R:
 *     Dr Revenue account   R
 *   For each expense account with a debit balance E:
 *     Cr Expense account   E
 *   Then the net to retained earnings:
 *     net = totalRevenue - totalExpense
 *     if net > 0 (profit):   Cr 3200 Retained Earnings   net
 *     if net < 0 (loss):     Dr 3200 Retained Earnings   |net|
 *
 *   (We post directly to 3200; the 3300 clearing account is recorded on
 *   the year_end_closings row for traceability and is available as an
 *   intermediate if a two-step sweep is later required. Posting straight to
 *   3200 keeps the closing entry a single balanced batch.)
 *
 * All postings go through the PostingEngine so period locks, validation,
 * and audit all apply. Reversal (on year reopen) uses JournalReversalService.
 *
 * Pre-conditions:
 *   - All periods in the year should be closed (soft or hard). The service
 *     warns but does not hard-block if some are open, because the caller
 *     (controller) decides policy; however it WILL refuse if the year is
 *     already locked or already has a completed closing.
 */
class YearEndCloseService
{
    use HandlesReportingDecimals;

    protected const RETAINED_EARNINGS_CODE      = '3200';
    protected const CURRENT_YEAR_EARNINGS_CODE  = '3300';

    public function __construct(
        protected CurrentOrganization $currentOrganization,
        protected PostingEngine $postingEngine,
        protected JournalReversalService $reversalService,
    ) {
    }

    /**
     * @throws PeriodCloseException
     */
    public function close(FiscalYear $year, User $actor, ?string $notes = null): YearEndClosing
    {
        if ($year->isLocked()) {
            throw new PeriodCloseException("Fiscal year #{$year->id} is locked; cannot run year-end close.");
        }

        $existing = YearEndClosing::query()
            ->where('fiscal_year_id', $year->id)
            ->where('status', YearEndClosing::STATUS_COMPLETED)
            ->first();
        if ($existing) {
            throw new PeriodCloseException("Fiscal year #{$year->id} already has a completed year-end close (#{$existing->id}).");
        }

        $organizationId = $this->currentOrganization->id();

        $retainedEarnings = $this->requireAccount(self::RETAINED_EARNINGS_CODE);
        $clearing         = $this->requireAccount(self::CURRENT_YEAR_EARNINGS_CODE);

        // The closing date is the year's last day.
        $closingDate = CarbonImmutable::parse($year->end_date->toDateString());

        // Aggregate P&L balances over the whole year window.
        $yearStart = CarbonImmutable::parse($year->start_date->toDateString());

        $plBalances = $this->plAccountBalances($yearStart, $closingDate);

        if (empty($plBalances['lines'])) {
            throw new PeriodCloseException(
                "Fiscal year #{$year->id} has no P&L activity to close."
            );
        }

        $totalRevenue = $plBalances['total_revenue'];
        $totalExpense = $plBalances['total_expense'];
        $netProfit    = $this->decSub($totalRevenue, $totalExpense);

        return DB::transaction(function () use (
            $year, $actor, $notes, $organizationId, $retainedEarnings, $clearing,
            $closingDate, $plBalances, $totalRevenue, $totalExpense, $netProfit
        ) {
            // Create the in-progress record first.
            $closing = YearEndClosing::create([
                'organization_id'                 => $organizationId,
                'fiscal_year_id'                  => $year->id,
                'status'                          => YearEndClosing::STATUS_IN_PROGRESS,
                'total_revenue'                   => $totalRevenue,
                'total_expense'                   => $totalExpense,
                'net_profit'                      => $netProfit,
                'retained_earnings_account_id'    => $retainedEarnings->id,
                'current_year_earnings_account_id'=> $clearing->id,
                'performed_by_user_id'            => $actor->id,
                'notes'                           => $notes,
            ]);

            // Build the closing journal lines.
            $lines = [];

            foreach ($plBalances['lines'] as $line) {
                // Revenue (credit-normal, positive balance): debit it to zero.
                // Expense (debit-normal, positive balance): credit it to zero.
                if ($line['kind'] === 'revenue') {
                    $lines[] = new JournalLineDraft(
                        accountId:   $line['account_id'],
                        debit:       $line['balance'],
                        credit:      '0',
                        description: 'Year-end close: clear revenue',
                    );
                } else {
                    $lines[] = new JournalLineDraft(
                        accountId:   $line['account_id'],
                        debit:       '0',
                        credit:      $line['balance'],
                        description: 'Year-end close: clear expense',
                    );
                }
            }

            // Balancing line to Retained Earnings.
            $netComp = $this->decComp($netProfit, '0');
            if ($netComp > 0) {
                // Profit: credit RE.
                $lines[] = new JournalLineDraft(
                    accountId:   $retainedEarnings->id,
                    debit:       '0',
                    credit:      $netProfit,
                    description: 'Year-end close: net profit to Retained Earnings',
                );
            } elseif ($netComp < 0) {
                // Loss: debit RE.
                $lines[] = new JournalLineDraft(
                    accountId:   $retainedEarnings->id,
                    debit:       $this->decAbs($netProfit),
                    credit:      '0',
                    description: 'Year-end close: net loss to Retained Earnings',
                );
            }
            // net == 0: no RE line; the revenue/expense lines already balance.

            $journalDraft = new JournalDraft(
                journalType: 'closing',
                lines:       $lines,
                reference:   'YEC-' . $year->id,
                description: "Year-end closing entry for fiscal year {$year->id}",
            );

            $batchDraft = new JournalBatchDraft(
                batchType:      'auto_year_end_close',
                postingDate:    $closingDate,
                journals:       [$journalDraft],
                description:    "Year-end close FY{$year->id}",
                sourceModule:   'finance.close',
                sourceType:     'year_end_closing',
                sourceId:       $closing->id,
                idempotencyKey: 'year_end_close:' . ($organizationId ?? 'null') . ':' . $year->id,
                postingPurpose: 'year_end_close',
            );

            $batch = $this->postingEngine->post($batchDraft, $actor);

            $closing->update([
                'status'                   => YearEndClosing::STATUS_COMPLETED,
                'closing_journal_batch_id' => $batch->id,
                'completed_at'             => now(),
            ]);

            // Mark the year closed.
            $year->update(['status' => FiscalYear::STATUS_CLOSED]);

            return $closing->fresh();
        });
    }

    /**
     * Reverse a completed year-end close (on year reopen).
     *
     * @throws PeriodCloseException
     */
    public function reverse(FiscalYear $year, User $actor, string $reason): YearEndClosing
    {
        if (trim($reason) === '') {
            throw new PeriodCloseException('A reason is required to reverse a year-end close.');
        }

        $closing = YearEndClosing::query()
            ->where('fiscal_year_id', $year->id)
            ->where('status', YearEndClosing::STATUS_COMPLETED)
            ->first();

        if (! $closing) {
            throw new PeriodCloseException("Fiscal year #{$year->id} has no completed year-end close to reverse.");
        }

        if (! $closing->closing_journal_batch_id) {
            throw new PeriodCloseException("Year-end closing #{$closing->id} has no linked journal batch.");
        }

        return DB::transaction(function () use ($year, $actor, $reason, $closing) {
            $batch = $closing->closingBatch;
            $reversalBatch = $this->reversalService->reverse($batch, $actor, $reason);

            $closing->update([
                'status'                    => YearEndClosing::STATUS_REVERSED,
                'reversal_journal_batch_id' => $reversalBatch->id,
                'reversed_at'               => now(),
                'reversed_by_user_id'       => $actor->id,
                'reversal_reason'           => $reason,
            ]);

            $year->update(['status' => FiscalYear::STATUS_OPEN]);

            return $closing->fresh();
        });
    }

    // ----------------------------------------------------------------

    /**
     * Posted P&L account balances over the year window.
     *
     * @return array{lines: array<int, array{account_id:int, code:string, kind:string, balance:string}>, total_revenue:string, total_expense:string}
     */
    protected function plAccountBalances(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $organizationId = $this->currentOrganization->id();

        $accounts = Account::query()
            ->when(
                $organizationId !== null,
                fn ($q) => $q->where('organization_id', $organizationId),
                fn ($q) => $q->whereNull('organization_id'),
            )
            ->whereIn('account_type', [Account::TYPE_REVENUE, Account::TYPE_EXPENSE])
            ->orderBy('code')
            ->get();

        $agg = JournalLine::query()
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
            ->whereDate('journals.posting_date', '>=', $from->toDateString())
            ->whereDate('journals.posting_date', '<=', $to->toDateString())
            ->groupBy('journal_lines.account_id')
            ->get()
            ->keyBy('account_id');

        $lines = [];
        $totalRevenue = '0';
        $totalExpense = '0';

        foreach ($accounts as $account) {
            $row = $agg->get($account->id);
            if ($row === null) {
                continue;
            }
            $debit  = (string) $row->total_debit;
            $credit = (string) $row->total_credit;

            if ($account->account_type === Account::TYPE_REVENUE) {
                $balance = $this->decSub($credit, $debit); // credit-normal
                if ($this->decComp($balance, '0') === 0) {
                    continue;
                }
                $lines[] = ['account_id' => $account->id, 'code' => $account->code, 'kind' => 'revenue', 'balance' => $this->decAbs($balance)];
                $totalRevenue = $this->decAdd($totalRevenue, $balance);
            } else {
                $balance = $this->decSub($debit, $credit); // debit-normal
                if ($this->decComp($balance, '0') === 0) {
                    continue;
                }
                $lines[] = ['account_id' => $account->id, 'code' => $account->code, 'kind' => 'expense', 'balance' => $this->decAbs($balance)];
                $totalExpense = $this->decAdd($totalExpense, $balance);
            }
        }

        return [
            'lines'         => $lines,
            'total_revenue' => $totalRevenue,
            'total_expense' => $totalExpense,
        ];
    }

    protected function requireAccount(string $code): Account
    {
        $organizationId = $this->currentOrganization->id();
        $account = Account::query()
            ->when(
                $organizationId !== null,
                fn ($q) => $q->where('organization_id', $organizationId),
                fn ($q) => $q->whereNull('organization_id'),
            )
            ->where('code', $code)
            ->first();

        if (! $account) {
            throw new PeriodCloseException("Required account '{$code}' is missing from the chart of accounts.");
        }
        return $account;
    }
}
