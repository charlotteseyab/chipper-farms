<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreLoanRequest;
use App\Http\Requests\Finance\RepayLoanRequest;
use App\Models\Finance\BankAccount;
use App\Models\Finance\Loan;
use App\Models\Finance\LoanScheduleLine;
use App\Services\Finance\Loans\Exceptions\LoanException;
use App\Services\Finance\Loans\LoanReclassificationService;
use App\Services\Finance\Loans\LoanService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Phase 8 — loan management.
 *
 *   index/show         view loans (view_loans)
 *   create/store       draft a loan + schedule (manage_loans)
 *   disburse           post disbursement (manage_loans)
 *   repay              record a repayment (manage_loans)
 *   reclassification   current/long-term report (view_loans)
 */
class LoanController extends Controller
{
    public function __construct(
        protected CurrentOrganization $currentOrg,
        protected LoanService $loans,
    ) {
    }

    /**
     * Loans, in the language a farm owner uses (Phase 7, blueprint §26).
     *
     * §26 asks for "add loan / receive loan money / record repayment / view
     * upcoming payments" and says to "hide amortisation and liability
     * terminology from owner view unless the user opens details". The list was
     * previously a register — reference, lender, type, principal, outstanding —
     * with the schedule reachable only by opening a loan and scrolling past an
     * amortisation table. The one thing an owner needs first, *when is the next
     * payment due*, was the hardest thing on the screen to find.
     *
     * The figures are read straight off the loans and their schedule lines. No
     * new calculation: `LoanService` still owns everything that touches the GL.
     */
    public function index(Request $request)
    {
        $orgId = $this->currentOrg->id();

        $loans = Loan::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->orderByDesc('id')
            ->paginate(25);

        // What is still owed across every active loan — principal plus interest
        // already accrued, because both are money the farm has to find.
        // bcmath, never floats: these are balances, not display values.
        $stillOwed = '0.00';
        foreach (Loan::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->active()
            ->get(['principal_outstanding', 'interest_accrued_outstanding']) as $active) {
            $stillOwed = bcadd($stillOwed, (string) $active->principal_outstanding, 2);
            $stillOwed = bcadd($stillOwed, (string) $active->interest_accrued_outstanding, 2);
        }

        // The next payments due, across all loans. §26's "view upcoming
        // payments" — the schedule lines that already exist, surfaced where the
        // question is asked rather than inside one loan's amortisation table.
        $upcoming = LoanScheduleLine::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->where('status', '!=', 'paid')
            ->whereHas('loan', fn ($q) => $q->where('status', Loan::STATUS_ACTIVE))
            ->with('loan')
            ->orderBy('due_date')
            ->limit(6)
            ->get();

        return view('finance.loans.index', [
            'loans'     => $loans,
            'stillOwed' => $stillOwed,
            'upcoming'  => $upcoming,
            // Reclassification is an accounting act, and §26 lists it under
            // advanced accounting. It stays available; it comes off the
            // owner's front page for organisations that are not doing it.
            'advancedAccounting' => app(\App\Services\Organizations\OrganizationFeatureService::class)
                ->usable(\App\Support\Features\FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING),
        ]);
    }

    public function create()
    {
        return view('finance.loans.create', [
            'bankAccounts' => $this->bankAccounts(),
            'types'        => config('loans.types'),
        ]);
    }

    public function store(StoreLoanRequest $request)
    {
        try {
            $loan = $this->loans->createDraft($request->validated(), $request->user());
            return redirect()->route('finance.loans.show', $loan)
                ->with('success', "Loan {$loan->reference} drafted with {$loan->scheduleLines->count()} scheduled instalments.");
        } catch (\Throwable $e) {
            Log::error('LoanController::store failed', ['error' => $e->getMessage()]);
            return back()->with('error', 'Could not create loan: ' . $e->getMessage())->withInput();
        }
    }

    public function show(Loan $loan)
    {
        $this->ensureInOrg($loan);
        $loan->load(['scheduleLines', 'transactions.journalBatch', 'bankAccount']);

        return view('finance.loans.show', [
            'loan'         => $loan,
            'bankAccounts' => $this->bankAccounts(),
        ]);
    }

    public function disburse(Request $request, Loan $loan)
    {
        $this->ensureInOrg($loan);
        try {
            $date = $request->filled('disbursement_date') ? CarbonImmutable::parse($request->get('disbursement_date')) : null;
            $this->loans->disburse($loan, $request->user(), $date);
            return back()->with('success', "Loan {$loan->reference} disbursed.");
        } catch (LoanException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('LoanController::disburse failed', ['error' => $e->getMessage(), 'loan' => $loan->id]);
            return back()->with('error', 'Disbursement failed: ' . $e->getMessage());
        }
    }

    public function repay(RepayLoanRequest $request, Loan $loan)
    {
        $this->ensureInOrg($loan);
        try {
            $v = $request->validated();
            $this->loans->repay($loan, [
                'principal'        => (string) ($v['principal'] ?? '0'),
                'interest'         => (string) ($v['interest'] ?? '0'),
                'fee'              => (string) ($v['fee'] ?? '0'),
                'penalty'          => (string) ($v['penalty'] ?? '0'),
                'schedule_line_id' => $v['schedule_line_id'] ?? null,
                'bank_account_id'  => $v['bank_account_id'] ?? null,
            ], $request->user(), $request->filled('txn_date') ? CarbonImmutable::parse($v['txn_date']) : null);

            return back()->with('success', "Repayment recorded for {$loan->reference}.");
        } catch (LoanException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('LoanController::repay failed', ['error' => $e->getMessage(), 'loan' => $loan->id]);
            return back()->with('error', 'Repayment failed: ' . $e->getMessage());
        }
    }

    public function reclassification(Request $request, LoanReclassificationService $service)
    {
        $asOf = $request->filled('as_of_date') ? CarbonImmutable::parse($request->get('as_of_date')) : null;
        return view('finance.loans.reclassification', ['report' => $service->generate($asOf)]);
    }

    protected function bankAccounts()
    {
        $orgId = $this->currentOrg->id();
        return BankAccount::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->where('is_active', true)
            ->get();
    }

    protected function ensureInOrg(Loan $loan): void
    {
        $orgId = $this->currentOrg->id();
        if ($orgId !== null && $loan->organization_id !== null && $loan->organization_id !== $orgId) {
            abort(404);
        }
    }
}
