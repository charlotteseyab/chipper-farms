<?php

namespace Tests\Feature\Stock;

use App\Models\HealthRecord;
use App\Models\MedicationAdministration;
use App\Models\Organization;
use App\Models\PigManagement;
use App\Models\PigWeight;
use App\Models\Role;
use App\Models\User;
use App\Support\CurrentOrganization;
use Database\Seeders\FarmOperationsPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The All stock page: what each row says, and the filters that narrow it. */
class AllStockIndexTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $manager;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 6, 15)->setTime(9, 0));
        $this->org = Organization::factory()->create();
        app(CurrentOrganization::class)->bind($this->org);
        $this->seed(FarmOperationsPermissionsSeeder::class);
        $role = Role::firstOrCreate(['name' => 'farmmanager']);
        $this->manager = User::forceCreate(['name' => 'Manager', 'email' => 'manager@stock.test', 'password' => bcrypt('password'),
            'role' => 'farmmanager', 'role_id' => $role->id, 'organization_id' => $this->org->id, 'active_status' => true]);
    }

    public function test_a_row_shows_the_latest_weight_not_just_a_weight_alert(): void
    {
        $pig = $this->pig('AS-1');
        PigWeight::create(['pig_id' => $pig->id, 'weight' => 64.5, 'weigh_date' => '2026-06-10']);

        $this->page()->assertSee('64.5 kg')->assertSee('2026-06-10')->assertDontSee('No Data');
    }

    public function test_health_status_shows_withdrawal_and_open_cases(): void
    {
        $treated = $this->pig('AS-TREATED');
        $sick = $this->pig('AS-SICK');
        $this->pig('AS-WELL');
        MedicationAdministration::create(['pig_id' => $treated->id, 'administration_date' => now()->toDateString(),
            'medication_name' => 'Amoxicillin', 'dosage' => '5 ml', 'withdrawal_period_days' => 10]);
        HealthRecord::create(['pig_id' => $sick->id, 'record_date' => now()->toDateString(), 'condition' => 'Lameness',
            'severity' => 'moderate', 'case_status' => 'open']);

        $this->page()
            ->assertSee('Withdrawal to 2026-06-25')
            ->assertSee('Lameness')
            ->assertSeeInOrder(['AS-WELL', 'None']);
    }

    public function test_the_health_filter_narrows_the_list(): void
    {
        $treated = $this->pig('AS-TREATED');
        $this->pig('AS-WELL');
        MedicationAdministration::create(['pig_id' => $treated->id, 'administration_date' => now()->toDateString(),
            'medication_name' => 'Amoxicillin', 'dosage' => '5 ml', 'withdrawal_period_days' => 10]);

        $this->page(['health' => 'withdrawal'])
            ->assertSee('AS-TREATED')->assertDontSee('AS-WELL');
    }

    public function test_rows_per_page_can_be_chosen_from_the_allowed_sizes(): void
    {
        foreach (range(1, 12) as $n) {
            $this->pig('AS-'.$n);
        }

        $this->page()->assertViewHas('pigmanagement', fn ($p) => $p->count() === 10);
        $this->page(['per_page' => 25])->assertViewHas('pigmanagement', fn ($p) => $p->count() === 12);
        $this->page(['per_page' => 9999])->assertViewHas('pigmanagement', fn ($p) => $p->perPage() === 10);
    }

    public function test_the_delete_confirmation_is_escaped(): void
    {
        $this->pig('AS-1', tag: "O'Neil");

        $this->page()
            ->assertSee('data-confirm="Are you sure you want to delete O&#039;Neil? This action cannot be undone."', false)
            ->assertDontSee("confirm('Are you sure", false);
    }

    public function test_an_empty_search_says_nothing_matched(): void
    {
        $this->pig('AS-1');

        $this->page(['q' => 'nothing-matches-this'])->assertSee('No pigs match your current search and filter criteria.');
    }

    private function page(array $query = [])
    {
        return $this->actingAs($this->manager)->get(route('all-stocks.index', $query))->assertOk();
    }

    private function pig(string $code, ?string $tag = null): PigManagement
    {
        $n = ++$this->sequence;

        return PigManagement::create(['organization_id' => $this->org->id, 'ear_tag_number' => $tag ?? (string) (800 + $n), 'code' => $code,
            'breed' => 'LW', 'gender' => 'F', 'stock_type' => 'standard', 'date_of_birth' => now()->subMonths(5)->toDateString()]);
    }
}
