<?php

namespace App\Http\Controllers;

use App\Models\FeedBatch;
use App\Models\FeedType;
use App\Models\RawMaterial;
use App\Services\Feed\ProduceFeedBatchService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Feed production — feed types, recipes, and batch mixing.
 *
 * Phase E2 rewrite. mixFeed delegates to ProduceFeedBatchService,
 * fixing closure scope, missing imports, and missing event dispatch.
 */
class FeedProductionController extends Controller
{
    use AuthorizesRequests;

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
            'feed_types'          => FeedType::count(),
            'recipes'             => \App\Models\FeedTypeRecipe::count(),
            'batches'             => FeedBatch::count(),
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
            'name'  => ['required', 'string', 'max:255', 'unique:feed_types,name'],
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
            'feed_type_id'              => ['required', 'exists:feed_types,id'],
            'items'                     => ['required', 'array', 'min:1'],
            'items.*.raw_material_id'   => ['required', 'exists:raw_materials,id'],
            'items.*.quantity_per_100kg'=> ['required', 'numeric', 'min:0.01'],
        ]);

        DB::transaction(function () use ($validated) {
            $feedType = FeedType::findOrFail($validated['feed_type_id']);

            $feedType->recipes()->delete();

            foreach ($validated['items'] as $item) {
                $feedType->recipes()->create([
                    'raw_material_id'    => $item['raw_material_id'],
                    'quantity_per_100kg' => $item['quantity_per_100kg'],
                ]);
            }
        });

        return redirect()
            ->route('feed-production.index')
            ->with('success', 'Recipe saved successfully.');
    }

    /**
     * Mix a feed batch from a recipe — produces a FeedBatch and updates
     * raw material stock + valuation in one transaction.
     */
    public function mixFeed(Request $request, ProduceFeedBatchService $service)
    {
        $validated = $request->validate([
            'feed_type_id'  => ['required', 'exists:feed_types,id'],
            'batch_date'    => ['required', 'date'],
            'batch_size_kg' => ['required', 'numeric', 'min:0.01'],
            'notes'         => ['nullable', 'string'],
        ]);

        $service->execute($request->user(), [
            'feed_type_id'  => (int) $validated['feed_type_id'],
            'batch_date'    => $validated['batch_date'],
            'batch_size_kg' => (float) $validated['batch_size_kg'],
            'notes'         => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('feed-production.index')
            ->with('success', 'Feed mixed successfully and raw material stock updated.');
    }
}
