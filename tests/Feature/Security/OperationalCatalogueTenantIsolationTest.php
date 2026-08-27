<?php

namespace Tests\Feature\Security;

use App\Models\ExpectedWeightStandard;
use App\Models\FeedType;
use App\Models\Group;
use App\Models\Ingredient;
use App\Models\Organization;
use App\Models\PerformanceMetric;
use App\Models\RawMaterial;
use App\Models\Structure;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class OperationalCatalogueTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app(CurrentOrganization::class)->reset();
        parent::tearDown();
    }

    public function test_operational_catalogues_are_invisible_outside_their_farm(): void
    {
        $one = Organization::create(['name' => 'Farm One', 'slug' => 'farm-one']);
        $two = Organization::create(['name' => 'Farm Two', 'slug' => 'farm-two']);
        $creator = User::factory()->create(['organization_id' => $one->id]);

        Structure::withoutGlobalScopes()->create(['organization_id' => $one->id, 'name' => 'Farm One Barn', 'code' => 'BARN']);
        Group::withoutGlobalScopes()->create(['organization_id' => $one->id, 'name' => 'Farm One Group', 'type' => 'stage']);
        RawMaterial::withoutGlobalScopes()->create(['organization_id' => $one->id, 'name' => 'Farm One Maize']);
        FeedType::withoutGlobalScopes()->create(['organization_id' => $one->id, 'name' => 'Farm One Feed']);
        Ingredient::withoutGlobalScopes()->create(['organization_id' => $one->id, 'name' => 'Farm One Ingredient']);
        PerformanceMetric::withoutGlobalScopes()->create(['organization_id' => $one->id, 'measure' => 'Farm One Measure', 'unit' => 'count']);
        ExpectedWeightStandard::withoutGlobalScopes()->create([
            'organization_id' => $one->id,
            'breed' => 'LW',
            'gender' => 'F',
            'stock_type' => 'breeding',
            'week_number' => 20,
            'expected_min_weight' => 30,
            'expected_max_weight' => 45,
            'optimal_weight' => 38,
            'created_by' => $creator->id,
        ]);

        app(CurrentOrganization::class)->bind($two);

        $this->assertSame(0, Structure::count());
        $this->assertSame(0, Group::count());
        $this->assertSame(0, RawMaterial::count());
        $this->assertSame(0, FeedType::count());
        $this->assertSame(0, Ingredient::count());
        $this->assertSame(0, PerformanceMetric::count());
        $this->assertSame(0, ExpectedWeightStandard::count());
    }

    public function test_each_farm_can_use_the_same_catalogue_names_and_codes(): void
    {
        $one = Organization::create(['name' => 'Farm One', 'slug' => 'farm-one']);
        $two = Organization::create(['name' => 'Farm Two', 'slug' => 'farm-two']);

        Structure::withoutGlobalScopes()->create(['organization_id' => $one->id, 'name' => 'Main Barn', 'code' => 'MAIN']);
        Structure::withoutGlobalScopes()->create(['organization_id' => $two->id, 'name' => 'Main Barn', 'code' => 'MAIN']);
        FeedType::withoutGlobalScopes()->create(['organization_id' => $one->id, 'name' => 'Grower']);
        FeedType::withoutGlobalScopes()->create(['organization_id' => $two->id, 'name' => 'Grower']);
        Ingredient::withoutGlobalScopes()->create(['organization_id' => $one->id, 'name' => 'Maize']);
        Ingredient::withoutGlobalScopes()->create(['organization_id' => $two->id, 'name' => 'Maize']);

        $this->assertSame(2, Structure::withoutGlobalScopes()->where('code', 'MAIN')->count());
        $this->assertSame(2, FeedType::withoutGlobalScopes()->where('name', 'Grower')->count());
        $this->assertSame(2, Ingredient::withoutGlobalScopes()->where('name', 'Maize')->count());
    }

    public function test_database_presence_validation_rejects_another_farms_record_id(): void
    {
        $one = Organization::create(['name' => 'Farm One', 'slug' => 'farm-one']);
        $two = Organization::create(['name' => 'Farm Two', 'slug' => 'farm-two']);
        $material = RawMaterial::withoutGlobalScopes()->create([
            'organization_id' => $one->id,
            'name' => 'Private Maize',
        ]);

        app(CurrentOrganization::class)->bind($two);

        $validator = Validator::make(
            ['raw_material_id' => $material->id],
            ['raw_material_id' => ['required', 'exists:raw_materials,id']],
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('raw_material_id', $validator->errors()->toArray());
    }
}
