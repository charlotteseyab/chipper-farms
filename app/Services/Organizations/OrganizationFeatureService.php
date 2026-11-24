<?php

namespace App\Services\Organizations;

use App\Models\Organization;
use App\Models\OrganizationFeatureFlag;
use App\Models\User;
use App\Support\CurrentOrganization;
use App\Support\Features\FeatureCatalog;
use App\Support\Features\ModulePreset;
use Illuminate\Support\Facades\DB;

/**
 * The single place that answers "is this feature on for this organisation?"
 * (Phase 1, blueprint §32).
 *
 * A FEATURE FLAG IS NOT AN AUTHORIZATION CONTROL
 * -----------------------------------------------
 * Stated in the blueprint, in the prompt, and repeated here because this is the
 * class people will reach for. The required access rule is:
 *
 *     feature enabled AND permission granted AND organisation scope valid
 *
 * `enabled()` answers only the first clause. It must never be the only check in
 * front of a money or data operation. Permissions and policies stay
 * authoritative; the flag decides whether a surface is offered, not whether a
 * user is allowed to use it.
 *
 * CACHING AND WHY IT IS SHAPED THIS WAY
 * -------------------------------------
 * Navigation resolves many flags per request, so an uncached implementation
 * would issue a query per menu item. The cache here is:
 *
 *   - per instance, not static. An earlier defect in this codebase
 *     (EnsureFinanceAccess) used a static memo keyed by role and went stale
 *     under RefreshDatabase and Octane. Static state outlives the thing it
 *     describes; instance state does not.
 *   - keyed by organisation id, so resolving one tenant cannot answer for
 *     another.
 *   - invalidated on every write through this service.
 *
 * The service is registered as a singleton, which in Laravel means one instance
 * per request and one per test (the container is rebuilt between tests), so the
 * cache cannot leak across either boundary. There is a test that asserts this.
 *
 * ABSENT ROW RESOLVES TO THE CATALOGUE DEFAULT
 * ---------------------------------------------
 * Not to false. An empty table must not switch off 193 migrations' worth of
 * existing functionality the moment it is created.
 */
class OrganizationFeatureService
{
    /**
     * organisation id => (feature key => enabled)
     *
     * @var array<int, array<string, bool>>
     */
    protected array $cache = [];

    public function __construct(
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    /* ------------------------------------------------------------------ */
    /* Reading                                                            */
    /* ------------------------------------------------------------------ */

    public function enabled(string $featureKey, Organization|int|null $organization = null): bool
    {
        FeatureCatalog::assertKnown($featureKey);

        $organizationId = $this->resolveOrganizationId($organization);

        // No organisation context (unauthenticated CLI, public shop before an
        // organisation is bound) resolves to the catalogue default. Failing
        // closed here would black out the public storefront; the permission
        // layer is what protects anything that matters.
        if ($organizationId === null) {
            return FeatureCatalog::defaultFor($featureKey);
        }

        if (! isset($this->cache[$organizationId])) {
            $this->cache[$organizationId] = $this->loadOrganization($organizationId);
        }

        return $this->cache[$organizationId][$featureKey]
            ?? FeatureCatalog::defaultFor($featureKey);
    }

    public function disabled(string $featureKey, Organization|int|null $organization = null): bool
    {
        return ! $this->enabled($featureKey, $organization);
    }

    /**
     * All features that must be true for this one to be usable, plus the
     * feature itself. Used by middleware so a route guarded on a leaf feature
     * also fails when an ancestor is off.
     *
     * @return list<string>
     */
    public function dependencies(string $featureKey): array
    {
        return FeatureCatalog::requiresDeep($featureKey);
    }

    /**
     * True only when the feature and its entire dependency chain are enabled.
     */
    public function usable(string $featureKey, Organization|int|null $organization = null): bool
    {
        if (! $this->enabled($featureKey, $organization)) {
            return false;
        }

        foreach ($this->dependencies($featureKey) as $parent) {
            if (! $this->enabled($parent, $organization)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Per-organisation configuration payload for a feature.
     *
     * @return array<string, mixed>
     */
    public function configuration(string $featureKey, Organization|int|null $organization = null): array
    {
        FeatureCatalog::assertKnown($featureKey);

        $organizationId = $this->resolveOrganizationId($organization);

        if ($organizationId === null) {
            return [];
        }

        $row = OrganizationFeatureFlag::query()
            ->forOrganization($organizationId)
            ->where('feature_key', $featureKey)
            ->first();

        return $row?->configuration ?? [];
    }

    /**
     * Resolved state of every catalogue feature for an organisation.
     *
     * @return array<string, bool>
     */
    public function map(Organization|int|null $organization = null): array
    {
        $out = [];
        foreach (FeatureCatalog::keys() as $key) {
            $out[$key] = $this->enabled($key, $organization);
        }

        return $out;
    }

    /* ------------------------------------------------------------------ */
    /* Writing                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Enable a feature, pulling in whatever it depends on.
     *
     * Enabling Withholding Tax without Advanced Payables would produce a menu
     * item that leads to certificates for payments the organisation cannot
     * make. The dependency closure is applied rather than validated so the
     * caller is not forced to know the graph.
     *
     * @return list<string> every key actually turned on, dependencies first
     */
    public function enable(
        string $featureKey,
        Organization|int|null $organization = null,
        ?User $actor = null,
        array $configuration = [],
        ?string $preset = null,
    ): array {
        FeatureCatalog::assertKnown($featureKey);

        $organizationId = $this->requireOrganizationId($organization);

        $chain = array_merge(
            array_reverse(FeatureCatalog::requiresDeep($featureKey)),
            [$featureKey],
        );

        DB::transaction(function () use ($chain, $organizationId, $actor, $featureKey, $configuration, $preset): void {
            foreach ($chain as $key) {
                $this->writeState(
                    $organizationId,
                    $key,
                    true,
                    $actor,
                    $key === $featureKey ? $configuration : null,
                    $preset,
                );
            }
        });

        $this->forget($organizationId);

        return $chain;
    }

    /**
     * Disable a feature and everything that depends on it.
     *
     * The cascade is the point. Leaving a dependant enabled when its parent is
     * off is how a settings screen ends up describing a configuration the
     * application cannot honour.
     *
     * @return list<string> every key actually turned off
     */
    public function disable(
        string $featureKey,
        Organization|int|null $organization = null,
        ?User $actor = null,
        ?string $preset = null,
    ): array {
        FeatureCatalog::assertKnown($featureKey);

        $organizationId = $this->requireOrganizationId($organization);

        $chain = array_merge([$featureKey], FeatureCatalog::dependentsDeep($featureKey));

        DB::transaction(function () use ($chain, $organizationId, $actor, $preset): void {
            foreach ($chain as $key) {
                $this->writeState($organizationId, $key, false, $actor, null, $preset);
            }
        });

        $this->forget($organizationId);

        return $chain;
    }

    /**
     * Apply a named preset.
     *
     * Additive by default: the preset's features are turned on and nothing else
     * is touched. Pass $exclusive to also turn off every non-overlay feature the
     * preset does not list — a genuine reset, which the UI must confirm before
     * requesting, because it can remove an accountant's working setup.
     *
     * @return array{enabled: list<string>, disabled: list<string>}
     */
    public function applyPreset(
        string $presetCode,
        Organization|int|null $organization = null,
        ?User $actor = null,
        bool $exclusive = false,
        ?array $resetOnly = null,
    ): array {
        ModulePreset::assertKnown($presetCode);

        $organizationId = $this->requireOrganizationId($organization);
        $wanted         = ModulePreset::enables($presetCode);

        // Expand to the dependency closure so an exclusive apply cannot switch
        // off something the preset's own features need.
        $required = [];
        foreach ($wanted as $key) {
            foreach (FeatureCatalog::requiresDeep($key) as $parent) {
                $required[$parent] = true;
            }
            $required[$key] = true;
        }
        $required = array_keys($required);

        $turnedOff = [];

        DB::transaction(function () use ($required, $organizationId, $actor, $presetCode, $exclusive, $resetOnly, &$turnedOff): void {
            foreach ($required as $key) {
                $this->writeState($organizationId, $key, true, $actor, null, $presetCode);
            }

            if (! $exclusive) {
                return;
            }

            // $resetOnly limits the reset to those features (a finance setup
            // leaves the shop alone).
            foreach ($resetOnly ?? FeatureCatalog::keys() as $key) {
                // Only what is on is switched off, so the result names real changes.
                if (in_array($key, $required, true) || ! $this->enabled($key, $organizationId)) {
                    continue;
                }
                $this->writeState($organizationId, $key, false, $actor, null, $presetCode);
                $turnedOff[] = $key;
            }
        });

        $this->forget($organizationId);

        return ['enabled' => $required, 'disabled' => $turnedOff];
    }

    /**
     * The preset an organisation is currently on, plus how far it has drifted.
     *
     * @return array{preset: ?string, drift: list<string>}
     */
    public function currentPreset(Organization|int|null $organization = null): array
    {
        $organizationId = $this->resolveOrganizationId($organization);

        if ($organizationId === null) {
            return ['preset' => null, 'drift' => []];
        }

        $preset = OrganizationFeatureFlag::query()
            ->forOrganization($organizationId)
            ->whereNotNull('applied_preset')
            ->orderByDesc('updated_at')
            ->value('applied_preset');

        if ($preset === null || ! ModulePreset::isKnown($preset)) {
            return ['preset' => null, 'drift' => []];
        }

        $expected = ModulePreset::enables($preset);
        $drift    = [];

        foreach ($expected as $key) {
            if (! $this->enabled($key, $organizationId)) {
                $drift[] = $key;
            }
        }

        return ['preset' => $preset, 'drift' => $drift];
    }

    /* ------------------------------------------------------------------ */
    /* Cache control                                                      */
    /* ------------------------------------------------------------------ */

    public function forget(?int $organizationId = null): void
    {
        if ($organizationId === null) {
            $this->cache = [];

            return;
        }

        unset($this->cache[$organizationId]);
    }

    /* ------------------------------------------------------------------ */
    /* Internals                                                          */
    /* ------------------------------------------------------------------ */

    /** @return array<string, bool> */
    protected function loadOrganization(int $organizationId): array
    {
        return OrganizationFeatureFlag::query()
            ->forOrganization($organizationId)
            ->pluck('enabled', 'feature_key')
            ->map(static fn ($v): bool => (bool) $v)
            ->all();
    }

    protected function writeState(
        int $organizationId,
        string $featureKey,
        bool $enabled,
        ?User $actor,
        ?array $configuration,
        ?string $preset,
    ): void {
        $now = now();

        $attributes = [
            'enabled'        => $enabled,
            'applied_preset' => $preset,
        ];

        if ($configuration !== null) {
            $attributes['configuration'] = $configuration;
        }

        if ($enabled) {
            $attributes['enabled_by'] = $actor?->id;
            $attributes['enabled_at'] = $now;
        } else {
            $attributes['disabled_by'] = $actor?->id;
            $attributes['disabled_at'] = $now;
        }

        OrganizationFeatureFlag::updateOrCreate(
            ['organization_id' => $organizationId, 'feature_key' => $featureKey],
            $attributes,
        );
    }

    protected function resolveOrganizationId(Organization|int|null $organization): ?int
    {
        if ($organization instanceof Organization) {
            return (int) $organization->id;
        }

        if (is_int($organization)) {
            return $organization;
        }

        return $this->currentOrganization->get()?->id;
    }

    protected function requireOrganizationId(Organization|int|null $organization): int
    {
        $id = $this->resolveOrganizationId($organization);

        if ($id === null) {
            throw new \RuntimeException(
                'Cannot change feature flags without an organisation context.'
            );
        }

        return $id;
    }
}
