<?php

namespace App\Http\Controllers\Finance;

use App\Finance\Exceptions\PostingException;
use App\Http\Controllers\Controller;
use App\Models\Counterparty;
use App\Models\Finance\Invoice;
use App\Models\PaymentMethod;
use App\Services\Finance\Invoicing\InvoiceLineDraft;
use App\Services\Finance\Invoicing\InvoicePostingService;
use App\Services\Finance\Invoicing\InvoiceService;
use App\Services\Finance\JournalReversalService;
use App\Support\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Phase 2b — Invoice controller.
 *
 * Permission gates:
 *   index, show:           view_finance
 *   create, store:         create_invoice
 *   post:                  post_invoice
 *   cancel:                create_invoice  (cancel a draft you can edit)
 *   reverse:               reverse_journal (reuses the journal-reversal permission)
 *
 * Two new permissions added in this batch's seeder: create_invoice + post_invoice.
 */
class InvoiceController extends Controller
{
    public function index(Request $request, CurrentOrganization $currentOrg)
    {
        $orgId = $currentOrg->id();
        $status = $request->get('status', 'all');
        $customerId = $request->get('customer');
        $search = trim((string) $request->get('search'));
        $overdueOnly = (bool) $request->get('overdue');

        $query = Invoice::with(['counterparty', 'journalBatch'])
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->latest('id');

        if (in_array($status, ['draft', 'posted', 'cancelled', 'reversed'], true)) {
            $query->where('status', $status);
        }

        if ($customerId) {
            $query->where('counterparty_id', $customerId);
        }

        if ($overdueOnly) {
            $query->overdue();
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%");
            });
        }

        $invoices = $query->paginate(25)->withQueryString();

        $counts = [
            'draft'     => Invoice::draft()->when($orgId, fn ($q) => $q->where('organization_id', $orgId))->count(),
            'posted'    => Invoice::posted()->when($orgId, fn ($q) => $q->where('organization_id', $orgId))->count(),
            'cancelled' => Invoice::where('status', 'cancelled')->when($orgId, fn ($q) => $q->where('organization_id', $orgId))->count(),
            'overdue'   => Invoice::overdue()->when($orgId, fn ($q) => $q->where('organization_id', $orgId))->count(),
        ];

        return view('finance.invoices.index', compact('invoices', 'counts', 'status', 'overdueOnly', 'search', 'customerId'));
    }

    public function show(Invoice $invoice, CurrentOrganization $currentOrg)
    {
        $this->ensureInOrg($invoice, $currentOrg);

        $invoice->load([
            'counterparty.category',
            'lines.taxCode',
            'lines.revenueAccount',
            'lines.pig',
            'paymentMethod',
            'journalBatch.journals.lines.account',
            'createdBy',
            'postedBy',
            'cancelledBy',
        ]);

        return view('finance.invoices.show', compact('invoice'));
    }

    public function create(CurrentOrganization $currentOrg)
    {
        $orgId = $currentOrg->id();

        $customers = Counterparty::customers()->active()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'currency_code']);

        $paymentMethods = PaymentMethod::active()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->get(['id', 'name', 'code']);

        return view('finance.invoices.create', compact('customers', 'paymentMethods'));
    }

    public function store(Request $request, InvoiceService $service, CurrentOrganization $currentOrg)
    {
        $orgId = $currentOrg->id();

        $validated = $request->validate([
            'counterparty_id'        => ['required', 'integer',
                                          Rule::exists('counterparties', 'id')
                                              ->where(fn ($q) => $q->where('organization_id', $orgId))],
            'invoice_date'           => ['required', 'date'],
            'due_date'               => ['nullable', 'date'],
            'currency_code'          => ['required', 'string', 'size:3'],
            'reference'              => ['nullable', 'string', 'max:100'],
            'payment_method_id'      => ['nullable', 'integer', 'exists:payment_methods,id'],
            'description'            => ['nullable', 'string', 'max:500'],
            'header_discount_percent'=> ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines'                  => ['required', 'array', 'min:1'],
            'lines.*.description'    => ['required', 'string', 'max:500'],
            'lines.*.quantity'       => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price'     => ['required', 'numeric', 'min:0'],
            'lines.*.unit_of_measure'=> ['nullable', 'string', 'max:20'],
            'lines.*.discount_percent'=> ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.tax_rate_percent'=> ['nullable', 'numeric', 'min:0'],
        ]);

        $customer = Counterparty::findOrFail($validated['counterparty_id']);

        $lineDrafts = [];
        foreach ($validated['lines'] as $line) {
            $lineDrafts[] = new InvoiceLineDraft(
                description:     $line['description'],
                quantity:        (string) $line['quantity'],
                unitPrice:       (string) $line['unit_price'],
                unitOfMeasure:   $line['unit_of_measure'] ?? 'unit',
                discountPercent: (string) ($line['discount_percent'] ?? '0'),
                taxRatePercent:  (string) ($line['tax_rate_percent'] ?? '0'),
            );
        }

        $invoice = $service->createDraft(
            customer: $customer,
            headerData: array_intersect_key($validated, array_flip([
                'invoice_date', 'due_date', 'currency_code', 'reference',
                'payment_method_id', 'description', 'header_discount_percent',
            ])),
            lineDrafts: $lineDrafts,
            createdBy: $request->user(),
        );

        return redirect()
            ->route('finance.invoices.show', $invoice)
            ->with('success', "Invoice {$invoice->invoice_number} created in draft. Review and post when ready.");
    }

    public function post(Request $request, Invoice $invoice, InvoicePostingService $service, CurrentOrganization $currentOrg)
    {
        $this->ensureInOrg($invoice, $currentOrg);

        try {
            $service->post($invoice, $request->user());
            return redirect()
                ->route('finance.invoices.show', $invoice)
                ->with('success', "Invoice {$invoice->invoice_number} posted to GL.");
        } catch (PostingException | \DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function cancel(Request $request, Invoice $invoice, InvoiceService $service, CurrentOrganization $currentOrg)
    {
        $this->ensureInOrg($invoice, $currentOrg);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $service->cancel($invoice, $request->user(), $validated['reason']);
            return redirect()
                ->route('finance.invoices.index')
                ->with('success', "Invoice {$invoice->invoice_number} cancelled.");
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reverse(Request $request, Invoice $invoice, JournalReversalService $reversalService, CurrentOrganization $currentOrg)
    {
        $this->ensureInOrg($invoice, $currentOrg);

        if (! $invoice->canReverse()) {
            return back()->with('error', 'This invoice cannot be reversed in its current state.');
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $reversalBatch = $reversalService->reverse(
                original: $invoice->journalBatch,
                reverser: $request->user(),
                reason:   "Invoice {$invoice->invoice_number}: " . $validated['reason'],
            );

            $invoice->update([
                'status'                       => Invoice::STATUS_REVERSED,
                'reversed_by_journal_batch_id' => $reversalBatch->id,
            ]);

            return redirect()
                ->route('finance.invoices.show', $invoice)
                ->with('success', "Invoice {$invoice->invoice_number} reversed.");
        } catch (PostingException | \DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    protected function ensureInOrg(Invoice $invoice, CurrentOrganization $currentOrg): void
    {
        $orgId = $currentOrg->id();
        if ($orgId !== null && $invoice->organization_id !== null && (int) $invoice->organization_id !== (int) $orgId) {
            abort(403, 'Invoice belongs to a different organisation.');
        }
    }
}
