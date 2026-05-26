<?php

namespace Tests\Feature\Finance\Resolvers;

use App\Finance\Resolvers\CounterpartyResolver;
use App\Models\Counterparty;
use App\Models\Organization;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CounterpartyResolverTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create(['slug' => 'cf', 'name' => 'CF']);
        $this->app->make(CurrentOrganization::class)->bind($this->org);
    }

    public function test_returns_explicit_customer_when_id_set(): void
    {
        $cust = Counterparty::create([
            'organization_id' => $this->org->id,
            'type' => 'customer',
            'code' => 'CUST-001',
            'name' => 'Real Customer',
            'is_active' => true,
        ]);

        $result = $this->app->make(CounterpartyResolver::class)->forPigSale($cust->id);

        $this->assertSame($cust->id, $result->id);
    }

    public function test_falls_back_to_walkin_when_id_null(): void
    {
        $resolver = $this->app->make(CounterpartyResolver::class);

        $result = $resolver->forPigSale(null);

        $this->assertSame('WALKIN-CUST', $result->code);
        $this->assertSame('Walk-in Customer', $result->name);
    }

    public function test_falls_back_to_walkin_when_id_invalid(): void
    {
        $resolver = $this->app->make(CounterpartyResolver::class);

        $result = $resolver->forPigSale(999999);

        $this->assertSame('WALKIN-CUST', $result->code);
    }

    public function test_falls_back_to_walkin_when_explicit_inactive(): void
    {
        $cust = Counterparty::create([
            'organization_id' => $this->org->id,
            'type' => 'customer',
            'code' => 'CUST-INACTIVE',
            'name' => 'Inactive',
            'is_active' => false,
        ]);

        $result = $this->app->make(CounterpartyResolver::class)->forPigSale($cust->id);

        $this->assertSame('WALKIN-CUST', $result->code);
    }

    public function test_walkin_is_idempotent(): void
    {
        $resolver = $this->app->make(CounterpartyResolver::class);

        $first  = $resolver->forPigSale(null);
        $second = $resolver->forPigSale(null);

        $this->assertSame($first->id, $second->id);
    }

    public function test_for_purchase_returns_walkin_supplier(): void
    {
        $resolver = $this->app->make(CounterpartyResolver::class);

        $result = $resolver->forPurchase(null);

        $this->assertSame('WALKIN-SUPP', $result->code);
        $this->assertSame('supplier', $result->type);
    }
}
