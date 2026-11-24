<?php

namespace Tests\Feature\Health;

use App\Models\MedicationAdministration;
use App\Models\Medicine;
use App\Models\Organization;
use App\Models\PigManagement;
use App\Models\Role;
use App\Models\TreatmentCourse;
use App\Models\User;
use App\Services\Health\HealthAlertService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Database\Seeders\FarmOperationsPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Health alerts: vaccination, deworming, treatment, follow-up and withdrawal,
 * each as overdue, due today or coming up — on the alerts page, the pig's own
 * pages, the dashboards, and as reminders.
 */
class HealthAlertsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $manager;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 6, 15)->setTime(7, 0));
        $this->org = Organization::factory()->create();
        app(CurrentOrganization::class)->bind($this->org);
        $this->seed(FarmOperationsPermissionsSeeder::class);
        $this->manager = $this->user('farmmanager');
    }

    public function test_each_kind_lands_in_the_right_state(): void
    {
        $pig = $this->pig();

        $this->course('deworming', 'Ivermectin', [$pig], '2026-06-13 08:00');   // two days ago, never given
        $this->course('treatment', 'Amoxicillin', [$pig], '2026-06-15 16:00');  // later today
        $this->course('vaccination', 'PRRS vaccine', [$pig], '2026-06-18 09:00', followUp: '2026-06-20');

        $alerts = app(HealthAlertService::class)->alerts(CarbonImmutable::now());
        $state = fn (string $type) => $alerts->firstWhere('type', $type)['state'] ?? null;

        $this->assertSame('overdue', $state('deworming'));
        $this->assertSame('today', $state('treatment'));
        $this->assertSame('upcoming', $state('vaccination'));
        $this->assertSame('upcoming', $state('follow_up'));

        $summary = app(HealthAlertService::class)->summary(CarbonImmutable::now());
        $this->assertSame([1, 1, 2], [$summary['overdue'], $summary['today'], $summary['upcoming']]);
        $this->assertSame('1 deworming', HealthAlertService::describe($summary['by_type']['overdue']));
    }

    public function test_a_dose_due_earlier_today_turns_overdue_after_the_grace_period(): void
    {
        $this->course('treatment', 'Amoxicillin', [$this->pig()], '2026-06-15 06:30');

        $this->assertSame('today', app(HealthAlertService::class)->alerts(CarbonImmutable::now())->first()['state']);

        $this->travelTo(now()->setTime(9, 0));  // 2½ hours late; grace is 2 hours
        $this->assertSame('overdue', app(HealthAlertService::class)->alerts(CarbonImmutable::now())->first()['state']);
    }

    public function test_doses_beyond_the_look_ahead_are_not_listed(): void
    {
        $this->course('vaccination', 'PRRS vaccine', [$this->pig()], '2026-06-30 09:00');

        $this->assertTrue(app(HealthAlertService::class)->alerts(CarbonImmutable::now())->isEmpty());
        $this->assertCount(1, app(HealthAlertService::class)->alerts(CarbonImmutable::now(), days: 30));
    }

    public function test_withdrawal_ending_soon_is_a_farm_alert_and_any_withdrawal_shows_on_the_pig(): void
    {
        $soon = $this->pig();
        $later = $this->pig();
        $this->withdrawal($soon, 3);    // ends 18 Jun
        $this->withdrawal($later, 20);  // ends 5 Jul — outside the farm window

        $farm = app(HealthAlertService::class)->alerts(CarbonImmutable::now())->where('type', 'withdrawal');
        $this->assertCount(1, $farm);
        $this->assertStringContainsString($soon->code, $farm->first()['detail']);
        $this->assertStringContainsString('Can be sold from Fri 19 Jun', $farm->first()['title']);

        $this->assertCount(1, app(HealthAlertService::class)->alerts(CarbonImmutable::now(), pigId: $later->id)->where('type', 'withdrawal'));
    }

    public function test_the_alerts_page_filters_by_kind_and_state(): void
    {
        $pig = $this->pig();
        $this->course('deworming', 'Ivermectin', [$pig], '2026-06-13 08:00');
        $this->course('vaccination', 'PRRS vaccine', [$pig], '2026-06-18 09:00');

        $this->actingAs($this->manager)->get(route('health.alerts'))->assertOk()
            ->assertSeeInOrder(['Overdue (1)', 'Deworming: Ivermectin', 'Due today (0)', 'Coming up (1)', 'Vaccination: PRRS vaccine']);

        $this->actingAs($this->manager)->get(route('health.alerts', ['type' => 'vaccination']))->assertOk()
            ->assertSee('Vaccination: PRRS vaccine')->assertDontSee('Deworming: Ivermectin');

        $this->actingAs($this->manager)->get(route('health.alerts', ['state' => 'overdue']))->assertOk()
            ->assertSee('Deworming: Ivermectin')->assertDontSee('Vaccination: PRRS vaccine');
    }

    public function test_the_pig_pages_show_only_that_pigs_alerts(): void
    {
        $mine = $this->pig();
        $other = $this->pig();
        $this->course('vaccination', 'PRRS vaccine', [$mine], '2026-06-18 09:00');
        $this->course('deworming', 'Ivermectin', [$other], '2026-06-17 09:00');

        foreach ([route('pigs.show', $mine->id), route('health.show', $mine)] as $url) {
            $this->actingAs($this->manager)->get($url)->assertOk()
                ->assertSee('Health alerts')
                ->assertSee('Vaccination: PRRS vaccine')
                ->assertDontSee('Deworming: Ivermectin');
        }
    }

    public function test_the_manager_dashboard_lists_overdue_health_tasks(): void
    {
        $this->course('deworming', 'Ivermectin', [$this->pig(), $this->pig()], '2026-06-13 08:00');

        $this->actingAs($this->manager)->get(route('manager.dashboard'))->assertOk()
            ->assertSee('Overdue health tasks')
            ->assertSee('1 deworming not yet recorded.');
    }

    public function test_the_data_entry_dashboard_shows_what_is_due_now(): void
    {
        $this->course('deworming', 'Ivermectin', [$this->pig()], '2026-06-13 08:00');
        $this->course('vaccination', 'PRRS vaccine', [$this->pig()], '2026-06-15 16:00');

        $this->actingAs($this->user('dataentry'))->get(route('dataentry.dashboard'))->assertOk()
            ->assertSeeInOrder(['Health tasks', 'Overdue: 1 deworming.', 'Today: 1 vaccination.', 'Open the worklist']);
    }

    public function test_reminders_name_the_kind_and_announce_vaccinations_a_day_ahead(): void
    {
        $this->course('vaccination', 'PRRS vaccine', [$this->pig()], '2026-06-16 09:00');  // tomorrow
        $this->course('deworming', 'Ivermectin', [$this->pig()], '2026-06-14 08:00');      // yesterday
        $this->course('treatment', 'Amoxicillin', [$this->pig()], '2026-06-16 09:00');     // tomorrow, not routine

        $this->artisan('health:notify')->assertSuccessful();
        $this->artisan('health:notify')->assertSuccessful();

        $upcoming = $this->manager->notifications()->where('data->type', 'health.dose_upcoming')->get();
        $this->assertCount(1, $upcoming, 'Only vaccinations and deworming are announced ahead, and only once.');
        $this->assertStringStartsWith('Vaccination tomorrow 09:00: PRRS vaccine', $upcoming->first()->data['title']);

        $overdue = $this->manager->notifications()->where('data->type', 'health.dose_overdue')->firstOrFail();
        $this->assertStringStartsWith('Overdue deworming: Ivermectin', $overdue->data['title']);
    }

    private function course(string $purpose, string $medicineName, array $pigs, string $start, ?string $followUp = null): TreatmentCourse
    {
        $medicine = Medicine::factory()->create(['name' => $medicineName, 'allowed_routes' => []]);

        // Prescribed on the start date, so a course starting in the past is legal.
        $realNow = now();
        $this->travelTo(CarbonImmutable::parse($start)->subHour());
        $this->actingAs($this->manager)->post(route('treatment-courses.store'), array_filter([
            'purpose' => $purpose, 'pig_ids' => array_map(fn ($p) => $p->id, $pigs), 'follow_up_on' => $followUp,
            'medicines' => [['medicine_id' => $medicine->id, 'dose_amount' => '2', 'dose_unit' => 'ml', 'frequency' => 'daily',
                'number_of_doses' => 1, 'start_at' => $start]],
        ]))->assertSessionHasNoErrors();
        $this->travelTo($realNow);

        return TreatmentCourse::latest('id')->firstOrFail();
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

        return PigManagement::create(['organization_id' => $this->org->id, 'ear_tag_number' => (string) (700 + $n), 'code' => 'HA-'.$n,
            'breed' => 'LW', 'gender' => 'F', 'stock_type' => 'standard', 'date_of_birth' => now()->subMonths(5)->toDateString()]);
    }

    private function user(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);

        return User::forceCreate(['name' => ucfirst($roleName).' '.(++$this->sequence), 'email' => $roleName.'-'.$this->sequence.'@alerts.test',
            'password' => bcrypt('password'), 'role' => $roleName, 'role_id' => $role->id, 'organization_id' => $this->org->id, 'active_status' => true]);
    }
}
