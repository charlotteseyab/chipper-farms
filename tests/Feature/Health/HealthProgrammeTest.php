<?php

namespace Tests\Feature\Health;

use App\Models\Farrowing;
use App\Models\HealthProgramme;
use App\Models\Mating;
use App\Models\Medicine;
use App\Models\Organization;
use App\Models\PigManagement;
use App\Models\Role;
use App\Models\ScheduledDose;
use App\Models\User;
use App\Services\Health\HealthAlertService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Database\Seeders\FarmOperationsPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Herd health programmes: routine doses set up once, scheduled for each
 * animal as it qualifies, never twice, never backdated, and skippable.
 */
class HealthProgrammeTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $manager;

    private Medicine $vaccine;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 6, 15)->setTime(7, 0));
        $this->org = Organization::factory()->create();
        app(CurrentOrganization::class)->bind($this->org);
        $this->seed(FarmOperationsPermissionsSeeder::class);
        $this->manager = $this->user('farmmanager');
        $this->vaccine = Medicine::factory()->create(['name' => 'PCV2 vaccine', 'allowed_routes' => [], 'dose_unit' => 'ml']);
    }

    public function test_an_age_rule_schedules_animals_reaching_that_age_once(): void
    {
        $young = $this->pig(bornDaysAgo: 10);   // 21 days old on 26 Jun
        $old = $this->pig(bornDaysAgo: 40);     // already past 21 days: not scheduled
        $breeder = $this->pig(bornDaysAgo: 10, stock: 'breeding');  // not a grower

        $this->programme(['trigger' => 'age', 'days' => 21, 'applies_to' => 'growers'])
            ->assertSessionHas('success', fn ($m) => str_contains($m, '1 dose(s) scheduled'));
        $this->artisan('health:schedule-programmes')->assertSuccessful();

        $doses = ScheduledDose::all();
        $this->assertCount(1, $doses, 'Scheduled once, however often the job runs.');
        $this->assertSame($young->id, (int) $doses->first()->pig_id);
        $this->assertSame('2026-06-26 08:00', $doses->first()->due_at->format('Y-m-d H:i'));
        $this->assertSame('vaccination', $doses->first()->courseMedicine->course->purpose);
    }

    public function test_an_animal_that_reached_the_age_before_it_was_on_the_system_is_not_backdated(): void
    {
        $this->programme(['trigger' => 'age', 'days' => 21, 'applies_to' => 'growers']);

        // Weaned onto stock at 28 days, a week after its 21-day dose was due.
        $this->travelTo(now()->addDays(3));
        $this->pig(bornDaysAgo: 28);
        $this->artisan('health:schedule-programmes')->assertSuccessful();

        $this->assertSame(0, ScheduledDose::count());
    }

    public function test_animals_reaching_the_age_later_are_picked_up_by_the_hourly_job(): void
    {
        $this->programme(['trigger' => 'age', 'days' => 21, 'applies_to' => 'growers']);
        $this->assertSame(0, ScheduledDose::count());

        $this->pig(bornDaysAgo: 2);  // 21 days old on 4 Jul
        $this->travelTo(now()->addDays(6));  // 4 Jul is now inside the 14-day window
        $this->artisan('health:schedule-programmes')->assertSuccessful();

        $this->assertSame('2026-07-04', ScheduledDose::firstOrFail()->due_at->toDateString());
    }

    public function test_an_interval_rule_doses_the_whole_group_on_each_date(): void
    {
        $sow = $this->pig(bornDaysAgo: 600, stock: 'breeding');
        $boar = $this->pig(bornDaysAgo: 700, stock: 'breeding', gender: 'M');
        $this->pig(bornDaysAgo: 90);  // grower

        $this->programme(['purpose' => 'deworming', 'trigger' => 'interval', 'days' => 7, 'applies_to' => 'breeding', 'starts_on' => '2026-06-16']);

        // 16, 23 and 30 Jun are inside 14 days ahead (to 29 Jun): two dates.
        $this->assertEqualsCanonicalizing(
            [$sow->id, $sow->id, $boar->id, $boar->id],
            ScheduledDose::pluck('pig_id')->map(fn ($id) => (int) $id)->all(),
        );
    }

    public function test_a_sow_confirmed_pregnant_is_scheduled_before_farrowing(): void
    {
        $sow = $this->pig(bornDaysAgo: 600, stock: 'breeding');
        $boar = $this->pig(bornDaysAgo: 700, stock: 'breeding', gender: 'M');
        Mating::create(['male_id' => $boar->id, 'female_id' => $sow->id, 'planned_date' => '2026-03-01', 'actual_date' => '2026-03-01',
            'status' => 'completed', 'pregnancy_status' => 'pregnant', 'expected_farrowing_date' => '2026-07-10']);

        $this->programme(['name' => 'E. coli vaccine', 'trigger' => 'before_farrowing', 'days' => 14, 'applies_to' => 'all']);

        $dose = ScheduledDose::firstOrFail();
        $this->assertSame($sow->id, (int) $dose->pig_id);
        $this->assertSame('2026-06-26', $dose->due_at->toDateString());
        $this->assertSame('breeding_females', HealthProgramme::firstOrFail()->applies_to, 'Breeding-cycle rules only ever apply to sows.');
    }

    public function test_a_sow_is_scheduled_after_she_farrows(): void
    {
        $sow = $this->pig(bornDaysAgo: 600, stock: 'breeding');
        $this->programme(['name' => 'Sow iron', 'trigger' => 'after_farrowing', 'days' => 3, 'applies_to' => 'all']);

        Farrowing::create(['sow_id' => $sow->id, 'farrowing_date' => '2026-06-16', 'live_births' => 10, 'total_piglets_born' => 10,
            'number_of_males' => 5, 'number_of_females' => 5, 'recorded_by' => $this->manager->id, 'status' => 'active']);
        $this->artisan('health:schedule-programmes')->assertSuccessful();

        $this->assertSame('2026-06-19', ScheduledDose::where('pig_id', $sow->id)->firstOrFail()->due_at->toDateString());
    }

    public function test_a_dose_skipped_for_one_animal_is_not_scheduled_again(): void
    {
        $pig = $this->pig(bornDaysAgo: 10);
        $this->programme(['trigger' => 'age', 'days' => 21, 'applies_to' => 'growers']);

        $dose = ScheduledDose::firstOrFail();
        $this->actingAs($this->manager)->post(route('scheduled-doses.close', $dose), ['status' => 'cancelled', 'reason' => 'Sick; vet says wait'])
            ->assertSessionHasNoErrors();
        $this->artisan('health:schedule-programmes')->assertSuccessful();

        $this->assertSame(1, ScheduledDose::where('pig_id', $pig->id)->count());
        $this->assertSame(ScheduledDose::CANCELLED, $dose->fresh()->status);
    }

    public function test_pausing_cancels_doses_not_yet_given_and_resuming_brings_them_back(): void
    {
        $this->pig(bornDaysAgo: 10);
        $this->programme(['trigger' => 'age', 'days' => 21, 'applies_to' => 'growers']);
        $programme = HealthProgramme::firstOrFail();

        $this->actingAs($this->manager)->post(route('health-programmes.pause', $programme), ['reason' => 'Vaccine out of stock'])->assertSessionHasNoErrors();
        $this->assertSame(0, ScheduledDose::where('status', ScheduledDose::SCHEDULED)->count());

        $this->actingAs($this->manager)->post(route('health-programmes.resume', $programme))->assertSessionHasNoErrors();
        $this->assertSame(1, ScheduledDose::where('status', ScheduledDose::SCHEDULED)->count());
    }

    public function test_programme_doses_show_in_health_alerts_and_on_the_programme_page(): void
    {
        $this->pig(bornDaysAgo: 18);  // 21 days old on 18 Jun
        $this->programme(['trigger' => 'age', 'days' => 21, 'applies_to' => 'growers']);

        $alert = app(HealthAlertService::class)->alerts(CarbonImmutable::now())->firstOrFail();
        $this->assertSame('vaccination', $alert['type']);
        $this->assertSame('upcoming', $alert['state']);

        $this->actingAs($this->manager)->get(route('health-programmes.index'))->assertOk()
            ->assertSee('PCV2 programme')->assertSee('At 21 days old')->assertSee('1 dose scheduled');
    }

    public function test_the_form_renders_for_adding_and_editing(): void
    {
        $this->actingAs($this->manager)->get(route('health-programmes.create'))->assertOk()->assertSee('Add a programme dose')->assertSee('Before expected farrowing');

        $this->programme(['trigger' => 'age', 'days' => 21, 'applies_to' => 'growers']);
        $this->actingAs($this->manager)->get(route('health-programmes.edit', HealthProgramme::firstOrFail()))->assertOk()->assertSee('Edit PCV2 programme');
    }

    public function test_one_farm_never_schedules_another_farms_animals(): void
    {
        $this->programme(['trigger' => 'age', 'days' => 21, 'applies_to' => 'all']);

        $other = Organization::factory()->create();
        PigManagement::withoutGlobalScopes()->create(['organization_id' => $other->id, 'ear_tag_number' => 'X-1', 'code' => 'OTHER-1',
            'breed' => 'LW', 'gender' => 'F', 'stock_type' => 'standard', 'date_of_birth' => now()->subDays(10)->toDateString()]);

        $this->artisan('health:schedule-programmes')->assertSuccessful();

        $this->assertSame(0, ScheduledDose::withoutGlobalScopes()->count());
    }

    private function programme(array $overrides)
    {
        return $this->actingAs($this->manager)->post(route('health-programmes.store'), $overrides + [
            'name' => 'PCV2 programme', 'purpose' => 'vaccination', 'medicine_id' => $this->vaccine->id, 'dose_amount' => '2',
            'due_time' => '08:00', 'starts_on' => now()->toDateString(),
        ])->assertSessionHasNoErrors();
    }

    /** Switching users mid-test would trip the password-hash session check, so start a fresh session per user. */
    public function actingAs($user, $guard = null)
    {
        if (auth()->user()?->isNot($user)) {
            $this->flushSession();
        }

        return parent::actingAs($user, $guard);
    }

    private function pig(int $bornDaysAgo, string $stock = 'standard', string $gender = 'F'): PigManagement
    {
        $n = ++$this->sequence;

        return PigManagement::create(['organization_id' => $this->org->id, 'ear_tag_number' => (string) (500 + $n), 'code' => 'HP-'.$n,
            'breed' => 'LW', 'gender' => $gender, 'stock_type' => $stock, 'date_of_birth' => now()->subDays($bornDaysAgo)->toDateString()]);
    }

    private function user(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);

        return User::forceCreate(['name' => ucfirst($roleName).' '.(++$this->sequence), 'email' => $roleName.'-'.$this->sequence.'@programme.test',
            'password' => bcrypt('password'), 'role' => $roleName, 'role_id' => $role->id, 'organization_id' => $this->org->id, 'active_status' => true]);
    }
}
