<?php
namespace App\Http\Controllers;

use App\Models\Ingredient;
use Illuminate\Http\Request;

class IngredientController extends Controller
{
    public function index()
    {
        return redirect()->route('feed-inventory.index', ['tab' => 'ingredients']);
    }

    public function create()
    {
        return view('ingredients.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120|unique:ingredients,name',
            'description' => 'nullable|string',
            'protein_percentage' => 'nullable|numeric|min:0|max:100',
            'energy_kcal_per_kg' => 'nullable|numeric|min:0',
            'fat_percentage' => 'nullable|numeric|min:0|max:100',
            'fiber_percentage' => 'nullable|numeric|min:0|max:100',
            'unit' => 'required|string|max:10',
            'cost_per_unit' => 'required|numeric|min:0',
            'initial_quantity' => 'nullable|numeric|min:0',
        ]);
        $ingredient = Ingredient::create($data);
        if (($data['initial_quantity'] ?? 0) > 0) {
            $inv = \App\Models\IngredientInventory::firstOrCreate(
                ['ingredient_id' => $ingredient->id],
                ['quantity' => 0, 'unit' => $ingredient->unit, 'last_updated' => now()]
            );
            $inv->quantity = (float)$inv->quantity + (float)($data['initial_quantity'] ?? 0);
            $inv->unit = $ingredient->unit;
            $inv->last_updated = now();
            $inv->save();
        }
        return redirect()->route('feed-inventory.index')->with('success', 'Ingredient created');
    }

    public function edit(Ingredient $ingredient)
    {
        $onHand = \App\Models\IngredientInventory::where('ingredient_id', $ingredient->id)->first();
        return view('ingredients.edit', compact('ingredient','onHand'));
    }

    public function update(Request $request, Ingredient $ingredient)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120|unique:ingredients,name,' . $ingredient->id,
            'description' => 'nullable|string',
            'protein_percentage' => 'nullable|numeric|min:0|max:100',
            'energy_kcal_per_kg' => 'nullable|numeric|min:0',
            'fat_percentage' => 'nullable|numeric|min:0|max:100',
            'fiber_percentage' => 'nullable|numeric|min:0|max:100',
            'unit' => 'required|string|max:10',
            'cost_per_unit' => 'required|numeric|min:0',
            'initial_quantity' => 'nullable|numeric|min:0',
            'adjust_quantity' => 'nullable|numeric|min:0',
        ]);
        $ingredient->update($data);
        if (($data['adjust_quantity'] ?? 0) > 0) {
            $inv = \App\Models\IngredientInventory::firstOrCreate(
                ['ingredient_id' => $ingredient->id],
                ['quantity' => 0, 'unit' => $ingredient->unit, 'last_updated' => now()]
            );
            $inv->quantity = (float)$inv->quantity + (float)$data['adjust_quantity'];
            $inv->unit = $ingredient->unit;
            $inv->last_updated = now();
            $inv->save();
        }
        // Also support increasing stock via initial_quantity on edit (quantity purchased)
        if (($data['initial_quantity'] ?? 0) > 0) {
            $inv = \App\Models\IngredientInventory::firstOrCreate(
                ['ingredient_id' => $ingredient->id],
                ['quantity' => 0, 'unit' => $ingredient->unit, 'last_updated' => now()]
            );
            $inv->quantity = (float)$inv->quantity + (float)$data['initial_quantity'];
            $inv->unit = $ingredient->unit;
            $inv->last_updated = now();
            $inv->save();
        }
        return redirect()->route('feed-inventory.index')->with('success', 'Ingredient updated');
    }

    public function destroy(Ingredient $ingredient)
    {
        $ingredient->delete();
        return back()->with('success', 'Ingredient deleted');
    }
}
