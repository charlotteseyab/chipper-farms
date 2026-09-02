<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\PigManagement;
use App\Models\Setting;
use App\Models\User;
use App\Services\Stock\Imports\StockExcelImportService;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnimalBreedSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_add_a_farm_specific_breed(): void
    {
        $org = Organization::create(['name' => 'Farm A', 'slug' => 'farm-a']);
        $user = User::factory()->create(['organization_id' => $org->id, 'role' => 'admin']);

        $this->actingAs($user)->post(route('settings.setup.breeds.save'), [
            'breed_names' => ['LW' => 'Large White', 'DR' => 'Duroc', 'LWDR' => 'Large White Duroc'],
            'new_breed_name' => 'Hampshire',
        ])->assertRedirect(route('settings.setup'));

        $this->app->make(CurrentOrganization::class)->bind($org);
        $this->assertSame('Hampshire', Setting::animalBreeds()['HAMP']);
        $this->assertSame('Hampshire', Setting::breedName('HAMP'));
    }

    public function test_a_breed_used_by_stock_cannot_be_removed(): void
    {
        $org = Organization::create(['name' => 'Farm A', 'slug' => 'farm-a']);
        $user = User::factory()->create(['organization_id' => $org->id, 'role' => 'admin']);
        PigManagement::create([
            'organization_id' => $org->id,
            'ear_tag_number' => 'LW-1',
            'code' => 'PIG-LW-1',
            'breed' => 'LW',
            'gender' => 'F',
            'stock_type' => 'breeding',
            'date_of_birth' => now()->subYear()->toDateString(),
        ]);

        $this->actingAs($user)->post(route('settings.setup.breeds.save'), [
            'breed_names' => ['LW' => 'Large White', 'DR' => 'Duroc', 'LWDR' => 'Large White Duroc'],
            'remove_breeds' => ['LW'],
        ])->assertSessionHasErrors('breeds');
    }

    public function test_stock_import_sample_uses_only_the_current_farms_breed_codes(): void
    {
        $farmA = Organization::create(['name' => 'Farm A', 'slug' => 'farm-a']);
        $farmB = Organization::create(['name' => 'Farm B', 'slug' => 'farm-b']);
        $organization = $this->app->make(CurrentOrganization::class);

        $organization->bind($farmA);
        Setting::setAnimalBreeds(['HAMP' => 'Hampshire']);

        $organization->bind($farmB);
        Setting::setAnimalBreeds(['YORK' => 'Yorkshire']);

        $organization->bind($farmA);
        $rows = $this->app->make(StockExcelImportService::class)->sampleRows();

        $this->assertSame(StockExcelImportService::HEADERS, $rows[0]);
        $this->assertSame('HAMP', $rows[1][1]);
        $this->assertStringContainsString('Hampshire (HAMP)', $rows[1][12]);
        $this->assertStringNotContainsString('YORK', json_encode($rows));
    }
}
