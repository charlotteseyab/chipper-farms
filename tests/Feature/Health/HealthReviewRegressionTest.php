<?php

namespace Tests\Feature\Health;

use App\Models\Medicine;
use App\Models\Organization;
use App\Models\PigManagement;
use App\Models\Role;
use App\Models\ScheduledDose;
use App\Models\User;
use App\Services\Health\CorrectDoseService;
use App\Services\Health\HealthRecordService;
use App\Services\Health\HealthReminderService;
use App\Services\Health\HealthWorklistService;
use App\Services\Health\MedicineStockService;
use App\Services\Health\RecordDoseService;
use App\Services\Health\ScheduledDoseService;
use App\Services\Health\TreatmentCourseService;
use App\Support\CurrentOrganization;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Database\Seeders\FarmOperationsPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class HealthReviewRegressionTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 6, 15)->setTime(10, 0));
        $org = Organization::factory()->create();
        app(CurrentOrganization::class)->bind($org);
        $this->actor = User::factory()->create(['organization_id' => $org->id]);
        $this->seed(FarmOperationsPermissionsSeeder::class);
        $role = Role::where('name', 'farmmanager')->firstOrFail();
        $this->actor->forceFill(['role' => 'farmmanager', 'role_id' => $role->id, 'active_status' => true])->save();
        $this->actingAs($this->actor);
    }

    private function course($pig, $medicine, $extra = [])
    {
        return app(TreatmentCourseService::class)->prescribe($this->actor, ['purpose' => 'treatment'] + $extra, [$pig], [['medicine_id' => $medicine->id, 'dose_amount' => '1', 'dose_unit' => 'ml', 'frequency' => 'once', 'start_at' => now()->subHour()->toDateTimeString()]]);
    }

    private function stock($medicine)
    {
        return app(MedicineStockService::class)->receive($this->actor, $medicine, ['batch_number' => 'REVIEW', 'expiry_date' => '2027-01-01', 'received_at' => '2026-06-15', 'quantity_received' => '10']);
    }

    private function dose($medicine, $pig, $extra = [])
    {
        return app(RecordDoseService::class)->record($this->actor, $extra + ['medicine_id' => $medicine->id, 'dose_amount' => '1', 'dose_unit' => 'ml', 'administered_at' => now()->subHour()->toDateTimeString(), 'idempotency_key' => (string) Str::uuid()], [$pig]);
    }

    public function test_course_rejects_animal_outside_linked_case(): void
    {
        $a = PigManagement::factory()->create();
        $b = PigManagement::factory()->create();
        $m = Medicine::factory()->create();
        $case = app(HealthRecordService::class)->openCase($this->actor, [$a], ['observed_at' => now()->subDay(), 'condition' => 'Review']);
        $this->expectException(ValidationException::class);
        $this->course($b, $m, ['health_record_id' => $case->id]);
    }

    public function test_completed_course_keeps_future_follow_up(): void
    {
        $p = PigManagement::factory()->create();
        $m = Medicine::factory()->create();
        $c = $this->course($p, $m, ['follow_up_on' => '2026-06-16']);
        $s = $c->medicines->first()->scheduledDoses->first();
        app(ScheduledDoseService::class)->close($this->actor, $s, ScheduledDose::MISSED, 'Review probe');
        $this->assertSame('completed', $c->fresh()->status);
        $this->assertCount(1, app(HealthWorklistService::class)->followUpsDue(CarbonImmutable::parse('2026-06-16')));
        $this->assertTrue(app(HealthReminderService::class)->due($this->actor->organization_id, CarbonImmutable::parse('2026-06-16'))->contains('type', 'health.follow_up_due'));
    }

    public function test_backdated_dose_rejects_batch_received_later(): void
    {
        $p = PigManagement::factory()->create();
        $m = Medicine::factory()->create();
        $this->stock($m);
        $this->expectException(ValidationException::class);
        $this->dose($m, $p, ['administered_at' => '2026-06-10 09:00:00']);
    }

    public function test_wrong_medicine_correction_releases_original_schedule(): void
    {
        $p = PigManagement::factory()->create();
        $m = Medicine::factory()->create();
        $other = Medicine::factory()->create();
        $this->stock($m);
        $this->stock($other);
        $c = $this->course($p, $m);
        $s = $c->medicines->first()->scheduledDoses->first();
        $e = $this->dose($m, $p, ['scheduled_dose_ids' => [$s->id]]);
        $replacement = app(CorrectDoseService::class)->correct($this->actor, $e, ['medicine_id' => $other->id, 'dose_amount' => '1', 'dose_unit' => 'ml', 'administered_at' => now()->subHour()->toDateTimeString()], [$p], 'Wrong medicine selected');
        $this->assertSame($other->id, $replacement->medicine_id);
        $this->assertSame(ScheduledDose::SCHEDULED, $s->fresh()->status);
        $this->assertSame('active', $c->fresh()->status);
    }

    public function test_group_member_in_overview_summary_and_pdf(): void
    {
        $a = PigManagement::factory()->create();
        $b = PigManagement::factory()->create();
        $case = app(HealthRecordService::class)->openCase($this->actor, [$a, $b], ['observed_at' => now()->subDay(), 'condition' => 'Group review']);
        $this->assertTrue($case->animals()->where('pig_id', $b->id)->exists());
        $this->get(route('health.index'))->assertOk()->assertSee($b->code);
        $this->get(route('health.show', $b))->assertOk()->assertViewHas('lastRecord', fn ($record) => $record->id === $case->id);
        Pdf::shouldReceive('loadView')->once()->with('health.pdf', \Mockery::on(fn ($data) => $data['healthRecords']->contains('id', $case->id)))->andReturnSelf();
        Pdf::shouldReceive('setPaper')->andReturnSelf();
        Pdf::shouldReceive('download')->andReturn(response('pdf'));
        $this->get(route('health.export-pdf', $b))->assertOk();
    }

    public function test_partial_group_dose_keeps_removed_animal_due(): void
    {
        $a = PigManagement::factory()->create();
        $b = PigManagement::factory()->create();
        $m = Medicine::factory()->create();
        $this->stock($m);
        $c = app(TreatmentCourseService::class)->prescribe($this->actor, ['purpose' => 'treatment'], [$a, $b], [['medicine_id' => $m->id, 'dose_amount' => '1', 'dose_unit' => 'ml', 'frequency' => 'once', 'start_at' => now()->subHour()->toDateTimeString()]]);
        $ids = $c->medicines->first()->scheduledDoses->modelKeys();
        $e = $this->dose($m, $a, ['scheduled_dose_ids' => $ids]);
        $this->assertCount(1, $e->administrations);
        $this->assertSame(ScheduledDose::SCHEDULED, ScheduledDose::where('pig_id', $b->id)->firstOrFail()->status);
    }

    public function test_explicit_batch_cannot_bypass_receipt_date_and_saves_nothing(): void
    {
        $pig = PigManagement::factory()->create();
        $medicine = Medicine::factory()->create();
        $batch = $this->stock($medicine);
        try {
            $this->dose($medicine, $pig, ['batch_id' => $batch->id, 'administered_at' => '2026-06-10 09:00:00']);
            $this->fail('A batch received later must be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('batch_id', $e->errors());
        }
        $this->assertDatabaseCount('medication_dosing_events', 0);
        $this->assertSame(0, bccomp('10', app(MedicineStockService::class)->onHand($batch), 4));
    }

    public function test_backdated_dose_can_use_a_batch_received_that_day(): void
    {
        $pig = PigManagement::factory()->create();
        $medicine = Medicine::factory()->create();
        $batch = $this->stock($medicine);
        $batch->update(['received_at' => '2026-06-10']);
        $event = $this->dose($medicine, $pig, ['administered_at' => '2026-06-10 09:00:00']);
        $this->assertSame($batch->id, $event->allocations->first()->medicine_batch_id);
    }

    public function test_recorded_case_follow_up_clears_completed_course_follow_up(): void
    {
        $pig = PigManagement::factory()->create();
        $medicine = Medicine::factory()->create();
        $case = app(HealthRecordService::class)->openCase($this->actor, [$pig], ['observed_at' => now()->subDays(2), 'condition' => 'Review']);
        $course = $this->course($pig, $medicine, ['health_record_id' => $case->id, 'follow_up_on' => '2026-06-15']);
        app(ScheduledDoseService::class)->close($this->actor, $course->medicines->first()->scheduledDoses->first(), ScheduledDose::MISSED, 'Not administered');
        $this->assertCount(1, app(HealthWorklistService::class)->followUpsDue(CarbonImmutable::today()));
        app(HealthRecordService::class)->addFollowUp($this->actor, $case, ['observed_at' => now()->toDateTimeString(), 'findings' => 'Checked today']);
        $this->assertCount(0, app(HealthWorklistService::class)->followUpsDue(CarbonImmutable::today()));
        $this->assertFalse(app(HealthReminderService::class)->due($this->actor->organization_id, CarbonImmutable::now())->contains('type', 'health.follow_up_due'));
    }

    public function test_group_case_filters_and_latest_record_apply_to_every_member(): void
    {
        $lead = PigManagement::factory()->create();
        $member = PigManagement::factory()->create();
        app(HealthRecordService::class)->openCase($this->actor, [$member], ['observed_at' => now()->subDays(2), 'condition' => 'Older case']);
        $group = app(HealthRecordService::class)->openCase($this->actor, [$lead, $member], ['observed_at' => now()->subDay(), 'condition' => 'Group review', 'severity' => 'severe']);
        foreach ([['condition' => 'Group review'], ['attention' => 'open_critical']] as $filters) {
            $this->get(route('health.index', $filters))->assertOk()->assertViewHas('pigs', function ($pigs) use ($member, $group) {
                $row = $pigs->firstWhere('id', $member->id);

                return $row && $row->healthRecords->first()->id === $group->id;
            });
        }
    }
}
