<?php

namespace App\Services\Finance\Investors;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Posting\PostingEngine;
use App\Finance\Resolvers\FinanceAccountResolver;
use App\Models\Finance\Investor;
use App\Models\Finance\InvestorProfitAllocationRun;
use App\Models\Finance\InvestorTransaction;
use App\Models\FiscalYear;
use App\Models\User;
use App\Services\Finance\Investors\Concerns\HandlesInvestorDecimals;
use App\Services\Finance\Investors\Exceptions\InvestorException;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Allocates a profit pool to equity investors and posts the result.
 *
 * ACCOUNTING MODEL (locked):
 *   Allocation posts Dr 3200 Retained Earnings / Cr 3100 Investor Capital
 *   (per investor). This MOVES profit already swept into retained earnings by
 *   the Phase 6 year-end close into investor capital — it does NOT create new
 *   equity, so total equity is unchanged; it is reapportioned.
 *
 * OPERATIONAL RULE (guarded + documented):
 *   Run allocation AFTER the year-end close has swept the year's profit into
 *   retained earnings. The caller supplies the profit_pool to allocate
 *   (typically the year's net profit, or a board-declared portion of it).
 *   We do not read net profit implicitly, to keep the allocation an explicit,
 *   auditable, board-style decision rather than a hidden side effect of close.
 *
 * METHODS:
 *   ownership_pct   each active equity investor gets profit_pool ×
 *                   ownership_percentage. The sum of ownership_percentage
 *                   across active equity investors should be <= 1; any
 *                   unallocated remainder stays in retained earnings.
 *   fixed_schedule  caller passes [investor_id => amount]; those exact
 *                   amounts are allocated.
 *
 * Idempotency / double-allocation guard: one COMPLETED run per fiscal year
 * unless a prior run was reversed. Each per-investor posting is idempotent via
 * the posting engine key (run id + investor id).
 *
 * @phpstan-type FixedSchedule array<int, string>
 */
class InvestorProfitAllocationService
{
    use HandlesInvestorDecimals;

    public function __construct(
        protected CurrentOrganization $currentOrganization,
        protected PostingEngine $postingEngine,
        protected FinanceAccountResolver $accounts,
    ) {
    }

    /**
     * Allocate a profit pool for a fiscal year.
     *
     * @param  array<int, string>  $fixedSchedule  investor_id => amount (fixed_schedule only)
     */
    public function allocate(
        FiscalYear $year,
        string $profitPool,
        User $actor,
        ?CarbonImmutable $date = null,
        ?string $method = null,
        array $fixedSchedule = [],
        ?string $notes = null,
    ): InvestorProfitAllocationRun {
        $method ??= (string) config('investors.allocation.method', InvestorProfitAllocationRun::METHOD_OWNERSHIP_PCT);
        $profitPool = $this->n($profitPool);
        $date ??= CarbonImmutable::parse($year->end_date->toDateString());
        $organizationId = $this->currentOrganization->id();

        $this->guardNoCompletedRun($year);

        // Build the per-investor allocation list.
        $allocations = $method === InvestorProfitAllocationRun::METHOD_FIXED_SCHEDULE
            ? $this->buildFixedAllocations($fixedSchedule)
            : $this->buildOwnershipAllocations($profitPool);

        if (empty($allocations)) {
            throw new InvestorException('No investor allocations to post (no active equity investors or zero amounts).');
        }

        $totalAllocated = array_reduce($allocations, fn (string $c, array $a) => $this->add($c, $a['amount']), '0');
        if ($this->cmp($totalAllocated, '0') <= 0) {
            throw new InvestorException('Total allocation is zero.');
        }

        $cfg = (array) config('investors.accounts');
        $debitAcctCode = (string) config('investors.allocation.post_debit_account', $cfg['retained_earnings']);
        $debitAcct = $this->accounts->byCode($debitAcctCode)->id;
        $capitalControl = $this->accounts->byCode($cfg['capital_control'])->id;

        return DB::transaction(function () use (
            $year, $actor, $date, $method, $profitPool, $allocations, $totalAllocated,
            $organizationId, $debitAcct, $capitalControl, $notes
        ) {
            $run = InvestorProfitAllocationRun::create([
                'organization_id'      => $organizationId,
                'fiscal_year_id'       => $year->id,
                'method'               => $method,
                'profit_pool'          => $totalAllocated,
                'allocation_date'      => $date->toDateString(),
                'status'               => InvestorProfitAllocationRun::STATUS_COMPLETED,
                'performed_by_user_id' => $actor->id,
                'notes'                => $notes,
            ]);

            // One balanced batch: Dr retained earnings (total) / Cr 3100 per investor.
            $lines = [
                JournalLineDraft::debit($debitAcct, $totalAllocated, "Investor profit allocation FY{$year->id}"),
            ];
            foreach ($allocations as $alloc) {
                $lines[] = JournalLineDraft::credit(
                    $capitalControl,
                    $alloc['amount'],
                    "Profit allocation — {$alloc['investor']->name}",
                    ['source_type' => Investor::class, 'source_id' => $alloc['investor']->id],
                );
            }

            $batch = $this->postingEngine->post(new JournalBatchDraft(
                batchType:      'auto_investor_allocation',
                postingDate:    $date,
                journals:       [new JournalDraft(journalType: 'investor_profit_allocation', lines: $lines)],
                description:    "Investor profit allocation FY{$year->id}",
                sourceModule:   'finance.investors',
                sourceType:     'investor_allocation_run',
                sourceId:       $run->id,
                idempotencyKey: sprintf('investor_allocation:%s:%d', $organizationId ?? 'null', $run->id),
                postingPurpose: 'investor_profit_allocation',
            ), $actor);

            foreach ($allocations as $alloc) {
                $investor = $alloc['investor'];
                $account = $investor->capitalAccount()->first();

                InvestorTransaction::create([
                    'organization_id'    => $organizationId,
                    'investor_id'        => $investor->id,
                    'capital_account_id' => $account?->id,
                    'txn_type'           => InvestorTransaction::TYPE_ALLOCATION,
                    'txn_date'           => $date->toDateString(),
                    'amount'             => $alloc['amount'],
                    'journal_batch_id'   => $batch->id,
                    'allocation_run_id'  => $run->id,
                    'created_by_user_id' => $actor->id,
                ]);

                if ($account) {
                    $account->update([
                        'total_allocated' => $this->add((string) $account->total_allocated, $alloc['amount']),
                        'current_balance' => $this->add((string) $account->current_balance, $alloc['amount']),
                        'last_movement_at'=> now(),
                    ]);
                }
            }

            return $run->fresh('transactions');
        });
    }

    /**
     * @return array<int, array{investor: Investor, amount: string}>
     */
    protected function buildOwnershipAllocations(string $profitPool): array
    {
        if ($this->cmp($profitPool, '0') <= 0) {
            throw new InvestorException('Profit pool must be greater than zero for ownership-pct allocation.');
        }

        $investors = Investor::query()
            ->when($this->currentOrganization->id() !== null,
                fn ($q) => $q->where('organization_id', $this->currentOrganization->id()),
                fn ($q) => $q->whereNull('organization_id'))
            ->where('status', Investor::STATUS_ACTIVE)
            ->where('investor_class', Investor::CLASS_EQUITY)
            ->where('ownership_percentage', '>', 0)
            ->orderBy('id')
            ->get();

        $out = [];
        $running = '0';
        $count = $investors->count();
        $i = 0;

        foreach ($investors as $investor) {
            $i++;
            // Last investor absorbs rounding so the sum equals the intended
            // total share (sum of ownership × pool), avoiding 1-pesewa drift.
            if ($i < $count) {
                $amount = $this->share($profitPool, (string) $investor->ownership_percentage);
                $running = $this->add($running, $amount);
            } else {
                $intendedTotal = $this->share($profitPool, $this->totalOwnership($investors));
                $amount = $this->sub($intendedTotal, $running);
                if ($this->cmp($amount, '0') < 0) {
                    $amount = '0.00';
                }
            }

            if ($this->cmp($amount, '0') > 0) {
                $out[] = ['investor' => $investor, 'amount' => $amount];
            }
        }

        return $out;
    }

    /**
     * @param  array<int, string>  $fixedSchedule
     * @return array<int, array{investor: Investor, amount: string}>
     */
    protected function buildFixedAllocations(array $fixedSchedule): array
    {
        $out = [];
        foreach ($fixedSchedule as $investorId => $amount) {
            $amount = $this->n((string) $amount);
            if ($this->cmp($amount, '0') <= 0) {
                continue;
            }
            $investor = Investor::query()
                ->when($this->currentOrganization->id() !== null,
                    fn ($q) => $q->where('organization_id', $this->currentOrganization->id()),
                    fn ($q) => $q->whereNull('organization_id'))
                ->where('id', (int) $investorId)
                ->where('status', Investor::STATUS_ACTIVE)
                ->first();
            if (! $investor) {
                throw new InvestorException("Investor #{$investorId} not found or inactive.");
            }
            $out[] = ['investor' => $investor, 'amount' => $amount];
        }
        return $out;
    }

    protected function totalOwnership($investors): string
    {
        return $investors->reduce(fn (string $c, Investor $i) => $this->add($c, (string) $i->ownership_percentage, 6), '0');
    }

    protected function guardNoCompletedRun(FiscalYear $year): void
    {
        $exists = InvestorProfitAllocationRun::query()
            ->where('fiscal_year_id', $year->id)
            ->where('status', InvestorProfitAllocationRun::STATUS_COMPLETED)
            ->exists();

        if ($exists) {
            throw new InvestorException(
                "Fiscal year #{$year->id} already has a completed profit-allocation run. "
                . 'Reverse it before allocating again.'
            );
        }
    }
}
