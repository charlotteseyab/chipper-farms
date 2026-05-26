<?php

namespace Tests\Feature\Finance\Customers;

use App\Models\Counterparty;
use App\Models\CustomerCategory;
use App\Models\Organization;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerCategoryFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create(['slug' => 'cf', 'name' => 'CF']);
        $this->app->make(CurrentOrganization::class)->bind($this->org);
    }

    public function test_customer_with_explicit_discount_overrides_category(): void
    {
        $cat = CustomerCategory::create([
            'organization_id' => $this->org->id,
            'code' => 'WHOLE',
            'name' => 'Wholesale',
            'default_discount_percent' => 5.00,
            'default_payment_terms_days' => 30,
            'default_credit_limit' => 50000,
        ]);

        $cp = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'Test',
            'type' => 'customer',
            'currency_code' => 'GHS',
            'customer_category_id' => $cat->id,
            'discount_percent' => 7.50,  // override
            'payment_terms_days' => 45,
            'credit_limit' => 75000,
        ]);

        $this->assertSame('7.50', $cp->effectiveDiscountPercent());
        $this->assertSame(45, $cp->effectivePaymentTermsDays());
        $this->assertSame('75000.00', $cp->effectiveCreditLimit());
    }

    public function test_customer_falls_back_to_category_when_blank(): void
    {
        $cat = CustomerCategory::create([
            'organization_id' => $this->org->id,
            'code' => 'WHOLE',
            'name' => 'Wholesale',
            'default_discount_percent' => 5.00,
            'default_payment_terms_days' => 30,
            'default_credit_limit' => 50000,
        ]);

        $cp = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'Test',
            'type' => 'customer',
            'currency_code' => 'GHS',
            'customer_category_id' => $cat->id,
            'discount_percent' => 0,  // No override → category default
        ]);

        $cp->load('category');

        $this->assertSame('5.00', $cp->effectiveDiscountPercent());
        $this->assertSame(30, $cp->effectivePaymentTermsDays());
        $this->assertSame('50000.00', $cp->effectiveCreditLimit());
    }

    public function test_no_category_returns_zero_or_null(): void
    {
        $cp = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'Test',
            'type' => 'customer',
            'currency_code' => 'GHS',
            'customer_category_id' => null,
            'discount_percent' => 0,
        ]);

        $this->assertSame('0', $cp->effectiveDiscountPercent());
        $this->assertNull($cp->effectivePaymentTermsDays());
        $this->assertNull($cp->effectiveCreditLimit());
    }
}
