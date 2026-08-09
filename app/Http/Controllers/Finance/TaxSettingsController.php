<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\TaxProfile;
use App\Models\Finance\TaxProfileComponent;
use App\Services\Finance\Tax\TaxCalculator;
use App\Services\Finance\Tax\TaxProfileResolver;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Finance → Tax settings (Phase 2 completion).
 *
 * Phase 2 built the tax engine — effective-dated profiles, same-base and
 * cascading components, decimal arithmetic, snapshots. It deferred the screen,
 * which meant a farm could not actually enter the taxes its business charges.
 * This is that screen.
 *
 * PERCENTAGE OR FIXED, BECAUSE BOTH ARE REAL
 * -------------------------------------------
 * A component is either a rate on the base or a flat amount per transaction.
 * `TaxCalculator` has always supported both; this exposes it. A farm charging
 * a fixed levy per pig needs the second, and forcing it into a percentage would
 * mean recalculating the rate for every price.
 *
 * A LIVE PREVIEW, NOT A PROMISE
 * -----------------------------
 * The screen computes what a profile would charge on GHS 100 using the real
 * `TaxCalculator` — not a simplified copy. Someone configuring VAT plus two
 * levies can see 20.00 rather than trusting that three components combine the
 * way they expect. A preview computed by different code from the posting path
 * would be worse than none.
 *
 * EFFECTIVE DATES ARE REQUIRED
 * ----------------------------
 * A profile without one cannot be resolved for a back-dated transaction, and
 * back-dating is ordinary in farm accounting. The form defaults to today and
 * leaves the end date open.
 */
class TaxSettingsController extends Controller
{
    public function __construct(
        protected TaxProfileResolver $resolver,
        protected TaxCalculator $calculator,
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    public function index(): View
    {
        $organizationId = $this->currentOrganization->get()?->id;

        $profiles = TaxProfile::query()
            ->when($organizationId, fn ($q) => $q->forOrganization($organizationId))
            ->with('components')
            ->orderByDesc('is_default_sales')
            ->orderBy('treatment')
            ->orderByDesc('effective_from')
            ->get();

        // Preview each profile on a round 100 using the real calculator, so
        // what the screen shows and what a sale posts cannot diverge.
        $previews = [];
        foreach ($profiles as $profile) {
            $previews[$profile->id] = $this->calculator->apply($profile, '100.00');
        }

        return view('finance.tax.index', [
            'profiles'  => $profiles,
            'previews'  => $previews,
            'treatments' => [
                TaxProfile::TREATMENT_STANDARD   => 'Standard — tax is charged',
                TaxProfile::TREATMENT_ZERO_RATED => 'Zero-rated — 0%, but you can still reclaim input tax',
                TaxProfile::TREATMENT_EXEMPT     => 'Exempt — 0%, and you cannot reclaim input tax',
                TaxProfile::TREATMENT_NO_TAX     => 'No tax — for a business that is not registered',
            ],
        ]);
    }

    public function storeProfile(Request $request): RedirectResponse
    {
        $organizationId = $this->currentOrganization->get()?->id;

        if ($organizationId === null) {
            return back()->withErrors(['profile' => 'No organisation context for this request.']);
        }

        $validated = $request->validate([
            'code'           => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9_]+$/'],
            'name'           => ['required', 'string', 'max:255'],
            'description'    => ['nullable', 'string', 'max:500'],
            'treatment'      => ['required', Rule::in([
                TaxProfile::TREATMENT_STANDARD, TaxProfile::TREATMENT_ZERO_RATED,
                TaxProfile::TREATMENT_EXEMPT, TaxProfile::TREATMENT_NO_TAX,
            ])],
            'applies_to'     => ['required', Rule::in([
                TaxProfile::APPLIES_SALE, TaxProfile::APPLIES_PURCHASE, TaxProfile::APPLIES_BOTH,
            ])],
            'price_mode'     => ['required', Rule::in([
                TaxProfile::PRICE_INCLUSIVE, TaxProfile::PRICE_EXCLUSIVE,
            ])],
            'effective_from' => ['required', 'date'],
            'effective_to'   => ['nullable', 'date', 'after:effective_from'],
        ], [
            'code.regex' => 'The code may only use capital letters, numbers and underscores.',
        ]);

        $duplicate = TaxProfile::query()
            ->forOrganization($organizationId)
            ->where('code', $validated['code'])
            ->whereDate('effective_from', $validated['effective_from'])
            ->exists();

        if ($duplicate) {
            return back()->withInput()->withErrors([
                'code' => 'A profile with that code already starts on that date. '
                    . 'Give it a different start date, or edit the existing one.',
            ]);
        }

        TaxProfile::create($validated + [
            'organization_id' => $organizationId,
            'is_active'       => true,
        ]);

        return redirect()->route('finance.tax.index')
            ->with('success', "Added {$validated['name']}. Now add what it charges.");
    }

    public function storeComponent(Request $request, TaxProfile $profile): RedirectResponse
    {
        $this->assertOwned($profile);

        $validated = $request->validate([
            'code'      => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9_]+$/'],
            'label'     => ['required', 'string', 'max:255'],
            'rate_type' => ['required', Rule::in([
                TaxProfileComponent::RATE_PERCENT, TaxProfileComponent::RATE_FIXED,
            ])],
            'rate'      => ['required', 'numeric', 'min:0'],
            'base_mode' => ['required', Rule::in([
                TaxProfileComponent::BASE_NET, TaxProfileComponent::BASE_COMPOUND,
            ])],
            'is_recoverable' => ['sometimes', 'boolean'],
        ], [
            'code.regex' => 'The code may only use capital letters, numbers and underscores.',
        ]);

        if ($profile->components()->where('code', $validated['code'])->exists()) {
            return back()->withInput()->withErrors([
                'code' => 'That charge is already on this profile.',
            ]);
        }

        // Rate stays a string all the way to the database. Casting to float
        // here would undo the decimal arithmetic the calculator depends on.
        $validated['rate'] = (string) $request->input('rate');

        $profile->components()->create($validated + [
            'calculation_order' => ((int) $profile->components()->max('calculation_order')) + 10,
            'is_recoverable'    => (bool) ($validated['is_recoverable'] ?? false),
        ]);

        return redirect()->route('finance.tax.index')
            ->with('success', "Added {$validated['label']} to {$profile->name}.");
    }

    public function destroyComponent(TaxProfile $profile, TaxProfileComponent $component): RedirectResponse
    {
        $this->assertOwned($profile);

        if ((int) $component->tax_profile_id !== (int) $profile->id) {
            abort(404);
        }

        $label = $component->label;
        $component->delete();

        return redirect()->route('finance.tax.index')
            ->with('success', "Removed {$label}.");
    }

    /**
     * Make a profile the default for sales, purchases, or both.
     *
     * Only one default per side per organisation, cleared in the same
     * transaction so a failure cannot leave two profiles both claiming it —
     * which would make resolution depend on row order.
     */
    public function setDefault(Request $request, TaxProfile $profile): RedirectResponse
    {
        $this->assertOwned($profile);

        $validated = $request->validate([
            'side' => ['required', Rule::in(['sales', 'purchase'])],
        ]);

        $column = $validated['side'] === 'sales' ? 'is_default_sales' : 'is_default_purchase';

        DB::transaction(function () use ($profile, $column): void {
            TaxProfile::query()
                ->forOrganization((int) $profile->organization_id)
                ->update([$column => false]);

            $profile->forceFill([$column => true])->save();
        });

        return redirect()->route('finance.tax.index')
            ->with('success', "{$profile->name} is now the default for {$validated['side']}.");
    }

    protected function assertOwned(TaxProfile $profile): void
    {
        $organizationId = $this->currentOrganization->get()?->id;

        // Fails closed: no context, or a profile belonging to another
        // organisation, both deny.
        if ($organizationId === null || (int) $profile->organization_id !== $organizationId) {
            abort(404);
        }
    }
}
