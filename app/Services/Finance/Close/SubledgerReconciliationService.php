<?php

namespace App\Services\Finance\Close;

use App\Models\Finance\Account;
use App\Models\Finance\GroupCostPool;
use App\Models\Finance\GrIrEntry;
use App\Models\Finance\InventoryBalance;
use App\Models\Finance\Journal;
use App\Models\Finance\JournalLine;
use App\Services\Finance\Ageing\ApAgeingService;
use App\Services\Finance\Ageing\ArAgeingService;
use App\Services\Finance\Reporting\Concerns\HandlesReportingDecimals;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Reconciles each control account's GL balance against its operational
 * subledger as at a date. Used by the period-close checklist (and usable
 * standalone for ad-hoc reconciliation).
 *
 * Five reconciliations:
 *
 *   AR        GL sum of 1110/1120/1130  ==  AR ageing grand total
 *   AP        GL sum of 2010/2020/2030/2090  ==  AP ageing grand total
 *   Inventory GL sum of 1210/1220  ==  inventory_balances on-hand value
 *   GR/IR     GL balance of 2050  ==  sum of outstanding gr_ir_entries
 *   Pig pool  GL balance of 1230  ==  sum of group_cost_pools carrying value
 *
 * Each returns a structured result: gl_balance, subledger_balance,
 * variance, reconciled (variance == 0 at scale 2).
 *
 * IMPORTANT: balances are computed as-at a date from POSTED journals only,
 * matching the statement services. The subledger sides use each module's
 * own authoritative source.
 */
class SubledgerReconciliationService
{
    use HandlesReportingDecimals;

    /** Control account code groups per reconciliation. */
    protected const AR_CODES        = ['1110', '1120', '1130'];
    protected const AP_CODES        = ['2010', '2020', '2030', '2090'];
    protected const INVENTORY_CODES = ['1210', '1220'];
    protected const GRIR_CODE       = '2050';
    protected const PIG_POOL_CODE   = '1230';

    public function __construct(
        protected CurrentOrganization $currentOrganization,
        protected ArAgeingService $arAgeing,
        protected ApAgeingService $apAgeing,
    ) {
    }

    /**
     * Run all five reconciliations as at $asOf.
     *
     * @return array<string, array<string, mixed>>  keyed by check_key
     */
    public function all(CarbonInterface $asOf): array
    {
        return [
            'ar_reconciled'        => $this->ar($asOf),
            'ap_reconciled'        => $this->ap($asOf),
            'inventory_reconciled' => $this->inventory($asOf),
            'grir_reconciled'      => $this->grIr($asOf),
            'pig_pool_reconciled'  => $this->pigPool($asOf),
        ];
    }

    /** @return array<string, mixed> */
    public function ar(CarbonInterface $asOf): array
    {
        $gl = $this->glBalanceForCodes(self::AR_CODES, $asOf, debitNormal: true);
        $report = $this->arAgeing->build(CarbonImmutable::parse($asOf->toDateString()));
        $sub = (string) ($report->grandTotals['total'] ?? '0');

        return $this->result($gl, $sub, 'Accounts Receivable (1110/1120/1130) vs AR ageing');
    }

    /** @return array<string, mixed> */
    public function ap(CarbonInterface $asOf): array
    {
        $gl = $this->glBalanceForCodes(self::AP_CODES, $asOf, debitNormal: false);
        $report = $this->apAgeing->build(CarbonImmutable::parse($asOf->toDateString()));
        $sub = (string) ($report->grandTotals['total'] ?? '0');

        return $this->result($gl, $sub, 'Accounts Payable (2010/2020/2030/2090) vs AP ageing');
    }

    /** @return array<string, mixed> */
    public function inventory(CarbonInterface $asOf): array
    {
        $gl = $this->glBalanceForCodes(self::INVENTORY_CODES, $asOf, debitNormal: true);

        // Subledger: live inventory_balances on-hand value. NOTE: this is a
        // CURRENT snapshot, not historical-as-at — acceptable for period
        // close run at/near period end. Documented in the deploy guide.
        $organizationId = $this->currentOrganization->id();
        $sub = InventoryBalance::query()
            ->when(
                $organizationId !== null,
                fn ($q) => $q->where('organization_id', $organizationId),
                fn ($q) => $q->whereNull('organization_id'),
            )
            // Medicine stock is not posted to the GL, so it is not in 1210/1220.
            ->whereIn('item_kind', InventoryBalance::GL_POSTED_ITEM_KINDS)
            ->get()
            ->reduce(fn (string $carry, InventoryBalance $b) => $this->decAdd($carry, (string) $b->on_hand_value), '0');

        return $this->result($gl, $sub, 'Inventory (1210/1220) vs inventory_balances on-hand value');
    }

    /** @return array<string, mixed> */
    public function grIr(CarbonInterface $asOf): array
    {
        $gl = $this->glBalanceForCodes([self::GRIR_CODE], $asOf, debitNormal: false);

        $organizationId = $this->currentOrganization->id();
        $sub = GrIrEntry::query()
            ->where('status', GrIrEntry::STATUS_OUTSTANDING)
            ->when(
                $organizationId !== null,
                fn ($q) => $q->where('organization_id', $organizationId),
                fn ($q) => $q->whereNull('organization_id'),
            )
            ->get()
            ->reduce(function (string $carry, GrIrEntry $e) {
                $outstanding = $this->decSub((string) $e->receipt_amount, (string) ($e->cleared_amount ?? '0'));
                return $this->decAdd($carry, $outstanding);
            }, '0');

        return $this->result($gl, $sub, 'GR/IR (2050) vs outstanding gr_ir_entries');
    }

    /** @return array<string, mixed> */
    public function pigPool(CarbonInterface $asOf): array
    {
        $gl = $this->glBalanceForCodes([self::PIG_POOL_CODE], $asOf, debitNormal: true);

        $organizationId = $this->currentOrganization->id();
        $sub = GroupCostPool::query()
            ->when(
                $organizationId !== null,
                fn ($q) => $q->where('organization_id', $organizationId),
                fn ($q) => $q->whereNull('organization_id'),
            )
            ->get()
            ->reduce(fn (string $carry, GroupCostPool $p) => $this->decAdd($carry, (string) $p->total_carrying_value), '0');

        return $this->result($gl, $sub, 'Live Pig Inventory (1230) vs group_cost_pools carrying value');
    }

    // ----------------------------------------------------------------

    /**
     * GL balance (posted, as-at) summed across a set of account codes, in
     * the requested normal-balance sign.
     *
     * @param  array<int, string>  $codes
     */
    protected function glBalanceForCodes(array $codes, CarbonInterface $asOf, bool $debitNormal): string
    {
        $organizationId = $this->currentOrganization->id();

        $accountIds = Account::query()
            ->when(
                $organizationId !== null,
                fn ($q) => $q->where('organization_id', $organizationId),
                fn ($q) => $q->whereNull('organization_id'),
            )
            ->whereIn('code', $codes)
            ->pluck('id')
            ->all();

        if (empty($accountIds)) {
            return '0';
        }

        $row = JournalLine::query()
            ->join('journals', 'journal_lines.journal_id', '=', 'journals.id')
            ->where('journals.status', Journal::STATUS_POSTED)
            ->whereIn('journal_lines.account_id', $accountIds)
            ->when(
                $organizationId !== null,
                fn ($q) => $q->where('journal_lines.organization_id', $organizationId),
                fn ($q) => $q->whereNull('journal_lines.organization_id'),
            )
            ->whereDate('journals.posting_date', '<=', $asOf->toDateString())
            ->selectRaw('SUM(journal_lines.debit) as d, SUM(journal_lines.credit) as c')
            ->first();

        $debit  = $row && $row->d !== null ? (string) $row->d : '0';
        $credit = $row && $row->c !== null ? (string) $row->c : '0';

        return $debitNormal ? $this->decSub($debit, $credit) : $this->decSub($credit, $debit);
    }

    /**
     * Build a reconciliation result row.
     *
     * @return array<string, mixed>
     */
    protected function result(string $glBalance, string $subledgerBalance, string $label): array
    {
        $variance = $this->decSub($glBalance, $subledgerBalance);
        $reconciled = $this->decComp($variance, '0') === 0;

        return [
            'label'             => $label,
            'gl_balance'        => $this->decAdd($glBalance, '0'),        // normalise to scale 2
            'subledger_balance' => $this->decAdd($subledgerBalance, '0'),
            'variance'          => $variance,
            'reconciled'        => $reconciled,
        ];
    }
}
