<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\PeriodCloseRun;
use App\Support\CurrentOrganization;

/**
 * Phase 6 — close pack viewer. Renders the immutable snapshot statements
 * frozen at the time of a completed close run.
 */
class CloseRunController extends Controller
{
    public function __construct(protected CurrentOrganization $currentOrg)
    {
        $this->middleware('auth');
        $this->middleware('permission:view_period_close');
    }

    public function show(PeriodCloseRun $run)
    {
        $orgId = $this->currentOrg->id();
        if ($orgId !== null && $run->organization_id !== null && $run->organization_id !== $orgId) {
            abort(404);
        }

        $run->load(['checks', 'statements', 'fiscalPeriod', 'initiatedBy', 'completedBy']);

        $statements = $run->statements->keyBy('statement_type')
            ->map(fn ($s) => $s->decodedPayload());

        return view('finance.close.run', [
            'run'        => $run,
            'statements' => $statements,
        ]);
    }
}
