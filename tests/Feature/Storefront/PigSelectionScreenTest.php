<?php

namespace Tests\Feature\Storefront;

use App\Models\Permission;
use App\Models\PigManagement;
use App\Models\PigSale;
use App\Models\Role;
use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontOrder;
use App\Models\Storefront\StorefrontOrderItem;
use App\Models\Storefront\StorefrontOrderItemPigAllocation;
use App\Models\User;
use App\Storefront\Services\StorefrontPigPoolService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The approval screen, over HTTP.
 *
 * The service tests prove the rules. This proves a person can reach them: that
 * the panel appears on the order the approver actually opens, that the form
 * posts, and — the part that matters most — that the permission is enforced by
 * the route rather than by whether the panel happened to render.
 *
 * A screen that hides a button is not a control. Anyone can post the form.
 */
class PigSelectionScreenTest extends StorefrontTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedStorefrontPermissions();
        $this->seedStorefrontOrderPermissions();
    }

    /** @return array{0: StorefrontOrder, 1: StorefrontOrderItem, 2: list<PigManagement>} */
    protected function pooledOrder(int $poolSize = 4, int $quantity = 2): array
    {
        $listing = $this->makePublishedListing([
            'kind'               => StorefrontListing::KIND_LIVE_PIG,
            'title'              => 'Market-ready live pigs',
            'pricing_basis'      => StorefrontListing::BASIS_FIXED,
            'fixed_price'        => '900.00',
            'quantity_available' => '0',
        ]);

        $pigs = [];
        for ($i = 0; $i < $poolSize; $i++) {
            $pigs[] = PigManagement::create([
                'organization_id' => $this->org->id,
                'ear_tag_number'  => 'SCR-' . strtoupper(uniqid()),
                'breed'           => 'Duroc',
                'gender'          => 'male',
                'stock_type'      => 'standard',
                'date_of_birth'   => now()->subMonths(8)->toDateString(),
                'code'            => 'PIG-' . uniqid(),
            ]);
        }

        $this->app->make(StorefrontPigPoolService::class)
            ->attachPigs($listing, array_column($pigs, 'id'), $this->makeUser('admin'));

        $order = StorefrontOrder::create([
            'organization_id'   => $this->org->id,
            'reference'         => 'SO-SCR-' . strtoupper(uniqid()),
            'status'            => StorefrontOrder::STATUS_PENDING_CONFIRMATION,
            'payment_status'    => StorefrontOrder::PAY_COD,
            'payment_method'    => StorefrontOrder::METHOD_COD,
            'fulfilment_method' => StorefrontOrder::FULFIL_PICKUP,
            'subtotal' => (900 * $quantity) . '.00', 'delivery_fee' => '0.00',
            'total' => (900 * $quantity) . '.00',
            'currency_code' => 'GHS', 'placed_at' => now(),
        ]);

        $item = StorefrontOrderItem::create([
            'organization_id' => $this->org->id,
            'order_id'        => $order->id,
            'listing_id'      => $listing->id,
            'kind'            => StorefrontListing::KIND_LIVE_PIG,
            'description'     => 'Market-ready live pigs',
            'quantity'        => (string) $quantity,
            'quantity_unit'   => 'head',
            'price_basis'     => 'fixed',
            'unit_price'      => '900.00',
            'line_total'      => (900 * $quantity) . '.00',
        ]);

        return [$order, $item, $pigs];
    }

    protected function userWithOnlyPermission(string $permission): User
    {
        static $sequence = 0;
        $role = Role::firstOrCreate(['name' => 'sel-viewer-' . (++$sequence)]);
        $role->permissions()->syncWithoutDetaching(
            Permission::where('name', $permission)->pluck('id')->all()
        );

        return User::forceCreate([
            'name'              => 'Viewer ' . $sequence,
            'email'             => "sel-viewer-{$sequence}@example.com",
            'password'          => bcrypt('secret'),
            'role'              => $role->name,
            'role_id'           => $role->id,
            'organization_id'   => $this->org->id,
            'active_status'     => true,
            'email_verified_at' => now(),
        ]);
    }

    /* ================================================================== */

    public function test_the_order_page_asks_the_approver_to_choose_the_pigs(): void
    {
        [$order] = $this->pooledOrder(poolSize: 4, quantity: 2);

        $this->actingAs($this->makeUser('admin'))
            ->get(route('storefront.admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Choose the pigs for')
            ->assertSee('Ordered')
            ->assertSee('Selected so far');
    }

    public function test_the_pool_animals_are_listed_with_enough_detail_to_choose_between_them(): void
    {
        [$order, , $pigs] = $this->pooledOrder(poolSize: 3, quantity: 1);

        $response = $this->actingAs($this->makeUser('admin'))
            ->get(route('storefront.admin.orders.show', $order))
            ->assertOk();

        // An approver picking livestock needs to tell the animals apart.
        $response->assertSee($pigs[0]->ear_tag_number);
        $response->assertSee('Duroc');
    }

    public function test_selecting_the_pigs_over_http_records_the_choice(): void
    {
        [$order, $item, $pigs] = $this->pooledOrder(poolSize: 4, quantity: 2);

        $this->actingAs($this->makeUser('admin'))
            ->post(route('storefront.admin.orders.select-pigs', [$order, $item]), [
                'pig_ids' => [$pigs[0]->id, $pigs[1]->id],
            ])
            ->assertRedirect();

        $this->assertSame(
            2,
            StorefrontOrderItemPigAllocation::where('storefront_order_item_id', $item->id)
                ->whereIn('status', StorefrontOrderItemPigAllocation::ACTIVE_STATUSES)
                ->count(),
        );

        // Selecting sells nothing.
        $this->assertSame(0, PigSale::count());
    }

    public function test_the_wrong_number_of_pigs_is_refused_with_a_readable_message(): void
    {
        [$order, $item, $pigs] = $this->pooledOrder(poolSize: 4, quantity: 2);

        $this->actingAs($this->makeUser('admin'))
            ->post(route('storefront.admin.orders.select-pigs', [$order, $item]), [
                'pig_ids' => [$pigs[0]->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, StorefrontOrderItemPigAllocation::count());
    }

    /**
     * The control that matters: the route enforces the permission.
     *
     * Hiding the form from a viewer is presentation. Anyone can post it.
     */
    public function test_choosing_pigs_requires_permission_to_manage_orders(): void
    {
        [$order, $item, $pigs] = $this->pooledOrder(poolSize: 4, quantity: 2);

        $viewer = $this->userWithOnlyPermission('view_storefront_orders');

        // They can read the order and see what it needs...
        $this->actingAs($viewer)
            ->get(route('storefront.admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Choose the pigs for')
            ->assertDontSee('Save selection');

        $this->flushSession();

        // ...and cannot act on it.
        $this->actingAs($viewer)
            ->post(route('storefront.admin.orders.select-pigs', [$order, $item]), [
                'pig_ids' => [$pigs[0]->id, $pigs[1]->id],
            ])
            ->assertForbidden();

        $this->assertSame(0, StorefrontOrderItemPigAllocation::count());
    }

    /**
     * Another farm's order is indistinguishable from one that never existed
     * (IMP-112).
     */
    public function test_another_organisations_order_cannot_have_pigs_selected(): void
    {
        [, $item, $pigs] = $this->pooledOrder(poolSize: 2, quantity: 1);

        $other = \App\Models\Organization::create(['slug' => 'other-sel-farm', 'name' => 'Other Farm']);

        $foreign = StorefrontOrder::create([
            'organization_id'   => $other->id,
            'reference'         => 'SO-FOREIGN-SEL',
            'status'            => StorefrontOrder::STATUS_PENDING_CONFIRMATION,
            'payment_status'    => StorefrontOrder::PAY_COD,
            'payment_method'    => StorefrontOrder::METHOD_COD,
            'fulfilment_method' => StorefrontOrder::FULFIL_PICKUP,
            'subtotal' => '900.00', 'delivery_fee' => '0.00', 'total' => '900.00',
            'currency_code' => 'GHS', 'placed_at' => now(),
        ]);

        $this->actingAs($this->makeUser('admin'))
            ->post(route('storefront.admin.orders.select-pigs', [$foreign, $item]), [
                'pig_ids' => [$pigs[0]->id],
            ])
            ->assertNotFound();
    }

    /**
     * A product order shows none of this.
     */
    public function test_an_order_without_pooled_pigs_shows_no_selection_panel(): void
    {
        $listing = $this->makePublishedListing([
            'kind'               => StorefrontListing::KIND_FEED,
            'pricing_basis'      => StorefrontListing::BASIS_FIXED,
            'fixed_price'        => '50.00',
            'quantity_available' => '10',
        ]);

        $order = StorefrontOrder::create([
            'organization_id'   => $this->org->id,
            'reference'         => 'SO-FEED-SEL',
            'status'            => StorefrontOrder::STATUS_PENDING_CONFIRMATION,
            'payment_status'    => StorefrontOrder::PAY_COD,
            'payment_method'    => StorefrontOrder::METHOD_COD,
            'fulfilment_method' => StorefrontOrder::FULFIL_PICKUP,
            'subtotal' => '50.00', 'delivery_fee' => '0.00', 'total' => '50.00',
            'currency_code' => 'GHS', 'placed_at' => now(),
        ]);

        StorefrontOrderItem::create([
            'organization_id' => $this->org->id,
            'order_id'        => $order->id,
            'listing_id'      => $listing->id,
            'kind'            => StorefrontListing::KIND_FEED,
            'description'     => 'A bag of feed',
            'quantity'        => '1',
            'quantity_unit'   => 'bag',
            'price_basis'     => 'fixed',
            'unit_price'      => '50.00',
            'line_total'      => '50.00',
        ]);

        $this->actingAs($this->makeUser('admin'))
            ->get(route('storefront.admin.orders.show', $order))
            ->assertOk()
            ->assertDontSee('Choose the pigs for');
    }
}
