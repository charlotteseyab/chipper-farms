<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Services\Finance\Treasury\TreasuryService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Phase 8 — treasury cash-position dashboard (read-only).
 */
class TreasuryController extends Controller
{
    public function __construct(protected CurrentOrganization $currentOrg)
    {
    }

    public function __invoke(Request $request, TreasuryService $service)
    {
        $asOf = $request->filled('as_of_date') ? CarbonImmutable::parse($request->get('as_of_date')) : null;
        return view('finance.treasury.index', [
            'position' => $service->position($asOf, 12),
        ]);
    }
}
