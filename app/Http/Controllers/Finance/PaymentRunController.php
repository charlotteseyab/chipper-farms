<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Counterparty;
use App\Models\Finance\Account;
use App\Models\Finance\PaymentRun;
use App\Models\Finance\PaymentRunLine;
use App\Models\Finance\VendorInvoice;
use App\Models\PaymentMethod;
use App\Services\Finance\Payments\PaymentRunExecutor;
use App\Services\Finance\Payments\PaymentRunService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentRunController extends Controller
{
    public function index(Request $request, CurrentOrganization $currentOrg)
    {
        $orgId = $currentOrg->id();
        $status = $request->get('status', 'all');

        $query = PaymentRun::with(['createdBy:id,name', 'executedBy:id,name'])
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->latest('id');

        if (in_array($status, ['draft', 'executed', 'cancelled', 'reversed'], true)) {
            $query->where('status', $status);
        }

        $runs = $query->paginate(25)->withQueryString();

        $counts = [
            'total'    => PaymentRun::when($orgId, fn ($q) => $q->where('organization_id', $orgId))->count(),
            'draft'    => PaymentRun::when($orgId, fn ($q) => $q->where('organization_id', $orgId))->draft()->count(),
            'executed' => PaymentRun::when($orgId, fn ($q) => $q->where('organization_id', $orgId))->executed()->count(),
        ];

        return view('finance.payment-runs.index', compact('runs', 'counts', 'status'));
    }

    public function show(PaymentRun $run, CurrentOrganization $currentOrg)
    {
        $this->ensureInOrg($run, $currentOrg);

        $run->load([
            'lines.vendorInvoice.counterparty:id,name,code',
            'lines.counterparty:id,name,code',
            'lines.paymentMethod',
            'lines.sourceAccount',
            'lines.payment',
            'lines.paymentAllocation',
            'payments.counterparty:id,name',
            'payments.paymentMethod:id,code,name',
            'defaultPaymentMethod',
            'defaultSourceAccount',
            'createdBy', 'executedBy', 'cancelledBy', 'reversedBy',
        ]);

        $availableInvoices = collect();
        if ($run->isDraft()) {
            $linkedVis = $run->lines->pluck('vendor_invoice_id')->all();
            $availableInvoices = VendorInvoice::query()
                ->where('organization_id', $run->organization_id)
                ->where('currency_code', $run->currency_code)
                ->where('status', VendorInvoice::STATUS_POSTED)
                ->where('balance_due', '>', 0)
                ->when(! empty($linkedVis), fn ($q) => $q->whereNotIn('id', $linkedVis))
                ->with('counterparty:id,name,code')
                ->orderBy('invoice_date')
                ->limit(100)
                ->get();
        }

        return view('finance.payment-runs.show', compact('run', 'availableInvoices'));
    }

    public function create(Request $request, CurrentOrganization $currentOrg)
    {
        $orgId = $currentOrg->id();

        $methods = PaymentMethod::query()
            ->where(function ($q) {
                $q->where('is_cash', true)
                  ->orWhere('is_bank', true)
                  ->orWhere('is_mobile_money', true);
            })
            ->orderBy('display_order')
            ->get();

        $cashAccounts = Account::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->where('is_active', true)
            ->where('account_type', 'asset')
            ->where('account_subtype', 'current_asset')
            ->where('code', 'like', '10%')
            ->where('allow_manual_journal', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return view('finance.payment-runs.create', compact('methods', 'cashAccounts'));
    }

    public function store(Request $request, PaymentRunService $service)
    {
        $validated = $request->validate([
            'run_date'                    => ['required', 'date'],
            'currency_code'               => ['required', 'string', 'size:3'],
            'default_payment_method_id'   => ['nullable', 'integer', 'exists:payment_methods,id'],
            'default_source_account_id'   => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'description'                 => ['nullable', 'string', 'max:500'],
            'notes'                       => ['nullable', 'string', 'max:2000'],
        ]);

        $run = $service->createDraft(
            runDate:                  CarbonImmutable::parse($validated['run_date']),
            currencyCode:             $validated['currency_code'],
            defaultPaymentMethodId:   $validated['default_payment_method_id'] ?? null,
            defaultSourceAccountId:   $validated['default_source_account_id'] ?? null,
            createdBy:                $request->user(),
            extra: [
                'description' => $validated['description'] ?? null,
                'notes'       => $validated['notes'] ?? null,
            ],
        );

        return redirect()
            ->route('finance.payment-runs.show', $run)
            ->with('success', "Payment run {$run->run_number} created.");
    }

    public function addLine(Request $request, PaymentRun $run, PaymentRunService $service, CurrentOrganization $currentOrg)
    {
        $this->ensureInOrg($run, $currentOrg);

        $validated = $request->validate([
            'vendor_invoice_id'          => ['required', 'integer',
                Rule::exists('vendor_invoices', 'id')
                    ->where(fn ($q) => $q->where('organization_id', $run->organization_id))],
            'amount'                     => ['nullable', 'numeric', 'gt:0'],
            'payment_method_id'          => ['nullable', 'integer', 'exists:payment_methods,id'],
            'source_account_id'          => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'notes'                      => ['nullable', 'string', 'max:500'],
        ]);

        $invoice = VendorInvoice::findOrFail($validated['vendor_invoice_id']);

        try {
            $service->addLine(
                run:                       $run,
                invoice:                   $invoice,
                amount:                    isset($validated['amount']) ? (string) $validated['amount'] : null,
                paymentMethodOverrideId:   $validated['payment_method_id'] ?? null,
                sourceAccountOverrideId:   $validated['source_account_id'] ?? null,
                notes:                     $validated['notes'] ?? null,
            );
            return back()->with('success', "Line added: {$invoice->vendor_invoice_number}");
        } catch (\DomainException | \InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function removeLine(PaymentRunLine $line, PaymentRunService $service, CurrentOrganization $currentOrg)
    {
        $run = $line->paymentRun;
        $this->ensureInOrg($run, $currentOrg);

        try {
            $service->removeLine($line);
            return back()->with('success', 'Line removed.');
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function execute(Request $request, PaymentRun $run, PaymentRunExecutor $executor, CurrentOrganization $currentOrg)
    {
        $this->ensureInOrg($run, $currentOrg);

        try {
            $executor->execute($run, $request->user());
            return back()->with('success', "Payment run {$run->run_number} executed.");
        } catch (\DomainException | \App\Finance\Exceptions\PostingException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function cancel(Request $request, PaymentRun $run, PaymentRunService $service, CurrentOrganization $currentOrg)
    {
        $this->ensureInOrg($run, $currentOrg);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        try {
            $service->cancel($run, $request->user(), $validated['reason']);
            return redirect()
                ->route('finance.payment-runs.index')
                ->with('success', "Payment run {$run->run_number} cancelled.");
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reverse(Request $request, PaymentRun $run, PaymentRunExecutor $executor, CurrentOrganization $currentOrg)
    {
        $this->ensureInOrg($run, $currentOrg);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        try {
            $executor->reverse($run, $request->user(), $validated['reason']);
            return back()->with('success', "Payment run {$run->run_number} reversed.");
        } catch (\DomainException | \App\Finance\Exceptions\PostingException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /* internals */

    protected function ensureInOrg(?PaymentRun $run, CurrentOrganization $currentOrg): void
    {
        if (! $run) return;
        $orgId = $currentOrg->id();
        if ($orgId !== null && $run->organization_id !== null
            && (int) $run->organization_id !== (int) $orgId) {
            abort(403);
        }
    }
}
