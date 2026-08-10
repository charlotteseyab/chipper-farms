<?php

namespace Tests\Feature\Storefront;

use App\Models\Storefront\StorefrontOrder;
use App\Services\Finance\Overview\NeedsAttentionService;
use App\Storefront\Services\CartService;
use App\Storefront\Services\CheckoutService;
use App\Storefront\Services\OrderService;
use App\Storefront\Support\CheckoutData;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Phase 12 — the storefront/finance boundary (blueprint §41).
 *
 * §41 is a PRESERVATION requirement, not a build one. It names the integration
 * pattern that already works — order confirmed → domain event → subscriber →
 * existing sale spine — and then states the rule that keeps it working:
 *
 *   "Do not add direct GL posting inside storefront checkout, payment callback,
 *    or order controllers."
 *
 * A rule stated in a document constrains nobody. The first test below is that
 * rule as a regression guard: it reads the storefront source and fails if any
 * of it reaches for the posting engine directly.
 *
 * WHY THIS MATTERS MORE THAN IT LOOKS
 * -----------------------------------
 * The tempting shortcut is real. A developer adding, say, a payment-received
 * journal inside the Paystack callback would produce something that works in
 * testing and double-posts in production the moment the subscriber also runs —
 * and the storefront's own history has an example of the opposite failure
 * (IMP-044: every storefront pig sale silently failing to reach finance),
 * which is exactly what makes a direct-posting "fix" attractive to someone
 * debugging under pressure.
 */
class StorefrontFinanceBoundaryTest extends StorefrontTestCase
{
    use RefreshDatabase;

    /**
     * The storefront must reach finance through domain events, never by posting
     * itself.
     */
    public function test_no_storefront_code_posts_to_the_general_ledger_directly(): void
    {
        $forbidden = [
            'PostingEngine',
            'JournalBatchDraft',
            'JournalBatch::create',
            'JournalLine::create',
        ];

        $searched = 0;
        $offences = [];

        foreach ($this->storefrontSourceFiles() as $file) {
            $searched++;
            $contents = file_get_contents($file) ?: '';

            // Comments discuss the rule; only code may break it. Strip block and
            // line comments before searching, or this test would fail on the
            // docblock explaining why the rule exists.
            $code = preg_replace('#/\*.*?\*/|//[^\n]*#s', '', $contents) ?? $contents;

            foreach ($forbidden as $needle) {
                if (str_contains($code, $needle)) {
                    $offences[] = str_replace(base_path() . '/', '', $file) . " uses {$needle}";
                }
            }
        }

        $this->assertGreaterThan(
            20,
            $searched,
            'The file sweep found almost nothing, so a passing result would mean the '
            . 'paths moved rather than that the rule holds.',
        );

        $this->assertSame(
            [],
            $offences,
            "Blueprint §41: the storefront must not post to the GL directly.\n"
            . "Route the effect through a domain event and a finance subscriber instead —\n"
            . "posting here double-posts the moment the subscriber runs too.\n\n"
            . implode("\n", $offences),
        );
    }

    /**
     * The pattern §41 asks to preserve, exercised end to end: confirming an
     * order emits the event that carries it into the sale spine, and the
     * storefront itself writes no journal.
     */
    public function test_confirmation_reaches_finance_through_the_event_not_the_storefront(): void
    {
        $listing = $this->makePublishedListing([
            'kind'               => \App\Models\Storefront\StorefrontListing::KIND_FEED,
            'pricing_basis'      => \App\Models\Storefront\StorefrontListing::BASIS_FIXED,
            'fixed_price'        => '250.00',
            'quantity_available' => '5',
        ]);

        $buyer = $this->makeUser('customer');
        $this->app->make(CartService::class)->addItem($buyer, $listing, '1');

        $order = $this->app->make(CheckoutService::class)->checkout($buyer, CheckoutData::fromArray([
            'payment_method' => 'cod', 'fulfilment_method' => 'pickup',
        ]));

        // Checkout posts nothing. That is the point: money reaches the ledger
        // when staff confirm, not when a basket is submitted.
        $this->assertSame(0, \App\Models\Finance\JournalBatch::count());

        $this->app->make(OrderService::class)->confirm($order->fresh(), $this->makeUser('admin'));

        // And the confirmation raised the domain event rather than posting.
        $this->assertDatabaseHas('domain_events', [
            'event_type' => \App\Domain\Events\Sales\StorefrontOrderConfirmed::TYPE,
        ]);
    }

    /* ---------------------------------------------------------------- */
    /* Settlement discrepancies are visible (IMP-027)                    */
    /* ---------------------------------------------------------------- */

    /**
     * Money taken at the gateway that never reached the books is the serious
     * case: the farm has been paid and its accounts do not show it.
     */
    public function test_a_paid_order_that_never_reached_finance_is_flagged_as_critical(): void
    {
        $this->makeOrder([
            'payment_status'         => StorefrontOrder::PAY_PAYSTACK_VERIF,
            'status'                 => StorefrontOrder::STATUS_CONFIRMED,
            'finance_posting_status' => 'failed',
        ]);

        $item = $this->attentionItem('storefront.settlement_discrepancies');

        $this->assertNotNull($item, 'A verified payment that never posted must be surfaced.');
        $this->assertSame(NeedsAttentionService::SEVERITY_CRITICAL, $item['severity']);
        $this->assertStringContainsString('not reached the books', $item['message']);
    }

    /**
     * A buyer who wandered off mid-payment is untidy, not urgent. Ranking it
     * with the money-missing case would train people to ignore both.
     */
    public function test_an_abandoned_payment_is_flagged_but_not_as_critical(): void
    {
        $this->makeOrder([
            'payment_status' => StorefrontOrder::PAY_PAYSTACK_PEND,
            'status'         => StorefrontOrder::STATUS_PENDING_PAYMENT,
            'placed_at'      => now()->subDays(3),
        ]);

        $item = $this->attentionItem('storefront.settlement_discrepancies');

        $this->assertNotNull($item);
        $this->assertSame(NeedsAttentionService::SEVERITY_INFO, $item['severity']);
        $this->assertStringContainsString('waiting for payment', $item['message']);
    }

    public function test_a_healthy_shop_raises_nothing(): void
    {
        $this->makeOrder([
            'payment_status'         => StorefrontOrder::PAY_PAYSTACK_VERIF,
            'status'                 => StorefrontOrder::STATUS_COMPLETED,
            'finance_posting_status' => 'posted',
        ]);

        // A pending payment from ten minutes ago is a buyer still typing their
        // card number, not a discrepancy.
        $this->makeOrder([
            'payment_status' => StorefrontOrder::PAY_PAYSTACK_PEND,
            'status'         => StorefrontOrder::STATUS_PENDING_PAYMENT,
            'placed_at'      => now()->subMinutes(10),
        ]);

        $this->assertNull($this->attentionItem('storefront.settlement_discrepancies'));
    }

    /* ---------------------------------------------------------------- */

    protected function attentionItem(string $key): ?array
    {
        foreach ($this->app->make(NeedsAttentionService::class)->items($this->org) as $item) {
            if ($item['key'] === $key) {
                return $item;
            }
        }

        return null;
    }

    protected function makeOrder(array $overrides = []): StorefrontOrder
    {
        static $sequence = 0;

        return StorefrontOrder::create(array_merge([
            'organization_id'   => $this->org->id,
            'reference'         => 'SO-B-' . (++$sequence),
            'status'            => StorefrontOrder::STATUS_CONFIRMED,
            'payment_status'    => StorefrontOrder::PAY_PAID,
            'payment_method'    => StorefrontOrder::METHOD_PAYSTACK,
            'fulfilment_method' => StorefrontOrder::FULFIL_PICKUP,
            'subtotal' => '250.00', 'delivery_fee' => '0.00', 'total' => '250.00',
            'currency_code' => 'GHS', 'placed_at' => now(),
        ], $overrides));
    }

    /**
     * Every PHP file the storefront owns.
     *
     * @return list<string>
     */
    protected function storefrontSourceFiles(): array
    {
        $roots = [
            app_path('Storefront'),
            app_path('Http/Controllers/Storefront'),
            app_path('Http/Requests/Storefront'),
            app_path('Models/Storefront'),
        ];

        $files = [];

        foreach ($roots as $root) {
            if (! is_dir($root)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }
}
