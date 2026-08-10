<?php

namespace Tests\Concurrency;

/**
 * True multi-process evidence that checkout holds cannot oversell
 * (Phase 10, blueprint §37).
 *
 * WHY THIS EXISTS ALONGSIDE OrderConfirmationConcurrencyTest
 * -----------------------------------------------------------
 * That file proves the *confirmation* layer cannot oversell. It has always been
 * the last line of defence and it still is. But it protects the farm's
 * inventory, not the buyer's money: by the time confirmation rejects an order,
 * the losing customer has already been sent to Paystack and may already have
 * paid. §37 states the problem exactly — "this prevents a customer paying for
 * stock that another customer bought in the same window".
 *
 * Holds move the refusal earlier, to checkout, before anyone is sent to pay.
 * This file proves that earlier refusal holds under real contention.
 *
 * SEQUENTIAL TESTS CANNOT PROVE THIS
 * ----------------------------------
 * `HoldServiceTest` asserts the same outcomes in-process and would pass against
 * an unlocked read-then-write, because nothing ever interleaves. SQLite cannot
 * show row locking at all, and RefreshDatabase wraps each test in a transaction
 * that hides interleaving by construction. Hence separate processes against a
 * real server, released together.
 *
 * The assertions are about OUTCOMES, not mechanism: N units of stock must yield
 * at most N successful checkouts, and successes plus refusals must account for
 * every attempt. A test asserting "a lock was taken" would pass on an
 * implementation that locked the wrong row.
 */
class HoldConcurrencyTest extends ConcurrencyTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $migrate = $this->artisan('migrate', '--force');
        $this->assertSame(
            0,
            $migrate['exit'],
            "Could not migrate the concurrency database:\n" . $migrate['out'] . $migrate['err'],
        );
    }

    /**
     * Build a listing and $buyerCount buyers each holding a cart for it.
     *
     * The carts are built here rather than in the workers so that every worker
     * starts from the same committed state and the only thing racing is the
     * checkout itself.
     *
     * @return array{listing_id:int, buyer_ids:list<int>}
     */
    protected function seedBuyers(int $buyerCount, string $available, string $kind): array
    {
        $result = $this->runConcurrently(1, <<<PHP
    \$org = App\\Models\\Organization::firstOrCreate(
        ['slug' => 'conc-holds'],
        ['name' => 'Concurrency Holds', 'is_active' => true],
    );
    app(App\\Support\\CurrentOrganization::class)->bind(\$org);

    \$listing = App\\Models\\Storefront\\StorefrontListing::create([
        'organization_id' => \$org->id,
        'reference' => 'LIST-' . strtoupper(substr(md5(uniqid()), 0, 8)),
        'title' => 'Contended Item',
        'slug' => 'hold-conc-' . uniqid(),
        'kind' => '{$kind}',
        'sale_mode' => 'fixed',
        'pricing_basis' => 'fixed',
        'fixed_price' => '100.00',
        'quantity_available' => '{$available}',
        'status' => 'published',
        'published_at' => now(),
    ]);

    \$buyerIds = [];
    for (\$i = 0; \$i < {$buyerCount}; \$i++) {
        \$buyer = App\\Models\\User::forceCreate([
            'name' => 'Hold Buyer ' . \$i,
            'email' => 'holdbuyer_' . \$i . '_' . uniqid() . '@example.com',
            'password' => bcrypt('x'),
            'role' => 'customer',
            'role_id' => App\\Models\\Role::firstOrCreate(['name' => 'customer'])->id,
            'organization_id' => \$org->id,
            'active_status' => true,
        ]);

        // Straight to the cart row: CartService::addItem() consults
        // availableToSell(), which would refuse the second buyer here and
        // remove the very race under test. That advisory refusal is proved
        // in HoldServiceTest; what this file needs is several buyers arriving
        // at checkout together, which is exactly what happens when they all
        // filled their baskets before anyone checked out.
        \$cart = App\\Models\\Storefront\\StorefrontCart::create([
            'organization_id' => \$org->id,
            'user_id' => \$buyer->id,
            'status' => 'active',
        ]);
        App\\Models\\Storefront\\StorefrontCartItem::create([
            'organization_id' => \$org->id,
            'cart_id' => \$cart->id,
            'listing_id' => \$listing->id,
            'quantity' => '1',
            'price_snapshot' => '100.00',
            'price_basis' => 'fixed',
        ]);

        \$buyerIds[] = \$buyer->id;
    }

    echo json_encode(['listing_id' => \$listing->id, 'buyer_ids' => \$buyerIds]);
PHP);

        $this->assertSame(
            0,
            $result[0]['exit'],
            'Seeding the hold concurrency fixture failed: ' . ($result[0]['err'] ?? ''),
        );

        $decoded = json_decode($result[0]['out'], true);
        $this->assertIsArray($decoded, 'Fixture seeding returned unparseable output: ' . $result[0]['out']);

        return $decoded;
    }

    /**
     * One animal, four buyers checking out at the same instant.
     *
     * Exactly one may reach the payment page. The other three must be refused
     * at checkout — before any of them is sent to Paystack.
     */
    public function test_concurrent_checkouts_cannot_both_hold_the_last_animal(): void
    {
        $buyerCount = 4;
        $fixture    = $this->seedBuyers($buyerCount, '1', 'live_pig');
        $buyerJson  = json_encode($fixture['buyer_ids']);

        $results = $this->runConcurrently($buyerCount, <<<PHP
    \$buyerIds = json_decode('{$buyerJson}', true);
    \$buyer = App\\Models\\User::find(\$buyerIds[\$workerId]);
    app(App\\Support\\CurrentOrganization::class)->bind(\$buyer->organization);

    try {
        app(App\\Storefront\\Services\\CheckoutService::class)->checkout(
            \$buyer,
            App\\Storefront\\Support\\CheckoutData::fromArray([
                'payment_method' => 'cod',
                'fulfilment_method' => 'pickup',
            ]),
        );
        echo 'PLACED';
    } catch (App\\Storefront\\Services\\Exceptions\\InventoryConflictException \$e) {
        echo 'REFUSED';
    } catch (App\\Storefront\\Services\\Exceptions\\CheckoutException \$e) {
        echo 'REFUSED';
    }
PHP, isolation: 'READ COMMITTED');

        [$placed, $refused] = $this->tally($results);

        $this->assertSame(
            1,
            $placed,
            "Expected exactly 1 checkout to succeed for 1 animal, got {$placed}. "
            . 'More than one means two customers were both sent to pay for the same animal.',
        );
        $this->assertSame($buyerCount - 1, $refused, 'Every losing buyer must be refused explicitly.');

        $this->assertSame(
            1,
            $this->liveHoldCount($fixture['listing_id']),
            'Exactly one live hold must exist for one animal.',
        );

        // And the hold took no stock: the column moves at confirmation, once.
        $this->assertSame('1.0000', $this->listingQuantity($fixture['listing_id']));
    }

    /**
     * The listing row lock itself, with nothing else able to serialise it.
     *
     * WHY THIS TEST EXISTS SEPARATELY FROM THE ONE ABOVE
     * ---------------------------------------------------
     * The end-to-end checkout test above passes even with `lockForUpdate()`
     * removed from `HoldService`. That was discovered by reintroducing the
     * defect and watching it stay green, and the reason is instructive:
     * `CheckoutService` allocates an order reference through `NumberingService`,
     * which takes `SELECT ... FOR UPDATE` on a per-organisation sequence row.
     * Every concurrent checkout for one farm therefore serialises on that row,
     * and each worker sees the previous hold already committed.
     *
     * That is real protection, but it is incidental. It would disappear the day
     * numbering changed, and a test that cannot fail when the lock it is named
     * after is deleted is not evidence of anything.
     *
     * So this test races `placeForOrder()` directly against pre-created orders.
     * No numbering, no other shared row: the listing lock is the only thing
     * between the workers and the same animal.
     *
     * TWELVE WORKERS, NOT FOUR, AND THE NUMBER IS LOAD-BEARING
     * --------------------------------------------------------
     * The unguarded window is between the availability read and the insert —
     * microseconds. With four workers the operating system staggers them enough
     * that one commits before the others read, and the unlocked version passes:
     * measured at 13–32ms per worker, exactly one hold, no oversell. That was
     * observed, not assumed.
     *
     * HOW GOOD IS THE NEGATIVE CONTROL? HONESTLY: PARTIAL.
     * ----------------------------------------------------
     * With `lockForUpdate()` removed, twelve workers DID produce two live holds
     * on one animal — a genuine oversell, observed, not theorised. So the lock
     * is protecting something real.
     *
     * But it did not reproduce on demand. Repeated runs of four rounds against
     * the unlocked code stayed green on an otherwise idle machine: the workers
     * that overlapped in the failing run were the slow ones (124ms and 159ms
     * against 12–17ms for the rest), which is to say the window opened because
     * the machine was busy. The unguarded gap between the availability read and
     * the insert is microseconds wide, and process scheduling usually separates
     * the workers by more than that.
     *
     * So this test is supporting evidence, not a deterministic control. What the
     * guarantee actually rests on is construction — the same `lockForUpdate()`
     * on the same rows in the same ascending order that
     * `OrderService::applyInventoryEffect()` has used since CF-P0-006, whose own
     * oversell was demonstrable. Four rounds are run rather than one because
     * more attempts catch more, and the limitation is recorded here rather than
     * tuned until the number looked convincing.
     */
    public function test_the_listing_lock_alone_prevents_a_double_hold(): void
    {
        $workers = 12;
        $rounds  = 4;

        for ($round = 0; $round < $rounds; $round++) {
            $this->assertOneHoldWins($workers, $round);
        }
    }

    /**
     * One round of the race: N workers, one animal, exactly one winner.
     */
    protected function assertOneHoldWins(int $workers, int $round): void
    {

        $result = $this->runConcurrently(1, <<<PHP
    \$org = App\\Models\\Organization::firstOrCreate(
        ['slug' => 'conc-hold-lock'],
        ['name' => 'Concurrency Hold Lock', 'is_active' => true],
    );
    app(App\\Support\\CurrentOrganization::class)->bind(\$org);

    \$listing = App\\Models\\Storefront\\StorefrontListing::create([
        'organization_id' => \$org->id,
        'reference' => 'LIST-LOCK-' . strtoupper(substr(md5(uniqid()), 0, 8)),
        'title' => 'Lock Test Animal',
        'slug' => 'lock-' . uniqid(),
        'kind' => 'live_pig',
        'sale_mode' => 'fixed',
        'pricing_basis' => 'fixed',
        'fixed_price' => '800.00',
        'quantity_available' => '1',
        'status' => 'published',
        'published_at' => now(),
    ]);

    // Orders built directly: the reference is supplied rather than allocated,
    // so NumberingService's sequence lock is out of the path entirely.
    \$orderIds = [];
    for (\$i = 0; \$i < {$workers}; \$i++) {
        \$order = App\\Models\\Storefront\\StorefrontOrder::create([
            'organization_id' => \$org->id,
            'reference' => 'SO-LOCK-' . uniqid() . '-' . \$i,
            'status' => 'pending_confirmation',
            'payment_status' => 'cash_on_delivery',
            'payment_method' => 'cod',
            'fulfilment_method' => 'pickup',
            'subtotal' => '800.00', 'delivery_fee' => '0.00', 'total' => '800.00',
            'currency_code' => 'GHS', 'placed_at' => now(),
        ]);

        App\\Models\\Storefront\\StorefrontOrderItem::create([
            'organization_id' => \$org->id,
            'order_id' => \$order->id,
            'listing_id' => \$listing->id,
            'kind' => 'live_pig',
            'description' => 'Lock Test Animal',
            'quantity' => '1',
            'price_basis' => 'fixed',
            'unit_price' => '800.00',
            'line_total' => '800.00',
        ]);

        \$orderIds[] = \$order->id;
    }

    echo json_encode(['listing_id' => \$listing->id, 'order_ids' => \$orderIds]);
PHP);

        $this->assertSame(0, $result[0]['exit'], 'Lock fixture failed: ' . ($result[0]['err'] ?? ''));

        $fixture   = json_decode($result[0]['out'], true);
        $orderJson = json_encode($fixture['order_ids']);

        $results = $this->runConcurrently($workers, <<<PHP
    \$orderIds = json_decode('{$orderJson}', true);
    \$order = App\\Models\\Storefront\\StorefrontOrder::find(\$orderIds[\$workerId]);
    app(App\\Support\\CurrentOrganization::class)->bind(\$order->organization);

    try {
        // The transaction the checkout path would have provided. Holds must be
        // placed inside one, so the guarantee is tested in the shape it ships.
        Illuminate\\Support\\Facades\\DB::transaction(function () use (\$order) {
            app(App\\Storefront\\Services\\HoldService::class)->placeForOrder(\$order);
        });
        echo 'PLACED';
    } catch (App\\Storefront\\Services\\Exceptions\\InventoryConflictException \$e) {
        echo 'REFUSED';
    }
PHP, isolation: 'READ COMMITTED');

        [$placed, $refused] = $this->tally($results);

        $this->assertSame(
            1,
            $placed,
            "Round {$round}: expected exactly 1 hold on 1 animal, got {$placed}. "
            . 'Without the row lock, several workers read "one available" before any of them writes.',
        );
        $this->assertSame($workers - 1, $refused, "Round {$round}: every loser must be refused explicitly.");
        $this->assertSame(
            1,
            $this->liveHoldCount($fixture['listing_id']),
            "Round {$round}: more than one live hold exists for one animal.",
        );
    }

    /**
     * Three units, five concurrent checkouts. Exactly three may proceed.
     *
     * The quantity case matters separately from the single-animal one: a hold
     * carries an amount, so an implementation that treated a listing as simply
     * "held or not" would let one buyer block the other four, or let all five
     * through.
     */
    public function test_concurrent_checkouts_cannot_oversell_a_quantity(): void
    {
        $buyerCount = 5;
        $available  = 3;

        $fixture   = $this->seedBuyers($buyerCount, (string) $available, 'feed');
        $buyerJson = json_encode($fixture['buyer_ids']);

        $results = $this->runConcurrently($buyerCount, <<<PHP
    \$buyerIds = json_decode('{$buyerJson}', true);
    \$buyer = App\\Models\\User::find(\$buyerIds[\$workerId]);
    app(App\\Support\\CurrentOrganization::class)->bind(\$buyer->organization);

    try {
        app(App\\Storefront\\Services\\CheckoutService::class)->checkout(
            \$buyer,
            App\\Storefront\\Support\\CheckoutData::fromArray([
                'payment_method' => 'cod',
                'fulfilment_method' => 'pickup',
            ]),
        );
        echo 'PLACED';
    } catch (App\\Storefront\\Services\\Exceptions\\InventoryConflictException \$e) {
        echo 'REFUSED';
    } catch (App\\Storefront\\Services\\Exceptions\\CheckoutException \$e) {
        echo 'REFUSED';
    }
PHP, isolation: 'READ COMMITTED');

        [$placed, $refused] = $this->tally($results);

        $this->assertSame(
            $available,
            $placed,
            "Expected exactly {$available} checkouts to succeed for {$available} units, got {$placed}.",
        );
        $this->assertSame($buyerCount - $available, $refused);

        $this->assertSame($available, $this->liveHoldCount($fixture['listing_id']));
    }

    /**
     * Holds and confirmations contend for the same rows. Both paths sort by
     * listing id before locking; if either stopped doing so, two transactions
     * over the same pair of listings would take them in opposite orders and
     * deadlock.
     *
     * Ten workers alternate between checking out and confirming over two shared
     * listings. A deadlock surfaces as a worker exiting non-zero, which the
     * tally asserts against.
     */
    public function test_holds_and_confirmations_do_not_deadlock_over_shared_listings(): void
    {
        $result = $this->runConcurrently(1, <<<'PHP'
    $org = App\Models\Organization::firstOrCreate(
        ['slug' => 'conc-holds-deadlock'],
        ['name' => 'Concurrency Deadlock', 'is_active' => true],
    );
    app(App\Support\CurrentOrganization::class)->bind($org);

    $ids = [];
    foreach (['A', 'B'] as $label) {
        $ids[] = App\Models\Storefront\StorefrontListing::create([
            'organization_id' => $org->id,
            'reference' => 'LIST-DL-' . $label . '-' . strtoupper(substr(md5(uniqid()), 0, 6)),
            'title' => 'Deadlock ' . $label,
            'slug' => 'deadlock-' . strtolower($label) . '-' . uniqid(),
            'kind' => 'feed',
            'sale_mode' => 'fixed',
            'pricing_basis' => 'fixed',
            'fixed_price' => '10.00',
            'quantity_available' => '100',
            'status' => 'published',
            'published_at' => now(),
        ])->id;
    }

    $buyerIds = [];
    for ($i = 0; $i < 10; $i++) {
        $buyer = App\Models\User::forceCreate([
            'name' => 'DL Buyer ' . $i,
            'email' => 'dlbuyer_' . $i . '_' . uniqid() . '@example.com',
            'password' => bcrypt('x'),
            'role' => 'customer',
            'role_id' => App\Models\Role::firstOrCreate(['name' => 'customer'])->id,
            'organization_id' => $org->id,
            'active_status' => true,
        ]);

        $cart = App\Models\Storefront\StorefrontCart::create([
            'organization_id' => $org->id, 'user_id' => $buyer->id, 'status' => 'active',
        ]);

        // Half the buyers add the two listings in one order, half in the other.
        // Opposite cart order is precisely the shape that deadlocks when lock
        // order follows the cart.
        $order = ($i % 2 === 0) ? $ids : array_reverse($ids);

        foreach ($order as $listingId) {
            App\Models\Storefront\StorefrontCartItem::create([
                'organization_id' => $org->id,
                'cart_id' => $cart->id,
                'listing_id' => $listingId,
                'quantity' => '1',
                'price_snapshot' => '10.00',
                'price_basis' => 'fixed',
            ]);
        }

        $buyerIds[] = $buyer->id;
    }

    echo json_encode(['buyer_ids' => $buyerIds, 'listing_ids' => $ids]);
PHP);

        $this->assertSame(0, $result[0]['exit'], 'Deadlock fixture failed: ' . ($result[0]['err'] ?? ''));

        $fixture   = json_decode($result[0]['out'], true);
        $buyerJson = json_encode($fixture['buyer_ids']);

        $results = $this->runConcurrently(10, <<<PHP
    \$buyerIds = json_decode('{$buyerJson}', true);
    \$buyer = App\\Models\\User::find(\$buyerIds[\$workerId]);
    app(App\\Support\\CurrentOrganization::class)->bind(\$buyer->organization);

    try {
        app(App\\Storefront\\Services\\CheckoutService::class)->checkout(
            \$buyer,
            App\\Storefront\\Support\\CheckoutData::fromArray([
                'payment_method' => 'cod',
                'fulfilment_method' => 'pickup',
            ]),
        );
        echo 'PLACED';
    } catch (App\\Storefront\\Services\\Exceptions\\InventoryConflictException \$e) {
        echo 'REFUSED';
    }
PHP, isolation: 'READ COMMITTED');

        foreach ($results as $i => $r) {
            $this->assertSame(
                0,
                $r['exit'],
                "Worker {$i} died rather than completing. A deadlock here means the lock "
                . "order stopped being ascending-by-listing-id in one of the paths:\n"
                . ($r['err'] ?? ''),
            );
            $this->assertSame(
                'PLACED',
                $r['out'],
                "Worker {$i} did not place its order; there is stock for all ten.",
            );
        }
    }

    /* ---------------------------------------------------------------- */

    /**
     * @param  array<int, array{exit:int, out:string}>  $results
     * @return array{0:int, 1:int} placed, refused
     */
    protected function tally(array $results): array
    {
        $placed = 0;
        $refused = 0;

        foreach ($results as $i => $r) {
            $this->assertSame(
                0,
                $r['exit'],
                "Worker {$i} errored rather than placing or cleanly refusing: " . ($r['err'] ?? ''),
            );

            match ($r['out']) {
                'PLACED'  => $placed++,
                'REFUSED' => $refused++,
                default   => $this->fail("Worker {$i} produced unexpected output: {$r['out']}"),
            };
        }

        return [$placed, $refused];
    }

    protected function liveHoldCount(int $listingId): int
    {
        $result = $this->runConcurrently(1, <<<PHP
    echo App\\Models\\Storefront\\StorefrontInventoryHold::query()
        ->where('listing_id', {$listingId})
        ->live()
        ->count();
PHP);

        return (int) $result[0]['out'];
    }

    protected function listingQuantity(int $listingId): string
    {
        $result = $this->runConcurrently(1, <<<PHP
    echo (string) App\\Models\\Storefront\\StorefrontListing::find({$listingId})->quantity_available;
PHP);

        return trim($result[0]['out']);
    }
}
