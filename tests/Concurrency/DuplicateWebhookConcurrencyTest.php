<?php

namespace Tests\Concurrency;

/**
 * Phase 17 (§60) — the same Paystack webhook delivered simultaneously.
 *
 * §60 lists "duplicate Paystack webhook" beside simultaneous checkout and
 * confirmation, under Concurrency, and means it: Paystack retries until it gets
 * a 2xx and re-delivers on its own schedule besides. A retry that overlaps the
 * original is ordinary, not exotic.
 *
 * WHY THIS CANNOT BE AN IN-PROCESS TEST
 * -------------------------------------
 * `DuplicateWebhookTest` covers sequential re-delivery and passes, because each
 * delivery sees the previous one's committed work. The interesting case is two
 * deliveries in flight at once, where three separate guards are all
 * read-then-act with no lock between the read and the write:
 *
 *   - `PaystackService::verify()` returns early if the payment is already
 *     verified;
 *   - `PaymentService::markPaystackVerified()` checks the same thing again;
 *   - `OrderService::markPaymentConfirmed()` returns early unless the order is
 *     still pending_payment.
 *
 * Every one of those can be passed by two workers that read before either
 * writes. `RefreshDatabase` wraps a test in a transaction that hides exactly
 * this interleaving, and SQLite cannot demonstrate row locking at all.
 *
 * WHAT IS ASSERTED
 * ----------------
 * Outcomes, not mechanism. One charge must leave one verified payment and one
 * payment entry on the order's timeline, however the two deliveries interleave.
 * A test that asserted "a lock was taken" would pass against an implementation
 * that locked the wrong row.
 */
class DuplicateWebhookConcurrencyTest extends ConcurrencyTestCase
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
     * A real Paystack order awaiting payment.
     *
     * Placed through checkout and `initialise()` in a single child process, so
     * the payment row and reference are the ones the application produces.
     *
     * @return array{order_id:int, reference:string}
     */
    protected function seedPayingOrder(): array
    {
        $result = $this->runConcurrently(1, <<<'PHP'
    $org = App\Models\Organization::firstOrCreate(
        ['slug' => 'conc-webhook'],
        ['name' => 'Concurrency Webhook Farm', 'is_active' => true],
    );
    app(App\Support\CurrentOrganization::class)->bind($org);

    $buyer = App\Models\User::forceCreate([
        'name' => 'Webhook Buyer',
        'email' => 'webhookbuyer_' . uniqid() . '@example.com',
        'password' => bcrypt('x'),
        'role' => 'customer',
        'role_id' => App\Models\Role::firstOrCreate(['name' => 'customer'])->id,
        'organization_id' => $org->id,
        'active_status' => true,
    ]);

    $listing = App\Models\Storefront\StorefrontListing::create([
        'organization_id' => $org->id,
        'reference' => 'LIST-' . strtoupper(substr(md5(uniqid()), 0, 8)),
        'title' => 'Webhook Test Feed',
        'slug' => 'conc-webhook-' . uniqid(),
        'kind' => 'feed',
        'sale_mode' => 'fixed',
        'pricing_basis' => 'fixed',
        'fixed_price' => '150.00',
        'quantity_available' => '10',
        'status' => 'published',
        'published_at' => now(),
    ]);

    app(App\Storefront\Services\CartService::class)->addItem($buyer, $listing, '1');

    $order = app(App\Storefront\Services\CheckoutService::class)->checkout(
        $buyer,
        App\Storefront\Support\CheckoutData::fromArray([
            'payment_method' => 'paystack',
            'fulfilment_method' => 'pickup',
        ]),
    );

    $init = app(App\Storefront\Services\PaystackService::class)
        ->initialise($order, 'https://app.test/cb');

    echo json_encode(['order_id' => $order->id, 'reference' => $init['reference']]);
PHP);

        $this->assertSame(
            0,
            $result[0]['exit'],
            'Seeding the webhook fixture failed: ' . ($result[0]['err'] ?? ''),
        );

        $decoded = json_decode($result[0]['out'], true);

        $this->assertIsArray($decoded, 'Fixture seeding returned unparseable output: ' . $result[0]['out']);

        return $decoded;
    }

    /**
     * Read committed state directly, outside any of the workers' connections.
     */
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

    public function test_simultaneous_delivery_of_one_charge_verifies_it_once(): void
    {
        $workers = 6;
        $fixture = $this->seedPayingOrder();

        $results = $this->runConcurrently($workers, <<<PHP
    \$order = App\\Models\\Storefront\\StorefrontOrder::find({$fixture['order_id']});
    app(App\\Support\\CurrentOrganization::class)->bind(\$order->organization);

    // The gateway is stubbed so no worker reaches the live Paystack
    // environment — the standing rule for this programme. What is under test is
    // the application's own handling of a repeated verified charge, not the
    // gateway.
    app()->instance(App\\Storefront\\Payments\\PaystackClient::class, new class implements App\\Storefront\\Payments\\PaystackClient {
        public function initializeTransaction(string \$email, int \$amountMinor, string \$reference, string \$currency, ?string \$callbackUrl = null, array \$metadata = []): array
        {
            return ['authorization_url' => 'https://stub', 'access_code' => 'stub', 'reference' => \$reference];
        }

        public function verifyTransaction(string \$reference): array
        {
            return [
                'status' => 'success',
                'amount' => 15000,
                'currency' => 'GHS',
                'reference' => \$reference,
                'raw' => ['reference' => \$reference],
            ];
        }

        public function refundTransaction(string \$transactionReference, ?int \$amountMinor = null, ?string \$reason = null, ?string \$idempotencyKey = null): array
        {
            return ['status' => 'pending', 'provider_reference' => null, 'amount' => null, 'currency' => null, 'raw' => []];
        }
    });

    try {
        app(App\\Storefront\\Services\\PaystackService::class)
            ->handleVerifiedReference('{$fixture['reference']}');
        echo 'HANDLED';
    } catch (Throwable \$e) {
        echo 'ERROR:' . get_class(\$e) . ':' . \$e->getMessage();
    }
PHP, isolation: 'READ COMMITTED');

        foreach ($results as $i => $r) {
            $this->assertSame(
                0,
                $r['exit'],
                "Worker {$i} died rather than handling the webhook: " . ($r['err'] ?? ''),
            );
            $this->assertSame(
                'HANDLED',
                $r['out'],
                "Worker {$i} did not handle the delivery cleanly: {$r['out']}",
            );
        }

        $verified = $this->scalar(
            'SELECT COUNT(*) FROM storefront_order_payments WHERE order_id = ? AND status = ?',
            [$fixture['order_id'], 'verified'],
        );

        $this->assertSame(
            '1',
            (string) $verified,
            "Six simultaneous deliveries of one charge left {$verified} verified payments. "
            . 'The customer paid once.',
        );

        $paymentEvents = $this->scalar(
            'SELECT COUNT(*) FROM storefront_order_events WHERE order_id = ? AND type = ?',
            [$fixture['order_id'], 'payment'],
        );

        $this->assertSame(
            '1',
            (string) $paymentEvents,
            "The order's timeline records {$paymentEvents} payment entries for one charge, so the "
            . 'audit trail claims the customer paid more than once.',
        );
    }
}
