<?php

namespace Tests\Browser;

use App\Models\ActivityLog;
use App\Models\FeedBatch;
use App\Models\FeedIssue;
use App\Models\FeedType;
use App\Models\Group;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\PigManagement;
use App\Models\RawMaterial;
use App\Models\Role;
use App\Models\User;
use App\Services\Feed\FeedPlanningService;
use App\Services\Finance\Inventory\InventoryService;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\DB;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class FeedWorkflowTest extends DuskTestCase
{
    public function test_farm_operator_can_configure_plan_produce_and_issue_feed(): void
    {
        $org = Organization::create(['name' => 'Feed browser farm', 'slug' => 'feed-browser-'.uniqid()]);
        app(CurrentOrganization::class)->bind($org);
        $role = Role::firstOrCreate(['name' => 'admin']);
        foreach (['view_feed_production', 'manage_feed_production', 'manage_feed_issues', 'view_group_pools', 'view_finance'] as $name) {
            $permission = Permission::firstOrCreate(['name' => $name], ['description' => $name, 'module' => 'finance']);
            $role->permissions()->syncWithoutDetaching($permission->id);
        }
        $actor = User::factory()->create(['organization_id' => $org->id, 'role' => 'admin', 'role_id' => $role->id]);
        $feed = FeedType::create(['name' => 'Browser grower feed']);
        $group = Group::create(['name' => 'Browser growing group', 'type' => 'pen']);
        PigManagement::create(['ear_tag_number' => '', 'date_of_birth' => now()->subDays(120), 'stock_type' => 'standard', 'gender' => 'F', 'group_id' => $group->id]);
        $maize = RawMaterial::create(['name' => 'Browser maize', 'unit' => 'kg', 'quantity_in_stock' => 1000, 'unit_cost' => 2]);
        $soya = RawMaterial::create(['name' => 'Browser soya', 'unit' => 'kg', 'quantity_in_stock' => 1000, 'unit_cost' => 4]);
        foreach ([$maize, $soya] as $material) {
            DB::transaction(fn () => app(InventoryService::class)->recordReceive('raw_material', $material->id, 1000, $material->unit_cost, 'stock_log', 900000 + $material->id));
        }
        $feed->recipes()->createMany([['raw_material_id' => $maize->id, 'quantity_per_100kg' => 70], ['raw_material_id' => $soya->id, 'quantity_per_100kg' => 30]]);
        $this->browse(function (Browser $browser) use ($actor, $feed, $group) {
            $browser->loginAs($actor)->visit('/planning-reports?tab=setup')->waitForText('Feeding standards');
            $browser->within('form[action$="/planning-reports/standards"]', function (Browser $form) use ($feed) {
                $form->select('stage', 'grower')->select('feed_type_id', $feed->id)->type('daily_kg', '2')->press('Save feeding standard');
            });
            $browser->waitForText('Feeding standard version saved')->visit('/planning-reports')->assertSee('14.0000')->assertSee('9.8000')->assertSee('4.2000');
            $browser->script('document.querySelector(\'form[action$="/feed-production/plans"]\').scrollIntoView({block: "center"})');
            $browser->press('Create production plan')->waitForText('Production planned. Stock is unchanged until confirmation.');
            $this->assertSame(0, FeedBatch::count());
            $browser->script('document.querySelector(\'form[action$="/complete"] button\').scrollIntoView({block: "center"})');
            $browser->within('form[action$="/complete"]', function (Browser $form) {
                $form->press('Confirm production and post stock');
            });
            $browser->waitForText('Production confirmed; inventory posted once.')->assertSee('Completed');
            // The grower stage has the only animal, so it is the first row with its standard feed preselected.
            $browser->visit('/feed/issues/create')->waitFor('#qty-0')->assertSelected('#feed-0', (string) $feed->id)->type('#qty-0', '3');
            $browser->script('document.querySelector(\'form[action$="/feed/issues"] button[type="submit"]\').scrollIntoView({block: "center"})');
            $browser->press('Record feeding')->waitForText('Feeding recorded')->assertSee('Grower');
            $this->assertSame('2.0000', (string) FeedIssue::firstOrFail()->planned_quantity_kg);
            $browser->visit('/planning-reports?tab=consumption&period=today')->waitForText('Planned versus actual consumption')->assertSee('50.00');
            $browser->visit('/planning-reports')->resize(390, 844)->pause(400)->assertSee('Feed Planning')->screenshot('feed-planning-mobile');
            $browser->resize(1440, 1000)->pause(400)->screenshot('feed-planning-desktop');
        });
        $this->assertSame(1, FeedBatch::count());
        $this->assertSame(1, FeedIssue::count());
        $this->assertEquals(990.2, $maize->fresh()->quantity_in_stock);
        $this->assertEquals(995.8, $soya->fresh()->quantity_in_stock);
        $this->assertEquals(11, app(FeedPlanningService::class)->available($org->id, 'finished_feed', $feed->id));
        $this->assertSame(1, ActivityLog::where('action', 'feed_production_confirmed')->count());
        $this->assertSame(1, ActivityLog::where('action', 'feed_issued')->count());
    }
}
