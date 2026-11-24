<?php

namespace Tests\Feature\Health;

use App\Models\HealthRecord;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\Organization;
use App\Models\PigManagement;
use App\Models\Role;
use App\Models\ScheduledDose;
use App\Models\TreatmentCourse;
use App\Models\User;
use App\Models\VetExamination;
use App\Models\VetVisit;
use App\Support\CurrentOrganization;
use Database\Seeders\FarmOperationsPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The reminders added after the review: medicine run out or short for the
 * doses due, expired batches still in stock, finished courses, follow-ups
 * that have passed, and open cases nobody has looked at. Plus the bell and
 * the at-a-glance cards.
 */
class HealthRemindersCoverageTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $manager;

    private User $dataEntry;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 6, 15)->setTime(7, 0));
        $this->org = Organization::factory()->create();
        app(CurrentOrganization::class)->bind($this->org);
        $this->seed(FarmOperationsPermissionsSeeder::class);
        $this->manager = $this->user('farmmanager');
        $this->dataEntry = $this->user('dataentry');
    }

    public function test_a_medicine_that_runs_out_is_reported_even_without_a_reorder_level(): void
    {
        $medicine = $this->stocked('Iron dextran', '4', reorder: null);
        $this->dose($medicine, [$this->pig(), $this->pig()]);  // 2 ml each: all 4 ml used

        $this->notify();
        $this->notify();

        $this->assertSame(1, $this->sent('health.medicine_out_of_stock'));
        $this->assertSame(0, $this->sent('health.medication_low_stock'), 'Empty is reported as out, not as low');
        $this->assertStringContainsString('Iron dextran has run out', $this->latest('health.medicine_out_of_stock')->data['title']);
    }

    public function test_not_enough_stock_for_the_doses_due_is_reported_once_a_day(): void
    {
        $medicine = $this->stocked('Amoxicillin', '4');
        $this->course($medicine, [$this->pig(), $this->pig(), $this->pig()], ['start' => '2026-06-15 08:00']);  // needs 6 ml

        $this->notify();
        $this->notify();
        $this->assertSame(1, $this->sent('health.stock_short'));
        $this->assertStringContainsString('need 6 ml', $this->latest('health.stock_short')->data['message']);

        $this->travelTo(now()->addDay()->setTime(7, 0));
        $this->notify();
        $this->assertSame(2, $this->sent('health.stock_short'), 'Reminded again the next day while still short');
    }

    public function test_an_expired_batch_still_in_stock_is_reported(): void
    {
        $medicine = $this->stocked('Penicillin', '50', expiry: '2026-06-20');
        $this->travelTo(now()->setDate(2026, 6, 25));
        $this->notify();
        $this->notify();

        $this->assertSame(1, $this->sent('health.batch_expired'));
        $this->assertStringContainsString('dispose of it', $this->latest('health.batch_expired')->data['message']);
        $this->assertNotNull($medicine);
    }

    public function test_a_finished_course_asks_for_the_case_to_be_checked(): void
    {
        $pig = $this->pig();
        $case = $this->openCase([$pig], 'Scours');
        $medicine = $this->stocked('Amoxicillin', '100');
        $course = $this->course($medicine, [$pig], ['start' => '2026-06-15 06:30', 'frequency' => 'once', 'case' => $case->id]);
        $this->dose($medicine, [$pig], ['scheduled_dose_ids' => ScheduledDose::pluck('id')->all(), 'health_record_id' => $case->id]);
        $this->assertSame('completed', $course->fresh()->status);

        $this->notify();
        $this->notify();

        $this->assertSame(1, $this->sent('health.course_finished'));
        $this->assertStringContainsString('close the case "Scours"', $this->latest('health.course_finished')->data['message']);
        $this->assertSame(route('health-cases.show', $case), $this->latest('health.course_finished')->data['link']);
    }

    public function test_follow_ups_that_have_passed_are_chased_once(): void
    {
        $medicine = $this->stocked('Amoxicillin', '100');
        $this->course($medicine, [$this->pig()], ['start' => '2026-06-20 08:00', 'follow_up_on' => '2026-06-16']);
        [$checked, $forgotten] = [$this->pig(), $this->pig()];
        $this->actingAs($this->dataEntry)->post(route('vet-visits.store'), ['visited_at' => now()->subHour()->format('Y-m-d H:i'), 'veterinarian_name' => 'Dr Mensah']);
        $visit = VetVisit::firstOrFail();
        foreach ([$checked, $forgotten] as $pig) {
            $this->actingAs($this->dataEntry)->post(route('vet-visits.examinations.store', $visit), ['pig_id' => $pig->id, 'assessment' => 'abnormal_findings',
                'follow_up_on' => '2026-06-17', 'open_case' => 1, 'case_condition' => 'Lameness'])->assertSessionHasNoErrors();
        }

        $this->travelTo(now()->setDate(2026, 6, 18)->setTime(9, 0));
        $checkedCase = HealthRecord::where('pig_id', $checked->id)->firstOrFail();
        $this->actingAs($this->dataEntry)->post(route('health-cases.follow-ups.store', $checkedCase), ['observed_at' => now()->format('Y-m-d H:i'), 'response' => 'improving']);
        $this->notify();
        $this->notify();

        $titles = $this->manager->notifications()->where('data->type', 'health.follow_up_overdue')->get()->pluck('data.title');
        $this->assertCount(2, $titles);
        $this->assertTrue($titles->contains('Follow-up overdue: course #'.TreatmentCourse::firstOrFail()->id));
        $this->assertTrue($titles->contains('Re-check overdue: '.$forgotten->code));
        $this->assertFalse($titles->contains('Re-check overdue: '.$checked->code), 'A follow-up was recorded after the date');
    }

    public function test_an_open_case_left_alone_for_a_week_is_flagged_weekly(): void
    {
        $quiet = $this->openCase([$this->pig()], 'Coughing');
        $busy = $this->openCase([$this->pig()], 'Lameness');

        $this->travelTo(now()->addDays(9));
        $this->actingAs($this->dataEntry)->post(route('health-cases.follow-ups.store', $busy), ['observed_at' => now()->format('Y-m-d H:i'), 'response' => 'unchanged']);
        $this->notify();
        $this->notify();
        $this->assertSame(1, $this->sent('health.case_needs_review'));
        $this->assertStringContainsString('Coughing', $this->latest('health.case_needs_review')->data['title']);

        $this->travelTo(now()->addWeek()->addHour());  // just over a week since the busy case's follow-up
        $this->notify();
        $this->assertSame(2, $this->sent('health.case_needs_review'), 'One reminder a week');
        $this->assertSame('2 open cases need a check', $this->latest('health.case_needs_review')->data['title'], 'Both cases are now quiet, listed together');
        $this->assertNotNull($quiet);
    }

    public function test_the_bell_shows_the_reminder_title_and_message(): void
    {
        $this->stocked('Iron dextran', '2', reorder: null);
        $this->dose(Medicine::firstOrFail(), [$this->pig()]);
        $this->notify();

        $this->actingAs($this->manager)->get(route('health.index'))->assertOk()
            ->assertSee('Iron dextran has run out')->assertSee('Receive a batch before the next dose is due');
    }

    public function test_the_at_a_glance_cards_show_what_needs_attention(): void
    {
        $medicine = $this->stocked('Amoxicillin', '4');
        $pigs = [$this->pig(), $this->pig(), $this->pig()];
        $this->course($medicine, $pigs, ['start' => '2026-06-15 08:00']);

        $this->actingAs($this->dataEntry)->get(route('medicines.show', $medicine))->assertOk()
            ->assertSeeInOrder(['On hand', '4 ml', 'Needed, next 7 days', '6 ml']);
        $this->actingAs($this->dataEntry)->get(route('health.worklist'))->assertOk()->assertSeeInOrder(['Still to give', '3', 'of 3 due today']);
        $this->actingAs($this->dataEntry)->get(route('health.show', $pigs[0]))->assertOk()->assertSeeInOrder(['Next dose', 'Mon 15 Jun 08:00', 'Amoxicillin']);
        $this->actingAs($this->dataEntry)->get(route('medicines.index'))->assertOk()->assertSeeInOrder(['Out of stock or low', '1', '0 out · 1 low']);
    }

    // --- helpers ------------------------------------------------------------------------

    private function stocked(string $name, string $quantity, ?string $reorder = '5', string $expiry = '2027-06-01'): Medicine
    {
        $medicine = Medicine::factory()->create(['name' => $name, 'allowed_routes' => [], 'reorder_level' => $reorder]);
        $this->actingAs($this->manager)->post(route('medicines.batches.store', $medicine), ['batch_number' => 'L-'.Str::random(5), 'received_at' => '2026-06-01',
            'expiry_date' => $expiry, 'quantity_received' => $quantity, 'unit_cost' => '1'])->assertSessionHasNoErrors();

        return $medicine;
    }

    private function course(Medicine $medicine, array $pigs, array $o = []): TreatmentCourse
    {
        $this->actingAs($this->manager)->post(route('treatment-courses.store'), array_filter([
            'purpose' => 'treatment', 'health_record_id' => $o['case'] ?? null, 'pig_ids' => array_map(fn ($p) => $p->id, $pigs), 'follow_up_on' => $o['follow_up_on'] ?? null,
            'medicines' => [['medicine_id' => $medicine->id, 'dose_amount' => '2', 'dose_unit' => 'ml', 'frequency' => $o['frequency'] ?? 'daily',
                'number_of_doses' => 1, 'start_at' => $o['start']]],
        ]))->assertSessionHasNoErrors();

        return TreatmentCourse::latest('id')->firstOrFail();
    }

    private function dose(Medicine $medicine, array $pigs, array $extra = []): void
    {
        $this->actingAs($this->dataEntry)->post(route('medications.store'), $extra + ['pig_ids' => array_map(fn ($p) => $p->id, $pigs), 'medicine_id' => $medicine->id,
            'administered_at' => now()->subMinutes(10)->format('Y-m-d H:i'), 'amount_basis' => 'per_animal', 'dose_amount' => '2', 'dose_unit' => 'ml',
            'idempotency_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
    }

    private function openCase(array $pigs, string $condition): HealthRecord
    {
        $this->actingAs($this->dataEntry)->post(route('health.store'), ['pig_ids' => array_map(fn ($p) => $p->id, $pigs),
            'observed_at' => now()->subHour()->format('Y-m-d H:i'), 'condition' => $condition])->assertSessionHasNoErrors();

        return HealthRecord::latest('id')->firstOrFail();
    }

    private function notify(): void
    {
        $this->artisan('health:notify')->assertSuccessful();
    }

    private function sent(string $type): int
    {
        return $this->manager->notifications()->where('data->type', $type)->count();
    }

    private function latest(string $type)
    {
        return $this->manager->notifications()->where('data->type', $type)->latest()->firstOrFail();
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

        return PigManagement::create(['organization_id' => $this->org->id, 'ear_tag_number' => (string) (900 + $n), 'code' => 'RC-'.$n,
            'breed' => 'LW', 'gender' => 'F', 'stock_type' => 'standard', 'date_of_birth' => now()->subMonths(5)->toDateString()]);
    }

    private function user(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);

        return User::forceCreate(['name' => ucfirst($roleName).' '.(++$this->sequence), 'email' => $roleName.'-'.$this->sequence.'@coverage.test',
            'password' => bcrypt('password'), 'role' => $roleName, 'role_id' => $role->id, 'organization_id' => $this->org->id, 'active_status' => true]);
    }
}
