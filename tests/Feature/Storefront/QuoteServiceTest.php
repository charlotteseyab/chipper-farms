<?php

namespace Tests\Feature\Storefront;

use App\Models\Storefront\StorefrontQuote;
use App\Notifications\StorefrontQuoteRequestedNotification;
use App\Storefront\Services\Exceptions\InvalidQuoteTransitionException;
use App\Storefront\Services\QuoteService;
use App\Storefront\Support\QuoteRequestData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Mail;
use App\Mail\TemplatedEmail;

class QuoteServiceTest extends StorefrontTestCase
{
    use RefreshDatabase;

    protected function service(): QuoteService
    {
        return $this->app->make(QuoteService::class);
    }

    protected function sampleData(): QuoteRequestData
    {
        return QuoteRequestData::fromArray([
            'contact_name'  => 'Kofi Mensah',
            'contact_email' => 'kofi@example.com',
            'source'        => 'bulk',
            'items'         => [
                ['description' => '20 weaner piglets', 'quantity' => '20', 'quantity_unit' => 'head'],
                ['description' => '5 bags grower feed', 'quantity' => '5', 'quantity_unit' => 'bag'],
            ],
        ]);
    }

    public function test_guest_request_creates_quote_with_items_and_notifies_staff(): void
    {
        Notification::fake();
        $this->makeUser('salesperson'); // a recipient

        $quote = $this->service()->request($this->sampleData(), null);

        $this->assertSame(StorefrontQuote::STATUS_REQUESTED, $quote->status);
        $this->assertSame('Kofi Mensah', $quote->contact_name);
        $this->assertNull($quote->counterparty_id);
        $this->assertCount(2, $quote->items);
        $this->assertNotNull($quote->reference);

        Notification::assertSentTo(
            \App\Models\User::where('role', 'salesperson')->get(),
            StorefrontQuoteRequestedNotification::class
        );
    }

    public function test_authenticated_customer_request_links_counterparty(): void
    {
        Notification::fake();
        $customer = $this->makeUser('customer');

        $quote = $this->service()->request($this->sampleData(), $customer);

        $this->assertSame($customer->id, $quote->customer_user_id);
        $this->assertNotNull($quote->counterparty_id);
    }

    public function test_full_happy_path_transitions(): void
    {
        Notification::fake();
        $actor = $this->makeUser('salesperson');
        $quote = $this->service()->request($this->sampleData(), null);

        $quote = $this->service()->markReviewing($quote, $actor);
        $this->assertSame(StorefrontQuote::STATUS_REVIEWING, $quote->status);

        $quote = $this->service()->provideQuote($quote, $actor, '1500.00', now()->addDays(7)->toDateString(), 'Bulk rate');
        $this->assertSame(StorefrontQuote::STATUS_QUOTED, $quote->status);
        $this->assertSame('1500.00', $quote->quoted_total);
        $this->assertNotNull($quote->responded_at);

        $quote = $this->service()->accept($quote, $actor);
        $this->assertSame(StorefrontQuote::STATUS_ACCEPTED, $quote->status);
    }

    public function test_priced_quote_sends_customer_email(): void
    {
        Notification::fake();
        Mail::fake();
        $actor = $this->makeUser('admin');
        $quote = $this->service()->request($this->sampleData(), null);

        $this->service()->provideQuote($quote, $actor, '1500.00', now()->addDays(7)->toDateString());

        Mail::assertSent(TemplatedEmail::class, fn (TemplatedEmail $mail) => $mail->hasTo('kofi@example.com'));
    }

    public function test_illegal_transition_throws(): void
    {
        Notification::fake();
        $actor = $this->makeUser('salesperson');
        $quote = $this->service()->request($this->sampleData(), null);

        // Cannot accept a quote that hasn't been quoted.
        $this->expectException(InvalidQuoteTransitionException::class);
        $this->service()->accept($quote, $actor);
    }

    public function test_declined_quote_is_terminal(): void
    {
        Notification::fake();
        $actor = $this->makeUser('salesperson');
        $quote = $this->service()->request($this->sampleData(), null);

        $quote = $this->service()->decline($quote, $actor, 'Out of stock');
        $this->assertSame(StorefrontQuote::STATUS_DECLINED, $quote->status);
        $this->assertTrue($quote->isTerminal());

        $this->expectException(InvalidQuoteTransitionException::class);
        $this->service()->markReviewing($quote, $actor);
    }

    public function test_staff_see_the_in_app_alert_at_once_while_the_email_waits_on_the_queue(): void
    {
        config(['queue.default' => 'database']);
        $staff = $this->makeUser('salesperson');

        $quote = $this->service()->request($this->sampleData(), null);

        $this->assertSame(1, $staff->notifications()->where('type', StorefrontQuoteRequestedNotification::class)->count(),
            'The bell alert is written without a queue worker.');
        $queued = \Illuminate\Support\Facades\DB::table('jobs')->pluck('payload')
            ->filter(fn ($payload) => str_contains($payload, 'StorefrontQuoteRequestedNotification'));
        $this->assertCount(1, $queued, 'Only the email is left on the queue.');
        $this->assertStringContainsString($quote->reference, $staff->notifications()->first()->data['message']);

        // A worker can rebuild and send the queued email.
        $this->artisan('queue:work', ['--once' => true, '--stop-when-empty' => true])->assertSuccessful();
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('failed_jobs')->count());
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('jobs')->count());
    }
}
