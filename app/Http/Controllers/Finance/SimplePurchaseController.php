<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Counterparty;
use App\Models\Finance\SimplePurchase;
use App\Models\Finance\SimplePurchaseLine;
use App\Models\Finance\Payment;
use App\Models\Finance\VendorInvoice;
use App\Models\PurchaseRequest;
use App\Models\RawMaterial;
use App\Services\Finance\Cash\MoneyAccountService;
use App\Services\Finance\Purchases\RecordSimplePurchaseService;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Purchases (Phase 6b, blueprint §24).
 *
 * The controller does no accounting. It validates, hands input to
 * `RecordSimplePurchaseService`, and turns the service's exceptions into form
 * errors written for a farm operator.
 *
 * PER-LINE VALIDATION IS DELIBERATELY SHALLOW HERE
 * -------------------------------------------------
 * The request rules check shape — a line has a kind, a positive quantity, a
 * non-negative cost. The rules that carry meaning ("a stock line needs an item
 * so the delivery can be received", "a pay-later purchase needs someone to owe")
 * live in the service, because they must hold whoever calls it. Duplicating them
 * here would create two places to change and one to forget.
 */
class SimplePurchaseController extends Controller
{
    public function __construct(
        protected RecordSimplePurchaseService $purchases,
        protected CurrentOrganization $currentOrganization,
        protected MoneyAccountService $moneyAccounts,
    ) {
    }

    public function index(Request $request): View
    {
        $organizationId = $this->currentOrganization->get()?->id;
        $tab = in_array($request->string('tab')->toString(), ['all', 'requests', 'direct', 'invoices', 'payments'], true)
            ? $request->string('tab')->toString()
            : 'all';
        $search = trim($request->string('search')->toString());

        $directQuery = SimplePurchase::query()->when($organizationId, fn ($q) => $q->forOrganization($organizationId));
        $requestQuery = PurchaseRequest::query()->when($organizationId, fn ($q) => $q->forOrganization($organizationId));
        $invoiceQuery = VendorInvoice::query()->when($organizationId, fn ($q) => $q->forOrganization($organizationId))->whereNotNull('purchase_request_id');
        $paymentQuery = Payment::query()->when($organizationId, fn ($q) => $q->forOrganization($organizationId))->whereNotNull('purchase_request_id')->where('status', Payment::STATUS_POSTED);

        $directTotal = (float) (clone $directQuery)->where('status', SimplePurchase::STATUS_POSTED)->sum('total_amount');
        $requestedTotal = (float) (clone $requestQuery)->whereNotIn('status', ['declined', 'draft'])->sum('total_amount');
        $paidTotal = (float) (clone $paymentQuery)->sum('amount');
        $openRequestCount = (clone $requestQuery)->whereIn('status', ['pending', 'approved', 'paid'])->count();

        $directPurchases = (clone $directQuery)
            ->with(['supplier:id,name', 'lines'])
            ->when($search !== '', fn ($query) => $query->where(function ($nested) use ($search) {
                $nested->where('purchase_number', 'like', "%{$search}%")
                    ->orWhere('supplier_invoice_reference', 'like', "%{$search}%")
                    ->orWhereHas('supplier', fn ($supplier) => $supplier->where('name', 'like', "%{$search}%"));
            }))
            ->orderByDesc('purchase_date')->orderByDesc('id')
            ->paginate(15, ['*'], 'direct_page')->withQueryString();

        $purchaseRequests = (clone $requestQuery)
            ->with([
                'supplier:id,name',
                'items:id,purchase_request_id,quantity,quantity_received,is_receipt_closed',
                'vendorInvoices:id,purchase_request_id,vendor_invoice_number,status,total,balance_due',
            ])
            ->withSum(['payments as posted_payment_total' => fn ($query) => $query->where('status', Payment::STATUS_POSTED)], 'amount')
            ->when($search !== '', fn ($query) => $query->where(function ($nested) use ($search) {
                $nested->where('request_no', 'like', "%{$search}%")
                    ->orWhere('purpose', 'like', "%{$search}%")
                    ->orWhereHas('supplier', fn ($supplier) => $supplier->where('name', 'like', "%{$search}%"));
            }))
            ->latest('request_date')->latest('id')
            ->paginate(15, ['*'], 'requests_page')->withQueryString();

        $vendorInvoices = (clone $invoiceQuery)
            ->with(['supplier:id,name', 'purchaseRequest:id,request_no'])
            ->when($search !== '', fn ($query) => $query->where(function ($nested) use ($search) {
                $nested->where('vendor_invoice_number', 'like', "%{$search}%")
                    ->orWhere('supplier_invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('purchaseRequest', fn ($pr) => $pr->where('request_no', 'like', "%{$search}%"));
            }))
            ->latest('invoice_date')->latest('id')
            ->paginate(15, ['*'], 'invoices_page')->withQueryString();

        $payments = (clone $paymentQuery)
            ->with(['supplier:id,name', 'purchaseRequest:id,request_no'])
            ->when($search !== '', fn ($query) => $query->where(function ($nested) use ($search) {
                $nested->where('payment_number', 'like', "%{$search}%")
                    ->orWhere('external_reference', 'like', "%{$search}%")
                    ->orWhereHas('purchaseRequest', fn ($pr) => $pr->where('request_no', 'like', "%{$search}%"));
            }))
            ->latest('payment_date')->latest('id')
            ->paginate(15, ['*'], 'payments_page')->withQueryString();

        return view('finance.purchases.index', [
            'tab' => $tab,
            'search' => $search,
            'directPurchases' => $directPurchases,
            'purchaseRequests' => $purchaseRequests,
            'vendorInvoices' => $vendorInvoices,
            'payments' => $payments,
            'summary' => compact('directTotal', 'requestedTotal', 'paidTotal', 'openRequestCount'),
        ]);
    }

    public function create(): View
    {
        $organizationId = $this->currentOrganization->get()?->id;

        return view('finance.purchases.create', [
            'suppliers'       => Counterparty::query()
                ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
                ->where('type', 'supplier')
                ->orderBy('name')
                ->get(['id', 'name']),
            // raw_materials carries no organization_id — the catalogue is shared
            // across tenants (Phase 0 §6). Listing it unscoped matches the schema
            // rather than silently returning nothing.
            'materials'       => RawMaterial::query()->orderBy('name')->get(['id', 'name', 'unit']),
            'categories'      => config('expense_categories.categories'),
            'moneyAccounts'   => $this->moneyAccounts->selectable($organizationId),
            'idempotencyKey'  => (string) Str::uuid(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $organizationId = $this->currentOrganization->get()?->id;

        $validated = $request->validate([
            'purchase_date'              => ['required', 'date'],
            'supplier_id'                => ['nullable', 'integer', 'exists:counterparties,id'],
            'supplier_invoice_reference' => ['nullable', 'string', 'max:120'],
            'settlement'                 => ['required', Rule::in([
                SimplePurchase::SETTLEMENT_PAID_NOW,
                SimplePurchase::SETTLEMENT_PAY_LATER,
            ])],
            'money_account_id'           => ['nullable', 'integer',
                Rule::exists('bank_accounts', 'id')
                    ->where(fn ($q) => $q->where('organization_id', $organizationId)
                        ->where('is_active', true))],
            'payment_method_key'         => ['nullable', Rule::in(array_keys(config('expense_categories.payment_accounts')))],
            'external_reference'         => ['nullable', 'string', 'max:120'],
            'notes'                      => ['nullable', 'string', 'max:2000'],
            'idempotency_key'            => ['required', 'string', 'max:120'],

            'lines'                      => ['required', 'array', 'min:1'],
            'lines.*.line_kind'          => ['required', Rule::in([
                SimplePurchaseLine::KIND_STOCK,
                SimplePurchaseLine::KIND_EXPENSE,
            ])],
            'lines.*.description'        => ['required', 'string', 'max:500'],
            'lines.*.quantity'           => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_cost'          => ['required', 'numeric', 'min:0'],
            'lines.*.raw_material_id'    => ['nullable', 'integer', 'exists:raw_materials,id'],
            'lines.*.category_key'       => ['nullable', 'string', 'max:60'],
            'lines.*.unit'               => ['nullable', 'string', 'max:20'],
        ]);

        // Quantities and costs stay strings all the way to bcmath. Casting them
        // to float here would undo the decimal arithmetic the service relies on.
        foreach ($validated['lines'] as $i => $line) {
            $validated['lines'][$i]['quantity']  = (string) $request->input("lines.{$i}.quantity");
            $validated['lines'][$i]['unit_cost'] = (string) $request->input("lines.{$i}.unit_cost");
        }

        try {
            $purchase = $this->purchases->record($validated, $request->user());
        } catch (\InvalidArgumentException | \DomainException $e) {
            return back()->withInput()->withErrors(['purchase' => $e->getMessage()]);
        }

        $stockLines = $purchase->lines->where('line_kind', SimplePurchaseLine::KIND_STOCK)->count();

        $message = "Recorded {$purchase->purchase_number}: GHS "
            . number_format((float) $purchase->total_amount, 2) . '.';

        if ($stockLines > 0) {
            $message .= ' Stock has been received into inventory.';
        }

        if (! $purchase->isPaidNow()) {
            $message .= ' It is on the supplier\'s account until you pay it.';
        }

        return redirect()->route('finance.purchases.index')->with('success', $message);
    }
}
