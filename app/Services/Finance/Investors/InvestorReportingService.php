<?php

namespace App\Services\Finance\Investors;

use App\Models\Finance\Account;
use App\Models\Finance\Investor;
use App\Models\Finance\InvestorCapitalAccount;
use App\Models\Finance\InvestorTransaction;
use App\Models\Finance\Journal;
use App\Models\Finance\JournalLine;
use App\Services\Finance\Investors\Concerns\HandlesInvestorDecimals;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Read-only investor reporting:
 *
 *   statement()  per-investor ledger: contributions, allocations,
 *                distributions (and preferred accruals/payments), running
 *                capital balance.
 *
 *   capTable()   org-level table: each investor's ownership %, total
 *                contributed/allocated/distributed, current capital balance,
 *                and share of total capital.
 *
 *   reconcile()  ties the investor capital subledger (sum of capital-account
 *                current_balance) to the 3100 GL control balance.
 */
class InvestorReportingService
{
    use HandlesInvestorDecimals;

    public function __construct(protected CurrentOrganization $currentOrganization)
    {
    }

    /**
     * @return array{investor: Investor, lines: array<int, array<string,mixed>>, summary: array<string,string>}
     */
    public function statement(Investor $investor): array
    {
        $txns = InvestorTransaction::query()
            ->where('investor_id', $investor->id)
            ->orderBy('txn_date')
            ->orderBy('id')
            ->get();

        $lines = [];
        $balance = '0';
        foreach ($txns as $txn) {
            // Contributions + allocations increase capital; distributions reduce.
            // Preferred accruals/payments are liability-side, shown for context
            // but do not move the equity capital balance.
            $delta = match ($txn->txn_type) {
                InvestorTransaction::TYPE_CONTRIBUTION,
                InvestorTransaction::TYPE_ALLOCATION   => (string) $txn->amount,
                InvestorTransaction::TYPE_DISTRIBUTION => '-' . (string) $txn->amount,
                default => '0',
            };
            $balance = $this->add($balance, $delta);

            $lines[] = [
                'date'        => $txn->txn_date->toDateString(),
                'type'        => $txn->txn_type,
                'amount'      => (string) $txn->amount,
                'capital_delta' => $delta,
                'balance'     => $balance,
                'batch_id'    => $txn->journal_batch_id,
            ];
        }

        $account = $investor->capitalAccount()->first();

        return [
            'investor' => $investor,
            'lines'    => $lines,
            'summary'  => [
                'total_contributed' => (string) ($account->total_contributed ?? '0'),
                'total_allocated'   => (string) ($account->total_allocated ?? '0'),
                'total_distributed' => (string) ($account->total_distributed ?? '0'),
                'current_balance'   => (string) ($account->current_balance ?? '0'),
            ],
        ];
    }

    /**
     * @return array{rows: array<int, array<string,mixed>>, totals: array<string,string>}
     */
    public function capTable(): array
    {
        $organizationId = $this->currentOrganization->id();

        $investors = Investor::query()
            ->when($organizationId !== null,
                fn ($q) => $q->where('organization_id', $organizationId),
                fn ($q) => $q->whereNull('organization_id'))
            ->with('capitalAccount')
            ->orderByDesc('ownership_percentage')
            ->get();

        $totalCapital = $investors->reduce(
            fn (string $c, Investor $i) => $this->add($c, (string) ($i->capitalAccount->current_balance ?? '0')),
            '0'
        );

        $rows = [];
        foreach ($investors as $investor) {
            $balance = (string) ($investor->capitalAccount->current_balance ?? '0');
            $shareOfCapital = $this->cmp($totalCapital, '0') > 0
                ? bcadd(bcdiv(bcmul($balance, '100', 8), $totalCapital, 8), '0', 4)
                : '0';

            $rows[] = [
                'investor_id'        => $investor->id,
                'name'               => $investor->name,
                'class'              => $investor->investor_class,
                'ownership_pct'      => bcmul((string) $investor->ownership_percentage, '100', 4),
                'total_contributed'  => (string) ($investor->capitalAccount->total_contributed ?? '0'),
                'total_allocated'    => (string) ($investor->capitalAccount->total_allocated ?? '0'),
                'total_distributed'  => (string) ($investor->capitalAccount->total_distributed ?? '0'),
                'current_balance'    => $balance,
                'share_of_capital'   => $shareOfCapital,
            ];
        }

        return [
            'rows'   => $rows,
            'totals' => ['total_capital' => $totalCapital],
        ];
    }

    /**
     * @return array{label:string, subledger:string, gl:string, variance:string, reconciled:bool}
     */
    public function reconcile(?CarbonInterface $asOf = null): array
    {
        $asOf = $asOf ? CarbonImmutable::parse($asOf->toDateString()) : CarbonImmutable::now();
        $organizationId = $this->currentOrganization->id();

        $subledger = InvestorCapitalAccount::query()
            ->when($organizationId !== null,
                fn ($q) => $q->where('organization_id', $organizationId),
                fn ($q) => $q->whereNull('organization_id'))
            ->get()
            ->reduce(fn (string $c, $r) => $this->add($c, (string) $r->current_balance), '0');

        // Investor capital is represented across the 3100 block:
        //   3110 contributions (Cr), 3100 allocations (Cr), 3120 distributions (Dr).
        // The subledger current_balance = contributed + allocated − distributed,
        // so reconcile against the NET credit balance of the whole block.
        $cfg = (array) config('investors.accounts');
        $codes = array_values(array_unique([
            $cfg['capital_control'] ?? '3100',
            $cfg['contributions']   ?? '3110',
            $cfg['distributions']   ?? '3120',
        ]));

        $gl = '0';
        foreach ($codes as $code) {
            $gl = $this->add($gl, $this->glControlBalance($code, $asOf));
        }

        $variance = $this->sub($subledger, $gl);

        return [
            'label'      => 'Investor capital subledger vs 3100/3110/3120 block',
            'subledger'  => $subledger,
            'gl'         => $gl,
            'variance'   => $variance,
            'reconciled' => $this->cmp($variance, '0') === 0,
        ];
    }

    /**
     * Credit-normal control balance (equity): credits − debits.
     */
    protected function glControlBalance(string $code, CarbonImmutable $asOf): string
    {
        $organizationId = $this->currentOrganization->id();

        $acct = Account::query()
            ->when($organizationId !== null,
                fn ($q) => $q->where('organization_id', $organizationId),
                fn ($q) => $q->whereNull('organization_id'))
            ->where('code', $code)
            ->first();

        if (! $acct) {
            return '0';
        }

        $row = JournalLine::query()
            ->join('journals', 'journal_lines.journal_id', '=', 'journals.id')
            ->where('journals.status', Journal::STATUS_POSTED)
            ->where('journal_lines.account_id', $acct->id)
            ->when($organizationId !== null,
                fn ($q) => $q->where('journal_lines.organization_id', $organizationId),
                fn ($q) => $q->whereNull('journal_lines.organization_id'))
            ->whereDate('journals.posting_date', '<=', $asOf->toDateString())
            ->selectRaw('COALESCE(SUM(journal_lines.debit),0) d, COALESCE(SUM(journal_lines.credit),0) c')
            ->first();

        return $this->sub((string) ($row->c ?? '0'), (string) ($row->d ?? '0'));
    }
}
