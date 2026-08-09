<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Counterparty;
use App\Services\Finance\Ageing\ApAgeingService;
use App\Services\Finance\Ageing\ArAgeingService;
use App\Services\Finance\Customers\CustomerService;
use App\Services\Finance\Suppliers\SupplierService;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Finance → People & Businesses (Phase 7, blueprint §27).
 *
 * ONE EXPERIENCE, ROLE-FILTERED TABS
 * ----------------------------------
 * Blueprint §27 asks for customers and suppliers to "become one People &
 * Businesses experience with role-filtered tabs". Today they are two unrelated
 * screens, and a farm that buys feed from someone it also sells weaners to has
 * to know which screen that person lives on. They are already one table —
 * `counterparties` has `type` with a `both` value and `customers()` /
 * `suppliers()` scopes — so the split was never in the data, only in the UI.
 *
 * THE EXISTING SCREENS ARE KEPT
 * -----------------------------
 * This is an orchestration layer, per the preservation rule. Customer detail,
 * ledger, statement, credit hold, supplier detail and supplier statements all
 * stay exactly where they are and this workspace links out to them. An
 * accountant who knows the old screens loses nothing; an owner gets one place
 * to start.
 *
 * BALANCES COME FROM THE AGEING SERVICES
 * --------------------------------------
 * "Who owes us" and "who we owe" are read from `ArAgeingService` and
 * `ApAgeingService` — the same code the ageing reports use. A second
 * calculation would eventually disagree with the first, and the version an
 * owner reads on the friendly screen is exactly the one an accountant would
 * defend from the ageing report.
 *
 * GUIDED CREATION IS NARROWER THAN THE FULL FORM
 * ----------------------------------------------
 * The existing customer form asks for currency, discount, payment terms, credit
 * limit, category, tax id and registration number. Correct for an accountant
 * onboarding a distributor; wrong for someone adding the man who buys two pigs
 * a month. Guided creation asks for a name, whether they buy or sell, and a way
 * to contact them. Everything else takes the defaults the services already
 * apply, and the full form is one click away for the cases that need it.
 */
class ContactsController extends Controller
{
    public const TAB_CUSTOMERS = 'customers';
    public const TAB_SUPPLIERS = 'suppliers';
    public const TAB_BALANCES  = 'balances';

    public const TABS = [self::TAB_CUSTOMERS, self::TAB_SUPPLIERS, self::TAB_BALANCES];

    /** Guided creation roles, mapped to the permission each one requires. */
    public const ROLE_PERMISSIONS = [
        Counterparty::TYPE_CUSTOMER => ['manage_customers'],
        Counterparty::TYPE_SUPPLIER => ['manage_suppliers'],
        Counterparty::TYPE_BOTH     => ['manage_customers', 'manage_suppliers'],
    ];

    public function __construct(
        protected CurrentOrganization $currentOrganization,
        protected ArAgeingService $arAgeing,
        protected ApAgeingService $apAgeing,
    ) {
    }

    public function index(Request $request): View
    {
        $organizationId = $this->currentOrganization->requireId();

        $tab = $request->string('tab')->toString();
        if (! in_array($tab, self::TABS, true)) {
            $tab = self::TAB_CUSTOMERS;
        }

        $search = trim($request->string('q')->toString());

        $data = [
            'tab'        => $tab,
            'search'     => $search,
            'canAddCustomer' => $request->user()?->hasPermission('manage_customers') ?? false,
            'canAddSupplier' => $request->user()?->hasPermission('manage_suppliers') ?? false,
        ];

        if ($tab === self::TAB_BALANCES) {
            // Read through the ageing services so the friendly screen and the
            // accountant's report cannot disagree.
            //
            // WHAT THE FARM OWES IS A NARROWER PERMISSION THAN WHAT IT IS OWED.
            // `finance/ageing/ap` requires `view_ap_ageing` while `finance/ageing/ar`
            // requires only `view_finance` — a deliberate existing distinction,
            // and a friendly summary that showed both to everyone would route
            // around it. The AR side is built for anyone who reached this route;
            // the AP side only for whoever may see the AP ageing report.
            $canSeePayables = $request->user()?->hasPermission('view_ap_ageing') ?? false;

            $ar = $this->arAgeing->build();

            $data['owedToUs']      = $ar->customerLines;
            $data['owedToUsTotal'] = $ar->grandTotals['total'] ?? '0.00';
            $data['canSeePayables'] = $canSeePayables;

            if ($canSeePayables) {
                $ap = $this->apAgeing->build();
                $data['owedByUs']      = $ap->customerLines;
                $data['owedByUsTotal'] = $ap->grandTotals['total'] ?? '0.00';
            }

            return view('finance.contacts.index', $data);
        }

        $query = Counterparty::query()
            ->where('organization_id', $organizationId)
            ->when($tab === self::TAB_CUSTOMERS, fn ($q) => $q->customers())
            ->when($tab === self::TAB_SUPPLIERS, fn ($q) => $q->suppliers())
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name');

        $data['contacts'] = $query->paginate(25)->withQueryString();

        return view('finance.contacts.index', $data);
    }

    /**
     * Guided creation — name, role, and a way to reach them.
     */
    public function store(Request $request, CustomerService $customers, SupplierService $suppliers): RedirectResponse
    {
        $organizationId = $this->currentOrganization->requireId();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', Rule::in(array_keys(self::ROLE_PERMISSIONS))],
            // One of the two is required. A contact with no way to reach them
            // is a name in a list: the invoice cannot be sent, the payment
            // cannot be chased, and the record earns its keep only as a label.
            'phone' => ['nullable', 'string', 'max:50', 'required_without:email'],
            'email' => ['nullable', 'email', 'max:100', 'required_without:phone'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'phone.required_without' => 'Give a phone number or an email address.',
            'email.required_without' => 'Give a phone number or an email address.',
        ]);

        // The role decides the permission, not the screen. Someone who may add
        // customers must not gain the ability to add suppliers by picking a
        // different radio button — a supplier is who the farm pays.
        foreach (self::ROLE_PERMISSIONS[$validated['role']] as $permission) {
            abort_unless($request->user()?->hasPermission($permission), 403);
        }

        $attributes = [
            'name'          => $validated['name'],
            'phone'         => $validated['phone'] ?? null,
            'email'         => $validated['email'] ?? null,
            'notes'         => $validated['notes'] ?? null,
            'type'          => $validated['role'],
            'currency_code' => 'GHS',
        ];

        // Delegated to the existing services, which own code allocation through
        // NumberingService, payment-term defaults and the counterparty profile.
        // Writing the row directly here would mint a duplicate code the first
        // time two people saved at once.
        $contact = $validated['role'] === Counterparty::TYPE_SUPPLIER
            ? $suppliers->createSupplier($attributes)
            : $customers->createCustomer($attributes);

        $route = $contact->isSupplier() && ! $contact->isCustomer()
            ? 'finance.suppliers.show'
            : 'finance.customers.show';

        return redirect()->route($route, $contact)
            ->with('success', "{$contact->name} added.");
    }
}
