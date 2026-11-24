<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Finance\TaxProfile;
use App\Models\Storefront\StorefrontDeliveryZone;
use App\Models\Storefront\StorefrontSetting;
use App\Services\Organizations\OrganizationFeatureService;
use App\Storefront\Support\StorefrontSettings;
use App\Storefront\Support\StorefrontSettingsResolver;
use App\Support\CurrentOrganization;
use App\Support\Features\FeatureCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Storefront → Settings (Phase 8, blueprint §34).
 *
 * WHAT THIS SCREEN IS FOR
 * -----------------------
 * Phase 1 added thirteen `storefront.*` feature flags and, until this phase,
 * enforced none of them. Phase 8 made them real — a switched-off category
 * disappears from the shop, a switched-off payment method is refused at
 * checkout. A screen that lets a farm see and change those switches in the
 * place it thinks about its shop is the other half of that: enforcement without
 * a control surface is just an outage nobody can explain.
 *
 * ONE SWITCH PER BEHAVIOUR
 * ------------------------
 * The toggles here write **feature flags**, through
 * `OrganizationFeatureService`, rather than to a second copy in
 * `storefront_settings`. Duplicating them would give one behaviour two
 * switches and an administrator who turns COD on here and finds it still off on
 * the Modules screen. The values that flags have no room for — store name,
 * pickup address, hold minutes, refund policy, delivery zones — live in the
 * settings row.
 *
 * The one apparent exception is the open/closed sign, which is a settings
 * column rather than the `storefront.enabled` flag. It is not a duplicate: the
 * flag decides whether the organisation has a storefront at all, and because
 * every other storefront flag depends on it, switching it off here would
 * cascade and destroy the farm's payment and category choices for the sake of
 * a seasonal pause. Modules owns "do you have a shop"; this owns "is it open".
 *
 * PERMISSION, NOT FEATURE FLAG
 * ----------------------------
 * Gated on `manage_storefront_settings` — granted to admin and superuser only —
 * and, like the rest of the shop back office, on `feature:storefront.enabled`.
 * This screen used to stay open with the module off, to explain why the shop
 * was dark; but only the platform superuser can switch the module back on
 * (Settings → Modules & Features, per farm), so a farm without a shop has
 * nothing to do here. Orders stay open for orders still being fulfilled.
 */
class StorefrontSettingsController extends Controller
{
    /**
     * The flags this screen owns, grouped as the farm thinks about them.
     *
     * `storefront.enabled` is absent on purpose — see the class docblock.
     *
     * @var array<string, list<string>>
     */
    protected const TOGGLE_GROUPS = [
        'catalog' => [
            FeatureCatalog::STOREFRONT_LIVE_PIGS,
            FeatureCatalog::STOREFRONT_PIGLETS,
            FeatureCatalog::STOREFRONT_BREEDING_STOCK,
            FeatureCatalog::STOREFRONT_FEED_SALES,
            FeatureCatalog::STOREFRONT_MANURE_SALES,
            FeatureCatalog::STOREFRONT_SERVICES,
        ],
        'payments' => [
            FeatureCatalog::STOREFRONT_PAYSTACK,
            FeatureCatalog::STOREFRONT_COD,
        ],
        'checkout' => [
            FeatureCatalog::STOREFRONT_DELIVERY,
            FeatureCatalog::STOREFRONT_GUEST_CHECKOUT,
            FeatureCatalog::STOREFRONT_CUSTOMER_ACCOUNTS,
            FeatureCatalog::STOREFRONT_QUOTES,
        ],
    ];

    public function __construct(
        protected OrganizationFeatureService $features,
        protected StorefrontSettingsResolver $resolver,
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    public function index(): View
    {
        $organizationId = $this->currentOrganization->requireId();

        $settings = $this->resolver->for($organizationId);

        $flags = [];
        foreach (self::TOGGLE_GROUPS as $group => $keys) {
            foreach ($keys as $key) {
                $flags[$key] = [
                    'group'       => $group,
                    'label'       => FeatureCatalog::all()[$key]['label'] ?? $key,
                    'description' => FeatureCatalog::all()[$key]['description'] ?? '',
                    'enabled'     => $this->features->enabled($key, $organizationId),
                ];
            }
        }

        return view('storefront.admin.settings', [
            'settings'       => $settings,
            'flags'          => $flags,
            'groups'         => array_keys(self::TOGGLE_GROUPS),
            'moduleEnabled'  => StorefrontSettings::moduleEnabled($organizationId),
            'zones'          => StorefrontDeliveryZone::query()
                ->where('organization_id', $organizationId)
                ->ordered()->get(),
            'taxProfiles'    => TaxProfile::query()
                ->where('organization_id', $organizationId)
                ->orderByDesc('is_default_sales')
                ->orderByDesc('effective_from')
                ->get(),
            'paystack'       => StorefrontSettings::paystackHealth(),
            'taxDisplayModes' => [
                StorefrontSetting::TAX_DISPLAY_EXCLUSIVE => 'Exclusive — show prices before tax, add it at checkout',
                StorefrontSetting::TAX_DISPLAY_INCLUSIVE => 'Inclusive — the price shown already contains tax',
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $organizationId = $this->currentOrganization->requireId();

        $validated = $request->validate([
            'public_shop_open' => ['nullable', 'boolean'],
            'store_name'       => ['nullable', 'string', 'max:150'],
            'support_email'    => ['nullable', 'email', 'max:190'],
            'support_phone'    => ['nullable', 'string', 'max:60'],
            'default_currency' => ['required', 'string', 'size:3', 'alpha'],
            'pickup_address'   => ['nullable', 'string', 'max:1000'],
            'hero_headline'    => ['nullable', 'string', 'max:255'],
            'hero_subheadline' => ['nullable', 'string', 'max:255'],
            'tax_display_mode' => ['required', Rule::in(StorefrontSetting::TAX_DISPLAY_MODES)],

            // Scoped to this organisation. A bare `exists:tax_profiles,id`
            // would let a posted id point the shop at another farm's tax
            // profile, which is a cross-tenant defect wearing a settings form.
            'default_tax_profile_id' => [
                'nullable',
                'integer',
                Rule::exists('tax_profiles', 'id')->where('organization_id', $organizationId),
            ],

            'checkout_hold_minutes' => [
                'required', 'integer',
                'min:' . StorefrontSetting::MIN_HOLD_MINUTES,
                'max:' . StorefrontSetting::MAX_HOLD_MINUTES,
            ],
            'refund_policy' => ['nullable', 'string', 'max:5000'],

            'features'   => ['nullable', 'array'],
            'features.*' => ['string'],
        ], [
            'checkout_hold_minutes.min' => 'A hold shorter than :min minutes expires while the buyer is still paying.',
            'checkout_hold_minutes.max' => 'A hold longer than a day parks stock nobody is buying.',
            'default_tax_profile_id.exists' => 'Choose a tax profile that belongs to your farm.',
        ]);

        $this->resolver->save([
            'public_shop_open'       => $request->boolean('public_shop_open'),
            'store_name'             => $validated['store_name'] ?? null,
            'support_email'          => $validated['support_email'] ?? null,
            'support_phone'          => $validated['support_phone'] ?? null,
            'default_currency'       => strtoupper($validated['default_currency']),
            'pickup_address'         => $validated['pickup_address'] ?? null,
            'hero_headline'          => $validated['hero_headline'] ?? null,
            'hero_subheadline'       => $validated['hero_subheadline'] ?? null,
            'tax_display_mode'       => $validated['tax_display_mode'],
            'default_tax_profile_id' => $validated['default_tax_profile_id'] ?? null,
            'checkout_hold_minutes'  => (int) $validated['checkout_hold_minutes'],
            'refund_policy'          => $validated['refund_policy'] ?? null,
        ], $organizationId, $request->user());

        $this->applyToggles((array) ($validated['features'] ?? []), $organizationId, $request);

        return redirect()->route('storefront.admin.settings.index')
            ->with('success', 'Storefront settings saved.');
    }

    public function storeZone(Request $request): RedirectResponse
    {
        $organizationId = $this->currentOrganization->requireId();

        $validated = $request->validate([
            'name'        => [
                'required', 'string', 'max:120',
                Rule::unique('storefront_delivery_zones', 'name')->where('organization_id', $organizationId),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            // Decimal string, never a float: the value lands on
            // storefront_orders.delivery_fee and is added with bcadd.
            'fee'         => ['required', 'numeric', 'min:0', 'max:9999999999999999'],
        ], [
            'name.unique' => 'You already have a delivery area with that name.',
        ]);

        StorefrontDeliveryZone::create([
            'organization_id' => $organizationId,
            'name'            => $validated['name'],
            'description'     => $validated['description'] ?? null,
            'fee'             => bcadd((string) $validated['fee'], '0', 2),
            'is_active'       => true,
            'display_order'   => (int) StorefrontDeliveryZone::query()
                ->where('organization_id', $organizationId)->max('display_order') + 1,
        ]);

        $this->resolver->forget($organizationId);

        return redirect()->route('storefront.admin.settings.index')
            ->with('success', "Delivery area \"{$validated['name']}\" added.");
    }

    public function updateZone(Request $request, StorefrontDeliveryZone $zone): RedirectResponse
    {
        $organizationId = $this->currentOrganization->requireId();

        // Route-model binding resolves by id alone, so the organisation check
        // is the only thing between one farm and another farm's delivery
        // pricing. 404 rather than 403: another tenant's row should not be
        // discoverable (prompt §118).
        abort_unless((int) $zone->organization_id === $organizationId, 404);

        $validated = $request->validate([
            'name'        => [
                'required', 'string', 'max:120',
                Rule::unique('storefront_delivery_zones', 'name')
                    ->where('organization_id', $organizationId)
                    ->ignore($zone->id),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'fee'         => ['required', 'numeric', 'min:0', 'max:9999999999999999'],
            'is_active'   => ['nullable', 'boolean'],
        ]);

        $zone->update([
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? null,
            'fee'         => bcadd((string) $validated['fee'], '0', 2),
            'is_active'   => $request->boolean('is_active'),
        ]);

        $this->resolver->forget($organizationId);

        return redirect()->route('storefront.admin.settings.index')
            ->with('success', "Delivery area \"{$zone->name}\" updated.");
    }

    public function destroyZone(Request $request, StorefrontDeliveryZone $zone): RedirectResponse
    {
        $organizationId = $this->currentOrganization->requireId();

        abort_unless((int) $zone->organization_id === $organizationId, 404);

        $name = $zone->name;

        // Orders keep `delivery_zone_name`, so history still explains what an
        // order was charged and why after the zone is gone; the foreign key
        // nulls the id rather than blocking or cascading.
        $zone->delete();

        $this->resolver->forget($organizationId);

        return redirect()->route('storefront.admin.settings.index')
            ->with('success', "Delivery area \"{$name}\" removed.");
    }

    /**
     * Reconcile the submitted checkboxes against current flag state.
     *
     * Only keys this screen owns are touched, and only when they actually
     * change — writing every key on every save would stamp `enabled_by` and
     * `applied_preset` on rows nobody edited and make the audit trail useless
     * for answering "who turned this off".
     *
     * @param  list<string>  $submitted
     */
    protected function applyToggles(array $submitted, int $organizationId, Request $request): void
    {
        $owned = array_merge(...array_values(self::TOGGLE_GROUPS));

        foreach ($owned as $key) {
            $wanted  = in_array($key, $submitted, true);
            $current = $this->features->enabled($key, $organizationId);

            if ($wanted === $current) {
                continue;
            }

            if ($wanted) {
                $this->features->enable($key, $organizationId, $request->user());
            } else {
                $this->features->disable($key, $organizationId, $request->user());
            }
        }
    }
}
