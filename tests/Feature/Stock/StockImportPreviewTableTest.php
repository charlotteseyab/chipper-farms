<?php

namespace Tests\Feature\Stock;

use App\Models\Organization;
use App\Models\Role;
use App\Models\Room;
use App\Models\Setting;
use App\Models\Structure;
use App\Models\User;
use App\Services\Stock\Imports\StockExcelImportService;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * The import's validation table shows the uploaded file as it is: every
 * column, in the file's order, with the values as typed — including the
 * location, which is checked against the farm's rooms.
 */
class StockImportPreviewTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_column_of_the_file_shows_in_the_validation_table(): void
    {
        $farm = Organization::create(['name' => 'Import Farm', 'slug' => 'import-farm']);
        $role = Role::firstOrCreate(['name' => 'farmmanager']);
        $manager = User::forceCreate([
            'name' => 'Manager', 'email' => 'import-manager@example.com', 'password' => bcrypt('secret'),
            'role' => 'farmmanager', 'role_id' => $role->id, 'organization_id' => $farm->id,
        ]);
        $this->actingAs($manager);
        $this->app->make(CurrentOrganization::class)->bind($farm);
        Setting::setAnimalBreeds(['HAMP' => 'Hampshire']);
        $structure = Structure::create(['name' => 'Nursery', 'code' => 'NUR', 'created_by' => $manager->id]);
        Room::create(['structure_id' => $structure->id, 'name' => 'Pen 4', 'code' => 'P4', 'capacity' => 10]);

        $service = $this->app->make(StockExcelImportService::class);
        $path = $service->buildXlsx([
            StockExcelImportService::HEADERS,
            ['101', 'Unspecified', 'Hampshire', 'Breeding', 'Female', '2025-01-10', 'Nursery / Pen 4', '72 kg'],
            ['102', 'Unspecified', 'Hampshire', 'Standard', 'Male', '2025-02-11', 'Nursery / Pen 9', ''],
        ]);

        try {
            $preview = $service->preview(new UploadedFile($path, 'all-stock.xlsx', null, null, true));
        } finally {
            @unlink($path);
        }

        $this->assertSame(StockExcelImportService::HEADERS, $preview['columns']);
        $this->assertSame('Nursery / Pen 4', $preview['valid_rows'][0]['cells'][6]);
        $this->assertSame('Nursery / Pen 9', $preview['invalid_rows'][0]['cells'][6]);

        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
        $html = view('stocks.all.import.preview', ['preview' => $preview])->render();

        // Row 2, every cell as uploaded, then its status.
        $this->assertMatchesRegularExpression('/101.*Unspecified.*Hampshire.*Breeding.*Female.*2025-01-10.*Nursery \/ Pen 4.*72 kg.*Valid/s', $html);
        // Row 3: the unknown room is shown and named in the error.
        $this->assertMatchesRegularExpression('/102.*Nursery \/ Pen 9.*Room name does not match/s', $html);
        foreach (StockExcelImportService::HEADERS as $header) {
            $this->assertStringContainsString('>' . $header . '</th>', $html);
        }
    }

    public function test_an_older_file_with_an_actions_column_still_imports_without_showing_it(): void
    {
        $farm = Organization::create(['name' => 'Import Farm', 'slug' => 'import-farm']);
        $this->app->make(CurrentOrganization::class)->bind($farm);
        Setting::setAnimalBreeds(['HAMP' => 'Hampshire']);

        $service = $this->app->make(StockExcelImportService::class);
        $path = $service->buildXlsx([
            [...StockExcelImportService::HEADERS, 'Actions'],
            ['101', 'Unspecified', 'Hampshire', 'Breeding', 'Female', '2025-01-10', '', '72 kg', 'View'],
        ]);

        try {
            $preview = $service->preview(new UploadedFile($path, 'old-export.xlsx', null, null, true));
        } finally {
            @unlink($path);
        }

        $this->assertSame([], $preview['missing_headers']);
        $this->assertSame(1, $preview['valid_count']);
        $this->assertNotContains('Actions', $preview['columns']);
        $this->assertNotContains('View', $preview['valid_rows'][0]['cells']);
    }

    public function test_room_capacity_is_checked_in_the_preview_counting_rows_in_the_file(): void
    {
        $farm = Organization::create(['name' => 'Import Farm', 'slug' => 'import-farm']);
        $role = Role::firstOrCreate(['name' => 'farmmanager']);
        $manager = User::forceCreate([
            'name' => 'Manager', 'email' => 'cap-manager@example.com', 'password' => bcrypt('secret'),
            'role' => 'farmmanager', 'role_id' => $role->id, 'organization_id' => $farm->id,
        ]);
        $this->actingAs($manager);
        $this->app->make(CurrentOrganization::class)->bind($farm);
        Setting::setAnimalBreeds(['HAMP' => 'Hampshire']);
        $structure = Structure::create(['name' => 'Sties', 'code' => 'ST', 'created_by' => $manager->id]);
        $full = Room::create(['structure_id' => $structure->id, 'name' => 'sty 1', 'code' => 'S1', 'capacity' => 1]);
        Room::create(['structure_id' => $structure->id, 'name' => 'sty 2', 'code' => 'S2', 'capacity' => 1]);
        \App\Models\PigManagement::create([
            'organization_id' => $farm->id, 'ear_tag_number' => 'OLD-1', 'breed' => 'HAMP', 'gender' => 'F',
            'stock_type' => 'standard', 'date_of_birth' => '2025-01-01', 'room_id' => $full->id,
        ]);

        $service = $this->app->make(StockExcelImportService::class);
        $path = $service->buildXlsx([
            StockExcelImportService::HEADERS,
            ['201', 'Unspecified', 'Hampshire', 'Standard', 'Female', '2025-03-01', 'Sties / sty 1', ''],
            ['202', 'Unspecified', 'Hampshire', 'Standard', 'Female', '2025-03-01', 'Sties / sty 2', ''],
            ['203', 'Unspecified', 'Hampshire', 'Standard', 'Male', '2025-03-01', 'Sties / sty 2', ''],
        ]);

        try {
            $preview = $service->preview(new UploadedFile($path, 'all-stock.xlsx', null, null, true));
        } finally {
            @unlink($path);
        }

        // sty 1 is already full; sty 2 takes the first of the two rows only.
        $this->assertSame(1, $preview['valid_count']);
        $this->assertSame('202', $preview['valid_rows'][0]['data']['ear_tag_number']);
        $invalid = collect($preview['invalid_rows'])->keyBy(fn ($r) => $r['data']['ear_tag_number']);
        $this->assertContains('Room Sties / sty 1 is full. Choose another room.', $invalid['201']['errors']);
        $this->assertStringContainsString('earlier rows in this file already fill it', implode(' ', $invalid['203']['errors']));

        // And the sample never suggests a room without space.
        $sample = $service->sampleRows();
        $this->assertSame('Sties / sty 2', $sample[1][6]);
    }
}
