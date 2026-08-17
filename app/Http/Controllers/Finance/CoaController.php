<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\Account;
use App\Models\Finance\JournalLine;
use App\Support\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Phase 1d — Chart of Accounts management.
 *
 * Permission gates (applied at route level):
 *   index/show: view_finance
 *   store/update/toggleActive: manage_coa
 *
 * Activation/deactivation is the supported "delete" mechanism — accounts
 * with historical journal lines cannot be hard-deleted (FK restrict).
 * The is_system flag prevents renaming or deactivation of essential
 * accounts (Retained Earnings, Suspense, etc.) — they're reserved for
 * Phase 2+ posting code that hardcodes them.
 */
class CoaController extends Controller
{
    public function index(Request $request, CurrentOrganization $currentOrg)
    {
        $orgId = $currentOrg->id();
        $type = $request->get('type');
        $search = trim((string) $request->get('search'));
        $showInactive = (bool) $request->get('inactive');

        $query = Account::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->orderBy('code');

        if (! $showInactive) {
            $query->where('is_active', true);
        }

        if ($type && in_array($type, ['asset', 'liability', 'equity', 'revenue', 'expense'], true)) {
            $query->where('account_type', $type);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $accounts = $query->paginate(50)->withQueryString();

        $referenceAccounts = Account::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'description', 'account_type', 'account_subtype', 'is_control', 'is_system', 'is_active']);

        $stats = [
            'total'     => Account::when($orgId, fn ($q) => $q->where('organization_id', $orgId))->count(),
            'active'    => Account::when($orgId, fn ($q) => $q->where('organization_id', $orgId))->where('is_active', true)->count(),
            'inactive'  => Account::when($orgId, fn ($q) => $q->where('organization_id', $orgId))->where('is_active', false)->count(),
            'control'   => Account::when($orgId, fn ($q) => $q->where('organization_id', $orgId))->where('is_control', true)->count(),
        ];

        $postingGuide = $this->postingGuide($referenceAccounts);

        return view('finance.coa.index', compact('accounts', 'stats', 'type', 'search', 'showInactive', 'referenceAccounts', 'postingGuide'));
    }

    public function show(Account $account, CurrentOrganization $currentOrg)
    {
        $this->ensureAccountInOrg($account, $currentOrg);

        $recentLines = JournalLine::with(['journal:id,journal_number,posting_date,status,description'])
            ->where('account_id', $account->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return view('finance.coa.show', [
            'account'      => $account,
            'recentLines'  => $recentLines,
        ]);
    }

    public function store(Request $request, CurrentOrganization $currentOrg)
    {
        $orgId = $currentOrg->id();

        $validated = $request->validate([
            'code'                 => ['required', 'string', 'max:20',
                                       Rule::unique('chart_of_accounts', 'code')
                                           ->where(fn ($q) => $q->where('organization_id', $orgId))],
            'name'                 => ['required', 'string', 'max:200'],
            'description'          => ['nullable', 'string', 'max:1000'],
            'account_type'         => ['required', Rule::in(['asset', 'liability', 'equity', 'revenue', 'expense'])],
            'account_subtype'      => ['nullable', 'string', 'max:50'],
            'parent_id'            => ['nullable', 'integer',
                                       Rule::exists('chart_of_accounts', 'id')
                                           ->where(fn ($q) => $q->where('organization_id', $orgId))],
            'currency_code'        => ['required', 'string', 'size:3'],
            'is_control'           => ['nullable', 'boolean'],
            'allow_manual_journal' => ['nullable', 'boolean'],
        ]);

        Account::create([
            'organization_id'      => $orgId,
            'code'                 => $validated['code'],
            'name'                 => $validated['name'],
            'description'          => $validated['description'] ?? null,
            'account_type'         => $validated['account_type'],
            'account_subtype'      => $validated['account_subtype'] ?? null,
            'parent_id'            => $validated['parent_id'] ?? null,
            'currency_code'        => $validated['currency_code'],
            'is_control'           => (bool) ($validated['is_control'] ?? false),
            'is_active'            => true,
            'is_system'            => false,
            'allow_manual_journal' => (bool) ($validated['allow_manual_journal'] ?? true),
        ]);

        return redirect()
            ->route('finance.coa.index')
            ->with('success', 'Account created.');
    }

    public function update(Request $request, Account $account, CurrentOrganization $currentOrg)
    {
        $this->ensureAccountInOrg($account, $currentOrg);

        if ($account->is_system) {
            return redirect()
                ->route('finance.coa.show', $account)
                ->with('error', 'System accounts cannot be modified through the UI.');
        }

        $validated = $request->validate([
            'name'                 => ['required', 'string', 'max:200'],
            'description'          => ['nullable', 'string', 'max:1000'],
            'account_subtype'      => ['nullable', 'string', 'max:50'],
            'allow_manual_journal' => ['nullable', 'boolean'],
        ]);

        // Code, account_type, currency_code are intentionally NOT editable
        // once the account exists. Changing them would break historical
        // journal references and report classifications.

        $account->update([
            'name'                 => $validated['name'],
            'description'          => $validated['description'] ?? null,
            'account_subtype'      => $validated['account_subtype'] ?? null,
            'allow_manual_journal' => (bool) ($validated['allow_manual_journal'] ?? $account->allow_manual_journal),
        ]);

        return redirect()
            ->route('finance.coa.show', $account)
            ->with('success', 'Account updated.');
    }

    public function toggleActive(Account $account, CurrentOrganization $currentOrg)
    {
        $this->ensureAccountInOrg($account, $currentOrg);

        if ($account->is_system) {
            return back()->with('error', 'System accounts cannot be deactivated.');
        }

        $account->update(['is_active' => ! $account->is_active]);

        return back()->with('success', $account->is_active ? 'Account activated.' : 'Account deactivated.');
    }

    protected function ensureAccountInOrg(Account $account, CurrentOrganization $currentOrg): void
    {
        $orgId = $currentOrg->id();
        if ($orgId !== null && $account->organization_id !== null && (int) $account->organization_id !== (int) $orgId) {
            abort(403, 'Account belongs to a different organisation.');
        }
    }

    protected function postingGuide($accounts): array
    {
        $byCode = $accounts->keyBy('code');

        $row = function (string $area, string $use, string $code, string $fallback = '') use ($byCode): array {
            $account = $byCode->get($code);

            return [
                'area'     => $area,
                'use'      => $use,
                'code'     => $code,
                'name'     => $account?->name,
                'status'   => $account ? ($account->is_active ? 'ready' : 'inactive') : 'missing',
                'fallback' => $fallback,
            ];
        };

        return [
            $row('Money in', 'Cash collected at pickup or counter sales', '1010'),
            $row('Money in', 'Main bank deposits, bank transfers, and current Paystack fallback', '1030', 'Create a dedicated Paystack clearing account if settlement needs separate tracking.'),
            $row('Money in', 'MTN mobile money receipts', '1060'),
            $row('Money in', 'Receipts that could not be mapped to a real cash/bank account', '1910', 'Suspense only; reclassify after fixing payment-method setup.'),
            $row('Receivables', 'Customer invoices from pig sales on credit', '1110'),
            $row('Receivables', 'Customer invoices from feed sales on credit', '1120'),
            $row('Storefront', 'Customer money received before fulfilment/confirmation', '2310'),
            $row('Revenue', 'Pig sales revenue', '4010'),
            $row('Revenue', 'Feed sales revenue', '4120'),
            $row('COGS', 'Cost of pigs sold', '5110'),
            $row('COGS', 'Feed cost of goods sold', '5100'),
        ];
    }
}
