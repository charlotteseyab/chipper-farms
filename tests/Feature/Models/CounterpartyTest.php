<?php

namespace Tests\Feature\Models;

use App\Models\Counterparty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CounterpartyTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_type_is_reported_correctly(): void
    {
        $c = Counterparty::create(['name' => 'ABC Market', 'type' => Counterparty::TYPE_CUSTOMER]);

        $this->assertTrue($c->isCustomer());
        $this->assertFalse($c->isSupplier());
    }

    public function test_supplier_type_is_reported_correctly(): void
    {
        $c = Counterparty::create(['name' => 'Feed Supplies Ltd', 'type' => Counterparty::TYPE_SUPPLIER]);

        $this->assertFalse($c->isCustomer());
        $this->assertTrue($c->isSupplier());
    }

    public function test_both_type_is_reported_as_both(): void
    {
        $c = Counterparty::create(['name' => 'Farm Co-op', 'type' => Counterparty::TYPE_BOTH]);

        $this->assertTrue($c->isCustomer());
        $this->assertTrue($c->isSupplier());
    }

    public function test_customers_scope_includes_customer_and_both(): void
    {
        Counterparty::create(['name' => 'Customer Only', 'type' => Counterparty::TYPE_CUSTOMER]);
        Counterparty::create(['name' => 'Supplier Only', 'type' => Counterparty::TYPE_SUPPLIER]);
        Counterparty::create(['name' => 'Both', 'type' => Counterparty::TYPE_BOTH]);

        $customers = Counterparty::customers()->get();

        $this->assertCount(2, $customers);
        $this->assertEqualsCanonicalizing(['Customer Only', 'Both'], $customers->pluck('name')->all());
    }

    public function test_suppliers_scope_includes_supplier_and_both(): void
    {
        Counterparty::create(['name' => 'Customer Only', 'type' => Counterparty::TYPE_CUSTOMER]);
        Counterparty::create(['name' => 'Supplier Only', 'type' => Counterparty::TYPE_SUPPLIER]);
        Counterparty::create(['name' => 'Both', 'type' => Counterparty::TYPE_BOTH]);

        $suppliers = Counterparty::suppliers()->get();

        $this->assertCount(2, $suppliers);
        $this->assertEqualsCanonicalizing(['Supplier Only', 'Both'], $suppliers->pluck('name')->all());
    }

    public function test_inactive_excluded_from_active_scope(): void
    {
        Counterparty::create(['name' => 'Active', 'type' => 'both', 'is_active' => true]);
        Counterparty::create(['name' => 'Inactive', 'type' => 'both', 'is_active' => false]);

        $this->assertCount(1, Counterparty::active()->get());
    }

    public function test_soft_delete_works(): void
    {
        $c = Counterparty::create(['name' => 'To Delete', 'type' => 'both']);
        $c->delete();

        $this->assertSoftDeleted('counterparties', ['id' => $c->id]);
        $this->assertNull(Counterparty::find($c->id));
        $this->assertNotNull(Counterparty::withTrashed()->find($c->id));
    }
}