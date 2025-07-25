<?php

namespace App\Http\Controllers;

use App\Models\BreedingStock;
use Illuminate\Http\Request;

class BreedingStockController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $breedingStocks = BreedingStock::orderBy('created_at', 'desc')->get();
        return view('breeding_stocks.index', compact('breedingStocks'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('breeding_stocks.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'animal_id' => 'required|string|max:255',
            'breed' => 'required|string|max:255',
            'gender' => 'required|in:male,female',
            'date_of_birth' => 'required|date',
            'status' => 'required|string|max:255',
            'notes' => 'nullable|string',
        ]);
        BreedingStock::create($validated);
        return redirect()->route('breeding-stocks.index')->with('success', 'Breeding stock added successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(BreedingStock $breedingStock)
    {
        return view('breeding_stocks.edit', compact('breedingStock'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, BreedingStock $breedingStock)
    {
        $validated = $request->validate([
            'animal_id' => 'required|string|max:255',
            'breed' => 'required|string|max:255',
            'gender' => 'required|in:male,female',
            'date_of_birth' => 'required|date',
            'status' => 'required|string|max:255',
            'notes' => 'nullable|string',
        ]);
        $breedingStock->update($validated);
        return redirect()->route('breeding-stocks.index')->with('success', 'Breeding stock updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(BreedingStock $breedingStock)
    {
        $breedingStock->delete();
        return redirect()->route('breeding-stocks.index')->with('success', 'Breeding stock deleted successfully.');
    }
}
