<?php

namespace Tests\Concurrency;

/**
 * Two ways to buy a pig, and the races they create between them.
 *
 * WHY THIS SUITE EXISTS SEPARATELY
 * --------------------------------
 * `PigAllocationConcurrencyTest` proves one animal cannot be sold twice. That
 * check counts ANIMALS, and the carcass flow reserves KILOGRAMS — a promise
 * with no animal attached to it. The two are enforced by different code, under
 * the same lock, and only a real race can show whether that lock actually
 * covers both.
 *
 * The failures being hunted are all the same shape: the farm promising more
 * than it has, in a way nothing notices until delivery day.
 *
 *   - two buyers each taking 150 kg from a listing that holds 200
 *   - a buyer taking an animal another buyer is checking out with
 *   - a farm-gate sale landing while a carcass checkout is mid-flight
 *   - one order's approval running twice and creating two litters
 *
 * WHAT IS ASSERTED
 * ----------------
 * Outcomes in the tables that decide what the farm owes: `pig_sales`,
 * `storefront_inventory_holds`, `pigs_management`. Never a storefront-only
 * counter, which would pass just as happily against an implementation that
 * promised the meat twice and recorded it once.
 */
class CarcassAndLivePurchaseConcurrencyTest extends ConcurrencyTestCase
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
     * A published listing backed by animals of the given weights, plus a buyer
     * per worker.
     *
     * Built through the real services in a child process, so the fixture is
     * whatever the application actually produces rather than hand-built SQL
     * that might have drifted from it.
     *
     * @param  list<int>  $weights
     * @return array{org_id:int, listing_id:int, pig_ids:list<int>, buyer_ids:list<int>, staff_id:int}
     */
    protected function seedPool(array $weights, int $buyers): array
    {
        // Each test gets its OWN farm: this suite's database is not reset
        // between tests, and settings, holds and pool rows are all per-farm.
        $slug     = 'conc-carcass-' . bin2hex(random_bytes(4));
        $weightsJson = json_encode($weights);

        $result = $this->runConcurrently(1, <<<PHP
    \$org = App\\Models\\Organization::firstOrCreate(
        ['slug' => '{$slug}'],
        ['name' => 'Concurrency Carcass Farm', 'is_active' => true],
    );
    app(App\\Support\\CurrentOrganization::class)->bind(\$org);

    // The rates the shop prices from. Per-farm, so this cannot disturb another
    // test running against the same database.
    App\\Models\\Setting::set('sales.pricing.live_weight_price', 30);
    App\\Models\\Setting::set('sales.pricing.carcass_weight_price', 50);

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
        'reference' => 'LIST-CARC-' . strtoupper(substr(md5(uniqid()), 0, 8)),
        'title' => 'Contended carcass pool',
        'slug' => 'carcass-' . uniqid(),
        'kind' => 'live_pig',
        'source_category' => 'standard_stock',
        'sale_mode' => 'fixed',
        'pricing_basis' => 'fixed',
        'fixed_price' => '0.00',
        'quantity_available' => '0',
        'quantity_unit' => 'head',
        'pickup_allowed' => true,
        'status' => 'published',
        'published_at' => now(),
    ]);

    \$pigIds = [];

    foreach (json_decode('{$weightsJson}', true) as \$i => \$kg) {
        // local_id explicit so parallel fixtures never contend on the global
        // sequence — see PigAllocationConcurrencyTest for the history.
        \$pig = App\\Models\\PigManagement::create([
            'organization_id' => \$org->id,
            'local_id' => random_int(1000000, 99999999),
            'ear_tag_number' => 'CARC-' . strtoupper(substr(md5(uniqid()), 0, 6)),
            'breed' => 'Large White',
            'gender' => 'female',
            'stock_type' => 'standard',
            'date_of_birth' => now()->subMonths(8)->toDateString(),
            'code' => 'PIG-' . uniqid(),
        ]);

        App\\Models\\PigWeight::create([
            'pig_id' => \$pig->id,
            'weight' => \$kg,
            'weigh_date' => now()->toDateString(),
        ]);

        \$pigIds[] = \$pig->id;
    }

    app(App\\Storefront\\Services\\StorefrontPigPoolService::class)
        ->attachPigs(\$listing, \$pigIds, \$staff);

    app(App\\Storefront\\Services\\StorefrontPigPricingService::class)
        ->refreshListingSnapshot(\$listing->fresh());

    \$buyerIds = [];

    for (\$i = 0; \$i < {$buyers}; \$i++) {
        \$buyer = App\\Models\\User::forceCreate([
            'name' => 'Conc Buyer ' . \$i,
            'email' => 'concbuyer_' . uniqid() . '_' . \$i . '@example.com',
            'password' => bcrypt('x'),
            'role' => 'admin',
            'role_id' => \$role->id,
            'organization_id' => \$org->id,
            'active_status' => true,
        ]);

        \$buyerIds[] = \$buyer->id;
    }

    echo json_encode([
        'org_id'     => \$org->id,
        'listing_id' => \$listing->id,
        'pig_ids'    => \$pigIds,
        'buyer_ids'  => \$buyerIds,
        'staff_id'   => \$staff->id,
    ]);
PHP);

        $this->assertSame(
            0,
            $result[0]['exit'],
            'Seeding the carcass fixture failed: ' . ($result[0]['err'] ?? ''),
        );

        $decoded = json_decode($result[0]['out'], true);

        $this->assertIsArray($decoded, 'Fixture seeding returned unparseable output: ' . $result[0]['out']);

        return $decoded;
    }

    protected function scalar(string $sql, array $bindings = []): string
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

        $stmt = $pdo->prepare($sql);
        $stmt->execute($bindings);

        return (string) $stmt->fetchColumn();
    }

    /** @param array<int, array<string, mixed>> $results */
    protected function tally(array $results, string ...$expected): array
    {
        $counts = array_fill_keys($expected, 0);

        foreach ($results as $i => $r) {
            $this->assertSame(0, $r['exit'], "Worker {$i} errored: " . ($r['err'] ?? ''));

            $out = trim($r['out']);

            $this->assertArrayHasKey($out, $counts, "Worker {$i} produced unexpected output: {$r['out']}");

            $counts[$out]++;
        }

        return $counts;
    }

    /* ================================================================== */

    /**
     * THE RACE THE CARCASS CHECK EXISTS FOR.
     *
     * A listing holding 200 kg, four buyers each asking for 150. At most one
     * can be promised; the rest must be refused at the moment they check out,
     * not discovered on delivery day.
     */
    public function test_the_same_kilograms_cannot_be_promised_twice(): void
    {
        $workers = 4;
        $fixture = $this->seedPool([100, 100], $workers);   // 200 kg

        $buyerJson = json_encode($fixture['buyer_ids']);

        $results = $this->runConcurrently($workers, <<<PHP
    \$buyerIds = json_decode('{$buyerJson}', true);
    \$buyer = App\\Models\\User::find(\$buyerIds[\$workerId]);
    app(App\\Support\\CurrentOrganization::class)->bind(\$buyer->organization);

    \$listing = App\\Models\\Storefront\\StorefrontListing::find({$fixture['listing_id']});
    \$owner   = App\\Storefront\\Support\\CartOwner::from(\$buyer);

    try {
        app(App\\Storefront\\Services\\StorefrontPurchaseLineService::class)
            ->addCarcassLine(\$owner, \$listing, '150');

        app(App\\Storefront\\Services\\CheckoutService::class)->checkout(
            \$owner,
            App\\Storefront\\Support\\CheckoutData::fromArray([
                'payment_method' => 'cod',
                'fulfilment_method' => 'pickup',
            ]),
        );

        echo 'PROMISED';
    } catch (App\\Storefront\\Services\\Exceptions\\InventoryConflictException \$e) {
        echo 'REFUSED';
    } catch (App\\Storefront\\Services\\Exceptions\\CartException \$e) {
        echo 'REFUSED';
    }
PHP, isolation: 'READ COMMITTED');

        $counts = $this->tally($results, 'PROMISED', 'REFUSED');

        $this->assertSame(
            1,
            $counts['PROMISED'],
            "{$counts['PROMISED']} buyers were each promised 150 kg from a 200 kg listing.",
        );

        $heldKg = $this->scalar(
            'SELECT COALESCE(SUM(requested_kg), 0) FROM storefront_inventory_holds
             WHERE listing_id = ? AND hold_type = ? AND status = ?',
            [$fixture['listing_id'], 'carcass_kg', 'active'],
        );

        $this->assertSame(
            0,
            bccomp($heldKg, '200.0000', 4) > 0 ? 1 : 0,
            "The listing has {$heldKg} kg reserved against 200 kg of animals.",
        );
    }

    /**
     * Enough for two, asked for by four.
     */
    public function test_capacity_is_shared_out_and_not_exceeded(): void
    {
        $workers = 4;
        $fixture = $this->seedPool([100, 100, 100], $workers);   // 300 kg

        $buyerJson = json_encode($fixture['buyer_ids']);

        $results = $this->runConcurrently($workers, <<<PHP
    \$buyerIds = json_decode('{$buyerJson}', true);
    \$buyer = App\\Models\\User::find(\$buyerIds[\$workerId]);
    app(App\\Support\\CurrentOrganization::class)->bind(\$buyer->organization);

    \$listing = App\\Models\\Storefront\\StorefrontListing::find({$fixture['listing_id']});
    \$owner   = App\\Storefront\\Support\\CartOwner::from(\$buyer);

    try {
        app(App\\Storefront\\Services\\StorefrontPurchaseLineService::class)
            ->addCarcassLine(\$owner, \$listing, '120');

        app(App\\Storefront\\Services\\CheckoutService::class)->checkout(
            \$owner,
            App\\Storefront\\Support\\CheckoutData::fromArray([
                'payment_method' => 'cod',
                'fulfilment_method' => 'pickup',
            ]),
        );

        echo 'PROMISED';
    } catch (App\\Storefront\\Services\\Exceptions\\InventoryConflictException \$e) {
        echo 'REFUSED';
    } catch (App\\Storefront\\Services\\Exceptions\\CartException \$e) {
        echo 'REFUSED';
    }
PHP, isolation: 'READ COMMITTED');

        $counts = $this->tally($results, 'PROMISED', 'REFUSED');

        // 300 / 120 = two whole orders.
        $this->assertLessThanOrEqual(
            2,
            $counts['PROMISED'],
            "{$counts['PROMISED']} orders of 120 kg were promised from 300 kg of animals.",
        );
        $this->assertGreaterThanOrEqual(1, $counts['PROMISED'], 'At least one buyer should have succeeded.');

        $heldKg = $this->scalar(
            'SELECT COALESCE(SUM(requested_kg), 0) FROM storefront_inventory_holds
             WHERE listing_id = ? AND hold_type = ? AND status = ?',
            [$fixture['listing_id'], 'carcass_kg', 'active'],
        );

        $this->assertLessThanOrEqual(
            300.0,
            (float) $heldKg,
            "The listing has {$heldKg} kg reserved against 300 kg of animals.",
        );
    }

    /**
     * The animal itself, contended by several buyers checking out at once.
     *
     * A live purchase names the pig. Two orders naming the same one is one
     * customer who will not be getting theirs.
     */
    public function test_two_buyers_cannot_check_out_with_the_same_animal(): void
    {
        $workers = 4;
        $fixture = $this->seedPool([100], $workers);

        $buyerJson = json_encode($fixture['buyer_ids']);

        $results = $this->runConcurrently($workers, <<<PHP
    \$buyerIds = json_decode('{$buyerJson}', true);
    \$buyer = App\\Models\\User::find(\$buyerIds[\$workerId]);
    app(App\\Support\\CurrentOrganization::class)->bind(\$buyer->organization);

    \$listing = App\\Models\\Storefront\\StorefrontListing::find({$fixture['listing_id']});
    \$owner   = App\\Storefront\\Support\\CartOwner::from(\$buyer);

    \$rowId = App\\Models\\Storefront\\StorefrontListingPig::where('storefront_listing_id', \$listing->id)
        ->value('id');

    try {
        app(App\\Storefront\\Services\\StorefrontPurchaseLineService::class)
            ->addLiveLines(\$owner, \$listing, [\$rowId]);

        app(App\\Storefront\\Services\\CheckoutService::class)->checkout(
            \$owner,
            App\\Storefront\\Support\\CheckoutData::fromArray([
                'payment_method' => 'cod',
                'fulfilment_method' => 'pickup',
            ]),
        );

        echo 'BOUGHT';
    } catch (App\\Storefront\\Services\\Exceptions\\InventoryConflictException \$e) {
        echo 'REFUSED';
    } catch (App\\Storefront\\Services\\Exceptions\\CartException \$e) {
        echo 'REFUSED';
    }
PHP, isolation: 'READ COMMITTED');

        $counts = $this->tally($results, 'BOUGHT', 'REFUSED');

        $this->assertSame(
            1,
            $counts['BOUGHT'],
            "{$counts['BOUGHT']} buyers checked out with the same animal.",
        );

        $active = $this->scalar(
            'SELECT COUNT(*) FROM storefront_order_item_pig_allocations
             WHERE pig_id = ? AND status IN (?, ?)',
            [$fixture['pig_ids'][0], 'selected', 'held'],
        );

        $this->assertSame('1', $active, "The animal is committed to {$active} orders at once.");
    }

    /**
     * A farm-gate sale landing while carcass checkouts are in flight.
     *
     * Either outcome is correct — the yard may win, or the shop may — but the
     * farm must never end up owing meat it no longer has.
     */
    public function test_a_gate_sale_and_a_carcass_checkout_cannot_both_overcommit(): void
    {
        $workers = 4;
        $fixture = $this->seedPool([100, 100], $workers);   // 200 kg

        $buyerJson = json_encode($fixture['buyer_ids']);

        $results = $this->runConcurrently($workers, <<<PHP
    \$buyerIds = json_decode('{$buyerJson}', true);
    \$buyer = App\\Models\\User::find(\$buyerIds[\$workerId]);
    app(App\\Support\\CurrentOrganization::class)->bind(\$buyer->organization);

    \$listing = App\\Models\\Storefront\\StorefrontListing::find({$fixture['listing_id']});

    // Worker 0 is the yard: an ordinary farm-gate sale of one animal.
    if (\$workerId === 0) {
        \$staff = App\\Models\\User::find({$fixture['staff_id']});
        \$pig   = App\\Models\\PigManagement::find({$fixture['pig_ids'][0]});

        try {
            app(App\\Services\\Sales\\RecordPigSaleService::class)->execute(\$pig, \$staff, [
                'sale_date' => now()->toDateString(),
                'sale_price' => 3000,
                'weight_type' => 'live',
                'sale_weight' => 100,
                'buyer_name' => 'Walk-in buyer',
            ]);

            echo 'SOLD_AT_GATE';
        } catch (Throwable \$e) {
            echo 'GATE_REFUSED';
        }

        return;
    }

    \$owner = App\\Storefront\\Support\\CartOwner::from(\$buyer);

    try {
        app(App\\Storefront\\Services\\StorefrontPurchaseLineService::class)
            ->addCarcassLine(\$owner, \$listing, '150');

        app(App\\Storefront\\Services\\CheckoutService::class)->checkout(
            \$owner,
            App\\Storefront\\Support\\CheckoutData::fromArray([
                'payment_method' => 'cod',
                'fulfilment_method' => 'pickup',
            ]),
        );

        echo 'PROMISED';
    } catch (App\\Storefront\\Services\\Exceptions\\InventoryConflictException \$e) {
        echo 'REFUSED';
    } catch (App\\Storefront\\Services\\Exceptions\\CartException \$e) {
        echo 'REFUSED';
    }
PHP, isolation: 'READ COMMITTED');

        $counts = $this->tally($results, 'SOLD_AT_GATE', 'GATE_REFUSED', 'PROMISED', 'REFUSED');

        $soldKg = $counts['SOLD_AT_GATE'] === 1 ? '100.0000' : '0.0000';

        $heldKg = $this->scalar(
            'SELECT COALESCE(SUM(requested_kg), 0) FROM storefront_inventory_holds
             WHERE listing_id = ? AND hold_type = ? AND status = ?',
            [$fixture['listing_id'], 'carcass_kg', 'active'],
        );

        $remaining = bcsub('200.0000', $soldKg, 4);

        $this->assertTrue(
            bccomp($heldKg, $remaining, 4) <= 0,
            "The shop has promised {$heldKg} kg but the farm has only {$remaining} kg left after the gate sale.",
        );
    }

    /**
     * One piglet order, several approvals at once.
     *
     * Materialisation creates animals that did not exist. Running twice must
     * not put a second litter of them in the herd, nor a second set of sales in
     * the ledger.
     */
    public function test_approving_a_piglet_order_concurrently_creates_one_litter(): void
    {
        $workers = 4;
        $slug    = 'conc-piglet-' . bin2hex(random_bytes(4));

        $seed = $this->runConcurrently(1, <<<PHP
    \$org = App\\Models\\Organization::firstOrCreate(
        ['slug' => '{$slug}'],
        ['name' => 'Concurrency Piglet Farm', 'is_active' => true],
    );
    app(App\\Support\\CurrentOrganization::class)->bind(\$org);

    \$role = App\\Models\\Role::firstOrCreate(['name' => 'admin']);
    \$staff = App\\Models\\User::forceCreate([
        'name' => 'Conc Piglet Staff',
        'email' => 'concpiglet_' . uniqid() . '@example.com',
        'password' => bcrypt('x'),
        'role' => 'admin',
        'role_id' => \$role->id,
        'organization_id' => \$org->id,
        'active_status' => true,
    ]);

    \$listing = App\\Models\\Storefront\\StorefrontListing::create([
        'organization_id' => \$org->id,
        'reference' => 'LIST-PGL-' . strtoupper(substr(md5(uniqid()), 0, 8)),
        'title' => 'Weaner piglets',
        'slug' => 'piglets-' . uniqid(),
        'kind' => 'piglet_lot',
        'source_category' => 'piglets',
        'sale_mode' => 'fixed',
        'pricing_basis' => 'fixed',
        'fixed_price' => '250.00',
        'quantity_available' => '20',
        'quantity_unit' => 'head',
        'pickup_allowed' => true,
        'status' => 'published',
        'published_at' => now(),
    ]);

    \$order = App\\Models\\Storefront\\StorefrontOrder::create([
        'organization_id' => \$org->id,
        'reference' => 'SO-PGL-' . strtoupper(substr(md5(uniqid()), 0, 8)),
        'status' => 'pending_confirmation',
        'payment_status' => 'cash_on_delivery',
        'payment_method' => 'cod',
        'fulfilment_method' => 'pickup',
        'subtotal' => '1250.00', 'delivery_fee' => '0.00', 'total' => '1250.00',
        'currency_code' => 'GHS', 'placed_at' => now(),
    ]);

    App\\Models\\Storefront\\StorefrontOrderItem::create([
        'organization_id' => \$org->id,
        'order_id' => \$order->id,
        'listing_id' => \$listing->id,
        'kind' => 'piglet_lot',
        'source_category' => 'piglets',
        'description' => 'Weaner piglets',
        'quantity' => '5',
        'quantity_unit' => 'head',
        'price_basis' => 'fixed',
        'unit_price' => '250.00',
        'line_total' => '1250.00',
    ]);

    echo json_encode(['order_id' => \$order->id, 'staff_id' => \$staff->id, 'org_id' => \$org->id]);
PHP);

        $this->assertSame(0, $seed[0]['exit'], 'Seeding the piglet fixture failed: ' . ($seed[0]['err'] ?? ''));

        $fixture = json_decode($seed[0]['out'], true);
        $this->assertIsArray($fixture, 'Fixture seeding returned unparseable output: ' . $seed[0]['out']);

        $results = $this->runConcurrently($workers, <<<PHP
    \$staff = App\\Models\\User::find({$fixture['staff_id']});
    app(App\\Support\\CurrentOrganization::class)->bind(\$staff->organization);

    \$order = App\\Models\\Storefront\\StorefrontOrder::find({$fixture['order_id']});

    try {
        \$sales = app(App\\Storefront\\Services\\StorefrontPigAllocationService::class)
            ->approveAllocationsForOrder(\$order, \$staff);

        echo 'APPROVED:' . \$sales->count();
    } catch (Throwable \$e) {
        // Reported, not swallowed. A worker that failed for an unexpected
        // reason must not look like a worker that lost a race.
        echo 'FAILED:' . get_class(\$e) . ':' . \$e->getMessage();
    }
PHP, isolation: 'READ COMMITTED');

        foreach ($results as $i => $r) {
            $this->assertSame(0, $r['exit'], "Worker {$i} errored: " . ($r['err'] ?? ''));

            $this->assertStringStartsWith(
                'APPROVED:',
                trim($r['out']),
                "Worker {$i} did not approve cleanly: " . trim($r['out']),
            );
        }

        $sales = $this->scalar(
            'SELECT COUNT(*) FROM pig_sales ps
             JOIN storefront_order_item_pig_allocations a ON a.pig_sale_id = ps.id
             WHERE a.storefront_order_id = ?',
            [$fixture['order_id']],
        );

        $this->assertSame(
            '5',
            $sales,
            "The order bought five piglets and produced {$sales} sales.",
        );

        $animals = $this->scalar(
            'SELECT COUNT(*) FROM storefront_order_item_pig_allocations WHERE storefront_order_id = ?',
            [$fixture['order_id']],
        );

        $this->assertSame(
            '5',
            $animals,
            "Five piglets were bought and {$animals} animal records were created for them.",
        );
    }

    /**
     * Live and carcass buyers contending for the same pool at once.
     *
     * They reserve different things — animals and kilograms — and the danger is
     * that each check only sees its own kind, so between them they promise more
     * than the pool holds.
     */
    public function test_live_and_carcass_buyers_cannot_between_them_oversell_the_pool(): void
    {
        $workers = 4;
        $fixture = $this->seedPool([100, 100], $workers);   // 2 head, 200 kg

        $buyerJson = json_encode($fixture['buyer_ids']);

        $results = $this->runConcurrently($workers, <<<PHP
    \$buyerIds = json_decode('{$buyerJson}', true);
    \$buyer = App\\Models\\User::find(\$buyerIds[\$workerId]);
    app(App\\Support\\CurrentOrganization::class)->bind(\$buyer->organization);

    \$listing = App\\Models\\Storefront\\StorefrontListing::find({$fixture['listing_id']});
    \$owner   = App\\Storefront\\Support\\CartOwner::from(\$buyer);
    \$lines   = app(App\\Storefront\\Services\\StorefrontPurchaseLineService::class);

    try {
        // Even workers want a named animal; odd workers want meat.
        if (\$workerId % 2 === 0) {
            \$rowIds = App\\Models\\Storefront\\StorefrontListingPig::where('storefront_listing_id', \$listing->id)
                ->where('status', 'available')
                ->orderBy('id')
                ->pluck('id')
                ->all();

            if (\$rowIds === []) {
                echo 'REFUSED';
                return;
            }

            \$lines->addLiveLines(\$owner, \$listing, [\$rowIds[0]]);
        } else {
            \$lines->addCarcassLine(\$owner, \$listing, '100');
        }

        app(App\\Storefront\\Services\\CheckoutService::class)->checkout(
            \$owner,
            App\\Storefront\\Support\\CheckoutData::fromArray([
                'payment_method' => 'cod',
                'fulfilment_method' => 'pickup',
            ]),
        );

        echo 'PROMISED';
    } catch (App\\Storefront\\Services\\Exceptions\\InventoryConflictException \$e) {
        echo 'REFUSED';
    } catch (App\\Storefront\\Services\\Exceptions\\CartException \$e) {
        echo 'REFUSED';
    }
PHP, isolation: 'READ COMMITTED');

        $this->tally($results, 'PROMISED', 'REFUSED');

        // Whatever the interleaving, no animal may be committed to two orders
        // and no more meat may be promised than the unclaimed animals can cut.
        $doubleCommitted = $this->scalar(
            'SELECT COUNT(*) FROM (
                SELECT pig_id FROM storefront_order_item_pig_allocations
                WHERE storefront_listing_id = ? AND status IN (?, ?)
                GROUP BY pig_id HAVING COUNT(*) > 1
             ) AS doubles',
            [$fixture['listing_id'], 'selected', 'held'],
        );

        $this->assertSame('0', $doubleCommitted, 'An animal is committed to more than one order.');

        $committedHead = (int) $this->scalar(
            'SELECT COUNT(DISTINCT pig_id) FROM storefront_order_item_pig_allocations
             WHERE storefront_listing_id = ? AND status IN (?, ?)',
            [$fixture['listing_id'], 'selected', 'held'],
        );

        $heldKg = $this->scalar(
            'SELECT COALESCE(SUM(requested_kg), 0) FROM storefront_inventory_holds
             WHERE listing_id = ? AND hold_type = ? AND status = ?',
            [$fixture['listing_id'], 'carcass_kg', 'active'],
        );

        // Every committed animal is 100 kg that can no longer be cut for meat.
        $meatLeft = bcsub('200.0000', bcmul((string) $committedHead, '100', 4), 4);

        $this->assertTrue(
            bccomp($heldKg, $meatLeft, 4) <= 0,
            "{$heldKg} kg of meat is promised, but {$committedHead} animal(s) are spoken for live, " .
            "leaving only {$meatLeft} kg to cut.",
        );
    }
}
