<?php

namespace Tests\Feature\Health;

use App\Models\ActivityLog;
use App\Models\EarTag;
use App\Models\EarTagColor;
use App\Models\HealthRecord;
use App\Models\MedicationDosingEvent;
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
use App\Services\Stock\Tags\EarTagAssignmentService;
use App\Services\Health\HealthTimelineService;
use App\Services\Health\WithdrawalStatusService;
use App\Storefront\Services\PigListingEligibilityService;
use App\Support\CurrentOrganization;
use Database\Seeders\FarmOperationsPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Acceptance scenarios from the implementation plan, section 7, that are not
 * already proven end to end elsewhere. Each test names its scenario number.
 * Scenario 5 (simultaneous recording) is in tests/Concurrency.
 */
class HealthAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $dataEntry;

    private User $manager;

    private EarTagColor $green;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 6, 15)->setTime(7, 0));
        $this->org = Organization::factory()->create();
        app(CurrentOrganization::class)->bind($this->org);
        $this->seed(FarmOperationsPermissionsSeeder::class);
        $this->dataEntry = $this->user('dataentry');
        $this->manager = $this->user('farmmanager');
        $this->green = EarTagColor::create(['organization_id' => $this->org->id, 'name' => 'Green', 'normalized_name' => 'green', 'hex_color' => '#15803D']);
    }

    /** Scenario 1: case, two medicines, several doses, follow-up, close; the timeline tells the whole story. */
    public function test_scenario_1_a_case_from_first_sign_to_recovery(): void
    {
        $pig = $this->pig();
        $case = $this->openCase([$pig], 'Lameness');
        [$antibiotic, $painkiller] = [$this->stocked('Amoxicillin', 14), $this->stocked('Meloxicam', 5)];
        $this->actingAs($this->manager)->post(route('treatment-courses.store'), [
            'purpose' => 'treatment', 'health_record_id' => $case->id, 'pig_ids' => [$pig->id],
            'medicines' => [$this->line($antibiotic, 'daily', 2), $this->line($painkiller, 'once', 1)],
        ])->assertSessionHasNoErrors();

        $this->travelTo(now()->setTime(9, 0));
        $this->giveDue();
        $this->travelTo(now()->addDay()->setTime(9, 0));
        $this->giveDue();
        $this->assertSame('completed', TreatmentCourse::firstOrFail()->status);
        $this->assertSame('under_treatment', $case->fresh()->case_status, 'Finishing a course does not resolve the case (D1)');

        $this->actingAs($this->dataEntry)->post(route('health-cases.follow-ups.store', $case), ['observed_at' => now()->format('Y-m-d H:i'), 'response' => 'improving']);
        $this->travelTo(now()->addHour());
        $this->actingAs($this->dataEntry)->post(route('health-cases.close', $case), [
            'final_status' => 'closed', 'outcome' => 'recovered', 'closed_at' => now()->format('Y-m-d H:i'), 'closure_notes' => 'Walking normally',
        ])->assertSessionHasNoErrors();

        $kinds = app(HealthTimelineService::class)->forPig($pig->fresh())->countBy('kind')->all();
        $kinds = array_intersect_key($kinds, array_flip(['case', 'course', 'dose', 'follow_up', 'withdrawal']));
        ksort($kinds);
        $this->assertSame(['case' => 1, 'course' => 1, 'dose' => 3, 'follow_up' => 2, 'withdrawal' => 3], $kinds);
        $this->assertSame('2026-06-30', app(WithdrawalStatusService::class)->forPig($pig)['until']->toDateString(), 'Last Amoxicillin dose on the 16th + 14 days');
        $this->actingAs($this->dataEntry)->get(route('health.show', $pig))->assertOk()
            ->assertSeeInOrder(['Case closed: Recovered', 'Follow-up: Improving', 'Dose: Amoxicillin', 'Treatment course', 'Health case: Lameness']);
    }

    /** Scenario 3: a group treated in part, then a pen change; who was treated and what was used stay as recorded. */
    public function test_scenario_3_partial_group_treatment_survives_a_pen_change(): void
    {
        [$penA, $penB] = [$this->room('Pen A'), $this->room('Pen B')];
        $pigs = [$this->pig($penA), $this->pig($penA), $this->pig($penA)];
        $case = $this->openCase($pigs, 'Coughing', 'Pen A');
        $medicine = $this->stocked('Tylosin', 14);
        $this->actingAs($this->manager)->post(route('treatment-courses.store'), [
            'purpose' => 'treatment', 'health_record_id' => $case->id, 'pig_ids' => array_map(fn ($p) => $p->id, $pigs), 'medicines' => [$this->line($medicine, 'once', 1)],
        ])->assertSessionHasNoErrors();

        $this->travelTo(now()->setTime(9, 0));
        $due = ScheduledDose::orderBy('pig_id')->get();
        $this->record($medicine, [$pigs[0], $pigs[1]], ['scheduled_dose_ids' => $due->take(2)->modelKeys(), 'health_record_id' => $case->id]);
        $this->actingAs($this->dataEntry)->post(route('scheduled-doses.close', $due[2]), ['status' => 'missed', 'reason' => 'Could not catch her']);

        $pigs[0]->update(['room_id' => $penB->id]);
        $this->pig($penA);  // a newcomer to Pen A

        $this->assertEqualsCanonicalizing(array_map(fn ($p) => $p->id, $pigs), $case->animals()->pluck('pig_id')->all());
        $this->assertEqualsCanonicalizing([$pigs[0]->id, $pigs[1]->id], MedicationDosingEvent::firstOrFail()->administrations()->pluck('pig_id')->all());
        $this->assertSame('4.0000', (string) MedicationDosingEvent::firstOrFail()->total_stock_quantity);
        $csv = $this->actingAs($this->dataEntry)->get(route('health.reports.export', ['tab' => 'usage', 'by' => 'animal']))->streamedContent();
        $this->assertSame(2, substr_count($csv, 'Tylosin'), 'Usage by animal lists only the two treated pigs');
        $this->assertFalse(app(WithdrawalStatusService::class)->forPig($pigs[2])['restricted'], 'The missed pig is not under withdrawal');
    }

    /** Scenario 4: dates that end before they start are refused. */
    public function test_scenario_4_nothing_can_end_before_it_started(): void
    {
        $case = $this->openCase([$this->pig()], 'Diarrhoea');

        $this->actingAs($this->dataEntry)->from('/')->post(route('health-cases.close', $case), [
            'final_status' => 'resolved', 'outcome' => 'recovered', 'closed_at' => now()->subDays(3)->format('Y-m-d H:i'),
        ])->assertSessionHasErrors();
        $this->actingAs($this->dataEntry)->from('/')->post(route('health-cases.follow-ups.store', $case), [
            'observed_at' => now()->subDays(3)->format('Y-m-d H:i'), 'response' => 'improving',
        ])->assertSessionHasErrors();
        $this->assertSame('open', $case->fresh()->case_status);
    }

    /** Scenario 8: overlapping withdrawals, the longest one counts; changing the last dose recalculates; sale and storefront both refuse. */
    public function test_scenario_8_overlapping_withdrawals_follow_corrections(): void
    {
        $pig = $this->pig();
        $long = $this->stocked('Oxytetracycline LA', 28);
        $short = $this->stocked('Penicillin', 7);
        $this->record($long, [$pig], ['administered_at' => now()->subDays(10)->format('Y-m-d H:i')]);
        $this->record($short, [$pig], ['administered_at' => now()->subDay()->format('Y-m-d H:i')]);

        $withdrawal = app(WithdrawalStatusService::class);
        $this->assertSame('2026-07-03', $withdrawal->forPig($pig)['until']->toDateString(), 'The 28-day dose on the 5th outlasts the 7-day dose');
        $this->assertCount(2, $withdrawal->forPig($pig)['doses'], 'Both doses stay visible');
        $this->assertFalse(app(PigListingEligibilityService::class)->check($pig)['eligible']);

        $longDose = MedicationDosingEvent::where('medicine_id', $long->id)->firstOrFail();
        $this->actingAs($this->manager)->put(route('doses.update', $longDose), $this->dosePayload($short, [$pig], [
            'administered_at' => now()->subDays(10)->format('Y-m-d H:i'), 'correction_reason' => 'It was Penicillin, not Oxytetracycline',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('2026-06-21', $withdrawal->forPig($pig)['until']->toDateString(), 'Now the latest is the 14th + 7 days');
        $this->assertTrue(ActivityLog::where('action', 'medication_dose_corrected')->exists());
        $this->travelTo(now()->setDate(2026, 6, 22));
        $this->assertFalse($withdrawal->forPig($pig)['restricted']);
        $this->assertTrue(app(PigListingEligibilityService::class)->check($pig->fresh())['eligible']);
    }

    /** Scenarios 9 and 13: a pig leaves, its tag goes to another pig; history and finalised notes stay with the first pig. */
    public function test_scenarios_9_and_13_history_follows_the_animal_not_the_tag(): void
    {
        $first = $this->pig(null, '3');
        $this->travelTo(now()->addHours(2));  // the visit is after the pig was tagged
        $tagThen = $first->tagLabelAt(now());
        $medicine = $this->stocked('Ivermectin', 21);

        // First visit: abnormal finding with a case; then a repeat after a pen change.
        $this->actingAs($this->dataEntry)->post(route('vet-visits.store'), ['visited_at' => now()->subHour()->format('Y-m-d H:i'), 'veterinarian_name' => 'Dr Asante', 'general_notes' => 'Herd mostly fine']);
        $visit = VetVisit::firstOrFail();
        $this->actingAs($this->dataEntry)->post(route('vet-visits.examinations.store', $visit), ['pig_id' => $first->id, 'assessment' => 'abnormal_findings',
            'findings' => 'Mange on flank', 'follow_up_on' => now()->addWeek()->toDateString(), 'open_case' => 1, 'case_condition' => 'Mange'])->assertSessionHasNoErrors();
        $this->record($medicine, [$first]);
        $first->update(['room_id' => $this->room('Isolation')->id]);
        $this->actingAs($this->dataEntry)->post(route('vet-visits.examinations.store', $visit), ['pig_id' => $first->id, 'assessment' => 'no_issue_found',
            'is_repeat' => 1, 'vet_notes' => 'Better after moving'])->assertSessionHasNoErrors();

        // Finalised notes are amended with history.
        $this->actingAs($this->manager)->post(route('vet-visits.finalise', $visit));
        $this->actingAs($this->manager)->put(route('vet-visits.update', $visit), ['visited_at' => $visit->visited_at->format('Y-m-d H:i'), 'veterinarian_name' => 'Dr Asante',
            'general_notes' => 'Herd fine; one pig with mange isolated', 'amendment_reason' => 'Vet added a line by phone'])->assertSessionHasNoErrors();
        $this->assertSame('Herd mostly fine', ActivityLog::where('action', 'vet_visit_amended')->firstOrFail()->old_values['general_notes']);

        // The pig is culled for disposal (allowed during withdrawal) and its tag reused.
        $this->travelTo(now()->addDays(2));
        $this->actingAs($this->manager)->post(route('pig-exits.store', $first), ['exit_type' => 'cull', 'exit_date' => now()->toDateString(),
            'reason' => 'lameness', 'disposal' => 'destroyed', 'tag_outcome' => EarTag::AVAILABLE])->assertSessionHasNoErrors();
        $second = $this->pig(null, null);
        app(EarTagAssignmentService::class)->assign($second, $this->green, '3', $this->manager);
        $this->assertSame($tagThen, $second->fresh()->tagLabelAt(now()), 'The same tag is now on another pig');

        $this->assertSame(2, VetExamination::where('pig_id', $first->id)->count());
        $this->assertSame(0, VetExamination::where('pig_id', $second->id)->count() + HealthRecord::where('pig_id', $second->id)->count());
        $this->assertTrue(app(HealthTimelineService::class)->forPig($second->fresh())->isEmpty(), 'Nothing from the first pig shows on the new tag holder');
        $this->assertFalse(app(WithdrawalStatusService::class)->forPig($second)['restricted']);

        $firstTimeline = app(HealthTimelineService::class)->forPig(PigManagement::withTrashed()->findOrFail($first->id));
        $this->assertSame([$tagThen], $firstTimeline->where('kind', 'examination')->pluck('tag')->unique()->values()->all(), 'Entries show the tag worn on the day');
        $this->actingAs($this->dataEntry)->get(route('health.show', $first->id))->assertOk()->assertSee('Mange');
        $this->actingAs($this->dataEntry)->get(route('vet-visits.show', $visit))->assertOk()->assertSee($first->code)->assertSee('Mange on flank');
    }

    // --- helpers --------------------------------------------------------------------

    private function giveDue(): void
    {
        ScheduledDose::with('courseMedicine.medicine')->where('status', 'scheduled')->where('due_at', '<=', now())->get()
            ->groupBy(fn ($d) => $d->treatment_course_medicine_id.'|'.$d->due_at)
            ->each(fn ($slot) => $this->record($slot->first()->courseMedicine->medicine, PigManagement::whereKey($slot->pluck('pig_id'))->get()->all(),
                ['scheduled_dose_ids' => $slot->modelKeys(), 'dose_amount' => (string) $slot->first()->courseMedicine->dose_amount]));
    }

    private function record(Medicine $medicine, array $pigs, array $extra = []): void
    {
        $this->actingAs($this->dataEntry)->post(route('medications.store'), $this->dosePayload($medicine, $pigs, $extra))->assertSessionHasNoErrors();
    }

    private function dosePayload(Medicine $medicine, array $pigs, array $extra = []): array
    {
        return $extra + ['pig_ids' => array_map(fn ($p) => $p->id, $pigs), 'medicine_id' => $medicine->id, 'administered_at' => now()->subMinutes(5)->format('Y-m-d H:i'),
            'amount_basis' => 'per_animal', 'dose_amount' => '2', 'dose_unit' => 'ml', 'idempotency_key' => (string) Str::uuid()];
    }

    private function line(Medicine $medicine, string $frequency, int $doses): array
    {
        return ['medicine_id' => $medicine->id, 'dose_amount' => '2', 'dose_unit' => 'ml', 'frequency' => $frequency, 'number_of_doses' => $doses, 'start_at' => '2026-06-15 08:00'];
    }

    private function stocked(string $name, int $withdrawalDays): Medicine
    {
        $medicine = Medicine::factory()->create(['name' => $name, 'meat_withdrawal_days' => $withdrawalDays, 'allowed_routes' => []]);
        $this->actingAs($this->manager)->post(route('medicines.batches.store', $medicine), ['batch_number' => 'L-'.Str::random(5), 'received_at' => '2026-06-01',
            'expiry_date' => '2027-06-01', 'quantity_received' => '200', 'unit_cost' => '1.5'])->assertSessionHasNoErrors();

        return $medicine;
    }

    private function openCase(array $pigs, string $condition, ?string $group = null): HealthRecord
    {
        $this->actingAs($this->dataEntry)->post(route('health.store'), array_filter(['pig_ids' => array_map(fn ($p) => $p->id, $pigs), 'group_label' => $group,
            'observed_at' => now()->subHour()->format('Y-m-d H:i'), 'condition' => $condition, 'severity' => 'moderate']))->assertSessionHasNoErrors();

        return HealthRecord::latest('id')->firstOrFail();
    }

    private function pig(?Room $room = null, ?string $tag = 'auto'): PigManagement
    {
        $n = ++$this->sequence;
        $pig = PigManagement::create(array_filter([
            'organization_id' => $this->org->id, 'ear_tag_number' => $tag === 'auto' ? (string) (600 + $n) : $tag, 'code' => 'AC-'.$n,
            'breed' => 'LW', 'gender' => 'F', 'stock_type' => 'standard', 'date_of_birth' => now()->subMonths(5)->toDateString(), 'room_id' => $room?->id,
        ], fn ($v) => $v !== null));
        if ($tag !== 'auto' && $tag !== null && ! $pig->currentEarTagAssignment()->exists()) {
            app(EarTagAssignmentService::class)->assign($pig, $this->green, $tag, $this->manager);
        }

        return $pig;
    }

    private function room(string $name): Room
    {
        $structure = Structure::firstOrCreate(['organization_id' => $this->org->id, 'name' => 'Main barn'], ['code' => 'MB']);

        return Room::create(['organization_id' => $this->org->id, 'structure_id' => $structure->id, 'name' => $name, 'code' => strtoupper(Str::random(3))]);
    }

    /** Switching users mid-test would trip the password-hash session check, so start a fresh session per user. */
    public function actingAs($user, $guard = null)
    {
        if (auth()->user()?->isNot($user)) {
            $this->flushSession();
        }

        return parent::actingAs($user, $guard);
    }

    private function user(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);

        return User::forceCreate([
            'name' => ucfirst($roleName), 'email' => $roleName.'-'.(++$this->sequence).'@acceptance.test', 'password' => bcrypt('password'),
            'role' => $roleName, 'role_id' => $role->id, 'organization_id' => $this->org->id, 'active_status' => true,
        ]);
    }
}
