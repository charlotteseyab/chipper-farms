<?php
namespace App\Http\Controllers;

use App\Models\FeedType;
use Illuminate\Http\Request;
use App\Models\IngredientInventory; // ⬅️ ADDED: To fetch stock quantities

class FeedTypeController extends Controller
{
    public function index()
    {
        $feedTypes = FeedType::orderBy('name')->get();
        return view('feed_types.index', compact('feedTypes'));
    }

    public function create()
    {
        // 1. Fetch all ingredients (using full namespace as per file style)
        $ingredients = \App\Models\Ingredient::orderBy('name')->get(['id','name']);

        // 2. Fetch all ingredient inventory quantities and map by ingredient_id
        // This gives us a collection of inventory records keyed by ingredient_id
        $inventoryMap = IngredientInventory::get()->keyBy('ingredient_id');

        // 3. Filter the ingredient list to only include those with stock > 0
        $availableIngredients = $ingredients->filter(function ($ingredient) use ($inventoryMap) {
            $inventoryRecord = $inventoryMap->get($ingredient->id);
            
            // Get quantity (default to 0 if no inventory record exists)
            $quantity = $inventoryRecord ? $inventoryRecord->quantity : 0;
            
            // We also need the unit for the view, default to 'kg' if not on inventory record
            $unit = $inventoryRecord ? $inventoryRecord->unit : 'kg'; 

            // Augment the ingredient model with stock data for the view
            $ingredient->quantity = $quantity;
            $ingredient->unit = $unit;
            
            // Only return ingredients that have stock
            return $quantity > 0;
        });
        
        // Pass the filtered and augmented collection to the view
        return view('feed_types.create', [
            'ingredients' => $availableIngredients,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100|unique:feed_types,name',
            'description' => 'nullable|string|max:255',
            'protein_percent' => 'nullable|numeric|min:0|max:100',
            'energy_mj_per_kg' => 'nullable|numeric|min:0',
            'fiber_percent' => 'nullable|numeric|min:0|max:100',
            'cost_per_unit' => 'nullable|numeric|min:0',
            'unit' => 'required|string|max:10',
            'items' => 'nullable|array',
            'items.*.ingredient_id' => 'nullable|exists:ingredients,id',
            'items.*.quantity' => 'nullable|numeric|min:0.001',
        ]);
        $feedType = FeedType::create($data);

        // Save recipe if provided
        $rawItems = $request->input('items', []);
        $items = collect($rawItems)
            ->filter(function ($it) {
                return !empty($it['ingredient_id']) && isset($it['quantity']) && $it['quantity'] !== '';
            })
            ->values()
            ->all();
        if (!empty($items)) {
            $recipe = \App\Models\FeedRecipe::create(['feed_type_id' => $feedType->id, 'name' => null]);
            $totalBatch = 0.0;
            foreach ($items as $it) {
                $qty = (float)$it['quantity'];
                $totalBatch += $qty;
                \App\Models\FeedRecipeItem::create([
                    'feed_recipe_id' => $recipe->id,
                    'ingredient_id' => $it['ingredient_id'],
                    'percentage' => $qty, // reuse column to store absolute qty (kg)
                ]);
            }
            // Deduct exact ingredient quantities and increase feed stock by total batch
            if ($totalBatch > 0) {
                foreach ($items as $it) {
                    $ingredientId = (int)$it['ingredient_id'];
                    $shareKg = (float)$it['quantity'];
                    $inv = \App\Models\IngredientInventory::firstOrCreate(
                        ['ingredient_id' => $ingredientId],
                        ['quantity' => 0, 'unit' => 'kg', 'last_updated' => now()]
                    );
                    $inv->quantity = max(0, (float)$inv->quantity - $shareKg);
                    $inv->last_updated = now();
                    $inv->save();
                }
                // Increase feed type stock in FeedInventory by batch size
                $feedInv = \App\Models\FeedInventory::firstOrCreate(
                    ['feed_type_id' => $feedType->id],
                    ['quantity' => 0, 'unit' => $feedType->unit ?? 'kg', 'last_updated' => now()]
                );
                $feedInv->quantity = (float)$feedInv->quantity + $totalBatch;
                $feedInv->last_updated = now();
                $feedInv->save();
            }
        }

        return redirect()->route('feed-types.index')->with('success', 'Feed type created!');
    }

    public function edit(FeedType $feedType)
    {
        $feedType->load('recipe.items.ingredient');
        // This logic should also be updated if you want to apply the same filter to the edit view
        $ingredients = \App\Models\Ingredient::orderBy('name')->get(['id','name']); 
        return view('feed_types.edit', compact('feedType','ingredients'));
    }

    public function update(Request $request, FeedType $feedType)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100|unique:feed_types,name,' . $feedType->id,
            'description' => 'nullable|string|max:255',
            'protein_percent' => 'nullable|numeric|min:0|max:100',
            'energy_mj_per_kg' => 'nullable|numeric|min:0',
            'fiber_percent' => 'nullable|numeric|min:0|max:100',
            'cost_per_unit' => 'nullable|numeric|min:0',
            'unit' => 'required|string|max:10',
            'items' => 'nullable|array',
            'items.*.ingredient_id' => 'nullable|exists:ingredients,id',
            'items.*.quantity' => 'nullable|numeric|min:0.001',
        ]);
        $feedType->update($data);

        // Update recipe if provided
        if ($request->has('items')) {
            $items = collect($request->input('items', []))
                ->filter(function ($it) {
                    return !empty($it['ingredient_id']) && isset($it['quantity']) && $it['quantity'] !== '';
                })
                ->values()
                ->all();
            // Replace recipe
            $recipe = $feedType->recipe ?: \App\Models\FeedRecipe::create(['feed_type_id' => $feedType->id, 'name' => null]);
            $recipe->items()->delete();
            $totalBatch = 0.0;
            foreach ($items as $it) {
                $qty = (float)$it['quantity'];
                $totalBatch += $qty;
                \App\Models\FeedRecipeItem::create([
                    'feed_recipe_id' => $recipe->id,
                    'ingredient_id' => $it['ingredient_id'],
                    'percentage' => $qty, // store absolute qty (kg)
                ]);
            }

            // On edit, if items provided, deduct exact quantities and increase feed stock by sum
            if ($totalBatch > 0) {
                foreach ($items as $it) {
                    $ingredientId = (int)$it['ingredient_id'];
                    $shareKg = (float)$it['quantity'];
                    $inv = \App\Models\IngredientInventory::firstOrCreate(
                        ['ingredient_id' => $ingredientId],
                        ['quantity' => 0, 'unit' => 'kg', 'last_updated' => now()]
                    );
                    $inv->quantity = max(0, (float)$inv->quantity - $shareKg);
                    $inv->last_updated = now();
                    $inv->save();
                }
                $feedInv = \App\Models\FeedInventory::firstOrCreate(
                    ['feed_type_id' => $feedType->id],
                    ['quantity' => 0, 'unit' => $feedType->unit ?? 'kg', 'last_updated' => now()]
                );
                $feedInv->quantity = (float)$feedInv->quantity + $totalBatch;
                $feedInv->last_updated = now();
                $feedInv->save();
            }
        }

        return redirect()->route('feed-types.index')->with('success', 'Feed type updated!');
    }

    public function destroy(FeedType $feedType)
    {
        $feedType->delete();
        return redirect()->route('feed-types.index')->with('success', 'Feed type deleted!');
    }
}
