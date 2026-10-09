<?php

namespace Tests\Feature\Feed;

use App\Models\FeedBatch;
use App\Models\FeedType;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\PigManagement;
use App\Models\RawMaterial;
use App\Models\Role;
use App\Models\User;
use App\Services\Feed\FeedConfigurationService;
use App\Services\Feed\FeedPlanningService;
use App\Services\Feed\FeedRecipeService;
use App\Services\Feed\ProduceFeedBatchService;
use App\Services\Finance\Inventory\InventoryService;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class FeedSetupTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $actor;

    private int $source = 800000;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 2, 1)->startOfDay());
        $this->org = Organization::create(['name' => 'Setup farm', 'slug' => 'setup-farm']);
        app(CurrentOrganization::class)->bind($this->org);
        $role = Role::firstOrCreate(['name' => 'admin']);
        foreach (['view_feed_production', 'manage_feed_production'] as $name) {
            $permission = Permission::firstOrCreate(['name' => $name], ['description' => $name, 'module' => 'feed']);
            $role->permissions()->syncWithoutDetaching($permission->id);
        }
        $this->actor = User::factory()->create(['organization_id' => $this->org->id, 'role' => 'admin', 'role_id' => $role->id]);
    }

    public function test_empty_farm_shows_every_step_outstanding(): void
    {
        $response = $this->actingAs($this->actor)->get(route('feed-setup.index'))->assertOk()->assertSee('0 of 5 steps done');

        $this->assertSame(['materials' => false, 'feed_types' => false, 'recipes' => false, 'standards' => false, 'stock' => false], $response->viewData('steps'));
    }

    public function test_fully_configured_farm_completes_every_step(): void
    {
        $maize = $this->material('Maize', 'kg', 1000, 2);
        $feed = FeedType::create(['name' => 'Grower'])->fresh();
        app(FeedRecipeService::class)->save($this->actor, ['feed_type_id' => $feed->id, 'items' => [['raw_material_id' => $maize->id, 'quantity_per_100kg' => 100]]]);
        PigManagement::create(['ear_tag_number' => '', 'date_of_birth' => now()->subDays(120), 'stock_type' => 'standard', 'gender' => 'F']);
        app(FeedConfigurationService::class)->standard($this->actor, ['stage' => 'grower', 'feed_type_id' => $feed->id, 'daily_kg' => 2, 'effective_from' => now()->toDateString()]);
        $this->receive('finished_feed', $feed->id, 50);

        $response = $this->actingAs($this->actor)->get(route('feed-setup.index'))->assertOk()->assertSee('Feed is fully set up');

        $this->assertNotContains(false, $response->viewData('steps'));
        $grower = $response->viewData('stages')->firstWhere('stage', 'grower');
        $this->assertSame(1, $grower['animals']);
        $this->assertEquals(2, $grower['daily_kg']);
        $this->assertEquals(2, $response->viewData('feedTypes')->firstWhere('id', $feed->id)['cost_per_kg']);
    }

    public function test_recipe_that_does_not_add_up_is_explained_with_its_total(): void
    {
        $maize = $this->material('Maize', 'kg', 1000, 2);
        $feed = FeedType::create(['name' => 'Grower'])->fresh();
        $feed->recipes()->create(['raw_material_id' => $maize->id, 'quantity_per_100kg' => 20]);

        $response = $this->actingAs($this->actor)->get(route('feed-setup.index'))
            ->assertOk()
            ->assertSee('adds up to 20 kg', false)
            ->assertSee('Fix recipe');

        $this->assertFalse($response->viewData('steps')['recipes']);
    }

    public function test_stage_with_animals_but_no_ration_is_flagged(): void
    {
        PigManagement::create(['ear_tag_number' => '', 'date_of_birth' => now()->subDays(30), 'stock_type' => 'standard', 'gender' => 'F']);

        $response = $this->actingAs($this->actor)->get(route('feed-setup.index'))->assertOk();

        $piglet = $response->viewData('stages')->firstWhere('stage', 'piglet');
        $this->assertTrue($piglet['needs_standard']);
        $this->assertFalse($response->viewData('stages')->firstWhere('stage', 'boar')['needs_standard']);
    }

    public function test_bag_material_needs_kg_per_bag_before_a_recipe_can_use_it(): void
    {
        $corn = $this->material('Corn', 'bags', 0, 20);
        $feed = FeedType::create(['name' => 'Grower'])->fresh();

        try {
            app(FeedRecipeService::class)->save($this->actor, ['feed_type_id' => $feed->id, 'items' => [['raw_material_id' => $corn->id, 'quantity_per_100kg' => 100]]]);
            $this->fail('A bag material without kg per bag must be refused.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Set its kg per bags', $e->validator->errors()->first());
        }

        $corn->update(['kg_per_unit' => 50]);
        app(FeedRecipeService::class)->save($this->actor, ['feed_type_id' => $feed->id, 'items' => [['raw_material_id' => $corn->id, 'quantity_per_100kg' => 100]]]);
        $this->assertTrue($this->actingAs($this->actor)->get(route('feed-setup.index'))->viewData('steps')['recipes']);
    }

    public function test_mixing_converts_recipe_kg_into_bags_for_stock_and_cost(): void
    {
        // 10 bags of 50 kg at GH₵ 100 a bag = GH₵ 2 per kg.
        $corn = $this->material('Corn', 'bags', 10, 100, kgPerUnit: 50);
        $feed = FeedType::create(['name' => 'Grower'])->fresh();
        app(FeedRecipeService::class)->save($this->actor, ['feed_type_id' => $feed->id, 'items' => [['raw_material_id' => $corn->id, 'quantity_per_100kg' => 100]]]);

        $this->actingAs($this->actor);
        $result = app(ProduceFeedBatchService::class)->execute($this->actor, ['feed_type_id' => $feed->id, 'batch_date' => now()->toDateString(), 'batch_size_kg' => 100]);

        $this->assertEquals(2, $result['items'][0]['quantity_used'], '100 kg of corn is 2 bags');
        $this->assertEquals(200, $result['total_cost']);
        $this->assertEquals(8, app(FeedPlanningService::class)->available($this->org->id, 'raw_material', $corn->id));
        $this->assertEquals(400, app(FeedPlanningService::class)->availableKg($this->org->id, $corn->id));
    }

    public function test_mixing_more_bags_than_stocked_is_refused_with_kg_and_bags(): void
    {
        $corn = $this->material('Corn', 'bags', 1, 100, kgPerUnit: 50);
        $feed = FeedType::create(['name' => 'Grower'])->fresh();
        app(FeedRecipeService::class)->save($this->actor, ['feed_type_id' => $feed->id, 'items' => [['raw_material_id' => $corn->id, 'quantity_per_100kg' => 100]]]);

        $this->actingAs($this->actor);
        try {
            app(ProduceFeedBatchService::class)->execute($this->actor, ['feed_type_id' => $feed->id, 'batch_date' => now()->toDateString(), 'batch_size_kg' => 100]);
            $this->fail('Overdrawing bags must be refused.');
        } catch (HttpException $e) {
            $this->assertStringContainsString('Required: 100.00 kg (2.00 bags), Available: 1.00 bags', $e->getMessage());
        }
        $this->assertSame(0, FeedBatch::count());
    }

    public function test_feed_type_names_are_unique_per_farm_ignoring_case(): void
    {
        FeedType::create(['name' => 'Grower feed']);

        $this->actingAs($this->actor)->from(route('feed-setup.index'))
            ->post(route('feed-production.feed-types.store'), ['name' => '  grower FEED '])
            ->assertSessionHasErrors('name');

        $other = Organization::create(['name' => 'Other farm', 'slug' => 'other-farm']);
        FeedType::withoutGlobalScopes()->create(['name' => 'Sow feed', 'organization_id' => $other->id]);
        $this->actingAs($this->actor)->post(route('feed-production.feed-types.store'), ['name' => 'Sow feed'])
            ->assertSessionHasNoErrors();
    }

    public function test_feed_type_can_be_renamed_and_switched_off(): void
    {
        $feed = FeedType::create(['name' => 'Grower feed']);
        FeedType::create(['name' => 'Sow feed']);

        $this->actingAs($this->actor)->put(route('feed-production.feed-types.update', $feed), ['name' => 'Sow feed', 'is_active' => 1])
            ->assertSessionHasErrors('name');

        $this->actingAs($this->actor)->put(route('feed-production.feed-types.update', $feed), ['name' => 'Grower feed (old)', 'is_active' => 0])
            ->assertSessionHasNoErrors();
        $this->assertSame('Grower feed (old)', $feed->fresh()->name);
        $this->assertFalse((bool) $feed->fresh()->is_active);
    }

    public function test_material_unit_cannot_change_after_stock_has_moved(): void
    {
        $corn = $this->material('Corn', 'bags', 5, 100, kgPerUnit: 50);
        Permission::firstOrCreate(['name' => 'manage_raw_materials'], ['description' => 'x', 'module' => 'feed']);
        $this->actor->role()->first()->permissions()->syncWithoutDetaching(Permission::where('name', 'manage_raw_materials')->value('id'));

        $this->actingAs($this->actor)->put(route('raw-materials.update', $corn), ['name' => 'Corn', 'unit' => 'kg'])
            ->assertSessionHas('error');
        $this->assertSame('bags', $corn->fresh()->unit);

        $this->actingAs($this->actor)->put(route('raw-materials.update', $corn), ['name' => 'Corn', 'unit' => 'bags', 'kg_per_unit' => 25])
            ->assertSessionHas('success');
        $this->assertEquals(25, $corn->fresh()->kg_per_unit);
    }

    public function test_viewing_setup_requires_feed_production_permission(): void
    {
        $user = User::factory()->create(['organization_id' => $this->org->id, 'role' => 'dataentry', 'role_id' => Role::firstOrCreate(['name' => 'dataentry'])->id]);

        $this->actingAs($user)->get(route('feed-setup.index'))->assertForbidden();
    }

    private function material(string $name, string $unit, float $stock, float $unitCost, ?float $kgPerUnit = null): RawMaterial
    {
        $material = RawMaterial::create(['name' => $name, 'unit' => $unit, 'kg_per_unit' => $kgPerUnit, 'quantity_in_stock' => $stock, 'unit_cost' => $unitCost])->fresh();
        if ($stock > 0) {
            $this->receive('raw_material', $material->id, $stock, $unitCost);
        }

        return $material;
    }

    private function receive(string $kind, int $id, float $qty, float $cost = 2): void
    {
        DB::transaction(fn () => app(InventoryService::class)->recordReceive($kind, $id, $qty, $cost, 'stock_log', ++$this->source));
    }
}
