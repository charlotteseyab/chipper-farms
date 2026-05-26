<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreInvestorRequest;
use App\Http\Requests\Finance\InvestorCapitalMovementRequest;
use App\Http\Requests\Finance\AllocateProfitRequest;
use App\Models\Finance\BankAccount;
use App\Models\Finance\Investor;
use App\Models\FiscalYear;
use App\Services\Finance\Investors\Exceptions\InvestorException;
use App\Services\Finance\Investors\InvestorProfitAllocationService;
use App\Services\Finance\Investors\InvestorReportingService;
use App\Services\Finance\Investors\InvestorService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Phase 9 — investor / fund management.
 *
 *   index/show/capTable/statement   view (view_investors)
 *   store/contribute/distribute     manage (manage_investors)
 *   allocateProfit                  manage (manage_investors) — coordinates
 *                                   with year-end close (allocate AFTER close)
 */
class InvestorController extends Controller
{
    public function __construct(
        protected CurrentOrganization $currentOrg,
        protected InvestorService $investors,
    ) {
        $this->middleware('auth');
        $this->middleware('permission:view_investors')->only(['index', 'show', 'capTable', 'statement', 'allocateForm']);
        $this->middleware('permission:manage_investors')->only(['store', 'contribute', 'distribute', 'allocateProfit']);
    }

    public function index()
    {
        $orgId = $this->currentOrg->id();
        $investors = Investor::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->with('capitalAccount')
            ->orderByDesc('id')
            ->paginate(25);

        return view('finance.investors.index', ['investors' => $investors]);
    }

    public function create()
    {
        return view('finance.investors.create');
    }

    public function store(StoreInvestorRequest $request)
    {
        try {
            $investor = $this->investors->createInvestor($request->validated(), $request->user());
            return redirect()->route('finance.investors.show', $investor)
                ->with('success', "Investor {$investor->name} created.");
        } catch (\Throwable $e) {
            Log::error('InvestorController::store failed', ['error' => $e->getMessage()]);
            return back()->with('error', 'Could not create investor: ' . $e->getMessage())->withInput();
        }
    }

    public function show(Investor $investor, InvestorReportingService $reporting)
    {
        $this->ensureInOrg($investor);
        return view('finance.investors.show', [
            'investor'     => $investor->load('capitalAccount', 'transactions.journalBatch', 'linkedLoan'),
            'statement'    => $reporting->statement($investor),
            'bankAccounts' => $this->bankAccounts(),
        ]);
    }

    public function contribute(InvestorCapitalMovementRequest $request, Investor $investor)
    {
        $this->ensureInOrg($investor);
        try {
            $v = $request->validated();
            $this->investors->contribute(
                $investor, (string) $v['amount'], $request->user(),
                $v['bank_account_id'] ?? null,
                $request->filled('txn_date') ? CarbonImmutable::parse($v['txn_date']) : null,
                $v['notes'] ?? null,
            );
            return back()->with('success', 'Contribution recorded.');
        } catch (InvestorException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('InvestorController::contribute failed', ['error' => $e->getMessage(), 'investor' => $investor->id]);
            return back()->with('error', 'Contribution failed: ' . $e->getMessage());
        }
    }

    public function distribute(InvestorCapitalMovementRequest $request, Investor $investor)
    {
        $this->ensureInOrg($investor);
        try {
            $v = $request->validated();
            $this->investors->distribute(
                $investor, (string) $v['amount'], $request->user(),
                $v['bank_account_id'] ?? null,
                $request->filled('txn_date') ? CarbonImmutable::parse($v['txn_date']) : null,
                $v['notes'] ?? null,
            );
            return back()->with('success', 'Distribution recorded.');
        } catch (InvestorException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('InvestorController::distribute failed', ['error' => $e->getMessage(), 'investor' => $investor->id]);
            return back()->with('error', 'Distribution failed: ' . $e->getMessage());
        }
    }

    public function capTable(InvestorReportingService $reporting)
    {
        return view('finance.investors.cap-table', [
            'capTable'       => $reporting->capTable(),
            'reconciliation' => $reporting->reconcile(),
        ]);
    }

    public function allocateForm()
    {
        $orgId = $this->currentOrg->id();
        return view('finance.investors.allocate', [
            'years' => FiscalYear::query()->when($orgId, fn ($q) => $q->where('organization_id', $orgId))->orderByDesc('start_date')->get(),
        ]);
    }

    public function allocateProfit(AllocateProfitRequest $request, InvestorProfitAllocationService $allocator)
    {
        try {
            $v = $request->validated();
            $year = FiscalYear::findOrFail($v['fiscal_year_id']);
            $run = $allocator->allocate(
                $year, (string) ($v['profit_pool'] ?? '0'), $request->user(),
                $request->filled('allocation_date') ? CarbonImmutable::parse($v['allocation_date']) : null,
                $v['method'] ?? null,
                $v['fixed_schedule'] ?? [],
                $v['notes'] ?? null,
            );
            return redirect()->route('finance.investors.cap-table')
                ->with('success', "Profit allocated (run #{$run->id}, pool GHS " . number_format((float) $run->profit_pool, 2) . ').');
        } catch (InvestorException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        } catch (\Throwable $e) {
            Log::error('InvestorController::allocateProfit failed', ['error' => $e->getMessage()]);
            return back()->with('error', 'Allocation failed: ' . $e->getMessage())->withInput();
        }
    }

    protected function bankAccounts()
    {
        $orgId = $this->currentOrg->id();
        return BankAccount::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->where('is_active', true)->get();
    }

    protected function ensureInOrg(Investor $investor): void
    {
        $orgId = $this->currentOrg->id();
        if ($orgId !== null && $investor->organization_id !== null && $investor->organization_id !== $orgId) {
            abort(404);
        }
    }
}
