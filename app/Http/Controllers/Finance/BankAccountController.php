<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\Account;
use App\Models\Finance\BankAccount;
use App\Services\Finance\BankStatements\Parsers\BankStatementParserRegistry;
use App\Support\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Bank account CRUD — Phase 3e.
 *
 * Bank accounts are an overlay on a GL account that adds bank-specific
 * metadata (bank name, account number, currency, opening balance).
 *
 * Permissions:
 *   - view_bank_accounts:   read (index, show)
 *   - manage_bank_accounts: write (create, edit, activate/deactivate)
 */
class BankAccountController extends Controller
{
    public function index(Request $request, CurrentOrganization $currentOrg)
    {
        $this->ensureViewPermission($request);
        $orgId = $currentOrg->id();
        $statusFilter = $request->get('status', 'active');

        $q = BankAccount::query()
            ->with(['account', 'createdBy'])
            ->where('organization_id', $orgId)
            ->orderBy('label');

        if ($statusFilter === 'active') {
            $q->where('is_active', true);
        } elseif ($statusFilter === 'inactive') {
            $q->where('is_active', false);
        }

        $bankAccounts = $q->paginate(50)->withQueryString();

        return view('finance.bank-accounts.index', [
            'bankAccounts' => $bankAccounts,
            'statusFilter' => $statusFilter,
        ]);
    }

    public function create(CurrentOrganization $currentOrg, BankStatementParserRegistry $registry)
    {
        $this->ensureManagePermission();
        $orgId = $currentOrg->id();
        // Available bank GL accounts (Asset type, not already linked).
        $linked = BankAccount::query()
            ->where('organization_id', $orgId)
            ->pluck('account_id')
            ->all();
        $accounts = Account::query()
            ->where('organization_id', $orgId)
            ->where('account_type', Account::TYPE_ASSET)
            ->where('is_active', true)
            ->whereNotIn('id', $linked)
            ->orderBy('code')
            ->get();

        return view('finance.bank-accounts.create', [
            'accounts'   => $accounts,
            'parsers'    => $registry->picker(),
        ]);
    }

    public function store(Request $request, CurrentOrganization $currentOrg)
    {
        $this->ensureManagePermission($request);
        $orgId = $currentOrg->id();
        $data = $request->validate([
            'account_id'           => ['required', 'integer',
                Rule::exists('chart_of_accounts', 'id')->where(fn ($q) => $q->where('organization_id', $orgId))],
            'label'                => ['required', 'string', 'max:120'],
            'bank_name'            => ['required', 'string', 'max:100'],
            'account_number'       => ['nullable', 'string', 'max:64'],
            'swift_code'           => ['nullable', 'string', 'max:20'],
            'branch'               => ['nullable', 'string', 'max:100'],
            'currency_code'        => ['required', 'string', 'size:3'],
            'opening_balance'      => ['required', 'numeric'],
            'opening_balance_date' => ['required', 'date'],
            'default_csv_format'   => ['nullable', 'string', 'max:50'],
            'notes'                => ['nullable', 'string'],
        ]);

        // Refuse if a bank_account already exists for this account_id.
        $exists = BankAccount::query()
            ->where('organization_id', $orgId)
            ->where('account_id', $data['account_id'])
            ->exists();
        if ($exists) {
            return back()
                ->withInput()
                ->with('error', 'A bank account is already linked to that GL account.');
        }

        $bankAccount = BankAccount::create(array_merge($data, [
            'organization_id'     => $orgId,
            'is_active'           => true,
            'created_by_user_id'  => $request->user()->id,
        ]));

        return redirect()
            ->route('finance.bank-accounts.show', $bankAccount)
            ->with('success', "Bank account '{$bankAccount->label}' created.");
    }

    public function show(BankAccount $bankAccount, CurrentOrganization $currentOrg)
    {
        $this->ensureViewPermission();
        $this->ensureInOrg($bankAccount, $currentOrg);

        $bankAccount->load(['account', 'createdBy']);

        $recentStatements = $bankAccount->statements()
            ->latest('imported_at')
            ->limit(10)
            ->get();
        $recentRecons = $bankAccount->reconciliations()
            ->latest('period_to')
            ->limit(10)
            ->get();

        return view('finance.bank-accounts.show', [
            'bankAccount'      => $bankAccount,
            'recentStatements' => $recentStatements,
            'recentRecons'     => $recentRecons,
        ]);
    }

    public function edit(BankAccount $bankAccount, CurrentOrganization $currentOrg, BankStatementParserRegistry $registry)
    {
        $this->ensureManagePermission();
        $this->ensureInOrg($bankAccount, $currentOrg);
        return view('finance.bank-accounts.edit', [
            'bankAccount' => $bankAccount,
            'parsers'     => $registry->picker(),
        ]);
    }

    public function update(Request $request, BankAccount $bankAccount, CurrentOrganization $currentOrg)
    {
        $this->ensureManagePermission($request);
        $this->ensureInOrg($bankAccount, $currentOrg);
        $data = $request->validate([
            'label'                => ['required', 'string', 'max:120'],
            'bank_name'            => ['required', 'string', 'max:100'],
            'account_number'       => ['nullable', 'string', 'max:64'],
            'swift_code'           => ['nullable', 'string', 'max:20'],
            'branch'               => ['nullable', 'string', 'max:100'],
            'default_csv_format'   => ['nullable', 'string', 'max:50'],
            'notes'                => ['nullable', 'string'],
        ]);
        // opening_balance + currency_code intentionally NOT editable
        // here — those affect reconciliation arithmetic.
        $bankAccount->update($data);

        return redirect()
            ->route('finance.bank-accounts.show', $bankAccount)
            ->with('success', 'Bank account updated.');
    }

    public function activate(BankAccount $bankAccount, CurrentOrganization $currentOrg)
    {
        $this->ensureManagePermission();
        $this->ensureInOrg($bankAccount, $currentOrg);
        $bankAccount->update(['is_active' => true]);
        return back()->with('success', 'Bank account activated.');
    }

    public function deactivate(BankAccount $bankAccount, CurrentOrganization $currentOrg)
    {
        $this->ensureManagePermission();
        $this->ensureInOrg($bankAccount, $currentOrg);
        // Refuse if there are open reconciliations.
        $hasOpen = $bankAccount->reconciliations()
            ->whereIn('status', ['draft', 'in_progress'])
            ->exists();
        if ($hasOpen) {
            return back()->with('error',
                'Cannot deactivate: there are open reconciliations on this bank account.');
        }
        $bankAccount->update(['is_active' => false]);
        return back()->with('success', 'Bank account deactivated.');
    }

    protected function ensureInOrg(BankAccount $bankAccount, CurrentOrganization $currentOrg): void
    {
        $orgId = $currentOrg->id();
        if ($bankAccount->organization_id !== $orgId) {
            abort(404);
        }
    }

    protected function ensureViewPermission(?Request $request = null): void
    {
        $user = $request?->user() ?? auth()->user();
        if (
            ! $user?->hasPermission('view_bank_accounts')
            && ! $user?->hasPermission('manage_bank_accounts')
            && ! $user?->hasPermission('view_finance')
        ) {
            abort(403, 'Missing permission: view_bank_accounts');
        }
    }

    protected function ensureManagePermission(?Request $request = null): void
    {
        $user = $request?->user() ?? auth()->user();
        if (! $user?->hasPermission('manage_bank_accounts')) {
            abort(403, 'Missing permission: manage_bank_accounts');
        }
    }
}
