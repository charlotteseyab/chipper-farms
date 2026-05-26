<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Services\Finance\Reporting\BalanceSheetService;
use App\Support\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Phase 6 — Balance Sheet (Statement of Financial Position) as at a date.
 */
class BalanceSheetController extends Controller
{
    public function __construct(protected CurrentOrganization $currentOrg)
    {
        $this->middleware('auth');
        $this->middleware('permission:view_financial_reports');
    }

    public function __invoke(Request $request, BalanceSheetService $service)
    {
        $asOf = $request->filled('as_of_date')
            ? Carbon::parse($request->get('as_of_date'))->endOfDay()
            : Carbon::now()->endOfDay();

        $statement = $service->generate($asOf);

        return view('finance.reports.balance-sheet', [
            'statement' => $statement,
            'asOf'      => $asOf,
        ]);
    }
}
