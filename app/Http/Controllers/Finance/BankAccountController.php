<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\Account;
use App\Models\Finance\BankAccount;
use App\Models\PaymentMethod;
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
        $accounts = $this->eligibleLedgerAccounts($orgId)
            ->whereNotIn('id', $linked)
            ->get();

        return view('finance.bank-accounts.create', [
            'accounts'   => $accounts,
            'kinds'      => $this->accountKinds(),
            'parsers'    => $registry->picker(),
        ]);
    }

    public function store(Request $request, CurrentOrganization $currentOrg)
    {
        $this->ensureManagePermission($request);
        $orgId = $currentOrg->id();
        $data = $request->validate([
            'account_kind'         => ['required', Rule::in(array_keys($this->accountKinds()))],
            'account_id'           => ['required', 'integer',
                Rule::exists('chart_of_accounts', 'id')->where(fn ($q) => $q->where('organization_id', $orgId))],
            'label'                => ['required', 'string', 'max:120'],
            'bank_name'            => ['nullable', 'string', 'max:100'],
            'provider'             => ['nullable', 'string', 'max:80'],
            'account_name'         => ['nullable', 'string', 'max:120'],
            'account_number'       => ['nullable', 'string', 'max:64'],
            'wallet_number'        => ['nullable', 'string', 'max:40'],
            'swift_code'           => ['nullable', 'string', 'max:20'],
            'branch'               => ['nullable', 'string', 'max:100'],
            'currency_code'        => ['required', 'string', 'size:3'],
            'opening_balance'      => ['required', 'numeric'],
            'opening_balance_date' => ['required', 'date'],
            'default_csv_format'   => ['nullable', 'string', 'max:50'],
            'storefront_payment_method' => ['nullable', Rule::in([
                BankAccount::STOREFRONT_COD,
                BankAccount::STOREFRONT_PAYSTACK,
                BankAccount::STOREFRONT_MOMO,
                BankAccount::STOREFRONT_BANK_TRANSFER,
            ])],
            'notes'                => ['nullable', 'string'],
        ]);

        if (! $this->eligibleLedgerAccounts($orgId)->where('id', $data['account_id'])->exists()) {
            return back()
                ->withInput()
                ->with('error', 'Choose a cash, bank, MoMo, or payment-clearing GL account.');
        }

        $this->validateKindDetails($data);
        $data = $this->normalizeMoneyAccountData($data);

        // Refuse if a bank_account already exists for this account_id.
        $exists = BankAccount::query()
            ->where('organization_id', $orgId)
            ->where('account_id', $data['account_id'])
            ->exists();
        if ($exists) {
            return back()
                ->withInput()
                ->with('error', 'A money account is already linked to that GL account.');
        }

        $bankAccount = BankAccount::create(array_merge($data, [
            'organization_id'     => $orgId,
            'is_active'           => true,
            'created_by_user_id'  => $request->user()->id,
        ]));
        $this->syncStorefrontPaymentMethod($bankAccount->fresh('account'));

        return redirect()
            ->route('finance.bank-accounts.show', $bankAccount)
            ->with('success', "Money account '{$bankAccount->label}' created.");
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
            'kinds'       => $this->accountKinds(),
            'parsers'     => $registry->picker(),
        ]);
    }

    public function update(Request $request, BankAccount $bankAccount, CurrentOrganization $currentOrg)
    {
        $this->ensureManagePermission($request);
        $this->ensureInOrg($bankAccount, $currentOrg);
        $data = $request->validate([
            'account_kind'         => ['required', Rule::in(array_keys($this->accountKinds()))],
            'label'                => ['required', 'string', 'max:120'],
            'bank_name'            => ['nullable', 'string', 'max:100'],
            'provider'             => ['nullable', 'string', 'max:80'],
            'account_name'         => ['nullable', 'string', 'max:120'],
            'account_number'       => ['nullable', 'string', 'max:64'],
            'wallet_number'        => ['nullable', 'string', 'max:40'],
            'swift_code'           => ['nullable', 'string', 'max:20'],
            'branch'               => ['nullable', 'string', 'max:100'],
            'default_csv_format'   => ['nullable', 'string', 'max:50'],
            'storefront_payment_method' => ['nullable', Rule::in([
                BankAccount::STOREFRONT_COD,
                BankAccount::STOREFRONT_PAYSTACK,
                BankAccount::STOREFRONT_MOMO,
                BankAccount::STOREFRONT_BANK_TRANSFER,
            ])],
            'notes'                => ['nullable', 'string'],
        ]);
        $this->validateKindDetails($data);
        $data = $this->normalizeMoneyAccountData($data);
        // opening_balance + currency_code intentionally NOT editable
        // here — those affect reconciliation arithmetic.
        $bankAccount->update($data);
        $this->syncStorefrontPaymentMethod($bankAccount->fresh('account'));

        return redirect()
            ->route('finance.bank-accounts.show', $bankAccount)
            ->with('success', 'Money account updated.');
    }

    public function activate(BankAccount $bankAccount, CurrentOrganization $currentOrg)
    {
        $this->ensureManagePermission();
        $this->ensureInOrg($bankAccount, $currentOrg);
        $bankAccount->update(['is_active' => true]);
        return back()->with('success', 'Money account activated.');
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
                'Cannot deactivate: there are open reconciliations on this money account.');
        }
        $bankAccount->update(['is_active' => false]);
        return back()->with('success', 'Money account deactivated.');
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

    protected function eligibleLedgerAccounts(int $orgId)
    {
        return Account::query()
            ->where('organization_id', $orgId)
            ->where('account_type', Account::TYPE_ASSET)
            ->where('account_subtype', 'current_asset')
            ->where('is_active', true)
            ->where('allow_manual_journal', true)
            ->where(function ($q) {
                $q->where('code', 'like', '10%');
            })
            ->orderBy('code');
    }

    protected function accountKinds(): array
    {
        return [
            BankAccount::KIND_CASH    => 'Cash on hand',
            BankAccount::KIND_BANK    => 'Bank account',
            BankAccount::KIND_MOMO    => 'Mobile money account',
            BankAccount::KIND_GATEWAY => 'Payment gateway / clearing',
        ];
    }

    protected function validateKindDetails(array $data): void
    {
        $kind = $data['account_kind'] ?? BankAccount::KIND_BANK;

        if ($kind === BankAccount::KIND_BANK && trim((string) ($data['bank_name'] ?? '')) === '') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'bank_name' => 'A bank account needs the bank name.',
            ]);
        }

        if ($kind === BankAccount::KIND_MOMO && trim((string) ($data['wallet_number'] ?? '')) === '') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'wallet_number' => 'A mobile money account needs the wallet number.',
            ]);
        }
    }

    protected function normalizeMoneyAccountData(array $data): array
    {
        $kind = $data['account_kind'];

        if ($kind === BankAccount::KIND_CASH) {
            $data['bank_name'] = $data['bank_name'] ?: 'Cash';
        } elseif ($kind === BankAccount::KIND_MOMO) {
            $data['provider'] = $data['provider'] ?: 'Mobile Money';
            $data['bank_name'] = $data['bank_name'] ?: $data['provider'];
            $data['account_number'] = $data['account_number'] ?: ($data['wallet_number'] ?? null);
        } elseif ($kind === BankAccount::KIND_GATEWAY) {
            $data['provider'] = $data['provider'] ?: 'Payment Gateway';
            $data['bank_name'] = $data['bank_name'] ?: $data['provider'];
        }

        return $data;
    }

    protected function syncStorefrontPaymentMethod(BankAccount $moneyAccount): void
    {
        if (! $moneyAccount->storefront_payment_method || ! $moneyAccount->account) {
            return;
        }

        $code = match ($moneyAccount->storefront_payment_method) {
            BankAccount::STOREFRONT_PAYSTACK => 'PAYSTACK',
            BankAccount::STOREFRONT_MOMO => str_contains(strtolower((string) $moneyAccount->provider), 'at')
                ? 'MOMO_ATGO'
                : (str_contains(strtolower((string) $moneyAccount->provider), 'telecel')
                    || str_contains(strtolower((string) $moneyAccount->provider), 'voda')
                        ? 'MOMO_VODA'
                        : 'MOMO_MTN'),
            BankAccount::STOREFRONT_BANK_TRANSFER => 'BANK_TRANSFER',
            default => 'CASH',
        };

        PaymentMethod::query()->updateOrCreate(
            [
                'organization_id' => $moneyAccount->organization_id,
                'code'            => $code,
            ],
            [
                'name'               => match ($code) {
                    'PAYSTACK' => 'Paystack Online Checkout',
                    'BANK_TRANSFER' => 'Bank Transfer',
                    'MOMO_ATGO' => 'AirtelTigo Money',
                    'MOMO_VODA' => 'Telecel Cash',
                    'MOMO_MTN' => 'MTN Mobile Money',
                    default => 'Cash',
                },
                'is_cash'            => $code === 'CASH',
                'is_bank'            => in_array($code, ['BANK_TRANSFER', 'PAYSTACK'], true),
                'is_mobile_money'    => str_starts_with($code, 'MOMO_'),
                'is_credit'          => false,
                'requires_reference' => $code !== 'CASH',
                'gl_account_hint'    => (string) $moneyAccount->account->code,
                'display_order'      => $code === 'CASH' ? 10 : ($code === 'PAYSTACK' ? 33 : 20),
                'is_active'          => true,
            ],
        );
    }
}
