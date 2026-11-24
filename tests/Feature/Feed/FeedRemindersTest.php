<?php

namespace Tests\Feature\Feed;

use App\Models\FeedProductionPlan;
use App\Models\FeedType;
use App\Models\Organization;
use App\Models\PigManagement;
use App\Models\RawMaterial;
use App\Models\Role;
use App\Models\Setting;
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

/** Feed reminders: feed running low, feedings not recorded, ingredients low or short, plans waiting, large variances. */
class FeedRemindersTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $manager;

    private User $dataEntry;

    private FeedType $grower;

    private RawMaterial $maize;

    private int $source = 100;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 3, 31)->setTime(8, 0));
        $this->org = Organization::factory()->create();
        app(CurrentOrganization::class)->bind($this->org);
        $this->seed([FarmOperationsPermissionsSeeder::class, Phase5PigCostingPermissionsSeeder::class]);
        $this->manager = $this->user('farmmanager');
        $this->dataEntry = $this->user('dataentry');
        $this->actingAs($this->manager);

        $this->maize = RawMaterial::create(['name' => 'Maize', 'unit' => 'kg', 'quantity_in_stock' => 0, 'unit_cost' => 4, 'reorder_level' => 50]);
        $this->grower = FeedType::create(['name' => 'Grower'])->fresh();
        app(FeedRecipeService::class)->save($this->manager, ['feed_type_id' => $this->grower->id, 'items' => [['raw_material_id' => $this->maize->id, 'quantity_per_100kg' => 100]]]);
        app(FeedConfigurationService::class)->standard($this->manager, ['stage' => 'grower', 'feed_type_id' => $this->grower->id, 'daily_kg' => 2, 'effective_from' => now()->toDateString()]);
        foreach (range(1, 5) as $n) {  // five growers eat 10 kg a day
            PigManagement::create(['ear_tag_number' => '', 'code' => 'FR-'.$n, 'breed' => 'LW', 'gender' => 'F', 'stock_type' => 'standard',
                'date_of_birth' => now()->subDays(120)->toDateString()]);
        }
        $this->stock('raw_material', $this->maize->id, 500);
    }

    public function test_finished_feed_running_low_then_mix_now_and_again_after_the_next_batch(): void
    {
        $this->stock('finished_feed', $this->grower->id, 50);  // 5 days
        $this->notify();
        $this->notify();
        $this->assertSame(1, $this->sent($this->manager, 'feed.stock_low'));
        $this->assertStringContainsString('Grower is running low: about 5 days left', $this->latest('feed.stock_low')->data['title']);
        $this->assertSame(0, $this->sent($this->dataEntry, 'feed.stock_low'), 'Mixing is not their job');

        $this->feed(30);  // 20 kg left: 2 days
        $this->notify();
        $this->assertSame(1, $this->sent($this->manager, 'feed.stock_critical'));
        $this->assertStringContainsString('Mix Grower now', $this->latest('feed.stock_critical')->data['title']);

        $this->stock('finished_feed', $this->grower->id, 30);  // a new batch, still under 7 days
        $this->notify();
        $this->assertSame(2, $this->sent($this->manager, 'feed.stock_low'), 'Reminded again after the restock');
    }

    public function test_a_stage_not_fed_by_ten_is_reminded_once_and_not_after_it_is_recorded(): void
    {
        $this->stock('finished_feed', $this->grower->id, 500);
        $this->notify();
        $this->assertSame(0, $this->sent($this->dataEntry, 'feed.not_recorded'), 'Not before 10:00');

        $this->travelTo(now()->setTime(10, 15));
        $this->notify();
        $this->notify();
        $this->assertSame(1, $this->sent($this->dataEntry, 'feed.not_recorded'), 'Whoever records feedings');
        $this->assertSame(1, $this->sent($this->manager, 'feed.not_recorded'));
        $this->assertStringContainsString('Grower', $this->latest('feed.not_recorded', $this->dataEntry)->data['title']);

        $this->travelTo(now()->addDay()->setTime(9, 0));
        $this->feed(10);
        $this->travelTo(now()->setTime(11, 0));
        $this->notify();
        $this->assertSame(1, $this->sent($this->dataEntry, 'feed.not_recorded'), 'Fed today, so no reminder');
    }

    public function test_low_ingredients_come_in_one_reminder_to_the_people_who_buy_them(): void
    {
        RawMaterial::create(['name' => 'Salt', 'unit' => 'kg', 'quantity_in_stock' => 0, 'unit_cost' => 1, 'reorder_level' => 25]);
        $this->stock('raw_material', RawMaterial::where('name', 'Salt')->value('id'), 10);
        RawMaterial::create(['name' => 'Premix', 'unit' => 'kg', 'quantity_in_stock' => 0, 'unit_cost' => 1, 'reorder_level' => 25]);

        $this->notify();
        $this->notify();

        $this->assertSame(1, $this->sent($this->manager, 'feed.raw_material_low'));
        $reminder = $this->latest('feed.raw_material_low');
        $this->assertSame('2 ingredients low, 1 out', $reminder->data['title']);
        $this->assertStringContainsString('Premix 0 kg (reorder at 25)', $reminder->data['message']);
        $this->assertSame(0, $this->sent($this->dataEntry, 'feed.raw_material_low'));
    }

    public function test_not_enough_ingredients_for_the_mixing_ahead(): void
    {
        $this->travelTo(now()->setTime(8, 0));
        DB::table('inventory_balances')->where('item_kind', 'raw_material')->update(['on_hand_qty' => 20]);
        RawMaterial::whereKey($this->maize->id)->update(['quantity_in_stock' => 20]);  // 7 days need 70 kg of feed, all maize

        $this->notify();
        $this->notify();
        $this->assertSame(1, $this->sent($this->manager, 'feed.ingredients_short'));
        $this->assertStringContainsString('Maize short 50 kg', $this->latest('feed.ingredients_short')->data['message']);
    }

    public function test_a_plan_waiting_a_day_is_chased_once(): void
    {
        $this->stock('finished_feed', $this->grower->id, 500);
        $plan = FeedProductionPlan::create(['feed_type_id' => $this->grower->id, 'planned_quantity_kg' => 100, 'production_date' => now()->toDateString(),
            'recipe_snapshot' => ['ingredients' => []], 'status' => 'planned', 'created_by' => $this->manager->id, 'submission_key' => (string) Str::uuid()]);

        $this->notify();
        $this->assertSame(0, $this->sent($this->manager, 'feed.plan_waiting'));
        $this->travelTo(now()->addHours(25));
        $this->notify();
        $this->notify();
        $this->assertSame(1, $this->sent($this->manager, 'feed.plan_waiting'));
        $this->assertStringContainsString('#'.$plan->id, $this->latest('feed.plan_waiting')->data['title']);
    }

    public function test_large_differences_only_once_a_variance_alert_is_set(): void
    {
        $this->stock('finished_feed', $this->grower->id, 500);
        $this->feed(100);  // ration 10 kg

        $this->notify();
        $this->assertSame(0, $this->sent($this->manager, 'feed.variance'), 'No threshold set: the farm has not chosen one');

        Setting::set('feed.alerts.variance_percent', 25);
        $this->notify();
        $this->notify();
        $this->assertSame(1, $this->sent($this->manager, 'feed.variance'));
        $this->assertStringContainsString('100 kg given against 10 kg', $this->latest('feed.variance')->data['message']);
    }

    public function test_settings_change_the_timings_and_must_make_sense(): void
    {
        $this->actingAs($this->manager)->from('/')->post(route('planning-reports.alerts'), ['cover_days' => 5, 'cover_urgent_days' => 6])
            ->assertSessionHasErrors('cover_urgent_days');
        $this->actingAs($this->manager)->post(route('planning-reports.alerts'), ['cover_days' => 10, 'cover_urgent_days' => 2, 'feeding_due_by' => '09:00'])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->manager)->post(route('planning-reports.alerts'), ['cover_days' => 2])->assertSessionHasNoErrors();
        $this->actingAs($this->manager)->post(route('planning-reports.alerts'), ['cover_days' => 10, 'cover_urgent_days' => 2, 'feeding_due_by' => '09:00']);
        $this->assertSame('09:00', \App\Services\Feed\FeedReminderService::setting('feeding_due_by'));
        $this->stock('finished_feed', $this->grower->id, 80);  // 8 days: low only because the farm chose 10
        $this->notify();
        $this->assertSame(1, $this->sent($this->manager, 'feed.stock_low'));

        $this->actingAs($this->manager)->get(route('feed-setup.settings'))->assertRedirect(route('feed-setup.index', ['settings' => 1]).'#settings');
        $this->actingAs($this->manager)->get(route('feed-setup.index'))->assertOk()->assertSee('Feed running low under (days)')->assertSee('Feeding recorded by');
    }

    // --- helpers ------------------------------------------------------------------------

    private function stock(string $kind, int $id, float $kg): void
    {
        DB::transaction(fn () => app(InventoryService::class)->recordReceive($kind, $id, $kg, 4, 'stock_log', ++$this->source));
    }

    private function feed(float $kg): void
    {
        app(IssueFeedToGroupService::class)->execute($this->manager, ['feeding_stage' => 'grower', 'feed_type_id' => $this->grower->id,
            'quantity_kg' => $kg, 'issued_at' => now()->toDateTimeString(), 'submission_key' => (string) Str::uuid()]);
    }

    private function notify(): void
    {
        $this->artisan('feed:notify')->assertSuccessful();
    }

    private function sent(User $user, string $type): int
    {
        return $user->notifications()->where('data->type', $type)->count();
    }

    private function latest(string $type, ?User $user = null)
    {
        return ($user ?? $this->manager)->notifications()->where('data->type', $type)->latest()->firstOrFail();
    }

    private function user(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);

        return User::forceCreate(['name' => ucfirst($roleName), 'email' => $roleName.'@feed-reminders.test', 'password' => bcrypt('password'),
            'role' => $roleName, 'role_id' => $role->id, 'organization_id' => $this->org->id, 'active_status' => true]);
    }
}
