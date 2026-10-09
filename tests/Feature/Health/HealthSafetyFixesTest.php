<?php

namespace Tests\Feature\Health;

use App\Models\ActivityLog;
use App\Models\HealthRecord;
use App\Models\MedicationAdministration;
use App\Models\Organization;
use App\Models\PigDeath;
use App\Models\PigManagement;
use App\Models\PigSale;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\Health\Exceptions\PigUnderWithdrawalException;
use App\Services\Health\WithdrawalStatusService;
use App\Services\Sales\RecordPigSaleService;
use App\Storefront\Services\PigListingEligibilityService;
use App\Support\CurrentOrganization;
use Database\Seeders\FarmOperationsPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Health and medication Phase 0: withdrawal on direct sales, void instead of
 * delete, correctable doses, record-id actions, policies, severity list, date
 * checks and history for sold or dead pigs.
 */
class HealthSafetyFixesTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $dataEntry;

    private User $manager;

    private PigManagement $pig;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 6, 15)->setTime(9, 0));

        $this->organization = Organization::create(['slug' => 'health-safety', 'name' => 'Health Safety', 'is_active' => true]);
        $this->app->make(CurrentOrganization::class)->bind($this->organization);
        $this->seed(FarmOperationsPermissionsSeeder::class);

        $this->dataEntry = $this->user('dataentry');
        $this->manager = $this->user('farmmanager');
        $this->pig = $this->pig();
    }

    // --- withdrawal on direct sales --------------------------------------

    public function test_direct_sale_is_refused_during_withdrawal_and_allowed_the_day_after(): void
    {
        // 28 days from 1 June: restricted through 29 June, saleable from 30 June (D7).
        $this->dose($this->pig, '2026-06-01', 28);

        $this->travelTo(now()->setDate(2026, 6, 29));
        $this->assertTrue(app(WithdrawalStatusService::class)->forPig($this->pig)['restricted']);
        try {
            $this->sell($this->pig);
            $this->fail('A pig under withdrawal must not be sold.');
        } catch (PigUnderWithdrawalException $e) {
            $this->assertStringContainsString('until 29 Jun 2026', $e->getMessage());
            $this->assertStringContainsString('sold from 30 Jun 2026', $e->getMessage());
        }
        $this->assertSame(0, PigSale::count());

        $this->travelTo(now()->setDate(2026, 6, 30));
        $this->sell($this->pig);
        $this->assertSame(1, PigSale::count());
    }

    public function test_sale_form_warns_and_refuses_on_submit(): void
    {
        $admin = $this->user('admin');
        $this->dose($this->pig, now()->toDateString(), 10);

        $this->actingAs($admin)->get(route('pigs.sale-form', $this->pig->id))
            ->assertOk()
            ->assertSee('This pig cannot be sold yet');

        $this->actingAs($admin)->from(route('pigs.sale-form', $this->pig->id))
            ->post(route('pigs.record-sale', $this->pig->id), [
                'sale_date' => now()->toDateString(), 'sale_weight' => 90, 'weight_type' => 'live',
                'sale_purpose' => 'market', 'payment_status' => 'pending',
            ])
            ->assertSessionHasErrors('sale');
        $this->assertSame(0, PigSale::count());
    }

    public function test_voided_dose_no_longer_restricts_and_storefront_uses_the_same_rule(): void
    {
        $dose = $this->dose($this->pig, now()->toDateString(), 14);
        $this->assertNotEmpty($this->storefrontBlockers());

        $this->actingAs($this->manager)
            ->post(route('medications.void', $dose), ['void_reason' => 'Dose was never given'])
            ->assertRedirect(route('medications.show', $dose));

        $this->assertFalse(app(WithdrawalStatusService::class)->forPig($this->pig)['restricted']);
        $this->assertSame([], $this->storefrontBlockers());
        $this->assertTrue(ActivityLog::where('action', 'medication_voided')->where('auditable_id', $dose->id)->exists());
    }

    // --- corrections and voids ----------------------------------------

    public function test_correcting_a_dose_keeps_the_original_and_recalculates_withdrawal(): void
    {
        $original = $this->dose($this->pig, now()->subDays(10)->toDateString(), 7);
        $this->assertFalse(app(WithdrawalStatusService::class)->forPig($this->pig)['restricted']);

        $response = $this->actingAs($this->manager)->put(route('medications.update', $original), [
            'administration_date' => $original->administration_date->toDateString(),
            'medication_name' => 'Oxytetracycline',
            'dosage' => '5 ml',
            'withdrawal_period_days' => 28,
            'correction_reason' => 'Label says 28 days, not 7',
        ]);

        $replacement = MedicationAdministration::where('corrects_id', $original->id)->firstOrFail();
        $response->assertRedirect(route('medications.show', $replacement));

        $original->refresh();
        $this->assertTrue($original->isVoided());
        $this->assertSame('Corrected: Label says 28 days, not 7', $original->void_reason);
        $this->assertSame(28, $replacement->withdrawal_period_days);
        $this->assertSame($original->administered_by, $replacement->administered_by);

        $status = app(WithdrawalStatusService::class)->forPig($this->pig);
        $this->assertTrue($status['restricted']);
        $this->assertSame($replacement->id, $status['doses']->first()->id);

        $log = ActivityLog::where('action', 'medication_corrected')->firstOrFail();
        $this->assertEquals(7, $log->old_values['withdrawal_period_days']);
        $this->assertEquals(28, $log->new_values['withdrawal_period_days']);

        $this->actingAs($this->manager)->get(route('medications.edit', $original))->assertForbidden();
    }

    public function test_dataentry_cannot_correct_or_void_doses(): void
    {
        $dose = $this->dose($this->pig, now()->toDateString(), 5);

        $this->actingAs($this->dataEntry)->post(route('medications.void', $dose), ['void_reason' => 'Entered by mistake'])->assertForbidden();
        $this->actingAs($this->dataEntry)->put(route('medications.update', $dose), [])->assertForbidden();
        $this->assertFalse($dose->fresh()->isVoided());
    }

    public function test_voiding_a_health_record_keeps_it_marked_and_audited(): void
    {
        $record = $this->record($this->pig, now()->subDays(3)->toDateString());

        $this->actingAs($this->manager)
            ->post(route('health.records.void', $record), ['void_reason' => 'Recorded on the wrong pig'])
            ->assertRedirect(route('health.show', $this->pig));

        $record->refresh();
        $this->assertTrue($record->isVoided());
        $this->assertSame($this->manager->id, (int) $record->voided_by);
        $this->assertTrue(ActivityLog::where('action', 'health_record_voided')->where('auditable_id', $record->id)->exists());

        $this->actingAs($this->manager)->get(route('health.show', $this->pig))
            ->assertOk()->assertSee('Voided')->assertSee('Recorded on the wrong pig');
        $this->actingAs($this->manager)->get(route('health.records.edit', $record))->assertForbidden();
    }

    public function test_void_needs_a_reason(): void
    {
        $record = $this->record($this->pig, now()->toDateString());

        $this->actingAs($this->manager)->post(route('health.records.void', $record), ['void_reason' => ''])
            ->assertSessionHasErrors('void_reason');
        $this->assertFalse($record->fresh()->isVoided());
    }

    // --- record-id actions and organisation scope ---------------------

    public function test_editing_an_older_record_changes_only_that_record_and_is_audited(): void
    {
        $older = $this->record($this->pig, now()->subDays(5)->toDateString(), 'Cough');
        $latest = $this->record($this->pig, now()->subDay()->toDateString(), 'Lameness');

        $this->actingAs($this->dataEntry)->put(route('health.records.update', $older), [
            'condition' => 'Cough', 'severity' => 'severe', 'treatment_status' => 'completed',
        ])->assertRedirect(route('health.show', $this->pig));

        $this->assertSame('severe', $older->fresh()->severity);
        $this->assertSame('mild', $latest->fresh()->severity);
        $log = ActivityLog::where('action', 'health_record_amended')->where('auditable_id', $older->id)->firstOrFail();
        $this->assertSame(['severity' => 'mild', 'treatment_status' => 'ongoing'], $log->old_values);
    }

    public function test_another_farms_records_return_404(): void
    {
        $other = Organization::create(['slug' => 'other-farm', 'name' => 'Other farm', 'is_active' => true]);
        $otherPig = PigManagement::withoutGlobalScopes()->create([
            'organization_id' => $other->id, 'ear_tag_number' => 'X-1', 'code' => 'OTHER-1', 'breed' => 'Duroc',
            'gender' => 'F', 'stock_type' => 'standard', 'date_of_birth' => now()->subMonths(6)->toDateString(),
        ]);
        $record = HealthRecord::withoutGlobalScopes()->create(['pig_id' => $otherPig->id, 'record_date' => now()->toDateString(), 'condition' => 'X']);
        $dose = MedicationAdministration::withoutGlobalScopes()->create(['pig_id' => $otherPig->id, 'administration_date' => now()->toDateString(), 'medication_name' => 'X', 'dosage' => '1 ml']);

        $this->actingAs($this->manager)->get(route('health.show', $otherPig->id))->assertNotFound();
        $this->actingAs($this->manager)->get(route('health.records.edit', $record->id))->assertNotFound();
        $this->actingAs($this->manager)->post(route('health.records.void', $record->id), ['void_reason' => 'Not ours at all'])->assertNotFound();
        $this->actingAs($this->manager)->post(route('medications.void', $dose->id), ['void_reason' => 'Not ours at all'])->assertNotFound();
        $this->assertNull($record->fresh()->voided_at);
    }

    // --- sold and dead pigs ---------------------------------------------

    public function test_history_of_sold_and_dead_pigs_stays_reachable(): void
    {
        $this->record($this->pig, now()->subDays(20)->toDateString());
        $this->sell($this->pig);

        $dead = $this->pig();
        $this->record($dead, now()->subDays(10)->toDateString());
        PigDeath::create(['pig_id' => $dead->id, 'death_date' => now()->subDays(2)->toDateString(), 'death_cause' => 'unknown',
            'recorded_by' => $this->manager->id, 'organization_id' => $this->organization->id]);
        $dead->delete();

        $this->actingAs($this->dataEntry)->get(route('health.show', $this->pig))->assertOk()->assertSee('has left the farm');
        $this->actingAs($this->dataEntry)->get(route('health.show', $dead))->assertOk();
        $this->actingAs($this->dataEntry)->get(route('health.export-pdf', $dead))->assertOk();

        $this->actingAs($this->dataEntry)->get(route('health.index'))->assertDontSee($this->pig->code);
        $this->actingAs($this->dataEntry)->get(route('health.index', ['include_departed' => 1]))
            ->assertSee($this->pig->code)->assertSee($dead->code);
    }

    public function test_only_managers_with_a_reason_can_add_earlier_records_for_a_sold_pig(): void
    {
        // Registered (and tagged) a month ago, so a sale five days ago is valid.
        $this->travel(-30)->days();
        $this->pig = $this->pig();
        $this->travelBack();
        $this->travelTo(now()->setDate(2026, 6, 15)->setTime(9, 0));
        $this->sell($this->pig, now()->subDays(5)->toDateString());
        $before = now()->subDays(8)->toDateString();
        $payload = ['record_date' => $before, 'condition' => 'Found in paper notes'];

        $this->actingAs($this->dataEntry)->from('/')->post(route('health.storeForPig', $this->pig), $payload)
            ->assertSessionHasErrors('record_date');
        $this->flushSession(); // a new user in the same test needs a fresh session
        $this->actingAs($this->manager)->from('/')->post(route('health.storeForPig', $this->pig), $payload)
            ->assertSessionHasErrors('late_entry_reason');
        $this->actingAs($this->manager)->from('/')->post(route('health.storeForPig', $this->pig), ['record_date' => now()->subDays(2)->toDateString(), 'late_entry_reason' => 'Copied from vet notes'] + $payload)
            ->assertSessionHasErrors('record_date');

        $this->actingAs($this->manager)->post(route('health.storeForPig', $this->pig), $payload + ['late_entry_reason' => 'Copied from vet notes'])
            ->assertRedirect(route('health.show', $this->pig));
        $this->assertSame(1, HealthRecord::where('pig_id', $this->pig->id)->where('condition', 'Found in paper notes')->count());
        $this->assertTrue(ActivityLog::where('action', 'health_record_created')->where('description', 'like', '%Copied from vet notes%')->exists());
    }

    // --- dates and severity ----------------------------------------------

    public function test_entries_cannot_be_dated_in_the_future_or_before_birth(): void
    {
        $this->actingAs($this->dataEntry)->from('/')->post(route('health.storeForPig', $this->pig), [
            'record_date' => now()->addDay()->toDateString(), 'condition' => 'X',
        ])->assertSessionHasErrors('record_date');

        // Doses now use the catalogue form (Phase 3); the same date rule applies.
        $iron = \App\Models\Medicine::factory()->create(['organization_id' => $this->pig->organization_id, 'name' => 'Iron']);
        $this->actingAs($this->dataEntry)->from('/')->post(route('medications.storeForPig', $this->pig), [
            'administered_at' => $this->pig->date_of_birth->copy()->subDay()->format('Y-m-d 08:00'), 'medicine_id' => $iron->id,
            'amount_basis' => 'per_animal', 'dose_amount' => '1', 'dose_unit' => 'ml', 'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
        ])->assertSessionHasErrors('administered_at');

        $this->assertSame(0, HealthRecord::count() + MedicationAdministration::count());
    }

    public function test_severity_is_a_fixed_list(): void
    {
        $this->actingAs($this->dataEntry)->from('/')->post(route('health.storeForPig', $this->pig), [
            'record_date' => now()->toDateString(), 'condition' => 'Cough', 'severity' => 'high',
        ])->assertSessionHasErrors('severity');

        $this->actingAs($this->dataEntry)->post(route('health.storeForPig', $this->pig), [
            'record_date' => now()->toDateString(), 'condition' => 'Cough', 'severity' => 'severe',
        ])->assertRedirect(route('health.show', $this->pig));
        $this->assertSame('severe', HealthRecord::firstOrFail()->severity);
    }

    // --- helpers -------------------------------------------------------

    private function user(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);

        return User::forceCreate([
            'name' => ucfirst($roleName).' '.(++$this->sequence),
            'email' => "{$roleName}-{$this->sequence}@health-safety.test",
            'password' => bcrypt('password'),
            'role' => $roleName,
            'role_id' => $role->id,
            'organization_id' => $this->organization->id,
            'active_status' => true,
        ]);
    }

    private function pig(): PigManagement
    {
        $n = ++$this->sequence;

        return PigManagement::create([
            'organization_id' => $this->organization->id,
            'ear_tag_number' => "HS-{$n}",
            'code' => "PIG-HS-{$n}",
            'breed' => 'Large White',
            'gender' => 'F',
            'stock_type' => 'standard',
            'date_of_birth' => now()->subMonths(7)->toDateString(),
        ]);
    }

    private function dose(PigManagement $pig, string $date, ?int $withdrawalDays): MedicationAdministration
    {
        return MedicationAdministration::create([
            'pig_id' => $pig->id, 'administration_date' => $date, 'medication_name' => 'Oxytetracycline',
            'dosage' => '5 ml', 'withdrawal_period_days' => $withdrawalDays, 'administered_by' => $this->dataEntry->id,
        ]);
    }

    private function record(PigManagement $pig, string $date, string $condition = 'Cough'): HealthRecord
    {
        return HealthRecord::create([
            'pig_id' => $pig->id, 'record_date' => $date, 'condition' => $condition,
            'severity' => 'mild', 'treatment_status' => 'ongoing', 'recorded_by' => $this->dataEntry->id,
        ]);
    }

    private function sell(PigManagement $pig, ?string $date = null): PigSale
    {
        Setting::set('sales.pricing.live_weight_price', 30);

        return app(RecordPigSaleService::class)->execute($pig->fresh() ?? $pig, $this->user('admin'), [
            'sale_date' => $date ?? now()->toDateString(), 'sale_weight' => 90, 'weight_type' => 'live',
            'sale_purpose' => 'market', 'payment_status' => 'pending',
        ]);
    }

    private function storefrontBlockers(): array
    {
        return array_values(array_filter(
            app(PigListingEligibilityService::class)->check($this->pig->fresh(), null, $this->organization->id)['blockers'] ?? [],
            fn (string $b) => str_contains($b, 'withdrawal'),
        ));
    }
}
