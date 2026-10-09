<?php

namespace Tests\Feature\Health;

use App\Models\ActivityLog;
use App\Models\Finance\InventoryBalance;
use App\Models\Finance\InventoryMovement;
use App\Models\MedicationAdministration;
use App\Models\MedicationDosingEvent;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\Organization;
use App\Models\PigManagement;
use App\Models\Role;
use App\Models\ScheduledDose;
use App\Models\TreatmentCourse;
use App\Models\User;
use App\Services\Finance\Inventory\InventoryLedgerReader;
use App\Services\Finance\Inventory\InventoryService;
use App\Services\Health\WithdrawalStatusService;
use App\Support\CurrentOrganization;
use Database\Seeders\FarmOperationsPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Health upgrade Phase 3: medicine batches and stock, recording doses, missed doses, corrections, withdrawal. */
class MedicineStockAndDosesTest extends TestCase
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

    // --- batches and stock ----------------------------------------------------

    public function test_receiving_a_batch_puts_it_in_the_ledger_without_a_gl_posting(): void
    {
        $medicine = Medicine::factory()->create();
        $this->receive($medicine, 'L100', '100', '2026-12-31', ['unit_cost' => '1.50']);

        $batch = MedicineBatch::firstOrFail();
        $this->assertSame('100.0000', (string) $this->balance($batch)->on_hand_qty);
        $this->assertSame('150.0000', (string) $this->balance($batch)->on_hand_value);
        $this->assertSame(InventoryMovement::SOURCE_MEDICINE_BATCH_RECEIPT, InventoryMovement::firstOrFail()->source_type);
        $this->assertTrue(ActivityLog::where('action', 'medicine_batch_received')->exists());

        // Finance reads only the kinds it posts, so medicine never shows as a GL mismatch (D10).
        $this->assertTrue(app(InventoryLedgerReader::class)->summary()->where('item_kind', 'medicine_batch')->isEmpty());
        $this->assertSame(0, bccomp(app(InventoryLedgerReader::class)->totalValue()['total_value'], '0', 4));
    }

    public function test_batch_rules_duplicates_expiry_and_permission(): void
    {
        $medicine = Medicine::factory()->create();
        $this->receive($medicine, 'L1', '10');

        $this->actingAs($this->manager)->from('/')->post(route('medicines.batches.store', $medicine), $this->batch('L1', '5'))->assertSessionHasErrors('batch_number');
        $this->actingAs($this->manager)->from('/')->post(route('medicines.batches.store', $medicine), $this->batch('L2', '5', '2026-01-01'))->assertSessionHasErrors('expiry_date');
        $this->actingAs($this->manager)->from('/')->post(route('medicines.batches.store', $medicine), $this->batch('L3', '0'))->assertSessionHasErrors('quantity_received');
        $this->actingAs($this->dataEntry)->post(route('medicines.batches.store', $medicine), $this->batch('L4', '5'))->assertForbidden();
        $this->assertSame(1, MedicineBatch::count());
    }

    public function test_adjustments_and_counts_move_stock_with_a_reason(): void
    {
        $medicine = Medicine::factory()->create();
        $batch = $this->receive($medicine, 'L1', '50');

        $this->actingAs($this->manager)->post(route('medicine-batches.adjust', $batch), ['type' => 'deduct', 'quantity' => '5', 'reason' => 'Vial dropped'])->assertSessionHasNoErrors();
        $this->actingAs($this->manager)->post(route('medicine-batches.adjust', $batch), ['type' => 'count', 'quantity' => '40', 'reason' => 'Monthly count'])->assertSessionHasNoErrors();
        $this->assertSame('40.0000', (string) $this->balance($batch)->on_hand_qty);

        $this->actingAs($this->manager)->from('/')->post(route('medicine-batches.adjust', $batch), ['type' => 'deduct', 'quantity' => '41', 'reason' => 'Too much'])->assertSessionHasErrors('quantity');
        $this->actingAs($this->manager)->from('/')->post(route('medicine-batches.adjust', $batch), ['type' => 'add', 'quantity' => '1'])->assertSessionHasErrors('reason');
        $this->actingAs($this->dataEntry)->post(route('medicine-batches.adjust', $batch), ['type' => 'add', 'quantity' => '1', 'reason' => 'Found one'])->assertForbidden();
        $this->assertSame('40.0000', (string) $this->balance($batch)->on_hand_qty);
        $this->assertSame(2, ActivityLog::where('action', 'medicine_stock_adjusted')->count());
    }

    public function test_units_cannot_change_once_a_medicine_has_stock(): void
    {
        $medicine = Medicine::factory()->create(['name' => 'Penstrep']);
        $this->receive($medicine, 'L1', '10');

        $this->actingAs($this->manager)->from('/')->put(route('medicines.update', $medicine), [
            'name' => 'Penstrep', 'formulation' => $medicine->formulation, 'concentration' => $medicine->concentration, 'dose_unit' => 'ml',
            'stock_unit' => 'bottle', 'dose_units_per_stock_unit' => '100', 'meat_withdrawal_days' => 28, 'withdrawal_source_note' => 'Label', 'is_active' => 1,
        ])->assertSessionHasErrors('stock_unit');
        $this->assertSame('ml', $medicine->fresh()->stock_unit);
    }

    // --- recording doses --------------------------------------------------------

    public function test_a_group_dose_takes_stock_once_and_each_pig_gets_its_own_withdrawal(): void
    {
        $pigs = [$this->pig(), $this->pig(), $this->pig()];
        $medicine = Medicine::factory()->create(['name' => 'Oxytetracycline', 'meat_withdrawal_days' => 28]);
        $batch = $this->receive($medicine, 'L1', '100');

        $this->actingAs($this->dataEntry)->post(route('medications.store'), $this->dose($medicine, $pigs, ['dose_amount' => '2']))->assertSessionHasNoErrors();

        $event = MedicationDosingEvent::firstOrFail();
        $this->assertSame('6.0000', (string) $event->total_stock_quantity);
        $this->assertSame(1, $event->allocations()->count());
        $this->assertSame('94.0000', (string) $this->balance($batch)->on_hand_qty);
        $this->assertSame(1, InventoryMovement::where('source_type', InventoryMovement::SOURCE_MEDICATION_DOSING_ALLOCATION)->count());

        $doses = MedicationAdministration::orderBy('pig_id')->get();
        $this->assertCount(3, $doses);
        foreach ($doses as $dose) {
            $this->assertSame('2.0000', (string) $dose->dose_amount);
            $this->assertSame(28, $dose->withdrawal_days_snapshot);
            $this->assertSame('2026-07-13', $dose->withdrawal_ends_on->toDateString());
        }
        $this->assertSame('6.0000', bcadd(bcadd((string) $doses[0]->stock_quantity, (string) $doses[1]->stock_quantity, 4), (string) $doses[2]->stock_quantity, 4));
        $this->assertTrue(app(WithdrawalStatusService::class)->forPig($pigs[1])['restricted']);
        $this->assertSame('2026-07-14', app(WithdrawalStatusService::class)->clearsOn($pigs[1])->toDateString(), 'Sellable the day after (D7)');
    }

    public function test_doses_convert_to_stock_units_and_can_be_entered_as_a_group_total(): void
    {
        $vaccine = Medicine::factory()->vialOf50Doses()->create(['name' => 'Circovirus vaccine']);
        $this->receive($vaccine, 'V1', '4');
        $pigs = array_map(fn () => $this->pig(), range(1, 10));

        $this->actingAs($this->dataEntry)->post(route('medications.store'), $this->dose($vaccine, $pigs, ['dose_amount' => '1', 'dose_unit' => 'dose']))->assertSessionHasNoErrors();
        $this->assertSame('0.2000', (string) MedicationDosingEvent::latest('id')->first()->total_stock_quantity, '10 doses from 50-dose vials');

        $water = Medicine::factory()->create(['name' => 'Amoxicillin soluble', 'allowed_routes' => ['Water']]);
        $this->receive($water, 'W1', '500');
        $this->actingAs($this->dataEntry)->post(route('medications.store'), $this->dose($water, array_slice($pigs, 0, 4),
            ['amount_basis' => 'total', 'dose_amount' => '200', 'route' => 'Water']))->assertSessionHasNoErrors();
        $event = MedicationDosingEvent::latest('id')->first();
        $this->assertSame('200.0000', (string) $event->total_stock_quantity);
        $this->assertSame('50.0000', (string) $event->administrations()->first()->dose_amount);
    }

    public function test_batches_are_used_earliest_expiry_first_skipping_expired_ones(): void
    {
        $medicine = Medicine::factory()->create();
        $expired = $this->receive($medicine, 'OLD', '50', '2026-06-20');
        $soon = $this->receive($medicine, 'SOON', '3', '2026-08-01');
        $later = $this->receive($medicine, 'LATER', '100', '2027-01-01');
        $this->travel(10)->days();  // OLD has now expired

        $this->actingAs($this->dataEntry)->post(route('medications.store'), $this->dose($medicine, [$this->pig()], ['dose_amount' => '5']))->assertSessionHasNoErrors();

        $allocations = MedicationDosingEvent::firstOrFail()->allocations()->orderBy('id')->get();
        $this->assertSame([$soon->id, $later->id], $allocations->pluck('medicine_batch_id')->all());
        $this->assertSame(['3.0000', '2.0000'], $allocations->map(fn ($a) => (string) $a->stock_quantity)->all());
        $this->assertSame('50.0000', (string) $this->balance($expired)->on_hand_qty);

        $this->actingAs($this->dataEntry)->from('/')->post(route('medications.store'), $this->dose($medicine, [$this->pig()], ['batch_id' => $expired->id]))
            ->assertSessionHasErrors('batch_id');
    }

    public function test_not_enough_stock_saves_nothing(): void
    {
        $medicine = Medicine::factory()->create();
        $batch = $this->receive($medicine, 'L1', '3');
        $pigs = [$this->pig(), $this->pig()];

        $this->actingAs($this->dataEntry)->from('/')->post(route('medications.store'), $this->dose($medicine, $pigs, ['dose_amount' => '2']))->assertSessionHasErrors('dose_amount');
        $this->actingAs($this->dataEntry)->from('/')->post(route('medications.store'), $this->dose($medicine, $pigs, ['dose_amount' => '2', 'batch_id' => $batch->id]))->assertSessionHasErrors('batch_id');

        $this->assertSame(0, MedicationDosingEvent::count() + MedicationAdministration::count());
        $this->assertSame('3.0000', (string) $this->balance($batch)->on_hand_qty);
    }

    public function test_amounts_and_units_are_checked(): void
    {
        $medicine = Medicine::factory()->create(['dose_unit' => 'ml', 'allowed_routes' => ['IM']]);
        $this->receive($medicine, 'L1', '100');
        $pig = $this->pig();

        foreach ([['dose_unit' => 'mg'], ['dose_amount' => '0'], ['dose_amount' => '-1'], ['dose_amount' => '1.23456'], ['route' => 'Oral']] as $bad) {
            $this->actingAs($this->dataEntry)->from('/')->post(route('medications.store'), $this->dose($medicine, [$pig], $bad))->assertSessionHasErrors();
        }
        $this->actingAs($this->dataEntry)->from('/')->post(route('medications.store'), $this->dose($medicine, [$pig], ['administered_at' => '2026-06-16 09:00']))->assertSessionHasErrors('administered_at');
        $this->assertSame(0, MedicationDosingEvent::count());
    }

    public function test_a_double_submit_deducts_once(): void
    {
        $medicine = Medicine::factory()->create();
        $batch = $this->receive($medicine, 'L1', '100');
        $payload = $this->dose($medicine, [$this->pig()], ['dose_amount' => '4']);

        $this->actingAs($this->dataEntry)->post(route('medications.store'), $payload)->assertRedirect();
        $this->actingAs($this->dataEntry)->post(route('medications.store'), $payload)->assertRedirect();

        $this->assertSame(1, MedicationDosingEvent::count());
        $this->assertSame(1, MedicationAdministration::count());
        $this->assertSame('96.0000', (string) $this->balance($batch)->on_hand_qty);
    }

    public function test_a_stock_failure_leaves_no_dose_behind(): void
    {
        $medicine = Medicine::factory()->create();
        $this->receive($medicine, 'L1', '100');
        $this->partialMock(InventoryService::class, fn ($mock) => $mock->shouldReceive('recordUsage')->andThrow(new \RuntimeException('Ledger unavailable')));

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($this->dataEntry)->post(route('medications.store'), $this->dose($medicine, [$this->pig()]));
            $this->fail('Expected the ledger failure to surface.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Ledger unavailable', $e->getMessage());
        }
        $this->assertSame(0, MedicationDosingEvent::count() + MedicationAdministration::count());
    }

    public function test_a_dose_keeps_the_withdrawal_it_was_given_with(): void
    {
        $pig = $this->pig();
        $medicine = Medicine::factory()->create(['meat_withdrawal_days' => 7]);
        $this->receive($medicine, 'L1', '100');
        $this->actingAs($this->dataEntry)->post(route('medications.store'), $this->dose($medicine, [$pig]));

        $medicine->update(['meat_withdrawal_days' => 35]);
        $dose = MedicationAdministration::firstOrFail();
        $this->assertSame(7, $dose->withdrawal_days_snapshot);
        $this->assertSame('2026-06-22', $dose->withdrawal_ends_on->toDateString());
    }

    // --- scheduled doses ----------------------------------------------------------

    public function test_giving_and_missing_scheduled_doses_completes_the_course(): void
    {
        $pigs = [$this->pig(), $this->pig()];
        $medicine = Medicine::factory()->create(['allowed_routes' => []]);
        $batch = $this->receive($medicine, 'L1', '100');
        $this->actingAs($this->manager)->post(route('treatment-courses.store'), [
            'purpose' => 'treatment', 'pig_ids' => array_map(fn ($p) => $p->id, $pigs),
            'medicines' => [['medicine_id' => $medicine->id, 'dose_amount' => '3', 'dose_unit' => 'ml', 'frequency' => 'daily', 'number_of_doses' => 2, 'start_at' => '2026-06-15 08:00']],
        ])->assertSessionHasNoErrors();
        $course = TreatmentCourse::firstOrFail();
        $first = ScheduledDose::whereDate('due_at', '2026-06-15')->pluck('id')->all();

        $this->actingAs($this->dataEntry)->get(route('health.create-medication', ['scheduled' => $first]))->assertOk()->assertSee('Giving 2 scheduled dose(s)');
        $this->actingAs($this->dataEntry)->post(route('medications.store'), $this->dose($medicine, $pigs, ['dose_amount' => '3', 'scheduled_dose_ids' => $first]))->assertSessionHasNoErrors();
        $this->assertSame(2, ScheduledDose::where('status', 'administered')->whereNotNull('medication_administration_id')->count());
        $this->assertSame($course->id, (int) MedicationAdministration::first()->treatment_course_id);

        $this->actingAs($this->dataEntry)->post(route('medications.store'), $this->dose($medicine, $pigs, ['scheduled_dose_ids' => $first]))->assertSessionHasErrors('scheduled_dose_ids');

        $before = (string) $this->balance($batch)->on_hand_qty;
        foreach (ScheduledDose::where('status', 'scheduled')->get() as $dose) {
            $this->actingAs($this->dataEntry)->post(route('scheduled-doses.close', $dose), ['status' => 'missed', 'reason' => 'Pig off feed'])->assertSessionHasNoErrors();
        }
        $this->assertSame($before, (string) $this->balance($batch)->on_hand_qty, 'Missed doses take no stock');
        $this->assertSame('completed', $course->fresh()->status);
        $this->assertSame(2, ActivityLog::where('action', 'scheduled_dose_missed')->count());
    }

    // --- corrections ----------------------------------------------------------------

    public function test_voiding_returns_stock_lifts_withdrawal_and_reopens_the_scheduled_dose(): void
    {
        $pig = $this->pig();
        $medicine = Medicine::factory()->create(['allowed_routes' => []]);
        $batch = $this->receive($medicine, 'L1', '100');
        $this->actingAs($this->manager)->post(route('treatment-courses.store'), [
            'purpose' => 'treatment', 'pig_ids' => [$pig->id],
            'medicines' => [['medicine_id' => $medicine->id, 'dose_amount' => '5', 'dose_unit' => 'ml', 'frequency' => 'once', 'start_at' => '2026-06-15 08:00']],
        ]);
        $scheduled = ScheduledDose::firstOrFail();
        $this->actingAs($this->dataEntry)->post(route('medications.store'), $this->dose($medicine, [$pig], ['dose_amount' => '5', 'scheduled_dose_ids' => [$scheduled->id]]));
        $event = MedicationDosingEvent::firstOrFail();
        $this->assertSame('completed', TreatmentCourse::first()->status);

        $this->actingAs($this->dataEntry)->post(route('doses.void', $event), ['void_reason' => 'Wrong pig entered'])->assertForbidden();
        $this->actingAs($this->manager)->post(route('doses.void', $event), ['void_reason' => 'Wrong pig entered'])->assertRedirect(route('doses.show', $event));

        $this->assertSame('voided', $event->fresh()->status);
        $this->assertSame('100.0000', (string) $this->balance($batch)->on_hand_qty);
        $this->assertSame('voided', MedicationAdministration::firstOrFail()->status);
        $this->assertFalse(app(WithdrawalStatusService::class)->forPig($pig)['restricted']);
        $this->assertSame('scheduled', $scheduled->fresh()->status);
        $this->assertSame('active', TreatmentCourse::first()->status);
        $this->assertTrue(ActivityLog::where('action', 'medication_dose_voided')->exists());
        $this->actingAs($this->manager)->post(route('doses.void', $event), ['void_reason' => 'Again please'])->assertForbidden();
    }

    public function test_correcting_puts_the_old_stock_back_and_takes_the_new_amount(): void
    {
        $pigs = [$this->pig(), $this->pig()];
        $medicine = Medicine::factory()->create();
        $batch = $this->receive($medicine, 'L1', '100');
        $this->actingAs($this->dataEntry)->post(route('medications.store'), $this->dose($medicine, $pigs, ['dose_amount' => '2']));
        $original = MedicationDosingEvent::firstOrFail();

        $this->actingAs($this->manager)->get(route('doses.edit', $original))->assertOk()->assertSee('Correct Dose #'.$original->id);
        $this->actingAs($this->manager)->from('/')->put(route('doses.update', $original), $this->dose($medicine, $pigs, ['dose_amount' => '3']))->assertSessionHasErrors('correction_reason');
        $this->actingAs($this->manager)->put(route('doses.update', $original), $this->dose($medicine, $pigs, ['dose_amount' => '3', 'correction_reason' => 'Vet gave 3 ml, not 2']))
            ->assertRedirect();

        $replacement = MedicationDosingEvent::where('corrects_event_id', $original->id)->firstOrFail();
        $this->assertSame('corrected', $original->fresh()->status);
        $this->assertSame('6.0000', (string) $replacement->total_stock_quantity);
        $this->assertSame('94.0000', (string) $this->balance($batch)->on_hand_qty);
        $this->assertSame((int) $this->dataEntry->id, (int) $replacement->administered_by, 'Who gave the dose does not change');
        $this->assertSame(1, InventoryMovement::where('source_type', InventoryMovement::SOURCE_MEDICATION_DOSING_ALLOCATION_REVERSAL)->count());
        $this->assertSame((string) $this->balance($batch)->on_hand_qty, $this->ledgerSum($batch), 'Movements reconcile to the balance');
        $this->assertSame(2, MedicationAdministration::where('status', 'corrected')->count());
        $this->assertEqualsCanonicalizing(MedicationAdministration::where('status', 'corrected')->pluck('id')->all(),
            MedicationAdministration::where('medication_dosing_event_id', $replacement->id)->pluck('corrects_id')->all());
    }

    public function test_stock_doses_are_corrected_on_their_dose_record_not_the_legacy_form(): void
    {
        $medicine = Medicine::factory()->create();
        $this->receive($medicine, 'L1', '100');
        $this->actingAs($this->dataEntry)->post(route('medications.store'), $this->dose($medicine, [$this->pig()]));
        $row = MedicationAdministration::firstOrFail();

        $this->actingAs($this->manager)->get(route('medications.edit', $row))->assertForbidden();
        $this->actingAs($this->manager)->get(route('medications.show', $row))->assertOk()->assertSee('Open the dose record');
    }

    public function test_another_farms_doses_and_batches_return_404(): void
    {
        $other = Organization::factory()->create();
        app(CurrentOrganization::class)->bind($other);
        $theirs = Medicine::factory()->create(['organization_id' => $other->id]);
        $batch = MedicineBatch::factory()->create(['organization_id' => $other->id, 'medicine_id' => $theirs->id]);
        $event = MedicationDosingEvent::create(['organization_id' => $other->id, 'medicine_id' => $theirs->id, 'administered_at' => now(),
            'total_stock_quantity' => '1', 'stock_unit' => 'ml', 'idempotency_key' => (string) Str::uuid()]);
        app(CurrentOrganization::class)->bind($this->org);

        $this->actingAs($this->manager)->get(route('doses.show', $event->id))->assertNotFound();
        $this->actingAs($this->manager)->post(route('doses.void', $event->id), ['void_reason' => 'Not ours at all'])->assertNotFound();
        $this->actingAs($this->manager)->get(route('medicines.show', $theirs->id))->assertNotFound();
        $this->actingAs($this->manager)->post(route('medicine-batches.adjust', $batch->id), ['type' => 'add', 'quantity' => '1', 'reason' => 'Sneaky'])->assertNotFound();
        $this->actingAs($this->dataEntry)->from('/')->post(route('medications.store'), $this->dose($theirs, [$this->pig()]))->assertSessionHasErrors('medicine_id');
    }

    public function test_pages_render(): void
    {
        $medicine = Medicine::factory()->create();
        $this->receive($medicine, 'L1', '100');
        $pig = $this->pig();
        $this->actingAs($this->dataEntry)->post(route('medications.store'), $this->dose($medicine, [$pig]));

        $this->actingAs($this->dataEntry)->get(route('health.create-medication'))->assertOk()->assertSee('Record a Dose');
        $this->actingAs($this->dataEntry)->get(route('medications.createForPig', $pig))->assertOk();
        $this->actingAs($this->dataEntry)->get(route('doses.show', MedicationDosingEvent::first()))->assertOk()->assertSee('Stock taken')->assertDontSee('Void dose');
        $this->actingAs($this->dataEntry)->get(route('medicines.show', $medicine))->assertOk()->assertSee('L1')->assertDontSee('Receive a batch');
        $this->actingAs($this->manager)->get(route('medicines.show', $medicine))->assertOk()->assertSee('Receive a batch');
        $this->actingAs($this->dataEntry)->get(route('medicines.index'))->assertOk()->assertSee('98 ml');
        $this->actingAs($this->dataEntry)->get(route('health.show', $pig))->assertOk();
    }

    // --- helpers --------------------------------------------------------------------

    /** Switching users mid-test would trip the password-hash session check, so start a fresh session per user. */
    public function actingAs($user, $guard = null)
    {
        if (auth()->user()?->isNot($user)) {
            $this->flushSession();
        }

        return parent::actingAs($user, $guard);
    }

    private function receive(Medicine $medicine, string $number, string $quantity, string $expiry = '2027-06-30', array $extra = []): MedicineBatch
    {
        $this->actingAs($this->manager)->post(route('medicines.batches.store', $medicine), $this->batch($number, $quantity, $expiry) + $extra)->assertSessionHasNoErrors();

        return MedicineBatch::where('medicine_id', $medicine->id)->where('batch_number', $number)->firstOrFail();
    }

    private function batch(string $number, string $quantity, string $expiry = '2027-06-30'): array
    {
        return ['batch_number' => $number, 'received_at' => '2026-06-10', 'expiry_date' => $expiry, 'quantity_received' => $quantity];
    }

    private function dose(Medicine $medicine, array $pigs, array $overrides = []): array
    {
        return $overrides + [
            'pig_ids' => array_map(fn ($p) => $p->id, $pigs), 'medicine_id' => $medicine->id, 'administered_at' => now()->subHour()->format('Y-m-d H:i'),
            'amount_basis' => 'per_animal', 'dose_amount' => '2', 'dose_unit' => $medicine->dose_unit, 'idempotency_key' => (string) Str::uuid(),
        ];
    }

    private function balance(MedicineBatch $batch): InventoryBalance
    {
        return InventoryBalance::where('item_kind', 'medicine_batch')->where('item_id', $batch->id)->firstOrFail();
    }

    private function ledgerSum(MedicineBatch $batch): string
    {
        return InventoryMovement::where('item_kind', 'medicine_batch')->where('item_id', $batch->id)->get()
            ->reduce(fn ($sum, $m) => bcadd($sum, (string) $m->quantity_delta, 4), '0.0000');
    }

    private function pig(): PigManagement
    {
        $n = ++$this->sequence;

        return PigManagement::create([
            'organization_id' => $this->org->id, 'ear_tag_number' => (string) (300 + $n), 'code' => 'MS-'.$n,
            'breed' => 'LW', 'gender' => 'F', 'stock_type' => 'standard', 'date_of_birth' => now()->subMonths(5)->toDateString(),
        ]);
    }

    private function user(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);

        return User::forceCreate([
            'name' => ucfirst($roleName), 'email' => $roleName.'-'.(++$this->sequence).'@stock.test', 'password' => bcrypt('password'),
            'role' => $roleName, 'role_id' => $role->id, 'organization_id' => $this->org->id, 'active_status' => true,
        ]);
    }
}
