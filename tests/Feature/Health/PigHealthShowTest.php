<?php

namespace Tests\Feature\Health;

use App\Models\HealthRecord;
use App\Models\MedicationAdministration;
use App\Models\Organization;
use App\Models\PigManagement;
use App\Models\Role;
use App\Models\User;
use App\Support\CurrentOrganization;
use Database\Seeders\FarmOperationsPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PigHealthShowTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private User $user;
    private PigManagement $pig;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'slug' => 'health-tests',
            'name' => 'Health Tests',
            'is_active' => true,
        ]);
        $this->app->make(CurrentOrganization::class)->bind($this->organization);
        $this->seed(FarmOperationsPermissionsSeeder::class);

        $role = Role::where('name', 'dataentry')->firstOrFail();
        $this->user = User::forceCreate([
            'name' => 'Health Data Entry',
            'email' => 'health-tests@example.com',
            'password' => bcrypt('password'),
            'role' => 'dataentry',
            'role_id' => $role->id,
            'organization_id' => $this->organization->id,
            'active_status' => true,
        ]);

        $this->pig = PigManagement::create([
            'organization_id' => $this->organization->id,
            'ear_tag_number' => 'HEALTH-001',
            'code' => 'PIG-HEALTH-001',
            'breed' => 'Large White',
            'gender' => 'F',
            'stock_type' => 'standard',
            'date_of_birth' => now()->subMonths(8)->toDateString(),
        ]);
    }

    public function test_health_and_medication_histories_are_paginated_independently_by_ten(): void
    {
        foreach (range(1, 12) as $day) {
            $this->makeHealthRecord($day);
        }

        foreach (range(1, 11) as $day) {
            MedicationAdministration::create([
                'pig_id' => $this->pig->id,
                'administration_date' => now()->subDays($day)->toDateString(),
                'medication_name' => 'Medication '.$day,
                'dosage' => '5 ml',
                'purpose' => 'Treatment',
                'administered_by' => $this->user->id,
            ]);
        }

        $this->actingAs($this->user)
            ->get(route('health.show', $this->pig))
            ->assertOk()
            ->assertViewHas('healthRecords', fn ($records) => $records->perPage() === 10
                && $records->total() === 12
                && $records->count() === 10)
            ->assertViewHas('medicationRecords', fn ($records) => $records->perPage() === 10
                && $records->total() === 11
                && $records->count() === 10)
            ->assertSee('Previous')
            ->assertSee('Next')
            ->assertSee('Showing')
            ->assertSee('of')
            ->assertSee('>2<', false);
    }

    public function test_both_cards_show_their_count_even_when_each_has_one_page(): void
    {
        $this->makeHealthRecord(1);
        MedicationAdministration::create([
            'pig_id' => $this->pig->id,
            'administration_date' => now()->toDateString(),
            'medication_name' => 'Single medication',
            'dosage' => '5 ml',
            'administered_by' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->get(route('health.show', $this->pig))
            ->assertOk()
            ->assertSeeInOrder(['Health Records', '1', 'records', 'Medication Administrations', '1', 'doses'])
            ->assertDontSee('Page 1 of 1');
    }

    public function test_health_status_modal_endpoint_updates_only_the_chosen_record(): void
    {
        $older = $this->makeHealthRecord(2);
        $latest = $this->makeHealthRecord(1);

        $this->actingAs($this->user)
            ->patch(route('health.records.status', $latest), [
                'severity' => 'moderate',
                'treatment_status' => 'completed',
                'outcome' => 'recovered',
                'resolved_at' => now()->toDateString(),
                'resolution_notes' => 'Responded well and returned to normal activity.',
            ])
            ->assertRedirect(route('health.show', $this->pig))
            ->assertSessionHas('success');

        $this->assertSame('completed', $latest->fresh()->treatment_status);
        $this->assertSame('recovered', $latest->fresh()->outcome);
        $this->assertSame('ongoing', $older->fresh()->treatment_status);
    }

    public function test_updated_health_status_is_visible_on_the_health_index(): void
    {
        $record = $this->makeHealthRecord(1);
        $record->update([
            'treatment_status' => 'completed',
            'outcome' => null,
            'resolved_at' => null,
        ]);

        $this->actingAs($this->user)
            ->get(route('health.index'))
            ->assertOk()
            ->assertSee('Treatment Completed')
            ->assertSee('Treatment: Completed')
            ->assertSee('Outcome: Not assessed');
    }

    public function test_health_index_only_lists_animals_with_a_saved_health_record(): void
    {
        $unrecordedPig = PigManagement::create([
            'organization_id' => $this->organization->id,
            'ear_tag_number' => 'HEALTH-UNRECORDED',
            'code' => 'PIG-WITHOUT-HEALTH',
            'breed' => 'Duroc',
            'gender' => 'M',
            'stock_type' => 'standard',
            'date_of_birth' => now()->subMonths(5)->toDateString(),
        ]);

        $this->makeHealthRecord(1);

        $this->actingAs($this->user)
            ->get(route('health.index'))
            ->assertOk()
            ->assertSee($this->pig->code)
            ->assertDontSee($unrecordedPig->code);
    }

    public function test_medication_index_shows_only_the_latest_record_for_each_animal(): void
    {
        MedicationAdministration::create([
            'pig_id' => $this->pig->id,
            'administration_date' => now()->subDay()->toDateString(),
            'medication_name' => 'Older treatment',
            'dosage' => '2 ml',
            'administered_by' => $this->user->id,
        ]);

        MedicationAdministration::create([
            'pig_id' => $this->pig->id,
            'administration_date' => now()->toDateString(),
            'medication_name' => 'Latest treatment',
            'dosage' => '3 ml',
            'administered_by' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->get(route('medications.index'))
            ->assertOk()
            ->assertViewHas('medications', fn ($records) => $records->total() === 1
                && $records->first()->medication_name === 'Latest treatment')
            ->assertSee('View medication history')
            // The Medication Name filter offers every name used, older ones included.
            ->assertViewHas('medicationNames', fn ($names) => in_array('Older treatment', $names, true) && in_array('Latest treatment', $names, true))
            ->assertSee('role="combobox"', false);
    }

    public function test_pig_details_include_latest_health_and_medication_summary(): void
    {
        $this->makeHealthRecord(3);
        $latestHealth = $this->makeHealthRecord(1);
        $latestHealth->update([
            'condition' => 'Latest respiratory review',
            'severity' => 'medium',
            'treatment_status' => 'ongoing',
        ]);

        MedicationAdministration::create([
            'pig_id' => $this->pig->id,
            'administration_date' => now()->toDateString(),
            'medication_name' => 'Respiratory medication',
            'dosage' => '4 ml',
            'route' => 'injection',
            'withdrawal_period_days' => 14,
            'administered_by' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->get(route('pigs.show', $this->pig))
            ->assertOk()
            ->assertSee('Health &amp; Medication Summary', false)
            ->assertSee('Latest respiratory review')
            ->assertSee('Respiratory medication')
            ->assertSee('2 records')
            ->assertSee('Withdrawal active')
            ->assertSee('View complete history');
    }

    public function test_index_and_show_use_the_same_latest_record_when_dates_are_equal(): void
    {
        $planned = $this->makeHealthRecord(1);
        $planned->update(['treatment_status' => 'planned']);

        $completed = $this->makeHealthRecord(1);
        $completed->update(['treatment_status' => 'completed']);

        $this->actingAs($this->user)
            ->get(route('health.index'))
            ->assertOk()
            ->assertSee('Treatment Completed')
            ->assertDontSee('Treatment: Planned');

        $this->actingAs($this->user)
            ->get(route('health.show', $this->pig))
            ->assertOk()
            ->assertSee('Treatment Completed');
    }

    public function test_pdf_export_returns_a_named_pdf_download(): void
    {
        $this->makeHealthRecord(1);

        $this->actingAs($this->user)
            ->get(route('health.export-pdf', $this->pig))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('health-report-PIG-HEALTH-001-'.now()->format('Y-m-d').'.pdf');
    }

    private function makeHealthRecord(int $daysAgo): HealthRecord
    {
        return HealthRecord::create([
            'pig_id' => $this->pig->id,
            'record_date' => now()->subDays($daysAgo)->toDateString(),
            'condition' => 'Condition '.$daysAgo,
            'severity' => 'mild',
            'treatment_status' => 'ongoing',
            'recorded_by' => $this->user->id,
        ]);
    }
}
