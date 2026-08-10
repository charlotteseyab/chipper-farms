<?php

namespace Tests\Feature\Finance\Phase14;

use App\Services\Organizations\OrganizationFeatureService;
use App\Support\Features\FeatureCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route as RouteFacade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

/**
 * Phase 14 — advanced feature gating (blueprint §16, §18).
 *
 * WHAT WAS WRONG
 * --------------
 * Phase 1 defined twenty finance features. By Phase 13, eight of them gated a
 * route; the ten *advanced* ones gated nothing. `FinanceNavigation` hid the menu
 * entries and every URL stayed live — prompt §110: "a disabled feature must not
 * remain usable via URL". Each phase since 7 has recorded this and left it here,
 * because gating them piecemeal would have left a worse half-state than gating
 * them at all.
 *
 * A FLAG IS STILL NOT AN AUTHORIZATION CONTROL
 * --------------------------------------------
 * The rule from Phase 1 (SEC-001) has not moved: access needs
 * `feature enabled AND permission granted AND organisation scope valid`. The
 * second half of this file is the guard on that — every gated surface, with its
 * feature ON, must still refuse a user who lacks the permission. Gating without
 * that check is how a feature flag quietly becomes the only thing standing
 * between a user and someone else's ledger.
 */
class AdvancedFeatureGatingTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    /**
     * Every advanced surface and the flag that governs it.
     *
     * Read against `FeatureCatalog`'s own `backs` field, which is where the
     * intent was written down.
     *
     * @return array<string, array{0:string, 1:string}>
     */
    public static function advancedSurfaces(): array
    {
        return [
            'chart of accounts'   => [FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING, 'finance.coa.index'],
            'manual journals'     => [FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING, 'finance.journals.index'],
            'trial balance'       => [FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING, 'finance.trial-balance'],
            'vendor invoices'     => [FeatureCatalog::FINANCE_ADVANCED_AP, 'finance.vendor-invoices.index'],
            'payment runs'        => [FeatureCatalog::FINANCE_ADVANCED_AP, 'finance.payment-runs.index'],
            'WHT certificates'    => [FeatureCatalog::FINANCE_WHT, 'finance.wht-certificates.index'],
            'bank statements'     => [FeatureCatalog::FINANCE_BANK_RECONCILIATION, 'finance.bank-statements.index'],
            'bank reconciliation' => [FeatureCatalog::FINANCE_BANK_RECONCILIATION, 'finance.bank-reconciliation.index'],
            'period close'        => [FeatureCatalog::FINANCE_PERIOD_CLOSE, 'finance.close.index'],
            'investors'           => [FeatureCatalog::FINANCE_INVESTOR_MANAGEMENT, 'finance.investors.index'],
            'biological assets'   => [FeatureCatalog::FINANCE_ADVANCED_BIOLOGICAL, 'finance.biological-assets.index'],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        // The fixture is an established organisation with everything on. Each
        // test below switches ONE thing off, which is the interesting shape:
        // "this surface disappears when the farm turns it off".
        $this->seedEveryFinancePermission();
    }

    #[DataProvider('advancedSurfaces')]
    public function test_an_advanced_surface_is_unreachable_when_its_feature_is_off(
        string $feature,
        string $route,
    ): void {
        if (! RouteFacade::has($route)) {
            $this->markTestSkipped("Route {$route} is not registered.");
        }

        // On: reachable (or at least not hidden — permission is a separate
        // matter and is asserted below).
        $this->actingAs($this->user)->get(route($route))->assertOk();

        $this->app->make(OrganizationFeatureService::class)->disable($feature, $this->org);

        // Off: gone. 404 rather than 403 — the surface is not part of this
        // organisation's product, and 403 would confirm to a prober that it
        // exists and is merely switched off.
        $this->actingAs($this->user)->get(route($route))->assertNotFound();
    }

    /**
     * The rule that matters more than the gating (SEC-001).
     *
     * If someone ever "simplifies" a route by dropping `permission:` because
     * `feature:` is already there, this fails.
     *
     * THE USER HOLDS ONE FINANCE PERMISSION, DELIBERATELY
     * ---------------------------------------------------
     * A user with NO finance permission at all is stopped by the outer
     * `finance.access` boundary, so this test passed even with a route's own
     * `permission:` middleware removed — it could not tell "the route checked"
     * from "the boundary caught it". Discovered by reintroducing exactly that
     * defect and watching it stay green.
     *
     * The user therefore holds `record_expenses`: enough to clear the outer
     * boundary, nothing that entitles them to a ledger. Now a missing per-route
     * permission really does show up.
     */
    #[DataProvider('advancedSurfaces')]
    public function test_an_enabled_feature_still_enforces_permission(
        string $feature,
        string $route,
    ): void {
        if (! RouteFacade::has($route)) {
            $this->markTestSkipped("Route {$route} is not registered.");
        }

        // Feature ON for the organisation, but the user holds nothing.
        $this->app->make(OrganizationFeatureService::class)->enable($feature, $this->org);

        $nobody = $this->makeUserWithOnlyExpensePermission();

        $response = $this->actingAs($nobody)->get(route($route));

        $this->assertContains(
            $response->status(),
            [403, 404],
            "A user with no permissions reached {$route} with status {$response->status()}. "
            . 'A feature flag is not an authorization control.',
        );
        $this->assertNotSame(200, $response->status());
    }

    /**
     * Disabling a parent takes its dependants with it — otherwise the menu
     * describes a configuration the application cannot honour.
     *
     * WHT requires Advanced AP, which the catalogue declares. Switching AP off
     * must close the certificates too, not leave them reachable behind a
     * feature whose prerequisite is gone.
     */
    public function test_disabling_a_parent_feature_closes_its_dependants(): void
    {
        $this->actingAs($this->user)->get(route('finance.wht-certificates.index'))->assertOk();

        $this->app->make(OrganizationFeatureService::class)
            ->disable(FeatureCatalog::FINANCE_ADVANCED_AP, $this->org);

        $this->actingAs($this->user)
            ->get(route('finance.wht-certificates.index'))
            ->assertNotFound();
    }

    /**
     * The essential surfaces are unaffected. A farm on Essentials still records
     * expenses and takes money, which is the whole point of hiding the ledger
     * rather than the business.
     */
    public function test_switching_off_advanced_accounting_leaves_the_everyday_screens(): void
    {
        $this->app->make(OrganizationFeatureService::class)
            ->disable(FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING, $this->org);

        $this->actingAs($this->user)->get(route('finance.expenses.index'))->assertOk();
        $this->actingAs($this->user)->get(route('finance.money-in.index'))->assertOk();
    }

    /* ---------------------------------------------------------------- */

    protected function seedEveryFinancePermission(): void
    {
        foreach ([
            \Database\Seeders\FinancePermissionsSeeder::class,
            \Database\Seeders\Phase2aFinancePermissionsSeeder::class,
            \Database\Seeders\Phase2bInvoicePermissionsSeeder::class,
            \Database\Seeders\Phase2cReceiptPermissionsSeeder::class,
            \Database\Seeders\Phase3aSupplierPermissionsSeeder::class,
            \Database\Seeders\Phase3bVendorInvoicePermissionsSeeder::class,
            \Database\Seeders\Phase3cPaymentPermissionsSeeder::class,
            \Database\Seeders\Phase3dPermissionsSeeder::class,
            \Database\Seeders\Phase3ePermissionsSeeder::class,
            \Database\Seeders\Phase5PigCostingPermissionsSeeder::class,
            \Database\Seeders\Phase6ReportingClosePermissionsSeeder::class,
            \Database\Seeders\Phase8LoansTreasuryPermissionsSeeder::class,
            \Database\Seeders\Phase9InvestorPermissionsSeeder::class,
        ] as $seeder) {
            $this->seed($seeder);
        }

        // The fixture user is an admin; give that role everything seeded above
        // so a refusal below can only be about the feature, never about a
        // permission that happens to be missing.
        $role = \App\Models\Role::firstOrCreate(['name' => 'admin']);
        $role->permissions()->syncWithoutDetaching(\App\Models\Permission::pluck('id')->all());

        $this->user->forceFill(['role' => 'admin', 'role_id' => $role->id])->save();
    }

    /**
     * Inside the finance module, entitled to nothing advanced.
     *
     * `record_expenses` is the narrowest real finance permission there is — it
     * lets a store officer photograph a receipt. It clears `finance.access` and
     * grants no ledger, which is exactly the shape needed to test a per-route
     * permission rather than the outer boundary.
     */
    protected function makeUserWithOnlyExpensePermission(): \App\Models\User
    {
        static $sequence = 0;
        $role = \App\Models\Role::firstOrCreate(['name' => 'expenses-only-' . (++$sequence)]);

        $permission = \App\Models\Permission::firstOrCreate(
            ['name' => 'record_expenses'],
            ['name' => 'record_expenses', 'description' => 'Record expenses', 'module' => 'finance'],
        );
        $role->permissions()->syncWithoutDetaching([$permission->id]);

        return \App\Models\User::forceCreate([
            'name'              => 'Nobody ' . $sequence,
            'email'             => "nobody-{$sequence}@example.com",
            'password'          => bcrypt('secret'),
            'role'              => $role->name,
            'role_id'           => $role->id,
            'organization_id'   => $this->org->id,
            'active_status'     => true,
            'email_verified_at' => now(),
        ]);
    }
}
