<?php

namespace Tests\Feature\Finance;

use App\Models\Finance\Account;
use App\Models\Finance\PeriodLockDate;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Support\CurrentOrganization;

/**
 * Shared setup helpers for Phase 1b finance tests. Tests use this trait
 * to avoid repeating the same scaffolding 50 times.
 *
 * Provides:
 *   $this->org             — the Organization
 *   $this->period          — current open fiscal period
 *   $this->user            — test user (admin role)
 *   $this->cashAccount     — '1010' Cash on Hand
 *   $this->revenueAccount  — '4010' Revenue (live pigs standard)
 *   $this->arAccount       — '1110' AR Trade Pig Sales (control account)
 *   $this->expenseAccount  — '6010' Vet Medicine
 */
trait FinanceTestSetup
{
    protected Organization $org;
    protected FiscalYear $year;
    protected FiscalPeriod $period;
    protected User $user;
    protected Account $cashAccount;
    protected Account $revenueAccount;
    protected Account $arAccount;
    protected Account $expenseAccount;

    protected function setUpFinanceFixtures(): void
    {
        $this->org = Organization::create(['slug' => 'cf', 'name' => 'CF']);
        $this->app->make(CurrentOrganization::class)->bind($this->org);

        $this->year = FiscalYear::create([
            'organization_id' => $this->org->id,
            'fiscal_year'     => 2026,
            'name'            => 'FY2026',
            'start_date'      => '2026-01-01',
            'end_date'        => '2026-12-31',
            'status'          => 'open',
        ]);

        $this->year2025 = FiscalYear::create([
            'organization_id' => $this->org->id,
            'fiscal_year'     => 2025,
            'name'            => 'FY2025',
            'start_date'      => '2025-01-01',
            'end_date'        => '2025-12-31',
            'status'          => 'open',
        ]);

        FiscalPeriod::create([
            'organization_id' => $this->org->id,
            'fiscal_year_id'  => $this->year2025->id,
            'period_number'   => 12,
            'name'            => 'December 2025',
            'start_date'      => '2025-12-01',
            'end_date'        => '2025-12-31',
            'status'          => FiscalPeriod::STATUS_OPEN,
            'posting_count'   => 0,
        ]);

        for ($month = 1; $month <= 12; $month++) {
            $start = \Carbon\CarbonImmutable::create(2026, $month, 1);
            $period = FiscalPeriod::create([
                'organization_id' => $this->org->id,
                'fiscal_year_id'  => $this->year->id,
                'period_number'   => $month,
                'name'            => $start->format('F Y'),
                'start_date'      => $start->toDateString(),
                'end_date'        => $start->endOfMonth()->toDateString(),
                'status'          => FiscalPeriod::STATUS_OPEN,
                'posting_count'   => 0,
            ]);

            if ($month === 5) {
                $this->period = $period;
            }
        }

        // Also seed FY2027 & June period — some tests post in 2027 dates
        $this->year2027 = FiscalYear::create([
            'organization_id' => $this->org->id,
            'fiscal_year'     => 2027,
            'name'            => 'FY2027',
            'start_date'      => '2027-01-01',
            'end_date'        => '2027-12-31',
            'status'          => 'open',
        ]);

        FiscalPeriod::create([
            'organization_id' => $this->org->id,
            'fiscal_year_id'  => $this->year2027->id,
            'period_number'   => 6,
            'name'            => 'June 2027',
            'start_date'      => '2027-06-01',
            'end_date'        => '2027-06-30',
            'status'          => FiscalPeriod::STATUS_OPEN,
            'posting_count'   => 0,
        ]);

        $role = Role::firstOrCreate(['name' => 'admin']);
        $this->user = User::forceCreate([
            'name'            => 'Admin',
            'email'           => 'admin_' . uniqid() . '@example.com',
            'password'        => bcrypt('x'),
            'role'            => 'admin',
            'role_id'         => $role->id,
            'organization_id' => $this->org->id,
        ]);

        $this->cashAccount = Account::create([
            'organization_id'      => $this->org->id,
            'code'                 => '1010',
            'name'                 => 'Cash on Hand',
            'account_type'         => Account::TYPE_ASSET,
            'currency_code'        => 'GHS',
            'is_active'            => true,
            'allow_manual_journal' => true,
        ]);

        $this->revenueAccount = Account::create([
            'organization_id'      => $this->org->id,
            'code'                 => '4010',
            'name'                 => 'Revenue — Live Pigs Standard',
            'account_type'         => Account::TYPE_REVENUE,
            'currency_code'        => 'GHS',
            'is_active'            => true,
            'allow_manual_journal' => true,
        ]);

        $this->arAccount = Account::create([
            'organization_id'      => $this->org->id,
            'code'                 => '1110',
            'name'                 => 'AR — Pig Sales',
            'account_type'         => Account::TYPE_ASSET,
            'currency_code'        => 'GHS',
            'is_control'           => true,
            'is_active'            => true,
            'allow_manual_journal' => false,
        ]);

        $this->expenseAccount = Account::create([
            'organization_id'      => $this->org->id,
            'code'                 => '6010',
            'name'                 => 'Vet Medicine',
            'account_type'         => Account::TYPE_EXPENSE,
            'currency_code'        => 'GHS',
            'is_active'            => true,
            'allow_manual_journal' => true,
        ]);

        // Minimal account required by biological-asset resolver used by inventory
        
        
        
        Account::firstOrCreate(['organization_id' => $this->org->id, 'code' => '1360'], [
            'name'                 => 'Biological Assets',
            'account_type'         => Account::TYPE_ASSET,
            'currency_code'        => 'GHS',
            'is_active'            => true,
            'allow_manual_journal' => true,
        ]);

        // Ensure common accounts referenced by posting subscribers exist
        $accountTypes = [
            // Inventory accounts
            '1220' => Account::TYPE_ASSET,
            '1230' => Account::TYPE_ASSET,  // clearing account (Phase 7 transitional)
            '2010' => Account::TYPE_LIABILITY,
            // Biological asset stage accounts (Phase 7)
            '1310' => Account::TYPE_ASSET,
            '1320' => Account::TYPE_ASSET,
            '1330' => Account::TYPE_ASSET,
            '1340' => Account::TYPE_ASSET,
            '1350' => Account::TYPE_ASSET,
            '1360' => Account::TYPE_ASSET,
            '1370' => Account::TYPE_ASSET,
            // COGS & expense accounts
            '5110' => Account::TYPE_EXPENSE,
            '5320' => Account::TYPE_EXPENSE,
            '6990' => Account::TYPE_EXPENSE,
        ];
        $accountNames = [
            '1220' => 'Finished Feed Inventory',
            '1230' => 'Live Pig Inventory (Clearing)',
            '2010' => 'Accounts Payable',
            '1310' => 'Breeding Stock',
            '1320' => 'Sows in Production',
            '1330' => 'Boars',
            '1340' => 'Piglets',
            '1350' => 'Weaners',
            '1360' => 'Growers',
            '1370' => 'Finishers',
            '5110' => 'Cost of Pigs Sold',
            '5320' => 'Mortality Expense',
            '6990' => 'Sundry Expense',
        ];
        foreach ($accountTypes as $code => $type) {
            Account::firstOrCreate(['organization_id' => $this->org->id, 'code' => $code], [
                'name'                 => $accountNames[$code],
                'account_type'         => $type,
                'currency_code'        => 'GHS',
                'is_active'            => true,
                'allow_manual_journal' => true,
            ]);
        }
    }
}
