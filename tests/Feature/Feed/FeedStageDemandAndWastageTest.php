<?php

namespace Tests\Feature\Feed;

use App\Models\FeedRecipeVersion;
use App\Models\FeedType;
use App\Models\Group;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\PigManagement;
use App\Models\RawMaterial;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\Feed\FeedConfigurationService;
use App\Services\Feed\FeedPlanningService;
use App\Services\Feed\FeedRecipeService;
use App\Services\Finance\Inventory\InventoryService;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Feed planning by stage, a wastage allowance per feed type, and recipes typed
 * as kg in a batch.
 */
class FeedStageDemandAndWastageTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $actor;

    private FeedType $grower;

    private FeedType $creep;

    private RawMaterial $maize;

    private RawMaterial $soya;

    private RawMaterial $premix;

    private Group $group;

    private int $source = 800000;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 2, 1)->startOfDay());
        $this->org = Organization::create(['name' => 'Feed farm', 'slug' => 'feed-farm']);
        app(CurrentOrganization::class)->bind($this->org);
        $role = Role::firstOrCreate(['name' => 'admin']);
        foreach (['view_feed_production', 'manage_feed_production', 'manage_feed_issues', 'view_group_pools', 'view_finance'] as $name) {
            $permission = Permission::firstOrCreate(['name' => $name], ['description' => $name, 'module' => 'finance']);
            $role->permissions()->syncWithoutDetaching($permission->id);
        }
        $this->actor = User::factory()->create(['organization_id' => $this->org->id, 'role' => 'admin', 'role_id' => $role->id]);
        $this->group = Group::create(['name' => 'Growing group', 'type' => 'pen']);
        $this->maize = RawMaterial::create(['name' => 'Maize', 'unit' => 'kg', 'quantity_in_stock' => 1000, 'unit_cost' => 2])->fresh();
        $this->soya = RawMaterial::create(['name' => 'Soya', 'unit' => 'kg', 'quantity_in_stock' => 1000, 'unit_cost' => 4])->fresh();
        $this->premix = RawMaterial::create(['name' => 'Premix', 'unit' => 'kg', 'quantity_in_stock' => 1000, 'unit_cost' => 9])->fresh();
        foreach ([$this->maize, $this->soya, $this->premix] as $material) {
            DB::transaction(fn () => app(InventoryService::class)->recordReceive('raw_material', $material->id, 1000, 2, 'stock_log', ++$this->source));
        }
        $this->grower = $this->feedType('Grower meal');
        $this->creep = $this->feedType('Creep feed');
    }

    public function test_a_feed_types_own_wastage_overrides_the_farms(): void
    {
        Setting::set(FeedPlanningService::WASTAGE_SETTING, '10');
        $this->creep->update(['wastage_percent' => 25]);
        $this->standard('grower', 2, $this->grower);   // 1 grower × 2 kg × 5 days = 10 kg
        $this->standard('piglet', 0.5, $this->creep);  // 2 piglets × 0.5 kg × 5 days = 5 kg
        $this->pig(120);
        $this->pig(20);
        $this->pig(25);

        $plan = $this->plan('2026-02-05');
        $feeds = collect($plan['feeds'])->keyBy('name');

        $this->assertEquals(10.0, $feeds['Grower meal']['wastage_percent'], 'No own allowance: the farm\'s.');
        $this->assertEquals(11, $feeds['Grower meal']['required_kg']);
        $this->assertEquals(25.0, $feeds['Creep feed']['wastage_percent']);
        $this->assertEquals(6.25, $feeds['Creep feed']['required_kg']);
        $this->assertTrue($plan['wastage_varies']);
        $this->assertEquals(15, $plan['base_kg']);
        $this->assertEquals(2.25, $plan['wastage_kg']);
    }

    public function test_a_feed_type_set_to_zero_wastage_has_none_even_when_the_farm_has_some(): void
    {
        Setting::set(FeedPlanningService::WASTAGE_SETTING, '10');
        $this->grower->update(['wastage_percent' => 0]);
        $this->standard('grower', 2, $this->grower);
        $this->pig(120);

        $this->assertEquals(0, $this->plan('2026-02-05')['wastage_kg']);
    }

    public function test_feeding_expectations_ignore_every_wastage_allowance(): void
    {
        $this->creep->update(['wastage_percent' => 25]);
        $this->standard('piglet', 0.5, $this->creep);
        $this->pig(20);

        $feeding = app(FeedPlanningService::class)->calculate($this->org->id, '2026-02-01', '2026-02-01', [], applyWastage: false);
        $this->assertEquals(0.5, $feeding['required_kg']);
    }

    public function test_the_plan_counts_animals_per_stage_and_flags_a_stage_with_no_ration(): void
    {
        $this->standard('grower', 2, $this->grower);
        $this->standard('weaner', 1, $this->grower);
        $this->pig(120);
        $this->pig(130);
        $this->pig(80);
        $this->pig(400, ['stock_type' => 'breeding', 'gender' => 'M']);  // a boar, no ration set

        $plan = $this->plan('2026-02-01');
        $byStage = collect($plan['by_stage'])->keyBy('stage');

        $this->assertEqualsCanonicalizing(['weaner' => 1, 'grower' => 2, 'boar' => 1], $plan['stage_counts']);
        $this->assertSame(['weaner', 'grower', 'boar'], $byStage->keys()->all(), 'In the farm\'s stage order.');
        $this->assertEquals(4, $byStage['grower']['required_kg']);
        $this->assertSame('Grower meal', $byStage['grower']['rations'][0]['feed_type']);
        $this->assertSame([], $byStage['boar']['rations']);

        $this->actingAs($this->actor)->get(route('feed.mix-buy', ['weeks' => 1]))->assertOk()
            ->assertSee('Where these numbers come from')
            ->assertSeeInOrder(['Weaner', 'Grower', 'Boar'])
            ->assertSee('Some animals are not counted yet');
    }

    public function test_a_batch_converts_to_shares_of_the_mix(): void
    {
        $recipes = app(FeedRecipeService::class);

        $this->assertSame(['62.0000', '20.0000', '18.0000'], array_column($recipes->percentagesFromBatch([
            ['raw_material_id' => 1, 'quantity_kg' => '620'], ['raw_material_id' => 2, 'quantity_kg' => '200'], ['raw_material_id' => 3, 'quantity_kg' => '180'],
        ]), 'quantity_per_100kg'));

        // Thirds cannot be exact at 4 decimals: the remainder goes to one ingredient so it totals 100.
        $thirds = array_column($recipes->percentagesFromBatch([
            ['raw_material_id' => 1, 'quantity_kg' => '1'], ['raw_material_id' => 2, 'quantity_kg' => '1'], ['raw_material_id' => 3, 'quantity_kg' => '1'],
        ]), 'quantity_per_100kg');
        $this->assertSame('100.0000', array_reduce($thirds, fn ($sum, $p) => bcadd($sum, $p, 4), '0'));

        $this->expectException(ValidationException::class);
        $recipes->percentagesFromBatch([['raw_material_id' => 1, 'quantity_kg' => '0']]);
    }

    public function test_a_recipe_can_be_saved_from_a_mixing_sheet(): void
    {
        $this->actingAs($this->actor)->post(route('feed-production.recipes.store'), [
            'feed_type_id' => $this->grower->id, 'effective_from' => '2026-02-01', 'entry_mode' => 'batch',
            'items' => [
                ['raw_material_id' => $this->maize->id, 'quantity_kg' => '310'],
                ['raw_material_id' => $this->soya->id, 'quantity_kg' => '100'],
                ['raw_material_id' => $this->premix->id, 'quantity_kg' => '90'],
            ],
        ])->assertSessionHasNoErrors();

        $version = FeedRecipeVersion::where('feed_type_id', $this->grower->id)->latest('id')->firstOrFail();
        $this->assertSame(['62.0000', '20.0000', '18.0000'], array_column($version->ingredients, 'quantity_per_100kg'), 'A 500 kg batch is stored as shares.');
    }

    public function test_entering_by_percentage_still_works(): void
    {
        $this->actingAs($this->actor)->post(route('feed-production.recipes.store'), [
            'feed_type_id' => $this->grower->id, 'effective_from' => '2026-02-01',
            'items' => [['raw_material_id' => $this->maize->id, 'quantity_per_100kg' => '70'], ['raw_material_id' => $this->soya->id, 'quantity_per_100kg' => '30']],
        ])->assertSessionHasNoErrors();

        $this->assertEquals([70.0, 30.0], array_map('floatval',
            array_column(FeedRecipeVersion::where('feed_type_id', $this->grower->id)->latest('id')->firstOrFail()->ingredients, 'quantity_per_100kg')));
    }

    public function test_a_feed_types_wastage_is_set_and_cleared_from_feed_setup(): void
    {
        $this->actingAs($this->actor)->post(route('feed-production.feed-types.store'), ['name' => 'Sow meal', 'wastage_percent' => '7.5'])->assertSessionHasNoErrors();
        $sow = FeedType::where('name', 'Sow meal')->firstOrFail();
        $this->assertEquals(7.5, $sow->wastage_percent);

        $this->actingAs($this->actor)->put(route('feed-production.feed-types.update', $sow), ['name' => 'Sow meal', 'is_active' => 1, 'wastage_percent' => ''])->assertSessionHasNoErrors();
        $this->assertNull($sow->fresh()->wastage_percent, 'Blank goes back to the farm allowance.');

        $this->actingAs($this->actor)->put(route('feed-production.feed-types.update', $sow), ['name' => 'Sow meal', 'is_active' => 1, 'wastage_percent' => '80'])
            ->assertSessionHasErrors('wastage_percent');

        $this->actingAs($this->actor)->get(route('feed-setup.index'))->assertOk()->assertSee('Kg in a batch')->assertSee('Wastage %');
    }

    private function feedType(string $name): FeedType
    {
        $type = FeedType::create(['name' => $name, 'is_active' => true])->fresh();
        $type->recipes()->createMany([
            ['raw_material_id' => $this->maize->id, 'quantity_per_100kg' => 70],
            ['raw_material_id' => $this->soya->id, 'quantity_per_100kg' => 30],
        ]);

        return $type;
    }

    private function pig(int $age, array $extra = []): PigManagement
    {
        return PigManagement::create($extra + ['ear_tag_number' => '', 'date_of_birth' => now()->subDays($age), 'stock_type' => 'standard', 'gender' => 'F', 'group_id' => $this->group->id]);
    }

    private function standard(string $stage, float $rate, FeedType $feed): void
    {
        app(FeedConfigurationService::class)->standard($this->actor, ['stage' => $stage, 'daily_kg' => $rate,
            'feed_type_id' => $feed->id, 'effective_from' => '2026-02-01']);
    }

    private function plan(string $to): array
    {
        return app(FeedPlanningService::class)->calculate($this->org->id, '2026-02-01', $to);
    }
}
