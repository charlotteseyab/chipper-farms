<?php

namespace Tests\Feature\Finance\Phase7;

use App\Models\Finance\Loan;
use App\Models\Finance\LoanScheduleLine;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Organizations\OrganizationFeatureService;
use App\Support\Features\FeatureCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

/**
 * Phase 7 — loans in farm language (blueprint §26).
 *
 * §26: "hide amortisation and liability terminology from owner view unless the
 * user opens details", and "view upcoming payments". The schedule lines already
 * existed; they were reachable only inside one loan's amortisation table.
 */
class LoansOwnerViewTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        $this->seed(\Database\Seeders\FinancePermissionsSeeder::class);
        $this->seed(\Database\Seeders\Phase8LoansTreasuryPermissionsSeeder::class);
    }

    public function test_the_list_shows_what_is_still_owed_across_active_loans(): void
    {
        $this->makeLoan('Ecobank', principal: '10000.00', outstanding: '6000.00', accrued: '250.00');
        $this->makeLoan('Fidelity', principal: '5000.00', outstanding: '5000.00', accrued: '0.00');

        // A settled loan is not money the farm still has to find.
        $this->makeLoan('Old loan', principal: '1000.00', outstanding: '0.00', accrued: '0.00', status: Loan::STATUS_SETTLED);

        $this->actingAs($this->userWith(['view_finance', 'view_loans']))
            ->get(route('finance.loans.index'))
            ->assertOk()
            ->assertSee('Still to pay back')
            ->assertSee('11,250.00');
    }

    public function test_upcoming_payments_are_shown_on_the_list(): void
    {
        $loan = $this->makeLoan('Ecobank', principal: '10000.00', outstanding: '6000.00', accrued: '0.00');

        LoanScheduleLine::create([
            'organization_id' => $this->org->id,
            'loan_id'         => $loan->id,
            'period_number'   => 1,
            'due_date'        => '2026-06-30',
            'opening_balance' => '6000.00',
            'interest_due'    => '50.00',
            'principal_due'   => '450.00',
            'total_due'       => '500.00',
            'closing_balance' => '5550.00',
            'status'          => 'pending',
        ]);

        $this->actingAs($this->userWith(['view_finance', 'view_loans']))
            ->get(route('finance.loans.index'))
            ->assertOk()
            ->assertSee('Payments coming up')
            ->assertSee('500.00');
    }

    /**
     * §26 lists reclassification under advanced accounting. It stays available;
     * it comes off the owner's front page.
     */
    public function test_reclassification_is_off_the_owner_front_page(): void
    {
        $this->makeLoan('Ecobank', principal: '10000.00', outstanding: '6000.00', accrued: '0.00');

        $features = $this->app->make(OrganizationFeatureService::class);
        $features->disable(FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING, $this->org);

        // Asserted on the page's own wording, not the bare word: the legacy
        // sidebar (NavigationHelper) carries its own reclassification link and
        // is not gated on the feature — a Phase 14 concern, recorded rather
        // than silently absorbed into this assertion.
        $this->actingAs($this->userWith(['view_finance', 'view_loans']))
            ->get(route('finance.loans.index'))
            ->assertOk()
            ->assertDontSee('current / non-current reclassification');

        $this->flushSession();

        // Resolved fresh from the container rather than reusing the instance
        // captured before the request: the flag service caches per organisation
        // for the life of the instance, and the one the request used is the one
        // whose cache has to be invalidated.
        $this->app->make(OrganizationFeatureService::class)
            ->enable(FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING, $this->org);

        $this->actingAs($this->userWith(['view_finance', 'view_loans']))
            ->get(route('finance.loans.index'))
            ->assertOk()
            ->assertSee('current / non-current reclassification');
    }

    public function test_the_loans_surface_closes_when_the_feature_is_off(): void
    {
        $this->app->make(OrganizationFeatureService::class)
            ->disable(FeatureCatalog::FINANCE_LOANS, $this->org);

        $this->actingAs($this->userWith(['view_finance', 'view_loans']))
            ->get(route('finance.loans.index'))
            ->assertNotFound();
    }

    /* ---------------------------------------------------------------- */

    protected function makeLoan(
        string $lender,
        string $principal,
        string $outstanding,
        string $accrued,
        string $status = Loan::STATUS_ACTIVE,
    ): Loan {
        static $counter = 0;

        return Loan::create([
            'organization_id'               => $this->org->id,
            'reference'                     => 'LOAN-' . str_pad((string) (++$counter), 4, '0', STR_PAD_LEFT),
            'lender_name'                   => $lender,
            'loan_type'                     => Loan::TYPE_BANK,
            'principal_amount'              => $principal,
            'principal_outstanding'         => $outstanding,
            'interest_accrued_outstanding'  => $accrued,
            'total_interest_charged'        => '0.00',
            'annual_interest_rate'          => '0.100000',
            'term_months'                   => 12,
            'amortisation_method'           => Loan::METHOD_REDUCING_BALANCE,
            'status'                        => $status,
            'created_by_user_id'            => $this->user->id,
        ]);
    }

    /** @param  list<string>  $permissions */
    protected function userWith(array $permissions): User
    {
        static $sequence = 0;
        $role = Role::firstOrCreate(['name' => 'phase7-loans-' . (++$sequence)]);

        foreach ($permissions as $name) {
            $permission = Permission::firstOrCreate(
                ['name' => $name],
                ['name' => $name, 'description' => $name, 'module' => 'finance'],
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        return User::forceCreate([
            'name'              => 'Loan tester ' . $sequence,
            'email'             => "loans-{$sequence}@example.com",
            'password'          => bcrypt('secret'),
            'role'              => $role->name,
            'role_id'           => $role->id,
            'organization_id'   => $this->org->id,
            'active_status'     => true,
            'email_verified_at' => now(),
        ]);
    }
}
