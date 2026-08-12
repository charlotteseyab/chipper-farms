<?php

namespace Tests\Concurrency;

/**
 * The same animal cannot be sold twice — proved with real processes.
 *
 * WHY THE FEATURE TESTS ARE NOT ENOUGH
 * ------------------------------------
 * Every "cannot be sold twice" assertion elsewhere passes by construction: one
 * process, sequential calls, each seeing the previous one's committed work. The
 * interesting case is two managers pressing Approve at the same moment, or a
 * farm-gate sale landing while an approval is mid-flight.
 *
 * `RefreshDatabase` wraps a test in a transaction that hides exactly that
 * interleaving, and SQLite cannot demonstrate row locking at all. So these run
 * as separate OS processes against MariaDB, released together on a shared start
 * file.
 *
 * WHAT IS ASSERTED
 * ----------------
 * Outcomes in `pig_sales`, never in a storefront table. One animal must yield
 * exactly one sale however the workers interleave. A test that counted
 * storefront allocations would pass just as happily against an implementation
 * that sold the pig twice and recorded it once.
 */
class PigAllocationConcurrencyTest extends ConcurrencyTestCase
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
     * One listing, one pig in its pool, and $orderCount orders each wanting it.
     *
     * Built in a single child process so it goes through the same services the
     * application uses, rather than hand-built SQL that might not match what the
     * app actually produces.
     *
     * @return array{listing_id:int, pig_id:int, order_ids:list<int>, item_ids:list<int>, staff_id:int}
     */
    protected function seedContendedPig(int $orderCount): array
    {
        // Each test gets its OWN farm.
        //
        // This suite's database is not reset between tests, and `local_id` is a
        // per-organisation sequence with a unique index. Sharing one
        // organisation across tests made the second fixture collide on a key
        // that has nothing to do with what is being tested.
        $slug = 'conc-pig-pool-' . bin2hex(random_bytes(4));

        $result = $this->runConcurrently(1, <<<PHP
    \$org = App\\Models\\Organization::firstOrCreate(
        ['slug' => '{$slug}'],
        ['name' => 'Concurrency Pig Pool', 'is_active' => true],
    );
    app(App\\Support\\CurrentOrganization::class)->bind(\$org);

    \$role = App\\Models\\Role::firstOrCreate(['name' => 'admin']);
    \$staff = App\\Models\\User::forceCreate([
        'name' => 'Conc Approver',
        'email' => 'concapprover_' . uniqid() . '@example.com',
        'password' => bcrypt('x'),
        'role' => 'admin',
        'role_id' => \$role->id,
        'organization_id' => \$org->id,
        'active_status' => true,
    ]);

    \$listing = App\\Models\\Storefront\\StorefrontListing::create([
        'organization_id' => \$org->id,
        'reference' => 'LIST-CONC-' . strtoupper(substr(md5(uniqid()), 0, 8)),
        'title' => 'Contended pig',
        'slug' => 'contended-' . uniqid(),
        'kind' => 'live_pig',
        'sale_mode' => 'fixed',
        'pricing_basis' => 'fixed',
        'fixed_price' => '900.00',
        'quantity_available' => '0',
        'status' => 'published',
        'published_at' => now(),
    ]);

    // local_id is set explicitly to dodge a PRE-EXISTING application bug:
    // PigManagement generates it as max(local_id)+1 through an
    // organisation-scoped model, but `pigs_local_id_unique` is a GLOBAL unique
    // index on that column alone. The moment a second farm exists the generated
    // value collides. Recorded rather than fixed here — it is not this feature's
    // defect, and repairing it means a migration and a renumbering decision.
    \$pig = App\\Models\\PigManagement::create([
        'organization_id' => \$org->id,
        'local_id' => random_int(1000000, 99999999),
        'ear_tag_number' => 'CONC-' . strtoupper(substr(md5(uniqid()), 0, 6)),
        'breed' => 'Large White',
        'gender' => 'male',
        'stock_type' => 'standard',
        'date_of_birth' => now()->subMonths(8)->toDateString(),
        'code' => 'PIG-' . uniqid(),
    ]);

    app(App\\Storefront\\Services\\StorefrontPigPoolService::class)
        ->attachPigs(\$listing, [\$pig->id], \$staff);

    \$orderIds = [];
    \$itemIds  = [];

    for (\$i = 0; \$i < {$orderCount}; \$i++) {
        \$order = App\\Models\\Storefront\\StorefrontOrder::create([
            'organization_id' => \$org->id,
            'reference' => 'SO-CONC-' . uniqid() . '-' . \$i,
            'status' => 'pending_confirmation',
            'payment_status' => 'cash_on_delivery',
            'payment_method' => 'cod',
            'fulfilment_method' => 'pickup',
            'subtotal' => '900.00', 'delivery_fee' => '0.00', 'total' => '900.00',
            'currency_code' => 'GHS', 'placed_at' => now(),
        ]);

        \$item = App\\Models\\Storefront\\StorefrontOrderItem::create([
            'organization_id' => \$org->id,
            'order_id' => \$order->id,
            'listing_id' => \$listing->id,
            'kind' => 'live_pig',
            'description' => 'Contended pig',
            'quantity' => '1',
            'quantity_unit' => 'head',
            'price_basis' => 'fixed',
            'unit_price' => '900.00',
            'line_total' => '900.00',
        ]);

        \$orderIds[] = \$order->id;
        \$itemIds[]  = \$item->id;
    }

    echo json_encode([
        'listing_id' => \$listing->id,
        'pig_id'     => \$pig->id,
        'order_ids'  => \$orderIds,
        'item_ids'   => \$itemIds,
        'staff_id'   => \$staff->id,
    ]);
PHP);

        $this->assertSame(
            0,
            $result[0]['exit'],
            'Seeding the pig-pool fixture failed: ' . ($result[0]['err'] ?? ''),
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

    /* ================================================================== */

    /**
     * Six orders, one animal. Exactly one may end up owning it.
     */
    public function test_only_one_order_can_allocate_the_same_pig(): void
    {
        $workers = 6;
        $fixture = $this->seedContendedPig($workers);

        $itemJson = json_encode($fixture['item_ids']);

        $results = $this->runConcurrently($workers, <<<PHP
    \$itemIds = json_decode('{$itemJson}', true);
    \$item = App\\Models\\Storefront\\StorefrontOrderItem::find(\$itemIds[\$workerId]);
    \$staff = App\\Models\\User::find({$fixture['staff_id']});
    app(App\\Support\\CurrentOrganization::class)->bind(\$staff->organization);

    try {
        app(App\\Storefront\\Services\\StorefrontPigAllocationService::class)
            ->selectForOrderItem(\$item, [{$fixture['pig_id']}], \$staff);
        echo 'ALLOCATED';
    } catch (App\\Storefront\\Services\\Exceptions\\InventoryConflictException \$e) {
        echo 'REFUSED';
    }
PHP, isolation: 'READ COMMITTED');

        $allocated = 0;
        $refused   = 0;

        foreach ($results as $i => $r) {
            $this->assertSame(0, $r['exit'], "Worker {$i} errored: " . ($r['err'] ?? ''));

            match ($r['out']) {
                'ALLOCATED' => $allocated++,
                'REFUSED'   => $refused++,
                default     => $this->fail("Worker {$i} produced unexpected output: {$r['out']}"),
            };
        }

        $this->assertSame(
            1,
            $allocated,
            "{$allocated} orders claimed the same animal. Only one customer can be sold one pig.",
        );

        $this->assertSame($workers - 1, $refused, 'Every losing order must be refused explicitly.');

        $active = $this->scalar(
            'SELECT COUNT(*) FROM storefront_order_item_pig_allocations WHERE pig_id = ? AND status IN (?, ?)',
            [$fixture['pig_id'], 'selected', 'approved'],
        );

        $this->assertSame('1', $active, 'Exactly one live allocation may exist for one animal.');
    }

    /**
     * Two approvals of the SAME order at once must produce one sale.
     *
     * This is the double-clicked Confirm, raced for real rather than called
     * twice in sequence.
     */
    public function test_two_simultaneous_approvals_of_one_order_sell_the_pig_once(): void
    {
        $fixture = $this->seedContendedPig(1);

        // Choose the animal first, in its own process.
        $prep = $this->runConcurrently(1, <<<PHP
    \$item = App\\Models\\Storefront\\StorefrontOrderItem::find({$fixture['item_ids'][0]});
    \$staff = App\\Models\\User::find({$fixture['staff_id']});
    app(App\\Support\\CurrentOrganization::class)->bind(\$staff->organization);

    app(App\\Storefront\\Services\\StorefrontPigAllocationService::class)
        ->selectForOrderItem(\$item, [{$fixture['pig_id']}], \$staff);

    echo 'SELECTED';
PHP);

        $this->assertSame('SELECTED', $prep[0]['out'], 'Selection failed: ' . ($prep[0]['err'] ?? ''));

        $results = $this->runConcurrently(4, <<<PHP
    \$order = App\\Models\\Storefront\\StorefrontOrder::find({$fixture['order_ids'][0]});
    \$staff = App\\Models\\User::find({$fixture['staff_id']});
    app(App\\Support\\CurrentOrganization::class)->bind(\$staff->organization);

    try {
        \$sales = app(App\\Storefront\\Services\\StorefrontPigAllocationService::class)
            ->approveAllocationsForOrder(\$order, \$staff);
        echo 'SOLD:' . \$sales->count();
    } catch (Throwable \$e) {
        echo 'ERROR:' . get_class(\$e);
    }
PHP, isolation: 'READ COMMITTED');

        foreach ($results as $i => $r) {
            $this->assertSame(0, $r['exit'], "Worker {$i} died: " . ($r['err'] ?? ''));
            $this->assertStringStartsWith('SOLD:', $r['out'], "Worker {$i}: {$r['out']}");
        }

        // The count that matters is in pig_sales, not in the workers' replies.
        $sales = $this->scalar('SELECT COUNT(*) FROM pig_sales WHERE pig_id = ?', [$fixture['pig_id']]);

        $this->assertSame(
            '1',
            $sales,
            "One animal produced {$sales} sales. /sales is the ledger; it must record one sale per pig.",
        );
    }

    /**
     * A sale cannot complete while another transaction holds the animal.
     *
     * WHAT THIS PROVES, AND WHAT IT DOES NOT
     * --------------------------------------
     * It proves the service cannot write a sale for a pig whose row somebody
     * else has locked: it waits, hits the lock timeout, and sells nothing.
     *
     * It does NOT isolate the explicit `lockForUpdate()` added to
     * `RecordPigSaleService`. Removing that line leaves this test green, because
     * the soft-delete the service performs on the same row blocks anyway. That
     * was measured, not assumed — the first version of this docblock claimed a
     * deterministic control it did not have.
     *
     * The discriminating evidence for the lock is the race below, and it is
     * PROBABILISTIC: with `lockForUpdate()` removed it produced two sales for
     * one animal on two runs out of three. Same limitation as IMP-084, recorded
     * rather than dressed up.
     *
     * This test still earns its place. It is the deterministic half — no
     * scheduling luck involved — of the guarantee that matters: while one
     * transaction owns an animal, nobody else can sell it.
     */
    public function test_recording_a_sale_waits_behind_a_lock_on_the_pig_row(): void
    {
        $fixture = $this->seedContendedPig(1);

        $results = $this->runConcurrently(2, <<<PHP
    \$staff = App\\Models\\User::find({$fixture['staff_id']});
    app(App\\Support\\CurrentOrganization::class)->bind(\$staff->organization);

    if (\$workerId === 0) {
        Illuminate\\Support\\Facades\\DB::transaction(function () {
            Illuminate\\Support\\Facades\\DB::select(
                'SELECT id FROM pigs_management WHERE id = ? FOR UPDATE',
                [{$fixture['pig_id']}]
            );
            usleep(6000000);
        });
        echo 'HELD';
        return;
    }

    // Let worker 0 take its lock first. This is a queue, not a race — which is
    // what makes the result deterministic.
    usleep(1500000);

    Illuminate\\Support\\Facades\\DB::statement('SET SESSION innodb_lock_wait_timeout = 2');

    try {
        \$pig = App\\Models\\PigManagement::find({$fixture['pig_id']});

        app(App\\Services\\Sales\\RecordPigSaleService::class)->execute(\$pig, \$staff, [
            'sale_date' => now()->toDateString(),
            'sale_price' => 900,
            'weight_type' => 'live',
            'sale_weight' => 90,
            'buyer_name' => 'Walk-in buyer',
        ]);
        echo 'SOLD_WITHOUT_WAITING';
    } catch (Illuminate\\Database\\QueryException \$e) {
        echo str_contains(\$e->getMessage(), 'Lock wait timeout')
            ? 'BLOCKED_BY_LOCK'
            : 'OTHER_DB_ERROR';
    } catch (Throwable \$e) {
        echo 'OTHER_ERROR:' . get_class(\$e);
    }
PHP, isolation: 'READ COMMITTED');

        $this->assertSame('HELD', $results[0]['out'], 'The lock holder did not finish: ' . ($results[0]['err'] ?? ''));

        $this->assertSame(
            'BLOCKED_BY_LOCK',
            $results[1]['out'],
            'RecordPigSaleService did not wait behind an exclusive lock on the pig it was about to '
            . 'sell. Without that lock two paths can each read an unsold animal and both write, '
            . "putting the same pig in the ledger twice.\nWorker said: " . $results[1]['out'],
        );

        $this->assertSame(
            '0',
            $this->scalar('SELECT COUNT(*) FROM pig_sales WHERE pig_id = ?', [$fixture['pig_id']]),
            'The blocked attempt must not have sold anything.',
        );
    }

    /**
     * A farm-gate sale landing while an approval runs.
     *
     * Both paths call `RecordPigSaleService`, which is the point: whichever
     * wins, the animal is sold exactly once and the loser must not add a second
     * sale on top.
     */
    public function test_a_direct_sale_racing_an_approval_sells_the_pig_once(): void
    {
        $fixture = $this->seedContendedPig(1);

        $prep = $this->runConcurrently(1, <<<PHP
    \$item = App\\Models\\Storefront\\StorefrontOrderItem::find({$fixture['item_ids'][0]});
    \$staff = App\\Models\\User::find({$fixture['staff_id']});
    app(App\\Support\\CurrentOrganization::class)->bind(\$staff->organization);

    app(App\\Storefront\\Services\\StorefrontPigAllocationService::class)
        ->selectForOrderItem(\$item, [{$fixture['pig_id']}], \$staff);

    echo 'SELECTED';
PHP);

        $this->assertSame('SELECTED', $prep[0]['out'], 'Selection failed: ' . ($prep[0]['err'] ?? ''));

        // Worker 0 approves the order; worker 1 sells the same animal at the
        // gate. Released together.
        $results = $this->runConcurrently(2, <<<PHP
    \$staff = App\\Models\\User::find({$fixture['staff_id']});
    app(App\\Support\\CurrentOrganization::class)->bind(\$staff->organization);

    if (\$workerId === 0) {
        try {
            \$sales = app(App\\Storefront\\Services\\StorefrontPigAllocationService::class)
                ->approveAllocationsForOrder(
                    App\\Models\\Storefront\\StorefrontOrder::find({$fixture['order_ids'][0]}),
                    \$staff,
                );
            echo 'APPROVAL_SOLD:' . \$sales->count();
        } catch (Throwable \$e) {
            echo 'APPROVAL_REFUSED';
        }
        return;
    }

    try {
        \$pig = App\\Models\\PigManagement::find({$fixture['pig_id']});

        if (\$pig === null) {
            echo 'DIRECT_REFUSED';
            return;
        }

        app(App\\Services\\Sales\\RecordPigSaleService::class)->execute(\$pig, \$staff, [
            'sale_date' => now()->toDateString(),
            'sale_price' => 900,
            'weight_type' => 'live',
            'sale_weight' => 90,
            'buyer_name' => 'Walk-in buyer',
        ]);
        echo 'DIRECT_SOLD';
    } catch (Throwable \$e) {
        echo 'DIRECT_REFUSED';
    }
PHP, isolation: 'READ COMMITTED');

        foreach ($results as $i => $r) {
            $this->assertSame(0, $r['exit'], "Worker {$i} died: " . ($r['err'] ?? ''));
        }

        $sales = $this->scalar('SELECT COUNT(*) FROM pig_sales WHERE pig_id = ?', [$fixture['pig_id']]);

        $this->assertSame(
            '1',
            $sales,
            sprintf(
                "One animal produced %s sales when a gate sale raced an approval. Workers said: %s / %s.",
                $sales,
                $results[0]['out'],
                $results[1]['out'],
            ),
        );
    }
}
