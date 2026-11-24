<?php

namespace Tests\Feature\Health;

use App\Models\ActivityLog;
use App\Models\Finance\InventoryMovement;
use App\Models\HealthRecord;
use App\Models\Medicine;
use App\Models\Organization;
use App\Models\PigManagement;
use App\Models\Role;
use App\Models\Room;
use App\Models\ScheduledDose;
use App\Models\Structure;
use App\Models\TreatmentCourse;
use App\Models\User;
use App\Models\VetExamination;
use App\Models\VetVisit;
use App\Support\CurrentOrganization;
use Database\Seeders\FarmOperationsPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Health upgrade Phase 2: vet visits, cases, group cases, courses, follow-ups, closing, timeline. */
class HealthWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $dataEntry;

    private User $manager;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 6, 15)->setTime(10, 0));
        $this->org = Organization::factory()->create();
        app(CurrentOrganization::class)->bind($this->org);
        $this->seed(FarmOperationsPermissionsSeeder::class);
        $this->dataEntry = $this->user('dataentry');
        $this->manager = $this->user('farmmanager');
    }

    // --- vet visits -------------------------------------------------------

    public function test_a_visit_checking_three_animals_keeps_separate_notes_and_counts_only_examined(): void
    {
        [$healthy, $sick, $pending, $plannedOnly] = [$this->pig(), $this->pig(), $this->pig(), $this->pig()];

        $this->actingAs($this->dataEntry)->post(route('vet-visits.store'), [
            'visited_at' => now()->subHour()->format('Y-m-d H:i'), 'veterinarian_name' => 'Dr Asante', 'reason' => 'Herd check',
            'planned_pig_ids' => [$healthy->id, $sick->id, $pending->id, $plannedOnly->id],
        ])->assertSessionHasNoErrors();
        $visit = VetVisit::firstOrFail();
        $this->assertSame($this->dataEntry->id, (int) $visit->entered_by_user_id);

        $this->examine($visit, $healthy, ['assessment' => 'no_issue_found', 'vet_notes' => 'Good condition']);
        $this->examine($visit, $sick, ['assessment' => 'abnormal_findings', 'findings' => 'Swollen joint', 'assessment_text' => 'Suspected arthritis',
            'follow_up_on' => now()->addDays(3)->toDateString(), 'open_case' => 1, 'case_condition' => 'Lameness']);
        $this->examine($visit, $pending, ['assessment' => 'pending_assessment', 'tests_requested' => 'Blood sample', 'follow_up_on' => now()->addWeek()->toDateString()]);

        $exams = VetExamination::where('vet_visit_id', $visit->id)->get()->keyBy('pig_id');
        $this->assertCount(3, $exams);
        $this->assertSame('Good condition', $exams[$healthy->id]->vet_notes);
        $case = HealthRecord::where('vet_examination_id', $exams[$sick->id]->id)->firstOrFail();
        $this->assertSame($case->id, (int) $exams[$sick->id]->health_record_id, 'Linked both ways');
        $this->assertSame('Lameness', $case->condition);
        $this->assertSame('unconfirmed', $case->diagnosis_status);
        $this->assertNull($exams[$pending->id]->health_record_id);

        $this->actingAs($this->dataEntry)->get(route('vet-visits.show', $visit))
            ->assertOk()->assertSee('Planned, not yet examined')->assertSee($plannedOnly->code);
        $this->actingAs($this->dataEntry)->get(route('vet-visits.index'))->assertOk()->assertSeeInOrder(['Dr Asante', '3', '4']);
        $this->actingAs($this->dataEntry)->get(route('health.show', $healthy))->assertOk()->assertSee('Vet examination: No issue found');
    }

    public function test_a_pig_cannot_be_examined_twice_on_a_visit_unless_marked_as_a_repeat(): void
    {
        $pig = $this->pig();
        $visit = VetVisit::factory()->create();
        $this->examine($visit, $pig, ['assessment' => 'no_issue_found']);

        $this->actingAs($this->dataEntry)->from('/')->post(route('vet-visits.examinations.store', $visit), ['pig_id' => $pig->id, 'assessment' => 'no_issue_found'])
            ->assertSessionHasErrors('pig_id');
        $this->examine($visit, $pig, ['assessment' => 'abnormal_findings', 'is_repeat' => 1, 'follow_up_not_needed' => 1]);

        $this->assertSame([0, 1], VetExamination::where('pig_id', $pig->id)->orderBy('repeat_number')->pluck('repeat_number')->all());
    }

    public function test_finalised_visits_need_a_manager_and_a_reason_to_change(): void
    {
        $visit = VetVisit::factory()->create(['veterinarian_name' => 'Dr Asante']);
        $this->actingAs($this->dataEntry)->post(route('vet-visits.finalise', $visit))->assertForbidden();
        $this->actingAs($this->manager)->post(route('vet-visits.finalise', $visit))->assertRedirect();
        $this->assertTrue($visit->fresh()->isFinalised());

        $changes = ['visited_at' => $visit->visited_at->format('Y-m-d H:i'), 'veterinarian_name' => 'Dr K. Asante'];
        $this->actingAs($this->dataEntry)->put(route('vet-visits.update', $visit), $changes)->assertForbidden();
        $this->actingAs($this->manager)->from('/')->put(route('vet-visits.update', $visit), $changes)->assertSessionHasErrors('amendment_reason');
        $this->actingAs($this->manager)->put(route('vet-visits.update', $visit), $changes + ['amendment_reason' => 'Vet spelled her name'])->assertRedirect();

        $this->assertSame('Dr K. Asante', $visit->fresh()->veterinarian_name);
        $log = ActivityLog::where('action', 'vet_visit_amended')->firstOrFail();
        $this->assertSame('Dr Asante', $log->old_values['veterinarian_name']);
        $this->assertStringContainsString('Vet spelled her name', $log->description);
    }

    // --- cases --------------------------------------------------------------

    public function test_a_group_case_keeps_its_animals_when_pens_change(): void
    {
        [$roomA, $roomB] = [$this->room('Pen A'), $this->room('Pen B')];
        $pigs = [$this->pig(['room_id' => $roomA->id]), $this->pig(['room_id' => $roomA->id])];

        $this->actingAs($this->dataEntry)->post(route('health.store'), [
            'pig_ids' => array_map(fn ($p) => $p->id, $pigs), 'group_label' => 'Pen A', 'observed_at' => now()->subHour()->format('Y-m-d H:i'),
            'condition' => 'Coughing', 'severity' => 'moderate',
        ])->assertRedirect();
        $case = HealthRecord::firstOrFail();
        $this->assertSame('group', $case->scope);

        $pigs[0]->update(['room_id' => $roomB->id]);
        $this->pig(['room_id' => $roomA->id]);  // a newcomer to the pen is not part of the case

        $this->assertEqualsCanonicalizing(array_map(fn ($p) => $p->id, $pigs), $case->animals()->pluck('pig_id')->all());
        $this->actingAs($this->dataEntry)->get(route('health.show', $pigs[1]))->assertOk()->assertSee('Group case: Coughing');
    }

    public function test_follow_up_resolve_close_and_reopen(): void
    {
        $case = $this->openCase($this->pig());

        $this->actingAs($this->dataEntry)->post(route('health-cases.follow-ups.store', $case), [
            'observed_at' => now()->format('Y-m-d H:i'), 'response' => 'improving', 'findings' => 'Walking better',
        ])->assertRedirect(route('health-cases.show', $case));
        $this->actingAs($this->dataEntry)->post(route('health-cases.close', $case), [
            'final_status' => 'closed', 'outcome' => 'recovered', 'closed_at' => now()->format('Y-m-d H:i'), 'closure_notes' => 'Fully recovered',
        ])->assertRedirect();

        $case->refresh();
        $this->assertSame('closed', $case->case_status);
        $this->assertSame('recovered', $case->outcome);
        $this->assertTrue($case->followUps()->where('is_closing', true)->exists());

        $this->actingAs($this->dataEntry)->post(route('health-cases.follow-ups.store', $case), ['observed_at' => now()->format('Y-m-d H:i')])->assertForbidden();
        $this->actingAs($this->dataEntry)->post(route('health-cases.reopen', $case), ['reopen_reason' => 'Limping again'])->assertRedirect();
        $this->assertSame('open', $case->fresh()->case_status);
        $this->assertTrue(ActivityLog::where('action', 'health_case_reopened')->exists());
    }

    // --- courses ----------------------------------------------------------

    public function test_a_course_with_two_medicines_schedules_doses_and_moves_no_stock(): void
    {
        $pigs = [$this->pig(), $this->pig()];
        $case = app(\App\Services\Health\HealthRecordService::class)->openCase(
            $this->dataEntry, $pigs, ['observed_at' => now()->subHour(), 'condition' => 'Group treatment'],
        );
        [$antibiotic, $painkiller] = [Medicine::factory()->create(['name' => 'Amoxicillin']), Medicine::factory()->create(['name' => 'Meloxicam'])];
        $movements = InventoryMovement::count();

        $this->actingAs($this->manager)->post(route('treatment-courses.store'), [
            'purpose' => 'treatment', 'health_record_id' => $case->id, 'pig_ids' => array_map(fn ($p) => $p->id, $pigs),
            'prescriber_name' => 'Dr Asante',
            'medicines' => [
                $this->line($antibiotic, ['frequency' => 'daily', 'number_of_doses' => 3]),
                $this->line($painkiller, ['frequency' => 'every_n_hours', 'interval' => 12, 'number_of_doses' => 2]),
            ],
        ])->assertSessionHasNoErrors();

        $course = TreatmentCourse::firstOrFail();
        $this->assertSame(2 * (3 + 2), ScheduledDose::count(), 'Two animals, three plus two doses each');
        $due = ScheduledDose::where('pig_id', $pigs[0]->id)->whereIn('treatment_course_medicine_id', $course->medicines()->where('medicine_id', $antibiotic->id)->pluck('id'))->orderBy('due_at')->pluck('due_at');
        $this->assertSame(['2026-06-16 08:00', '2026-06-17 08:00', '2026-06-18 08:00'], $due->map->format('Y-m-d H:i')->all());
        $this->assertSame('under_treatment', $case->fresh()->case_status);
        $this->assertSame($movements, InventoryMovement::count(), 'Prescribing never moves stock');
    }

    public function test_dose_units_must_match_the_medicine(): void
    {
        $medicine = Medicine::factory()->create(['dose_unit' => 'ml']);

        $this->actingAs($this->manager)->from('/')->post(route('treatment-courses.store'), [
            'purpose' => 'treatment', 'pig_ids' => [$this->pig()->id],
            'medicines' => [$this->line($medicine, ['dose_unit' => 'mg'])],
        ])->assertSessionHasErrors('medicines.0.dose_unit');
        $this->assertSame(0, TreatmentCourse::count());
    }

    public function test_amending_keeps_the_old_version_and_cancels_its_remaining_doses(): void
    {
        $pig = $this->pig();
        $medicine = Medicine::factory()->create();
        $this->actingAs($this->manager)->post(route('treatment-courses.store'), [
            'purpose' => 'vaccination', 'pig_ids' => [$pig->id], 'medicines' => [$this->line($medicine, ['number_of_doses' => 3])],
        ]);
        $old = TreatmentCourse::firstOrFail();

        $this->actingAs($this->manager)->put(route('treatment-courses.update', $old), [
            'pig_ids' => [$pig->id], 'medicines' => [$this->line($medicine, ['number_of_doses' => 2, 'dose_amount' => '3'])],
            'amendment_reason' => 'Vet halved the course',
        ])->assertSessionHasNoErrors();

        $new = TreatmentCourse::where('amended_from_course_id', $old->id)->firstOrFail();
        $this->assertSame('stopped', $old->fresh()->status);
        $this->assertSame('vaccination', $new->purpose);
        $this->assertSame(3, ScheduledDose::whereIn('treatment_course_medicine_id', $old->medicines()->pluck('id'))->where('status', 'cancelled')->count());
        $this->assertStringContainsString('Vet halved the course', ScheduledDose::where('status', 'cancelled')->value('status_reason'));
        $this->assertSame(2, ScheduledDose::whereIn('treatment_course_medicine_id', $new->medicines()->pluck('id'))->where('status', 'scheduled')->count());
        $this->assertEquals('2.0000', (string) $old->medicines()->first()->dose_amount, 'The earlier instructions are untouched');
    }

    public function test_route_must_be_one_the_medicine_allows(): void
    {
        $medicine = Medicine::factory()->create(['allowed_routes' => ['IM']]);

        $this->actingAs($this->manager)->from('/')->post(route('treatment-courses.store'), [
            'purpose' => 'treatment', 'pig_ids' => [$this->pig()->id], 'medicines' => [$this->line($medicine, ['route' => 'oral'])],
        ])->assertSessionHasErrors('medicines.0.route');
        $this->assertSame(0, TreatmentCourse::count());
    }

    public function test_a_preventive_course_needs_no_case_and_stopping_cancels_what_is_left(): void
    {
        $pigs = [$this->pig(), $this->pig()];
        $this->actingAs($this->manager)->post(route('treatment-courses.store'), [
            'purpose' => 'vaccination', 'pig_ids' => array_map(fn ($p) => $p->id, $pigs),
            'medicines' => [$this->line(Medicine::factory()->create(), ['frequency' => 'once'])],
        ])->assertSessionHasNoErrors();
        $course = TreatmentCourse::firstOrFail();
        $this->assertNull($course->health_record_id);
        $this->assertSame(2, ScheduledDose::count(), 'One dose per animal');
        $this->assertSame(0, HealthRecord::count(), 'Vaccination opens no case');

        $this->actingAs($this->manager)->from('/')->post(route('treatment-courses.stop', $course), [])->assertSessionHasErrors('stop_reason');
        $this->actingAs($this->manager)->post(route('treatment-courses.stop', $course), ['stop_reason' => 'Vaccine recalled'])->assertRedirect();

        $this->assertSame('stopped', $course->fresh()->status);
        $this->assertSame(2, ScheduledDose::where('status', 'cancelled')->count());
        $this->assertTrue(ActivityLog::where('action', 'treatment_course_stopped')->exists());
    }

    public function test_only_managers_prescribe(): void
    {
        $this->actingAs($this->dataEntry)->post(route('treatment-courses.store'), [
            'purpose' => 'deworming', 'pig_ids' => [$this->pig()->id], 'medicines' => [$this->line(Medicine::factory()->create())],
        ])->assertForbidden();
    }

    // --- catalogue, search, scope -------------------------------------------

    public function test_withdrawal_days_need_a_source_and_duplicates_are_refused(): void
    {
        $base = ['name' => 'Ivermectin', 'concentration' => '1%', 'dose_unit' => 'ml', 'stock_unit' => 'ml', 'dose_units_per_stock_unit' => 1, 'is_active' => 1];

        $this->actingAs($this->manager)->from('/')->post(route('medicines.store'), $base + ['meat_withdrawal_days' => 28])->assertSessionHasErrors('withdrawal_source_note');
        $this->actingAs($this->manager)->post(route('medicines.store'), $base + ['meat_withdrawal_days' => 28, 'withdrawal_source_note' => 'Label'])->assertSessionHasNoErrors();
        $this->actingAs($this->manager)->from('/')->post(route('medicines.store'), $base)->assertSessionHasErrors('name');
        $this->assertTrue(ActivityLog::where('action', 'medicine_added')->exists());
        $this->actingAs($this->dataEntry)->post(route('medicines.store'), $base + ['name' => 'Other'])->assertForbidden();
    }

    public function test_pigs_are_found_by_code_or_tag(): void
    {
        $pig = $this->pig();
        $tag = $pig->currentEarTagAssignment()->first()?->tag;

        $this->actingAs($this->dataEntry)->getJson(route('health.pig-search', ['q' => $pig->code]))->assertOk()->assertJsonFragment(['id' => $pig->id]);
        if ($tag) {
            $this->actingAs($this->dataEntry)->getJson(route('health.pig-search', ['q' => $tag->tag_number]))->assertOk()->assertJsonFragment(['id' => $pig->id]);
        }
    }

    /** Opening the picker lists the farm's live animals; typing still searches. */
    public function test_the_picker_lists_live_animals_when_opened(): void
    {
        $first = $this->pig();
        $second = $this->pig();
        $gone = $this->pig();
        $gone->delete();

        $ids = collect($this->actingAs($this->dataEntry)->getJson(route('health.pig-search'))->assertOk()->json())->pluck('id');

        $this->assertTrue($ids->contains($first->id) && $ids->contains($second->id));
        $this->assertFalse($ids->contains($gone->id), 'Animals that left are found by search, not listed.');
        $this->actingAs($this->dataEntry)->get(route('health.create'))->assertOk()->assertSee('role="combobox"', false);
    }

    public function test_another_farms_cases_visits_and_courses_return_404(): void
    {
        $other = Organization::factory()->create();
        app(CurrentOrganization::class)->bind($other);
        $theirPig = PigManagement::factory()->create(['organization_id' => $other->id]);
        $case = HealthRecord::create(['pig_id' => $theirPig->id, 'record_date' => '2026-06-14', 'condition' => 'Cough', 'case_status' => 'open']);
        $visit = VetVisit::factory()->create(['organization_id' => $other->id]);
        $course = TreatmentCourse::factory()->create(['organization_id' => $other->id]);
        app(CurrentOrganization::class)->bind($this->org);

        $this->actingAs($this->manager)->get(route('health-cases.show', $case->id))->assertNotFound();
        $this->actingAs($this->manager)->get(route('vet-visits.show', $visit->id))->assertNotFound();
        $this->actingAs($this->manager)->get(route('treatment-courses.show', $course->id))->assertNotFound();
        $this->actingAs($this->manager)->post(route('health-cases.close', $case->id), ['final_status' => 'closed', 'outcome' => 'recovered', 'closed_at' => now()->format('Y-m-d H:i')])->assertNotFound();
    }

    // --- helpers --------------------------------------------------------------

    /** Switching users mid-test would trip the password-hash session check, so start a fresh session per user. */
    public function actingAs($user, $guard = null)
    {
        if (auth()->user()?->isNot($user)) {
            $this->flushSession();
        }

        return parent::actingAs($user, $guard);
    }

    private function examine(VetVisit $visit, PigManagement $pig, array $data): void
    {
        $this->actingAs($this->dataEntry)->post(route('vet-visits.examinations.store', $visit), ['pig_id' => $pig->id] + $data)
            ->assertSessionHasNoErrors();
    }

    private function openCase(PigManagement $pig): HealthRecord
    {
        $this->actingAs($this->dataEntry)->post(route('health.store'), [
            'pig_ids' => [$pig->id], 'observed_at' => now()->subDay()->format('Y-m-d H:i'), 'condition' => 'Lameness',
        ])->assertSessionHasNoErrors();

        return HealthRecord::latest('id')->firstOrFail();
    }

    private function line(Medicine $medicine, array $overrides = []): array
    {
        return $overrides + ['medicine_id' => $medicine->id, 'dose_amount' => '2', 'dose_unit' => $medicine->dose_unit,
            'frequency' => 'daily', 'number_of_doses' => 3, 'start_at' => '2026-06-16 08:00'];
    }

    private function pig(array $attributes = []): PigManagement
    {
        $n = ++$this->sequence;

        return PigManagement::create($attributes + [
            'organization_id' => $this->org->id, 'ear_tag_number' => (string) (100 + $n), 'code' => 'WF-'.$n,
            'breed' => 'LW', 'gender' => 'F', 'stock_type' => 'standard', 'date_of_birth' => now()->subMonths(5)->toDateString(),
        ]);
    }

    private function room(string $name): Room
    {
        $structure = Structure::firstOrCreate(['organization_id' => $this->org->id, 'name' => 'Main barn'], ['code' => 'MB']);

        return Room::create(['organization_id' => $this->org->id, 'structure_id' => $structure->id, 'name' => $name, 'code' => strtoupper(substr($name, -1))]);
    }

    private function user(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);

        return User::forceCreate([
            'name' => ucfirst($roleName), 'email' => $roleName.'-'.(++$this->sequence).'@workflow.test', 'password' => bcrypt('password'),
            'role' => $roleName, 'role_id' => $role->id, 'organization_id' => $this->org->id, 'active_status' => true,
        ]);
    }
}
