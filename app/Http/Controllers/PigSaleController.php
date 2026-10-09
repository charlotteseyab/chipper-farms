<?php

namespace App\Http\Controllers;

use App\Http\Requests\Sales\RecordPigSaleRequest;
use App\Http\Requests\Sales\UpdatePaymentStatusRequest;
use App\Models\PigManagement;
use App\Models\PigSale;
use App\Models\ActivityLog;
use App\Models\SalesReturn;
use App\Services\Sales\AbortPigSaleService;
use App\Services\Sales\RecordPigSaleService;
use App\Services\Sales\SalesExportService;
use App\Services\Sales\UpdatePigSalePaymentStatusService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

/**
 * Pig sales — form + recording + listing + payment status + exports.
 *
 * Phase E1: every action enforces PigSalePolicy. Tenant isolation is
 * enforced by the policy via EnforcesOrganizationScope.
 */
class PigSaleController extends Controller
{
    use AuthorizesRequests;

    public function form($id)
    {
        $pig = PigManagement::withTrashed()->findOrFail($id);

        // Recording a sale = creating a PigSale.
        $this->authorize('create', PigSale::class);

        if ($pig->sales()->exists()) {
            return back()->withErrors(['sale' => 'This pig has already been sold.']);
        }

        if (! $pig->deaths()->exists()) {
            $eligibility = $pig->sale_eligibility;
            if (! $eligibility['eligible']) {
                return back()->withErrors(['sale' => $eligibility['reason']]);
            }
        }

        // Is the shop currently offering this animal?
        //
        // Selling here is still allowed — a farm-gate sale is a real sale and
        // must not be blocked by the shop — but the person doing it should know
        // the listing exists, because the animal will disappear from it the
        // moment they save. Silence would make the shop look broken later.
        //
        // An animal already COMMITTED to a storefront order is different: a
        // customer has been promised it. That is refused below rather than
        // warned about.
        $listingLink = \App\Models\Storefront\StorefrontListingPig::query()
            ->where('pig_id', $pig->getKey())
            ->whereIn('status', \App\Models\Storefront\StorefrontListingPig::ACTIVE_STATUSES)
            ->with('listing')
            ->first();

        $allocatedTo = \App\Models\Storefront\StorefrontOrderItemPigAllocation::query()
            ->where('pig_id', $pig->getKey())
            ->whereIn('status', \App\Models\Storefront\StorefrontOrderItemPigAllocation::ACTIVE_STATUSES)
            ->with('order')
            ->first();

        if ($allocatedTo !== null) {
            return back()->withErrors(['sale' => sprintf(
                'This pig is committed to storefront order %s. Release or cancel that order before '
                . 'selling the animal directly, or the customer will be promised a pig that is gone.',
                $allocatedTo->order?->reference ?? ('#' . $allocatedTo->storefront_order_id),
            )]);
        }

        $withdrawal = app(\App\Services\Health\WithdrawalStatusService::class)->forPig($pig);

        return view('pigs.sale', compact('pig', 'withdrawal') + ['storefrontListingLink' => $listingLink]);
    }

    public function record(RecordPigSaleRequest $request, RecordPigSaleService $service, $id)
    {
        $pig = PigManagement::withTrashed()->findOrFail($id);

        $this->authorize('create', PigSale::class);

        if ($pig->sales()->exists()) {
            return back()->withErrors(['sale' => 'This pig has already been sold.']);
        }

        if (! $pig->deaths()->exists()) {
            $eligibility = $pig->sale_eligibility;
            if (! $eligibility['eligible']) {
                return back()->withErrors(['sale' => $eligibility['reason']]);
            }
        }

        try {
            $service->execute($pig, $request->user(), $request->validated());
        } catch (\App\Storefront\Services\Exceptions\InventoryConflictException|\App\Services\Health\Exceptions\PigUnderWithdrawalException $exception) {
            return back()->withInput()->withErrors(['sale' => $exception->getMessage()]);
        }

        return redirect()->route('sales.index')
            ->with('success', "Sale of pig {$pig->code} recorded successfully!");
    }

    public function index(Request $request, SalesExportService $exportService)
    {
        $this->authorize('viewAny', PigSale::class);

        $sales = $exportService->buildFilteredQuery($request)->paginate(10);
        $sales->appends($request->query());

        $stats = [
            'total_sales'      => PigSale::count(),
            'total_value'      => PigSale::sum('sale_price'),
            'recent_sales'     => PigSale::recent(30)->count(),
            'recent_value'     => PigSale::recent(30)->sum('sale_price'),
            'pending_payments' => PigSale::pending()->count(),
            'pending_value'    => PigSale::pending()->sum('sale_price'),
            'average_price'    => PigSale::avg('sale_price'),
            'average_weight'   => PigSale::whereNotNull('sale_weight')->avg('sale_weight'),
        ];

        if ($request->ajax()) {
            return response()->json([
                'html' => view('sales._table', compact('sales'))->render(),
                'url' => $request->fullUrl(),
            ]);
        }

        return view('sales.index', compact('sales', 'stats'));
    }

    public function show($id)
    {
        $sale = PigSale::with(['pig', 'soldBy', 'salesReturns.returnedBy'])->findOrFail($id);

        // Per-instance check — verifies user's org matches the sale's org.
        $this->authorize('view', $sale);

        return view('sales.show', compact('sale'));
    }

    public function abort(Request $request, AbortPigSaleService $service, $id)
    {
        $sale = PigSale::with('pig')->findOrFail($id);
        $this->authorize('reverse', $sale);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
            'confirm_abort' => ['accepted'],
        ]);

        try {
            $return = $service->execute($sale, $request->user(), $data['reason']);
        } catch (\InvalidArgumentException $exception) {
            return back()->withErrors(['abort' => $exception->getMessage()]);
        }

        return redirect()->route('sales.show', $sale->id)->with(
            'success',
            "Sale reversal {$return->return_number} has been initiated. Financial reversal processing may continue in the background."
        );
    }

    public function audit($id)
    {
        $sale = PigSale::with(['pig', 'soldBy'])->findOrFail($id);
        $this->authorize('view', $sale);

        $logs = ActivityLog::with('user')
            ->where('module', 'sales')
            ->where('auditable_type', PigSale::class)
            ->where('auditable_id', $sale->id)
            ->where('organization_id', $sale->organization_id)
            ->latest('created_at')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $returns = SalesReturn::with('returnedBy')
            ->where('returnable_type', PigSale::class)
            ->where('returnable_id', $sale->id)
            ->latest('id')
            ->get();

        return view('sales.audit', compact('sale', 'logs', 'returns'));
    }

    public function updatePaymentStatus(
        UpdatePaymentStatusRequest $request,
        UpdatePigSalePaymentStatusService $service,
        $id
    ) {
        $sale = PigSale::findOrFail($id);

        // Specific ability — accountant has it; data entry doesn't.
        $this->authorize('updatePayment', $sale);

        $data = $request->validated();

        try {
            $service->execute(
                sale:        $sale,
                actor:       $request->user(),
                toStatus:    $data['payment_status'],
                notesAppend: $data['notes'] ?? null,
            );
        } catch (\InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['payment_status' => $exception->getMessage()]);
        }

        return back()->with('success', 'Payment status updated successfully!');
    }

    public function exportCsv(Request $request, SalesExportService $service)
    {
        $this->authorize('export', PigSale::class);
        return $service->csv($request);
    }

    public function exportPdf(Request $request, SalesExportService $service)
    {
        $this->authorize('export', PigSale::class);
        return $service->pdf($request);
    }
}
