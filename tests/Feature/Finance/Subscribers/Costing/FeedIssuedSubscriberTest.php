<?php

namespace Tests\Feature\Finance\Subscribers\Costing;

use App\Finance\Subscribers\Costing\FeedIssuedSubscriber;
use App\Models\DomainEvent;
use App\Models\FeedIssue;
use App\Models\FeedType;
use App\Models\Finance\Account;
use App\Models\Finance\BiologicalAssetStageBalance;
use App\Models\Finance\BiologicalAssetStageTransfer;
use App\Models\Finance\InventoryAccountMapping;
use App\Models\Finance\JournalBatch;
use App\Models\Finance\JournalLine;
use App\Models\Group;
use App\Services\Feed\IssueFeedToGroupService;
use App\Services\Finance\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class FeedIssuedSubscriberTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        $this->mkAcct('1220', 'Finished Feed Inventory', Account::TYPE_ASSET);
        $this->mkAcct('1230', 'Live Pig Inventory', Account::TYPE_ASSET);
        $this->mkAcct('1360', 'Biological Assets', Account::TYPE_ASSET);

        // Ensure inventory mapping points to the ORG-SCOPED account, not global
        $acct1220 = Account::where('organization_id', $this->org->id)->where('code', '1220')->first();
        InventoryAccountMapping::create([
            'organization_id'      => $this->org->id,
            'item_kind'            => 'finished_feed',
            'category'             => InventoryAccountMapping::DEFAULT_CATEGORY_TOKEN,
            'is_default'           => true,
            'inventory_account_id' => $acct1220->id,
        ]);
    }

    public function test_subscriber_posts_dr_1230_cr_1220(): void
    {
        [$issue, $domainEvent] = $this->issueFeed(100.0, 5.0, 50.0);

        $this->app->make(FeedIssuedSubscriber::class)->handle($domainEvent->id);

        $batch = JournalBatch::query()->where('source_id', $issue->id)->first();
        $this->assertNotNull($batch);

        $lines = JournalLine::query()
            ->whereIn('journal_id', $batch->journals()->pluck('id'))
            ->get();
        $this->assertCount(2, $lines);

        // Note: The debit goes to the stage account (not 1230 clearing account).
        // For fattening pigs, this is typically 1360 (growers) based on age.
        $acct1220Id = Account::where('organization_id', $this->org->id)->where('code', '1220')->value('id');
        $acct1360Id = Account::where('organization_id', $this->org->id)->where('code', '1360')->value('id');
        $dr = $lines->firstWhere('account_id', $acct1360Id);
        $cr = $lines->firstWhere('account_id', $acct1220Id);
        $this->assertNotNull($dr, 'Expected debit line for account 1360 (grower stage) not found');
        $this->assertNotNull($cr, 'Expected credit line for account 1220 not found');
        $this->assertEquals('250.00', $dr->debit);   // 50 × 5
        $this->assertEquals('250.00', $cr->credit);

        // Issue marked posted.
        $issue->refresh();
        $this->assertEquals('posted', $issue->posting_status);
        $this->assertEquals($batch->id, $issue->journal_batch_id);

        $transfer = BiologicalAssetStageTransfer::query()
            ->where('source_domain_event_id', $domainEvent->id)
            ->first();
        $this->assertNotNull($transfer, 'Feed issue should create a biological subledger transfer row');
        $this->assertSame(BiologicalAssetStageTransfer::REASON_FEED_ISSUE, $transfer->reason);
        $this->assertSame($batch->id, $transfer->journal_batch_id);
        $this->assertSame('250.0000', (string) $transfer->amount);

        $balance = BiologicalAssetStageBalance::query()
            ->where('group_id', $issue->group_id)
            ->where('stage_key', 'grower')
            ->first();
        $this->assertNotNull($balance, 'Feed issue should increase the group stage balance');
        $this->assertSame('250.0000', (string) $balance->carrying_value);
    }

    public function test_idempotent_on_retry(): void
    {
        [$issue, $domainEvent] = $this->issueFeed(100.0, 5.0, 50.0);

        $subscriber = $this->app->make(FeedIssuedSubscriber::class);
        $subscriber->handle($domainEvent->id);
        $batchCount = JournalBatch::count();
        $transferCount = BiologicalAssetStageTransfer::query()
            ->where('source_domain_event_id', $domainEvent->id)
            ->count();

        $domainEvent->update(['status' => DomainEvent::STATUS_PENDING]);
        $subscriber->handle($domainEvent->id);

        $this->assertEquals($batchCount, JournalBatch::count());
        $this->assertSame(
            $transferCount,
            BiologicalAssetStageTransfer::query()->where('source_domain_event_id', $domainEvent->id)->count(),
            'Retry must not duplicate biological subledger side effects',
        );
    }

    public function test_zero_cost_issuance_skipped(): void
    {
        [$issue, $domainEvent] = $this->issueFeed(qty: 100.0, unitCost: 0.0, issueQty: 50.0);
        $this->assertEquals('0.0000', (string) $issue->total_cost);

        $this->app->make(FeedIssuedSubscriber::class)->handle($domainEvent->id);

        $this->assertEquals(0, JournalBatch::count());
    }

    // -------- helpers --------

    /**
     * @return array{0: FeedIssue, 1: DomainEvent}
     */
    protected function issueFeed(float $qty, float $unitCost, float $issueQty): array
    {
        $invService = $this->app->make(InventoryService::class);

        $feedType = FeedType::create(['name' => 'Grower Feed']);

        // Seed inventory by recording a feed production.
        $invService->recordFeedProduction(
            feedTypeId:  $feedType->id,
            quantity:    $qty,
            totalCost:   $qty * $unitCost,
            feedBatchId: 1,
        );

        $group = Group::create(['name' => 'G-' . uniqid(), 'type' => 'pen']);

        $issuer = $this->app->make(IssueFeedToGroupService::class);
        $issue = $issuer->execute(
            actor: $this->user,
            data: [
                'group_id'     => $group->id,
                'feed_type_id' => $feedType->id,
                'quantity_kg'  => $issueQty,
            ],
        );

        $event = DomainEvent::query()
            ->where('event_type', 'feed.issued')
            ->where('source_id', $issue->id)
            ->first();

        return [$issue->fresh(), $event];
    }

    protected function mkAcct(string $code, string $name, string $type): Account
    {
        return Account::firstOrCreate(
            ['organization_id' => $this->org->id, 'code' => $code],
            [
                'name'                 => $name,
                'account_type'         => $type,
                'currency_code'        => 'GHS',
                'is_active'            => true,
                'allow_manual_journal' => true,
            ],
        );
    }
}
