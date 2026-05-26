<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\BankAccount;
use App\Models\Finance\BankReconciliation;
use App\Services\Finance\BankReconciliation\BankReconciliationService;
use App\Support\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Bank reconciliation controller — Phase 3e.
 *
 * Permissions:
 *   - reconcile_bank_statement: required for ALL routes here
 *     (creating a reconciliation IS the reconcile action; only
 *     treasurer/admin/superuser do this).
 */
class BankReconciliationController extends Controller
{
    public function index(Request $request, CurrentOrganization $currentOrg)
    {
        $this->ensureReconcilePermission($request);
        $orgId = $currentOrg->id();
        $bankAccountId = $request->get('bank_account_id');
        $statusFilter  = $request->get('status', 'open');

        $q = BankReconciliation::query()
            ->with(['bankAccount.account', 'createdBy', 'reconciledBy'])
            ->where('organization_id', $orgId)
            ->orderByDesc('period_to')
            ->orderByDesc('id');

        if ($bankAccountId) {
            $q->where('bank_account_id', $bankAccountId);
        }
        if ($statusFilter === 'open') {
            $q->whereIn('status', [
                BankReconciliation::STATUS_DRAFT,
                BankReconciliation::STATUS_IN_PROGRESS,
            ]);
        } elseif ($statusFilter === 'closed') {
            $q->whereIn('status', [
                BankReconciliation::STATUS_RECONCILED,
                BankReconciliation::STATUS_FLAGGED,
            ]);
        } elseif ($statusFilter !== 'all'
            && in_array($statusFilter, BankReconciliation::ALL_STATUSES, true)) {
            $q->where('status', $statusFilter);
        }

        $recons = $q->paginate(50)->withQueryString();

        $bankAccounts = BankAccount::active()
            ->where('organization_id', $orgId)
            ->orderBy('label')
            ->get();

        return view('finance.bank-reconciliation.index', [
            'recons'        => $recons,
            'bankAccounts'  => $bankAccounts,
            'bankAccountId' => $bankAccountId,
            'statusFilter'  => $statusFilter,
        ]);
    }

    public function create(Request $request, CurrentOrganization $currentOrg)
    {
        $this->ensureReconcilePermission($request);
        $orgId = $currentOrg->id();
        $bankAccounts = BankAccount::active()
            ->where('organization_id', $orgId)
            ->orderBy('label')
            ->get();

        $bankAccountId = $request->get('bank_account_id');
        $bankAccount = $bankAccountId
            ? $bankAccounts->firstWhere('id', (int) $bankAccountId)
            : null;

        return view('finance.bank-reconciliation.create', [
            'bankAccounts' => $bankAccounts,
            'bankAccount'  => $bankAccount,
        ]);
    }

    public function store(
        Request $request,
        CurrentOrganization $currentOrg,
        BankReconciliationService $service,
    ) {
        $this->ensureReconcilePermission($request);
        $orgId = $currentOrg->id();
        $data = $request->validate([
            'bank_account_id'           => ['required', 'integer',
                Rule::exists('bank_accounts', 'id')->where(fn ($q) => $q->where('organization_id', $orgId))],
            'period_from'               => ['required', 'date'],
            'period_to'                 => ['required', 'date', 'after_or_equal:period_from'],
            'closing_balance_per_bank'  => ['required', 'numeric'],
            'opening_balance_per_bank'  => ['nullable', 'numeric'],
        ]);

        $bankAccount = BankAccount::query()
            ->where('organization_id', $orgId)
            ->findOrFail($data['bank_account_id']);

        try {
            $rec = $service->createDraft(
                $bankAccount,
                $data['period_from'],
                $data['period_to'],
                (string) $data['closing_balance_per_bank'],
                $request->user(),
                isset($data['opening_balance_per_bank'])
                    ? (string) $data['opening_balance_per_bank']
                    : null,
            );
        } catch (\DomainException | \InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('finance.bank-reconciliation.show', $rec)
            ->with('success', "Reconciliation {$rec->reconciliation_number} created.");
    }

    public function show(BankReconciliation $bankReconciliation, CurrentOrganization $currentOrg)
    {
        $this->ensureReconcilePermission();
        $this->ensureInOrg($bankReconciliation, $currentOrg);
        $bankReconciliation->load([
            'bankAccount.account',
            'statements',
            'createdBy',
            'reconciledBy',
            'flaggedBy',
            'archivedBy',
            'clearedPayments',
            'clearedReceipts',
        ]);

        // Pull the lines we'd display in the workbench.
        $allLines = \App\Models\Finance\BankStatementLine::query()
            ->with('matchedBy')
            ->whereHas('bankStatement', function ($q) use ($bankReconciliation) {
                $q->where('bank_account_id', $bankReconciliation->bank_account_id)
                  ->where('status', '!=', \App\Models\Finance\BankStatement::STATUS_VOID)
                  ->whereDate('period_from', '>=', $bankReconciliation->period_from)
                  ->whereDate('period_to',   '<=', $bankReconciliation->period_to);
            })
            ->orderBy('value_date')
            ->orderBy('id')
            ->get();

        return view('finance.bank-reconciliation.show', [
            'rec'      => $bankReconciliation,
            'allLines' => $allLines,
        ]);
    }

    public function refresh(
        BankReconciliation $bankReconciliation,
        CurrentOrganization $currentOrg,
        BankReconciliationService $service,
    ) {
        $this->ensureReconcilePermission();
        $this->ensureInOrg($bankReconciliation, $currentOrg);
        try {
            $service->refreshArithmetic($bankReconciliation);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', 'Arithmetic refreshed.');
    }

    public function reconcile(
        BankReconciliation $bankReconciliation,
        Request $request,
        CurrentOrganization $currentOrg,
        BankReconciliationService $service,
    ) {
        $this->ensureReconcilePermission($request);
        $this->ensureInOrg($bankReconciliation, $currentOrg);
        try {
            $service->reconcile($bankReconciliation, $request->user());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', "Reconciliation {$bankReconciliation->reconciliation_number} closed.");
    }

    public function flag(
        BankReconciliation $bankReconciliation,
        Request $request,
        CurrentOrganization $currentOrg,
        BankReconciliationService $service,
    ) {
        $this->ensureReconcilePermission($request);
        $this->ensureInOrg($bankReconciliation, $currentOrg);
        $data = $request->validate([
            'flag_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        try {
            $service->flag($bankReconciliation, $request->user(), $data['flag_reason']);
        } catch (\DomainException | \InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', 'Reconciliation flagged.');
    }

    public function reopen(
        BankReconciliation $bankReconciliation,
        Request $request,
        CurrentOrganization $currentOrg,
        BankReconciliationService $service,
    ) {
        $this->ensureReconcilePermission($request);
        $this->ensureInOrg($bankReconciliation, $currentOrg);
        $data = $request->validate([
            'reopen_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        try {
            $service->reopen($bankReconciliation, $request->user(), $data['reopen_reason']);
        } catch (\DomainException | \InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', 'Reconciliation reopened.');
    }

    public function archive(
        BankReconciliation $bankReconciliation,
        Request $request,
        CurrentOrganization $currentOrg,
        BankReconciliationService $service,
    ) {
        $this->ensureReconcilePermission($request);
        $this->ensureInOrg($bankReconciliation, $currentOrg);
        $data = $request->validate([
            'archive_reason' => ['required', 'string', 'min:1', 'max:1000'],
        ]);
        try {
            $service->archive($bankReconciliation, $request->user(), $data['archive_reason']);
        } catch (\DomainException | \InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', 'Reconciliation archived.');
    }

    protected function ensureInOrg(BankReconciliation $rec, CurrentOrganization $currentOrg): void
    {
        $orgId = $currentOrg->id();
        if ($rec->organization_id !== $orgId) abort(404);
    }

    protected function ensureReconcilePermission(?Request $request = null): void
    {
        $user = $request?->user() ?? auth()->user();
        if (! $user?->hasPermission('reconcile_bank_statement')) {
            abort(403, 'Missing permission: reconcile_bank_statement');
        }
    }
}
