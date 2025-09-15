<?php
namespace App\Http\Controllers;

use App\Models\FeedType;
use Illuminate\Http\Request;

class FeedTypeController extends Controller
{
    public function index()
    {
        $feedTypes = FeedType::orderBy('name')->get();
        return view('feed_types.index', compact('feedTypes'));
    }

    public function create()
    {
        return view('feed_types.create');
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
        ]);
        FeedType::create($data);
        return redirect()->route('feed-types.index')->with('success', 'Feed type created!');
    }

    public function edit(FeedType $feedType)
    {
        return view('feed_types.edit', compact('feedType'));
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
        ]);
        $feedType->update($data);
        return redirect()->route('feed-types.index')->with('success', 'Feed type updated!');
    }

    public function destroy(FeedType $feedType)
    {
        $feedType->delete();
        return redirect()->route('feed-types.index')->with('success', 'Feed type deleted!');
    }
}
