<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\FeedSale;
use App\Models\Finance\Invoice;
use App\Models\PaymentMethod;
use App\Models\Finance\Receipt;
use App\Models\PigSale;
use App\Services\Finance\Allocations\AllocationService;
use App\Services\Finance\Receipts\ReceiptService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Finance → Sales & Money In (Phase 7, blueprint §28; closes IMP-027).
 *
 * WHAT WAS MISSING
 * ----------------
 * Blueprint §28 asks for "record payment: one button from invoice/customer/
 * order" and "allocate payment: automatic by default". Neither existed. To take
 * money against an invoice a farm had to open Receipts, start a new receipt,
 * pick the customer, type the amount, remember to tick auto-allocate, save, and
 * hope FIFO landed it on the invoice they had in mind. Five screens and a piece
 * of trust for what an owner thinks of as "Ama paid me".
 *
 * Phase 5 also deferred the Money In / Money Out views here (IMP-027).
 *
 * ONE BUTTON, TWO EXISTING SERVICES
 * ---------------------------------
 * `recordPayment()` is an orchestration layer and nothing more. It calls
 * `ReceiptService::createPosted()` — which owns numbering, the GL posting and
 * the cash-instrument check — and then `AllocationService::allocate()`, which
 * owns the row locks, the counterparty and currency checks and the aggregate
 * updates. It posts no journal of its own. A second posting path for cash
 * received is how one farm ends up with two versions of its bank balance.
 *
 * ALLOCATION IS TO *THIS* INVOICE, NOT FIFO
 * -----------------------------------------
 * The receipt screen's auto-allocate applies FIFO across the customer's open
 * invoices. That is right when someone hands over money against no particular
 * bill. It is wrong here: the farmer pressed the button on a specific row, and
 * silently paying an older invoice instead would be the software disagreeing
 * with the person about what just happened. So the allocation is explicit and
 * targeted, which is also what makes the button honest about its effect.
 *
 * OVERPAYMENT GOES THROUGH THE FULL SCREEN
 * ----------------------------------------
 * The amount is capped at the invoice balance. Money in excess of a bill is a
 * customer credit — a real thing this system models through unallocated
 * receipts, and a decision with consequences (it sits on the customer's ledger
 * until someone applies it). It should not be a rounding accident on a
 * one-button form. The full receipt screen remains one click away.
 */
class MoneyInController extends Controller
{
    public function __construct(
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    public function index(Request $request): View
    {
        $organizationId = $this->currentOrganization->requireId();

        // Unpaid posted invoices, oldest first — the ones a farm is chasing.
        $awaitingPayment = Invoice::query()
            ->where('organization_id', $organizationId)
            ->posted()
            ->where('balance_due', '>', 0)
            ->with('counterparty')
            ->orderBy('due_date')
            ->limit(50)
            ->get();

        // Totals in bcmath from the same rows the lists show, so the headline
        // figure and the list underneath it cannot disagree.
        $owed = '0.00';
        foreach ($awaitingPayment as $invoice) {
            $owed = bcadd($owed, (string) $invoice->balance_due, 2);
        }

        $monthStart = CarbonImmutable::today()->startOfMonth();
        $receivedThisMonth = $this->sumMoneyInSince($organizationId, $monthStart->toDateString());
        $recentMoneyIn = $this->recentMoneyIn($organizationId);

        return view('finance.money-in.index', [
            'awaitingPayment'   => $awaitingPayment,
            'recentMoneyIn'     => $recentMoneyIn,
            'owed'              => $owed,
            'receivedThisMonth' => $receivedThisMonth,
            // Cash-equivalent instruments only, organisation-scoped — the same
            // filter the receipt screen applies, and the same one
            // ReceiptService enforces when it refuses a non-cash method.
            'paymentMethods'    => PaymentMethod::active()
                ->where('organization_id', $organizationId)
                ->where(function ($q) {
                    $q->where('is_cash', true)
                        ->orWhere('is_bank', true)
                        ->orWhere('is_mobile_money', true);
                })
                ->orderBy('name')
                ->get(),
            'canRecord' => $request->user()?->hasPermission('create_receipt') ?? false,
        ]);
    }

    /**
     * Money-in is broader than receipts: direct paid sales and posted cash
     * invoices move cash immediately and may never create a receipt row.
     */
    protected function recentMoneyIn(int $organizationId): Collection
    {
        $receipts = Receipt::query()
            ->where('organization_id', $organizationId)
            ->where('status', Receipt::STATUS_POSTED)
            ->with(['counterparty', 'paymentMethod'])
            ->orderByDesc('receipt_date')
            ->orderByDesc('id')
            ->limit(15)
            ->get()
            ->map(fn (Receipt $receipt) => [
                'type' => 'Receipt',
                'name' => $receipt->counterparty?->name ?? 'Customer receipt',
                'reference' => $receipt->receipt_number,
                'date' => $receipt->receipt_date,
                'method' => $receipt->paymentMethod?->name,
                'amount' => (string) $receipt->amount,
                'href' => route('finance.receipts.show', $receipt),
            ]);

        $cashInvoices = Invoice::query()
            ->where('organization_id', $organizationId)
            ->posted()
            ->where('is_cash_invoice', true)
            ->with(['counterparty', 'paymentMethod'])
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->limit(15)
            ->get()
            ->map(fn (Invoice $invoice) => [
                'type' => 'Cash sale',
                'name' => $invoice->counterparty?->name ?? 'Cash customer',
                'reference' => $invoice->invoice_number,
                'date' => $invoice->invoice_date,
                'method' => $invoice->paymentMethod?->name,
                'amount' => (string) $invoice->total,
                'href' => route('finance.invoices.show', $invoice),
            ]);

        $pigSales = PigSale::query()
            ->where('organization_id', $organizationId)
            ->where('payment_status', 'paid')
            ->where('is_posted', true)
            ->whereNull('invoice_id')
            ->with(['customer', 'paymentMethodRef'])
            ->orderByDesc('sale_date')
            ->orderByDesc('id')
            ->limit(15)
            ->get()
            ->map(fn (PigSale $sale) => [
                'type' => 'Pig sale',
                'name' => $sale->customer?->name ?: ($sale->buyer_name ?: 'Pig sale customer'),
                'reference' => $sale->document_number ?: ('Sale #' . $sale->id),
                'date' => $sale->sale_date,
                'method' => $sale->paymentMethodRef?->name ?: $this->displayPaymentMethod($sale->payment_method),
                'amount' => (string) $sale->sale_price,
                'href' => route('sales.show', $sale->id),
            ]);

        $feedSales = FeedSale::query()
            ->where('organization_id', $organizationId)
            ->where('payment_status', FeedSale::PAYMENT_PAID)
            ->where('revenue_posting_status', FeedSale::POST_POSTED)
            ->with('counterparty')
            ->orderByDesc('sale_date')
            ->orderByDesc('id')
            ->limit(15)
            ->get()
            ->map(fn (FeedSale $sale) => [
                'type' => 'Feed sale',
                'name' => $sale->counterparty?->name ?: ($sale->buyer_name ?: 'Feed sale customer'),
                'reference' => 'Feed Sale #' . $sale->id,
                'date' => $sale->sale_date,
                'method' => $this->displayPaymentMethod($sale->payment_method),
                'amount' => bcadd((string) $sale->sale_total, (string) ($sale->tax_amount ?? '0'), 2),
                'href' => null,
            ]);

        return $receipts
            ->concat($cashInvoices)
            ->concat($pigSales)
            ->concat($feedSales)
            ->sortByDesc(fn (array $item) => optional($item['date'])->format('Y-m-d') . ' ' . $item['reference'])
            ->values()
            ->take(15);
    }

    protected function sumMoneyInSince(int $organizationId, string $startDate): string
    {
        $total = '0.00';

        foreach ([
            Receipt::query()
                ->where('organization_id', $organizationId)
                ->where('status', Receipt::STATUS_POSTED)
                ->where('receipt_date', '>=', $startDate)
                ->pluck('amount'),

            Invoice::query()
                ->where('organization_id', $organizationId)
                ->posted()
                ->where('is_cash_invoice', true)
                ->where('invoice_date', '>=', $startDate)
                ->pluck('total'),

            PigSale::query()
                ->where('organization_id', $organizationId)
                ->where('payment_status', 'paid')
                ->where('is_posted', true)
                ->whereNull('invoice_id')
                ->where('sale_date', '>=', $startDate)
                ->pluck('sale_price'),
        ] as $amounts) {
            foreach ($amounts as $amount) {
                $total = bcadd($total, (string) $amount, 2);
            }
        }

        $feedSales = FeedSale::query()
            ->where('organization_id', $organizationId)
            ->where('payment_status', FeedSale::PAYMENT_PAID)
            ->where('revenue_posting_status', FeedSale::POST_POSTED)
            ->where('sale_date', '>=', $startDate)
            ->get(['sale_total', 'tax_amount']);

        foreach ($feedSales as $sale) {
            $total = bcadd($total, bcadd((string) $sale->sale_total, (string) ($sale->tax_amount ?? '0'), 2), 2);
        }

        return $total;
    }

    protected function displayPaymentMethod(?string $method): ?string
    {
        if ($method === null || trim($method) === '') {
            return null;
        }

        return str((string) $method)->replace(['_', '-'], ' ')->title()->toString();
    }

    /**
     * Record money received against one invoice.
     */
    public function recordPayment(
        Request $request,
        Invoice $invoice,
        ReceiptService $receipts,
        AllocationService $allocations,
    ): RedirectResponse {
        $organizationId = $this->currentOrganization->requireId();

        // Route-model binding resolves by id alone. 404 rather than 403 so
        // another organisation's invoice stays undiscoverable (prompt §118).
        abort_unless((int) $invoice->organization_id === $organizationId, 404);

        $balance = bcadd((string) $invoice->balance_due, '0', 2);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', "max:{$balance}"],
            'payment_method_id' => [
                'required', 'integer',
                // Organisation-scoped: a posted id from another farm's method
                // list would post this farm's cash to the wrong account.
                Rule::exists('payment_methods', 'id')
                    ->where('is_active', true)
                    ->where('organization_id', $organizationId),
            ],
            'received_on' => ['nullable', 'date'],
            'reference'   => ['nullable', 'string', 'max:100'],
        ], [
            'amount.max' => "That is more than the :max still owed on this invoice. "
                . 'Record the extra through the full receipt screen so it lands on the customer as credit.',
        ]);

        $customer = $invoice->counterparty;

        if ($customer === null) {
            return back()->with('error', 'This invoice has no customer to receipt against.');
        }

        // The engine refuses these too — checked here so the farmer gets a
        // sentence rather than an exception page.
        if (! $invoice->isPosted()) {
            return back()->with('error', 'This invoice is not posted yet, so it cannot take a payment.');
        }
        if ($invoice->is_cash_invoice) {
            return back()->with('error', 'This was a cash sale — it was paid when it was raised.');
        }

        $amount = bcadd((string) $validated['amount'], '0', 2);

        $method = PaymentMethod::findOrFail($validated['payment_method_id']);

        $receipt = $receipts->createPosted(
            customer:      $customer,
            paymentMethod: $method,
            amount:        $amount,
            receiptDate:   isset($validated['received_on'])
                ? CarbonImmutable::parse($validated['received_on'])
                : CarbonImmutable::today(),
            createdBy:     $request->user(),
            extras: [
                'external_reference' => $validated['reference'] ?? null,
                'description'        => "Payment for invoice {$invoice->invoice_number}",
            ],
        );

        $allocations->allocate(
            receipt: $receipt,
            invoice: $invoice,
            amount:  $amount,
            user:    $request->user(),
            notes:   'Recorded from Sales & Money In.',
        );

        $remaining = bcadd((string) $invoice->fresh()->balance_due, '0', 2);

        $message = bccomp($remaining, '0', 2) === 0
            ? "GHS {$amount} received. Invoice {$invoice->invoice_number} is now paid in full."
            : "GHS {$amount} received. GHS {$remaining} still owed on invoice {$invoice->invoice_number}.";

        return redirect()->route('finance.money-in.index')->with('success', $message);
    }
}
