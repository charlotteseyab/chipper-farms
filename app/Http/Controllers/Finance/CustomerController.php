<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Counterparty;
use App\Models\CounterpartyAddress;
use App\Models\CounterpartyContact;
use App\Models\CustomerCategory;
use App\Services\Finance\Customers\CustomerService;
use App\Support\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Phase 2a — Customer master UI.
 *
 * Permission gates (route level):
 *   index, show: view_finance
 *   store, update, addAddress, addContact, etc.: manage_customers
 *
 * The 'manage_customers' permission is added in this batch's
 * FinancePermissionsSeeder extension. Phase 1d's permissions seeder ran
 * once; this batch adds the new permission and attaches it to admin/
 * accountant roles via a new seeder.
 */
class CustomerController extends Controller
{
    public function index(Request $request, CurrentOrganization $currentOrg)
    {
        $orgId = $currentOrg->id();

        $search = trim((string) $request->get('search'));
        $categoryId = $request->get('category');
        $showInactive = (bool) $request->get('inactive');
        $onHoldOnly = (bool) $request->get('hold');

        $query = Counterparty::query()
            ->customers()
            ->with(['category'])
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->orderBy('name');

        if (! $showInactive) {
            $query->active();
        }

        if ($onHoldOnly) {
            $query->onCreditHold();
        }

        if ($categoryId) {
            $query->where('customer_category_id', $categoryId);
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

        $customers = $query->paginate(25)->withQueryString();

        $stats = [
            'total'        => Counterparty::customers()->when($orgId, fn ($q) => $q->where('organization_id', $orgId))->count(),
            'active'       => Counterparty::customers()->active()->when($orgId, fn ($q) => $q->where('organization_id', $orgId))->count(),
            'on_hold'      => Counterparty::customers()->onCreditHold()->when($orgId, fn ($q) => $q->where('organization_id', $orgId))->count(),
            'walkin_count' => Counterparty::customers()->where('code', 'like', 'WALKIN-%')->when($orgId, fn ($q) => $q->where('organization_id', $orgId))->count(),
        ];

        $categories = CustomerCategory::active()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->get();

        return view('finance.customers.index', compact(
            'customers', 'stats', 'categories', 'search', 'categoryId', 'showInactive', 'onHoldOnly'
        ));
    }

    public function show(Counterparty $customer, CurrentOrganization $currentOrg)
    {
        $this->ensureInOrg($customer, $currentOrg);

        $customer->load(['category', 'addresses', 'contacts.counterparty', 'documents.uploadedBy', 'customerProfile']);

        return view('finance.customers.show', compact('customer'));
    }

    public function ledger(
        \App\Models\Counterparty $customer,
        \Illuminate\Http\Request $request,
        \App\Services\Finance\Allocations\CustomerLedgerService $ledgerService,
        \App\Support\CurrentOrganization $currentOrganization,
    ) {
        $orgId = $currentOrganization->resolve()?->id;
        if ($orgId !== null && $customer->organization_id !== null && (int) $customer->organization_id !== (int) $orgId) {
            abort(403);
        }

        $startDate = $request->filled('start_date')
            ? \Carbon\CarbonImmutable::parse($request->get('start_date'))
            : null;
        $endDate = $request->filled('end_date')
            ? \Carbon\CarbonImmutable::parse($request->get('end_date'))
            : null;

        $entries = $ledgerService->build($customer, $startDate, $endDate);
        $outstandingBalance = $ledgerService->outstandingBalance($customer);

        return view('finance.customers.ledger', compact('customer', 'entries', 'outstandingBalance'));
    }

    public function create(CurrentOrganization $currentOrg)
    {
        $orgId = $currentOrg->id();
        $categories = CustomerCategory::active()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->get();

        return view('finance.customers.create', compact('categories'));
    }

    public function store(Request $request, CustomerService $service, CurrentOrganization $currentOrg)
    {
        $orgId = $currentOrg->id();

        $validated = $request->validate([
            'name'                  => ['required', 'string', 'max:255'],
            'legal_name'            => ['nullable', 'string', 'max:255'],
            'code'                  => ['nullable', 'string', 'max:50',
                                         Rule::unique('counterparties', 'code')
                                             ->where(fn ($q) => $q->where('organization_id', $orgId))],
            'tax_id'                => ['nullable', 'string', 'max:50'],
            'registration_number'   => ['nullable', 'string', 'max:50'],
            'phone'                 => ['nullable', 'string', 'max:50'],
            'email'                 => ['nullable', 'email', 'max:100'],
            'address_line_1'        => ['nullable', 'string', 'max:255'],
            'city'                  => ['nullable', 'string', 'max:100'],
            'region'                => ['nullable', 'string', 'max:100'],
            'country'               => ['nullable', 'string', 'size:2'],
            'customer_category_id'  => ['nullable', 'integer',
                                         Rule::exists('customer_categories', 'id')
                                             ->where(fn ($q) => $q->where('organization_id', $orgId))],
            'currency_code'         => ['required', 'string', 'size:3'],
            'discount_percent'      => ['nullable', 'numeric', 'min:0', 'max:100'],
            'payment_terms_days'    => ['nullable', 'integer', 'min:0', 'max:365'],
            'credit_limit'          => ['nullable', 'numeric', 'min:0'],
            'type'                  => ['nullable', Rule::in(['customer', 'both'])],
            'notes'                 => ['nullable', 'string'],
        ]);

        $customer = $service->createCustomer($validated);

        return redirect()
            ->route('finance.customers.show', $customer)
            ->with('success', 'Customer created.');
    }

    public function update(Request $request, Counterparty $customer, CustomerService $service, CurrentOrganization $currentOrg)
    {
        $this->ensureInOrg($customer, $currentOrg);
        $orgId = $currentOrg->id();

        $validated = $request->validate([
            'name'                  => ['required', 'string', 'max:255'],
            'legal_name'            => ['nullable', 'string', 'max:255'],
            'tax_id'                => ['nullable', 'string', 'max:50'],
            'registration_number'   => ['nullable', 'string', 'max:50'],
            'phone'                 => ['nullable', 'string', 'max:50'],
            'email'                 => ['nullable', 'email', 'max:100'],
            'address_line_1'        => ['nullable', 'string', 'max:255'],
            'city'                  => ['nullable', 'string', 'max:100'],
            'region'                => ['nullable', 'string', 'max:100'],
            'country'               => ['nullable', 'string', 'size:2'],
            'customer_category_id'  => ['nullable', 'integer',
                                         Rule::exists('customer_categories', 'id')
                                             ->where(fn ($q) => $q->where('organization_id', $orgId))],
            'currency_code'         => ['required', 'string', 'size:3'],
            'discount_percent'      => ['nullable', 'numeric', 'min:0', 'max:100'],
            'payment_terms_days'    => ['nullable', 'integer', 'min:0', 'max:365'],
            'credit_limit'          => ['nullable', 'numeric', 'min:0'],
            'is_active'             => ['nullable', 'boolean'],
            'notes'                 => ['nullable', 'string'],
            'internal_notes'        => ['nullable', 'string'],
        ]);

        $service->updateCustomer($customer, $validated);

        return redirect()
            ->route('finance.customers.show', $customer)
            ->with('success', 'Customer updated.');
    }

    public function placeOnHold(Request $request, Counterparty $customer, CustomerService $service, CurrentOrganization $currentOrg)
    {
        $this->ensureInOrg($customer, $currentOrg);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $service->placeOnCreditHold($customer, $validated['reason']);

        return back()->with('success', 'Customer placed on credit hold.');
    }

    public function clearHold(Counterparty $customer, CustomerService $service, CurrentOrganization $currentOrg)
    {
        $this->ensureInOrg($customer, $currentOrg);

        $service->clearCreditHold($customer);

        return back()->with('success', 'Credit hold cleared.');
    }

    public function storeAddress(Request $request, Counterparty $customer, CustomerService $service, CurrentOrganization $currentOrg)
    {
        $this->ensureInOrg($customer, $currentOrg);

        $validated = $request->validate([
            'address_type'   => ['required', Rule::in(['billing', 'shipping', 'pickup', 'office', 'other'])],
            'label'          => ['nullable', 'string', 'max:100'],
            'attention_to'   => ['nullable', 'string', 'max:100'],
            'address_line_1' => ['required', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city'           => ['nullable', 'string', 'max:100'],
            'region'         => ['nullable', 'string', 'max:100'],
            'postal_code'    => ['nullable', 'string', 'max:20'],
            'country'        => ['nullable', 'string', 'size:2'],
            'ghana_post_gps' => ['nullable', 'string', 'max:20'],
            'is_default'     => ['nullable', 'boolean'],
            'notes'          => ['nullable', 'string'],
        ]);

        $service->addAddress($customer, $validated);

        return back()->with('success', 'Address added.');
    }

    public function storeContact(Request $request, Counterparty $customer, CustomerService $service, CurrentOrganization $currentOrg)
    {
        $this->ensureInOrg($customer, $currentOrg);

        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:100'],
            'role'      => ['nullable', 'string', 'max:100'],
            'phone'     => ['nullable', 'string', 'max:50'],
            'phone_alt' => ['nullable', 'string', 'max:50'],
            'email'     => ['nullable', 'email', 'max:100'],
            'whatsapp'  => ['nullable', 'string', 'max:50'],
            'is_primary'=> ['nullable', 'boolean'],
            'notes'     => ['nullable', 'string'],
        ]);

        $service->addContact($customer, $validated);

        return back()->with('success', 'Contact added.');
    }

    public function deleteAddress(CounterpartyAddress $address, CurrentOrganization $currentOrg)
    {
        $orgId = $currentOrg->id();
        if ($orgId !== null && $address->organization_id !== null && (int) $address->organization_id !== (int) $orgId) {
            abort(403);
        }

        $customer = $address->counterparty;
        $address->delete();

        return redirect()
            ->route('finance.customers.show', $customer)
            ->with('success', 'Address removed.');
    }

    public function deleteContact(CounterpartyContact $contact, CurrentOrganization $currentOrg)
    {
        $orgId = $currentOrg->id();
        if ($orgId !== null && $contact->organization_id !== null && (int) $contact->organization_id !== (int) $orgId) {
            abort(403);
        }

        $customer = $contact->counterparty;
        $contact->delete();

        return redirect()
            ->route('finance.customers.show', $customer)
            ->with('success', 'Contact removed.');
    }

    protected function ensureInOrg(Counterparty $cp, CurrentOrganization $currentOrg): void
    {
        $orgId = $currentOrg->id();
        if ($orgId !== null && $cp->organization_id !== null && (int) $cp->organization_id !== (int) $orgId) {
            abort(403, 'Customer belongs to a different organisation.');
        }
    }

    public function editCreditHold(\App\Models\Counterparty $customer)
    {
        // Reuses existing customer-edit permission. Adjust if your project has
        // a more granular `manage_customer_credit` perm.
        if (! auth()->user()?->hasPermission('edit_customer')
            && ! auth()->user()?->hasPermission('manage_customer_credit')) {
            abort(403);
        }

        return view('finance.customers.credit-hold-edit', compact('customer'));
    }

    public function updateCreditHold(\App\Models\Counterparty $customer, \Illuminate\Http\Request $request)
    {
        if (! auth()->user()?->hasPermission('edit_customer')
            && ! auth()->user()?->hasPermission('manage_customer_credit')) {
            abort(403);
        }

        $validated = $request->validate([
            'credit_hold_mode'   => ['required', 'string', 'in:none,warn,block'],
            'credit_hold_reason' => ['nullable', 'string', 'max:500'],
            'credit_limit'       => ['nullable', 'numeric', 'gte:0'],
        ]);

        $customer->update([
            'credit_hold_mode'             => $validated['credit_hold_mode'],
            'credit_hold_reason'           => $validated['credit_hold_reason'] ?? null,
            'credit_hold_set_at'           => now(),
            'credit_hold_set_by_user_id'   => $request->user()->id,
            'credit_limit'                 => $validated['credit_limit'] ?? $customer->credit_limit,
        ]);

        return redirect()
            ->route('finance.customers.show', $customer)
            ->with('success', "Credit hold mode set to '{$validated['credit_hold_mode']}'.");
    }
    
}
