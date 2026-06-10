<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\FiscalPeriod;
use App\Services\Finance\Reporting\CashFlowService;
use App\Support\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Phase 6 — Cash Flow Statement (indirect method).
 */
class CashFlowController extends Controller
{
    public function __construct(protected CurrentOrganization $currentOrg)
    {
    }

    public function __invoke(Request $request, CashFlowService $service)
    {
        $orgId = $this->currentOrg->id();

        if ($request->filled('from_date') && $request->filled('to_date')) {
            $from = Carbon::parse($request->get('from_date'))->startOfDay();
            $to   = Carbon::parse($request->get('to_date'))->endOfDay();
        } else {
            $period = FiscalPeriod::query()
                ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
                ->where('status', FiscalPeriod::STATUS_OPEN)
                ->where('start_date', '<=', now()->toDateString())
                ->where('end_date', '>=', now()->toDateString())
                ->first();

            if ($period) {
                $from = Carbon::parse($period->start_date)->startOfDay();
                $to   = Carbon::parse($period->end_date)->endOfDay();
            } else {
                $from = Carbon::now()->startOfYear();
                $to   = Carbon::now()->endOfDay();
            }
        }

        $statement = $service->generate($from, $to);

        return view('finance.reports.cash-flow', [
            'statement' => $statement,
            'from'      => $from,
            'to'        => $to,
        ]);
    }
}
