<?php

namespace App\Http\Controllers;

use App\Models\FeedType;
use App\Models\Finance\InventoryMovement;
use App\Models\RawMaterial;
use App\Models\StockCount;
use App\Services\Feed\FeedPlanningService;
use App\Services\Inventory\StockCountService;
use App\Support\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StockCountController extends Controller
{
    public function index()
    {
        $counts = StockCount::with('counter')
            ->withCount(['lines', 'lines as differences_count' => fn ($q) => $q->where('difference_qty', '!=', 0)])
            ->latest('counted_at')->latest('id')
            ->paginate(20)->withQueryString();

        return view('stock-counts.index', compact('counts') + ['glance' => app(\App\Services\Feed\FeedGlanceService::class)->stockCounts()]);
    }

    public function create(Request $request, FeedPlanningService $planning)
    {
        $orgId = app(CurrentOrganization::class)->requireId();
        $user = $request->user();
        $sections = [];

        if ($user->hasPermission(StockCountService::PERMISSIONS[InventoryMovement::ITEM_KIND_RAW_MATERIAL])) {
            $sections['raw_material'] = [
                'title' => 'Raw materials',
                'items' => RawMaterial::where('is_active', true)->orderBy('name')->get()->map(fn (RawMaterial $m) => [
                    'id' => $m->id, 'name' => $m->name, 'unit' => $m->unit,
                    'system' => $planning->available($orgId, 'raw_material', $m->id),
                ])->all(),
            ];
        }
        if ($user->hasPermission(StockCountService::PERMISSIONS[InventoryMovement::ITEM_KIND_FINISHED_FEED])) {
            $sections['finished_feed'] = [
                'title' => 'Finished feed',
                'items' => FeedType::where('is_active', true)->orderBy('name')->get()->map(fn (FeedType $t) => [
                    'id' => $t->id, 'name' => $t->name, 'unit' => 'kg',
                    'system' => $planning->available($orgId, 'finished_feed', $t->id),
                ])->all(),
            ];
        }

        return view('stock-counts.create', ['sections' => $sections, 'submissionKey' => (string) Str::uuid()]);
    }

    public function store(Request $request, StockCountService $service)
    {
        $validated = $request->validate([
            'submission_key'      => ['required', 'uuid'],
            'counted_at'          => ['nullable', 'date', 'before_or_equal:now'],
            'reference'           => ['nullable', 'string', 'max:200'],
            'notes'               => ['nullable', 'string', 'max:2000'],
            'counts'              => ['required', 'array'],
            'counts.*.*'          => ['nullable', 'numeric', 'min:0', 'max:100000000', 'decimal:0,4'],
        ], [
            'counts.*.*.min' => 'Counted quantities cannot be negative.',
        ]);

        $lines = [];
        foreach ($validated['counts'] as $kind => $items) {
            abort_unless(in_array($kind, array_keys(StockCountService::PERMISSIONS), true), 422);
            foreach ($items as $id => $qty) {
                if ($qty !== null && $qty !== '') {
                    $lines[] = ['item_kind' => $kind, 'item_id' => (int) $id, 'counted_qty' => (float) $qty];
                }
            }
        }

        try {
            $count = $service->record($request->user(), ['lines' => $lines] + $validated);
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $differences = $count->lines->where('difference_qty', '!=', 0)->count();

        return redirect()->route('stock-counts.show', $count)->with('success', $differences === 0
            ? 'Count saved. Everything matched the system.'
            : "Count saved. Stock updated for {$differences} ".Str::plural('item', $differences).' that differed.');
    }

    public function show(StockCount $stockCount)
    {
        $stockCount->load(['lines.stockLog', 'counter']);
        $showValues = request()->user()->hasPermission('view_finance');

        return view('stock-counts.show', ['count' => $stockCount, 'showValues' => $showValues]);
    }
}
