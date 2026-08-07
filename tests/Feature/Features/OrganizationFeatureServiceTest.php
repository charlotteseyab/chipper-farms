<?php

namespace Tests\Feature\Features;

use App\Models\Organization;
use App\Models\OrganizationFeatureFlag;
use App\Models\Role;
use App\Models\User;
use App\Services\Organizations\OrganizationFeatureService;
use App\Support\CurrentOrganization;
use App\Support\Features\FeatureCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Organisation feature flags — Phase 1 (blueprint §32).
 *
 * The tests that matter here are not "can we write a boolean". They are the
 * three ways a flag layer goes wrong in production:
 *
 *   1. an empty table silently switches off working functionality;
 *   2. one tenant's configuration answers for another;
 *   3. a dependency graph is half-applied, so a menu offers a screen whose
 *      prerequisites are off.
 */
class OrganizationFeatureServiceTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;
    protected Organization $other;
    protected User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'slug' => 'cf-' . uniqid(), 'name' => 'Chipper Farms', 'is_active' => true,
        ]);
        $this->other = Organization::create([
            'slug' => 'other-' . uniqid(), 'name' => 'Other Farm', 'is_active' => true,
        ]);

        $this->app->make(CurrentOrganization::class)->bind($this->org);

        $this->actor = User::forceCreate([
            'name' => 'Admin', 'email' => 'admin_' . uniqid() . '@example.com',
            'password' => bcrypt('x'), 'role' => 'admin',
            'role_id' => Role::firstOrCreate(['name' => 'admin'])->id,
            'organization_id' => $this->org->id, 'active_status' => true,
        ]);

        // These organisations are created after the backfill migration ran, so
        // they have no rows. That is the interesting starting state.
        OrganizationFeatureFlag::query()->delete();
    }

    protected function service(): OrganizationFeatureService
    {
        return app(OrganizationFeatureService::class);
    }

    /* ------------------------------------------------------------------ */
    /* Absent row semantics                                               */
    /* ------------------------------------------------------------------ */

    public function test_absent_row_resolves_to_catalogue_default_not_false(): void
    {
        $this->assertSame(0, OrganizationFeatureFlag::count());

        $this->assertTrue(
            $this->service()->enabled(FeatureCatalog::FINANCE_QUICK_EXPENSE),
            'An unconfigured essential feature must resolve to its catalogue default. '
            . 'Resolving an empty table to false would switch off working functionality '
            . 'the moment this table is created.',
        );

        $this->assertFalse(
            $this->service()->enabled(FeatureCatalog::FINANCE_WHT),
            'An unconfigured advanced feature must resolve to its default, which is off.',
        );
    }

    public function test_unknown_feature_key_is_rejected_rather_than_silently_false(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service()->enabled('finance.not_a_real_feature');
    }

    /* ------------------------------------------------------------------ */
    /* Writing and audit                                                  */
    /* ------------------------------------------------------------------ */

    public function test_enable_persists_state_and_records_the_actor(): void
    {
        $this->service()->enable(
            FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING,
            actor: $this->actor,
        );

        $row = OrganizationFeatureFlag::where('organization_id', $this->org->id)
            ->where('feature_key', FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING)
            ->firstOrFail();

        $this->assertTrue($row->enabled);
        $this->assertSame($this->actor->id, $row->enabled_by);
        $this->assertNotNull($row->enabled_at);
    }

    public function test_disable_records_who_turned_it_off(): void
    {
        $svc = $this->service();
        $svc->enable(FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING, actor: $this->actor);
        $svc->disable(FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING, actor: $this->actor);

        $row = OrganizationFeatureFlag::where('organization_id', $this->org->id)
            ->where('feature_key', FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING)
            ->firstOrFail();

        $this->assertFalse($row->enabled);
        $this->assertSame($this->actor->id, $row->disabled_by);
        $this->assertNotNull($row->disabled_at);
        $this->assertNotNull(
            $row->enabled_at,
            'The enable timestamp must survive a later disable — "when was this last on" '
            . 'is a question an auditor asks.',
        );
    }

    public function test_configuration_round_trips(): void
    {
        $this->service()->enable(
            FeatureCatalog::STOREFRONT_GUEST_CHECKOUT,
            actor: $this->actor,
            configuration: ['hold_minutes' => 15],
        );

        $this->assertSame(
            ['hold_minutes' => 15],
            $this->service()->configuration(FeatureCatalog::STOREFRONT_GUEST_CHECKOUT),
        );
    }

    /* ------------------------------------------------------------------ */
    /* Dependencies                                                       */
    /* ------------------------------------------------------------------ */

    public function test_enabling_a_leaf_feature_pulls_in_its_whole_chain(): void
    {
        // WHT requires Advanced AP, which requires Simple Purchase.
        $touched = $this->service()->enable(FeatureCatalog::FINANCE_WHT, actor: $this->actor);

        $this->assertTrue($this->service()->enabled(FeatureCatalog::FINANCE_WHT));
        $this->assertTrue(
            $this->service()->enabled(FeatureCatalog::FINANCE_ADVANCED_AP),
            'Withholding tax certifies supplier payments. Enabling it without Advanced '
            . 'Payables would offer certificates for payments the farm cannot make.',
        );
        $this->assertTrue($this->service()->enabled(FeatureCatalog::FINANCE_SIMPLE_PURCHASE));

        $this->assertSame(FeatureCatalog::FINANCE_WHT, end($touched),
            'Dependencies must be written before the feature that needs them.');
    }

    public function test_disabling_a_parent_cascades_to_everything_that_depends_on_it(): void
    {
        $svc = $this->service();
        $svc->enable(FeatureCatalog::FINANCE_WHT, actor: $this->actor);

        $svc->disable(FeatureCatalog::FINANCE_ADVANCED_AP, actor: $this->actor);

        $this->assertFalse($svc->enabled(FeatureCatalog::FINANCE_ADVANCED_AP));
        $this->assertFalse(
            $svc->enabled(FeatureCatalog::FINANCE_WHT),
            'Leaving WHT on after its parent is off would describe a configuration '
            . 'the application cannot honour.',
        );
    }

    public function test_usable_is_false_when_an_ancestor_is_off_even_if_the_leaf_row_says_true(): void
    {
        $svc = $this->service();
        $svc->enable(FeatureCatalog::FINANCE_WHT, actor: $this->actor);

        // Force an inconsistent state directly, bypassing the cascade — this is
        // what a hand-edited row or a partially-applied older release looks like.
        OrganizationFeatureFlag::where('organization_id', $this->org->id)
            ->where('feature_key', FeatureCatalog::FINANCE_ADVANCED_AP)
            ->update(['enabled' => false]);
        $svc->forget();

        $this->assertTrue($svc->enabled(FeatureCatalog::FINANCE_WHT));
        $this->assertFalse(
            $svc->usable(FeatureCatalog::FINANCE_WHT),
            'usable() must walk the chain, so an inconsistent row cannot open a surface '
            . 'whose prerequisites are off.',
        );
    }

    /* ------------------------------------------------------------------ */
    /* Organisation isolation                                             */
    /* ------------------------------------------------------------------ */

    public function test_enabling_for_one_organisation_does_not_affect_another(): void
    {
        $svc = $this->service();

        $svc->enable(FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING, $this->org, $this->actor);

        $this->assertTrue($svc->enabled(FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING, $this->org));
        $this->assertFalse(
            $svc->enabled(FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING, $this->other),
            'This is the defect that made a dedicated table necessary: system_configs has '
            . 'no organization_id, so a flag stored there would answer for every tenant.',
        );
    }

    public function test_the_cache_does_not_answer_one_organisation_with_anothers_state(): void
    {
        $svc = $this->service();

        // Warm the cache for both organisations, then change only one.
        $svc->enabled(FeatureCatalog::FINANCE_WHT, $this->org);
        $svc->enabled(FeatureCatalog::FINANCE_WHT, $this->other);

        $svc->enable(FeatureCatalog::FINANCE_WHT, $this->org, $this->actor);

        $this->assertTrue($svc->enabled(FeatureCatalog::FINANCE_WHT, $this->org));
        $this->assertFalse($svc->enabled(FeatureCatalog::FINANCE_WHT, $this->other));
    }

    public function test_writes_invalidate_the_cache(): void
    {
        $svc = $this->service();

        $this->assertFalse($svc->enabled(FeatureCatalog::FINANCE_WHT));

        $svc->enable(FeatureCatalog::FINANCE_WHT, actor: $this->actor);

        $this->assertTrue(
            $svc->enabled(FeatureCatalog::FINANCE_WHT),
            'A stale read after a write is exactly the defect a static memo caused '
            . 'elsewhere in this codebase.',
        );
    }

    public function test_changing_flags_without_an_organisation_context_is_refused(): void
    {
        $this->app->make(CurrentOrganization::class)->bind(null);

        $this->expectException(\RuntimeException::class);

        $this->service()->enable(FeatureCatalog::FINANCE_WHT, actor: $this->actor);
    }

    /* ------------------------------------------------------------------ */
    /* Catalogue integrity                                                */
    /* ------------------------------------------------------------------ */

    public function test_every_catalogue_dependency_points_at_a_real_feature(): void
    {
        foreach (FeatureCatalog::keys() as $key) {
            foreach (FeatureCatalog::requires($key) as $parent) {
                $this->assertTrue(
                    FeatureCatalog::isKnown($parent),
                    "Feature [{$key}] depends on unknown feature [{$parent}].",
                );
            }
        }
    }

    public function test_the_dependency_graph_has_no_cycles(): void
    {
        foreach (FeatureCatalog::keys() as $key) {
            // requiresDeep() would recurse forever on a cycle; reaching the
            // assertion at all is the real check.
            $chain = FeatureCatalog::requiresDeep($key);

            $this->assertNotContains(
                $key,
                $chain,
                "Feature [{$key}] transitively depends on itself.",
            );
        }
    }
}
