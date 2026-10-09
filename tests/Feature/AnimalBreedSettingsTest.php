<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\PigManagement;
use App\Models\Setting;
use App\Models\User;
use App\Services\Stock\Imports\StockExcelImportService;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
        $this->assertSame('Hampshire', $rows[1][2]);
        $this->assertStringNotContainsString('YORK', json_encode($rows));
    }

    public function test_stock_import_accepts_the_exact_all_stock_table_columns(): void
    {
        $farm = Organization::create(['name' => 'Farm A', 'slug' => 'farm-a']);
        $this->app->make(CurrentOrganization::class)->bind($farm);
        Setting::setAnimalBreeds(['HAMP' => 'Hampshire']);

        $service = $this->app->make(StockExcelImportService::class);
        $path = $service->buildXlsx([
            StockExcelImportService::HEADERS,
            ['101', 'Unspecified', 'Hampshire', 'Breeding', 'Female', '2025-01-10', '', '72 kg', 'View'],
        ]);

        try {
            $preview = $service->preview(new UploadedFile($path, 'all-stock.xlsx', null, null, true));
        } finally {
            @unlink($path);
        }

        $this->assertSame([], $preview['missing_headers']);
        $this->assertSame(1, $preview['valid_count']);
        $this->assertSame(0, $preview['invalid_count']);
        $this->assertSame('HAMP', $preview['valid_rows'][0]['data']['breed']);
        $this->assertSame('72', $preview['valid_rows'][0]['data']['weight']);
    }
}
