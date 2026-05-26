<?php

namespace App\Http\Controllers;

use App\Http\Requests\Sales\RecordPigSaleRequest;
use App\Http\Requests\Sales\UpdatePaymentStatusRequest;
use App\Models\PigManagement;
use App\Models\PigSale;
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
        $pig = PigManagement::findOrFail($id);

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

        return view('pigs.sale', compact('pig'));
    }

    public function record(RecordPigSaleRequest $request, RecordPigSaleService $service, $id)
    {
        $pig = PigManagement::findOrFail($id);

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

        $service->execute($pig, $request->user(), $request->validated());

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

        return view('sales.index', compact('sales', 'stats'));
    }

    public function show($id)
    {
        $sale = PigSale::with(['pig', 'soldBy'])->findOrFail($id);

        // Per-instance check — verifies user's org matches the sale's org.
        $this->authorize('view', $sale);

        return view('sales.show', compact('sale'));
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

        $service->execute(
            sale:        $sale,
            actor:       $request->user(),
            toStatus:    $data['payment_status'],
            notesAppend: $data['notes'] ?? null,
        );

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
