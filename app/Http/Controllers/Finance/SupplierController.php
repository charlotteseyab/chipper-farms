<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Counterparty;
use App\Models\Finance\Account;
use App\Models\Finance\SupplierCategory;
use App\Models\PaymentMethod;
use App\Services\Finance\Suppliers\SupplierService;
use App\Support\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Phase 3a — Supplier master UI.
 *
 * Permissions:
 *   index, show: view_finance
 *   create, store, edit, update, deactivate: manage_suppliers
 *
 * The 'manage_suppliers' permission is added in this batch's seeder.
 */
class SupplierController extends Controller
{
    public function index(Request $request, CurrentOrganization $currentOrg)
    {
        $orgId = $currentOrg->id();

        $search = trim((string) $request->get('search'));
        $categoryId = $request->get('category');
        $showInactive = (bool) $request->get('inactive');

        $query = Counterparty::query()
            ->suppliers()
            ->with(['supplierCategory'])
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->orderBy('name');

        if (! $showInactive) {
            $query->active();
        }

        if ($categoryId) {
            $query->where('supplier_category_id', $categoryId);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('tax_id', 'like', "%{$search}%");
            });
        }

        $suppliers = $query->paginate(25)->withQueryString();

        $categories = SupplierCategory::active()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->orderBy('name')
            ->get();

        $counts = [
            'total'    => Counterparty::suppliers()
                ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))->count(),
            'active'   => Counterparty::suppliers()->active()
                ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))->count(),
            'inactive' => Counterparty::suppliers()
                ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
                ->where('is_active', false)->count(),
        ];

        return view('finance.suppliers.index', compact('suppliers', 'categories', 'counts', 'search', 'categoryId', 'showInactive'));
    }

    public function show(Counterparty $supplier, CurrentOrganization $currentOrg)
    {
        $this->ensureIsSupplier($supplier);
        $this->ensureInOrg($supplier, $currentOrg);

        $supplier->load([
            'supplierCategory',
            'defaultExpenseAccount',
            'defaultPaymentMethodForAp',
            'addresses',
            'contacts',
        ]);

        return view('finance.suppliers.show', compact('supplier'));
    }

    public function create(CurrentOrganization $currentOrg)
    {
        $orgId = $currentOrg->id();

        return view('finance.suppliers.create', [
            'categories'     => $this->categoriesFor($orgId),
            'expenseAccounts'=> $this->expenseAccountsFor($orgId),
            'paymentMethods' => $this->paymentMethodsFor($orgId),
            'momoProviders'  => ['MTN', 'Vodafone', 'AirtelTigo'],
        ]);
    }

    public function store(Request $request, SupplierService $service, CurrentOrganization $currentOrg)
    {
        $orgId = $currentOrg->id();

        $validated = $request->validate([
            'name'                              => ['required', 'string', 'max:200'],
            'legal_name'                        => ['nullable', 'string', 'max:200'],
            'tax_id'                            => ['nullable', 'string', 'max:50'],
            'registration_number'               => ['nullable', 'string', 'max:50'],
            'code'                              => ['nullable', 'string', 'max:30',
                Rule::unique('counterparties', 'code')
                    ->where(fn ($q) => $q->where('organization_id', $orgId))
                    ->whereNull('deleted_at')],
            'type'                              => ['nullable', Rule::in(['supplier', 'both'])],
            'supplier_category_id'              => ['nullable', 'integer',
                Rule::exists('supplier_categories', 'id')
                    ->where(fn ($q) => $q->where('organization_id', $orgId))],
            'default_expense_account_id'        => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'default_payment_method_for_ap_id'  => ['nullable', 'integer', 'exists:payment_methods,id'],
            'currency_code'                     => ['nullable', 'string', 'size:3'],
            'payment_terms_days'                => ['nullable', 'integer', 'min:0', 'max:365'],
            'phone'                             => ['nullable', 'string', 'max:30'],
            'email'                             => ['nullable', 'email', 'max:200'],
            'address_line_1'                    => ['nullable', 'string', 'max:200'],
            'city'                              => ['nullable', 'string', 'max:100'],
            'region'                            => ['nullable', 'string', 'max:100'],
            'country'                           => ['nullable', 'string', 'max:2'],
            'bank_account_name'                 => ['nullable', 'string', 'max:200'],
            'bank_account_number'               => ['nullable', 'string', 'max:50'],
            'bank_name'                         => ['nullable', 'string', 'max:100'],
            'bank_branch'                       => ['nullable', 'string', 'max:100'],
            'bank_swift'                        => ['nullable', 'string', 'max:20'],
            'momo_provider'                     => ['nullable', Rule::in(['MTN', 'Vodafone', 'AirtelTigo'])],
            'momo_number'                       => ['nullable', 'string', 'max:30'],
            'momo_account_name'                 => ['nullable', 'string', 'max:200'],
            'withholding_percent'               => ['nullable', 'numeric', 'min:0', 'max:100'],
            'withholding_exempt'                => ['nullable', 'boolean'],
            'is_active'                         => ['nullable', 'boolean'],
            'notes'                             => ['nullable', 'string', 'max:2000'],
            'internal_notes'                    => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $supplier = $service->createSupplier($validated);
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('finance.suppliers.show', $supplier)
            ->with('success', "Supplier {$supplier->code} — {$supplier->name} created.");
    }

    public function edit(Counterparty $supplier, CurrentOrganization $currentOrg)
    {
        $this->ensureIsSupplier($supplier);
        $this->ensureInOrg($supplier, $currentOrg);

        $orgId = $currentOrg->id();

        return view('finance.suppliers.edit', [
            'supplier'        => $supplier,
            'categories'      => $this->categoriesFor($orgId),
            'expenseAccounts' => $this->expenseAccountsFor($orgId),
            'paymentMethods'  => $this->paymentMethodsFor($orgId),
            'momoProviders'   => ['MTN', 'Vodafone', 'AirtelTigo'],
        ]);
    }

    public function update(Request $request, Counterparty $supplier, SupplierService $service, CurrentOrganization $currentOrg)
    {
        $this->ensureIsSupplier($supplier);
        $this->ensureInOrg($supplier, $currentOrg);

        $validated = $request->validate([
            'name'                              => ['required', 'string', 'max:200'],
            'legal_name'                        => ['nullable', 'string', 'max:200'],
            'tax_id'                            => ['nullable', 'string', 'max:50'],
            'registration_number'               => ['nullable', 'string', 'max:50'],
            'type'                              => ['nullable', Rule::in(['supplier', 'both', 'customer'])],
            'supplier_category_id'              => ['nullable', 'integer', 'exists:supplier_categories,id'],
            'default_expense_account_id'        => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'default_payment_method_for_ap_id'  => ['nullable', 'integer', 'exists:payment_methods,id'],
            'currency_code'                     => ['nullable', 'string', 'size:3'],
            'payment_terms_days'                => ['nullable', 'integer', 'min:0', 'max:365'],
            'phone'                             => ['nullable', 'string', 'max:30'],
            'email'                             => ['nullable', 'email', 'max:200'],
            'bank_account_name'                 => ['nullable', 'string', 'max:200'],
            'bank_account_number'               => ['nullable', 'string', 'max:50'],
            'bank_name'                         => ['nullable', 'string', 'max:100'],
            'bank_branch'                       => ['nullable', 'string', 'max:100'],
            'bank_swift'                        => ['nullable', 'string', 'max:20'],
            'momo_provider'                     => ['nullable', Rule::in(['MTN', 'Vodafone', 'AirtelTigo'])],
            'momo_number'                       => ['nullable', 'string', 'max:30'],
            'momo_account_name'                 => ['nullable', 'string', 'max:200'],
            'withholding_percent'               => ['nullable', 'numeric', 'min:0', 'max:100'],
            'withholding_exempt'                => ['nullable', 'boolean'],
            'is_active'                         => ['nullable', 'boolean'],
            'notes'                             => ['nullable', 'string', 'max:2000'],
            'internal_notes'                    => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $service->updateSupplier($supplier, $validated);
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('finance.suppliers.show', $supplier)
            ->with('success', 'Supplier updated.');
    }

    public function deactivate(Request $request, Counterparty $supplier, SupplierService $service, CurrentOrganization $currentOrg)
    {
        $this->ensureIsSupplier($supplier);
        $this->ensureInOrg($supplier, $currentOrg);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $service->deactivate($supplier, $validated['reason'] ?? null);

        return redirect()
            ->route('finance.suppliers.show', $supplier)
            ->with('success', 'Supplier deactivated. Historical records preserved.');
    }

    /* internals */

    protected function ensureIsSupplier(Counterparty $cp): void
    {
        if (! $cp->isSupplier()) {
            abort(404, 'Counterparty is not a supplier.');
        }
    }

    protected function ensureInOrg(Counterparty $cp, CurrentOrganization $currentOrg): void
    {
        $orgId = $currentOrg->id();
        if ($orgId !== null && $cp->organization_id !== null && (int) $cp->organization_id !== (int) $orgId) {
            abort(403, 'Supplier belongs to a different organisation.');
        }
    }

    protected function categoriesFor(?int $orgId)
    {
        return SupplierCategory::active()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->orderBy('name')
            ->get();
    }

    protected function expenseAccountsFor(?int $orgId)
    {
        return Account::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->whereIn('account_type', [Account::TYPE_EXPENSE, Account::TYPE_ASSET])
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'account_type']);
    }

    protected function paymentMethodsFor(?int $orgId)
    {
        return PaymentMethod::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('is_cash', true)
                  ->orWhere('is_bank', true)
                  ->orWhere('is_mobile_money', true);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'is_cash', 'is_bank', 'is_mobile_money']);
    }
}
