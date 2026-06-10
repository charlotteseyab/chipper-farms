<?php

namespace Tests\Feature\Finance\VendorInvoicing;

use App\Models\Counterparty;
use App\Models\Finance\Account;
use App\Models\Finance\SupplierCategory;
use App\Models\RawMaterial;
use App\Services\Finance\VendorInvoicing\ExpenseAccountResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class ExpenseAccountResolverTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected ExpenseAccountResolver $resolver;
    protected Account $explicitAccount;
    protected Account $invAccount;
    protected Account $itemExpenseAccount;
    protected Account $supplierAccount;
    protected Account $categoryAccount;
    protected Account $sundryFallback;
    protected Counterparty $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        $this->explicitAccount    = $this->makeAccount('1100', 'Explicit',         Account::TYPE_ASSET);
        $this->invAccount         = $this->makeAccount('1310', 'Inventory Feed',   Account::TYPE_ASSET);
        $this->itemExpenseAccount = $this->makeAccount('6020', 'Vet Expense',      Account::TYPE_EXPENSE);
        $this->supplierAccount    = $this->makeAccount('6030', 'Supplier Default', Account::TYPE_EXPENSE);
        $this->categoryAccount    = $this->makeAccount('6040', 'Category Default', Account::TYPE_EXPENSE);
        $this->sundryFallback     = $this->makeAccount('6990', 'Sundry Expense',   Account::TYPE_EXPENSE);

        $category = SupplierCategory::create([
            'organization_id' => $this->org->id,
            'code' => 'TEST', 'name' => 'Test',
            'default_expense_account_id' => $this->categoryAccount->id,
            'payment_terms_days' => 30, 'default_withholding_percent' => 0,
            'is_active' => true,
        ]);

        $this->supplier = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'Test', 'code' => 'V-T',
            'type' => Counterparty::TYPE_SUPPLIER,
            'currency_code' => 'GHS',
            'supplier_category_id' => $category->id,
            'default_expense_account_id' => $this->supplierAccount->id,
        ]);

        $this->resolver = $this->app->make(ExpenseAccountResolver::class);
    }

    public function test_explicit_override_wins(): void
    {
        $id = $this->resolver->resolve(
            supplier: $this->supplier,
            explicitAccountId: $this->explicitAccount->id,
        );
        $this->assertSame($this->explicitAccount->id, $id);
    }

    public function test_raw_material_inventory_wins_over_expense(): void
    {
        $rm = RawMaterial::create([
            'name' => 'Soybean', 'unit' => 'kg',
            'inventory_account_id' => $this->invAccount->id,
            'expense_account_id'   => $this->itemExpenseAccount->id,
        ]);

        $id = $this->resolver->resolve(
            supplier: $this->supplier,
            rawMaterialId: $rm->id,
        );
        $this->assertSame($this->invAccount->id, $id, 'inventory account preferred over expense');
    }

    public function test_raw_material_expense_used_when_inventory_null(): void
    {
        $rm = RawMaterial::create([
            'name' => 'Cleaning Spray', 'unit' => 'litre',
            'inventory_account_id' => null,
            'expense_account_id'   => $this->itemExpenseAccount->id,
        ]);

        $id = $this->resolver->resolve(
            supplier: $this->supplier,
            rawMaterialId: $rm->id,
        );
        $this->assertSame($this->itemExpenseAccount->id, $id);
    }

    public function test_raw_material_with_no_account_falls_through_to_supplier(): void
    {
        $rm = RawMaterial::create([
            'name' => 'No GL', 'unit' => 'each',
            'inventory_account_id' => null,
            'expense_account_id'   => null,
        ]);

        $id = $this->resolver->resolve(
            supplier: $this->supplier,
            rawMaterialId: $rm->id,
        );
        $this->assertSame($this->supplierAccount->id, $id);
    }

    public function test_supplier_default_used_when_no_item(): void
    {
        $id = $this->resolver->resolve(supplier: $this->supplier);
        $this->assertSame($this->supplierAccount->id, $id);
    }

    public function test_category_default_used_when_no_supplier_default(): void
    {
        $this->supplier->update(['default_expense_account_id' => null]);
        $this->supplier->refresh();

        $id = $this->resolver->resolve(supplier: $this->supplier);
        $this->assertSame($this->categoryAccount->id, $id);
    }

    public function test_falls_back_to_sundry_when_nothing_set(): void
    {
        $bareSupplier = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'Bare', 'code' => 'V-B',
            'type' => Counterparty::TYPE_SUPPLIER,
            'currency_code' => 'GHS',
        ]);

        $id = $this->resolver->resolve(supplier: $bareSupplier);
        $this->assertSame($this->sundryFallback->id, $id);
    }

    public function test_throws_when_sundry_fallback_missing(): void
    {
        // Remove the fallback
        $this->sundryFallback->delete();

        $bareSupplier = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'Bare', 'code' => 'V-X',
            'type' => Counterparty::TYPE_SUPPLIER,
            'currency_code' => 'GHS',
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches("/COA fallback code '6990'/");

        $this->resolver->resolve(supplier: $bareSupplier);
    }

    /* helper */
    protected function makeAccount(string $code, string $name, string $type): Account
    {
        return Account::firstOrCreate([
            'organization_id' => $this->org->id,
            'code' => $code,
        ], [
            'name' => $name, 'account_type' => $type,
            'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => true,
        ]);
    }
}
