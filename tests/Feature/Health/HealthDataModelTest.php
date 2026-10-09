<?php

namespace Tests\Feature\Health;

use App\Models\Finance\InventoryMovement;
use App\Models\HealthRecord;
use App\Models\HealthRecordAnimal;
use App\Models\MedicationAdministration;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\Organization;
use App\Models\PigManagement;
use App\Models\User;
use App\Models\VetExamination;
use App\Models\VetVisit;
use App\Support\CurrentOrganization;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Health upgrade Phase 1: data model, backfill of existing records, scoping. */
class HealthDataModelTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 6, 15)->setTime(9, 0));
        $this->org = Organization::factory()->create();
        app(CurrentOrganization::class)->bind($this->org);
    }

    public function test_dry_run_reports_and_changes_nothing(): void
    {
        $record = $this->legacyRecord(['treatment_status' => 'ongoing']);

        $this->artisan('health:backfill')->expectsOutputToContain('Dry run')->assertSuccessful();

        $record->refresh();
        $this->assertNull($record->case_status);
        $this->assertNull($record->observed_at);
        $this->assertSame(0, HealthRecordAnimal::count());
    }

    public function test_existing_cases_are_mapped_without_inventing_values(): void
    {
        $resolved = $this->legacyRecord(['treatment_status' => 'completed', 'resolved_at' => '2026-06-10']);
        $ongoing = $this->legacyRecord(['treatment_status' => 'ongoing']);
        $planned = $this->legacyRecord(['treatment_status' => 'planned']);
        $blank = $this->legacyRecord(['treatment_status' => null]);
        $completedOnly = $this->legacyRecord(['treatment_status' => 'completed']);

        $this->artisan('health:backfill', ['--apply' => true])
            ->expectsOutputToContain('Needs a person to review')
            ->assertSuccessful();

        $this->assertSame('resolved', $resolved->fresh()->case_status);
        $this->assertSame('under_treatment', $ongoing->fresh()->case_status);
        $this->assertSame('open', $planned->fresh()->case_status);
        $this->assertSame('open', $blank->fresh()->case_status);
        $this->assertSame('open', $completedOnly->fresh()->case_status, 'Completed without a resolved date stays open for review');

        $this->assertSame('2026-06-01 00:00:00', $ongoing->fresh()->observed_at->format('Y-m-d H:i:s'));
        $this->assertNull($ongoing->fresh()->diagnosis_status, 'Diagnosis confirmation is unknown and stays blank');
        $animal = HealthRecordAnimal::where('health_record_id', $ongoing->id)->firstOrFail();
        $this->assertSame($ongoing->pig_id, $animal->pig_id);
        $this->assertSame($this->org->id, (int) $animal->organization_id);
    }

    public function test_existing_doses_become_legacy_with_snapshots_and_no_stock_movement(): void
    {
        $pig = PigManagement::factory()->create();
        $given = MedicationAdministration::create(['pig_id' => $pig->id, 'administration_date' => '2026-06-01', 'medication_name' => 'Penicillin', 'dosage' => '4 ml', 'withdrawal_period_days' => 10]);
        $voided = MedicationAdministration::create(['pig_id' => $pig->id, 'administration_date' => '2026-06-02', 'medication_name' => 'Ivermectin', 'dosage' => '3 ml', 'voided_at' => now(), 'void_reason' => 'Never given']);
        $original = MedicationAdministration::create(['pig_id' => $pig->id, 'administration_date' => '2026-06-03', 'medication_name' => 'Amoxicillin', 'dosage' => '2 ml', 'withdrawal_period_days' => 7, 'voided_at' => now()]);
        MedicationAdministration::create(['pig_id' => $pig->id, 'administration_date' => '2026-06-03', 'medication_name' => 'Amoxicillin', 'dosage' => '2 ml', 'withdrawal_period_days' => 14, 'corrects_id' => $original->id]);
        $movementsBefore = InventoryMovement::count();

        $this->artisan('health:backfill', ['--apply' => true])->assertSuccessful();

        $given->refresh();
        $this->assertTrue($given->is_legacy);
        $this->assertSame('Penicillin', $given->medicine_name_snapshot);
        $this->assertSame(10, $given->withdrawal_days_snapshot);
        $this->assertSame(MedicationAdministration::BASIS_DOSE_DATE_PLUS_DAYS, $given->withdrawal_basis);
        $this->assertSame('2026-06-01 00:00:00', $given->administered_at->format('Y-m-d H:i:s'));
        $this->assertNull($given->dose_amount, 'The free-text dosage is kept as it was; no amount is guessed');
        $this->assertSame(MedicationAdministration::STATUS_VOIDED, $voided->fresh()->status);
        $this->assertSame(MedicationAdministration::STATUS_CORRECTED, $original->fresh()->status);
        $this->assertSame($movementsBefore, InventoryMovement::count(), 'Legacy doses never move stock');
    }

    public function test_running_twice_changes_nothing_the_second_time(): void
    {
        $this->legacyRecord(['treatment_status' => 'ongoing']);
        $this->artisan('health:backfill', ['--apply' => true])->assertSuccessful();
        $animals = HealthRecordAnimal::count();

        $this->artisan('health:backfill', ['--apply' => true])
            ->expectsTable(['What', 'Changed'], [
                ['Health records given an observed time (record date, 00:00)', 0],
                ['Health records given their animal list (one row from the pig)', 0],
                ['Health records given a case status', 0],
                ['Doses marked as legacy (no stock movement, nothing guessed)', 0],
            ])
            ->assertSuccessful();
        $this->assertSame($animals, HealthRecordAnimal::count());
    }

    public function test_a_record_whose_pig_is_in_another_farm_is_listed_not_attached(): void
    {
        $other = Organization::factory()->create();
        $foreignPig = PigManagement::factory()->create(['organization_id' => $other->id]);
        $record = $this->legacyRecord(['treatment_status' => 'ongoing']);
        DB::table('health_records')->where('id', $record->id)->update(['pig_id' => $foreignPig->id]);

        $this->artisan('health:backfill', ['--apply' => true])
            ->expectsOutputToContain('Pig missing or in another farm')
            ->assertSuccessful();

        $this->assertNull(HealthRecord::withoutGlobalScopes()->find($record->id)->case_status);
        $this->assertSame(0, HealthRecordAnimal::withoutGlobalScopes()->where('health_record_id', $record->id)->count());
    }

    public function test_health_rows_belong_to_their_pigs_farm_and_are_hidden_from_other_farms(): void
    {
        $other = Organization::factory()->create();
        $theirPig = PigManagement::factory()->create(['organization_id' => $other->id]);

        // Even with this farm bound, a record for the other farm's pig is filed under that farm.
        $record = HealthRecord::withoutGlobalScopes()->create(['pig_id' => $theirPig->id, 'record_date' => '2026-06-14', 'condition' => 'Cough']);
        $this->assertSame($other->id, (int) $record->organization_id);
        $this->assertNull(HealthRecord::find($record->id), 'Not visible from this farm');

        app(CurrentOrganization::class)->bind($other);
        $this->assertNotNull(HealthRecord::find($record->id));
    }

    public function test_a_pig_is_examined_once_per_visit_unless_deliberately_repeated(): void
    {
        $pig = PigManagement::factory()->create();
        $visit = VetVisit::factory()->create();
        $first = VetExamination::create(['vet_visit_id' => $visit->id, 'pig_id' => $pig->id, 'examined_at' => now(), 'assessment' => 'no_issue_found']);
        VetExamination::create(['vet_visit_id' => $visit->id, 'pig_id' => $pig->id, 'examined_at' => now(), 'assessment' => 'abnormal_findings',
            'repeat_number' => 1, 'repeat_of_examination_id' => $first->id]);

        $this->expectException(QueryException::class);
        VetExamination::create(['vet_visit_id' => $visit->id, 'pig_id' => $pig->id, 'examined_at' => now(), 'assessment' => 'no_issue_found']);
    }

    public function test_the_same_medicine_cannot_be_catalogued_twice(): void
    {
        Medicine::factory()->create(['name' => 'Ivermectin', 'formulation' => '', 'concentration' => '1%']);

        $this->expectException(QueryException::class);
        Medicine::factory()->create(['name' => 'Ivermectin', 'formulation' => '', 'concentration' => '1%']);
    }

    public function test_a_batch_can_be_used_on_its_expiry_date_and_not_after(): void
    {
        $batch = MedicineBatch::factory()->create(['expiry_date' => '2026-06-20']);

        $this->assertFalse($batch->isExpiredOn('2026-06-20'));
        $this->assertTrue($batch->isExpiredOn('2026-06-21'));
    }

    public function test_correcting_a_dose_keeps_status_in_step(): void
    {
        $user = User::factory()->create(['organization_id' => $this->org->id, 'role' => 'admin']);
        $dose = MedicationAdministration::factory()->create(['administered_by' => $user->id]);

        app(\App\Services\Health\MedicationRecordService::class)->correct($user, $dose, [
            'administration_date' => $dose->administration_date->toDateString(), 'medication_name' => 'Oxytetracycline LA',
            'dosage' => '5 ml', 'withdrawal_period_days' => 21,
        ], 'Label says 21 days');

        $this->assertSame(MedicationAdministration::STATUS_CORRECTED, $dose->fresh()->status);
        $this->assertSame(MedicationAdministration::STATUS_RECORDED, MedicationAdministration::where('corrects_id', $dose->id)->value('status'));
    }

    private function legacyRecord(array $attributes): HealthRecord
    {
        $pig = PigManagement::factory()->create();
        $id = DB::table('health_records')->insertGetId($attributes + [
            'organization_id' => $this->org->id, 'pig_id' => $pig->id, 'record_date' => '2026-06-01',
            'condition' => 'Cough', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return HealthRecord::findOrFail($id);
    }
}
