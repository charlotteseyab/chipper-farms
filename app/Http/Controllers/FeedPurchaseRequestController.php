<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FeedPurchaseRequest;
use App\Models\User;
use App\Notifications\FeedPurchaseRequestNotificationImmediate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;

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
        $request = FeedPurchaseRequest::create($data);

        // Notify admins and superusers about new purchase request AFTER COMMIT with a per-user lock
        Log::info('[FPR] Created request', ['request_id' => $request->id, 'requested_by' => $data['requested_by']]);
        \DB::afterCommit(function() use ($request) {
            // Notify only admins for new requests; superuser will see all via UI aggregation
            $admins = User::whereHas('role', function($query) {
                $query->where('name', 'admin');
            })->get();
            Log::info('[FPR] After commit - dispatching notifications', ['request_id' => $request->id, 'admin_count' => $admins->count()]);
            foreach ($admins as $admin) {
                // Acquire a short-lived lock to prevent rapid duplicate sends
                $lockKey = 'notify:fpr:new:'.$admin->id.':'.$request->id;
                $lock = \Cache::lock($lockKey, 10);
                if ($lock->get()) {
                    try {
                        $alreadySent = \DB::table('notifications')
                            ->where('notifiable_id', $admin->id)
                            ->where('type', 'like', 'App\\Notifications\\FeedPurchaseRequestNotification%')
                            ->where(function($q) use ($request) {
                                // Handle JSON stored as number or string; also include a LIKE fallback
                                $q->whereJsonContains('data->request_id', (int) $request->id)
                                  ->orWhereJsonContains('data->request_id', (string) $request->id)
                                  ->orWhere('data', 'like', '%"request_id":'.((int)$request->id).'%')
                                  ->orWhere('data', 'like', '%"request_id":"'.((string)$request->id).'"%');
                            })
                            ->exists();
                        Log::info('[FPR] Notify check', [
                            'request_id' => $request->id,
                            'admin_id' => $admin->id,
                            'already_sent' => $alreadySent,
                            'lock_key' => $lockKey,
                        ]);
                        if (!$alreadySent) {
                            $admin->notify(new FeedPurchaseRequestNotificationImmediate($request, 'new'));
                            Log::info('[FPR] Notification sent', ['request_id' => $request->id, 'admin_id' => $admin->id]);
                        }
                    } finally {
                        optional($lock)->release();
                        Log::info('[FPR] Lock released', ['lock_key' => $lockKey]);
                    }
                } else {
                    Log::warning('[FPR] Could not acquire lock - possible duplicate prevented', ['lock_key' => $lockKey]);
                }
            }
        });
        
        // Redirect based on user role - managers/admins go to index, others go to their request
        $user = Auth::user();
        if ($user && ($user->isAdmin() || $user->isSuperuser() || optional($user->role)->name === 'farmmanager')) {
        return redirect()->route('feed-purchase-requests.index')->with('success', 'Procurement request submitted!');
        } else {
            return redirect()->route('feed-purchase-requests.show', $request->id)->with('success', 'Procurement request submitted!');
        }
    }

    // Show a single purchase request
    public function show(FeedPurchaseRequest $request)
    {
        $user = Auth::user();
        
        // Allow access if user is the requester, or if user is admin/manager/superuser
        if (!$user) {
            abort(403, 'Unauthorized');
        }
        
        $isRequester = $request->requested_by === $user->id;
        $isAdmin = $user->isAdmin() || $user->isSuperuser();
        $isManager = optional($user->role)->name === 'farmmanager';
        
        if (!$isRequester && !$isAdmin && !$isManager) {
            abort(403, 'You can only view your own purchase requests.');
        }
        
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
        
        // Notify the requester about approval
        $request->requestedBy->notify(new FeedPurchaseRequestNotificationImmediate($request, 'approved'));
        
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
        
        // Notify the requester about rejection
        $request->requestedBy->notify(new FeedPurchaseRequestNotificationImmediate($request, 'rejected'));
        
        return back()->with('success', 'Request rejected.');
    }
}
