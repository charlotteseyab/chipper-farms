<?php

namespace Tests\Feature\Storefront;

use App\Mail\TemplatedEmail;
use App\Models\MedicationAdministration;
use App\Models\PigManagement;
use App\Models\PigSale;
use App\Models\PigWeight;
use App\Models\Setting;
use App\Models\Storefront\StorefrontInventoryHold;
use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontListingPig;
use App\Models\Storefront\StorefrontOrder;
use App\Models\Storefront\StorefrontOrderPayment;
use App\Models\User;
use App\Storefront\Payments\PaystackClient;
use App\Storefront\Services\CheckoutService;
use App\Storefront\Services\PaystackService;
use App\Storefront\Services\StorefrontPigPoolService;
use App\Storefront\Services\StorefrontPigPricingService as Pricing;
use App\Storefront\Services\StorefrontPurchaseLineService;
use App\Storefront\Support\CartOwner;
use App\Storefront\Support\CheckoutData;
use App\Storefront\Support\OnlineShopActor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Storefront\Support\FakePaystackClient;

/**
 * A buyer who has paid is not asked to wait while the farm decides whether to
 * take their order.
 *
 * The stock is held and the exact animals committed at checkout, so a verified
 * payment accepts the order. It is left for staff only when a person still has
 * work to do — choosing the animals for a carcass order, or resolving an order
 * that could not be confirmed — and in either case the stock stays the buyer's.
 */
class PaidOrderAcceptanceTest extends StorefrontTestCase
{
    use RefreshDatabase;

    protected StorefrontListing $listing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedStorefrontPermissions();

        Setting::set('sales.pricing.live_weight_price', 30);
        Setting::set('sales.pricing.carcass_weight_price', 50);

        $this->app->instance(PaystackClient::class, new FakePaystackClient());

        $this->listing = $this->makePublishedListing([
            'kind'               => StorefrontListing::KIND_LIVE_PIG,
            'source_category'    => Pricing::CATEGORY_STANDARD,
            'title'              => 'Market-ready pigs',
            'pricing_basis'      => StorefrontListing::BASIS_FIXED,
            'fixed_price'        => '0.00',
            'quantity_available' => '0',
            'pickup_allowed'     => true,
        ]);

        $ids = [];
        foreach ([100, 120] as $i => $kg) {
            $pig = PigManagement::create([
                'organization_id' => $this->org->id,
                'ear_tag_number'  => 'PA-' . $i . '-' . strtoupper(uniqid()),
                'breed'           => 'Landrace',
                'gender'          => 'female',
                'stock_type'      => 'standard',
                'date_of_birth'   => now()->subMonths(8)->toDateString(),
                'code'            => 'PIG-' . uniqid(),
            ]);
            PigWeight::create(['pig_id' => $pig->id, 'weight' => $kg, 'weigh_date' => now()->toDateString()]);
            $ids[] = $pig->id;
        }

        $this->app->make(StorefrontPigPoolService::class)
            ->attachPigs($this->listing, $ids, $this->makeUser('admin'));
        $this->listing->refresh();
    }

    protected function lines(): StorefrontPurchaseLineService
    {
        return $this->app->make(StorefrontPurchaseLineService::class);
    }

    protected function firstRow(): StorefrontListingPig
    {
        return StorefrontListingPig::where('storefront_listing_id', $this->listing->id)->orderBy('id')->firstOrFail();
    }

    protected function checkout(User $buyer): StorefrontOrder
    {
        return $this->app->make(CheckoutService::class)->checkout(
            $buyer,
            CheckoutData::fromArray(['payment_method' => 'paystack', 'fulfilment_method' => 'pickup']),
        );
    }

    protected function pay(StorefrontOrder $order): StorefrontOrder
    {
        $paystack = $this->app->make(PaystackService::class);
        $paystack->verify($paystack->initialise($order, 'https://app.test/cb')['reference']);

        return $order->fresh();
    }

    public function test_paying_for_a_chosen_pig_sells_it_as_the_online_shop(): void
    {
        $buyer = $this->makeUser('customer');
        $this->lines()->addLiveLines(CartOwner::from($buyer), $this->listing, [$this->firstRow()->id]);

        $order = $this->pay($this->checkout($buyer));

        $this->assertSame(StorefrontOrder::STATUS_CONFIRMED, $order->status);

        $sale = PigSale::where('pig_id', $this->firstRow()->pig_id)->firstOrFail();
        $this->assertSame(OnlineShopActor::NAME, $sale->sold_by_name);
    }

    public function test_the_online_shop_account_cannot_sign_in_or_receive_staff_alerts(): void
    {
        $actor = OnlineShopActor::for($this->org->id);

        $this->assertFalse($actor->active_status);
        $this->assertSame($actor->id, OnlineShopActor::for($this->org->id)->id, 'One account per farm, not one per order.');

        $this->post('/login', ['email' => $actor->email, 'password' => 'anything'])->assertSessionHasErrors();
        $this->assertGuest();
    }

    public function test_a_carcass_order_waits_for_staff_but_its_stock_no_longer_expires(): void
    {
        $buyer = $this->makeUser('customer');
        $this->lines()->addCarcassLine(CartOwner::from($buyer), $this->listing, '150');

        $order = $this->pay($this->checkout($buyer));

        // Staff choose which animals make up 150 kg; nobody can do that for them.
        $this->assertSame(StorefrontOrder::STATUS_PAYMENT_CONFIRMED, $order->status);
        $this->assertTrue($order->events()->where('note', 'like', '%Choose the animals%')->exists());

        $this->travel(3)->hours();
        $this->artisan('storefront:expire-holds')->assertSuccessful();

        $this->assertSame(
            StorefrontInventoryHold::STATUS_ACTIVE,
            StorefrontInventoryHold::where('order_id', $order->id)->firstOrFail()->status,
            'A paid order\'s stock went back on sale while it waited for staff.',
        );
    }

    public function test_a_paid_order_that_cannot_be_confirmed_stays_paid_for_staff_to_resolve(): void
    {
        $buyer = $this->makeUser('customer');
        $row = $this->firstRow();
        $this->lines()->addLiveLines(CartOwner::from($buyer), $this->listing, [$row->id]);
        $order = $this->checkout($buyer);

        // Treated while the buyer was on the payment page.
        MedicationAdministration::create([
            'pig_id' => $row->pig_id, 'administration_date' => now()->toDateString(),
            'medication_name' => 'Amoxicillin', 'dosage' => '5 ml', 'withdrawal_period_days' => 14,
        ]);

        $order = $this->pay($order);

        $this->assertSame(StorefrontOrder::STATUS_PAYMENT_CONFIRMED, $order->status);
        $this->assertSame(StorefrontOrderPayment::STATUS_VERIFIED, $order->payments()->firstOrFail()->status);
        $this->assertTrue($order->events()->where('note', 'like', '%could not be confirmed automatically%')->exists());
        $this->assertFalse(PigSale::where('pig_id', $row->pig_id)->exists());
    }

    public function test_the_buyer_gets_one_order_confirmed_email_not_two(): void
    {
        Mail::fake();

        $buyer = $this->makeUser('customer');
        $this->lines()->addLiveLines(CartOwner::from($buyer), $this->listing, [$this->firstRow()->id]);
        $this->pay($this->checkout($buyer));

        Mail::assertSent(TemplatedEmail::class, fn (TemplatedEmail $m) => str_contains($m->emailSubject, 'is confirmed'));
        Mail::assertNotSent(TemplatedEmail::class, fn (TemplatedEmail $m) => str_starts_with($m->emailSubject, 'Payment confirmed'));
    }
}
