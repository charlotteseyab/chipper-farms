<?php
namespace App\Http\Controllers;

use App\Models\FeedingStandard;
use App\Models\FeedType;
use Illuminate\Http\Request;

class FeedingStandardController extends Controller
{
    public function index()
    {
        $standards = FeedingStandard::with('feedType')->orderBy('pig_category')->get();
        return view('feeding_standards.index', compact('standards'));
    }

    public function create()
    {
        $feedTypes = FeedType::orderBy('name')->get();
        return view('feeding_standards.create', compact('feedTypes'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'pig_category' => 'required|string|max:100',
            'feed_type_id' => 'required|exists:feed_types,id',
            'daily_amount' => 'required|numeric|min:0.01',
            'unit' => 'required|string|max:10',
        ]);
        FeedingStandard::create($data);
        return redirect()->route('feeding-standards.index')->with('success', 'Feeding standard created!');
    }

    public function edit(FeedingStandard $feedingStandard)
    {
        $feedTypes = FeedType::orderBy('name')->get();
        return view('feeding_standards.edit', compact('feedingStandard', 'feedTypes'));
    }

    public function update(Request $request, FeedingStandard $feedingStandard)
    {
        $data = $request->validate([
            'pig_category' => 'required|string|max:100',
            'feed_type_id' => 'required|exists:feed_types,id',
            'daily_amount' => 'required|numeric|min:0.01',
            'unit' => 'required|string|max:10',
        ]);
        $feedingStandard->update($data);
        return redirect()->route('feeding-standards.index')->with('success', 'Feeding standard updated!');
    }

    public function destroy(FeedingStandard $feedingStandard)
    {
        $feedingStandard->delete();
        return redirect()->route('feeding-standards.index')->with('success', 'Feeding standard deleted!');
    }
}
