<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Finance\GrIrEntry;
use App\Support\CurrentOrganization;
use Illuminate\Http\Request;

/**
 * Phase 4 — GR/IR ageing report.
 *
 * Shows outstanding GR/IR entries by supplier, with age buckets.
 * Reads from the gr_ir_entries operational mirror.
 *
 * Permission: view_gr_ir_ageing.
 */
class GrIrAgeingController extends Controller
{
    /** Age buckets in days. */
    public const BUCKETS = [
        ['label' => '0-7',     'min' => 0,   'max' => 7],
        ['label' => '8-30',    'min' => 8,   'max' => 30],
        ['label' => '31-60',   'min' => 31,  'max' => 60],
        ['label' => '61-90',   'min' => 61,  'max' => 90],
        ['label' => '90+',     'min' => 91,  'max' => null],
    ];

    public function __construct(protected CurrentOrganization $currentOrganization)
    {
        $this->middleware('auth');
        $this->middleware('permission:view_gr_ir_ageing');
    }

    public function index(Request $request)
    {
        $orgId = $this->currentOrganization->id();

        $outstanding = GrIrEntry::query()
            ->with(['supplier', 'rawMaterial', 'purchaseRequestItem.purchaseRequest', 'stockLog'])
            ->when(
                $orgId !== null,
                fn ($q) => $q->where('organization_id', $orgId),
                fn ($q) => $q->whereNull('organization_id'),
            )
            ->where('status', GrIrEntry::STATUS_OUTSTANDING)
            ->orderBy('occurred_at')
            ->get();

        // Group by supplier, compute buckets.
        $bySupplier = $outstanding->groupBy('supplier_id')->map(function ($entries) {
            $supplierName = optional($entries->first()->supplier)->name ?? '(unknown supplier)';
            $supplierId   = (int) $entries->first()->supplier_id;

            $buckets = [];
            foreach (self::BUCKETS as $b) {
                $buckets[$b['label']] = '0';
            }

            $totalOutstanding = '0';
            foreach ($entries as $entry) {
                $age = $entry->ageDays() ?? 0;
                $amount = (string) $entry->receipt_amount;
                $totalOutstanding = bcadd($totalOutstanding, $amount, 4);

                foreach (self::BUCKETS as $b) {
                    if ($age >= $b['min'] && ($b['max'] === null || $age <= $b['max'])) {
                        $buckets[$b['label']] = bcadd($buckets[$b['label']], $amount, 4);
                        break;
                    }
                }
            }

            return [
                'supplier_id'        => $supplierId,
                'supplier_name'      => $supplierName,
                'entries_count'      => $entries->count(),
                'total_outstanding'  => $totalOutstanding,
                'buckets'            => $buckets,
            ];
        })->values();

        return view('inventory.gr-ir-ageing.index', [
            'bySupplier' => $bySupplier,
            'buckets'    => self::BUCKETS,
        ]);
    }

    public function show(Request $request, int $supplierId)
    {
        $orgId = $this->currentOrganization->id();

        $entries = GrIrEntry::query()
            ->with(['rawMaterial', 'purchaseRequestItem.purchaseRequest', 'stockLog', 'clearedByVendorInvoiceLine.vendorInvoice', 'supplier'])
            ->when(
                $orgId !== null,
                fn ($q) => $q->where('organization_id', $orgId),
                fn ($q) => $q->whereNull('organization_id'),
            )
            ->where('supplier_id', $supplierId)
            ->where('status', GrIrEntry::STATUS_OUTSTANDING)
            ->orderBy('occurred_at')
            ->get();

        if ($entries->isEmpty()) {
            abort(404, 'No outstanding GR/IR entries for this supplier.');
        }

        return view('inventory.gr-ir-ageing.show', [
            'supplier' => $entries->first()->supplier,
            'entries'  => $entries,
        ]);
    }
}
