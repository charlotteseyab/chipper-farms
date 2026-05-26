<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\FiscalPeriod;
use App\Services\Finance\Reporting\TrialBalanceService;
use App\Support\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Phase 1d — Trial Balance report.
 *
 * Defaults to the current open fiscal period if no dates are passed.
 */
class TrialBalanceController extends Controller
{
    public function __invoke(Request $request, TrialBalanceService $service, CurrentOrganization $currentOrg)
    {
        $orgId = $currentOrg->id();

        // Determine date range. Either:
        //  - User passed from + as_of dates explicitly, OR
        //  - Default to current open period.
        $fromDate = $request->filled('from_date')
            ? Carbon::parse($request->get('from_date'))->startOfDay()
            : null;

        $asOfDate = $request->filled('as_of_date')
            ? Carbon::parse($request->get('as_of_date'))->endOfDay()
            : null;

        $hideZeroBalance = (bool) $request->get('hide_zero', false);

        if (! $asOfDate) {
            $currentPeriod = FiscalPeriod::query()
                ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
                ->where('status', FiscalPeriod::STATUS_OPEN)
                ->where('start_date', '<=', now()->toDateString())
                ->where('end_date', '>=', now()->toDateString())
                ->first();

            if ($currentPeriod) {
                $fromDate = Carbon::parse($currentPeriod->start_date)->startOfDay();
                $asOfDate = Carbon::parse($currentPeriod->end_date)->endOfDay();
            } else {
                // No current period — default to "everything up to today".
                $asOfDate = Carbon::today()->endOfDay();
            }
        }

        $result = $service->generate($fromDate, $asOfDate);

        if ($hideZeroBalance) {
            $result['rows'] = $result['rows']->filter(fn ($r) => $r->has_activity);
        }

        return view('finance.trial-balance.index', [
            'rows'            => $result['rows'],
            'totals'          => $result['totals'],
            'meta'            => $result['meta'],
            'fromDate'        => $fromDate?->toDateString(),
            'asOfDate'        => $asOfDate->toDateString(),
            'hideZeroBalance' => $hideZeroBalance,
        ]);
    }
}
