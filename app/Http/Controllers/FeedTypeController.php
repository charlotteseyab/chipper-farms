<?php
namespace App\Http\Controllers;

use App\Models\FeedType;
use Illuminate\Http\Request;
use App\Models\IngredientInventory;

class FeedTypeController extends Controller
{
    /**
     * Helper to fetch and augment all ingredients with calculated stock quantities,
     * providing both total KG (for calculations) and display quantity/unit (for UI clarity).
     */
    private function getAugmentedIngredients()
    {
        // 1. Fetch all ingredients, including the necessary conversion factor (bag_weight_kg)
        $ingredients = \App\Models\Ingredient::orderBy('name')->get(['id', 'name', 'bag_weight_kg']);

        // 2. Fetch all ingredient inventory records for fast lookup
        // NOTE: This assumes a single consolidated inventory record per ingredient ID.
        $inventoryMap = IngredientInventory::get()->keyBy('ingredient_id');

        // 3. Augment the ingredient list
        $augmentedIngredients = $ingredients->map(function ($ingredient) use ($inventoryMap) {
            $inventoryRecord = $inventoryMap->get($ingredient->id);
            
            $quantity = 0.0;
            $inventoryUnit = 'kg'; // Default unit assumption
            $kgPerBag = (float) ($ingredient->bag_weight_kg ?? 1.0);
            
            if ($inventoryRecord) {
                $quantity = (float) $inventoryRecord->quantity;
                $inventoryUnit = strtolower($inventoryRecord->unit);
            }
            
            // Compute total amount in KG for internal use (unchanged)
            $currentStockKg = ($inventoryUnit === 'bag' || $inventoryUnit === 'bags')
                ? ($quantity * max($kgPerBag, 1.0))
                : $quantity;
            $ingredient->current_stock_kg = round($currentStockKg, 2);

            // Display logic for UI:
            // - If inventory tracked in bags, show the bag weight in kg (per bag), NOT total kg
            // - Otherwise, show the quantity in kg
            if ($inventoryUnit === 'bag' || $inventoryUnit === 'bags') {
                $totalKg = $quantity * $kgPerBag;
                $ingredient->current_stock_display = number_format($quantity, 2) . ' bags  (' . number_format($kgPerBag, 2) . ' kg/bag, ' . number_format($totalKg, 2) . ' kg total)';
                $ingredient->current_stock_unit = 'bags';
                $ingredient->current_stock_kg = round($totalKg, 2);
                $ingredient->per_bag_kg = number_format($kgPerBag, 2);
                $ingredient->total_bags = number_format($quantity, 2);
            } else {
                $ingredient->current_stock_display = number_format($quantity, 2) . ' kg';
                $ingredient->current_stock_unit = 'kg';
                $ingredient->current_stock_kg = round($quantity, 2);
            }
            
            return $ingredient;
        });
        
        return $augmentedIngredients;
    }

    public function index()
    {
        $feedTypes = FeedType::orderBy('name')->get();
        return view('feed_types.index', compact('feedTypes'));
    }

    public function create()
    {
        // Use the new helper function to get ingredients with converted stock
        $augmentedIngredients = $this->getAugmentedIngredients();
        
        return view('feed_types.create', [
            'ingredients' => $augmentedIngredients,
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
            
            if ($totalBatch > 0) {
                // Deduct exact ingredient quantities using the correct unit
                foreach ($items as $it) {
                    $ingredientId = (int)$it['ingredient_id'];
                    $shareKg = (float)$it['quantity']; // Amount required by recipe, always in KG

                    // Fetch ingredient and inventory to determine deduction logic
                    $ingredient = \App\Models\Ingredient::find($ingredientId, ['bag_weight_kg']);
                    $inv = \App\Models\IngredientInventory::where('ingredient_id', $ingredientId)->first();

                    if (!$inv) {
                         // If no inventory exists, create a default record to prevent errors
                         // Defaulting to 'kg' unit for simple deduction.
                         $inv = \App\Models\IngredientInventory::create([
                             'ingredient_id' => $ingredientId,
                             'quantity' => 0.0, 
                             'unit' => 'kg', 
                             'last_updated' => now(),
                         ]);
                    }
                    
                    $inventoryUnit = strtolower($inv->unit);
                    $kgPerBag = (float) ($ingredient->bag_weight_kg ?? 1.0); 

                    if ($inventoryUnit === 'bag' || $inventoryUnit === 'bags') {
                        // Inventory is stored in bags: convert required KG amount to bags
                        // Deduction amount in BAGS = Required KG / KG per Bag
                        $deductionInBags = $shareKg / $kgPerBag;
                        $totalKgNow = $inv->quantity * $kgPerBag;
                        $remainingKg = max(0, $totalKgNow - $shareKg);
                        $inv->quantity = $kgPerBag > 0 ? ($remainingKg / $kgPerBag) : 0; // Always recompute bags as float
                    } else {
                        // Inventory is stored in KG: deduct KG directly
                        $inv->quantity = max(0, (float)$inv->quantity - $shareKg);
                    }
                    
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
        
        // Use the new helper function to get ingredients with converted stock
        $ingredients = $this->getAugmentedIngredients();

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
                    $shareKg = (float)$it['quantity']; // Amount required by recipe, always in KG

                    // Fetch ingredient and inventory to determine deduction logic
                    $ingredient = \App\Models\Ingredient::find($ingredientId, ['bag_weight_kg']);
                    $inv = \App\Models\IngredientInventory::where('ingredient_id', $ingredientId)->first();

                    if (!$inv) {
                         // If no inventory exists, create a default record to prevent errors
                         // Defaulting to 'kg' unit for simple deduction.
                         $inv = \App\Models\IngredientInventory::create([
                             'ingredient_id' => $ingredientId,
                             'quantity' => 0.0, 
                             'unit' => 'kg', 
                             'last_updated' => now(),
                         ]);
                    }
                    
                    $inventoryUnit = strtolower($inv->unit);
                    $kgPerBag = (float) ($ingredient->bag_weight_kg ?? 1.0); 

                    if ($inventoryUnit === 'bag' || $inventoryUnit === 'bags') {
                        // Inventory is stored in bags: convert required KG amount to bags
                        // Deduction amount in BAGS = Required KG / KG per Bag
                        $deductionInBags = $shareKg / $kgPerBag;
                        $totalKgNow = $inv->quantity * $kgPerBag;
                        $remainingKg = max(0, $totalKgNow - $shareKg);
                        $inv->quantity = $kgPerBag > 0 ? ($remainingKg / $kgPerBag) : 0; // Always recompute bags as float
                    } else {
                        // Inventory is stored in KG: deduct KG directly
                        $inv->quantity = max(0, (float)$inv->quantity - $shareKg);
                    }
                    
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
