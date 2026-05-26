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
            'name'            => 'FY2026',
            'start_date'      => '2026-01-01',
            'end_date'        => '2026-12-31',
            'status'          => 'open',
        ]);

        $this->period = FiscalPeriod::create([
            'organization_id' => $this->org->id,
            'fiscal_year_id'  => $this->year->id,
            'period_number'   => 5,
            'name'            => 'May 2026',
            'start_date'      => '2026-05-01',
            'end_date'        => '2026-05-31',
            'status'          => FiscalPeriod::STATUS_OPEN,
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
    }
}
