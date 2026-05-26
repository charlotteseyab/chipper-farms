<?php

namespace App\Http\Controllers\Costing;

use App\Http\Controllers\Controller;
use App\Models\Finance\GroupCostMovement;
use App\Models\Finance\GroupCostPool;
use App\Models\Group;
use App\Support\CurrentOrganization;
use Illuminate\Http\Request;

/**
 * Phase 5 — read-only view onto group cost pools.
 *
 * Index: list of all pools with current carrying value + pig count.
 * Show:  per-pool movement history and current state.
 *
 * Permission: view_group_pools.
 */
class GroupCostPoolController extends Controller
{
    public function __construct(
        protected CurrentOrganization $currentOrganization,
    ) {
        $this->middleware('auth');
        $this->middleware('permission:view_group_pools');
    }

    public function index(Request $request)
    {
        $orgId = $this->currentOrganization->id();
        $showClosed = $request->boolean('show_closed', false);

        $pools = GroupCostPool::query()
            ->with('group')
            ->when(
                $orgId !== null,
                fn ($q) => $q->where('organization_id', $orgId),
                fn ($q) => $q->whereNull('organization_id'),
            )
            ->when(! $showClosed, fn ($q) => $q->whereNull('closed_at'))
            ->orderByDesc('total_carrying_value')
            ->get();

        $totals = [
            'pool_count'        => $pools->count(),
            'total_pig_count'   => $pools->sum(fn ($p) => (int) $p->on_hand_pig_count),
            'total_value'       => $pools->reduce(
                fn ($carry, $p) => bcadd($carry, (string) $p->total_carrying_value, 4),
                '0',
            ),
            'total_weight_kg'   => $pools->reduce(
                fn ($carry, $p) => bcadd($carry, (string) $p->total_weight_kg, 4),
                '0',
            ),
        ];

        return view('costing.group-pools.index', [
            'pools'      => $pools,
            'totals'     => $totals,
            'showClosed' => $showClosed,
        ]);
    }

    public function show(Request $request, GroupCostPool $pool)
    {
        $this->ensureInOrg($pool);
        $pool->load('group', 'lastMovement');

        $movements = GroupCostMovement::query()
            ->with(['performedBy', 'journalBatch'])
            ->where('group_id', $pool->group_id)
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return view('costing.group-pools.show', [
            'pool'      => $pool,
            'movements' => $movements,
        ]);
    }

    // ============================================================

    protected function ensureInOrg(GroupCostPool $pool): void
    {
        $orgId = $this->currentOrganization->id();
        if ($pool->organization_id !== null && $pool->organization_id !== $orgId) {
            abort(404);
        }
    }
}
