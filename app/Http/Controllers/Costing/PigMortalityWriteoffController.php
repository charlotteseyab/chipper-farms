<?php

namespace App\Http\Controllers\Costing;

use App\Http\Controllers\Controller;
use App\Models\Finance\PigMortalityWriteoff;
use App\Support\CurrentOrganization;
use Illuminate\Http\Request;

/**
 * Phase 5 — read-only mortality writeoff audit.
 *
 * Permission: manage_pig_mortality.
 *
 * Shows the operational mirror of pig death GL impact:
 *   - Per-death writeoff_amount
 *   - Group pool snapshot at the time of writeoff
 *   - Used-average flag (when no per-pig weight existed)
 *   - Journal batch linkage
 */
class PigMortalityWriteoffController extends Controller
{
    public function __construct(
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    public function index(Request $request)
    {
        $orgId = $this->currentOrganization->id();

        $writeoffs = PigMortalityWriteoff::query()
            ->with(['pigDeath.pig', 'pig', 'group', 'journalBatch', 'groupCostMovement'])
            ->when(
                $orgId !== null,
                fn ($q) => $q->where('organization_id', $orgId),
                fn ($q) => $q->whereNull('organization_id'),
            )
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(25);

        $totals = [
            'count'           => $writeoffs->total(),
            'total_writeoff'  => PigMortalityWriteoff::query()
                ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
                ->sum('writeoff_amount'),
            'used_avg_count'  => PigMortalityWriteoff::query()
                ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
                ->where('used_group_average_weight', true)
                ->count(),
        ];

        return view('costing.mortality-writeoffs.index', [
            'writeoffs' => $writeoffs,
            'totals'    => $totals,
        ]);
    }
}
