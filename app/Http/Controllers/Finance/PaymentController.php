<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Counterparty;
use App\Models\Finance\Account;
use App\Models\Finance\Payment;
use App\Models\Finance\PaymentAllocation;
use App\Models\Finance\PaymentRun;
use App\Models\Finance\VendorInvoice;
use App\Models\PaymentMethod;
use App\Models\PurchaseRequest;
use App\Services\Finance\Cash\MoneyAccountService;
use App\Services\Finance\JournalReversalService;
use App\Services\Finance\Payments\PaymentAllocationService;
use App\Services\Finance\Payments\PaymentService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function index(Request $request, CurrentOrganization $currentOrg)
    {
        $orgId = $currentOrg->id();
        $status      = $request->get('status', 'all');
        $supplierId  = $request->get('supplier_id');
        $runId       = $request->get('payment_run_id');
        $reconciled  = $request->get('reconciled', 'all');

        $query = Payment::with([
            'counterparty:id,name,code',
            'paymentMethod:id,code,name',
            'paymentRun:id,run_number',
        ])
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->latest('id');

        if (in_array($status, [
            Payment::STATUS_DRAFT,
            Payment::STATUS_POSTED,
            Payment::STATUS_CANCELLED,
            Payment::STATUS_REVERSED,
        ], true)) {
            $query->where('status', $status);
        } elseif ($status === 'unallocated') {
            $query->unallocated();
        }

        if ($supplierId) {
            $query->where('counterparty_id', $supplierId);
        }
        if ($runId) {
            $query->where('payment_run_id', $runId);
        }
        if ($reconciled === 'yes') {
            $query->reconciled();
        } elseif ($reconciled === 'no') {
            $query->unreconciled();
        }

        $payments = $query->paginate(25)->withQueryString();

        $counts = [
            'total'        => Payment::when($orgId, fn ($q) => $q->where('organization_id', $orgId))->count(),
            'posted'       => Payment::when($orgId, fn ($q) => $q->where('organization_id', $orgId))->posted()->count(),
            'unallocated'  => Payment::when($orgId, fn ($q) => $q->where('organization_id', $orgId))->unallocated()->count(),
            'unreconciled' => Payment::when($orgId, fn ($q) => $q->where('organization_id', $orgId))->unreconciled()->count(),
        ];

        $suppliers = Counterparty::suppliers()->active()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return view('finance.payments.index', compact(
            'payments', 'counts', 'status', 'supplierId', 'runId', 'reconciled', 'suppliers'
        ));
    }

    public function show(Payment $payment, CurrentOrganization $currentOrg)
    {
        $this->ensureInOrg($payment, $currentOrg);

        $payment->load([
            'counterparty.supplierCategory',
            'paymentMethod',
            'sourceAccount',
            'paymentRun',
            'allAllocations.vendorInvoice',
            'allAllocations.createdBy',
            'allAllocations.reversedBy',
            'allAllocations.withholdingJournalBatch',
            'allAllocations.reversedByJournalBatch',
            'journalBatch',
            'reversedByJournalBatch',
            'createdBy',
            'reversedBy',
            'cancelledBy',
            'reconciledBy',
        ]);

        // Load open invoices for the same supplier/currency for the
        // allocation panel.
        $openInvoices = collect();
        if ($payment->isPosted() && $payment->hasUnallocatedAmount()) {
            $openInvoices = VendorInvoice::query()
                ->where('counterparty_id', $payment->counterparty_id)
                ->where('currency_code', $payment->currency_code)
                ->where('status', VendorInvoice::STATUS_POSTED)
                ->where('balance_due', '>', 0)
                ->orderBy('invoice_date')
                ->orderBy('id')
                ->get();
        }

        return view('finance.payments.show', compact('payment', 'openInvoices'));
    }

    public function create(Request $request, CurrentOrganization $currentOrg)
    {
        $orgId = $currentOrg->id();

        $suppliers = Counterparty::suppliers()->active()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'currency_code']);

        // Cash-equivalent methods only — no credit terms for outgoing payment.
        $methods = PaymentMethod::query()
            ->where(function ($q) {
                $q->where('is_cash', true)
                  ->orWhere('is_bank', true)
                  ->orWhere('is_mobile_money', true);
            })
            ->orderBy('display_order')
            ->get();

        $moneyAccounts = app(MoneyAccountService::class)->selectable($orgId);

        // Optional preselect from query.
        $preselectSupplierId = $request->get('supplier_id');
        $preselectVendorInvoice = null;
        $preselectPurchaseRequest = null;
        if ($request->filled('purchase_request_id')) {
            $candidate = PurchaseRequest::query()
                ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
                ->whereIn('status', ['approved', 'paid'])
                ->find($request->integer('purchase_request_id'));
            if ($candidate) {
                $preselectPurchaseRequest = $candidate;
                $preselectSupplierId = $candidate->supplier_id;
            }
        }
        if ($request->filled('vendor_invoice_id')) {
            $vi = VendorInvoice::find($request->get('vendor_invoice_id'));
            if ($vi && $vi->organization_id == $orgId && $vi->isPosted()) {
                $preselectVendorInvoice = $vi;
                $preselectSupplierId = $vi->counterparty_id;
            }
        }

        return view('finance.payments.create', compact(
            'suppliers', 'methods', 'moneyAccounts',
            'preselectSupplierId', 'preselectVendorInvoice'
            , 'preselectPurchaseRequest'
        ));
    }

    public function store(Request $request, PaymentService $service, PaymentAllocationService $allocService, CurrentOrganization $currentOrg)
    {
        $orgId = $currentOrg->id();

        $validated = $request->validate([
            'counterparty_id'    => ['required', 'integer',
                Rule::exists('counterparties', 'id')
                    ->where(fn ($q) => $q->where('organization_id', $orgId))],
            'payment_method_id'  => ['nullable', 'required_without:money_account_id', 'integer', 'exists:payment_methods,id'],
            'money_account_id'   => ['nullable', 'required_without:payment_method_id', 'integer',
                Rule::exists('bank_accounts', 'id')
                    ->where(fn ($q) => $q->where('organization_id', $orgId)
                        ->where('is_active', true))],
            'amount'             => ['required', 'numeric', 'gt:0'],
            'payment_date'       => ['required', 'date'],
            'currency_code'      => ['required', 'string', 'size:3'],
            'source_account_id'  => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'external_reference' => ['nullable', 'string', 'max:100'],
            'description'        => ['nullable', 'string', 'max:500'],
            'notes'              => ['nullable', 'string', 'max:2000'],
            'allocate_to'        => ['nullable', 'in:none,specific,fifo'],
            'vendor_invoice_id'  => ['nullable', 'integer',
                Rule::exists('vendor_invoices', 'id')
                    ->where(fn ($q) => $q->where('organization_id', $orgId))],
            'allocation_amount'  => ['nullable', 'numeric', 'gt:0'],
            'purchase_request_id'=> ['nullable', 'integer', Rule::exists('purchase_requests', 'id')->where(fn ($q) => $q->where('organization_id', $orgId))],
            'purchase_payment_stage' => ['nullable', Rule::in(['advance', 'deposit', 'balance', 'full'])],
        ]);

        $purchaseRequest = ! empty($validated['purchase_request_id'])
            ? PurchaseRequest::findOrFail($validated['purchase_request_id'])
            : null;
        if ($purchaseRequest && (int) $purchaseRequest->supplier_id !== (int) $validated['counterparty_id']) {
            return back()->withInput()->with('error', 'The payment supplier must match the purchase request supplier.');
        }

        $supplier = Counterparty::findOrFail($validated['counterparty_id']);
        $moneyAccounts = app(MoneyAccountService::class);
        if (empty($validated['money_account_id']) && empty($validated['payment_method_id'])) {
            return back()->withInput()->with('error', 'Choose the money account this payment leaves from.');
        }

        $method = ! empty($validated['money_account_id'])
            ? $moneyAccounts->paymentMethodForMoneyAccount((int) $validated['money_account_id'], $orgId)
            : PaymentMethod::findOrFail($validated['payment_method_id']);
        $sourceAccountId = ! empty($validated['money_account_id'])
            ? $moneyAccounts->sourceAccountIdForMoneyAccount((int) $validated['money_account_id'], $orgId)
            : ($validated['source_account_id'] ?? null);

        if ($method->requires_reference && empty($validated['external_reference'])) {
            return back()->withInput()->with('error',
                "Payment method '{$method->name}' requires an external reference (cheque number, transaction ID, etc.)"
            );
        }

        try {
            $payment = $service->createPosted(
                supplier:     $supplier,
                method:       $method,
                amount:       (string) $validated['amount'],
                paymentDate:  CarbonImmutable::parse($validated['payment_date']),
                createdBy:    $request->user(),
                extra: [
                    'currency_code'      => $validated['currency_code'],
                    'source_account_id'  => $sourceAccountId,
                    'external_reference' => $validated['external_reference'] ?? null,
                    'description'        => $validated['description'] ?? null,
                    'notes'              => $validated['notes'] ?? null,
                    'purchase_request_id'=> $purchaseRequest?->id,
                    'purchase_payment_stage' => $validated['purchase_payment_stage'] ?? null,
                ],
            );

            // Optional immediate allocation.
            //
            // payment.amount is NET cash (what leaves the bank). The allocation
            // amount is GROSS (the AP relief — what clears off the bill).
            //
            // For a non-WHT invoice: gross == net, so default = min(net, balance).
            // For a WHT invoice with ratio r = wht_total / total:
            //   net  = gross * (1 - r)
            //   gross_for_net = net / (1 - r)
            // The default GROSS we can allocate from the unallocated NET is:
            //   min(invoice.balance_due, unallocated_net / (1 - r))
            $allocateTo = $validated['allocate_to'] ?? 'none';
            if ($allocateTo === 'specific' && ! empty($validated['vendor_invoice_id'])) {
                $invoice = VendorInvoice::findOrFail($validated['vendor_invoice_id']);

                if (! empty($validated['allocation_amount'])) {
                    $allocAmount = (string) $validated['allocation_amount'];
                } else {
                    $unallocatedNet = $payment->fresh()->unallocatedAmount();
                    $allocAmount    = $this->defaultGrossAllocation($invoice, $unallocatedNet);
                }

                $allocService->allocate(
                    payment:   $payment,
                    invoice:   $invoice,
                    amount:    $allocAmount,
                    createdBy: $request->user(),
                );
            } elseif ($allocateTo === 'fifo') {
                $allocService->autoAllocateFifo($payment, $request->user());
            }
        } catch (\DomainException | \InvalidArgumentException | \App\Finance\Exceptions\PostingException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        if ($purchaseRequest) {
            $purchaseRequest->syncPaymentProgressFromFinance();
        }

        return $purchaseRequest
            ? redirect()->route('purchase-requests.show', $purchaseRequest)->with('success', "Payment {$payment->payment_number} posted and linked to {$purchaseRequest->request_no}.")
            : redirect()
            ->route('finance.payments.show', $payment)
            ->with('success', "Payment {$payment->payment_number} posted.");
    }

    public function allocate(Request $request, Payment $payment, PaymentAllocationService $service, CurrentOrganization $currentOrg)
    {
        $this->ensureInOrg($payment, $currentOrg);

        $validated = $request->validate([
            'vendor_invoice_id' => ['required', 'integer',
                Rule::exists('vendor_invoices', 'id')
                    ->where(fn ($q) => $q->where('organization_id', $payment->organization_id))],
            'amount'            => ['required', 'numeric', 'gt:0'],
            'notes'             => ['nullable', 'string', 'max:500'],
        ]);

        $invoice = VendorInvoice::findOrFail($validated['vendor_invoice_id']);

        try {
            $service->allocate(
                payment:    $payment,
                invoice:    $invoice,
                amount:     (string) $validated['amount'],
                createdBy:  $request->user(),
                notes:      $validated['notes'] ?? null,
            );
            return back()->with('success',
                "Allocated " . number_format((float) $validated['amount'], 2) .
                " to {$invoice->vendor_invoice_number}."
            );
        } catch (\DomainException | \InvalidArgumentException | \App\Finance\Exceptions\PostingException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function autoAllocate(Request $request, Payment $payment, PaymentAllocationService $service, CurrentOrganization $currentOrg)
    {
        $this->ensureInOrg($payment, $currentOrg);

        try {
            $allocations = $service->autoAllocateFifo($payment, $request->user());
            return back()->with('success',
                "Auto-allocated FIFO: " . count($allocations) . " allocation(s) created."
            );
        } catch (\DomainException | \App\Finance\Exceptions\PostingException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reverseAllocation(
        Request $request,
        PaymentAllocation $allocation,
        PaymentAllocationService $service,
        CurrentOrganization $currentOrg,
    ) {
        // Validate org via parent payment.
        $this->ensureInOrg($allocation->payment, $currentOrg);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        try {
            $service->reverse($allocation, $request->user(), $validated['reason']);
            return back()->with('success', "Allocation #{$allocation->id} reversed.");
        } catch (\DomainException | \App\Finance\Exceptions\PostingException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reverse(
        Request $request,
        Payment $payment,
        PaymentAllocationService $allocService,
        JournalReversalService $journalReversal,
        CurrentOrganization $currentOrg,
    ) {
        $this->ensureInOrg($payment, $currentOrg);

        if (! $payment->canReverse()) {
            $reason = $payment->is_backfilled
                ? 'Backfilled payments cannot be reversed (no journal to reverse).'
                : 'Payment is not in a reversible state.';
            return back()->with('error', $reason);
        }

        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        try {
            DB::transaction(function () use ($payment, $allocService, $journalReversal, $request, $validated) {
                $payment = Payment::lockForUpdate()->findOrFail($payment->id);

                // Reverse active allocations first.
                foreach ($payment->allocations as $allocation) {
                    $allocService->reverse(
                        allocation: $allocation,
                        reverser:   $request->user(),
                        reason:     "Payment {$payment->payment_number} reversal: {$validated['reason']}",
                    );
                }

                // Reverse the cash journal.
                $reversalBatch = $journalReversal->reverse(
                    original: $payment->journalBatch,
                    reverser: $request->user(),
                    reason:   "Payment {$payment->payment_number}: {$validated['reason']}",
                );

                $payment->update([
                    'status'                       => Payment::STATUS_REVERSED,
                    'reversed_by_user_id'          => $request->user()->id,
                    'reversed_at'                  => now(),
                    'reversal_reason'              => $validated['reason'],
                    'reversed_by_journal_batch_id' => $reversalBatch->id,
                ]);
            });

            $payment->purchaseRequest?->syncPaymentProgressFromFinance();

            return back()->with('success', "Payment {$payment->payment_number} reversed.");
        } catch (\DomainException | \App\Finance\Exceptions\PostingException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reconcile(Request $request, Payment $payment, CurrentOrganization $currentOrg)
    {
        $this->ensureInOrg($payment, $currentOrg);

        if (! $payment->isPosted()) {
            return back()->with('error', 'Only posted payments can be reconciled.');
        }
        if ($payment->isReconciled()) {
            return back()->with('error', 'Payment is already reconciled.');
        }

        $validated = $request->validate([
            'reconciliation_reference' => ['nullable', 'string', 'max:100'],
        ]);

        $payment->update([
            'reconciled_at'             => now(),
            'reconciled_by_user_id'     => $request->user()->id,
            'reconciliation_reference'  => $validated['reconciliation_reference'] ?? null,
        ]);

        return back()->with('success', "Payment {$payment->payment_number} marked reconciled.");
    }

    public function unreconcile(Request $request, Payment $payment, CurrentOrganization $currentOrg)
    {
        $this->ensureInOrg($payment, $currentOrg);

        if (! $payment->isReconciled()) {
            return back()->with('error', 'Payment is not reconciled.');
        }

        $payment->update([
            'reconciled_at'             => null,
            'reconciled_by_user_id'     => null,
            'reconciliation_reference'  => null,
        ]);

        return back()->with('success', "Payment {$payment->payment_number} unreconciled.");
    }

    /* internals */

    /**
     * Compute the GROSS allocation amount that consumes the given unallocated NET.
     *
     * @param  string  $unallocatedNet  How much net cash the payment still has to disburse.
     * @return string  GROSS amount (what to write to allocation.amount). Bounded by VI balance.
     */
    protected function defaultGrossAllocation(VendorInvoice $invoice, string $unallocatedNet): string
    {
        $balanceDue = (string) $invoice->balance_due;

        if (bccomp($unallocatedNet, '0', 2) <= 0) {
            return '0';
        }

        $viTotal = (string) $invoice->total;
        $whtTot  = (string) ($invoice->withholding_total ?? '0');

        // No WHT: gross == net. Cap at balance.
        if (bccomp($whtTot, '0', 2) <= 0 || bccomp($viTotal, '0', 2) <= 0) {
            return bccomp($unallocatedNet, $balanceDue, 2) < 0 ? $unallocatedNet : $balanceDue;
        }

        // WHT ratio r = wht / total. Gross-equivalent of net = net / (1 - r) = net * total / (total - wht).
        $denom = bcsub($viTotal, $whtTot, 8);
        if (bccomp($denom, '0', 8) <= 0) {
            // Pathological: WHT >= total. Fall back to balance.
            return $balanceDue;
        }
        $grossFromNet = bcdiv(bcmul($unallocatedNet, $viTotal, 8), $denom, 8);
        $grossFromNet = number_format((float) $grossFromNet, 2, '.', '');

        return bccomp($grossFromNet, $balanceDue, 2) < 0 ? $grossFromNet : $balanceDue;
    }

    protected function ensureInOrg(?Payment $payment, CurrentOrganization $currentOrg): void
    {
        if (! $payment) return;
        $orgId = $currentOrg->id();
        if ($orgId !== null && $payment->organization_id !== null
            && (int) $payment->organization_id !== (int) $orgId) {
            abort(403);
        }
    }
}
