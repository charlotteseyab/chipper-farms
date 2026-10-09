<?php

namespace Tests\Feature\Stock;

use App\Models\ActivityLog;
use App\Models\DomainEvent;
use App\Models\EarTag;
use App\Models\EarTagAssignment;
use App\Models\EarTagColor;
use App\Models\MedicationAdministration;
use App\Models\Organization;
use App\Models\PigExit;
use App\Models\PigManagement;
use App\Models\Role;
use App\Models\User;
use App\Services\Reports\SystemReportService;
use App\Services\Stock\Tags\EarTagAllocator;
use App\Services\Stock\Tags\EarTagAssignmentService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Database\Seeders\FarmOperationsPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Milestone 1: transfer out and cull release the tag and keep the animal's history. */
class PigExitTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $manager;

    private EarTagColor $green;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::create(['name' => 'Exit Farm', 'slug' => 'exit-farm', 'is_active' => true]);
        $this->app->make(CurrentOrganization::class)->bind($this->organization);
        $this->seed(FarmOperationsPermissionsSeeder::class);
        $this->manager = $this->user('farmmanager');
        $this->green = EarTagColor::create(['organization_id' => $this->organization->id, 'name' => 'Green', 'normalized_name' => 'green', 'hex_color' => '#15803D']);
    }

    public function test_transfer_out_removes_the_pig_releases_its_tag_and_keeps_history(): void
    {
        [$pig, $assignment] = $this->taggedPig('7');

        $this->actingAs($this->manager)->post(route('pig-exits.store', $pig), $this->transfer())
            ->assertRedirect(route('pig-exits.index'));

        $exit = PigExit::firstOrFail();
        $this->assertSame(PigExit::TRANSFER_OUT, $exit->exit_type);
        $this->assertSame('Kintampo farm', $exit->destination);
        $this->assertTrue($pig->fresh()->trashed());

        $assignment->refresh();
        $this->assertNull($assignment->is_current);
        $this->assertSame('transfer_out', $assignment->release_reason);
        $this->assertSame(EarTag::RETIRED, $assignment->tag->fresh()->status);
        $this->assertSame($pig->id, $assignment->pig_id, 'The tag history still points at this pig.');

        $this->assertTrue(ActivityLog::where('action', 'pig_transferred_out')->where('auditable_id', $exit->id)->exists());
        $this->assertTrue(DomainEvent::where('event_type', 'pig.exited')->where('source_id', $exit->id)->exists());
        $this->actingAs($this->manager)->get(route('pig-exits.index'))->assertOk()->assertSee($pig->code)->assertSee('Kintampo farm');
    }

    public function test_a_tag_removed_at_cull_is_offered_again_before_a_new_number(): void
    {
        [$pig, $assignment] = $this->taggedPig('3');
        $this->taggedPig('4');
        $colour = $assignment->tag->color;

        $this->actingAs($this->manager)->post(route('pig-exits.store', $pig), ['tag_outcome' => EarTag::AVAILABLE] + $this->cull('destroyed'))
            ->assertRedirect(route('pig-exits.index'));

        $this->assertSame('3', app(EarTagAllocator::class)->propose($this->organization->id, $colour)[0]['tag_number']);
    }

    public function test_a_cull_for_food_is_refused_during_withdrawal_but_disposal_is_not(): void
    {
        [$pig] = $this->taggedPig('9');
        MedicationAdministration::create(['pig_id' => $pig->id, 'administration_date' => now()->toDateString(),
            'medication_name' => 'Oxytetracycline', 'dosage' => '5 ml', 'withdrawal_period_days' => 28]);

        $this->actingAs($this->manager)->from(route('pig-exits.create', $pig))->post(route('pig-exits.store', $pig), $this->cull('slaughtered_on_farm'))
            ->assertSessionHasErrors('exit');
        $this->assertFalse($pig->fresh()->trashed());

        $this->actingAs($this->manager)->post(route('pig-exits.store', $pig), $this->cull('destroyed'))
            ->assertRedirect(route('pig-exits.index'));
        $this->assertTrue($pig->fresh()->trashed());
    }

    public function test_a_pig_can_only_leave_once(): void
    {
        [$pig] = $this->taggedPig('2');
        $this->actingAs($this->manager)->post(route('pig-exits.store', $pig), $this->transfer());

        // The pig is no longer an active animal, so its exit form is not found.
        $this->actingAs($this->manager)->post(route('pig-exits.store', $pig->id), $this->transfer())->assertNotFound();
        $this->assertSame(1, PigExit::count());
    }

    public function test_only_managers_can_remove_animals(): void
    {
        [$pig] = $this->taggedPig('5');

        $this->actingAs($this->user('dataentry'))->post(route('pig-exits.store', $pig), $this->transfer())->assertForbidden();
        $this->assertFalse($pig->fresh()->trashed());
    }

    public function test_required_details_are_checked(): void
    {
        [$pig] = $this->taggedPig('6');

        $this->actingAs($this->manager)->from(route('pig-exits.create', $pig))
            ->post(route('pig-exits.store', $pig), ['destination' => ''] + $this->transfer())
            ->assertSessionHasErrors('destination');
        $this->actingAs($this->manager)->from(route('pig-exits.create', $pig))
            ->post(route('pig-exits.store', $pig), ['reason' => 'poor_growth'] + $this->transfer())
            ->assertSessionHasErrors('reason');
        $this->actingAs($this->manager)->from(route('pig-exits.create', $pig))
            ->post(route('pig-exits.store', $pig), ['disposal' => ''] + $this->cull('destroyed'))
            ->assertSessionHasErrors('disposal');
    }

    public function test_herd_movement_still_balances_after_an_exit(): void
    {
        [$pig] = $this->taggedPig('8');
        $this->actingAs($this->manager)->post(route('pig-exits.store', $pig), $this->transfer());

        $report = app(SystemReportService::class)->build($this->organization->id, CarbonImmutable::today()->subDays(30), CarbonImmutable::today());
        $this->assertSame(1, $report['herdMovement']['transferred_or_culled']);
        $this->assertSame(0, $report['herdMovement']['variance']);
    }

    public function test_health_records_cannot_be_dated_after_a_transfer(): void
    {
        $this->travel(-10)->days();
        [$pig] = $this->taggedPig('1');
        $this->travelBack();
        $this->actingAs($this->manager)->post(route('pig-exits.store', $pig), ['exit_date' => now()->subDays(3)->toDateString()] + $this->transfer())
            ->assertRedirect(route('pig-exits.index'));

        $this->actingAs($this->manager)->from('/')->post(route('health.storeForPig', $pig->id), [
            'record_date' => now()->toDateString(), 'condition' => 'Cough', 'late_entry_reason' => 'Late paperwork',
        ])->assertSessionHasErrors('record_date');
    }

    private function transfer(): array
    {
        return ['exit_type' => 'transfer_out', 'exit_date' => now()->toDateString(), 'reason' => 'another_farm',
            'destination' => 'Kintampo farm', 'tag_outcome' => EarTag::RETIRED];
    }

    private function cull(string $disposal): array
    {
        return ['exit_type' => 'cull', 'exit_date' => now()->toDateString(), 'reason' => 'lameness',
            'disposal' => $disposal, 'tag_outcome' => EarTag::RETIRED];
    }

    /** @return array{0: PigManagement, 1: EarTagAssignment} */
    private function taggedPig(string $number): array
    {
        $pig = PigManagement::create([
            'organization_id' => $this->organization->id, 'ear_tag_number' => $number, 'code' => 'EXIT-'.$number,
            'breed' => 'LW', 'gender' => 'F', 'stock_type' => 'standard', 'date_of_birth' => now()->subMonths(5)->toDateString(),
        ]);
        $assignment = $pig->currentEarTagAssignment()->first()
            ?? app(EarTagAssignmentService::class)->assign($pig, $this->green, $number, $this->manager);

        return [$pig, $assignment];
    }

    private function user(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);

        return User::forceCreate([
            'name' => ucfirst($roleName), 'email' => $roleName.'-'.(++$this->sequence).'@exit-farm.test',
            'password' => bcrypt('password'), 'role' => $roleName, 'role_id' => $role->id,
            'organization_id' => $this->organization->id, 'active_status' => true,
        ]);
    }
}
