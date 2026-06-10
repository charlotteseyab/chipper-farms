<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\FiscalPeriod;
use App\Services\Finance\Reporting\IncomeStatementService;
use App\Support\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Phase 6 — Income Statement (P&L).
 *
 * Defaults to the current open fiscal period. Optional comparison to the
 * immediately preceding period of equal length when ?compare=1.
 */
class IncomeStatementController extends Controller
{
    public function __construct(protected CurrentOrganization $currentOrg)
    {
    }

    public function __invoke(Request $request, IncomeStatementService $service)
    {
        [$from, $to] = $this->resolveRange($request);

        $compareFrom = null;
        $compareTo = null;
        if ($request->boolean('compare')) {
            $days = $from->diffInDays($to);
            $compareTo = (clone $from)->subDay()->endOfDay();
            $compareFrom = (clone $compareTo)->subDays($days)->startOfDay();
        }

        $statement = $service->generate($from, $to, $compareFrom, $compareTo);

        return view('finance.reports.income-statement', [
            'statement' => $statement,
            'from'      => $from,
            'to'        => $to,
            'compare'   => $request->boolean('compare'),
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function resolveRange(Request $request): array
    {
        $orgId = $this->currentOrg->id();

        if ($request->filled('from_date') && $request->filled('to_date')) {
            return [
                Carbon::parse($request->get('from_date'))->startOfDay(),
                Carbon::parse($request->get('to_date'))->endOfDay(),
            ];
        }

        $period = FiscalPeriod::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->where('status', FiscalPeriod::STATUS_OPEN)
            ->where('start_date', '<=', now()->toDateString())
            ->where('end_date', '>=', now()->toDateString())
            ->first();

        if ($period) {
            return [
                Carbon::parse($period->start_date)->startOfDay(),
                Carbon::parse($period->end_date)->endOfDay(),
            ];
        }

        return [Carbon::now()->startOfYear(), Carbon::now()->endOfDay()];
    }
}
