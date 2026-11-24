<?php

namespace Tests\Feature\Health;

use App\Models\Medicine;
use App\Models\Organization;
use App\Models\PigManagement;
use App\Models\Role;
use App\Models\ScheduledDose;
use App\Models\TreatmentCourse;
use App\Models\User;
use App\Support\CurrentOrganization;
use Database\Seeders\FarmOperationsPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Every health table pages with Previous · numbers · Next, and each table on a page keeps its own page. */
class HealthPaginationTest extends TestCase
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
        $role = Role::firstOrCreate(['name' => 'farmmanager']);
        $this->manager = User::forceCreate(['name' => 'Manager', 'email' => 'manager@paging.test', 'password' => bcrypt('password'),
            'role' => 'farmmanager', 'role_id' => $role->id, 'organization_id' => $this->org->id, 'active_status' => true]);
    }

    public function test_the_catalogue_pages_with_previous_numbers_and_next(): void
    {
        Medicine::factory()->count(30)->create();

        $this->actingAs($this->manager)->get(route('medicines.index'))->assertOk()
            ->assertSeeInOrder(['Showing', '1–25', 'of', '30', 'medicines', '‹ Previous', '1', '2', 'Next ›'])
            ->assertDontSee('Page 1 of 2');
        $this->actingAs($this->manager)->get(route('medicines.index', ['page' => 2]))->assertOk()
            ->assertSeeInOrder(['26–30', 'of', '30']);
    }

    public function test_a_single_page_shows_just_the_count(): void
    {
        Medicine::factory()->count(3)->create();

        // "Showing 1–3 of 3 · Page 1 of 1" said the same thing three times.
        $this->actingAs($this->manager)->get(route('medicines.index'))->assertOk()
            ->assertSeeInOrder(['3', 'medicines'])
            ->assertDontSee('Showing 1–3')->assertDontSee('Page 1 of 1')->assertDontSee('Next ›');
    }

    public function test_a_course_pages_its_due_times_per_medicine(): void
    {
        $medicine = Medicine::factory()->create(['allowed_routes' => []]);
        $this->actingAs($this->manager)->post(route('treatment-courses.store'), ['purpose' => 'treatment', 'pig_ids' => [$this->pig()->id, $this->pig()->id],
            'medicines' => [['medicine_id' => $medicine->id, 'dose_amount' => '1', 'dose_unit' => 'ml', 'frequency' => 'daily', 'number_of_doses' => 12, 'start_at' => '2026-06-16 08:00']]])
            ->assertSessionHasNoErrors();
        $course = TreatmentCourse::firstOrFail();
        $param = 'medicine_'.$course->medicines()->value('id').'_page';

        // The first due time also shows on the summary card, so check the last ones, which only the table shows.
        $this->actingAs($this->manager)->get(route('treatment-courses.show', $course))->assertOk()
            ->assertSeeInOrder(['1–5', 'of', '12', 'due times'])->assertDontSee('Sat 27 Jun 08:00')->assertDontSee('Fri 26 Jun 08:00');
        $this->actingAs($this->manager)->get(route('treatment-courses.show', [$course, $param => 3]))->assertOk()
            ->assertSeeInOrder(['11–12', 'of', '12'])->assertSee('Sat 27 Jun 08:00')->assertSee('Fri 26 Jun 08:00')->assertDontSee('Wed 17 Jun 08:00');
        $this->assertSame(24, ScheduledDose::count(), 'Two animals at each due time stay together on one page');
    }

    public function test_a_group_dose_pages_its_animals_and_the_medicine_page_keeps_each_table_separate(): void
    {
        $medicine = Medicine::factory()->create(['allowed_routes' => []]);
        $this->actingAs($this->manager)->post(route('medicines.batches.store', $medicine), ['batch_number' => 'B1', 'received_at' => '2026-06-01',
            'expiry_date' => '2027-01-01', 'quantity_received' => '500', 'unit_cost' => '1']);
        $pigs = collect(range(1, 25))->map(fn () => $this->pig());
        $this->actingAs($this->manager)->post(route('medications.store'), ['pig_ids' => $pigs->pluck('id')->all(), 'medicine_id' => $medicine->id,
            'administered_at' => now()->subHour()->format('Y-m-d H:i'), 'amount_basis' => 'per_animal', 'dose_amount' => '1', 'dose_unit' => 'ml',
            'idempotency_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $event = \App\Models\MedicationDosingEvent::firstOrFail();

        $this->actingAs($this->manager)->get(route('doses.show', [$event, 'animals_page' => 2]))->assertOk()
            ->assertSeeInOrder(['Animals (25)', '21–25', 'of', '25', 'animals']);

        // Eleven batches put the batch table on two pages; its links keep the dose table's page.
        foreach (range(2, 11) as $n) {
            $this->actingAs($this->manager)->post(route('medicines.batches.store', $medicine), ['batch_number' => 'B'.$n, 'received_at' => '2026-06-01',
                'expiry_date' => '2027-01-01', 'quantity_received' => '5', 'unit_cost' => '1']);
        }
        $page = html_entity_decode($this->actingAs($this->manager)->get(route('medicines.show', [$medicine, 'usage_page' => 3]))->assertOk()->getContent());
        $this->assertMatchesRegularExpression('/\?(?=[^"]*usage_page=3)(?=[^"]*batches_page=2)[^"]*"/', $page, 'Paging one table keeps the other table\'s page');
    }

    public function test_report_tables_page_and_keep_their_filters(): void
    {
        $this->actingAs($this->manager)->get(route('health.reports', ['tab' => 'cases', 'status' => 'active']))->assertOk()
            ->assertDontSee('Showing');  // nothing to page yet

        $medicine = Medicine::factory()->create(['allowed_routes' => []]);
        $this->actingAs($this->manager)->post(route('treatment-courses.store'), ['purpose' => 'treatment', 'pig_ids' => [$this->pig()->id],
            'medicines' => [['medicine_id' => $medicine->id, 'dose_amount' => '1', 'dose_unit' => 'ml', 'frequency' => 'every_n_hours', 'interval' => 1, 'number_of_doses' => 30, 'start_at' => '2026-06-14 08:00']]]);
        ScheduledDose::query()->update(['status' => ScheduledDose::MISSED, 'status_reason' => 'Test']);

        $this->actingAs($this->manager)->get(route('health.reports', ['tab' => 'doses', 'from' => '2026-06-01', 'not_given_page' => 2]))->assertOk()
            ->assertSeeInOrder(['Scheduled doses not given', '26–30', 'of', '30', 'doses not given'])
            ->assertSee('from=2026-06-01', false);
    }

    private function pig(): PigManagement
    {
        $n = ++$this->sequence;

        return PigManagement::create(['organization_id' => $this->org->id, 'ear_tag_number' => (string) (1000 + $n), 'code' => 'PG-'.$n,
            'breed' => 'LW', 'gender' => 'F', 'stock_type' => 'standard', 'date_of_birth' => now()->subMonths(5)->toDateString()]);
    }
}
