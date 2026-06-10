<?php

namespace App\Services\Finance\Close;

use App\Models\FiscalPeriod;
use App\Models\Finance\PeriodCloseCheck;
use App\Models\Finance\PeriodCloseRun;
use App\Models\Finance\PeriodCloseStatement;
use App\Models\User;
use App\Services\Finance\Close\Exceptions\PeriodCloseException;
use App\Services\Finance\Reporting\BalanceSheetService;
use App\Services\Finance\Reporting\CashFlowService;
use App\Services\Finance\Reporting\IncomeStatementService;
use App\Services\Finance\Reporting\TrialBalanceService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates the period-close workflow:
 *
 *   1. Run the validation checklist.
 *   2. Apply any operator-supplied overrides (with reasons) to failed
 *      SOFT checks. HARD checks (tb_balanced) can never be overridden.
 *   3. Decide pass/fail: close may proceed only if every check is
 *      pass / overridden / skipped.
 *   4. Snapshot the trial balance + statements into an immutable close
 *      pack.
 *   5. Flip the fiscal period status (soft close -> closed; hard close
 *      -> locked) and stamp the actor/time.
 *   6. Persist the run + checks + statements atomically.
 *
 * Reopen reverses a completed run: period back to open, run -> reopened.
 * Hard (locked) periods require a privileged reopen (enforced by policy /
 * permission at the controller; the service refuses to reopen a locked
 * period unless $allowLockedReopen is set).
 *
 * Strictness policy = "block with documented override":
 *   - HARD fail            -> close blocked, no override possible.
 *   - SOFT fail w/o override-> close blocked.
 *   - SOFT fail w/ override -> allowed, recorded with reason + actor.
 */
class PeriodCloseService
{
    public function __construct(
        protected CurrentOrganization $currentOrganization,
        protected PeriodCloseValidationService $validation,
        protected TrialBalanceService $trialBalance,
        protected IncomeStatementService $incomeStatement,
        protected BalanceSheetService $balanceSheet,
        protected CashFlowService $cashFlow,
    ) {
    }

    /**
     * Preview the checklist without closing. Returns the raw check results.
     *
     * @return array<int, array<string, mixed>>
     */
    public function preview(FiscalPeriod $period): array
    {
        return $this->validation->run($period);
    }

    /**
     * Close a period.
     *
     * @param  array<string, string>  $overrides  check_key => reason for any
     *                                            failed soft checks the actor
     *                                            chooses to override.
     * @throws PeriodCloseException
     */
    public function close(
        FiscalPeriod $period,
        User $actor,
        string $closeLevel = PeriodCloseRun::LEVEL_SOFT,
        array $overrides = [],
        ?string $notes = null,
    ): PeriodCloseRun {
        if ($period->status === FiscalPeriod::STATUS_LOCKED) {
            throw new PeriodCloseException("Period #{$period->id} is locked and cannot be re-closed.");
        }

        if (! in_array($closeLevel, [PeriodCloseRun::LEVEL_SOFT, PeriodCloseRun::LEVEL_HARD], true)) {
            throw new PeriodCloseException("Invalid close level '{$closeLevel}'.");
        }

        $checkResults = $this->validation->run($period);

        // Resolve each check's final status against overrides.
        $resolved = [];
        $overrideCount = 0;
        $blocking = [];

        foreach ($checkResults as $check) {
            $key = $check['check_key'];
            $status = $check['status'];

            if ($status === PeriodCloseCheck::STATUS_FAIL) {
                $isHard = in_array($key, PeriodCloseCheck::HARD_CHECKS, true);

                if ($isHard) {
                    $blocking[] = $key;
                } elseif (isset($overrides[$key]) && trim((string) $overrides[$key]) !== '') {
                    $status = PeriodCloseCheck::STATUS_OVERRIDDEN;
                    $check['override_reason'] = trim((string) $overrides[$key]);
                    $overrideCount++;
                } else {
                    $blocking[] = $key;
                }
            }

            $check['resolved_status'] = $status;
            $resolved[] = $check;
        }

        if (! empty($blocking)) {
            throw new PeriodCloseException(
                'Period close blocked by failing checks: ' . implode(', ', $blocking)
                . '. Hard checks cannot be overridden; soft checks require a documented override reason.'
            );
        }

        $asOf = CarbonImmutable::parse($period->end_date->toDateString());

        return DB::transaction(function () use ($period, $actor, $closeLevel, $resolved, $overrideCount, $notes, $asOf) {
            // Build statements for the close pack.
            $periodStart = Carbon::parse($period->start_date->toDateString());
            $periodEnd   = Carbon::parse($period->end_date->toDateString());

            $tb = $this->trialBalance->generate(null, $periodEnd);
            $pl = $this->incomeStatement->generate($periodStart, $periodEnd);
            $bs = $this->balanceSheet->generate($periodEnd);
            $cf = $this->cashFlow->generate($periodStart, $periodEnd);

            $summary = $this->summarise($resolved);

            $run = PeriodCloseRun::create([
                'organization_id'        => $this->currentOrganization->id(),
                'fiscal_period_id'       => $period->id,
                'status'                 => PeriodCloseRun::STATUS_COMPLETED,
                'close_level'            => $closeLevel,
                'initiated_by_user_id'   => $actor->id,
                'completed_at'           => now(),
                'completed_by_user_id'   => $actor->id,
                'trial_balance_snapshot' => json_encode($this->serialiseTrialBalance($tb)),
                'checks_summary'         => $summary,
                'override_count'         => $overrideCount,
                'notes'                  => $notes,
            ]);

            foreach ($resolved as $check) {
                PeriodCloseCheck::create([
                    'period_close_run_id'   => $run->id,
                    'check_key'             => $check['check_key'],
                    'label'                 => $check['label'],
                    'status'                => $check['resolved_status'],
                    'is_overridable'        => $check['is_overridable'],
                    'detail'                => $check['detail'] ?? null,
                    'overridden_by_user_id' => $check['resolved_status'] === PeriodCloseCheck::STATUS_OVERRIDDEN ? $actor->id : null,
                    'override_reason'       => $check['override_reason'] ?? null,
                    'overridden_at'         => $check['resolved_status'] === PeriodCloseCheck::STATUS_OVERRIDDEN ? now() : null,
                ]);
            }

            // Snapshot statements.
            $this->storeStatement($run->id, PeriodCloseStatement::TYPE_TRIAL_BALANCE, $this->serialiseTrialBalance($tb));
            $this->storeStatement($run->id, PeriodCloseStatement::TYPE_INCOME_STATEMENT, $pl);
            $this->storeStatement($run->id, PeriodCloseStatement::TYPE_BALANCE_SHEET, $bs);
            $this->storeStatement($run->id, PeriodCloseStatement::TYPE_CASH_FLOW, $cf);

            // Flip the period status.
            $period->update([
                'status'             => $closeLevel === PeriodCloseRun::LEVEL_HARD ? FiscalPeriod::STATUS_LOCKED : FiscalPeriod::STATUS_CLOSED,
                'closed_at'          => now(),
                'closed_by_user_id'  => $actor->id,
                'locked_at'          => $closeLevel === PeriodCloseRun::LEVEL_HARD ? now() : $period->locked_at,
                'locked_by_user_id'  => $closeLevel === PeriodCloseRun::LEVEL_HARD ? $actor->id : $period->locked_by_user_id,
            ]);

            return $run->fresh(['checks', 'statements']);
        });
    }

    /**
     * Reopen a closed/locked period: reverse the completed run, period back
     * to open.
     *
     * @throws PeriodCloseException
     */
    public function reopen(
        FiscalPeriod $period,
        User $actor,
        string $reason,
        bool $allowLockedReopen = false,
    ): FiscalPeriod {
        if (trim($reason) === '') {
            throw new PeriodCloseException('A reason is required to reopen a period.');
        }

        if ($period->status === FiscalPeriod::STATUS_OPEN) {
            throw new PeriodCloseException("Period #{$period->id} is already open.");
        }

        if ($period->status === FiscalPeriod::STATUS_LOCKED && ! $allowLockedReopen) {
            throw new PeriodCloseException(
                "Period #{$period->id} is locked (hard close). Reopening a locked period requires "
                . 'a privileged override.'
            );
        }

        // Year must still be open to reopen a period.
        $year = $period->fiscalYear;
        if ($year && $year->isLocked()) {
            throw new PeriodCloseException(
                "Cannot reopen period #{$period->id}: its fiscal year is locked. Reopen the year first."
            );
        }

        return DB::transaction(function () use ($period, $actor, $reason) {
            PeriodCloseRun::query()
                ->where('fiscal_period_id', $period->id)
                ->where('status', PeriodCloseRun::STATUS_COMPLETED)
                ->update([
                    'status'              => PeriodCloseRun::STATUS_REOPENED,
                    'reopened_at'         => now(),
                    'reopened_by_user_id' => $actor->id,
                    'reopen_reason'       => $reason,
                ]);

            $period->update([
                'status'            => FiscalPeriod::STATUS_OPEN,
                'closed_at'         => null,
                'closed_by_user_id' => null,
                'locked_at'         => null,
                'locked_by_user_id' => null,
            ]);

            return $period->fresh();
        });
    }

    // ----------------------------------------------------------------

    /**
     * @param  array<int, array<string, mixed>>  $resolved
     * @return array<string, int>
     */
    protected function summarise(array $resolved): array
    {
        $summary = ['total' => count($resolved), 'pass' => 0, 'fail' => 0, 'overridden' => 0, 'skipped' => 0];
        foreach ($resolved as $check) {
            $status = $check['resolved_status'] ?? $check['status'];
            match ($status) {
                PeriodCloseCheck::STATUS_PASS       => $summary['pass']++,
                PeriodCloseCheck::STATUS_OVERRIDDEN => $summary['overridden']++,
                PeriodCloseCheck::STATUS_SKIPPED    => $summary['skipped']++,
                default                             => $summary['fail']++,
            };
        }
        return $summary;
    }

    /**
     * The trial balance contains a Collection of row objects; serialise to a
     * plain array for JSON snapshotting.
     *
     * @param  array<string, mixed>  $tb
     * @return array<string, mixed>
     */
    protected function serialiseTrialBalance(array $tb): array
    {
        $rows = collect($tb['rows'] ?? [])->map(fn ($r) => (array) $r)->values()->all();
        return [
            'rows'   => $rows,
            'totals' => $tb['totals'] ?? [],
            'meta'   => $tb['meta'] ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function storeStatement(int $runId, string $type, array $payload): void
    {
        PeriodCloseStatement::create([
            'period_close_run_id' => $runId,
            'statement_type'      => $type,
            'payload'             => json_encode($payload),
            'generated_at'        => now(),
        ]);
    }
}
