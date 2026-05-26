<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Counterparty;
use App\Models\Finance\VendorInvoice;
use App\Models\Finance\VendorInvoiceLine;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\RawMaterial;
use App\Models\TaxCode;
use App\Services\Finance\VendorInvoicing\ThreeWayMatchingService;
use App\Services\Finance\VendorInvoicing\VendorInvoiceLineDraft;
use App\Services\Finance\VendorInvoicing\VendorInvoicePostingService;
use App\Services\Finance\VendorInvoicing\VendorInvoiceService;
use App\Services\Finance\JournalReversalService;
use App\Support\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Phase 3b — Vendor invoice CRUD + state machine + posting.
 *
 * Permissions (route level):
 *   index, show:                  view_finance
 *   create, store:                create_vendor_invoice
 *   edit, update, addLine,
 *   removeLine, submit, cancel:   create_vendor_invoice (still draft phase)
 *   approve (post):               approve_vendor_invoice (two-person rule)
 *   reverse:                      reverse_journal
 *
 * Decision 30: approval-required by default. Junior accountants draft;
 * treasury approves and posts. The two-person rule prevents the same
 * user who drafted from also being the one who commits cash to GL.
 */
class VendorInvoiceController extends Controller
{
    public function index(Request $request, CurrentOrganization $currentOrg)
    {
        $orgId = $currentOrg->id();
        $status = $request->get('status', 'all');
        $supplierId = $request->get('supplier_id');
        $matchStatus = $request->get('match_status');

        $query = VendorInvoice::with(['counterparty:id,name,code', 'purchaseRequest:id,request_no'])
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->latest('id');

        if (in_array($status, [
            VendorInvoice::STATUS_DRAFT,
            VendorInvoice::STATUS_PENDING_APPROVAL,
            VendorInvoice::STATUS_POSTED,
            VendorInvoice::STATUS_CANCELLED,
            VendorInvoice::STATUS_REVERSED,
        ], true)) {
            $query->where('status', $status);
        } elseif ($status === 'open') {
            $query->where('status', VendorInvoice::STATUS_POSTED)
                  ->where('balance_due', '>', 0);
        }

        if ($supplierId) {
            $query->where('counterparty_id', $supplierId);
        }
        if ($matchStatus) {
            $query->where('match_status', $matchStatus);
        }

        $invoices = $query->paginate(25)->withQueryString();

        $counts = [
            'total'             => VendorInvoice::when($orgId, fn ($q) => $q->where('organization_id', $orgId))->count(),
            'draft'             => VendorInvoice::when($orgId, fn ($q) => $q->where('organization_id', $orgId))->draft()->count(),
            'pending_approval'  => VendorInvoice::when($orgId, fn ($q) => $q->where('organization_id', $orgId))->pendingApproval()->count(),
            'posted'            => VendorInvoice::when($orgId, fn ($q) => $q->where('organization_id', $orgId))->posted()->count(),
            'open'              => VendorInvoice::when($orgId, fn ($q) => $q->where('organization_id', $orgId))->open()->count(),
        ];

        $suppliers = Counterparty::suppliers()->active()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return view('finance.vendor-invoices.index', compact('invoices', 'counts', 'status', 'supplierId', 'matchStatus', 'suppliers'));
    }

    public function show(VendorInvoice $invoice, CurrentOrganization $currentOrg, ThreeWayMatchingService $matching)
    {
        $this->ensureInOrg($invoice, $currentOrg);

        $invoice->load([
            'counterparty.supplierCategory',
            'lines.account',
            'lines.taxCode',
            'lines.withholdingTaxCode',
            'lines.purchaseRequestItem',
            'lines.rawMaterial',
            'purchaseRequest',
            'journalBatch',
            'reversedByJournalBatch',
            'createdBy',
            'submittedForApprovalBy',
            'approvedBy',
            'reversedBy',
            'cancelledBy',
        ]);

        // Re-evaluate matching on every show — variances may shift if
        // operations adjusts the PR or receipts after the VI was created.
        // Phase 4+ may move this to event-driven; for now pull-on-show is fine.
        if ($invoice->isDraft() || $invoice->isPendingApproval()) {
            $matching->evaluateAndSave($invoice);
            $invoice->refresh();
        }

        return view('finance.vendor-invoices.show', compact('invoice'));
    }

    public function create(Request $request, CurrentOrganization $currentOrg)
    {
        $orgId = $currentOrg->id();

        $suppliers = Counterparty::suppliers()->active()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'currency_code', 'supplier_category_id', 'withholding_percent', 'withholding_exempt']);

        $taxCodes = TaxCode::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->where('is_active', true)
            ->whereIn('applies_to', ['purchase', 'both'])
            ->where('is_withholding', false)
            ->orderBy('display_order')->orderBy('code')
            ->get(['id', 'code', 'name', 'rate', 'is_recoverable']);

        $whtCodes = TaxCode::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->where('is_active', true)
            ->where('is_withholding', true)
            ->orderBy('display_order')->orderBy('code')
            ->get(['id', 'code', 'name', 'rate']);

        $rawMaterials = RawMaterial::orderBy('name')->get(['id', 'name', 'unit', 'unit_cost']);

        // Optional pre-selection from PR
        $pr = null;
        $prItems = collect();
        if ($request->filled('purchase_request_id')) {
            $pr = PurchaseRequest::with('items')->find($request->get('purchase_request_id'));
            if ($pr && $pr->organization_id == $orgId) {
                $prItems = $pr->items;
            } else {
                $pr = null;
            }
        }

        return view('finance.vendor-invoices.create', compact(
            'suppliers', 'taxCodes', 'whtCodes', 'rawMaterials', 'pr', 'prItems'
        ));
    }

    public function store(Request $request, VendorInvoiceService $service, CurrentOrganization $currentOrg)
    {
        $orgId = $currentOrg->id();

        $validated = $request->validate([
            'counterparty_id'          => ['required', 'integer',
                Rule::exists('counterparties', 'id')
                    ->where(fn ($q) => $q->where('organization_id', $orgId))],
            'invoice_date'             => ['required', 'date'],
            'due_date'                 => ['nullable', 'date'],
            'received_at'              => ['nullable', 'date'],
            'supplier_invoice_number'  => ['nullable', 'string', 'max:100'],
            'currency_code'            => ['required', 'string', 'size:3'],
            'purchase_request_id'      => ['nullable', 'integer',
                Rule::exists('purchase_requests', 'id')
                    ->where(fn ($q) => $q->where('organization_id', $orgId))],
            'description'              => ['nullable', 'string', 'max:500'],
            'notes'                    => ['nullable', 'string', 'max:2000'],
            'lines'                    => ['required', 'array', 'min:1'],
            'lines.*.description'      => ['required', 'string', 'max:500'],
            'lines.*.quantity'         => ['required', 'numeric', 'gt:0'],
            'lines.*.unit'             => ['nullable', 'string', 'max:30'],
            'lines.*.unit_cost'        => ['required', 'numeric', 'gte:0'],
            'lines.*.raw_material_id'  => ['nullable', 'integer', 'exists:raw_materials,id'],
            'lines.*.purchase_request_item_id' => ['nullable', 'integer', 'exists:purchase_request_items,id'],
            'lines.*.tax_code_id'      => ['nullable', 'integer', 'exists:tax_codes,id'],
            'lines.*.tax_rate'         => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.withholding_tax_code_id' => ['nullable', 'integer', 'exists:tax_codes,id'],
            'lines.*.withholding_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.account_id'       => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
        ]);

        $supplier = Counterparty::findOrFail($validated['counterparty_id']);

        try {
            $lineDrafts = [];
            foreach ($validated['lines'] as $lineData) {
                $lineDrafts[] = new VendorInvoiceLineDraft(
                    description:            $lineData['description'],
                    quantity:               (string) $lineData['quantity'],
                    unitCost:               (string) $lineData['unit_cost'],
                    unit:                   $lineData['unit'] ?? null,
                    rawMaterialId:          $lineData['raw_material_id'] ?? null,
                    purchaseRequestItemId:  $lineData['purchase_request_item_id'] ?? null,
                    taxRate:                (string) ($lineData['tax_rate'] ?? '0'),
                    taxCodeId:              $lineData['tax_code_id'] ?? null,
                    withholdingRate:        (string) ($lineData['withholding_rate'] ?? '0'),
                    withholdingTaxCodeId:   $lineData['withholding_tax_code_id'] ?? null,
                    accountId:              $lineData['account_id'] ?? null,
                );
            }

            $invoice = $service->createDraft(
                supplier:   $supplier,
                headerData: [
                    'invoice_date'            => $validated['invoice_date'],
                    'due_date'                => $validated['due_date'] ?? null,
                    'received_at'             => $validated['received_at'] ?? null,
                    'supplier_invoice_number' => $validated['supplier_invoice_number'] ?? null,
                    'currency_code'           => $validated['currency_code'],
                    'purchase_request_id'     => $validated['purchase_request_id'] ?? null,
                    'description'             => $validated['description'] ?? null,
                    'notes'                   => $validated['notes'] ?? null,
                ],
                lineDrafts: $lineDrafts,
                createdBy:  $request->user(),
            );
        } catch (\DomainException | \InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('finance.vendor-invoices.show', $invoice)
            ->with('success', "Vendor invoice {$invoice->vendor_invoice_number} created in draft.");
    }

    public function submitForApproval(
        Request $request,
        VendorInvoice $invoice,
        VendorInvoiceService $service,
        CurrentOrganization $currentOrg,
    ) {
        $this->ensureInOrg($invoice, $currentOrg);

        try {
            $service->submitForApproval($invoice, $request->user());
            return back()->with('success', "Vendor invoice {$invoice->vendor_invoice_number} submitted for approval.");
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function post(
        Request $request,
        VendorInvoice $invoice,
        VendorInvoicePostingService $postingService,
        CurrentOrganization $currentOrg,
    ) {
        $this->ensureInOrg($invoice, $currentOrg);

        // Two-person rule: the approver MUST be different from the creator,
        // unless the org has explicitly disabled this control.
        $enforceTwoPerson = (bool) config('finance.vendor_invoices.enforce_two_person_rule', true);
        if ($enforceTwoPerson && $invoice->created_by_user_id == $request->user()->id) {
            return back()->with('error',
                'Two-person rule: the user who created this invoice cannot also approve it. ' .
                'Another user with approve_vendor_invoice permission must approve.'
            );
        }

        try {
            $postingService->post($invoice, $request->user());
            return back()->with('success', "Vendor invoice {$invoice->vendor_invoice_number} posted to GL.");
        } catch (\DomainException | \App\Finance\Exceptions\PostingException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function cancel(
        Request $request,
        VendorInvoice $invoice,
        VendorInvoiceService $service,
        CurrentOrganization $currentOrg,
    ) {
        $this->ensureInOrg($invoice, $currentOrg);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $service->cancel($invoice, $request->user(), $validated['reason']);
            return redirect()
                ->route('finance.vendor-invoices.index')
                ->with('success', "Vendor invoice {$invoice->vendor_invoice_number} cancelled.");
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reverse(
        Request $request,
        VendorInvoice $invoice,
        JournalReversalService $reversalService,
        CurrentOrganization $currentOrg,
    ) {
        $this->ensureInOrg($invoice, $currentOrg);

        if (! $invoice->canReverse()) {
            return back()->with('error',
                'Vendor invoice cannot be reversed. ' .
                ($invoice->amount_paid > 0
                    ? 'Reverse the payments allocated to it first.'
                    : 'Already reversed or not in posted status.')
            );
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            DB::transaction(function () use ($invoice, $reversalService, $request, $validated) {
                $invoice = VendorInvoice::lockForUpdate()->findOrFail($invoice->id);

                $reversalBatch = $reversalService->reverse(
                    original: $invoice->journalBatch,
                    reverser: $request->user(),
                    reason:   "Vendor Invoice {$invoice->vendor_invoice_number}: {$validated['reason']}",
                );

                $invoice->update([
                    'status'                       => VendorInvoice::STATUS_REVERSED,
                    'reversed_by_journal_batch_id' => $reversalBatch->id,
                    'reversed_by_user_id'          => $request->user()->id,
                    'reversed_at'                  => now(),
                    'reversal_reason'              => $validated['reason'],
                ]);
            });
            return back()->with('success', "Vendor invoice {$invoice->vendor_invoice_number} reversed.");
        } catch (\DomainException | \App\Finance\Exceptions\PostingException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /* internals */

    protected function ensureInOrg(VendorInvoice $invoice, CurrentOrganization $currentOrg): void
    {
        $orgId = $currentOrg->id();
        if ($orgId !== null && $invoice->organization_id !== null && (int) $invoice->organization_id !== (int) $orgId) {
            abort(403);
        }
    }
}
