<?php

namespace Tests\Feature\Health;

use App\Models\HealthRecord;
use App\Models\MedicationAdministration;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\PigManagement;
use App\Models\Role;
use App\Models\ScheduledDose;
use App\Models\TreatmentCourse;
use App\Models\User;
use App\Support\CurrentOrganization;
use Database\Seeders\FarmOperationsPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Health upgrade Phase 4: reminders, worklist, overview, reports and exports, permissions. */
class HealthRemindersAndReportsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $dataEntry;

    private User $manager;

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
    }

    // --- reminders ------------------------------------------------------------------

    public function test_dose_reminders_go_to_managers_and_the_responsible_person_once(): void
    {
        $pigs = [$this->pig(), $this->pig()];
        $this->course($pigs, ['responsible_user_id' => $this->dataEntry->id]);
        $other = $this->user('dataentry');

        $this->notify();
        $this->notify();  // a second run sends nothing new

        $this->assertSame(1, $this->sent($this->manager, 'health.treatment_due'));
        $this->assertSame(1, $this->sent($this->dataEntry, 'health.treatment_due'), 'Responsible for the course');
        $this->assertSame(0, $this->sent($other, 'health.treatment_due'), 'Not a manager, not responsible');
        $this->assertSame(2, DB::table('health_reminders')->where('type', 'health.treatment_due')->count(), 'One key per dose');

        $this->travelTo(now()->setTime(10, 30));
        $this->notify();
        $this->notify();
        $this->assertSame(1, $this->sent($this->manager, 'health.dose_overdue'));
        $this->assertStringContainsString($pigs[0]->code, $this->latest($this->manager)->data['message']);
    }

    public function test_no_reminders_for_cancelled_doses_stopped_courses_or_pigs_that_left(): void
    {
        $gone = $this->pig();
        $course = $this->course([$this->pig(), $gone]);
        $gone->delete();  // sold or dead pigs are soft-deleted
        $this->actingAs($this->manager)->post(route('treatment-courses.stop', $course), ['stop_reason' => 'Vet changed plan']);

        $this->travelTo(now()->setTime(11, 0));
        $this->notify();
        $this->assertSame(0, DB::table('health_reminders')->whereIn('type', ['health.treatment_due', 'health.dose_overdue'])->count());
    }

    public function test_withdrawal_follow_up_expiry_and_low_stock_reminders(): void
    {
        $pig = $this->pig();
        $later = $this->pig();
        $medicine = Medicine::factory()->create(['name' => 'Oxytet', 'meat_withdrawal_days' => 7, 'reorder_level' => '95']);
        $this->receive($medicine, 'L1', '100', '2026-07-01');
        $this->dose($medicine, [$pig, $later], now()->subDays(5)->setTime(6, 0));                  // ends on the 17th
        $this->dose($medicine, [$later], now()->subDays(1)->setTime(6, 0));                        // keeps $later restricted to the 21st
        $this->course([$this->pig()], ['follow_up_on' => '2026-06-16', 'start' => '2026-06-20 08:00']);

        $this->notify();
        $this->notify();

        $withdrawal = $this->latest($this->manager, 'health.withdrawal_ending');
        $this->assertStringContainsString($pig->code, $withdrawal->data['message']);
        $this->assertStringNotContainsString($later->code, $withdrawal->data['message']);
        $this->assertSame(1, $this->sent($this->manager, 'health.follow_up_due'));
        $this->assertSame(1, $this->sent($this->manager, 'health.batch_expiring'));
        $this->assertSame(1, $this->sent($this->manager, 'health.medication_low_stock'));
    }

    public function test_reminders_never_cross_farms(): void
    {
        $other = Organization::factory()->create();
        app(CurrentOrganization::class)->bind($other);
        $theirManager = $this->user('farmmanager', $other);
        $theirPig = PigManagement::create(['organization_id' => $other->id, 'ear_tag_number' => '999', 'code' => 'OT-1', 'breed' => 'LW',
            'gender' => 'F', 'stock_type' => 'standard', 'date_of_birth' => now()->subMonths(5)->toDateString()]);
        app(CurrentOrganization::class)->bind($this->org);
        $this->course([$this->pig()]);

        $this->artisan('health:notify')->assertSuccessful();

        $this->assertSame(0, $theirManager->notifications()->count());
        $this->assertSame(1, $this->sent($this->manager, 'health.treatment_due'));
        $this->assertSame(0, DB::table('health_reminders')->where('organization_id', $other->id)->count());
        $this->assertNotNull($theirPig);
    }

    // --- worklist and overview ----------------------------------------------------------

    public function test_worklist_shows_due_overdue_and_follow_ups(): void
    {
        $this->travelTo(now()->setTime(12, 0));
        $pigs = [$this->pig(), $this->pig()];
        $this->course($pigs, ['start' => '2026-06-14 08:00', 'follow_up_on' => '2026-06-15', 'responsible_user_id' => $this->manager->id]);

        $this->actingAs($this->dataEntry)->get(route('health.worklist'))->assertOk()
            ->assertSee('Overdue from earlier days (2)')->assertSee('Give to 2')->assertSee('Follow-up')->assertSee($pigs[1]->code);
        $this->actingAs($this->dataEntry)->get(route('health.worklist', ['mine' => 1]))->assertOk()->assertSee('No doses scheduled for courses you are responsible for');
        $this->actingAs($this->manager)->get(route('health.worklist', ['mine' => 1]))->assertOk()->assertSee('Give to 2');

        $this->actingAs($this->dataEntry)->get(route('health.index'))->assertOk()
            ->assertSeeInOrder(['0', 'Open cases', '4', 'Doses due today or overdue', '1', 'Follow-ups due']);
    }

    // --- reports and exports --------------------------------------------------------------

    public function test_every_report_tab_renders(): void
    {
        $this->seedActivity();
        foreach (['cases', 'doses', 'usage', 'stock', 'visits'] as $tab) {
            $this->actingAs($this->dataEntry)->get(route('health.reports', ['tab' => $tab]))->assertOk();
        }
        $this->actingAs($this->dataEntry)->get(route('health.reports', ['tab' => 'usage']))->assertSee('Matches the ledger')->assertSee('12.00');
        $this->actingAs($this->dataEntry)->get(route('health.reports', ['tab' => 'stock']))->assertSee('Animals under withdrawal today (2)');
        $this->actingAs($this->dataEntry)->get(route('health.reports', ['tab' => 'cases', 'status' => 'active']))->assertSee('Lameness');
    }

    public function test_exports_stream_csv_and_need_the_export_permission(): void
    {
        $this->seedActivity();
        foreach (['cases', 'doses', 'usage', 'batches', 'withdrawal', 'visits'] as $tab) {
            $csv = $this->actingAs($this->dataEntry)->get(route('health.reports.export', ['tab' => $tab]))->assertOk()->streamedContent();
            $this->assertNotSame('', trim($csv), $tab);
        }
        $doses = $this->actingAs($this->dataEntry)->get(route('health.reports.export', ['tab' => 'doses']))->streamedContent();
        $this->assertStringContainsString('Medicine,Dose', $doses);
        $this->assertStringContainsString('Missed (not given)', $doses);
        $this->assertStringContainsString("'=SUM(A1)", $doses, 'Formula-looking text is neutralised');
        $this->actingAs($this->dataEntry)->get(route('medications.export', ['q' => 'Oxy']))->assertOk();
        $this->actingAs($this->dataEntry)->get(route('health.export'))->assertOk();

        $viewer = $this->user('viewer', null, ['view_health_records']);
        $this->actingAs($viewer)->get(route('health.reports'))->assertOk()->assertDontSee('Download CSV');
        $this->actingAs($viewer)->get(route('health.reports.export', ['tab' => 'cases']))->assertForbidden();
        $this->actingAs($viewer)->get(route('health.export'))->assertForbidden();
        $this->actingAs($viewer)->get(route('health.export-pdf', PigManagement::first()))->assertForbidden();
    }

    public function test_report_ranges_are_capped(): void
    {
        $this->actingAs($this->dataEntry)->get(route('health.reports', ['tab' => 'cases', 'from' => '2020-01-01', 'to' => '2026-06-15']))
            ->assertOk()->assertSee('Reports cover at most a year');
        $this->actingAs($this->dataEntry)->get(route('health.reports', ['days' => 100000]))->assertOk()->assertSee('14 Jun 2025');
    }

    public function test_exports_only_contain_this_farms_records(): void
    {
        $this->seedActivity();
        $other = Organization::factory()->create();
        app(CurrentOrganization::class)->bind($other);
        $theirPig = PigManagement::create(['organization_id' => $other->id, 'ear_tag_number' => '999', 'code' => 'THEIRS-1', 'breed' => 'LW',
            'gender' => 'F', 'stock_type' => 'standard', 'date_of_birth' => now()->subMonths(5)->toDateString()]);
        HealthRecord::create(['organization_id' => $other->id, 'pig_id' => $theirPig->id, 'record_date' => '2026-06-14', 'observed_at' => '2026-06-14 09:00',
            'condition' => 'Their cough', 'case_status' => 'open']);
        app(CurrentOrganization::class)->bind($this->org);

        $csv = $this->actingAs($this->dataEntry)->get(route('health.reports.export', ['tab' => 'cases']))->streamedContent();
        $this->assertStringNotContainsString('Their cough', $csv);
        $this->assertStringNotContainsString('THEIRS-1', $csv);
    }

    // --- permissions ----------------------------------------------------------------------

    public function test_correcting_and_voiding_need_correct_health_records(): void
    {
        $this->seedActivity();
        $case = HealthRecord::firstOrFail();
        $event = \App\Models\MedicationDosingEvent::firstOrFail();
        $this->assertFalse($this->dataEntry->hasPermission('correct_health_records'));
        $this->assertTrue($this->manager->hasPermission('correct_health_records'));

        $this->actingAs($this->dataEntry)->post(route('health.records.void', $case), ['void_reason' => 'Entered twice'])->assertForbidden();
        $this->actingAs($this->dataEntry)->get(route('doses.edit', $event))->assertForbidden();
        $this->actingAs($this->manager)->post(route('doses.void', $event), ['void_reason' => 'Never given'])->assertRedirect();
        $this->actingAs($this->manager)->post(route('health.records.void', $case), ['void_reason' => 'Entered twice'])->assertRedirect();
        $this->assertNotNull($case->fresh()->voided_at);
    }

    // --- helpers ------------------------------------------------------------------------

    /** A case, two doses (one with a formula-like note), a missed dose, a vet visit. */
    private function seedActivity(): void
    {
        $pigs = [$this->pig(), $this->pig()];
        $this->actingAs($this->dataEntry)->post(route('health.store'), ['pig_ids' => [$pigs[0]->id], 'observed_at' => now()->subDay()->format('Y-m-d H:i'), 'condition' => 'Lameness']);
        $medicine = Medicine::factory()->create(['name' => 'Oxytetracycline']);
        $this->receive($medicine, 'L1', '100', '2027-01-01', '2.00');
        $this->dose($medicine, $pigs, now()->subHours(2), ['dose_amount' => '3', 'notes' => '=SUM(A1)', 'purpose' => '=SUM(A1)']);
        $course = $this->course([$pigs[0]], ['start' => '2026-06-14 08:00']);
        $this->actingAs($this->dataEntry)->post(route('scheduled-doses.close', ScheduledDose::where('treatment_course_medicine_id', $course->medicines()->value('id'))->first()),
            ['status' => 'missed', 'reason' => 'Pig off feed']);
        $this->actingAs($this->dataEntry)->post(route('vet-visits.store'), ['visited_at' => now()->subHour()->format('Y-m-d H:i'), 'veterinarian_name' => 'Dr Asante']);
        $this->actingAs($this->dataEntry)->post(route('vet-visits.examinations.store', \App\Models\VetVisit::first()), ['pig_id' => $pigs[1]->id, 'assessment' => 'no_issue_found']);
    }

    private function course(array $pigs, array $options = []): TreatmentCourse
    {
        $medicine = Medicine::factory()->create(['allowed_routes' => []]);
        $this->actingAs($this->manager)->post(route('treatment-courses.store'), array_filter([
            'purpose' => 'treatment', 'pig_ids' => array_map(fn ($p) => $p->id, $pigs),
            'responsible_user_id' => $options['responsible_user_id'] ?? null, 'follow_up_on' => $options['follow_up_on'] ?? null,
            'medicines' => [['medicine_id' => $medicine->id, 'dose_amount' => '2', 'dose_unit' => 'ml', 'frequency' => 'daily', 'number_of_doses' => 2,
                'start_at' => $options['start'] ?? '2026-06-15 08:00']],
        ]))->assertSessionHasNoErrors();

        return TreatmentCourse::latest('id')->firstOrFail();
    }

    private function receive(Medicine $medicine, string $number, string $quantity, string $expiry, string $cost = '1'): MedicineBatch
    {
        $this->actingAs($this->manager)->post(route('medicines.batches.store', $medicine), ['batch_number' => $number, 'received_at' => '2026-06-01',
            'expiry_date' => $expiry, 'quantity_received' => $quantity, 'unit_cost' => $cost])->assertSessionHasNoErrors();

        return MedicineBatch::where('batch_number', $number)->firstOrFail();
    }

    private function dose(Medicine $medicine, array $pigs, $at, array $extra = []): void
    {
        $this->actingAs($this->dataEntry)->post(route('medications.store'), $extra + [
            'pig_ids' => array_map(fn ($p) => $p->id, $pigs), 'medicine_id' => $medicine->id, 'administered_at' => $at->format('Y-m-d H:i'),
            'amount_basis' => 'per_animal', 'dose_amount' => '2', 'dose_unit' => $medicine->dose_unit, 'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors();
    }

    private function notify(): void
    {
        $this->artisan('health:notify')->assertSuccessful();
    }

    private function sent(User $user, string $type): int
    {
        return $user->notifications()->where('data->type', $type)->count();
    }

    private function latest(User $user, ?string $type = null)
    {
        return $user->notifications()->when($type, fn ($q) => $q->where('data->type', $type))->latest()->firstOrFail();
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

        return PigManagement::create([
            'organization_id' => $this->org->id, 'ear_tag_number' => (string) (500 + $n), 'code' => 'RR-'.$n,
            'breed' => 'LW', 'gender' => 'F', 'stock_type' => 'standard', 'date_of_birth' => now()->subMonths(5)->toDateString(),
        ]);
    }

    private function user(string $roleName, ?Organization $org = null, ?array $permissions = null): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);
        if ($permissions !== null) {
            $role->permissions()->sync(Permission::whereIn('name', $permissions)->pluck('id'));
        }

        return User::forceCreate([
            'name' => ucfirst($roleName).' '.$this->sequence, 'email' => $roleName.'-'.(++$this->sequence).'@reminders.test', 'password' => bcrypt('password'),
            'role' => $roleName, 'role_id' => $role->id, 'organization_id' => ($org ?? $this->org)->id, 'active_status' => true,
        ]);
    }
}
