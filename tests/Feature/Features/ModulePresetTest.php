<?php

namespace Tests\Feature\Features;

use App\Models\Organization;
use App\Models\OrganizationFeatureFlag;
use App\Models\Role;
use App\Models\User;
use App\Services\Organizations\OrganizationFeatureService;
use App\Support\CurrentOrganization;
use App\Support\Features\FeatureCatalog;
use App\Support\Features\ModulePreset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module presets — Phase 1 (blueprint §33).
 *
 * The risk a preset introduces is destruction. "Apply Storefront Seller" must
 * not silently remove an accountant's ledger access, and the backfill must not
 * hide functionality a current customer already depends on. Those two
 * properties are what these tests protect.
 */
class ModulePresetTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;
    protected User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'slug' => 'cf-' . uniqid(), 'name' => 'Chipper Farms', 'is_active' => true,
        ]);
        $this->app->make(CurrentOrganization::class)->bind($this->org);

        $this->actor = User::forceCreate([
            'name' => 'Admin', 'email' => 'admin_' . uniqid() . '@example.com',
            'password' => bcrypt('x'), 'role' => 'admin',
            'role_id' => Role::firstOrCreate(['name' => 'admin'])->id,
            'organization_id' => $this->org->id, 'active_status' => true,
        ]);

        OrganizationFeatureFlag::query()->delete();
    }

    protected function service(): OrganizationFeatureService
    {
        return app(OrganizationFeatureService::class);
    }

    /* ------------------------------------------------------------------ */

    public function test_essentials_enables_the_everyday_farm_surface(): void
    {
        $this->service()->applyPreset(ModulePreset::ESSENTIALS, actor: $this->actor);

        foreach ([
            FeatureCatalog::FINANCE_OVERVIEW,
            FeatureCatalog::FINANCE_QUICK_EXPENSE,
            FeatureCatalog::FINANCE_SIMPLE_PURCHASE,
            FeatureCatalog::FINANCE_CASH_BANK_MOMO,
            FeatureCatalog::FINANCE_LOANS,
            FeatureCatalog::STOREFRONT_ENABLED,
        ] as $key) {
            $this->assertTrue($this->service()->enabled($key), "Essentials must enable {$key}.");
        }
    }

    public function test_essentials_leaves_the_ledger_hidden(): void
    {
        $this->service()->applyPreset(ModulePreset::ESSENTIALS, actor: $this->actor, exclusive: true);

        foreach ([
            FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING,
            FeatureCatalog::FINANCE_PERIOD_CLOSE,
            FeatureCatalog::FINANCE_WHT,
            FeatureCatalog::FINANCE_INVESTOR_MANAGEMENT,
        ] as $key) {
            $this->assertFalse(
                $this->service()->enabled($key),
                "Essentials is for a farm running on cash; {$key} must stay hidden.",
            );
        }
    }

    public function test_full_accounting_is_a_superset_of_growing_farm_which_is_a_superset_of_essentials(): void
    {
        $essentials = ModulePreset::enables(ModulePreset::ESSENTIALS);
        $growing    = ModulePreset::enables(ModulePreset::GROWING_FARM);
        $full       = ModulePreset::enables(ModulePreset::FULL_ACCOUNTING);

        $this->assertEmpty(
            array_diff($essentials, $growing),
            'Growing Farm must not remove anything Essentials provides — a farm that grows '
            . 'should never lose a screen it was using.',
        );
        $this->assertEmpty(
            array_diff($growing, $full),
            'Full Accounting must not remove anything Growing Farm provides.',
        );
    }

    public function test_applying_a_preset_is_additive_by_default(): void
    {
        $svc = $this->service();

        // An accountant has set up the ledger.
        $svc->enable(FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING, actor: $this->actor);

        // Someone then applies the storefront overlay.
        $svc->applyPreset(ModulePreset::STOREFRONT_SELLER, actor: $this->actor);

        $this->assertTrue(
            $svc->enabled(FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING),
            'Applying an overlay preset must not silently destroy an unrelated setup. '
            . 'This is why applyPreset is additive unless a reset is explicitly requested.',
        );
        $this->assertTrue($svc->enabled(FeatureCatalog::STOREFRONT_MANURE_SALES));
    }

    public function test_exclusive_apply_is_a_real_reset(): void
    {
        $svc = $this->service();
        $svc->enable(FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING, actor: $this->actor);

        $result = $svc->applyPreset(ModulePreset::ESSENTIALS, actor: $this->actor, exclusive: true);

        $this->assertFalse($svc->enabled(FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING));
        $this->assertContains(FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING, $result['disabled']);
    }

    public function test_exclusive_apply_never_strands_a_dependency(): void
    {
        $svc = $this->service();

        foreach (ModulePreset::codes() as $code) {
            OrganizationFeatureFlag::query()->delete();
            $svc->forget();

            $svc->applyPreset($code, actor: $this->actor, exclusive: true);

            foreach (ModulePreset::enables($code) as $key) {
                $this->assertTrue(
                    $svc->usable($key),
                    "Preset [{$code}] enables [{$key}] but an exclusive apply left one of its "
                    . 'prerequisites off, so the feature is unusable.',
                );
            }
        }
    }

    public function test_unknown_preset_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service()->applyPreset('not_a_preset', actor: $this->actor);
    }

    public function test_current_preset_reports_drift_after_a_manual_change(): void
    {
        $svc = $this->service();
        $svc->applyPreset(ModulePreset::FULL_ACCOUNTING, actor: $this->actor);

        $this->assertSame(ModulePreset::FULL_ACCOUNTING, $svc->currentPreset()['preset']);
        $this->assertEmpty($svc->currentPreset()['drift']);

        $svc->disable(FeatureCatalog::FINANCE_WHT, actor: $this->actor);

        $this->assertContains(
            FeatureCatalog::FINANCE_WHT,
            $svc->currentPreset()['drift'],
            'The settings screen needs to say "Full Accounting, with 1 manual change" '
            . 'rather than claiming a configuration the organisation is no longer on.',
        );
    }

    public function test_every_preset_references_only_real_features(): void
    {
        foreach (ModulePreset::codes() as $code) {
            foreach (ModulePreset::enables($code) as $key) {
                $this->assertTrue(
                    FeatureCatalog::isKnown($key),
                    "Preset [{$code}] references unknown feature [{$key}].",
                );
            }
        }
    }

    /* ------------------------------------------------------------------ */
    /* Backward compatibility — the backfill                              */
    /* ------------------------------------------------------------------ */

    public function test_organisations_existing_at_deploy_time_keep_everything(): void
    {
        // Re-run the backfill against an organisation that has no rows, which is
        // exactly the state a pre-existing tenant is in when the migration runs.
        $this->assertSame(0, OrganizationFeatureFlag::where('organization_id', $this->org->id)->count());

        require_once database_path(
            'migrations/2028_09_01_100100_backfill_existing_organizations_to_full_accounting.php'
        );
        $migration = include database_path(
            'migrations/2028_09_01_100100_backfill_existing_organizations_to_full_accounting.php'
        );
        $migration->up();
        $this->service()->forget();

        foreach (FeatureCatalog::keys() as $key) {
            $this->assertTrue(
                $this->service()->enabled($key, $this->org),
                "A pre-existing organisation lost access to [{$key}] at deploy time. "
                . 'Blueprint §53 requires the rollout to be additive.',
            );
        }
    }

    public function test_the_backfill_is_idempotent_and_does_not_overwrite_later_choices(): void
    {
        $path = database_path(
            'migrations/2028_09_01_100100_backfill_existing_organizations_to_full_accounting.php'
        );

        $migration = include $path;
        $migration->up();
        $this->service()->forget();

        // An administrator then switches something off.
        $this->service()->disable(FeatureCatalog::FINANCE_WHT, actor: $this->actor);
        $this->assertFalse($this->service()->enabled(FeatureCatalog::FINANCE_WHT));

        // Re-running the migration must not undo that decision.
        $migration = include $path;
        $migration->up();
        $this->service()->forget();

        $this->assertFalse(
            $this->service()->enabled(FeatureCatalog::FINANCE_WHT),
            'A re-run backfill overwrote an administrator choice. Backfills must be '
            . 'resumable without being destructive.',
        );
    }
}
