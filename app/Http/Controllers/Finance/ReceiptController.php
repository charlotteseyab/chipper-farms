<?php

namespace App\Http\Controllers\Finance;

use App\Finance\Exceptions\PostingException;
use App\Http\Controllers\Controller;
use App\Models\Counterparty;
use App\Models\Finance\Invoice;
use App\Models\Finance\Receipt;
use App\Models\Finance\ReceiptAllocation;
use App\Models\PaymentMethod;
use App\Services\Finance\Cash\MoneyAccountService;
use App\Services\Finance\Allocations\AllocationService;
use App\Services\Finance\BankReconciliation\BankReconciliationService;
use App\Services\Finance\JournalReversalService;
use App\Services\Finance\Receipts\ReceiptService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Phase 2c — Receipt controller.
 *
 * Permissions:
 *   index, show:           view_finance
 *   create, store, allocate, reverseAllocation: create_receipt
 *   reverse (full receipt): reverse_journal (reuses existing journal-reversal permission)
 */
class ReceiptController extends Controller
{
    public function index(Request $request, CurrentOrganization $currentOrg)
    {
        $orgId = $currentOrg->id();
        $status = $request->get('status', 'all');
        $unallocatedOnly = (bool) $request->get('unallocated');
        $search = trim((string) $request->get('search'));

        $query = Receipt::with(['counterparty', 'paymentMethod'])
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->latest('id');

        if (in_array($status, ['posted', 'reversed'], true)) {
            $query->where('status', $status);
        }

        if ($unallocatedOnly) {
            $query->withUnallocated();
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('receipt_number', 'like', "%{$search}%")
                  ->orWhere('external_reference', 'like', "%{$search}%");
            });
        }

        $receipts = $query->paginate(25)->withQueryString();

        $counts = [
            'total'       => Receipt::when($orgId, fn ($q) => $q->where('organization_id', $orgId))->count(),
            'unallocated' => Receipt::when($orgId, fn ($q) => $q->where('organization_id', $orgId))->withUnallocated()->count(),
            'reversed'    => Receipt::when($orgId, fn ($q) => $q->where('organization_id', $orgId))->reversed()->count(),
            'backfilled'  => Receipt::when($orgId, fn ($q) => $q->where('organization_id', $orgId))->backfilled()->count(),
        ];

        return view('finance.receipts.index', compact('receipts', 'counts', 'status', 'unallocatedOnly', 'search'));
    }

    public function show(Receipt $receipt, CurrentOrganization $currentOrg)
    {
        $this->ensureInOrg($receipt, $currentOrg);

        $receipt->load([
            'counterparty.category',
            'paymentMethod',
            'allocations.invoice',
            'allocations.createdBy',
            'allocations.reversedBy',
            'journalBatch',
            'reversedByJournalBatch',
            'createdBy',
            'reversedBy',
        ]);

        // Open invoices for the same customer + currency, for the inline allocation form.
        $openInvoices = Invoice::open()
            ->where('counterparty_id', $receipt->counterparty_id)
            ->where('currency_code', $receipt->currency_code)
            ->where('balance_due', '>', 0)
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get();

        return view('finance.receipts.show', compact('receipt', 'openInvoices'));
    }

    public function create(Request $request, CurrentOrganization $currentOrg)
    {
        $orgId = $currentOrg->id();

        $customers = Counterparty::customers()->active()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'currency_code']);

        $paymentMethods = PaymentMethod::active()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->where(function ($q) {
                $q->where('is_cash', true)
                  ->orWhere('is_bank', true)
                  ->orWhere('is_mobile_money', true);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'is_cash', 'is_bank', 'is_mobile_money']);
        $moneyAccounts = app(MoneyAccountService::class)->selectable($orgId);

        // Optional preselection from URL: ?customer=ID
        $preselectedCustomerId = $request->get('customer');
        $openInvoices = collect();
        if ($preselectedCustomerId) {
            $openInvoices = Invoice::open()
                ->where('counterparty_id', $preselectedCustomerId)
                ->where('balance_due', '>', 0)
                ->orderBy('invoice_date')
                ->get();
        }

        return view('finance.receipts.create', compact(
            'customers',
            'paymentMethods',
            'moneyAccounts',
            'preselectedCustomerId',
            'openInvoices',
        ));
    }

    public function store(
        Request $request,
        ReceiptService $receiptService,
        AllocationService $allocationService,
        CurrentOrganization $currentOrg,
    ) {
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
            'receipt_date'       => ['required', 'date'],
            'amount'             => ['required', 'numeric', 'gt:0'],
            'currency_code'      => ['required', 'string', 'size:3'],
            'external_reference' => ['nullable', 'string', 'max:100'],
            'description'        => ['nullable', 'string', 'max:500'],
            'auto_allocate'      => ['nullable', 'boolean'],
            'allocations'                => ['nullable', 'array'],
            'allocations.*.invoice_id'   => ['required_with:allocations', 'integer', 'exists:invoices,id'],
            'allocations.*.amount'       => ['required_with:allocations', 'numeric', 'gt:0'],
        ]);

        $customer = Counterparty::findOrFail($validated['counterparty_id']);
        $moneyAccounts = app(MoneyAccountService::class);
        if (empty($validated['money_account_id']) && empty($validated['payment_method_id'])) {
            return back()->withInput()->with('error', 'Choose the money account this receipt came into.');
        }

        $paymentMethod = ! empty($validated['money_account_id'])
            ? $moneyAccounts->paymentMethodForMoneyAccount((int) $validated['money_account_id'], $orgId)
            : PaymentMethod::findOrFail($validated['payment_method_id']);
        $sourceAccountId = ! empty($validated['money_account_id'])
            ? $moneyAccounts->sourceAccountIdForMoneyAccount((int) $validated['money_account_id'], $orgId)
            : null;

        try {
            $receipt = $receiptService->createPosted(
                customer:       $customer,
                paymentMethod:  $paymentMethod,
                amount:         (string) $validated['amount'],
                receiptDate:    CarbonImmutable::parse($validated['receipt_date']),
                createdBy:      $request->user(),
                extras: [
                    'currency_code'      => $validated['currency_code'],
                    'source_account_id'  => $sourceAccountId,
                    'external_reference' => $validated['external_reference'] ?? null,
                    'description'        => $validated['description'] ?? null,
                ],
            );
        } catch (PostingException | \DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        // Apply allocations.
        try {
            if (! empty($validated['allocations'])) {
                foreach ($validated['allocations'] as $alloc) {
                    if (((float) $alloc['amount']) <= 0) continue;
                    $invoice = Invoice::findOrFail($alloc['invoice_id']);
                    $allocationService->allocate(
                        receipt: $receipt,
                        invoice: $invoice,
                        amount:  (string) $alloc['amount'],
                        user:    $request->user(),
                    );
                }
            } elseif (! empty($validated['auto_allocate'])) {
                $allocationService->autoAllocateFifo($receipt, $request->user());
            }
        } catch (\DomainException $e) {
            return redirect()
                ->route('finance.receipts.show', $receipt)
                ->with('error', "Receipt created, but allocation failed: " . $e->getMessage());
        }

        return redirect()
            ->route('finance.receipts.show', $receipt)
            ->with('success', "Receipt {$receipt->receipt_number} posted to GL.");
    }

    public function storeAllocation(
        Request $request,
        Receipt $receipt,
        AllocationService $service,
        CurrentOrganization $currentOrg,
    ) {
        $this->ensureInOrg($receipt, $currentOrg);

        $validated = $request->validate([
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'amount'     => ['required', 'numeric', 'gt:0'],
            'notes'      => ['nullable', 'string', 'max:500'],
        ]);

        $invoice = Invoice::findOrFail($validated['invoice_id']);

        try {
            $service->allocate(
                receipt: $receipt,
                invoice: $invoice,
                amount:  (string) $validated['amount'],
                user:    $request->user(),
                notes:   $validated['notes'] ?? null,
            );

            return redirect()
                ->route('finance.receipts.show', $receipt)
                ->with('success', "Allocated " . number_format((float) $validated['amount'], 2) . " to invoice {$invoice->invoice_number}.");
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reverseAllocation(
        Request $request,
        ReceiptAllocation $allocation,
        AllocationService $service,
        CurrentOrganization $currentOrg,
    ) {
        if ($allocation->receipt && $allocation->receipt->organization_id !== null) {
            $this->ensureInOrg($allocation->receipt, $currentOrg);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $service->reverse($allocation, $request->user(), $validated['reason']);
            return back()->with('success', "Allocation reversed.");
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reverse(
        Request $request,
        Receipt $receipt,
        AllocationService $allocationService,
        JournalReversalService $reversalService,
        CurrentOrganization $currentOrg,
        BankReconciliationService $bankReconciliationService,
    ) {
        $this->ensureInOrg($receipt, $currentOrg);

        if (! $receipt->canReverse()) {
            return back()->with('error', 'This receipt cannot be reversed.');
        }
        
                // Phase 3e: refuse if the receipt is part of a closed
        // (reconciled or flagged) bank reconciliation.
        $lockingRecons = $bankReconciliationService->lockingReconciliations(
            \App\Models\Finance\BankStatementLine::MATCHED_TYPE_RECEIPT,
            (int) $receipt->id,
        );
        if (! empty($lockingRecons)) {
            $recNumbers = array_map(fn ($r) => $r->reconciliation_number, $lockingRecons);
            return back()->with(
                'error',
                "Cannot reverse receipt {$receipt->receipt_number} — it is part of "
                . "closed bank reconciliation(s): " . implode(', ', $recNumbers)
                . '. Reopen the reconciliation first.',
            );
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            // Step 1: cascade-reverse all active allocations.
            foreach ($receipt->activeAllocations as $allocation) {
                $allocationService->reverse(
                    $allocation,
                    $request->user(),
                    "Cascade from receipt reversal: " . $validated['reason'],
                );
            }

            // Step 2: reverse the cash-side journal batch (skipped for backfilled receipts).
            $reversalBatchId = null;
            if (! $receipt->is_backfilled && $receipt->journalBatch) {
                $reversalBatch = $reversalService->reverse(
                    original: $receipt->journalBatch,
                    reverser: $request->user(),
                    reason:   "Receipt {$receipt->receipt_number}: " . $validated['reason'],
                );
                $reversalBatchId = $reversalBatch->id;
            }

            // Step 3: flip receipt status.
            $receipt->update([
                'status'                       => Receipt::STATUS_REVERSED,
                'reversed_by_journal_batch_id' => $reversalBatchId,
                'reversed_by_user_id'          => $request->user()->id,
                'reversed_at'                  => now(),
                'reversal_reason'              => $validated['reason'],
            ]);

            return redirect()
                ->route('finance.receipts.show', $receipt)
                ->with('success', "Receipt {$receipt->receipt_number} reversed.");
        } catch (PostingException | \DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    protected function ensureInOrg(Receipt $receipt, CurrentOrganization $currentOrg): void
    {
        $orgId = $currentOrg->id();
        if ($orgId !== null && $receipt->organization_id !== null && (int) $receipt->organization_id !== (int) $orgId) {
            abort(403, 'Receipt belongs to a different organisation.');
        }
    }
}
