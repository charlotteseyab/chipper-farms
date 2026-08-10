<?php

namespace Tests\Feature\Finance\SalesReturns;

use App\Models\FeedSale;
use App\Models\FeedType;
use App\Models\Organization;
use App\Models\PigManagement;
use App\Models\PigSale;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SalesReturn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

/**
 * The return-request screen (IMP-098, blueprint §43 step 1).
 *
 * §43's lifecycle begins "user requests return/refund with reason and
 * quantity". Every step after it was built; the first had no surface, so a farm
 * could not record that a customer had brought feed back.
 *
 * The tests are shaped around the two rules the screen must not get wrong:
 * §45's indivisible pig return, and the organisation boundary — a posted sale
 * id from another farm would reverse their revenue, not this farm's.
 */
class SalesReturnScreenTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();
        $this->seed(\Database\Seeders\SalesReturnPermissionsSeeder::class);
    }

    protected function feedSale(array $overrides = []): FeedSale
    {
        return FeedSale::create(array_merge([
            'organization_id' => $this->org->id,
            'feed_type_id'    => FeedType::create(['name' => 'Grower Feed'])->id,
            'quantity_kg'     => '10',
            'unit_price'      => '12.00',
            'sale_total'      => '120.00',
            'sale_date'       => '2026-05-15',
            'channel'         => 'direct',
            'payment_status'  => FeedSale::PAYMENT_PAID,
            'sold_by_user_id' => $this->user->id,
        ], $overrides));
    }

    protected function pigSale(): PigSale
    {
        $pig = PigManagement::create([
            'organization_id' => $this->org->id,
            'ear_tag_number'  => 'TAG-' . uniqid(),
            'breed'           => 'Large White',
            'gender'          => 'female',
            'stock_type'      => 'standard',
            'date_of_birth'   => '2025-06-01',
            'code'            => 'PIG-' . uniqid(),
        ]);

        return PigSale::create([
            'organization_id' => $this->org->id,
            'pig_id'          => $pig->id,
            'sale_date'       => '2026-05-15',
            'sale_weight'     => '80',
            'price_per_kg'    => '10.00',
            'sale_price'      => '800.00',
            'sale_purpose'    => 'market',
            'payment_status'  => 'paid',
            'sold_by_user_id' => $this->user->id,
        ]);
    }

    /* ---------------------------------------------------------------- */
    /* Recording                                                        */
    /* ---------------------------------------------------------------- */

    public function test_a_feed_return_can_be_recorded(): void
    {
        $sale = $this->feedSale();

        $this->actingAs($this->userWith(['record_sales_returns']))
            ->post(route('finance.returns.store'), [
                'sale_type' => 'feed',
                'sale_id'   => $sale->id,
                'quantity'  => '4',
                'reason'    => 'Spoiled on arrival',
                'restock'   => '0',
            ])
            ->assertRedirect(route('finance.returns.index'));

        $return = SalesReturn::first();

        $this->assertNotNull($return);
        $this->assertSame('4.0000', bcadd((string) $return->quantity, '0', 4));
        $this->assertSame('Spoiled on arrival', $return->reason);
        $this->assertFalse((bool) $return->restock);
    }

    /**
     * §45: pig returns are indivisible. The form offers no quantity, and a
     * hand-posted one must not make it divisible either.
     */
    public function test_a_pig_return_is_always_one_whatever_is_posted(): void
    {
        $sale = $this->pigSale();

        $this->actingAs($this->userWith(['record_sales_returns']))
            ->post(route('finance.returns.store'), [
                'sale_type' => 'pig',
                'sale_id'   => $sale->id,
                // Ignored — a pig comes back whole.
                'quantity'  => '0.5',
                'reason'    => 'Lame on arrival',
            ])
            ->assertRedirect();

        $this->assertSame('1.0000', bcadd((string) SalesReturn::first()->quantity, '0', 4));
    }

    /**
     * §45: "staff must decide health/market state before relisting". A returned
     * animal must never go straight back on sale.
     */
    public function test_a_returned_pig_never_restocks_automatically(): void
    {
        $sale = $this->pigSale();

        $this->actingAs($this->userWith(['record_sales_returns']))
            ->post(route('finance.returns.store'), [
                'sale_type' => 'pig',
                'sale_id'   => $sale->id,
                'reason'    => 'Lame on arrival',
                // Even asked for explicitly.
                'restock'   => '1',
            ])
            ->assertRedirect();

        $this->assertFalse(
            (bool) SalesReturn::first()->restock,
            'A returned animal needs a staff health decision before it is sellable again.',
        );
    }

    /**
     * The service guards against cumulative over-return; the screen must
     * surface that refusal rather than swallowing it or crashing.
     */
    public function test_returning_more_than_was_sold_is_refused_with_the_reason(): void
    {
        $sale = $this->feedSale(['quantity_kg' => '10']);

        $this->actingAs($this->userWith(['record_sales_returns']))
            ->post(route('finance.returns.store'), [
                'sale_type' => 'feed',
                'sale_id'   => $sale->id,
                'quantity'  => '15',
                'reason'    => 'Too much',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, SalesReturn::count());
    }

    public function test_a_reason_is_required(): void
    {
        $sale = $this->feedSale();

        $this->actingAs($this->userWith(['record_sales_returns']))
            ->post(route('finance.returns.store'), [
                'sale_type' => 'feed',
                'sale_id'   => $sale->id,
                'quantity'  => '1',
            ])
            ->assertSessionHasErrors('reason');

        $this->assertSame(0, SalesReturn::count());
    }

    /* ---------------------------------------------------------------- */
    /* Boundaries                                                       */
    /* ---------------------------------------------------------------- */

    /**
     * A posted sale id from another farm would reverse THEIR revenue.
     */
    public function test_another_organisations_sale_cannot_be_returned(): void
    {
        $other = Organization::create(['slug' => 'other-returns', 'name' => 'Other Farm']);

        $foreign = FeedSale::create([
            'organization_id' => $other->id,
            'feed_type_id'    => FeedType::create(['name' => 'Their Feed'])->id,
            'quantity_kg'     => '10', 'unit_price' => '12.00', 'sale_total' => '120.00',
            'sale_date' => '2026-05-15', 'channel' => 'direct',
            'payment_status' => FeedSale::PAYMENT_PAID,
            'sold_by_user_id' => $this->user->id,
        ]);

        $this->actingAs($this->userWith(['record_sales_returns']))
            ->post(route('finance.returns.store'), [
                'sale_type' => 'feed',
                'sale_id'   => $foreign->id,
                'quantity'  => '1',
                'reason'    => 'Not mine to return',
            ])
            ->assertNotFound();

        $this->assertSame(0, SalesReturn::count());
    }

    public function test_recording_a_return_requires_the_permission(): void
    {
        $sale = $this->feedSale();

        $this->actingAs($this->userWith(['view_finance']))
            ->post(route('finance.returns.store'), [
                'sale_type' => 'feed',
                'sale_id'   => $sale->id,
                'quantity'  => '1',
                'reason'    => 'Spoiled',
            ])
            ->assertForbidden();

        $this->assertSame(0, SalesReturn::count());
    }

    public function test_the_screen_opens(): void
    {
        $this->feedSale();

        $this->actingAs($this->userWith(['record_sales_returns']))
            ->get(route('finance.returns.index'))
            ->assertOk()
            ->assertSee('Record a return');
    }

    /* ---------------------------------------------------------------- */

    /** @param  list<string>  $permissions */
    protected function userWith(array $permissions): User
    {
        static $sequence = 0;
        $role = Role::firstOrCreate(['name' => 'returns-tester-' . (++$sequence)]);

        foreach ($permissions as $name) {
            $permission = Permission::firstOrCreate(
                ['name' => $name],
                ['name' => $name, 'description' => $name, 'module' => 'finance'],
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        return User::forceCreate([
            'name'              => 'Returns tester ' . $sequence,
            'email'             => "returns-{$sequence}@example.com",
            'password'          => bcrypt('secret'),
            'role'              => $role->name,
            'role_id'           => $role->id,
            'organization_id'   => $this->org->id,
            'active_status'     => true,
            'email_verified_at' => now(),
        ]);
    }
}
