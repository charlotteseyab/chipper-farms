<?php

namespace Tests\Concurrency;

/**
 * True multi-process evidence that order confirmation cannot oversell
 * (CF-P0-006).
 *
 * The register is explicit that this needs "true multi-connection concurrency
 * tests", and it is right: the defects were
 *
 *   - the status check ran OUTSIDE the transaction against a stale in-memory
 *     order, so two confirmations both saw 'pending_confirmation';
 *   - listings were read with a plain find(), so both read the same
 *     quantity_available and each subtracted from the same starting value;
 *   - reservePigListing() returned silently when the listing was no longer
 *     published, so a second order for the same animal confirmed successfully;
 *   - adjustProductQuantity() clamped a negative result to zero, absorbing the
 *     oversell without a trace.
 *
 * None of those can be demonstrated in-process. SQLite cannot show row locking,
 * and RefreshDatabase wraps each test in a transaction that hides interleaving
 * by construction.
 *
 * The assertions here are deliberately about OUTCOMES rather than mechanism: N
 * units of stock must yield at most N confirmed orders, and the survivors plus
 * the rejections must account for every attempt. A test that asserted "a lock
 * was taken" would pass on an implementation that locked the wrong row.
 */
class OrderConfirmationConcurrencyTest extends ConcurrencyTestCase
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
     * Build a listing plus $orderCount pending orders, each for $qtyEach units.
     *
     * Runs in a single child process so it shares the application's own
     * services — building storefront orders by hand in SQL would risk testing a
     * shape the application never actually produces.
     *
     * @return array{listing_id:int, order_ids:list<int>, staff_id:int}
     */
    protected function seedOrders(int $orderCount, string $available, string $qtyEach, string $kind): array
    {
        $result = $this->runConcurrently(1, <<<PHP
    \$org = App\\Models\\Organization::firstOrCreate(
        ['slug' => 'conc-storefront'],
        ['name' => 'Concurrency Storefront', 'is_active' => true],
    );
    app(App\\Support\\CurrentOrganization::class)->bind(\$org);

    \$role = App\\Models\\Role::firstOrCreate(['name' => 'admin']);
    \$staff = App\\Models\\User::forceCreate([
        'name' => 'Conc Staff',
        'email' => 'concstaff_' . uniqid() . '@example.com',
        'password' => bcrypt('x'),
        'role' => 'admin',
        'role_id' => \$role->id,
        'organization_id' => \$org->id,
        'active_status' => true,
    ]);

    \$listing = App\\Models\\Storefront\\StorefrontListing::create([
        'organization_id' => \$org->id,
        'reference' => 'LIST-' . strtoupper(substr(md5(uniqid()), 0, 8)),
        'title' => 'Concurrency Test Item',
        'slug' => 'conc-' . uniqid(),
        'kind' => '{$kind}',
        // isOrderable() requires sale_mode = fixed; without it CartService
        // rejects the listing as "not available for direct purchase".
        'sale_mode' => 'fixed',
        'pricing_basis' => 'fixed',
        'fixed_price' => '100.00',
        'quantity_available' => '{$available}',
        'status' => 'published',
        'published_at' => now(),
    ]);

    \$orderIds = [];
    for (\$i = 0; \$i < {$orderCount}; \$i++) {
        \$buyer = App\\Models\\User::forceCreate([
            'name' => 'Buyer ' . \$i,
            'email' => 'buyer_' . \$i . '_' . uniqid() . '@example.com',
            'password' => bcrypt('x'),
            'role' => 'customer',
            'role_id' => App\\Models\\Role::firstOrCreate(['name' => 'customer'])->id,
            'organization_id' => \$org->id,
            'active_status' => true,
        ]);

        app(App\\Storefront\\Services\\CartService::class)->addItem(\$buyer, \$listing, '{$qtyEach}');
        \$order = app(App\\Storefront\\Services\\CheckoutService::class)->checkout(
            \$buyer,
            App\\Storefront\\Support\\CheckoutData::fromArray([
                'payment_method' => 'cod',
                'fulfilment_method' => 'pickup',
            ]),
        );
        \$orderIds[] = \$order->id;

        // PHASE 10: checkout now places an inventory hold, so more orders than
        // stock can no longer be created this way — which is the new FIRST line
        // of defence and is proved in HoldConcurrencyTest.
        //
        // This file tests the SECOND line: confirmation under contention. The
        // fixture therefore releases each hold as it goes, which is exactly the
        // real situation it must survive — holds that lapsed while several
        // unpaid orders were outstanding, and staff then confirming them all.
        app(App\\Storefront\\Services\\HoldService::class)
            ->releaseForOrder(\$order, 'Concurrency fixture: hold lapsed.');
    }

    echo json_encode([
        'listing_id' => \$listing->id,
        'order_ids'  => \$orderIds,
        'staff_id'   => \$staff->id,
    ]);
PHP);

        $this->assertSame(
            0,
            $result[0]['exit'],
            'Seeding the concurrency fixture failed: ' . ($result[0]['err'] ?? ''),
        );

        $decoded = json_decode($result[0]['out'], true);

        $this->assertIsArray($decoded, 'Fixture seeding returned unparseable output: ' . $result[0]['out']);

        return $decoded;
    }

    /**
     * Product stock: 3 units, 5 concurrent confirmations for 1 unit each.
     * Exactly 3 may succeed.
     */
    public function test_concurrent_confirmations_cannot_oversell_product_stock(): void
    {
        $available  = 3;
        $orderCount = 5;

        $fixture = $this->seedOrders($orderCount, (string) $available, '1', 'feed');
        $orderIdsJson = json_encode($fixture['order_ids']);

        $results = $this->runConcurrently($orderCount, <<<PHP
    \$orderIds = json_decode('{$orderIdsJson}', true);
    \$orderId  = \$orderIds[\$workerId];

    \$staff = App\\Models\\User::find({$fixture['staff_id']});
    \$order = App\\Models\\Storefront\\StorefrontOrder::find(\$orderId);
    app(App\\Support\\CurrentOrganization::class)->bind(\$staff->organization);

    try {
        app(App\\Storefront\\Services\\OrderService::class)->confirm(\$order, \$staff);
        echo 'CONFIRMED';
    } catch (App\\Storefront\\Services\\Exceptions\\InventoryConflictException \$e) {
        echo 'REJECTED';
    }
PHP, isolation: 'READ COMMITTED');

        $confirmed = 0;
        $rejected  = 0;

        foreach ($results as $i => $r) {
            $this->assertSame(
                0,
                $r['exit'],
                "Worker {$i} errored rather than confirming or cleanly rejecting: " . ($r['err'] ?? ''),
            );

            match ($r['out']) {
                'CONFIRMED' => $confirmed++,
                'REJECTED'  => $rejected++,
                default     => $this->fail("Worker {$i} produced unexpected output: {$r['out']}"),
            };
        }

        $this->assertSame(
            $available,
            $confirmed,
            "Expected exactly {$available} confirmations for {$available} units of stock, got {$confirmed}. "
            . 'More confirmations than stock means the oversell is back.',
        );

        $this->assertSame($orderCount - $available, $rejected, 'Every losing order must be rejected explicitly.');

        $this->assertSame(
            '0.0000',
            $this->listingQuantity($fixture['listing_id']),
            'All stock should be consumed and none created or destroyed.',
        );
    }

    /**
     * Stock must never go negative, whatever the interleaving.
     */
    public function test_stock_never_goes_negative_under_contention(): void
    {
        $fixture = $this->seedOrders(6, '2', '1', 'feed');
        $orderIdsJson = json_encode($fixture['order_ids']);

        $this->runConcurrently(6, <<<PHP
    \$orderIds = json_decode('{$orderIdsJson}', true);
    \$staff = App\\Models\\User::find({$fixture['staff_id']});
    app(App\\Support\\CurrentOrganization::class)->bind(\$staff->organization);
    \$order = App\\Models\\Storefront\\StorefrontOrder::find(\$orderIds[\$workerId]);

    try {
        app(App\\Storefront\\Services\\OrderService::class)->confirm(\$order, \$staff);
        echo 'CONFIRMED';
    } catch (App\\Storefront\\Services\\Exceptions\\InventoryConflictException \$e) {
        echo 'REJECTED';
    }
PHP, isolation: 'READ COMMITTED');

        $this->assertGreaterThanOrEqual(
            0,
            (float) $this->listingQuantity($fixture['listing_id']),
            'quantity_available went negative under concurrent confirmation.',
        );
    }

    /**
     * A pig is one animal. Concurrent confirmations for the same pig-backed
     * listing must produce exactly one confirmed order.
     *
     * This is the case with the sharpest real-world consequence: the previous
     * code confirmed BOTH orders and told two customers they had bought the same
     * animal, with nothing recorded anywhere to indicate a conflict.
     */
    public function test_concurrent_confirmations_cannot_double_sell_a_pig(): void
    {
        $orderCount = 4;
        $fixture = $this->seedOrders($orderCount, '1', '1', 'live_pig');
        $orderIdsJson = json_encode($fixture['order_ids']);

        $results = $this->runConcurrently($orderCount, <<<PHP
    \$orderIds = json_decode('{$orderIdsJson}', true);
    \$staff = App\\Models\\User::find({$fixture['staff_id']});
    app(App\\Support\\CurrentOrganization::class)->bind(\$staff->organization);
    \$order = App\\Models\\Storefront\\StorefrontOrder::find(\$orderIds[\$workerId]);

    try {
        app(App\\Storefront\\Services\\OrderService::class)->confirm(\$order, \$staff);
        echo 'CONFIRMED';
    } catch (App\\Storefront\\Services\\Exceptions\\InventoryConflictException \$e) {
        echo 'REJECTED';
    }
PHP, isolation: 'READ COMMITTED');

        $confirmed = 0;

        foreach ($results as $i => $r) {
            $this->assertSame(0, $r['exit'], "Worker {$i} errored: " . ($r['err'] ?? ''));

            if ($r['out'] === 'CONFIRMED') {
                $confirmed++;
            }
        }

        $this->assertSame(
            1,
            $confirmed,
            "One pig, {$orderCount} concurrent orders, {$confirmed} confirmations. "
            . 'Anything but 1 means the same animal was sold more than once.',
        );
    }

    protected function listingQuantity(int $listingId): string
    {
        $pdo = new \PDO(
            sprintf(
                'mysql:host=%s;port=%s;dbname=%s',
                $this->dbEnv['DB_HOST'],
                $this->dbEnv['DB_PORT'],
                $this->dbEnv['DB_DATABASE'],
            ),
            $this->dbEnv['DB_USERNAME'],
            $this->dbEnv['DB_PASSWORD'],
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION],
        );

        $stmt = $pdo->prepare('SELECT quantity_available FROM storefront_listings WHERE id = ?');
        $stmt->execute([$listingId]);

        return (string) $stmt->fetchColumn();
    }
}
