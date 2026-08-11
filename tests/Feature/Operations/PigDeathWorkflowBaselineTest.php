<?php

namespace Tests\Feature\Operations;

use App\Models\DomainEvent;
use App\Models\Organization;
use App\Models\PigDeath;
use App\Models\PigManagement;
use App\Models\Role;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 0.6 — regression baseline for recording a pig death.
 *
 * Asserts the observable outcomes of PigManagementController::recordDeath:
 *   - pig_deaths row is created with correct fields
 *   - pig is soft-deleted from active stock
 *   - "other" death cause / disposal method keep the enum and store the free
 *     text in their own column, surfaced by the model accessors
 *   - already-deceased pig cannot be recorded dead again
 *   - PigDeathRecorded event emitted (requires SOURCE_INTEGRATION_PATCHES.md
 *     Patch 7 applied)
 *
 * NOTE: the existing recordDeath method has no DB::transaction wrapping
 * around the death save + pig soft-delete (a latent bug). Batch B patches
 * this with a transaction as part of adding event dispatch. Phase 0.5
 * will further extract the method into a RecordPigDeathService.
 */
class PigDeathWorkflowBaselineTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;
    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create(['slug' => 'chipper-farms', 'name' => 'Chipper Farms']);

        $role = Role::firstOrCreate(['name' => 'farmmanager']);
        $this->manager = User::forceCreate([
            'name' => 'Farm Manager',
            'email' => 'fm@example.com',
            'password' => bcrypt('x'),
            'role' => 'farmmanager',
            'role_id' => $role->id,
            'organization_id' => $this->org->id,
        ]);

        $this->app->make(CurrentOrganization::class)->bind($this->org);
    }

    protected function makePig(): PigManagement
    {
        return PigManagement::create([
            'ear_tag_number' => 'TEST-' . uniqid(),
            'breed' => 'Large White',
            'gender' => 'male',
            'stock_type' => 'standard',
            'date_of_birth' => now()->subMonths(4)->toDateString(),
            'code' => 'PIG-' . uniqid(),
        ]);
    }

    public function test_recording_death_creates_record_and_soft_deletes_pig(): void
    {
        $pig = $this->makePig();

        $this->actingAs($this->manager)
            ->post(route('pigs.record-death', $pig->id), [
                'death_date' => now()->toDateString(),
                'death_cause' => 'disease',
                'final_weight' => 42.5,
                'death_location' => 'Pen 3',
                'disposal_method' => 'burial',
            ])
            ->assertRedirect();

        $death = PigDeath::where('pig_id', $pig->id)->first();

        $this->assertNotNull($death);
        $this->assertSame('disease', $death->death_cause);
        $this->assertSame(42.5, (float) $death->final_weight);
        $this->assertSame('burial', $death->disposal_method);
        $this->assertSame($this->manager->id, $death->recorded_by);

        $this->assertSoftDeleted('pigs_management', ['id' => $pig->id]);
    }

    public function test_other_cause_is_stored_beside_the_enum_and_read_back(): void
    {
        $pig = $this->makePig();

        $this->actingAs($this->manager)
            ->post(route('pigs.record-death', $pig->id), [
                'death_date' => now()->toDateString(),
                'death_cause' => 'other',
                'death_cause_other' => 'heat stroke during transport',
                'disposal_method' => 'disposal',
            ])
            ->assertRedirect();

        $death = PigDeath::where('pig_id', $pig->id)->first();
        $this->assertSame('heat stroke during transport', $death->death_cause);
    }

    public function test_other_disposal_method_is_stored_beside_the_enum_and_read_back(): void
    {
        $pig = $this->makePig();

        $this->actingAs($this->manager)
            ->post(route('pigs.record-death', $pig->id), [
                'death_date' => now()->toDateString(),
                'death_cause' => 'accident',
                'disposal_method' => 'other',
                'disposal_method_other' => 'research donation',
            ])
            ->assertRedirect();

        $death = PigDeath::where('pig_id', $pig->id)->first();
        $this->assertSame('research donation', $death->disposal_method);
    }

    public function test_already_deceased_pig_cannot_be_recorded_dead_again(): void
    {
        $pig = $this->makePig();

        // First death.
        $this->actingAs($this->manager)
            ->post(route('pigs.record-death', $pig->id), [
                'death_date' => now()->toDateString(),
                'death_cause' => 'natural',
                'disposal_method' => 'burial',
            ])
            ->assertRedirect();

        // Second attempt — pig is soft-deleted; route-model binding still
        // resolves it because the controller uses findOrFail on withTrashed?
        // Actually the recordDeath uses PigManagement::findOrFail which does
        // NOT include trashed. So the second call 404s. Both outcomes (404
        // OR a "already deceased" redirect) indicate the system rejects
        // the second death. Accept either.
        $response = $this->actingAs($this->manager)
            ->post(route('pigs.record-death', $pig->id), [
                'death_date' => now()->toDateString(),
                'death_cause' => 'natural',
                'disposal_method' => 'burial',
            ]);

        $this->assertTrue(
            in_array($response->status(), [302, 404], true),
            'Expected rejection (redirect with error or 404), got ' . $response->status()
        );

        // Only one death record exists.
        $this->assertSame(1, PigDeath::where('pig_id', $pig->id)->count());
    }

    public function test_death_event_is_emitted_with_correct_payload(): void
    {
        $pig = $this->makePig();

        $this->actingAs($this->manager)
            ->post(route('pigs.record-death', $pig->id), [
                'death_date' => now()->toDateString(),
                'death_cause' => 'disease',
                'final_weight' => 42.5,
                'disposal_method' => 'burial',
            ])
            ->assertRedirect();

        $event = DomainEvent::where('event_type', 'pig_death.recorded')->latest()->first();

        $this->assertNotNull($event);
        $this->assertSame($pig->id, $event->payload['pig_id']);
        $this->assertSame('disease', $event->payload['death_cause']);
        $this->assertSame(42.5, (float) $event->payload['final_weight']);
        $this->assertSame('burial', $event->payload['disposal_method']);
        $this->assertNotNull($event->payload['age_at_death_days']);
    }
}