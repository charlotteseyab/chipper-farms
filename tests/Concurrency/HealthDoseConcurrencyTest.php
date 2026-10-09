<?php

namespace Tests\Concurrency;

/**
 * Acceptance scenario 5 (health upgrade): two people recording at once, a
 * double-clicked submit, not enough stock, and a correction racing a void.
 * Each must take medicine from stock exactly once and never below zero.
 * Needs MariaDB/MySQL (phpunit.concurrency.xml).
 */
class HealthDoseConcurrencyTest extends ConcurrencyTestCase
{
    private array $ids;

    private string $bootstrap;

    protected function setUp(): void
    {
        parent::setUp();
        $migration = $this->artisan('migrate', '--force');
        $this->assertSame(0, $migration['exit'], json_encode($migration));
        $slug = 'dose-race-'.bin2hex(random_bytes(5));
        $setup = $this->runConcurrently(1, <<<PHP
\$org = App\Models\Organization::create(['name' => 'Dose race', 'slug' => '{$slug}']);
app(App\Support\CurrentOrganization::class)->bind(\$org);
\$actor = App\Models\User::factory()->create(['organization_id' => \$org->id, 'role' => 'admin']);
\$pigs = collect(range(1, 4))->map(fn (\$n) => App\Models\PigManagement::create(['organization_id' => \$org->id, 'ear_tag_number' => (string) (700 + \$n),
    'code' => '{$slug}-'.\$n, 'breed' => 'LW', 'gender' => 'F', 'stock_type' => 'standard', 'date_of_birth' => now()->subMonths(5)->toDateString()]));
\$medicine = App\Models\Medicine::create(['name' => '{$slug}', 'dose_unit' => 'ml', 'stock_unit' => 'ml', 'dose_units_per_stock_unit' => '1', 'meat_withdrawal_days' => 7]);
auth()->login(\$actor);
\$batch = app(App\Services\Health\MedicineStockService::class)->receive(\$actor, \$medicine, ['batch_number' => 'R1', 'received_at' => now()->toDateString(),
    'expiry_date' => now()->addYear()->toDateString(), 'quantity_received' => '100', 'unit_cost' => '1']);
\$course = app(App\Services\Health\TreatmentCourseService::class)->prescribe(\$actor, ['purpose' => 'treatment'], [\$pigs[0]],
    [['medicine_id' => \$medicine->id, 'dose_amount' => '5', 'dose_unit' => 'ml', 'frequency' => 'once', 'start_at' => now()->subHour()->toDateTimeString()]]);
echo json_encode(['org' => \$org->id, 'actor' => \$actor->id, 'pigs' => \$pigs->pluck('id'), 'medicine' => \$medicine->id, 'batch' => \$batch->id,
    'scheduled' => App\Models\ScheduledDose::where('pig_id', \$pigs[0]->id)->value('id')]);
PHP);
        $this->assertSame(0, $setup[0]['exit'], json_encode($setup));
        $this->ids = json_decode(trim($setup[0]['out']), true, flags: JSON_THROW_ON_ERROR);
        $this->bootstrap = "app(App\\Support\\CurrentOrganization::class)->bind(App\\Models\\Organization::findOrFail({$this->ids['org']})); "
            ."\$actor = App\\Models\\User::findOrFail({$this->ids['actor']}); auth()->login(\$actor); ";
    }

    public function test_a_double_submit_from_four_processes_takes_stock_once(): void
    {
        $key = '20000000-0000-4000-8000-'.str_pad((string) $this->ids['org'], 12, '0', STR_PAD_LEFT);
        $results = $this->runConcurrently(4, $this->bootstrap.$this->record([$this->ids['pigs'][1]], '4', "'{$key}'"), isolation: 'READ COMMITTED');

        $this->assertCount(1, array_unique(array_column($this->ok($results), 'out')), 'Every process returns the same dose record');
        $this->assertSame(['events' => 1, 'on_hand' => '96.0000', 'ledger' => '96.0000'], $this->state());
    }

    public function test_one_scheduled_dose_given_by_four_people_is_recorded_once(): void
    {
        $results = $this->runConcurrently(4, $this->bootstrap.$this->record([$this->ids['pigs'][0]], '5', '(string) Illuminate\Support\Str::uuid()',
            "'scheduled_dose_ids' => [{$this->ids['scheduled']}]"), isolation: 'READ COMMITTED');

        $outcomes = array_count_values(array_column($this->ok($results), 'out'));
        $this->assertSame(3, $outcomes['refused'] ?? 0, json_encode($results));
        $this->assertSame(['events' => 1, 'on_hand' => '95.0000', 'ledger' => '95.0000'], $this->state());
    }

    public function test_stock_never_goes_below_zero_when_doses_race(): void
    {
        // Three doses of 40 ml against 100 ml: exactly two can be taken.
        $results = $this->runConcurrently(3, $this->bootstrap.$this->record([$this->ids['pigs'][2]], '40', '(string) Illuminate\Support\Str::uuid()'), isolation: 'READ COMMITTED');

        $refused = count(array_filter($this->ok($results), fn ($r) => $r['out'] === 'refused'));
        $this->assertSame(1, $refused, json_encode($results));
        $this->assertSame(['events' => 2, 'on_hand' => '20.0000', 'ledger' => '20.0000'], $this->state());
    }

    public function test_a_correction_racing_a_void_leaves_the_ledger_consistent(): void
    {
        $setup = $this->runConcurrently(1, $this->bootstrap.$this->record([$this->ids['pigs'][3]], '10', '(string) Illuminate\Support\Str::uuid()'));
        $event = (int) $this->ok($setup)[0]['out'];

        $results = $this->runConcurrently(4, $this->bootstrap.<<<PHP
\$event = App\Models\MedicationDosingEvent::findOrFail({$event});
try {
    if (getmypid() % 2) {
        app(App\Services\Health\CorrectDoseService::class)->void(\$actor, \$event, 'Never given');
        echo 'voided';
    } else {
        app(App\Services\Health\CorrectDoseService::class)->correct(\$actor, \$event, ['medicine_id' => {$this->ids['medicine']}, 'administered_at' => now()->subMinutes(5)->toDateTimeString(),
            'amount_basis' => 'per_animal', 'dose_amount' => '12', 'dose_unit' => 'ml'], [App\Models\PigManagement::findOrFail({$this->ids['pigs'][3]})], 'Was 12 ml');
        echo 'corrected';
    }
} catch (DomainException \$e) { echo 'refused'; }
PHP, isolation: 'READ COMMITTED');

        $outcomes = array_count_values(array_column($this->ok($results), 'out'));
        $this->assertSame(1, ($outcomes['voided'] ?? 0) + ($outcomes['corrected'] ?? 0), json_encode($results));
        $state = $this->state();
        $this->assertSame($state['ledger'], $state['on_hand'], 'Balance equals the sum of its movements');
        $this->assertContains($state['on_hand'], ['100.0000', '88.0000'], 'Either all stock is back, or only the corrected amount is taken');
    }

    /** PHP for one process recording a dose; prints the event id, or "refused" when a rule stops it. */
    private function record(array $pigIds, string $amount, string $keyExpression, string $extra = ''): string
    {
        $pigs = implode(', ', $pigIds);

        return <<<PHP
try {
    \$event = app(App\Services\Health\RecordDoseService::class)->record(\$actor, ['medicine_id' => {$this->ids['medicine']},
        'administered_at' => now()->subMinutes(10)->toDateTimeString(), 'amount_basis' => 'per_animal', 'dose_amount' => '{$amount}', 'dose_unit' => 'ml',
        'idempotency_key' => {$keyExpression}, {$extra}], App\Models\PigManagement::whereKey([{$pigs}])->get());
    echo \$event->id;
} catch (Illuminate\Validation\ValidationException \$e) { echo 'refused'; }
PHP;
    }

    /** @return array{events: int, on_hand: string, ledger: string} */
    private function state(): array
    {
        $verify = $this->runConcurrently(1, $this->bootstrap.<<<PHP
\$balance = App\Models\Finance\InventoryBalance::where('item_kind', 'medicine_batch')->where('item_id', {$this->ids['batch']})->firstOrFail();
\$ledger = App\Models\Finance\InventoryMovement::where('item_kind', 'medicine_batch')->where('item_id', {$this->ids['batch']})->get()
    ->reduce(fn (\$s, \$m) => bcadd(\$s, (string) \$m->quantity_delta, 4), '0.0000');
echo json_encode(['events' => App\Models\MedicationDosingEvent::where('status', 'recorded')->count(), 'on_hand' => (string) \$balance->on_hand_qty, 'ledger' => \$ledger]);
PHP);

        return json_decode(trim($this->ok($verify)[0]['out']), true, flags: JSON_THROW_ON_ERROR);
    }

    private function ok(array $results): array
    {
        foreach ($results as $result) {
            $this->assertSame(0, $result['exit'], json_encode($result));
        }

        return $results;
    }
}
