<?php

namespace App\Http\Controllers;

use App\Models\PurchaseRequest;
use App\Models\RawMaterial;
use App\Models\Counterparty;
use App\Services\Procurement\PurchaseRequestLifecycleService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Purchase requests — index, create, store, show, approve, decline,
 * markAsPaid, edit, update, destroy.
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

        if ($status !== '' && in_array($status, ['pending', 'approved', 'paid', 'declined', 'draft'], true)) {
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
        $this->authorize('create', PurchaseRequest::class);

        return view('feeds.purchase-requests.create', [
            'rawMaterials' => RawMaterial::orderBy('name')->get(['id', 'name', 'unit', 'category']),
            'suppliers'    => Counterparty::query()
                ->suppliers()
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', PurchaseRequest::class);

        $request->validate([
            'request_date'              => ['required', 'date'],
            'purpose'                   => ['nullable', 'string'],
            'notes'                     => ['nullable', 'string'],
            'supplier_id'               => ['nullable', 'integer', 'exists:counterparties,id'],
            'items'                     => ['required', 'array', 'min:1'],
            'items.*.description'       => ['required', 'string'],
            'items.*.quantity'          => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price'        => ['required', 'numeric', 'min:0'],

            // Phase 4 — optional raw material linkage + unit per item.
            'items.*.raw_material_id'   => ['nullable', 'integer', 'exists:raw_materials,id'],
            'items.*.unit'              => ['nullable', 'string', 'max:20'],
        ]);

        DB::transaction(function () use ($request) {
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
        });

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
        ]);

        return view('feeds.purchase-requests.show', compact('purchaseRequest'));
    }

    public function approve(Request $request, PurchaseRequest $purchaseRequest, PurchaseRequestLifecycleService $lifecycle)
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

        $lifecycle->approve($purchaseRequest, $request->user());

        return redirect()
            ->route('purchase-requests.show', $purchaseRequest)
            ->with('success', 'Purchase request approved successfully.');
    }

    public function decline(Request $request, PurchaseRequest $purchaseRequest, PurchaseRequestLifecycleService $lifecycle)
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

        return redirect()
            ->route('purchase-requests.show', $purchaseRequest)
            ->with('success', 'Purchase request declined successfully.');
    }

    public function markAsPaid(Request $request, PurchaseRequest $purchaseRequest, PurchaseRequestLifecycleService $lifecycle)
    {
        $this->authorize('markPaid', $purchaseRequest);

        if ($purchaseRequest->status !== 'approved') {
            return redirect()
                ->route('purchase-requests.show', $purchaseRequest)
                ->with('error', 'Only approved requests can be marked as paid.');
        }

        $lifecycle->markAsPaid($purchaseRequest, $request->user());

        return redirect()
            ->route('purchase-requests.show', $purchaseRequest)
            ->with('success', 'Purchase request marked as paid successfully.');
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

        $request->validate([
            'request_date'              => ['required', 'date'],
            'purpose'                   => ['nullable', 'string'],
            'notes'                     => ['nullable', 'string'],
            'supplier_id'               => ['nullable', 'integer', 'exists:counterparties,id'],
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
