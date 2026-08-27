<?php

namespace App\Storefront\Support;

use App\Models\Organization;
use App\Models\Storefront\StorefrontDeliveryZone;
use App\Models\Storefront\StorefrontSetting;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Support\Collection;

/**
 * Resolves the storefront settings row for an organisation (Phase 8, §34).
 *
 * A SINGLETON, NOT A STATIC MEMO
 * ------------------------------
 * Deliberately shaped like `OrganizationFeatureService`. A static cache keyed
 * by organisation would outlive the rows it describes: an earlier defect in
 * this codebase (`EnsureFinanceAccess`) memoised statically and went stale
 * under `RefreshDatabase` and Octane. The container is rebuilt per request and
 * per test, so instance state cannot leak across either boundary — and every
 * write here invalidates the entry it touched.
 *
 * ABSENT MEANS "NOT CONFIGURED"
 * -----------------------------
 * `for()` never returns null. An organisation with no row gets an unsaved model
 * carrying the code defaults, so a farm that has never opened the settings
 * screen still has a working shop and no caller has to null-check.
 *
 * THE GUEST FALLBACK IS THE EXISTING ONE
 * --------------------------------------
 * `StorefrontController` already resolved an unauthenticated visitor to the
 * first organisation so the public catalogue renders. That rule now lives here
 * instead of being duplicated per controller, so the page a guest sees and the
 * settings that page reads cannot describe different organisations.
 */
class StorefrontSettingsResolver
{
    /** @var array<int, StorefrontSetting> */
    protected array $cache = [];

    /** @var array<int, Collection<int, StorefrontDeliveryZone>> */
    protected array $zoneCache = [];

    protected ?int $publicOrganizationId = null;
    protected bool $publicResolved = false;

    public function __construct(
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    /**
     * Which organisation the storefront is currently serving.
     *
     * An authenticated user's organisation wins. An unauthenticated visitor
     * falls back to the first organisation, which is what the public shop has
     * always done — single-tenant in practice for Chipper, and a public
     * catalogue that renders nothing at all is worse than one that renders the
     * only farm on the installation.
     */
    public function organizationId(): ?int
    {
        $bound = $this->currentOrganization->get();

        if ($bound !== null) {
            return (int) $bound->id;
        }

        if (! $this->publicResolved) {
            $this->publicOrganizationId = Organization::query()->orderBy('id')->value('id');
            $this->publicResolved       = true;
        }

        return $this->publicOrganizationId;
    }

    /**
     * The settings for an organisation — persisted row, or an unsaved model
     * carrying defaults when none exists.
     */
    public function for(Organization|int|null $organization = null): StorefrontSetting
    {
        $organizationId = $this->resolveId($organization);

        if ($organizationId === null) {
            return $this->defaults(null);
        }

        if (! isset($this->cache[$organizationId])) {
            // The explicit organization argument is authoritative here. This
            // resolver also serves guest storefronts and platform reports,
            // where the organization being resolved can differ from a bound
            // staff context; retaining the implicit scope would silently fall
            // back to defaults for the requested farm.
            $this->cache[$organizationId] = StorefrontSetting::withoutGlobalScope('current_organization')
                ->where('organization_id', $organizationId)
                ->first()
                ?? $this->defaults($organizationId);
        }

        return $this->cache[$organizationId];
    }

    /**
     * Active delivery zones, cheapest-first by display order.
     *
     * @return Collection<int, StorefrontDeliveryZone>
     */
    public function zones(Organization|int|null $organization = null): Collection
    {
        $organizationId = $this->resolveId($organization);

        if ($organizationId === null) {
            return collect();
        }

        if (! isset($this->zoneCache[$organizationId])) {
            $this->zoneCache[$organizationId] = StorefrontDeliveryZone::withoutGlobalScope('current_organization')
                ->where('organization_id', $organizationId)
                ->active()
                ->ordered()
                ->get();
        }

        return $this->zoneCache[$organizationId];
    }

    /**
     * Persist a settings change.
     *
     * `updateOrCreate` on the organisation, which the unique index backs, so
     * two administrators saving at once cannot leave duplicate rows whose
     * resolution depends on row order.
     */
    public function save(array $attributes, Organization|int|null $organization = null, ?User $actor = null): StorefrontSetting
    {
        $organizationId = $this->resolveId($organization);

        if ($organizationId === null) {
            throw new \RuntimeException('Cannot save storefront settings without an organisation context.');
        }

        $settings = StorefrontSetting::withoutGlobalScope('current_organization')->updateOrCreate(
            ['organization_id' => $organizationId],
            $attributes + ['updated_by' => $actor?->id],
        );

        $this->forget($organizationId);

        return $settings;
    }

    public function forget(?int $organizationId = null): void
    {
        if ($organizationId === null) {
            $this->cache     = [];
            $this->zoneCache = [];

            return;
        }

        unset($this->cache[$organizationId], $this->zoneCache[$organizationId]);
    }

    protected function defaults(?int $organizationId): StorefrontSetting
    {
        return new StorefrontSetting([
            'organization_id'       => $organizationId,
            'public_shop_open'      => true,
            'store_name'            => null,
            'default_currency'      => 'GHS',
            'tax_display_mode'      => StorefrontSetting::TAX_DISPLAY_EXCLUSIVE,
            'checkout_hold_minutes' => 30,
        ]);
    }

    protected function resolveId(Organization|int|null $organization): ?int
    {
        if ($organization instanceof Organization) {
            return (int) $organization->id;
        }

        if (is_int($organization)) {
            return $organization;
        }

        return $this->organizationId();
    }
}
