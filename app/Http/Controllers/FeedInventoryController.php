<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FeedInventory;
use App\Models\FeedType;
use App\Models\GroupFeeding;
use App\Models\Ingredient;
use App\Models\IngredientInventory;
use Carbon\Carbon;

class FeedInventoryController extends Controller
{
    // Show feed inventory and daily usage
    public function index(Request $request)
    {
        $inventories = FeedInventory::orderBy('feed_type_id')->get();

        // Daily usage for the last 14 days (feed types)
        $usage = GroupFeeding::selectRaw('feed_type_id, date as feed_date, SUM(amount) as total_used')
        ->where('date', '>=', now()->subDays(14)->toDateString())
        ->groupBy('feed_type_id', 'feed_date')
        ->orderBy('feed_date', 'desc')
        ->get();

        // Running balance (simple: current inventory minus sum of usage per day)
        $balances = [];
        foreach ($inventories as $inv) {
            $used = $usage->where('feed_type_id', $inv->feed_type_id)->sum('total_used');
            $balances[$inv->feed_type_id] = $inv->quantity - $used;
        }

        // Ingredient catalog + stock
        $ingredients = Ingredient::orderBy('name')->get();
        $ingredientInventoryMap = IngredientInventory::get()->keyBy('ingredient_id');

        return view('feeds.inventory', compact('inventories', 'usage', 'balances', 'ingredients', 'ingredientInventoryMap'));
    }

    // Show form to add feed stock
    public function create()
    {
        $feedTypes = FeedType::orderBy('name')->get();
        return view('feeds.inventory_create', compact('feedTypes'));
    }

    // Store new feed stock
    public function store(Request $request)
    {
        $data = $request->validate([
            'feed_type_id' => 'required|integer',
            'quantity' => 'required|numeric|min:0.1|max:100000',
            'unit' => 'required|string|max:10',
            'last_updated' => 'nullable|date',
        ]);
        $data['last_updated'] = $data['last_updated'] ?? now();
        FeedInventory::updateOrCreate(
            ['feed_type_id' => $data['feed_type_id']],
            $data
        );
        return redirect()->route('feed-inventory.index')->with('success', 'Feed inventory updated!');
    }

    // Analytics: show projected usage for a period
    public function analytics(Request $request)
    {
        $feedType = $request->feed_type;
        $days = $request->days ?? 7;
        $start = $request->start_date ?? now()->subDays($days)->toDateString();
        $end = $request->end_date ?? now()->toDateString();
        $usage = GroupFeeding::where('feed_type_id', $feedType)
            ->whereBetween('feed_date', [$start, $end])
            ->sum('feed_amount');
        $avgPerDay = $usage / max(1, $days);
        return response()->json([
            'total_used' => $usage,
            'avg_per_day' => $avgPerDay,
            'projected_needed' => $avgPerDay * $days,
        ]);
    }

    public function edit(FeedInventory $feedInventory)
    {
        $feedTypes = FeedType::all();

        return view('feeds.inventory-edit', [
            'feedInventory' => $feedInventory,
            'feedTypes' => $feedTypes,
        ]);
    }


    public function update(Request $request, FeedInventory $inventory)
    {
    $validated = $request->validate([
        'feed_type_id' => 'required|exists:feed_types,id',
        'quantity' => 'required|numeric|min:0.1',
        'unit' => 'required|string|max:50',
        'last_updated' => 'nullable|date',
    ]);

    $inventory->update($validated);

    return redirect()->route('feed-inventory.index')
        ->with('success', 'Feed inventory updated successfully.');
    }



    
    public function destroy(FeedInventory $inventory)
    {
        $inventory->delete();
        return redirect()->route('feed-inventory.index')->with('success', 'Feed inventory deleted!');
    }
    
    
}
