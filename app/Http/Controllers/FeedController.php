<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Feed;
use App\Models\PigManagement;
use App\Helpers\FlashHelper;
use Carbon\Carbon;

class FeedController extends Controller
{
    /**
     * Display a listing of feed records
     */
    public function index(Request $request)
    {
        $query = Feed::with(['pig', 'recorder']);

        // Filter by pig
        if ($request->pig_id) {
            $query->where('pig_id', $request->pig_id);
        }

        // Filter by feed type
        if ($request->feed_type) {
            $query->where('feed_type', $request->feed_type);
        }

        // Filter by date range
        if ($request->date_from) {
            $query->where('feed_date', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->where('feed_date', '<=', $request->date_to);
        }

        $feeds = $query->orderBy('feed_date', 'desc')
                      ->orderBy('feed_time', 'desc')
                      ->paginate(20);

        $feeds->appends($request->query());

        // Get filter options
        $pigs = PigManagement::active()->orderBy('ear_tag_number')->get();
        $feedTypes = Feed::distinct()->pluck('feed_type')->filter()->sort();

        return view('feeds.index', compact('feeds', 'pigs', 'feedTypes'));
    }

    /**
     * Show the form for creating a new feed record
     */
    public function create()
    {
        $pigs = PigManagement::active()->orderBy('ear_tag_number')->get();
        
        return view('feeds.create', compact('pigs'));
    }

    /**
     * Store a newly created feed record
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'pig_id' => 'required|exists:pigs_management,id',
            'feed_type' => 'required|in:starter,grower,finisher,breeding,lactating,maintenance,supplement,medicated',
            'feed_amount' => 'required|numeric|min:0.1|max:1000',
            'feed_unit' => 'required|in:kg,lbs,grams,pounds',
            'feed_date' => 'required|date|before_or_equal:today',
            'feed_time' => 'nullable|date_format:H:i',
            'notes' => 'nullable|string|max:1000',
            'feed_cost' => 'nullable|numeric|min:0|max:10000',
            'supplier' => 'nullable|string|max:255',
            'batch_number' => 'nullable|string|max:100',
            'expiry_date' => 'nullable|date|after:feed_date'
        ]);

        $data['recorded_by'] = auth()->id();

        $feed = Feed::create($data);

        return redirect()->route('feeds.index')
                        ->with('success', 'Feed record created successfully!');
    }

    /**
     * Display the specified feed record
     */
    public function show(Feed $feed)
    {
        $feed->load(['pig', 'recorder']);
        
        return view('feeds.show', compact('feed'));
    }

    /**
     * Show the form for editing the specified feed record
     */
    public function edit(Feed $feed)
    {
        $pigs = PigManagement::active()->orderBy('ear_tag_number')->get();
        
        return view('feeds.edit', compact('feed', 'pigs'));
    }

    /**
     * Update the specified feed record
     */
    public function update(Request $request, Feed $feed)
    {
        $data = $request->validate([
            'pig_id' => 'required|exists:pigs_management,id',
            'feed_type' => 'required|in:starter,grower,finisher,breeding,lactating,maintenance,supplement,medicated',
            'feed_amount' => 'required|numeric|min:0.1|max:1000',
            'feed_unit' => 'required|in:kg,lbs,grams,pounds',
            'feed_date' => 'required|date|before_or_equal:today',
            'feed_time' => 'nullable|date_format:H:i',
            'notes' => 'nullable|string|max:1000',
            'feed_cost' => 'nullable|numeric|min:0|max:10000',
            'supplier' => 'nullable|string|max:255',
            'batch_number' => 'nullable|string|max:100',
            'expiry_date' => 'nullable|date|after:feed_date'
        ]);

        $feed->update($data);

        return redirect()->route('feeds.index')
                        ->with('success', 'Feed record updated successfully!');
    }

    /**
     * Remove the specified feed record
     */
    public function destroy(Feed $feed)
    {
        $feed->delete();

        return redirect()->route('feeds.index')
                        ->with('success', 'Feed record deleted successfully!');
    }

    /**
     * Display feed schedule
     */
    public function schedule()
    {
        // Get upcoming feed schedules based on pig age and requirements
        $pigs = PigManagement::active()
                            ->with(['latestWeight'])
                            ->where('stock_type', '!=', 'breeding')
                            ->get();

        // This would typically integrate with a scheduling system
        // For now, we'll show a basic view
        return view('feeds.schedule', compact('pigs'));
    }

    /**
     * Display feed reports
     */
    public function reports(Request $request)
    {
        $startDate = $request->start_date ?? now()->subDays(30)->format('Y-m-d');
        $endDate = $request->end_date ?? now()->format('Y-m-d');

        // Get feed statistics
        $totalFeeds = Feed::whereBetween('feed_date', [$startDate, $endDate])->count();
        $totalCost = Feed::getTotalCost($startDate, $endDate);
        $totalAmount = Feed::getTotalAmount($startDate, $endDate, 'kg');

        // Get feed by type
        $feedsByType = Feed::whereBetween('feed_date', [$startDate, $endDate])
                          ->selectRaw('feed_type, COUNT(*) as count, SUM(feed_amount) as total_amount, SUM(feed_cost) as total_cost')
                          ->groupBy('feed_type')
                          ->orderBy('total_amount', 'desc')
                          ->get();

        // Get top consuming pigs
        $topConsumers = Feed::whereBetween('feed_date', [$startDate, $endDate])
                           ->with('pig')
                           ->selectRaw('pig_id, SUM(feed_amount) as total_amount, SUM(feed_cost) as total_cost, COUNT(*) as feed_count')
                           ->groupBy('pig_id')
                           ->orderBy('total_amount', 'desc')
                           ->limit(10)
                           ->get();

        return view('feeds.reports', compact(
            'totalFeeds', 
            'totalCost', 
            'totalAmount', 
            'feedsByType', 
            'topConsumers',
            'startDate',
            'endDate'
        ));
    }
}
