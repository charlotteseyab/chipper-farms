<?php

namespace App\Services\Finance\BiologicalAssets;

use App\Models\Finance\Account;
use App\Models\Finance\BiologicalAssetStageBalance;
use App\Models\Finance\GroupCostPool;
use App\Models\Finance\Journal;
use App\Models\Finance\JournalLine;
use App\Services\Finance\BiologicalAssets\Concerns\HandlesBioAssetDecimals;
use App\Support\CurrentOrganization;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Three-way reconciliation for biological assets:
 *
 *   A. Stage subledger total  ==  GL balance of stage accounts (1310–1370)
 *   B. Stage subledger total  ==  GroupCostPool total carrying value
 *   C. Clearing account 1230  ==  zero (should be drained post Phase 7)
 *
 * (A) proves the subledger ties to the ledger. (B) proves Phase 7's split
 * preserves Phase 5's group totals — i.e. we didn't lose or invent cost
 * when distributing across stages. (C) proves the cutover fully repointed
 * off the transitional clearing account.
 *
 * Read-only. Intended for the Phase 6 period-close checklist (the pig-pool
 * reconciliation should call this in place of the single-1230 check) and
 * for an on-demand admin report.
 */
class BiologicalAssetReconciliationService
{
    use HandlesBioAssetDecimals;

    /** @var array<int, string> */
    protected const STAGE_CODES = ['1310', '1320', '1330', '1340', '1350', '1360', '1370'];
    protected const CLEARING_CODE = '1230';

    public function __construct(
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    /**
     * @return array{
     *   subledger_vs_gl: array<string,mixed>,
     *   subledger_vs_pools: array<string,mixed>,
     *   clearing_drained: array<string,mixed>,
     *   reconciled: bool
     * }
     */
    public function run(CarbonInterface $asOf): array
    {
        $subledgerTotal = $this->subledgerTotal();
        $glStageTotal   = $this->glBalanceForCodes(self::STAGE_CODES, $asOf, debitNormal: true);
        $poolsTotal     = $this->groupPoolsTotal();
        $clearing       = $this->glBalanceForCodes([self::CLEARING_CODE], $asOf, debitNormal: true);

        $a = $this->result($subledgerTotal, $glStageTotal,
            'Stage subledger vs GL stage accounts (1310–1370)');
        $b = $this->result($subledgerTotal, $poolsTotal,
            'Stage subledger vs group cost pools');
        $c = $this->result($clearing, '0',
            'Clearing account 1230 drained to zero');

        return [
            'subledger_vs_gl'    => $a,
            'subledger_vs_pools' => $b,
            'clearing_drained'   => $c,
            'reconciled'         => $a['reconciled'] && $b['reconciled'] && $c['reconciled'],
        ];
    }

    protected function subledgerTotal(): string
    {
        $organizationId = $this->currentOrganization->id();

        return BiologicalAssetStageBalance::query()
            ->when($organizationId !== null,
                fn ($q) => $q->where('organization_id', $organizationId),
                fn ($q) => $q->whereNull('organization_id'))
            ->get()
            ->reduce(fn (string $c, $r) => $this->decAdd($c, (string) $r->carrying_value), '0');
    }

    protected function groupPoolsTotal(): string
    {
        $organizationId = $this->currentOrganization->id();

        return GroupCostPool::query()
            ->when($organizationId !== null,
                fn ($q) => $q->where('organization_id', $organizationId),
                fn ($q) => $q->whereNull('organization_id'))
            ->get()
            ->reduce(fn (string $c, $p) => $this->decAdd($c, (string) $p->total_carrying_value), '0');
    }

    /**
     * @param  array<int, string>  $codes
     */
    protected function glBalanceForCodes(array $codes, CarbonInterface $asOf, bool $debitNormal): string
    {
        $organizationId = $this->currentOrganization->id();

        $accountIds = Account::query()
            ->when($organizationId !== null,
                fn ($q) => $q->where('organization_id', $organizationId),
                fn ($q) => $q->whereNull('organization_id'))
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
            ->when($organizationId !== null,
                fn ($q) => $q->where('journal_lines.organization_id', $organizationId),
                fn ($q) => $q->whereNull('journal_lines.organization_id'))
            ->whereDate('journals.posting_date', '<=', $asOf->toDateString())
            ->selectRaw('COALESCE(SUM(journal_lines.debit),0) d, COALESCE(SUM(journal_lines.credit),0) c')
            ->first();

        $debit  = (string) ($row->d ?? '0');
        $credit = (string) ($row->c ?? '0');

        return $debitNormal ? $this->decSub($debit, $credit) : $this->decSub($credit, $debit);
    }

    /**
     * @return array<string, mixed>
     */
    protected function result(string $left, string $right, string $label): array
    {
        $variance = $this->decSub($left, $right, 2);
        return [
            'label'      => $label,
            'left'       => $this->decAdd($left, '0', 2),
            'right'      => $this->decAdd($right, '0', 2),
            'variance'   => $variance,
            'reconciled' => $this->decComp($variance, '0', 2) === 0,
        ];
    }
}
