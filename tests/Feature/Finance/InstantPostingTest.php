<?php

namespace Tests\Feature\Finance;

use App\Models\DomainEvent;
use App\Models\FeedIssue;
use App\Models\FeedType;
use App\Models\Finance\Account;
use App\Models\Finance\InventoryAccountMapping;
use App\Models\Finance\JournalBatch;
use App\Models\Group;
use App\Services\Feed\IssueFeedToGroupService;
use App\Services\Finance\Inventory\InventoryService;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Finance events post straight after the save that creates them commits,
 * with the outbox kept as the retry path.
 */
class InstantPostingTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    private FeedType $feed;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();
        config(['finance.instant_posting.enabled' => true]);

        $this->feed = FeedType::create(['name' => 'Grower Feed']);
        $this->group = Group::create(['name' => 'Pen 1', 'type' => 'pen']);
        app(InventoryService::class)->recordFeedProduction(feedTypeId: $this->feed->id, quantity: 100, totalCost: 500, feedBatchId: 1);
    }

    public function test_a_feeding_is_posted_as_soon_as_it_is_saved(): void
    {
        $this->mapFeedAccounts();

        $issue = DB::transaction(fn () => $this->issue(10));

        $event = $this->event($issue);
        $this->assertSame(DomainEvent::STATUS_PROCESSED, $event->status);
        $this->assertSame(FeedIssue::POSTING_POSTED, $issue->fresh()->posting_status);
        $this->assertNotNull($issue->fresh()->journal_batch_id);
    }

    public function test_nothing_posts_when_the_save_rolls_back(): void
    {
        $this->mapFeedAccounts();

        try {
            DB::transaction(function () {
                $this->issue(10);
                throw new \RuntimeException('Save abandoned');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame(0, FeedIssue::count());
        $this->assertSame(0, DomainEvent::where('event_type', 'feed.issued')->count());
        $this->assertSame(0, JournalBatch::count());
    }

    public function test_a_failed_instant_posting_does_not_fail_the_save_and_is_retried_later(): void
    {
        $this->mapFeedAccounts();
        // Dated in a year with no accounting period, so posting cannot succeed.
        $issue = DB::transaction(fn () => $this->issue(10, '2024-03-01 08:00:00'));

        $this->assertNotNull($issue->fresh(), 'The feeding itself is saved.');
        $event = $this->event($issue);
        $this->assertSame(DomainEvent::STATUS_PENDING, $event->status, 'Back in the outbox for a retry.');
        $this->assertNotNull($event->next_retry_at);
        $this->assertSame(1, (int) $event->processing_attempts);
        $this->assertSame($this->org->id, app(CurrentOrganization::class)->id(), 'The request keeps its organisation.');
    }

    public function test_the_outbox_does_not_post_an_instantly_posted_event_again(): void
    {
        $this->mapFeedAccounts();
        DB::transaction(fn () => $this->issue(10));
        $batches = JournalBatch::count();

        $this->artisan('finance:process-events')->assertSuccessful();

        $this->assertSame($batches, JournalBatch::count());
    }

    public function test_events_wait_for_the_outbox_when_instant_posting_is_off(): void
    {
        config(['finance.instant_posting.enabled' => false]);
        $this->mapFeedAccounts();

        $issue = DB::transaction(fn () => $this->issue(10));

        $this->assertSame(DomainEvent::STATUS_PENDING, $this->event($issue)->status);
        $this->artisan('finance:process-events')->assertSuccessful();
        $this->assertSame(DomainEvent::STATUS_PROCESSED, $this->event($issue)->status);
    }

    private function issue(float $kg, ?string $issuedAt = null): FeedIssue
    {
        return app(IssueFeedToGroupService::class)->execute($this->user, [
            'group_id' => $this->group->id, 'feed_type_id' => $this->feed->id, 'quantity_kg' => $kg, 'issued_at' => $issuedAt,
        ]);
    }

    private function event(FeedIssue $issue): DomainEvent
    {
        return DomainEvent::where('event_type', 'feed.issued')->where('source_id', $issue->id)->firstOrFail();
    }

    private function mapFeedAccounts(): void
    {
        foreach (['1220' => 'Finished Feed Inventory', '1230' => 'Live Pig Inventory', '1360' => 'Growers'] as $code => $name) {
            Account::firstOrCreate(['organization_id' => $this->org->id, 'code' => $code], [
                'name' => $name, 'account_type' => Account::TYPE_ASSET, 'currency_code' => 'GHS',
                'is_active' => true, 'allow_manual_journal' => true,
            ]);
        }
        InventoryAccountMapping::create([
            'organization_id' => $this->org->id, 'item_kind' => 'finished_feed',
            'category' => InventoryAccountMapping::DEFAULT_CATEGORY_TOKEN, 'is_default' => true,
            'inventory_account_id' => Account::where('organization_id', $this->org->id)->where('code', '1220')->value('id'),
        ]);
    }
}
