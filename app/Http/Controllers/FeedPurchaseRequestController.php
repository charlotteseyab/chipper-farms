<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FeedPurchaseRequest;
use Illuminate\Support\Facades\Auth;

class FeedPurchaseRequestController extends Controller
{
    // List all purchase requests
    public function index()
    {
        $requests = FeedPurchaseRequest::with('requestedBy')->orderBy('created_at', 'desc')->paginate(20);
        return view('feeds.purchase_requests', compact('requests'));
    }

    // Show form to create a new purchase request
    public function create()
    {
        $categories = ['feed','medication','equipment','supplies','maintenance'];
        return view('feeds.purchase_request_create', compact('categories'));
    }

    // Store a new purchase request
    public function store(Request $request)
    {
        $data = $request->validate([
            'category' => 'required|string|in:feed,medication,equipment,supplies,maintenance',
            'item_name' => 'nullable|string|max:120',
            'feed_type' => 'nullable|string|max:100', // legacy for category=feed
            'quantity' => 'required|numeric|min:1|max:100000',
            'unit' => 'string|max:10',
            'cost_ghc' => 'required|numeric|min:0.1|max:1000000',
            'needed_by' => 'nullable|date|after_or_equal:today',
            'notes' => 'nullable|string|max:1000',
            'vendor' => 'nullable|string|max:120',
            'priority' => 'nullable|string|in:low,normal,high,urgent',
        ]);

        // Normalize legacy field to avoid NOT NULL violations on feed_type column
        if (($data['category'] ?? null) === 'feed') {
            // Prefer explicit item_name; fallback to provided feed_type; ensure non-null string
            $data['feed_type'] = $data['item_name'] ?: ($data['feed_type'] ?? '');
        } else {
            // Non-feed categories: store empty string in legacy feed_type column
            $data['feed_type'] = $data['feed_type'] ?? '';
        }

        $data['requested_by'] = Auth::id();
        $data['status'] = 'pending';
        FeedPurchaseRequest::create($data);
        return redirect()->route('feed-purchase-requests.index')->with('success', 'Procurement request submitted!');
    }

    // Show a single purchase request
    public function show(FeedPurchaseRequest $request)
    {
        $request->load('requestedBy');
        return view('feeds.purchase_request_show', compact('request'));
    }

    // Approve a request (admin/superuser only)
    public function approve(Request $httpRequest, FeedPurchaseRequest $request)
    {
        abort_unless(Auth::user()?->isAdmin() || Auth::user()?->isSuperuser(), 403);
        $data = $httpRequest->validate([
            'decision_reason' => 'nullable|string|max:1000',
        ]);
        $request->update([
            'status' => 'approved',
            'decision_reason' => $data['decision_reason'] ?? null,
            'decided_by' => Auth::id(),
            'decided_at' => now(),
        ]);
        return back()->with('success', 'Request approved.');
    }

    // Reject a request (admin/superuser only)
    public function reject(Request $httpRequest, FeedPurchaseRequest $request)
    {
        abort_unless(Auth::user()?->isAdmin() || Auth::user()?->isSuperuser(), 403);
        $data = $httpRequest->validate([
            'decision_reason' => 'required|string|max:1000',
        ]);
        $request->update([
            'status' => 'rejected',
            'decision_reason' => $data['decision_reason'],
            'decided_by' => Auth::id(),
            'decided_at' => now(),
        ]);
        return back()->with('success', 'Request rejected.');
    }
}
