<?php

namespace App\Http\Controllers;

use App\Models\FeedBatch;
use App\Models\FeedType;
use App\Models\RawMaterial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FeedProductionController extends Controller
{
    public function index()
    {
        $feedTypes = FeedType::with(['recipes.rawMaterial'])
            ->latest()
            ->get();

        $rawMaterials = RawMaterial::orderBy('name')->get();

        $feedBatches = FeedBatch::with(['feedType', 'items.rawMaterial', 'mixer'])
            ->latest('batch_date')
            ->latest('id')
            ->paginate(10);

        $stats = [
            'feed_types' => FeedType::count(),
            'recipes' => \App\Models\FeedTypeRecipe::count(),
            'batches' => FeedBatch::count(),
            'total_feed_produced' => FeedBatch::sum('batch_size_kg'),
        ];

        return view('feeds.feed-production.index', compact(
            'feedTypes',
            'rawMaterials',
            'feedBatches',
            'stats'
        ));
    }

    public function storeFeedType(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:feed_types,name'],
            'notes' => ['nullable', 'string'],
        ]);

        FeedType::create($validated);

        return redirect()
            ->route('feed-production.index')
            ->with('success', 'Feed type created successfully.');
    }

    public function storeRecipe(Request $request)
    {
        $validated = $request->validate([
            'feed_type_id' => ['required', 'exists:feed_types,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.raw_material_id' => ['required', 'exists:raw_materials,id'],
            'items.*.quantity_per_100kg' => ['required', 'numeric', 'min:0.01'],
        ]);

        DB::transaction(function () use ($validated) {
            $feedType = FeedType::findOrFail($validated['feed_type_id']);

            $feedType->recipes()->delete();

            foreach ($validated['items'] as $item) {
                $feedType->recipes()->create([
                    'raw_material_id' => $item['raw_material_id'],
                    'quantity_per_100kg' => $item['quantity_per_100kg'],
                ]);
            }
        });

        return redirect()
            ->route('feed-production.index')
            ->with('success', 'Recipe saved successfully.');
    }

    public function mixFeed(Request $request)
    {
        $validated = $request->validate([
            'feed_type_id' => ['required', 'exists:feed_types,id'],
            'batch_date' => ['required', 'date'],
            'batch_size_kg' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($validated) {
            $feedType = FeedType::with(['recipes.rawMaterial'])->findOrFail($validated['feed_type_id']);

            if ($feedType->recipes->isEmpty()) {
                abort(422, 'This feed type does not have a recipe yet.');
            }

            $multiplier = ((float) $validated['batch_size_kg']) / 100;
            $totalCost = 0;
            $batchItems = [];

            foreach ($feedType->recipes as $recipe) {
                $requiredQty = (float) $recipe->quantity_per_100kg * $multiplier;
                $material = $recipe->rawMaterial;

                if (!$material) {
                    abort(422, 'A recipe ingredient is missing its raw material.');
                }

                if ((float) $material->quantity_in_stock < $requiredQty) {
                    abort(422, 'Not enough stock for ' . $material->name . '. Required: ' . number_format($requiredQty, 2) . ' ' . $material->unit . ', Available: ' . number_format($material->quantity_in_stock, 2) . ' ' . $material->unit . '.');
                }

                $unitCost = (float) $material->unit_cost;
                $itemTotal = $requiredQty * $unitCost;

                $totalCost += $itemTotal;

                $batchItems[] = [
                    'raw_material' => $material,
                    'quantity_used' => $requiredQty,
                    'unit_cost' => $unitCost,
                    'total_cost' => $itemTotal,
                ];
            }

            $feedBatch = FeedBatch::create([
                'feed_type_id' => $feedType->id,
                'batch_date' => $validated['batch_date'],
                'batch_size_kg' => $validated['batch_size_kg'],
                'total_cost' => $totalCost,
                'notes' => $validated['notes'] ?? null,
                'mixed_by' => Auth::id(),
            ]);

            foreach ($batchItems as $item) {
                $material = $item['raw_material'];

                $feedBatch->items()->create([
                    'raw_material_id' => $material->id,
                    'quantity_used' => $item['quantity_used'],
                    'unit_cost' => $item['unit_cost'],
                    'total_cost' => $item['total_cost'],
                ]);

                $material->decrement('quantity_in_stock', $item['quantity_used']);

                $material->stockLogs()->create([
                    'type' => 'usage',
                    'quantity' => -1 * $item['quantity_used'],
                    'unit_cost' => $item['unit_cost'],
                    'reference' => 'Feed Batch #' . $feedBatch->id,
                    'notes' => 'Used for ' . $feedType->name . ' batch on ' . $validated['batch_date'],
                    'performed_by' => Auth::id(),
                ]);
            }
        });

        return redirect()
            ->route('feed-production.index')
            ->with('success', 'Feed mixed successfully and raw material stock updated.');
    }
}