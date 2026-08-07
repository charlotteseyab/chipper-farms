<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Counterparty;
use App\Models\Finance\QuickExpense;
use App\Services\Finance\Expenses\RecordQuickExpenseService;
use App\Services\Finance\Tax\TaxProfileResolver;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Record Expense (Phase 4, blueprint §23).
 *
 * The controller does no accounting. It validates, hands the input to
 * `RecordQuickExpenseService`, and translates the service's exceptions into
 * form errors a farm operator can act on.
 *
 * THE FORM CARRIES ITS OWN IDEMPOTENCY TOKEN
 * -------------------------------------------
 * A hidden `idempotency_key` is minted when the form renders and travels with
 * the submission. That makes the browser's back-button-then-resubmit case safe
 * as well as the double-tap case: both replay the same token, and the service
 * returns the original expense instead of posting again.
 *
 * Relying on the derived key alone would be nearly as good, but not quite — a
 * user who genuinely buys diesel twice in a day for the same amount would be
 * told their second expense was a duplicate. An explicit per-render token
 * removes that false positive.
 */
class QuickExpenseController extends Controller
{
    public function __construct(
        protected RecordQuickExpenseService $expenses,
        protected TaxProfileResolver $taxProfiles,
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    public function index(Request $request): View
    {
        $organizationId = $this->currentOrganization->get()?->id;

        $expenses = QuickExpense::query()
            ->when($organizationId, fn ($q) => $q->forOrganization($organizationId))
            ->with(['supplier:id,name', 'recordedBy:id,name'])
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->paginate(25);

        return view('finance.expenses.index', [
            'expenses'   => $expenses,
            'categories' => config('expense_categories.categories'),
        ]);
    }

    public function create(): View
    {
        $organizationId = $this->currentOrganization->get()?->id;

        return view('finance.expenses.create', [
            'categories'      => config('expense_categories.categories'),
            'paymentAccounts' => config('expense_categories.payment_accounts'),
            'referenceNeeded' => config('expense_categories.reference_required_for'),
            'suppliers'       => Counterparty::query()
                ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
                ->where('type', 'supplier')
                ->orderBy('name')
                ->get(['id', 'name']),
            'taxProfiles'     => $this->taxProfiles->selectable('purchase'),
            'idempotencyKey'  => (string) Str::uuid(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'expense_date'       => ['required', 'date'],
            'category_key'       => ['required', 'string', Rule::in(array_keys(config('expense_categories.categories')))],
            'description'        => ['required', 'string', 'max:500'],
            'amount'             => ['required', 'numeric', 'gt:0'],
            'settlement'         => ['required', Rule::in([
                QuickExpense::SETTLEMENT_PAID_NOW,
                QuickExpense::SETTLEMENT_PAY_LATER,
            ])],
            'payment_method_key' => ['nullable', Rule::in(array_keys(config('expense_categories.payment_accounts')))],
            'external_reference' => ['nullable', 'string', 'max:120'],
            'supplier_id'        => ['nullable', 'integer', 'exists:counterparties,id'],
            'tax_profile_code'   => ['nullable', 'string', 'max:40'],
            'notes'              => ['nullable', 'string', 'max:2000'],
            'idempotency_key'    => ['required', 'string', 'max:120'],
        ]);

        // Amount arrives as a string and stays one. Casting to float here would
        // undo the decimal arithmetic the service and tax calculator rely on.
        $validated['amount'] = (string) $request->input('amount');

        try {
            $expense = $this->expenses->record($validated, $request->user());
        } catch (\InvalidArgumentException | \DomainException $e) {
            // The service's messages are written for a farm operator, so they
            // are shown as-is rather than replaced with a generic failure.
            return back()
                ->withInput()
                ->withErrors(['expense' => $e->getMessage()]);
        }

        $message = "Recorded {$expense->expense_number}: {$expense->categoryLabel()}, "
            . 'GHS ' . number_format((float) $expense->total_amount, 2) . '.';

        if ($expense->needs_reclassification) {
            $message .= ' We could not match that category to an account, so it is held for review.';
        }

        return redirect()
            ->route('finance.expenses.index')
            ->with('success', $message);
    }
}
