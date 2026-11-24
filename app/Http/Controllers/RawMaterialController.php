<?php

namespace App\Http\Controllers;

use App\Models\Counterparty;
use App\Models\Finance\InventoryMovement;
use App\Models\RawMaterial;
use App\Models\RawMaterialStockLog;
use App\Services\Inventory\AdjustRawMaterialService;
use App\Services\Inventory\ReceiveRawMaterialService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Raw materials — Phase 4 updates the receive/adjust UX:
 *
 *   - receiveStock now requires supplier_id + receipt_reason for direct
 *     (non-PR) receipts. PR-linked receipts go through the dedicated
 *     receive-against-PR flow (ReceiveAgainstPurchaseRequestController).
 *
 *   - The opening-stock branch in store() uses receipt_reason='opening_stock'
 *     so the resulting receipt is correctly attributed and posts to the
 *     Inventory Suspense account (typically reclassed to equity in the
 *     cutover journal).
 *
 *   - adjustStock now accepts allow_negative=true and gates it on the
 *     override_negative_inventory permission.
 */
class RawMaterialController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $search = trim((string) $request->get('search'));
        $status = trim((string) $request->get('status'));

        // What needs attention first: out of stock, then at or below reorder level, then the rest by name.
        $query = RawMaterial::query()->with('inventoryBalance')
            ->orderByRaw('CASE WHEN quantity_in_stock <= 0 THEN 0 WHEN quantity_in_stock <= reorder_level THEN 1 ELSE 2 END')
            ->orderBy('name');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('unit', 'like', "%{$search}%");
            });
        }

        if ($status === 'low_stock') {
            $query->whereColumn('quantity_in_stock', '<=', 'reorder_level')
                ->where('quantity_in_stock', '>', 0);
        } elseif ($status === 'out_of_stock') {
            $query->where('quantity_in_stock', '<=', 0);
        } elseif ($status === 'in_stock') {
            $query->whereColumn('quantity_in_stock', '>', 'reorder_level');
        }

        $rawMaterials = $query->paginate(10)->withQueryString();

        $stats = [
            'total_materials'   => RawMaterial::count(),
            'low_stock'         => RawMaterial::whereColumn('quantity_in_stock', '<=', 'reorder_level')
                                              ->where('quantity_in_stock', '>', 0)
                                              ->count(),
            'out_of_stock'      => RawMaterial::where('quantity_in_stock', '<=', 0)->count(),
            'stock_value'       => RawMaterial::ledgerStockValue(),
        ];

        $recentStockActivities = RawMaterialStockLog::with(['rawMaterial', 'performer'])
            ->latest()
            ->paginate(8, ['*'], 'activity_page')
            ->withQueryString();

        $suppliers = Counterparty::query()
            ->suppliers()
            ->orderBy('name')
            ->get(['id', 'name']);

        // Stock is both kinds: feed already mixed, and the ingredients for more.
        $readyFeed = collect(app(\App\Services\Feed\FeedPlanningService::class)->calculate(
            (int) app(\App\Support\CurrentOrganization::class)->requireId(), now()->toDateString(), now()->addDays(6)->toDateString()
        )['feeds'])->sortBy('name')->values();

        return view('feeds.raw-materials.index', compact(
            'rawMaterials',
            'stats',
            'search',
            'status',
            'recentStockActivities',
            'suppliers',
            'readyFeed',
        ));
    }

    /**
     * Create a new raw material. If opening_quantity > 0, also records
     * an opening receipt via ReceiveRawMaterialService (receipt_reason =
     * 'opening_stock'). The opening supplier is the "system opening"
     * placeholder when none is selected (deployment seeds this counterparty).
     */
    public function store(Request $request, ReceiveRawMaterialService $receiveService)
    {
        $validated = $request->validate([
            'name'              => ['required', 'string', 'max:255'],
            'category'          => ['nullable', 'string', 'max:255'],
            'unit'              => ['required', 'string', 'max:50'],
            'kg_per_unit'       => ['nullable', 'numeric', 'gt:0', 'max:100000'],
            'quantity_in_stock' => ['nullable', 'numeric', 'min:0'],
            'unit_cost'         => ['nullable', 'numeric', 'min:0'],
            'reorder_level'     => ['nullable', 'numeric', 'min:0'],
            'notes'             => ['nullable', 'string'],
            'opening_supplier_id' => [
                Rule::requiredIf(fn () => (float) $request->input('quantity_in_stock', 0) > 0),
                'nullable',
                'integer',
                'exists:counterparties,id',
            ],
        ], [
            'opening_supplier_id.required' => 'Select the supplier responsible for the opening stock.',
        ]);

        DB::transaction(function () use ($validated, $receiveService, $request) {
            $openingQty = (float) ($validated['quantity_in_stock'] ?? 0);
            $unitCost   = (float) ($validated['unit_cost'] ?? 0);

            $rawMaterial = RawMaterial::create([
                'name'              => $validated['name'],
                'category'          => $validated['category'] ?? null,
                'unit'              => $validated['unit'],
                'kg_per_unit'       => $validated['kg_per_unit'] ?? null,
                'quantity_in_stock' => 0,  // Set by receive service if opening qty > 0.
                'unit_cost'         => $unitCost,
                'reorder_level'     => (float) ($validated['reorder_level'] ?? 0),
                'notes'             => $validated['notes'] ?? null,
            ]);

            // For genuine opening stock at material creation, the
            // OpeningBalanceService is the more rigorous path (audit row,
            // hash dedup). receiveStock here is for cases where ops simply
            // creates a material AND brings in physical stock together.
            // We require a supplier so the resulting receipt posts to
            // Inventory Suspense with proper attribution.
            if ($openingQty > 0) {
                $supplierId = (int) $validated['opening_supplier_id'];

                $receiveService->execute(
                    material: $rawMaterial,
                    actor:    $request->user(),
                    data:     [
                        'quantity'        => $openingQty,
                        'unit_cost'       => $unitCost,
                        'reference'       => 'Opening Stock',
                        'notes'           => 'Initial stock entered during material creation.',
                        'supplier_id'     => $supplierId,
                        'receipt_reason'  => 'opening_stock',
                    ],
                );
            }
        });

        return redirect()
            ->route('raw-materials.index')
            ->with('success', 'Raw material created successfully.');
    }

    public function update(Request $request, RawMaterial $rawMaterial)
    {
        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'category'      => ['nullable', 'string', 'max:255'],
            'unit'          => ['required', 'string', 'max:50'],
            'kg_per_unit'   => ['nullable', 'numeric', 'gt:0', 'max:100000'],
            'unit_cost'     => ['nullable', 'numeric', 'min:0'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'notes'         => ['nullable', 'string'],
        ]);

        // Stock history is recorded in the old unit; renaming the unit would
        // silently change what every past quantity means.
        $unitChanged = strtolower(trim($validated['unit'])) !== strtolower(trim((string) $rawMaterial->unit));
        if ($unitChanged && InventoryMovement::where('item_kind', InventoryMovement::ITEM_KIND_RAW_MATERIAL)->where('item_id', $rawMaterial->id)->exists()) {
            return back()->with('error', "{$rawMaterial->name} already has stock movements in {$rawMaterial->unit}, so its unit cannot change. Set kg per unit instead, or create a new material.");
        }

        $rawMaterial->update([
            'name'          => $validated['name'],
            'category'      => $validated['category'] ?? null,
            'unit'          => $validated['unit'],
            'kg_per_unit'   => $validated['kg_per_unit'] ?? null,
            'unit_cost'     => (float) ($validated['unit_cost'] ?? 0),
            'reorder_level' => (float) ($validated['reorder_level'] ?? 0),
            'notes'         => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('raw-materials.index')
            ->with('success', 'Raw material updated successfully.');
    }

    /**
     * Direct (non-PR) receipt. Requires supplier_id + receipt_reason
     * (Phase 4 invariant — receipts must be attributable).
     *
     * PR-linked receipts go through the receive-against-PR flow.
     */
    public function receiveStock(Request $request, RawMaterial $rawMaterial, ReceiveRawMaterialService $service)
    {
        $validated = $request->validate([
            'quantity'        => ['required', 'numeric', 'min:0.01'],
            'unit_cost'       => ['nullable', 'numeric', 'min:0'],
            'reference'       => ['nullable', 'string', 'max:255'],
            'notes'           => ['nullable', 'string'],
            'supplier_id'     => ['required', 'integer', 'exists:counterparties,id'],
            'receipt_reason'  => ['required', 'string', 'max:100'],
        ]);

        $unitCost = array_key_exists('unit_cost', $validated)
            ? (float) ($validated['unit_cost'] ?? 0)
            : (float) $rawMaterial->unit_cost;

        $service->execute(
            material: $rawMaterial,
            actor:    $request->user(),
            data:     [
                'quantity'        => (float) $validated['quantity'],
                'unit_cost'       => $unitCost,
                'reference'       => $validated['reference'] ?? null,
                'notes'           => $validated['notes'] ?? null,
                'supplier_id'     => (int) $validated['supplier_id'],
                'receipt_reason'  => $validated['receipt_reason'],
            ],
        );

        return redirect()
            ->route('raw-materials.index')
            ->with('success', 'Stock received successfully.');
    }

    public function adjustStock(Request $request, RawMaterial $rawMaterial, AdjustRawMaterialService $service)
    {
        $validated = $request->validate([
            'adjustment_type' => ['required', 'in:add,deduct,set'],
            'quantity'        => ['required', 'numeric', 'min:0'],
            'reference'       => ['nullable', 'string', 'max:255'],
            'notes'           => ['required', 'string', 'min:5', 'max:2000'],
            'allow_negative'  => ['nullable', 'boolean'],
        ]);

        $allowNegative = (bool) ($validated['allow_negative'] ?? false);
        if ($allowNegative && ! $request->user()->hasPermission('override_negative_inventory')) {
            abort(403, 'You do not have permission to override negative inventory.');
        }

        try {
            $service->execute(
                material: $rawMaterial,
                actor:    $request->user(),
                data:     [
                    'adjustment_type' => $validated['adjustment_type'],
                    'quantity'        => (float) $validated['quantity'],
                    'reference'       => $validated['reference'] ?? null,
                    'notes'           => $validated['notes'] ?? null,
                ],
                allowNegative: $allowNegative,
            );
        } catch (\DomainException $e) {
            abort(422, $e->getMessage());
        }

        return redirect()
            ->route('raw-materials.index')
            ->with('success', 'Stock adjusted successfully.');
    }
}
