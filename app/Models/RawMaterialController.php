<?php

namespace App\Http\Controllers;

use App\Models\RawMaterial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RawMaterialController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->get('search'));
        $status = trim((string) $request->get('status'));

        $query = RawMaterial::query()->latest();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%")
                  ->orWhere('unit', 'like', "%{$search}%");
            });
        }

        if ($status !== '') {
            if ($status === 'out_of_stock') {
                $query->where('quantity_in_stock', '<=', 0);
            } elseif ($status === 'low_stock') {
                $query->where('quantity_in_stock', '>', 0)
                      ->whereColumn('quantity_in_stock', '<=', 'reorder_level');
            } elseif ($status === 'in_stock') {
                $query->whereColumn('quantity_in_stock', '>', 'reorder_level');
            }
        }

        $rawMaterials = $query->paginate(10)->withQueryString();

        $stats = [
            'total_materials' => RawMaterial::count(),
            'low_stock' => RawMaterial::where('quantity_in_stock', '>', 0)
                ->whereColumn('quantity_in_stock', '<=', 'reorder_level')
                ->count(),
            'out_of_stock' => RawMaterial::where('quantity_in_stock', '<=', 0)->count(),
            'stock_value' => RawMaterial::selectRaw('COALESCE(SUM(quantity_in_stock * unit_cost), 0) as total')
                ->value('total'),
        ];

        return view('feeds.raw-materials.index', compact(
            'rawMaterials',
            'stats',
            'search',
            'status'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:50'],
            'quantity_in_stock' => ['nullable', 'numeric', 'min:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($validated) {
            $openingQty = (float) ($validated['quantity_in_stock'] ?? 0);
            $unitCost = (float) ($validated['unit_cost'] ?? 0);

            $rawMaterial = RawMaterial::create([
                'name' => $validated['name'],
                'category' => $validated['category'] ?? null,
                'unit' => $validated['unit'],
                'quantity_in_stock' => $openingQty,
                'unit_cost' => $unitCost,
                'reorder_level' => (float) ($validated['reorder_level'] ?? 0),
                'notes' => $validated['notes'] ?? null,
            ]);

            if ($openingQty > 0) {
                $rawMaterial->stockLogs()->create([
                    'type' => 'receive',
                    'quantity' => $openingQty,
                    'unit_cost' => $unitCost,
                    'reference' => 'Opening Stock',
                    'notes' => 'Initial stock entered during material creation.',
                    'performed_by' => Auth::id(),
                ]);
            }
        });

        return redirect()
            ->route('raw-materials.index')
            ->with('success', 'Raw material created successfully.');
    }

    public function update(Request $request, RawMaterial $rawMaterial)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:50'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $rawMaterial->update([
            'name' => $validated['name'],
            'category' => $validated['category'] ?? null,
            'unit' => $validated['unit'],
            'unit_cost' => (float) ($validated['unit_cost'] ?? 0),
            'reorder_level' => (float) ($validated['reorder_level'] ?? 0),
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('raw-materials.index')
            ->with('success', 'Raw material updated successfully.');
    }

    public function receiveStock(Request $request, RawMaterial $rawMaterial)
    {
        $validated = $request->validate([
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($validated, $rawMaterial) {
            $quantity = (float) $validated['quantity'];
            $unitCost = array_key_exists('unit_cost', $validated)
                ? (float) ($validated['unit_cost'] ?? 0)
                : (float) $rawMaterial->unit_cost;

            $rawMaterial->increment('quantity_in_stock', $quantity);

            if ($unitCost >= 0) {
                $rawMaterial->update([
                    'unit_cost' => $unitCost,
                ]);
            }

            $rawMaterial->stockLogs()->create([
                'type' => 'receive',
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'reference' => $validated['reference'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'performed_by' => Auth::id(),
            ]);
        });

        return redirect()
            ->route('raw-materials.index')
            ->with('success', 'Stock received successfully.');
    }

    public function adjustStock(Request $request, RawMaterial $rawMaterial)
    {
        $validated = $request->validate([
            'adjustment_type' => ['required', 'in:add,deduct,set'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($validated, $rawMaterial) {
            $quantity = (float) $validated['quantity'];
            $current = (float) $rawMaterial->quantity_in_stock;
            $newQuantity = $current;

            if ($validated['adjustment_type'] === 'add') {
                $newQuantity = $current + $quantity;
            } elseif ($validated['adjustment_type'] === 'deduct') {
                $newQuantity = max(0, $current - $quantity);
            } elseif ($validated['adjustment_type'] === 'set') {
                $newQuantity = $quantity;
            }

            $difference = $newQuantity - $current;

            $rawMaterial->update([
                'quantity_in_stock' => $newQuantity,
            ]);

            $rawMaterial->stockLogs()->create([
                'type' => 'adjustment',
                'quantity' => $difference,
                'unit_cost' => $rawMaterial->unit_cost,
                'reference' => $validated['reference'] ?? null,
                'notes' => $validated['notes'] ?? ('Stock adjusted using "' . $validated['adjustment_type'] . '" action.'),
                'performed_by' => Auth::id(),
            ]);
        });

        return redirect()
            ->route('raw-materials.index')
            ->with('success', 'Stock adjusted successfully.');
    }
}