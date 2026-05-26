<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Finance\InventoryBalance;
use App\Services\Finance\Inventory\InventoryLedgerReader;
use App\Support\CurrentOrganization;
use Illuminate\Http\Request;

/**
 * Phase 4 — read-only inventory value report.
 *
 * Lists every (item_kind, item_id) with current on-hand qty, WAC, and
 * total value. Drilling into a row shows the per-item movement history.
 *
 * Permission gating:
 *   - view_inventory_value (required for both index and show)
 */
class InventoryValueController extends Controller
{
    public function __construct(
        protected CurrentOrganization $currentOrganization,
        protected InventoryLedgerReader $reader,
    ) {
        $this->middleware('auth');
        $this->middleware('permission:view_inventory_value');
    }

    public function index(Request $request)
    {
        $kind = $request->query('kind');
        if ($kind !== null && ! in_array($kind, InventoryBalance::ALLOWED_ITEM_KINDS, true)) {
            $kind = null;
        }

        $rows    = $this->reader->summary($kind);
        $totals  = $this->reader->totalValue();

        return view('inventory.value.index', [
            'rows'   => $rows,
            'totals' => $totals,
            'kind'   => $kind,
        ]);
    }

    public function show(Request $request, string $itemKind, int $itemId)
    {
        if (! in_array($itemKind, InventoryBalance::ALLOWED_ITEM_KINDS, true)) {
            abort(404);
        }

        $summary = $this->reader->summaryForItem($itemKind, $itemId);
        if (! $summary) {
            abort(404);
        }

        $movements = $this->reader->recentMovementsForItem($itemKind, $itemId, 100);

        return view('inventory.value.show', [
            'itemKind'  => $itemKind,
            'itemId'    => $itemId,
            'summary'   => $summary,
            'movements' => $movements,
        ]);
    }
}
