<?php

namespace Tests\Feature\Health;

use App\Models\ActivityLog;
use App\Models\Attachment;
use App\Models\HealthRecord;
use App\Models\MedicationAdministration;
use App\Models\MedicationDosingAllocation;
use App\Models\MedicationDosingEvent;
use App\Models\Medicine;
use App\Models\Organization;
use App\Models\PigManagement;
use App\Models\Role;
use App\Models\ScheduledDose;
use App\Models\User;
use App\Models\VetExamination;
use App\Models\VetVisit;
use App\Services\Health\HealthWorklistService;
use App\Services\Health\WithdrawalStatusService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Database\Seeders\FarmOperationsPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** The gaps found when checking the build against the upgrade guide, plan and build prompt. */
class HealthGuideAlignmentTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $dataEntry;

    private User $manager;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->travelTo(now()->setDate(2026, 6, 15)->setTime(10, 0));
        $this->org = Organization::factory()->create();
        app(CurrentOrganization::class)->bind($this->org);
        $this->seed(FarmOperationsPermissionsSeeder::class);
        $this->dataEntry = $this->user('dataentry');
        $this->manager = $this->user('farmmanager');
    }

    /** Guide 2-4, plan 3.2: files on visits, examinations, cases and follow-ups, using the attachments table. */
    public function test_files_can_be_attached_read_and_removed_with_a_reason(): void
    {
        $case = $this->openCase([$this->pig()]);
        $this->actingAs($this->dataEntry)->post(route('health.attachments.store', ['type' => 'case', 'id' => $case->id]), [
            'file' => UploadedFile::fake()->create('lab-result.pdf', 120, 'application/pdf'), 'title' => 'Faecal test',
        ])->assertSessionHasNoErrors();

        $file = Attachment::firstOrFail();
        $this->assertSame(HealthRecord::class, $file->attachable_type);
        Storage::disk('local')->assertExists($file->path);
        $this->assertTrue(ActivityLog::where('action', 'health_attachment_added')->exists());
        $this->actingAs($this->dataEntry)->get(route('health-cases.show', $case))->assertOk()->assertSee('Faecal test');
        $this->actingAs($this->dataEntry)->get(route('health.attachments.download', $file))->assertOk()->assertDownload('lab-result.pdf');

        $this->actingAs($this->dataEntry)->from('/')->post(route('health.attachments.store', ['type' => 'case', 'id' => $case->id]), [
            'file' => UploadedFile::fake()->create('script.exe', 10, 'application/x-msdownload'),
        ])->assertSessionHasErrors('file');

        $this->actingAs($this->dataEntry)->post(route('health.attachments.remove', $file), ['remove_reason' => 'Wrong pig'])->assertForbidden();
        $this->actingAs($this->manager)->post(route('health.attachments.remove', $file), ['remove_reason' => 'Wrong pig'])->assertRedirect();
        $this->assertSoftDeleted($file);
        Storage::disk('local')->assertExists($file->path);
        $this->assertTrue(ActivityLog::where('action', 'health_attachment_removed')->exists());
    }

    public function test_visit_and_examination_files_and_another_farms_files(): void
    {
        $pig = $this->pig();
        $this->actingAs($this->dataEntry)->post(route('vet-visits.store'), ['visited_at' => now()->subHour()->format('Y-m-d H:i'), 'veterinarian_name' => 'Dr Asante']);
        $visit = VetVisit::firstOrFail();
        $this->actingAs($this->dataEntry)->post(route('vet-visits.examinations.store', $visit), ['pig_id' => $pig->id, 'assessment' => 'no_issue_found']);
        $exam = VetExamination::firstOrFail();

        foreach (['visit' => $visit->id, 'examination' => $exam->id] as $type => $id) {
            $this->actingAs($this->dataEntry)->post(route('health.attachments.store', ['type' => $type, 'id' => $id]), [
                'file' => UploadedFile::fake()->image('report.jpg'),
            ])->assertSessionHasNoErrors();
        }
        $this->actingAs($this->dataEntry)->get(route('vet-visits.show', $visit))->assertOk()->assertSeeInOrder(['report.jpg', 'report.jpg']);

        $other = Organization::factory()->create();
        $outsider = $this->user('farmmanager', $other);
        $this->actingAs($outsider)->get(route('health.attachments.download', Attachment::first()->id))->assertNotFound();
        $this->actingAs($outsider)->post(route('health.attachments.store', ['type' => 'visit', 'id' => $visit->id]), ['file' => UploadedFile::fake()->image('x.jpg')])->assertNotFound();
    }

    /** Guide step 3 and "Administration": the person who gave the dose, apart from who entered it. */
    public function test_a_dose_records_who_gave_it_and_who_entered_it(): void
    {
        $medicine = $this->stocked();
        $this->record($medicine, [$this->pig()], ['administered_by' => $this->manager->id]);

        $event = MedicationDosingEvent::firstOrFail();
        $this->assertSame([$this->manager->id, $this->dataEntry->id], [(int) $event->administered_by, (int) $event->recorded_by]);
        $this->assertSame($this->manager->id, (int) MedicationAdministration::first()->administered_by);
        $this->actingAs($this->dataEntry)->get(route('doses.show', $event))->assertOk()->assertSee('by '.$this->manager->name)->assertSee('entered by '.$this->dataEntry->name);
    }

    /** Build prompt: conversion factor and unit cost are snapshotted onto each dose. */
    public function test_each_dose_keeps_its_conversion_and_cost_share(): void
    {
        $vaccine = Medicine::factory()->vialOf50Doses()->create(['name' => 'Vaccine']);
        $this->receive($vaccine, '4', '300');  // 300 per vial = 6 per dose
        $pigs = [$this->pig(), $this->pig(), $this->pig()];
        $this->record($vaccine, $pigs, ['dose_amount' => '1', 'dose_unit' => 'dose']);

        $doses = MedicationAdministration::get();
        $this->assertSame(['50.0000'], $doses->pluck('dose_units_per_stock_unit_snapshot')->map(fn ($v) => (string) $v)->unique()->values()->all());
        $total = $doses->reduce(fn ($s, $d) => bcadd($s, (string) $d->cost_snapshot, 4), '0');
        $allocated = bcmul((string) MedicationDosingAllocation::first()->stock_quantity, (string) MedicationDosingAllocation::first()->unit_cost_snapshot, 4);
        $this->assertSame($allocated, $total, 'Shares add up to the stock cost');
        $this->assertSame('18.0000', $total);

        $vaccine->update(['meat_withdrawal_days' => 99]);
        $this->assertSame('6.0000', (string) $doses->first()->fresh()->cost_snapshot);
    }

    /** Plan 3.7: the case page shows missed and cancelled doses. */
    public function test_the_case_page_lists_doses_not_given(): void
    {
        $pig = $this->pig();
        $case = $this->openCase([$pig]);
        $medicine = $this->stocked();
        $this->actingAs($this->manager)->post(route('treatment-courses.store'), ['purpose' => 'treatment', 'health_record_id' => $case->id, 'pig_ids' => [$pig->id],
            'medicines' => [['medicine_id' => $medicine->id, 'dose_amount' => '2', 'dose_unit' => 'ml', 'frequency' => 'once', 'start_at' => '2026-06-15 08:00']]]);
        $this->actingAs($this->dataEntry)->post(route('scheduled-doses.close', ScheduledDose::firstOrFail()), ['status' => 'missed', 'reason' => 'Could not catch her']);

        $this->actingAs($this->dataEntry)->get(route('health-cases.show', $case))->assertOk()
            ->assertSee('Doses not given (1)')->assertSee('Missed: Could not catch her');
    }

    /** Guide and plan 3.8: usage by group; vet visit export keeps individual notes and recommendations. */
    public function test_usage_by_group_and_vet_notes_in_the_export(): void
    {
        $pigs = [$this->pig(), $this->pig()];
        $case = $this->openCase($pigs, 'Weaner pen 2');
        $medicine = $this->stocked();
        $this->record($medicine, $pigs, ['health_record_id' => $case->id]);
        $csv = $this->actingAs($this->dataEntry)->get(route('health.reports.export', ['tab' => 'usage', 'by' => 'group']))->streamedContent();
        $this->assertStringContainsString('Weaner pen 2', $csv);

        $this->actingAs($this->dataEntry)->post(route('vet-visits.store'), ['visited_at' => now()->subHour()->format('Y-m-d H:i'), 'veterinarian_name' => 'Dr Asante']);
        $this->actingAs($this->dataEntry)->post(route('vet-visits.examinations.store', VetVisit::firstOrFail()), ['pig_id' => $pigs[0]->id, 'assessment' => 'abnormal_findings',
            'vet_notes' => 'Mild cough on handling', 'recommendations' => 'Improve ventilation', 'follow_up_not_needed' => 1]);
        $csv = $this->actingAs($this->dataEntry)->get(route('health.reports.export', ['tab' => 'visits']))->streamedContent();
        $this->assertStringContainsString('Vet notes', $csv);
        $this->assertStringContainsString('Mild cough on handling', $csv);
        $this->assertStringContainsString('Improve ventilation', $csv);
    }

    /** Build prompt: one withdrawal rule for every overview; counts animals on the farm, not doses. */
    public function test_withdrawal_counts_animals_on_the_farm_everywhere(): void
    {
        $medicine = $this->stocked();
        [$twice, $gone] = [$this->pig(), $this->pig()];
        $this->record($medicine, [$twice]);
        $this->record($medicine, [$twice, $gone]);
        $gone->delete();

        $this->assertSame(1, app(WithdrawalStatusService::class)->restrictedCount());
        $this->assertSame(1, app(HealthWorklistService::class)->counts(CarbonImmutable::today())['under_withdrawal']);
        $this->assertSame(1, app(\App\Services\Health\HealthReportService::class)->underWithdrawal()->count());
        $this->assertSame(3, MedicationAdministration::underWithdrawal()->count(), 'The dose-level scope still sees all three doses');
    }

    // --- helpers ------------------------------------------------------------------------

    private function openCase(array $pigs, ?string $group = null): HealthRecord
    {
        $this->actingAs($this->dataEntry)->post(route('health.store'), array_filter(['pig_ids' => array_map(fn ($p) => $p->id, $pigs), 'group_label' => $group,
            'observed_at' => now()->subHours(2)->format('Y-m-d H:i'), 'condition' => 'Scours']))->assertSessionHasNoErrors();

        return HealthRecord::latest('id')->firstOrFail();
    }

    private function stocked(): Medicine
    {
        $medicine = Medicine::factory()->create(['allowed_routes' => [], 'meat_withdrawal_days' => 14]);
        $this->receive($medicine, '200', '1.5');

        return $medicine;
    }

    private function receive(Medicine $medicine, string $quantity, string $cost): void
    {
        $this->actingAs($this->manager)->post(route('medicines.batches.store', $medicine), ['batch_number' => 'L-'.Str::random(5), 'received_at' => '2026-06-01',
            'expiry_date' => '2027-06-01', 'quantity_received' => $quantity, 'unit_cost' => $cost])->assertSessionHasNoErrors();
    }

    private function record(Medicine $medicine, array $pigs, array $extra = []): void
    {
        $this->actingAs($this->dataEntry)->post(route('medications.store'), $extra + ['pig_ids' => array_map(fn ($p) => $p->id, $pigs), 'medicine_id' => $medicine->id,
            'administered_at' => now()->subMinutes(30)->format('Y-m-d H:i'), 'amount_basis' => 'per_animal', 'dose_amount' => '2', 'dose_unit' => $medicine->dose_unit,
            'idempotency_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
    }

    /** Switching users mid-test would trip the password-hash session check, so start a fresh session per user. */
    public function actingAs($user, $guard = null)
    {
        if (auth()->user()?->isNot($user)) {
            $this->flushSession();
        }

        return parent::actingAs($user, $guard);
    }

    private function pig(): PigManagement
    {
        $n = ++$this->sequence;

        return PigManagement::create(['organization_id' => $this->org->id, 'ear_tag_number' => (string) (800 + $n), 'code' => 'GA-'.$n,
            'breed' => 'LW', 'gender' => 'F', 'stock_type' => 'standard', 'date_of_birth' => now()->subMonths(5)->toDateString()]);
    }

    private function user(string $roleName, ?Organization $org = null): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);

        return User::forceCreate([
            'name' => ucfirst($roleName).' '.(++$this->sequence), 'email' => $roleName.'-'.$this->sequence.'@alignment.test', 'password' => bcrypt('password'),
            'role' => $roleName, 'role_id' => $role->id, 'organization_id' => ($org ?? $this->org)->id, 'active_status' => true,
        ]);
    }
}
