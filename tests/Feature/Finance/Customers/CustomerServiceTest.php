<?php

namespace Tests\Feature\Finance\Customers;

use App\Models\Counterparty;
use App\Models\CounterpartyAddress;
use App\Models\CounterpartyContact;
use App\Models\CustomerCategory;
use App\Models\Organization;
use App\Services\Finance\Customers\CustomerService;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerServiceTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create(['slug' => 'cf', 'name' => 'CF']);
        $this->app->make(CurrentOrganization::class)->bind($this->org);
    }

    public function test_create_customer_with_minimal_fields(): void
    {
        $service = $this->app->make(CustomerService::class);

        $cp = $service->createCustomer([
            'name'          => 'Test Butcher',
            'currency_code' => 'GHS',
        ]);

        $this->assertNotNull($cp->id);
        $this->assertSame('Test Butcher', $cp->name);
        $this->assertSame('GHS', $cp->currency_code);
        $this->assertSame(Counterparty::TYPE_CUSTOMER, $cp->type);
        $this->assertNotEmpty($cp->code, 'Code should auto-generate when blank');
        $this->assertStringStartsWith('CUST-', $cp->code);
    }

    public function test_create_customer_preserves_explicit_code(): void
    {
        $service = $this->app->make(CustomerService::class);

        $cp = $service->createCustomer([
            'name'          => 'Test',
            'code'          => 'CUST-MANUAL-001',
            'currency_code' => 'GHS',
        ]);

        $this->assertSame('CUST-MANUAL-001', $cp->code);
    }

    public function test_create_customer_with_inline_addresses_and_contacts(): void
    {
        $service = $this->app->make(CustomerService::class);

        $cp = $service->createCustomer(
            data: ['name' => 'Test Hospital', 'currency_code' => 'GHS'],
            addresses: [
                ['address_type' => 'billing', 'address_line_1' => '123 Main St', 'is_default' => true],
                ['address_type' => 'shipping', 'address_line_1' => '456 Loading Bay', 'is_default' => true],
            ],
            contacts: [
                ['name' => 'Procurement Officer', 'phone' => '0244111222', 'is_primary' => true],
            ],
        );

        $this->assertSame(2, $cp->addresses->count());
        $this->assertSame(1, $cp->contacts->count());
        $this->assertTrue($cp->addresses->first()->is_default);
    }

    public function test_setting_new_default_address_clears_others(): void
    {
        $service = $this->app->make(CustomerService::class);

        $cp = $service->createCustomer(
            data: ['name' => 'Test', 'currency_code' => 'GHS'],
            addresses: [
                ['address_type' => 'billing', 'address_line_1' => 'First', 'is_default' => true],
            ],
        );

        $service->addAddress($cp, [
            'address_type'   => 'billing',
            'address_line_1' => 'Second',
            'is_default'     => true,
        ]);

        $cp->refresh();
        $defaults = $cp->addresses()->where('is_default', true)->where('address_type', 'billing')->get();
        $this->assertSame(1, $defaults->count(), 'Only one default per type allowed');
        $this->assertSame('Second', $defaults->first()->address_line_1);
    }

    public function test_update_customer_does_not_change_code(): void
    {
        $service = $this->app->make(CustomerService::class);
        $cp = $service->createCustomer(['name' => 'Original', 'currency_code' => 'GHS']);
        $originalCode = $cp->code;

        $updated = $service->updateCustomer($cp, [
            'name'          => 'Renamed',
            'code'          => 'TRYING-TO-CHANGE',  // Should be ignored
            'currency_code' => 'GHS',
        ]);

        $this->assertSame('Renamed', $updated->name);
        $this->assertSame($originalCode, $updated->code);
    }

    public function test_place_on_credit_hold(): void
    {
        $service = $this->app->make(CustomerService::class);
        $cp = $service->createCustomer(['name' => 'Risky', 'currency_code' => 'GHS']);

        $this->assertFalse($cp->on_credit_hold);

        $held = $service->placeOnCreditHold($cp, 'Multiple invoices past due');

        $this->assertTrue($held->on_credit_hold);
        $this->assertSame('Multiple invoices past due', $held->credit_hold_reason);
    }

    public function test_clear_credit_hold(): void
    {
        $service = $this->app->make(CustomerService::class);
        $cp = $service->createCustomer(['name' => 'Risky', 'currency_code' => 'GHS']);
        $cp = $service->placeOnCreditHold($cp, 'Test reason');

        $cleared = $service->clearCreditHold($cp);

        $this->assertFalse($cleared->on_credit_hold);
        $this->assertNull($cleared->credit_hold_reason);
    }

    public function test_setting_new_primary_contact_demotes_others(): void
    {
        $service = $this->app->make(CustomerService::class);
        $cp = $service->createCustomer(['name' => 'Test', 'currency_code' => 'GHS']);

        $service->addContact($cp, ['name' => 'Alice', 'is_primary' => true]);
        $service->addContact($cp, ['name' => 'Bob', 'is_primary' => true]);

        $primaries = $cp->contacts()->where('is_primary', true)->get();
        $this->assertSame(1, $primaries->count());
        $this->assertSame('Bob', $primaries->first()->name);
    }
}
