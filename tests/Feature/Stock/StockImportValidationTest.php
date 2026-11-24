<?php

namespace Tests\Feature\Stock;

use App\Models\Organization;
use App\Models\PigManagement;
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
use ZipArchive;

/**
 * The stock import from upload to saved pigs: every row rule, the file
 * reader's safety limits, and the preview → confirm handshake.
 */
class StockImportValidationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $farm;
    private User $manager;
    private StockExcelImportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->farm = Organization::create(['name' => 'Import Farm', 'slug' => 'import-farm']);
        $role = Role::firstOrCreate(['name' => 'farmmanager']);
        $this->manager = User::forceCreate([
            'name' => 'Manager', 'email' => 'validate-manager@example.com', 'password' => bcrypt('secret'),
            'role' => 'farmmanager', 'role_id' => $role->id, 'organization_id' => $this->farm->id,
            'active_status' => true, 'email_verified_at' => now(),
        ]);
        $this->actingAs($this->manager);
        $this->app->make(CurrentOrganization::class)->bind($this->farm);
        Setting::setAnimalBreeds(['HAMP' => 'Hampshire']);
        $this->service = $this->app->make(StockExcelImportService::class);
    }

    private function row(string $tag, array $overrides = []): array
    {
        $row = ['Ear Tag' => $tag, 'Tag Colour' => 'Unspecified', 'Breed' => 'Hampshire', 'Type' => 'Standard',
            'Gender' => 'Female', 'DOB' => '2025-01-01', 'Location' => '', 'Weight' => ''];

        return array_values(array_merge($row, $overrides));
    }

    private function file(array $rows, ?array $headers = null): UploadedFile
    {
        $path = $this->service->buildXlsx([$headers ?? StockExcelImportService::HEADERS, ...$rows]);

        return new UploadedFile($path, 'stock.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function errorsFor(array $row): array
    {
        $preview = $this->service->preview($this->file([$row]));

        return $preview['invalid_rows'][0]['errors'] ?? [];
    }

    /* Row rules ----------------------------------------------------------- */

    public function test_each_row_rule(): void
    {
        $this->assertContains('Date of birth cannot be in the future.', $this->errorsFor($this->row('1', ['DOB' => now()->addDay()->format('Y-m-d')])));
        $this->assertContains('Date of birth is more than 15 years ago. Check the year.', $this->errorsFor($this->row('2', ['DOB' => '1990-01-01'])));
        $this->assertContains('Weight must be between 0.1 and 500 kg.', $this->errorsFor($this->row('3', ['Weight' => '5000'])));
        $this->assertContains('Weight must be between 0.1 and 500 kg.', $this->errorsFor($this->row('4', ['Weight' => '0.01'])));
        $this->assertContains('Ear tag must be at most 50 characters.', $this->errorsFor($this->row(str_repeat('X', 51))));
        $this->assertSame(['Ear tag holds an Excel error (#N/A). Fix the cell in Excel.'], $this->errorsFor($this->row('#N/A')));
        $this->assertContains('Location must be written as Structure / Room, for example "Structure one / sty 1".',
            $this->errorsFor($this->row('5', ['Location' => 'Nursery'])));
    }

    public function test_tag_numbers_keep_their_leading_zeros(): void
    {
        $preview = $this->service->preview($this->file([$this->row('004'), $this->row('12.0')]));

        $this->assertSame(['004', '12'], array_map(fn ($r) => $r['data']['ear_tag_number'], $preview['valid_rows']));
    }

    public function test_a_room_name_shared_by_two_structures_needs_its_structure(): void
    {
        foreach (['A', 'B'] as $name) {
            $structure = Structure::create(['name' => $name, 'code' => $name, 'created_by' => $this->manager->id]);
            Room::create(['structure_id' => $structure->id, 'name' => 'pen', 'code' => $name . 'P']);
        }

        $this->assertNotEmpty($this->errorsFor($this->row('6', ['Location' => ' / pen'])));
        $preview = $this->service->preview($this->file([$this->row('7', ['Location' => 'B / pen'])]));
        $this->assertSame(Room::where('code', 'BP')->value('id'), $preview['valid_rows'][0]['data']['room_id']);
    }

    public function test_a_column_given_twice_is_refused(): void
    {
        $preview = $this->service->preview($this->file(
            [[...$this->row('8', ['Location' => '']), 'Nursery / pen']],
            [...StockExcelImportService::HEADERS, 'Location'],
        ));

        $this->assertSame(['Location'], $preview['duplicate_headers']);
        $this->assertSame(0, $preview['valid_count']);
    }

    /* The file itself ----------------------------------------------------- */

    public function test_a_file_with_an_entity_declaration_is_refused(): void
    {
        $file = $this->file([$this->row('9')]);
        $zip = new ZipArchive();
        $zip->open($file->getRealPath());
        $zip->addFromString('xl/worksheets/sheet1.xml',
            '<?xml version="1.0"?><!DOCTYPE x [<!ENTITY a "aaaa">]><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData/></worksheet>');
        $zip->close();

        $this->post(route('all-stocks.import.preview'), ['stock_file' => $file])
            ->assertRedirect(route('all-stocks.import.create'))
            ->assertSessionHasErrors(['stock_file' => 'The Excel file could not be read. Save it again from Excel and retry.']);
    }

    public function test_too_many_rows_are_refused_with_a_message_not_an_error_page(): void
    {
        $rows = [];
        for ($i = 1; $i <= StockExcelImportService::MAX_ROWS + 1; $i++) {
            $rows[] = $this->row((string) $i);
        }

        $this->post(route('all-stocks.import.preview'), ['stock_file' => $this->file($rows)])
            ->assertRedirect(route('all-stocks.import.create'))
            ->assertSessionHasErrors('stock_file');
    }

    public function test_the_first_tab_is_read_even_when_it_is_not_sheet1(): void
    {
        $file = $this->file([$this->row('10')]);
        $zip = new ZipArchive();
        $zip->open($file->getRealPath());
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->addFromString('xl/worksheets/sheet7.xml', $sheet);
        $zip->addFromString('xl/worksheets/sheet1.xml', str_replace('>10<', '>OLD<', $sheet));
        $zip->addFromString('xl/_rels/workbook.xml.rels', str_replace('worksheets/sheet1.xml', 'worksheets/sheet7.xml', $zip->getFromName('xl/_rels/workbook.xml.rels')));
        $zip->close();

        $preview = $this->service->preview($file);
        $this->assertSame('10', $preview['valid_rows'][0]['data']['ear_tag_number']);
    }

    /* Preview → confirm --------------------------------------------------- */

    public function test_confirm_imports_the_previewed_file_once(): void
    {
        $this->post(route('all-stocks.import.preview'), ['stock_file' => $this->file([$this->row('11')])])->assertOk();
        $token = session('stock_import_preview')['token'];

        $this->post(route('all-stocks.import.confirm'), ['preview_token' => $token])->assertRedirect(route('all-stocks.index'));
        $this->assertSame(1, PigManagement::where('ear_tag_number', '11')->count());

        // A second click finds nothing left to import.
        $this->post(route('all-stocks.import.confirm'), ['preview_token' => $token])
            ->assertRedirect(route('all-stocks.import.create'))->assertSessionHasErrors('stock_file');
        $this->assertSame(1, PigManagement::where('ear_tag_number', '11')->count());
    }

    public function test_confirm_from_an_older_tab_does_not_import_a_newer_upload(): void
    {
        $this->post(route('all-stocks.import.preview'), ['stock_file' => $this->file([$this->row('12')])]);
        $oldToken = session('stock_import_preview')['token'];
        $this->post(route('all-stocks.import.preview'), ['stock_file' => $this->file([$this->row('13')])]);

        $this->post(route('all-stocks.import.confirm'), ['preview_token' => $oldToken])
            ->assertSessionHasErrors('stock_file');
        $this->assertSame(0, PigManagement::whereIn('ear_tag_number', ['12', '13'])->count());

        // The newer preview is still there for its own tab.
        $this->post(route('all-stocks.import.confirm'), ['preview_token' => session('stock_import_preview')['token']]);
        $this->assertSame(1, PigManagement::where('ear_tag_number', '13')->count());
    }

    public function test_a_preview_cannot_be_confirmed_on_another_farm(): void
    {
        $preview = $this->service->preview($this->file([$this->row('14')]));
        $this->app->make(CurrentOrganization::class)->bind(Organization::create(['name' => 'Other', 'slug' => 'other']));

        $this->expectException(\App\Services\Stock\StockValidationException::class);
        $this->service->import($preview, $this->manager);
    }
}
