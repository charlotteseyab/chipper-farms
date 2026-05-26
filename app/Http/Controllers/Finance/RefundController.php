<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Counterparty;
use App\Models\Finance\Receipt;
use App\Models\Finance\Refund;
use App\Models\PaymentMethod;
use App\Services\Finance\Refunds\RefundService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Refund controller.
 *
 * Permissions:
 *   index, show:                  view_finance
 *   create, store, cancel (draft): create_refund
 *   post (approval):              approve_refund
 *   reverse:                      reverse_journal (existing perm)
 *
 * The approve_refund permission is intentionally separate from create_refund
 * to enable two-person rule: a junior accountant can DRAFT a refund, but
 * treasury must POST it. This is the standard segregation of duties for
 * outgoing money.
 */
class RefundController extends Controller
{
    public function index(Request $request, CurrentOrganization $currentOrg)
    {
        $orgId = $currentOrg->id();
        $status = $request->get('status', 'all');

        $query = Refund::with(['counterparty', 'paymentMethod', 'sourceReceipt'])
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->latest('id');

        if (in_array($status, [Refund::STATUS_DRAFT, Refund::STATUS_POSTED, Refund::STATUS_REVERSED], true)) {
            $query->where('status', $status);
        }

        $refunds = $query->paginate(25)->withQueryString();

        $counts = [
            'total'    => Refund::when($orgId, fn ($q) => $q->where('organization_id', $orgId))->count(),
            'draft'    => Refund::when($orgId, fn ($q) => $q->where('organization_id', $orgId))->draft()->count(),
            'posted'   => Refund::when($orgId, fn ($q) => $q->where('organization_id', $orgId))->posted()->count(),
            'reversed' => Refund::when($orgId, fn ($q) => $q->where('organization_id', $orgId))->reversed()->count(),
        ];

        return view('finance.refunds.index', compact('refunds', 'counts', 'status'));
    }

    public function show(Refund $refund, CurrentOrganization $currentOrg)
    {
        $this->ensureInOrg($refund, $currentOrg);

        $refund->load([
            'counterparty.category', 'paymentMethod', 'sourceReceipt',
            'journalBatch', 'reversedByJournalBatch',
            'createdBy', 'approvedBy', 'reversedBy',
        ]);

        return view('finance.refunds.show', compact('refund'));
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
            ->get(['id', 'name', 'code']);

        // Optional pre-selection: when refunding from a specific receipt
        $sourceReceipt = null;
        if ($request->filled('source_receipt_id')) {
            $sourceReceipt = Receipt::find($request->get('source_receipt_id'));
            if ($sourceReceipt && (! $orgId || $sourceReceipt->organization_id == $orgId)) {
                // Capture the unallocated amount as default
            } else {
                $sourceReceipt = null;
            }
        }

        return view('finance.refunds.create', compact('customers', 'paymentMethods', 'sourceReceipt'));
    }

    public function store(Request $request, RefundService $service, CurrentOrganization $currentOrg)
    {
        $orgId = $currentOrg->id();

        $validated = $request->validate([
            'counterparty_id'    => ['required', 'integer',
                                       Rule::exists('counterparties', 'id')
                                           ->where(fn ($q) => $q->where('organization_id', $orgId))],
            'payment_method_id'  => ['required', 'integer', 'exists:payment_methods,id'],
            'refund_date'        => ['required', 'date'],
            'amount'             => ['required', 'numeric', 'gt:0'],
            'currency_code'      => ['required', 'string', 'size:3'],
            'source_receipt_id'  => ['nullable', 'integer', 'exists:receipts,id'],
            'external_reference' => ['nullable', 'string', 'max:100'],
            'reason'             => ['required', 'string', 'max:500'],
            'description'        => ['nullable', 'string', 'max:500'],
        ]);

        $customer = Counterparty::findOrFail($validated['counterparty_id']);
        $method = PaymentMethod::findOrFail($validated['payment_method_id']);

        try {
            $refund = $service->createDraft(
                customer:       $customer,
                paymentMethod:  $method,
                amount:         (string) $validated['amount'],
                refundDate:     CarbonImmutable::parse($validated['refund_date']),
                createdBy:      $request->user(),
                extras: [
                    'currency_code'      => $validated['currency_code'],
                    'source_receipt_id'  => $validated['source_receipt_id'] ?? null,
                    'external_reference' => $validated['external_reference'] ?? null,
                    'reason'             => $validated['reason'],
                    'description'        => $validated['description'] ?? null,
                ],
            );
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('finance.refunds.show', $refund)
            ->with('success', "Refund {$refund->refund_number} created in draft. Awaiting approval to post.");
    }

    public function post(Request $request, Refund $refund, RefundService $service, CurrentOrganization $currentOrg)
    {
        $this->ensureInOrg($refund, $currentOrg);

        if (! $request->user()?->hasPermission('approve_refund')) {
            abort(403, 'You do not have permission to approve refunds.');
        }

        try {
            $service->post($refund, $request->user());
            return back()->with('success', "Refund {$refund->refund_number} posted to GL.");
        } catch (\DomainException | \App\Finance\Exceptions\PostingException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reverse(Request $request, Refund $refund, RefundService $service, CurrentOrganization $currentOrg)
    {
        $this->ensureInOrg($refund, $currentOrg);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $service->reverse($refund, $request->user(), $validated['reason']);
            return back()->with('success', "Refund {$refund->refund_number} reversed.");
        } catch (\DomainException | \App\Finance\Exceptions\PostingException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function cancel(Request $request, Refund $refund, RefundService $service, CurrentOrganization $currentOrg)
    {
        $this->ensureInOrg($refund, $currentOrg);

        try {
            $service->cancel($refund, $request->user());
            return redirect()
                ->route('finance.refunds.index')
                ->with('success', "Draft refund cancelled.");
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    protected function ensureInOrg(Refund $refund, CurrentOrganization $currentOrg): void
    {
        $orgId = $currentOrg->id();
        if ($orgId !== null && $refund->organization_id !== null && (int) $refund->organization_id !== (int) $orgId) {
            abort(403);
        }
    }
}
