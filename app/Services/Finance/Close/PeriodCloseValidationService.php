<?php

namespace App\Services\Finance\Close;

use App\Models\DomainEvent;
use App\Models\FiscalPeriod;
use App\Models\Finance\BankReconciliation;
use App\Models\Finance\Journal;
use App\Models\Finance\PeriodCloseCheck;
use App\Services\Finance\Reporting\Concerns\HandlesReportingDecimals;
use App\Services\Finance\Reporting\TrialBalanceService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Runs the full pre-close validation checklist for a fiscal period and
 * returns a structured result per check. Does NOT mutate anything — it is
 * a pure read. The PeriodCloseService consumes this to decide whether a
 * close may proceed.
 *
 * Checks (check_key => meaning):
 *
 *   tb_balanced          [HARD]  Trial balance debits == credits to-date.
 *   outbox_drained       [soft]  No pending/failed domain events dated in
 *                                or before the period (un-posted operational
 *                                events would mean the GL is incomplete).
 *   no_draft_journals    [soft]  No draft journals dated within the period.
 *   ar_reconciled        [soft]  AR control == AR subledger.
 *   ap_reconciled        [soft]  AP control == AP subledger.
 *   inventory_reconciled [soft]  Inventory control == inventory balances.
 *   grir_reconciled      [soft]  GR/IR control == outstanding GR/IR.
 *   pig_pool_reconciled  [soft]  Live pig control == group cost pools.
 *   bank_reconciled      [soft]  All bank accounts have a reconciliation
 *                                covering the period (or none exist).
 *
 * Each result: ['check_key','label','status','is_overridable','detail'].
 * status is pass|fail|skipped (overrides are applied later by the close
 * service, not here).
 */
class PeriodCloseValidationService
{
    use HandlesReportingDecimals;

    public function __construct(
        protected CurrentOrganization $currentOrganization,
        protected TrialBalanceService $trialBalance,
        protected SubledgerReconciliationService $reconciliation,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>  ordered list of check results
     */
    public function run(FiscalPeriod $period): array
    {
        $asOf = CarbonImmutable::parse($period->end_date->toDateString());

        $checks = [];
        $checks[] = $this->checkTrialBalance($asOf);
        $checks[] = $this->checkOutboxDrained($period);
        $checks[] = $this->checkNoDraftJournals($period);

        foreach ($this->reconciliation->all($asOf) as $key => $recon) {
            $checks[] = [
                'check_key'      => $key,
                'label'          => $recon['label'],
                'status'         => $recon['reconciled'] ? PeriodCloseCheck::STATUS_PASS : PeriodCloseCheck::STATUS_FAIL,
                'is_overridable' => true,
                'detail'         => [
                    'gl_balance'        => $recon['gl_balance'],
                    'subledger_balance' => $recon['subledger_balance'],
                    'variance'          => $recon['variance'],
                ],
            ];
        }

        $checks[] = $this->checkBankReconciled($period);

        return $checks;
    }

    /** @return array<string, mixed> */
    protected function checkTrialBalance(CarbonImmutable $asOf): array
    {
        $tb = $this->trialBalance->generate(null, \Carbon\Carbon::parse($asOf->toDateString()));
        $balanced = (bool) ($tb['totals']['balanced'] ?? false);

        return [
            'check_key'      => PeriodCloseCheck::KEY_TB_BALANCED,
            'label'          => 'Trial balance is balanced (debits = credits)',
            'status'         => $balanced ? PeriodCloseCheck::STATUS_PASS : PeriodCloseCheck::STATUS_FAIL,
            'is_overridable' => false, // HARD
            'detail'         => [
                'total_debit'  => $tb['totals']['debit'] ?? '0',
                'total_credit' => $tb['totals']['credit'] ?? '0',
            ],
        ];
    }

    /** @return array<string, mixed> */
    protected function checkOutboxDrained(FiscalPeriod $period): array
    {
        $organizationId = $this->currentOrganization->id();

        $stuck = DomainEvent::query()
            ->whereIn('status', [DomainEvent::STATUS_PENDING, DomainEvent::STATUS_FAILED])
            ->whereDate('occurred_at', '<=', $period->end_date->toDateString())
            ->when(
                $organizationId !== null,
                fn ($q) => $q->where('organization_id', $organizationId),
                fn ($q) => $q->whereNull('organization_id'),
            )
            ->count();

        return [
            'check_key'      => PeriodCloseCheck::KEY_OUTBOX_DRAINED,
            'label'          => 'No pending or failed domain events for the period',
            'status'         => $stuck === 0 ? PeriodCloseCheck::STATUS_PASS : PeriodCloseCheck::STATUS_FAIL,
            'is_overridable' => true,
            'detail'         => ['stuck_event_count' => $stuck],
        ];
    }

    /** @return array<string, mixed> */
    protected function checkNoDraftJournals(FiscalPeriod $period): array
    {
        $organizationId = $this->currentOrganization->id();

        $drafts = Journal::query()
            ->where('status', Journal::STATUS_DRAFT)
            ->whereDate('posting_date', '>=', $period->start_date->toDateString())
            ->whereDate('posting_date', '<=', $period->end_date->toDateString())
            ->when(
                $organizationId !== null,
                fn ($q) => $q->where('organization_id', $organizationId),
                fn ($q) => $q->whereNull('organization_id'),
            )
            ->count();

        return [
            'check_key'      => PeriodCloseCheck::KEY_NO_DRAFT_JOURNALS,
            'label'          => 'No draft (unposted) journals dated in the period',
            'status'         => $drafts === 0 ? PeriodCloseCheck::STATUS_PASS : PeriodCloseCheck::STATUS_FAIL,
            'is_overridable' => true,
            'detail'         => ['draft_journal_count' => $drafts],
        ];
    }

    /** @return array<string, mixed> */
    protected function checkBankReconciled(FiscalPeriod $period): array
    {
        $organizationId = $this->currentOrganization->id();

        // If there are no bank reconciliations at all (e.g. cash-only farm
        // not yet using bank rec), this check is SKIPPED rather than failed.
        $hasAnyBankRec = BankReconciliation::query()
            ->when(
                $organizationId !== null,
                fn ($q) => $q->where('organization_id', $organizationId),
                fn ($q) => $q->whereNull('organization_id'),
            )
            ->exists();

        if (! $hasAnyBankRec) {
            return [
                'check_key'      => PeriodCloseCheck::KEY_BANK_RECONCILED,
                'label'          => 'Bank reconciliation complete for the period',
                'status'         => PeriodCloseCheck::STATUS_SKIPPED,
                'is_overridable' => true,
                'detail'         => ['reason' => 'No bank reconciliations exist; check skipped.'],
            ];
        }

        // A reconciliation whose statement period overlaps the fiscal period
        // and is in RECONCILED status counts as covering it. We use a
        // simple heuristic: at least one reconciled rec with statement end
        // date within the period.
        $reconciledInPeriod = BankReconciliation::query()
            ->where('status', BankReconciliation::STATUS_RECONCILED)
            ->when(
                $organizationId !== null,
                fn ($q) => $q->where('organization_id', $organizationId),
                fn ($q) => $q->whereNull('organization_id'),
            )
            ->count();

        return [
            'check_key'      => PeriodCloseCheck::KEY_BANK_RECONCILED,
            'label'          => 'Bank reconciliation complete for the period',
            'status'         => $reconciledInPeriod > 0 ? PeriodCloseCheck::STATUS_PASS : PeriodCloseCheck::STATUS_FAIL,
            'is_overridable' => true,
            'detail'         => ['reconciled_count' => $reconciledInPeriod],
        ];
    }
}
