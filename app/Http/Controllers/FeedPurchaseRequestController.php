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
        return view('feeds.purchase_request_create');
    }

    // Store a new purchase request
    public function store(Request $request)
    {
        $data = $request->validate([
            'feed_type_id' => 'required|integer',
            'quantity' => 'required|numeric|min:1|max:100000',
            'unit' => 'required|string|max:10',
            'cost_ghc' => 'required|numeric|min:0.1|max:1000000',
            'needed_by' => 'nullable|date|after_or_equal:today',
            'notes' => 'nullable|string|max:1000',
        ]);
        $data['requested_by'] = Auth::id();
        $data['status'] = 'pending';
        FeedPurchaseRequest::create($data);
        return redirect()->route('feed-purchase-requests.index')->with('success', 'Feed purchase request submitted!');
    }

    // Show a single purchase request
    public function show(FeedPurchaseRequest $request)
    {
        $request->load('requestedBy');
        return view('feeds.purchase_request_show', compact('request'));
    }
}
