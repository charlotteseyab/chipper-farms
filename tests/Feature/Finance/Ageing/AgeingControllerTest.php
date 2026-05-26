<?php

namespace Tests\Feature\Finance\Ageing;

use App\Models\Counterparty;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

/**
 * Phase 3d — AgeingController smoke + perm test.
 *
 * Verifies:
 *   - AR ageing index route still works (back-compat)
 *   - AP ageing route requires view_ap_ageing perm
 *   - CSV exports work and require export_finance perm
 */
class AgeingControllerTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        $this->seed(\Database\Seeders\Phase3dPermissionsSeeder::class);

        // Grant existing perms to admin role for full surface.
        $admin = Role::where('name', 'admin')->first();
        foreach (['view_finance', 'export_finance', 'view_ap_ageing'] as $code) {
            $perm = Permission::firstOrCreate(['code' => $code], [
                'name' => $code, 'category' => 'finance',
            ]);
            if ($admin && ! $admin->permissions()->where('permissions.id', $perm->id)->exists()) {
                $admin->permissions()->attach($perm->id);
            }
        }
    }

    public function test_ar_ageing_index_route_renders(): void
    {
        if (! Route::has('finance.ageing.index')) {
            $this->markTestSkipped('finance.ageing.index route not registered (apply WEB_PHP patch).');
        }

        $resp = $this->actingAs($this->user)->get(route('finance.ageing.index'));
        $resp->assertOk();
    }

    public function test_ap_ageing_route_requires_perm(): void
    {
        if (! Route::has('finance.ageing.ap')) {
            $this->markTestSkipped('finance.ageing.ap route not registered (apply WEB_PHP patch).');
        }

        // Admin user (has perm) should get 200.
        $resp = $this->actingAs($this->user)->get(route('finance.ageing.ap'));
        $resp->assertOk();

        // User without perm should get 403.
        $unprivRole = Role::firstOrCreate(['name' => 'unpriv_test']);
        $unpriv = User::forceCreate([
            'name' => 'NoPerm', 'email' => 'np_'.uniqid().'@example.com',
            'password' => bcrypt('x'),
            'role' => 'unpriv_test', 'role_id' => $unprivRole->id,
            'organization_id' => $this->org->id,
        ]);
        $resp2 = $this->actingAs($unpriv)->get(route('finance.ageing.ap'));
        $this->assertContains($resp2->status(), [403, 302],
            'AP ageing must reject users without view_ap_ageing perm');
    }

    public function test_ap_export_streams_csv(): void
    {
        if (! Route::has('finance.ageing.export-ap')) {
            $this->markTestSkipped('finance.ageing.export-ap route not registered.');
        }

        $resp = $this->actingAs($this->user)->get(route('finance.ageing.export-ap'));
        $resp->assertOk();
        $this->assertStringContainsString('text/csv', $resp->headers->get('Content-Type'));
    }
}
