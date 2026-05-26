<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreLoanRequest;
use App\Http\Requests\Finance\RepayLoanRequest;
use App\Models\Finance\BankAccount;
use App\Models\Finance\Loan;
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
        $this->middleware('auth');
        $this->middleware('permission:view_loans')->only(['index', 'show', 'create', 'reclassification']);
        $this->middleware('permission:manage_loans')->only(['store', 'disburse', 'repay']);
    }

    public function index(Request $request)
    {
        $orgId = $this->currentOrg->id();
        $loans = Loan::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->orderByDesc('id')
            ->paginate(25);

        return view('finance.loans.index', ['loans' => $loans]);
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
