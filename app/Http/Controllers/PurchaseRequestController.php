<?php

namespace App\Http\Controllers;

use App\Models\PurchaseRequest;
use App\Models\RawMaterial;
use App\Models\Counterparty;
use App\Models\Setting;
use App\Services\Procurement\PurchaseRequestLifecycleService;
use App\Services\Notifications\InAppNotificationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Purchase requests — index, create, store, show, approve, decline,
 * edit, update, destroy. Payment status is owned by posted finance payments.
 *
 * Phase 4 — adds optional raw_material_id + unit at the PR item level,
 * and a supplier_id at the PR header level. The PR creation form now
 * lets users link line items to catalogued raw materials so that
 * receipts can be processed via the receive-against-PR flow.
 *
 * Non-raw-material PR items (services, equipment, ad-hoc supplies) can
 * still be added with raw_material_id left null; those continue to use
 * free-text item_name + description.
 */
class PurchaseRequestController extends Controller
{
    use AuthorizesRequests;

    /**
     * Legacy fallback used by views/routes that bypass policies.
     */
    protected function authorizeApprovalAction(): void
    {
        $this->abortUnlessRole('superuser', 'admin', 'farmmanager');
    }

    protected function canManageRequest(PurchaseRequest $purchaseRequest): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }
        return $user->isSuperuser()
            || $user->isAdmin()
            || $user->isManager()
            || (int) $purchaseRequest->requested_by === (int) $user->id;
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', PurchaseRequest::class);

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

        if ($status !== '' && in_array($status, ['pending', 'approved', 'paid', 'completed', 'declined', 'draft'], true)) {
            $query->where('status', $status);
        }

        $purchaseRequests = $query
            ->latest('request_date')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $stats = [
            'all'      => PurchaseRequest::count(),
            'pending'  => PurchaseRequest::where('status', 'pending')->count(),
            'approved' => PurchaseRequest::where('status', 'approved')->count(),
            'paid'     => PurchaseRequest::where('status', 'paid')->count(),
            'declined' => PurchaseRequest::where('status', 'declined')->count(),
            'completed'=> PurchaseRequest::where('status', 'completed')->count(),
        ];

        return view('feeds.purchase-requests.index', compact(
            'purchaseRequests',
            'stats',
            'search',
            'status'
        ));
    }

    public function create(Request $request)
    {
        $this->authorize('create', PurchaseRequest::class);

        $prefillItems = null;
        $prefillNotes = null;
        if ($request->filled('planning_material')) {
            $input = $request->validate(['planning_material' => ['required', 'integer'],
                'planning_from' => ['required', 'date_format:Y-m-d'], 'planning_to' => ['required', 'date_format:Y-m-d'],
                'stage' => ['nullable', 'string'], 'group_id' => ['nullable', 'integer'], 'room_id' => ['nullable', 'integer'],
                'structure_id' => ['nullable', 'integer'], 'feed_type_id' => ['nullable', 'integer']]);
            $plan = app(\App\Services\Feed\FeedPlanningService::class)->calculate((int) $request->user()->organization_id,
                $input['planning_from'], $input['planning_to'], $input);
            abort_unless($plan['exceptions'] === [], 422, 'Resolve the feed planning notices before creating a purchase request from this forecast.');
            $shortage = collect($plan['materials'])->firstWhere('raw_material_id', (int) $input['planning_material']);
            abort_unless($shortage && $shortage['shortfall_kg'] > 0, 422, 'This planning selection has no current shortage for that material.');
            $material = RawMaterial::where('organization_id', $request->user()->organization_id)->findOrFail($input['planning_material']);
            // Shortfalls are in kg; order whole bags for materials stocked in bags.
            $kgPerUnit = $material->kgPerUnit() ?? 1.0;
            $quantity = $kgPerUnit === 1.0
                ? max(0.01, ceil($shortage['shortfall_kg'] * 100) / 100)
                : (float) ceil($shortage['shortfall_kg'] / $kgPerUnit);
            $prefillItems = [['raw_material_id' => $material->id, 'description' => $material->name,
                'unit' => $material->unit, 'quantity' => $quantity, 'unit_price' => $material->unit_cost]];
            $prefillNotes = 'Feed plan '.$plan['from'].' to '.$plan['to'].'. Required '.$shortage['required_kg'].' kg; available '.$shortage['available_kg'].' kg; shortage '.$shortage['shortfall_kg'].' kg. Review before submitting.';
        }
        return view('feeds.purchase-requests.create', [
            'prefillItems' => $prefillItems, 'prefillNotes' => $prefillNotes,
            'rawMaterials' => RawMaterial::orderBy('name')->get(['id', 'name', 'unit', 'category']),
            'suppliers'    => Counterparty::query()
                ->suppliers()
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, InAppNotificationService $notifications)
    {
        $this->authorize('create', PurchaseRequest::class);
        $request->merge(['payment_arrangement' => $request->input('payment_arrangement', 'after_delivery')]);

        $request->validate([
            'request_date'              => ['required', 'date'],
            'purpose'                   => ['nullable', 'string'],
            'notes'                     => ['nullable', 'string'],
            'supplier_id'               => ['nullable', 'integer', 'exists:counterparties,id'],
            'payment_arrangement'       => ['required', Rule::in(['after_delivery', 'before_delivery', 'deposit_balance', 'supplier_credit'])],
            'deposit_percent'           => ['nullable', 'required_if:payment_arrangement,deposit_balance', 'numeric', 'gt:0', 'lt:100'],
            'items'                     => ['required', 'array', 'min:1'],
            'items.*.description'       => ['required', 'string'],
            'items.*.quantity'          => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price'        => ['required', 'numeric', 'min:0'],

            // Phase 4 — optional raw material linkage + unit per item.
            'items.*.raw_material_id'   => ['nullable', 'integer', 'exists:raw_materials,id'],
            'items.*.unit'              => ['nullable', 'string', 'max:20'],
        ]);

        $purchaseRequest = DB::transaction(function () use ($request) {
            $totalAmount = collect($request->items)->sum(function ($item) {
                return ((float) $item['quantity']) * ((float) $item['unit_price']);
            });

            $purchaseRequest = PurchaseRequest::create([
                'organization_id' => $request->user()->organization_id,
                'request_no'   => 'TMP-' . Str::uuid(),
                'request_date' => $request->request_date,
                'requested_by' => Auth::id(),
                'status'       => 'pending',
                'total_amount' => $totalAmount,
                'purpose'      => $request->purpose,
                'notes'        => $request->notes,
                'supplier_id'  => $request->supplier_id,  // Phase 4
                'payment_arrangement' => $request->payment_arrangement,
                'deposit_percent'     => $request->payment_arrangement === 'deposit_balance' ? $request->deposit_percent : null,
                'payment_progress'    => 'not_paid',
            ]);

            $purchaseRequest->update([
                'request_no' => 'PR-' . now()->format('Y') . '-' .
                    str_pad((string) $purchaseRequest->id, 4, '0', STR_PAD_LEFT),
            ]);

            foreach ($request->items as $item) {
                $totalPrice = ((float) $item['quantity']) * ((float) $item['unit_price']);

                $material = null;
                if (! empty($item['raw_material_id'])) {
                    $material = RawMaterial::find($item['raw_material_id']);
                }

                $purchaseRequest->items()->create([
                    'item_name'        => $material?->name ?? $item['description'],
                    'description'      => $item['description'],
                    'quantity'         => $item['quantity'],
                    'unit_price'       => $item['unit_price'],
                    'total_price'      => $totalPrice,
                    'raw_material_id'  => $material?->id,
                    'unit'             => $item['unit'] ?? $material?->unit,
                ]);
            }

            return $purchaseRequest;
        });

        $notifications->notifyRelevantStaff(
            (int) $purchaseRequest->organization_id,
            'procurement.request_submitted',
            'Purchase request submitted',
            "{$purchaseRequest->request_no} is waiting for review.",
            route('purchase-requests.show', $purchaseRequest),
            context: ['purchase_request_id' => $purchaseRequest->id],
        );

        return redirect()
            ->route('purchase-requests.index')
            ->with('success', 'Purchase request created successfully.');
    }

    public function show(PurchaseRequest $purchaseRequest)
    {
        $this->authorize('view', $purchaseRequest);

        abort_unless($this->canManageRequest($purchaseRequest), 403);

        $purchaseRequest->load([
            'items.rawMaterial',
            'requester',
            'approver',
            'supplier',
            'vendorInvoices' => fn ($query) => $query->latest('id'),
            'payments' => fn ($query) => $query->where('status', \App\Models\Finance\Payment::STATUS_POSTED)->latest('id'),
        ]);

        return view('feeds.purchase-requests.show', compact('purchaseRequest'));
    }

    public function approve(Request $request, PurchaseRequest $purchaseRequest, PurchaseRequestLifecycleService $lifecycle, InAppNotificationService $notifications)
    {
        $this->authorize('approve', $purchaseRequest);
        $this->authorizeApprovalAction();

        if (in_array($purchaseRequest->status, ['approved', 'paid'], true)) {
            return redirect()
                ->route('purchase-requests.show', $purchaseRequest)
                ->with('success', 'Purchase request is already approved.');
        }

        // Phase 4 — block approve if any line has a raw_material_id but
        // no supplier_id on the PR. Receipts can't post to GR/IR without
        // a supplier.
        $hasLinked = $purchaseRequest->items()->whereNotNull('raw_material_id')->exists();
        if ($hasLinked && ! $purchaseRequest->supplier_id) {
            return redirect()
                ->route('purchase-requests.show', $purchaseRequest)
                ->with('error',
                    'This PR has raw-material-linked items but no supplier assigned. '
                    . 'Set a supplier before approving so receipts can be tracked.');
        }
        if ($purchaseRequest->requiresAdvancePayment() && ! $purchaseRequest->supplier_id) {
            return redirect()->route('purchase-requests.show', $purchaseRequest)
                ->with('error', 'Assign the supplier before approving an advance-payment request.');
        }

        $lifecycle->approve($purchaseRequest, $request->user());

        $notifications->notifyRelevantStaff(
            (int) $purchaseRequest->organization_id,
            'procurement.request_approved',
            'Purchase request approved',
            "{$purchaseRequest->request_no} was approved by {$request->user()->name}.",
            route('purchase-requests.show', $purchaseRequest),
            context: ['purchase_request_id' => $purchaseRequest->id],
            recipientUserIds: [(int) $purchaseRequest->requested_by],
        );

        return redirect()
            ->route('purchase-requests.show', $purchaseRequest)
            ->with('success', 'Purchase request approved successfully.');
    }

    public function decline(Request $request, PurchaseRequest $purchaseRequest, PurchaseRequestLifecycleService $lifecycle, InAppNotificationService $notifications)
    {
        $this->authorize('decline', $purchaseRequest);
        $this->authorizeApprovalAction();

        $request->validate([
            'decline_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        if (in_array($purchaseRequest->status, ['approved', 'paid'], true)) {
            return redirect()
                ->route('purchase-requests.show', $purchaseRequest)
                ->with('error', 'Approved or paid requests cannot be declined.');
        }

        $lifecycle->decline($purchaseRequest, $request->user(), $request->decline_reason);

        $notifications->notifyRelevantStaff(
            (int) $purchaseRequest->organization_id,
            'procurement.request_declined',
            'Purchase request declined',
            "{$purchaseRequest->request_no} was declined" . ($request->decline_reason ? ": {$request->decline_reason}" : '.'),
            route('purchase-requests.show', $purchaseRequest),
            'warning',
            ['purchase_request_id' => $purchaseRequest->id],
            [(int) $purchaseRequest->requested_by],
        );

        return redirect()
            ->route('purchase-requests.show', $purchaseRequest)
            ->with('success', 'Purchase request declined successfully.');
    }

    public function complete(Request $request, PurchaseRequest $purchaseRequest, InAppNotificationService $notifications)
    {
        $this->authorize('complete', $purchaseRequest);
        $purchaseRequest->load(['items', 'vendorInvoices']);

        $unreceived = $purchaseRequest->items
            ->whereNotNull('raw_material_id')
            ->contains(fn ($item) => ! $item->is_receipt_closed);
        if ($unreceived) {
            return back()->with('error', 'This request cannot be completed while goods are still outstanding.');
        }

        $postedInvoices = $purchaseRequest->vendorInvoices
            ->where('status', \App\Models\Finance\VendorInvoice::STATUS_POSTED);
        if ($postedInvoices->isEmpty()) {
            return back()->with('error', 'Post the final vendor invoice before completing this request.');
        }
        if ($postedInvoices->contains(fn ($invoice) => bccomp((string) $invoice->balance_due, '0', 2) > 0)) {
            return back()->with('error', 'The posted vendor invoice still has an unpaid balance.');
        }

        $purchaseRequest->update([
            'status' => 'completed',
            'payment_progress' => 'paid',
            'completed_at' => now(),
        ]);

        $notifications->notifyRelevantStaff(
            (int) $purchaseRequest->organization_id,
            'procurement.request_completed',
            'Purchase completed',
            "{$purchaseRequest->request_no} has been fully received, invoiced and paid.",
            route('purchase-requests.show', $purchaseRequest),
            context: ['purchase_request_id' => $purchaseRequest->id],
            recipientUserIds: [(int) $purchaseRequest->requested_by],
        );

        return back()->with('success', "{$purchaseRequest->request_no} is complete.");
    }

    public function edit(PurchaseRequest $purchaseRequest)
    {
        $this->authorize('update', $purchaseRequest);
        abort_unless($this->canManageRequest($purchaseRequest), 403);

        if ($purchaseRequest->status !== 'pending') {
            return redirect()
                ->route('purchase-requests.show', $purchaseRequest)
                ->with('error', 'Only pending purchase requests can be edited.');
        }

        $purchaseRequest->load('items.rawMaterial');

        return view('feeds.purchase-requests.edit', [
            'purchaseRequest' => $purchaseRequest,
            'feedItems'       => Setting::purchaseFeedItems(),
            'rawMaterials'    => RawMaterial::orderBy('name')->get(['id', 'name', 'unit', 'category']),
            'suppliers'       => Counterparty::query()
                ->suppliers()
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorize('update', $purchaseRequest);
        abort_unless($this->canManageRequest($purchaseRequest), 403);

        if ($purchaseRequest->status !== 'pending') {
            return redirect()
                ->route('purchase-requests.show', $purchaseRequest)
                ->with('error', 'Only pending purchase requests can be updated.');
        }

        $request->merge(['payment_arrangement' => $request->input('payment_arrangement', 'after_delivery')]);
        $request->validate([
            'request_date'              => ['required', 'date'],
            'purpose'                   => ['nullable', 'string'],
            'notes'                     => ['nullable', 'string'],
            'supplier_id'               => ['nullable', 'integer', 'exists:counterparties,id'],
            'payment_arrangement'       => ['required', Rule::in(['after_delivery', 'before_delivery', 'deposit_balance', 'supplier_credit'])],
            'deposit_percent'           => ['nullable', 'required_if:payment_arrangement,deposit_balance', 'numeric', 'gt:0', 'lt:100'],
            'items'                     => ['required', 'array', 'min:1'],
            'items.*.description'       => ['required', 'string'],
            'items.*.quantity'          => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price'        => ['required', 'numeric', 'min:0'],
            'items.*.raw_material_id'   => ['nullable', 'integer', 'exists:raw_materials,id'],
            'items.*.unit'              => ['nullable', 'string', 'max:20'],
        ]);

        DB::transaction(function () use ($request, $purchaseRequest) {
            $totalAmount = collect($request->items)->sum(function ($item) {
                return ((float) $item['quantity']) * ((float) $item['unit_price']);
            });

            $purchaseRequest->update([
                'organization_id' => $purchaseRequest->organization_id
                    ?? $request->user()->organization_id,
                'request_date' => $request->request_date,
                'total_amount' => $totalAmount,
                'purpose'      => $request->purpose,
                'notes'        => $request->notes,
                'supplier_id'  => $request->supplier_id,
                'payment_arrangement' => $request->payment_arrangement,
                'deposit_percent'     => $request->payment_arrangement === 'deposit_balance' ? $request->deposit_percent : null,
            ]);

            $purchaseRequest->items()->delete();

            foreach ($request->items as $item) {
                $totalPrice = ((float) $item['quantity']) * ((float) $item['unit_price']);

                $material = null;
                if (! empty($item['raw_material_id'])) {
                    $material = RawMaterial::find($item['raw_material_id']);
                }

                $purchaseRequest->items()->create([
                    'item_name'        => $material?->name ?? $item['description'],
                    'description'      => $item['description'],
                    'quantity'         => $item['quantity'],
                    'unit_price'       => $item['unit_price'],
                    'total_price'      => $totalPrice,
                    'raw_material_id'  => $material?->id,
                    'unit'             => $item['unit'] ?? $material?->unit,
                ]);
            }
        });

        return redirect()
            ->route('purchase-requests.show', $purchaseRequest)
            ->with('success', 'Purchase request updated successfully.');
    }

    public function destroy(PurchaseRequest $purchaseRequest)
    {
        $this->authorize('delete', $purchaseRequest);
        abort_unless($this->canManageRequest($purchaseRequest), 403);

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
