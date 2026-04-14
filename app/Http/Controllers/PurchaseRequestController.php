<?php

namespace App\Http\Controllers;

use App\Models\PurchaseRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PurchaseRequestController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->get('search'));
        $status = trim((string) $request->get('status'));

        $query = PurchaseRequest::with(['requester', 'approver']);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('request_no', 'like', "%{$search}%")
                    ->orWhere('purpose', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($status !== '' && in_array($status, ['pending', 'approved', 'paid', 'declined', 'draft'])) {
            $query->where('status', $status);
        }

        $purchaseRequests = $query
            ->latest('request_date')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $stats = [
            'all' => PurchaseRequest::count(),
            'pending' => PurchaseRequest::where('status', 'pending')->count(),
            'approved' => PurchaseRequest::where('status', 'approved')->count(),
            'paid' => PurchaseRequest::where('status', 'paid')->count(),
            'declined' => PurchaseRequest::where('status', 'declined')->count(),
        ];

        return view('feeds.purchase-requests.index', compact(
            'purchaseRequests',
            'stats',
            'search',
            'status'
        ));
    }

    public function create()
    {
        return view('feeds.purchase-requests.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'request_date' => ['required', 'date'],
            'purpose' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($request) {
            $nextId = (PurchaseRequest::max('id') ?? 0) + 1;
            $requestNo = 'PR-' . now()->format('Y') . '-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

            $totalAmount = collect($request->items)->sum(function ($item) {
                return ((float) $item['quantity']) * ((float) $item['unit_price']);
            });

            $purchaseRequest = PurchaseRequest::create([
                'request_no' => $requestNo,
                'request_date' => $request->request_date,
                'requested_by' => Auth::id(),
                'status' => 'pending',
                'total_amount' => $totalAmount,
                'purpose' => $request->purpose,
                'notes' => $request->notes,
            ]);

            foreach ($request->items as $item) {
                $totalPrice = ((float) $item['quantity']) * ((float) $item['unit_price']);

                $purchaseRequest->items()->create([
                    'item_name' => $item['description'],
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $totalPrice,
                ]);
            }
        });

        return redirect()
            ->route('purchase-requests.index')
            ->with('success', 'Purchase request created successfully.');
    }

    public function show(PurchaseRequest $purchaseRequest)
    {
        $purchaseRequest->load(['items', 'requester', 'approver']);

        return view('feeds.purchase-requests.show', compact('purchaseRequest'));
    }

    public function approve(PurchaseRequest $purchaseRequest)
    {
        if (in_array($purchaseRequest->status, ['approved', 'paid'])) {
            return redirect()
                ->route('purchase-requests.show', $purchaseRequest)
                ->with('success', 'Purchase request is already approved.');
        }

        $purchaseRequest->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'decline_reason' => null,
        ]);

        return redirect()
            ->route('purchase-requests.show', $purchaseRequest)
            ->with('success', 'Purchase request approved successfully.');
    }

    public function decline(Request $request, PurchaseRequest $purchaseRequest)
    {
        $request->validate([
            'decline_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        if (in_array($purchaseRequest->status, ['approved', 'paid'])) {
            return redirect()
                ->route('purchase-requests.show', $purchaseRequest)
                ->with('error', 'Approved or paid requests cannot be declined.');
        }

        $purchaseRequest->update([
            'status' => 'declined',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'decline_reason' => $request->decline_reason,
        ]);

        return redirect()
            ->route('purchase-requests.show', $purchaseRequest)
            ->with('success', 'Purchase request declined successfully.');
    }

    public function markAsPaid(Request $request, PurchaseRequest $purchaseRequest)
    {
        if ($purchaseRequest->status !== 'approved') {
            return redirect()
                ->route('purchase-requests.show', $purchaseRequest)
                ->with('error', 'Only approved requests can be marked as paid.');
        }

        $purchaseRequest->update([
            'status' => 'paid',
        ]);

        return redirect()
            ->route('purchase-requests.show', $purchaseRequest)
            ->with('success', 'Purchase request marked as paid successfully.');
    }

    // public function destroy(PurchaseRequest $purchaseRequest)
    // {
    //     $purchaseRequest->delete();

    //     return redirect()
    //         ->route('purchase-requests.index')
    //         ->with('success', 'Purchase request deleted successfully.');
    // }

    public function edit(PurchaseRequest $purchaseRequest)
    {
        if ($purchaseRequest->status !== 'pending') {
            return redirect()
                ->route('purchase-requests.show', $purchaseRequest)
                ->with('error', 'Only pending purchase requests can be edited.');
        }

        $purchaseRequest->load('items');

        return view('feeds.purchase-requests.edit', compact('purchaseRequest'));
    }

    public function update(Request $request, PurchaseRequest $purchaseRequest)
    {
        if ($purchaseRequest->status !== 'pending') {
            return redirect()
                ->route('purchase-requests.show', $purchaseRequest)
                ->with('error', 'Only pending purchase requests can be updated.');
        }

        $request->validate([
            'request_date' => ['required', 'date'],
            'purpose' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($request, $purchaseRequest) {
            $totalAmount = collect($request->items)->sum(function ($item) {
                return ((float) $item['quantity']) * ((float) $item['unit_price']);
            });

            $purchaseRequest->update([
                'request_date' => $request->request_date,
                'total_amount' => $totalAmount,
                'purpose' => $request->purpose,
                'notes' => $request->notes,
            ]);

            $purchaseRequest->items()->delete();

            foreach ($request->items as $item) {
                $totalPrice = ((float) $item['quantity']) * ((float) $item['unit_price']);

                $purchaseRequest->items()->create([
                    'item_name' => $item['description'],
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $totalPrice,
                ]);
            }
        });

        return redirect()
            ->route('purchase-requests.show', $purchaseRequest)
            ->with('success', 'Purchase request updated successfully.');
    }

    public function destroy(PurchaseRequest $purchaseRequest)
    {
        if ($purchaseRequest->status !== 'pending') {
            return redirect()
                ->route('purchase-requests.show', $purchaseRequest)
                ->with('error', 'Only pending purchase requests can be deleted.');
        }

        $purchaseRequest->delete();

        return redirect()
            ->route('purchase-requests.index')
            ->with('success', 'Purchase request deleted successfully.');
    }
}