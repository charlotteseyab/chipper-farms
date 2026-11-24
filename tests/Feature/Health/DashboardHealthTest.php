<?php

namespace Tests\Feature\Health;

use App\Models\HealthRecord;
use App\Models\MedicationAdministration;
use App\Models\Medicine;
use App\Models\Organization;
use App\Models\PigManagement;
use App\Models\Role;
use App\Models\ScheduledDose;
use App\Models\User;
use App\Services\Dashboard\DashboardHealthMetrics;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Database\Seeders\FarmOperationsPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The herd-health section on the farm dashboards: what is due, who is sick,
 * what cannot be sold, whether prevention is kept up, whether there is
 * medicine — and that the dashboard's other health numbers agree with it.
 */
class DashboardHealthTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $manager;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 6, 15)->setTime(10, 0));
        $this->org = Organization::factory()->create();
        app(CurrentOrganization::class)->bind($this->org);
        $this->seed(FarmOperationsPermissionsSeeder::class);
        $this->manager = $this->user('farmmanager');
    }

    public function test_sick_animals_are_counted_per_animal_including_group_cases(): void
    {
        $this->openCase([$this->pig(), $this->pig(), $this->pig()], 'Diarrhoea (scours)', 'severe');
        $this->openCase([$this->pig()], 'Lameness', 'mild');
        $resolved = $this->openCase([$this->pig()], 'Cough', 'mild');
        $resolved->forceFill(['case_status' => 'resolved', 'resolved_at' => now()])->save();

        $cases = $this->metrics()['cases'];

        $this->assertSame(4, $cases['sick_animals']);
        $this->assertSame(2, $cases['open_cases']);
        $this->assertSame(3, $cases['severe_animals']);
        $this->assertSame(3, $cases['new_this_week']);
        $this->assertSame('Diarrhoea (scours)', array_key_first($cases['top_conditions']));
    }

    public function test_prevention_shows_how_many_vaccination_and_deworming_doses_were_given(): void
    {
        $vaccine = $this->medicine('PRRS vaccine', stock: '100');
        $this->course($vaccine, 'vaccination', [$this->pig(), $this->pig(), $this->pig(), $this->pig()], '2026-06-12 08:00');
        $this->course($vaccine, 'treatment', [$this->pig()], '2026-06-12 08:00');  // treatments are not prevention

        $doses = ScheduledDose::whereHas('courseMedicine.course', fn ($q) => $q->where('purpose', 'vaccination'))->orderBy('id')->get();
        $doses[0]->forceFill(['status' => ScheduledDose::ADMINISTERED])->save();
        $doses[1]->forceFill(['status' => ScheduledDose::ADMINISTERED])->save();
        $doses[2]->forceFill(['status' => ScheduledDose::CANCELLED, 'status_reason' => 'Sick'])->save();

        $p = $this->metrics()['prevention'];

        $this->assertSame([4, 2, 1, 1], [$p['due'], $p['given'], $p['not_given'], $p['unrecorded']]);
        $this->assertEquals(50, $p['rate']);
    }

    public function test_medicine_short_for_the_doses_due_is_flagged(): void
    {
        $wormer = $this->medicine('Ivermectin', stock: '4');  // 2 ml a dose: enough for two
        $this->course($wormer, 'deworming', [$this->pig(), $this->pig(), $this->pig()], '2026-06-17 08:00');

        $m = $this->metrics()['medicine'];

        $this->assertSame(1, $m['short']);
        $this->assertSame('Ivermectin', $m['short_names']);
    }

    public function test_withdrawal_counts_animals_and_those_clearing_soon(): void
    {
        $this->withdrawal($this->pig(), 3);
        $this->withdrawal($this->pig(), 30);

        $w = $this->metrics()['withdrawal'];

        $this->assertSame(2, $w['animals']);
        $this->assertSame(1, $w['clearing_soon']);
    }

    public function test_the_manager_dashboard_shows_the_section_and_agrees_with_it(): void
    {
        $this->openCase([$this->pig(), $this->pig()], 'Diarrhoea (scours)', 'severe');
        $this->withdrawal($this->pig(), 5);

        $this->actingAs($this->manager)->get(route('manager.dashboard'))->assertOk()
            ->assertSee('Herd health')
            ->assertSee('animals in 1 open case')
            ->assertSee('Diarrhoea (scours) · 2')
            ->assertSee('that must not be sold for food')
            // The side panel uses the same count as the section.
            ->assertSeeInOrder(['Sick animals', 'Animals in open health cases', '2']);
    }

    public function test_the_admin_and_data_entry_dashboards_show_health_work(): void
    {
        $this->actingAs($this->user('admin'))->get(route('admin.dashboard'))->assertOk()->assertSee('Herd health')->assertSee('Vaccination and deworming');
        // Data entry sees health as one line of the day's work, not the manager panel.
        $this->actingAs($this->user('dataentry'))->get(route('dataentry.dashboard'))->assertOk()->assertSee('Health tasks');
    }

    public function test_someone_without_health_access_does_not_see_it(): void
    {
        $this->actingAs($this->user('customer'))->get('/dashboard')->assertOk()->assertDontSee('Herd health');
    }

    private function metrics(): array
    {
        return app(DashboardHealthMetrics::class)->summary(CarbonImmutable::now());
    }

    private function openCase(array $pigs, string $condition, string $severity): HealthRecord
    {
        $this->actingAs($this->manager)->post(route('health.store'), ['pig_ids' => array_map(fn ($p) => $p->id, $pigs),
            'observed_at' => now()->subHour()->format('Y-m-d H:i'), 'condition' => $condition, 'severity' => $severity])->assertSessionHasNoErrors();

        return HealthRecord::latest('id')->firstOrFail();
    }

    private function medicine(string $name, string $stock): Medicine
    {
        $medicine = Medicine::factory()->create(['name' => $name, 'allowed_routes' => [], 'dose_unit' => 'ml']);
        $this->actingAs($this->manager)->post(route('medicines.batches.store', $medicine), ['batch_number' => 'L-'.Str::random(5), 'received_at' => '2026-06-01',
            'expiry_date' => '2027-06-01', 'quantity_received' => $stock, 'unit_cost' => '1'])->assertSessionHasNoErrors();

        return $medicine;
    }

    private function course(Medicine $medicine, string $purpose, array $pigs, string $start): void
    {
        $realNow = now();
        $this->travelTo(CarbonImmutable::parse($start)->subHour());
        $this->actingAs($this->manager)->post(route('treatment-courses.store'), [
            'purpose' => $purpose, 'pig_ids' => array_map(fn ($p) => $p->id, $pigs),
            'medicines' => [['medicine_id' => $medicine->id, 'dose_amount' => '2', 'dose_unit' => 'ml', 'frequency' => 'daily', 'number_of_doses' => 1, 'start_at' => $start]],
        ])->assertSessionHasNoErrors();
        $this->travelTo($realNow);
    }

    private function withdrawal(PigManagement $pig, int $days): void
    {
        MedicationAdministration::create(['pig_id' => $pig->id, 'administration_date' => now()->toDateString(),
            'medication_name' => 'Oxytetracycline', 'dosage' => '5 ml', 'withdrawal_period_days' => $days]);
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

        return PigManagement::create(['organization_id' => $this->org->id, 'ear_tag_number' => (string) (300 + $n), 'code' => 'DH-'.$n,
            'breed' => 'LW', 'gender' => 'F', 'stock_type' => 'standard', 'date_of_birth' => now()->subMonths(5)->toDateString()]);
    }

    private function user(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);

        return User::forceCreate(['name' => ucfirst($roleName).' '.(++$this->sequence), 'email' => $roleName.'-'.$this->sequence.'@dashboard.test',
            'password' => bcrypt('password'), 'role' => $roleName, 'role_id' => $role->id, 'organization_id' => $this->org->id, 'active_status' => true]);
    }
}
