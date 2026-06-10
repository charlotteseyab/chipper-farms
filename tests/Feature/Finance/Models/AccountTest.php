<?php

namespace Tests\Feature\Finance\Models;

use App\Models\Finance\Account;
use App\Models\Organization;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create(['slug' => 'cf', 'name' => 'CF']);
        $this->app->make(CurrentOrganization::class)->bind($this->org);
    }

    public function test_normal_balance_for_asset_is_debit(): void
    {
        $a = Account::create([
            'organization_id' => $this->org->id,
            'code' => '1010', 'name' => 'Cash',
            'account_type' => Account::TYPE_ASSET,
        ]);
        $this->assertSame(Account::NORMAL_BALANCE_DEBIT, $a->normalBalance());
        $this->assertTrue($a->isDebitNormal());
        $this->assertFalse($a->isCreditNormal());
    }

    public function test_normal_balance_for_liability_is_credit(): void
    {
        $a = Account::create([
            'organization_id' => $this->org->id,
            'code' => '2010', 'name' => 'AP',
            'account_type' => Account::TYPE_LIABILITY,
        ]);
        $this->assertSame(Account::NORMAL_BALANCE_CREDIT, $a->normalBalance());
        $this->assertTrue($a->isCreditNormal());
    }

    public function test_normal_balance_for_equity_is_credit(): void
    {
        $a = Account::create([
            'organization_id' => $this->org->id,
            'code' => '3010', 'name' => 'Capital',
            'account_type' => Account::TYPE_EQUITY,
        ]);
        $this->assertTrue($a->isCreditNormal());
    }

    public function test_normal_balance_for_revenue_is_credit(): void
    {
        $a = Account::create([
            'organization_id' => $this->org->id,
            'code' => '4010', 'name' => 'Revenue',
            'account_type' => Account::TYPE_REVENUE,
        ]);
        $this->assertTrue($a->isCreditNormal());
    }

    public function test_normal_balance_for_expense_is_debit(): void
    {
        $a = Account::create([
            'organization_id' => $this->org->id,
            'code' => '6010', 'name' => 'Vet Medicine',
            'account_type' => Account::TYPE_EXPENSE,
        ]);
        $this->assertTrue($a->isDebitNormal());
    }

    public function test_active_scope_excludes_inactive(): void
    {
        $active = Account::create([
            'organization_id' => $this->org->id,
            'code' => '1010', 'name' => 'Active',
            'account_type' => Account::TYPE_ASSET, 'is_active' => true,
        ]);
        $inactive = Account::create([
            'organization_id' => $this->org->id,
            'code' => '1011', 'name' => 'Inactive',
            'account_type' => Account::TYPE_ASSET, 'is_active' => false,
        ]);

        $this->assertSame(1, Account::active()->whereIn('id', [$active->id, $inactive->id])->count());
    }

    public function test_of_type_scope_filters_by_type(): void
    {
        $asset = Account::create([
            'organization_id' => $this->org->id,
            'code' => '1010', 'name' => 'Cash',
            'account_type' => Account::TYPE_ASSET,
        ]);
        $revenue = Account::create([
            'organization_id' => $this->org->id,
            'code' => '4010', 'name' => 'Revenue',
            'account_type' => Account::TYPE_REVENUE,
        ]);

        $ids = [$asset->id, $revenue->id];
        $this->assertSame(1, Account::ofType(Account::TYPE_ASSET)->whereIn('id', $ids)->count());
        $this->assertSame(1, Account::ofType(Account::TYPE_REVENUE)->whereIn('id', $ids)->count());
        $this->assertSame(0, Account::ofType(Account::TYPE_EXPENSE)->whereIn('id', $ids)->count());
    }

    public function test_accepts_manual_journal_scope(): void
    {
        $manual = Account::create([
            'organization_id' => $this->org->id,
            'code' => '1010', 'name' => 'Cash',
            'account_type' => Account::TYPE_ASSET,
            'allow_manual_journal' => true,
        ]);
        $control = Account::create([
            'organization_id' => $this->org->id,
            'code' => '1110', 'name' => 'AR Control',
            'account_type' => Account::TYPE_ASSET,
            'is_control' => true,
            'allow_manual_journal' => false,
        ]);

        $this->assertSame(1, Account::acceptsManualJournal()->whereIn('id', [$manual->id, $control->id])->count());
    }

    public function test_parent_child_relationship(): void
    {
        $parent = Account::create([
            'organization_id' => $this->org->id,
            'code' => '1000', 'name' => 'Cash and Bank',
            'account_type' => Account::TYPE_ASSET,
        ]);

        $child = Account::create([
            'organization_id' => $this->org->id,
            'code' => '1010', 'name' => 'Cash on Hand',
            'account_type' => Account::TYPE_ASSET,
            'parent_id' => $parent->id,
        ]);

        $this->assertSame($parent->id, $child->parent->id);
        $this->assertSame(1, $parent->children()->count());
    }
}
