<?php

namespace Tests\Feature\Feed;

use App\Models\FeedType;
use App\Models\Organization;
use App\Models\PigManagement;
use App\Models\RawMaterial;
use App\Models\Role;
use App\Models\Room;
use App\Models\Setting;
use App\Models\Structure;
use App\Models\User;
use App\Services\Feed\FeedConfigurationService;
use App\Services\Feed\FeedRecipeService;
use App\Services\Feed\IssueFeedToGroupService;
use App\Services\Finance\Inventory\InventoryService;
use App\Support\CurrentOrganization;
use Database\Seeders\FarmOperationsPermissionsSeeder;
use Database\Seeders\Phase5PigCostingPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Feed review fixes: forms only for those allowed, one stock list, readable numbers, clearer Mill and Plan pages. */
class FeedUsabilityTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $manager;

    private User $dataEntry;

    private FeedType $grower;

    private RawMaterial $maize;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 3, 31)->setTime(9, 0));
        $this->org = Organization::factory()->create();
        app(CurrentOrganization::class)->bind($this->org);
        $this->seed([FarmOperationsPermissionsSeeder::class, Phase5PigCostingPermissionsSeeder::class]);
        $this->manager = $this->user('farmmanager');
        $this->dataEntry = $this->user('dataentry');
        $this->actingAs($this->manager);

        $this->maize = RawMaterial::create(['name' => 'Maize', 'unit' => 'kg', 'quantity_in_stock' => 0, 'unit_cost' => 4.5, 'reorder_level' => 50]);
        $this->grower = FeedType::create(['name' => 'Grower'])->fresh();
        app(FeedRecipeService::class)->save($this->manager, ['feed_type_id' => $this->grower->id, 'items' => [['raw_material_id' => $this->maize->id, 'quantity_per_100kg' => 100]]]);
        app(FeedConfigurationService::class)->standard($this->manager, ['stage' => 'grower', 'feed_type_id' => $this->grower->id, 'daily_kg' => 2, 'effective_from' => now()->toDateString()]);
        DB::transaction(fn () => app(InventoryService::class)->recordReceive('finished_feed', $this->grower->id, 500, 4, 'stock_log', 1));
    }

    public function test_raw_material_forms_only_show_to_people_who_can_use_them(): void
    {
        $this->actingAs($this->manager)->get(route('raw-materials.index'))->assertOk()
            ->assertSee('+ Add Raw Material')->assertSee('Adjust Stock')->assertSee('Request purchase');

        $this->flushSession();
        $this->actingAs($this->dataEntry)->get(route('raw-materials.index'))->assertOk()
            ->assertDontSee('+ Add Raw Material')->assertDontSee('Adjust Stock')->assertDontSee('Save Adjustment')
            ->assertSee('Maize')->assertSee('Request purchase');
    }

    public function test_one_stock_list_with_problems_first_and_cards_as_filters(): void
    {
        RawMaterial::create(['name' => 'Aaa Healthy', 'unit' => 'kg', 'quantity_in_stock' => 0, 'unit_cost' => 1, 'reorder_level' => 0]);
        DB::transaction(fn () => app(InventoryService::class)->recordReceive('raw_material', RawMaterial::where('name', 'Aaa Healthy')->value('id'), 10, 1, 'stock_log', 2));

        $response = $this->actingAs($this->manager)->get(route('raw-materials.index'))->assertOk();
        $this->assertSame(['Maize', 'Aaa Healthy'], collect($response->viewData('rawMaterials')->items())->pluck('name')->all(), 'Out of stock before in stock, whatever the name');
        $response->assertDontSee('Low Stock Alerts');

        $this->actingAs($this->manager)->get(route('raw-materials.index', ['status' => 'out_of_stock']))->assertOk()
            ->assertSee('aria-current="true"', false);
    }

    public function test_request_purchase_prefills_enough_to_reach_the_reorder_level(): void
    {
        DB::transaction(fn () => app(InventoryService::class)->recordReceive('raw_material', $this->maize->id, 12.5, 4.5, 'stock_log', 3));

        $this->actingAs($this->manager)->get(route('purchase-requests.create', ['restock_material' => $this->maize->id]))->assertOk()
            ->assertViewHas('prefillItems', fn ($items) => $items[0]['raw_material_id'] === $this->maize->id && (float) $items[0]['quantity'] === 37.5)
            ->assertViewHas('prefillNotes', fn ($notes) => str_contains($notes, '12.5 kg in stock, reorder level 50 kg'));
    }

    public function test_plan_quantities_are_readable_rooms_name_their_building_and_zero_ingredients_are_hidden(): void
    {
        $barnA = Structure::create(['name' => 'Barn A', 'code' => 'A']);
        $barnB = Structure::create(['name' => 'Barn B', 'code' => 'B']);
        Room::create(['structure_id' => $barnA->id, 'name' => 'sty 1', 'code' => 'A1']);
        Room::create(['structure_id' => $barnB->id, 'name' => 'sty 1', 'code' => 'B1']);
        PigManagement::create(['ear_tag_number' => '', 'code' => 'U-1', 'breed' => 'LW', 'gender' => 'F', 'stock_type' => 'standard',
            'date_of_birth' => now()->subDays(120)->toDateString()]);

        // The detailed plan (filters by stage, room or building), linked from Mix & buy.
        $page = $this->actingAs($this->manager)->get(route('planning-reports.index', ['tab' => 'plan']))->assertOk();
        $page->assertSee('Barn A · sty 1')->assertSee('Barn B · sty 1');
        $page->assertDontSee('.0000', false);
        $page->assertSee('Nothing to mix for this period');
        $page->assertDontSee('<td class="p-3">Maize</td>', false);
    }

    public function test_planned_vs_actual_links_each_row_to_its_feedings_and_asks_for_a_variance_alert(): void
    {
        PigManagement::create(['ear_tag_number' => '', 'code' => 'G-1', 'breed' => 'LW', 'gender' => 'F', 'stock_type' => 'standard',
            'date_of_birth' => now()->subDays(120)->toDateString()]);
        $this->feed(150);  // one grower on a 2 kg ration: a typing slip

        $page = $this->actingAs($this->manager)->get(route('feed.planned-vs-actual'))->assertOk();
        $page->assertSee('No variance alert is set')->assertSee('Set a variance alert')->assertSee('Open feeding');

        Setting::set('feed.alerts.variance_percent', 25);
        $this->actingAs($this->manager)->get(route('feed.planned-vs-actual'))->assertOk()
            ->assertSee('Variance needs review')->assertDontSee('No variance alert is set');

        $this->feed(1.5, '2026-03-30 08:00');
        $this->actingAs($this->manager)->get(route('feed.issues.index', ['date' => '2026-03-30']))->assertOk()
            ->assertSee('Feedings on 30 Mar 2026')->assertViewHas('issues', fn ($issues) => $issues->total() === 1);
    }

    public function test_mix_and_batches_puts_the_mill_jobs_in_order_and_leaves_recipes_to_setup(): void
    {
        $page = $this->actingAs($this->manager)->get(route('feed-production.index'))->assertOk();
        $page->assertSeeInOrder(['Mix &amp; batches', '1. Plans to confirm', '2. Mix without a plan', '3. Batches made'], false);
        $page->assertDontSee('+ Add Feed Type')->assertDontSee('Set Feed Recipe')->assertSee('Feed types and recipes are kept in');
    }

    public function test_feed_pages_open_with_cards_and_page_like_the_health_tables(): void
    {
        PigManagement::create(['ear_tag_number' => '', 'code' => 'C-1', 'breed' => 'LW', 'gender' => 'F', 'stock_type' => 'standard',
            'date_of_birth' => now()->subDays(120)->toDateString()]);
        foreach (range(1, 27) as $day) {
            $this->feed(2, '2026-03-'.str_pad((string) $day, 2, '0', STR_PAD_LEFT).' 08:00');
        }
        $this->feed(3);

        $this->actingAs($this->manager)->get(route('feed.issues.index'))->assertOk()
            ->assertSeeInOrder(['Fed today', '3 kg', 'ration 2 kg', 'Stages still to feed', '0', 'Last 7 days'])
            ->assertSeeInOrder(['Showing', '1–25', 'of', '28', 'feedings', 'Next ›']);
        $this->actingAs($this->manager)->get(route('feed.issues.index', ['page' => 2]))->assertOk()->assertSeeInOrder(['26–28', 'of', '28']);

        // Planned vs actual: the cards total the whole month, the table pages it.
        $this->actingAs($this->manager)->get(route('feed.planned-vs-actual'))->assertOk()
            // The ration starts today, so only today's feeding can be compared; all 57 kg given still shows.
            ->assertSeeInOrder(['Ration for the period', '2 kg', 'Feed given', '57 kg', '54 kg had no ration to compare', 'Difference', '+1 kg'])
            ->assertSeeInOrder(['1–25', 'of', '28', 'rows']);

        $this->actingAs($this->manager)->get(route('feed-production.index'))->assertOk()->assertSeeInOrder(['Plans to confirm', '0', 'Batches this month', 'Lowest feed in stock']);
        $this->actingAs($this->manager)->get(route('stock-counts.index'))->assertOk()->assertSeeInOrder(['Last count', 'Never', 'count the store to start']);
    }

    public function test_feed_today_lists_what_needs_doing_and_each_stage_has_a_record_button(): void
    {
        PigManagement::create(['ear_tag_number' => '', 'code' => 'N-1', 'breed' => 'LW', 'gender' => 'F', 'stock_type' => 'standard',
            'date_of_birth' => now()->subDays(120)->toDateString()]);

        // Nothing fed, and maize is out (reorder level 50). Today lists both above the feeding form.
        $this->actingAs($this->manager)->get(route('feed-dashboard.index'))->assertRedirect(route('feed.issues.create'));
        $page = $this->actingAs($this->manager)->get(route('feed.issues.create'))->assertOk();
        $page->assertSeeInOrder(['Needs doing', '1 stage not fed yet: Grower', '1 ingredient low, 1 out: Maize', 'Stock', 'id="feeding"'], false);

        // The Record button opens the form at that stage, cursor in its kg field.
        $form = $this->actingAs($this->manager)->get(route('feed.issues.create', ['stage' => 'grower']))->assertOk();
        $form->assertSee('id="stage-grower"', false)->assertSee('autofocus', false);
        $this->actingAs($this->manager)->get(route('feed.issues.create'))->assertOk()->assertDontSee('autofocus', false);

        // A typing slip shows as far from the ration; a correct feeding and a restock clear the list.
        $this->feed(150);
        $this->actingAs($this->manager)->get(route('feed.issues.create'))->assertOk()
            ->assertSee('Fed far from the ration: Grower (150 of 2 kg)')->assertDontSee('not fed yet');

        \App\Models\FeedIssue::query()->update(['quantity_kg' => 2]);
        DB::transaction(fn () => app(InventoryService::class)->recordReceive('raw_material', $this->maize->id, 100, 4.5, 'stock_log', 9));
        $this->actingAs($this->manager)->get(route('feed.issues.create'))->assertOk()->assertSee('Nothing needs doing right now');

        // Data entry works from the same Today page.
        $this->flushSession();
        $this->actingAs($this->dataEntry)->get(route('feed.issues.create'))->assertOk()->assertSee('Needs doing');
    }

    private function feed(float $kg, string $when = '2026-03-31 08:00'): void
    {
        app(IssueFeedToGroupService::class)->execute($this->manager, ['feeding_stage' => 'grower', 'feed_type_id' => $this->grower->id,
            'quantity_kg' => $kg, 'issued_at' => $when, 'submission_key' => (string) Str::uuid()]);
    }

    private function user(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);

        return User::forceCreate(['name' => ucfirst($roleName), 'email' => $roleName.'@feed-usability.test', 'password' => bcrypt('password'),
            'role' => $roleName, 'role_id' => $role->id, 'organization_id' => $this->org->id, 'active_status' => true]);
    }
}
