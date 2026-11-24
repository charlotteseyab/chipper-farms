<?php

namespace Tests\Feature\Health;

use App\Models\ActivityLog;
use App\Models\Medicine;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Support\CurrentOrganization;
use Database\Seeders\FarmOperationsPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Brief details on each medicine: what it does, what it is used for, storage and precautions. */
class MedicineDetailsTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $dataEntry;

    protected function setUp(): void
    {
        parent::setUp();
        $org = Organization::factory()->create();
        app(CurrentOrganization::class)->bind($org);
        $this->seed(FarmOperationsPermissionsSeeder::class);
        $this->manager = $this->user('farmmanager', $org);
        $this->dataEntry = $this->user('dataentry', $org);
    }

    public function test_details_are_saved_and_shown_where_staff_need_them(): void
    {
        $this->actingAs($this->manager)->post(route('medicines.store'), $this->medicine([
            'description' => 'Long-acting antibiotic', 'used_for' => 'Respiratory infections, scours',
            'storage_instructions' => 'Below 25 °C, out of sunlight', 'precautions' => 'Swelling at the injection site can occur',
        ]))->assertSessionHasNoErrors();
        $medicine = Medicine::firstOrFail();

        $this->flushSession();
        $this->actingAs($this->dataEntry)->get(route('medicines.show', $medicine))->assertOk()
            ->assertSeeInOrder(['About this medicine', 'Long-acting antibiotic', 'Respiratory infections, scours', 'Below 25 °C', 'Swelling at the injection site']);
        $this->actingAs($this->dataEntry)->get(route('medicines.index'))->assertOk()->assertSee('Respiratory infections, scours')->assertSee('View');
        $this->actingAs($this->dataEntry)->get(route('health.create-medication'))->assertOk()->assertSee('Respiratory infections, scours', false);
    }

    public function test_a_medicine_without_details_invites_a_manager_to_add_them(): void
    {
        $this->actingAs($this->manager)->post(route('medicines.store'), $this->medicine());
        $medicine = Medicine::firstOrFail();

        $this->actingAs($this->manager)->get(route('medicines.show', $medicine))->assertOk()->assertSee('No details yet.')->assertSee("Add what it does");
        $this->flushSession();
        $this->actingAs($this->dataEntry)->get(route('medicines.show', $medicine))->assertOk()->assertSee('No details yet.')->assertDontSee("Add what it does");
    }

    public function test_changing_details_is_audited(): void
    {
        $this->actingAs($this->manager)->post(route('medicines.store'), $this->medicine(['used_for' => 'Scours']));
        $medicine = Medicine::firstOrFail();
        $this->actingAs($this->manager)->put(route('medicines.update', $medicine), $this->medicine(['used_for' => 'Scours and joint ill']))->assertSessionHasNoErrors();

        $log = ActivityLog::where('action', 'medicine_updated')->firstOrFail();
        $this->assertSame('Scours', $log->old_values['used_for']);
        $this->assertSame('Scours and joint ill', $log->new_values['used_for']);
    }

    private function medicine(array $details = []): array
    {
        return $details + ['name' => 'Amoxicillin LA', 'formulation' => 'Injectable suspension', 'concentration' => '150 mg/ml', 'dose_unit' => 'ml',
            'stock_unit' => 'ml', 'dose_units_per_stock_unit' => '1', 'meat_withdrawal_days' => 14, 'withdrawal_source_note' => 'Label', 'is_active' => 1];
    }

    private function user(string $roleName, Organization $org): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);

        return User::forceCreate(['name' => ucfirst($roleName), 'email' => $roleName.'@details.test', 'password' => bcrypt('password'),
            'role' => $roleName, 'role_id' => $role->id, 'organization_id' => $org->id, 'active_status' => true]);
    }
}
