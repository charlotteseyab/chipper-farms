<?php
namespace App\Http\Controllers;

use App\Models\FeedRecipe;
use App\Models\FeedRecipeItem;
use App\Models\FeedType;
use App\Models\Ingredient;
use Illuminate\Http\Request;

class FeedRecipeController extends Controller
{
    public function index()
    {
        $recipes = FeedRecipe::with('feedType','items.ingredient')->orderBy('created_at','desc')->paginate(20);
        return view('recipes.index', compact('recipes'));
    }

    public function create()
    {
        $feedTypes = FeedType::orderBy('name')->get();
        $ingredients = Ingredient::orderBy('name')->get();
        return view('recipes.create', compact('feedTypes','ingredients'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'feed_type_id' => 'required|exists:feed_types,id',
            'name' => 'nullable|string|max:120',
            'items' => 'required|array',
            'items.*.ingredient_id' => 'required|exists:ingredients,id',
            'items.*.percentage' => 'required|numeric|min:0|max:100',
        ]);
        $sum = collect($data['items'])->sum('percentage');
        if (abs($sum - 100) > 0.01) {
            return back()->withErrors(['items' => 'Recipe percentages must sum to 100%'])->withInput();
        }
        $recipe = FeedRecipe::create(['feed_type_id' => $data['feed_type_id'], 'name' => $data['name'] ?? null]);
        foreach ($data['items'] as $it) {
            FeedRecipeItem::create([
                'feed_recipe_id' => $recipe->id,
                'ingredient_id' => $it['ingredient_id'],
                'percentage' => $it['percentage'],
            ]);
        }
        return redirect()->route('recipes.index')->with('success','Recipe created');
    }

    public function edit(FeedRecipe $recipe)
    {
        $recipe->load('items.ingredient');
        $feedTypes = FeedType::orderBy('name')->get();
        $ingredients = Ingredient::orderBy('name')->get();
        return view('recipes.edit', compact('recipe','feedTypes','ingredients'));
    }

    public function update(Request $request, FeedRecipe $recipe)
    {
        $data = $request->validate([
            'feed_type_id' => 'required|exists:feed_types,id',
            'name' => 'nullable|string|max:120',
            'items' => 'required|array',
            'items.*.ingredient_id' => 'required|exists:ingredients,id',
            'items.*.percentage' => 'required|numeric|min:0|max:100',
        ]);
        $sum = collect($data['items'])->sum('percentage');
        if (abs($sum - 100) > 0.01) {
            return back()->withErrors(['items' => 'Recipe percentages must sum to 100%'])->withInput();
        }
        $recipe->update(['feed_type_id' => $data['feed_type_id'], 'name' => $data['name'] ?? null]);
        $recipe->items()->delete();
        foreach ($data['items'] as $it) {
            FeedRecipeItem::create([
                'feed_recipe_id' => $recipe->id,
                'ingredient_id' => $it['ingredient_id'],
                'percentage' => $it['percentage'],
            ]);
        }
        return redirect()->route('recipes.index')->with('success','Recipe updated');
    }

    public function destroy(FeedRecipe $recipe)
    {
        $recipe->delete();
        return back()->with('success','Recipe deleted');
    }
}
