<?php

namespace Tests\Feature\Health;

use App\Models\ActivityLog;
use App\Models\Attachment;
use App\Models\Medicine;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Support\CurrentOrganization;
use Database\Seeders\FarmOperationsPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** A photo of each medicine's pack or bottle, so staff can check they have the right one. */
class MedicinePhotoTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $manager;

    private User $dataEntry;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->org = Organization::factory()->create();
        app(CurrentOrganization::class)->bind($this->org);
        $this->seed(FarmOperationsPermissionsSeeder::class);
        $this->manager = $this->user('farmmanager');
        $this->dataEntry = $this->user('dataentry');
    }

    public function test_a_photo_is_added_with_the_medicine_and_shown_where_staff_pick_it(): void
    {
        $this->actingAs($this->manager)->post(route('medicines.store'), $this->medicine() + ['photo' => UploadedFile::fake()->image('bottle.jpg', 400, 400)])
            ->assertSessionHasNoErrors();

        $medicine = Medicine::firstOrFail();
        $photo = $medicine->photo;
        $this->assertNotNull($photo);
        $this->assertSame('health_medicine', $photo->category);
        Storage::disk('local')->assertExists($photo->path);
        $this->assertTrue(ActivityLog::where('action', 'health_attachment_added')->exists());

        $url = route('health.attachments.view', $photo);
        $this->flushSession();
        $this->actingAs($this->dataEntry)->get(route('medicines.index'))->assertOk()->assertSee($url, false);
        $this->actingAs($this->dataEntry)->get(route('medicines.show', $medicine))->assertOk()->assertSee($url, false);
        $doseForm = $this->actingAs($this->dataEntry)->get(route('health.create-medication'))->assertOk()->getContent();
        $this->assertStringContainsString($url, str_replace('\\', '', $doseForm), 'The dose form shows the photo once the medicine is picked');
        $response = $this->actingAs($this->dataEntry)->get($url)->assertOk();
        $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('inline', $response->headers->get('Content-Disposition'));
    }

    public function test_replacing_keeps_the_old_photo_and_removing_needs_no_new_one(): void
    {
        $this->actingAs($this->manager)->post(route('medicines.store'), $this->medicine() + ['photo' => UploadedFile::fake()->image('old.jpg')]);
        $medicine = Medicine::firstOrFail();
        $old = $medicine->photo;

        $this->actingAs($this->manager)->put(route('medicines.update', $medicine), $this->medicine() + ['photo' => UploadedFile::fake()->image('new.png')])
            ->assertSessionHasNoErrors();
        $this->assertSoftDeleted($old);
        Storage::disk('local')->assertExists($old->path);
        $this->assertSame('new.png', $medicine->fresh()->photo->original_filename);

        $this->actingAs($this->manager)->put(route('medicines.update', $medicine), $this->medicine() + ['remove_photo' => 1])->assertSessionHasNoErrors();
        $this->assertNull($medicine->fresh()->photo);
        $this->assertSame(2, Attachment::onlyTrashed()->count());
        $this->assertSame(2, ActivityLog::where('action', 'health_attachment_removed')->count());
    }

    public function test_only_photos_only_managers_and_only_this_farm(): void
    {
        $this->actingAs($this->manager)->from('/')->post(route('medicines.store'), $this->medicine() + ['photo' => UploadedFile::fake()->create('label.pdf', 50, 'application/pdf')])
            ->assertSessionHasErrors('photo');
        $this->assertSame(0, Medicine::count());

        $this->actingAs($this->manager)->post(route('medicines.store'), $this->medicine() + ['photo' => UploadedFile::fake()->image('bottle.jpg')]);
        $medicine = Medicine::firstOrFail();
        $this->flushSession();
        $this->actingAs($this->dataEntry)->put(route('medicines.update', $medicine), $this->medicine() + ['photo' => UploadedFile::fake()->image('x.jpg')])->assertForbidden();

        $outsider = $this->user('farmmanager', Organization::factory()->create());
        $this->flushSession();
        $this->actingAs($outsider)->get(route('health.attachments.view', $medicine->photo))->assertNotFound();
    }

    private function medicine(): array
    {
        return ['name' => 'Amoxicillin LA', 'formulation' => 'Injectable suspension', 'concentration' => '150 mg/ml', 'dose_unit' => 'ml', 'stock_unit' => 'ml',
            'dose_units_per_stock_unit' => '1', 'meat_withdrawal_days' => 14, 'withdrawal_source_note' => 'Label', 'is_active' => 1];
    }

    private function user(string $roleName, ?Organization $org = null): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);

        return User::forceCreate([
            'name' => ucfirst($roleName).' '.(++$this->sequence), 'email' => $roleName.'-'.$this->sequence.'@photo.test', 'password' => bcrypt('password'),
            'role' => $roleName, 'role_id' => $role->id, 'organization_id' => ($org ?? $this->org)->id, 'active_status' => true,
        ]);
    }
}
