<?php

namespace Tests\Feature\Finance\Subscribers\Inventory;

use App\Domain\Events\Feed\FeedBatchProduced;
use App\Domain\Events\Support\SourceReference;
use App\Finance\Subscribers\Inventory\FeedBatchProducedSubscriber;
use App\Models\DomainEvent;
use App\Models\FeedBatch;
use App\Models\FeedType;
use App\Models\Finance\Account;
use App\Models\Finance\InventoryAccountMapping;
use App\Models\Finance\InventoryMovement;
use App\Models\Finance\JournalBatch;
use App\Models\Finance\JournalLine;
use App\Models\RawMaterial;
use App\Services\DomainEvents\DispatchDomainEvent;
use App\Services\Feed\ProduceFeedBatchService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class FeedBatchProducedSubscriberTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected Account $rmAcct;
    protected Account $ffAcct;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        $this->rmAcct = $this->mkAcct('1210', 'RM Inventory',       Account::TYPE_ASSET);
        $this->ffAcct = $this->mkAcct('1220', 'Finished Feed Inv',  Account::TYPE_ASSET);

        InventoryAccountMapping::create([
            'organization_id'      => $this->org->id,
            'item_kind'            => 'raw_material',
            'category'             => InventoryAccountMapping::DEFAULT_CATEGORY_TOKEN,
            'is_default'           => true,
            'inventory_account_id' => $this->rmAcct->id,
        ]);
        InventoryAccountMapping::create([
            'organization_id'      => $this->org->id,
            'item_kind'            => 'finished_feed',
            'category'             => InventoryAccountMapping::DEFAULT_CATEGORY_TOKEN,
            'is_default'           => true,
            'inventory_account_id' => $this->ffAcct->id,
        ]);
    }

    public function test_feed_batch_posts_dr_finished_feed_cr_raw_materials(): void
    {
        // Set up a real recipe and produce a batch through the service so
        // movements + items + WAC all exist before subscriber runs.
        $maize = $this->mkRm('Maize');
        $soya  = $this->mkRm('Soya');

        $produceService = $this->app->make(ProduceFeedBatchService::class);

        // Need opening stock for ingredients.
        $invService = $this->app->make(\App\Services\Finance\Inventory\InventoryService::class);
        $maize->update(['quantity_in_stock' => 1000, 'unit_cost' => 3]);
        $soya->update(['quantity_in_stock' => 1000, 'unit_cost' => 5]);
        $invService->recordReceive(InventoryMovement::ITEM_KIND_RAW_MATERIAL, $maize->id, 1000.0, 3.0, InventoryMovement::SOURCE_STOCK_LOG, 9001);
        $invService->recordReceive(InventoryMovement::ITEM_KIND_RAW_MATERIAL, $soya->id,  1000.0, 5.0, InventoryMovement::SOURCE_STOCK_LOG, 9002);

        $feedType = FeedType::create(['name' => 'Grower Feed']);
        // 80% maize, 20% soya per 100kg.
        $feedType->recipes()->create(['raw_material_id' => $maize->id, 'quantity_per_100kg' => 80]);
        $feedType->recipes()->create(['raw_material_id' => $soya->id,  'quantity_per_100kg' => 20]);

        $result = $produceService->execute($this->user, [
            'feed_type_id'  => $feedType->id,
            'batch_date'    => now()->toDateString(),
            'batch_size_kg' => 100.0,
            'notes'         => null,
        ]);

        $batch = $result['feed_batch'];

        // Total cost should be 80*3 + 20*5 = 240 + 100 = 340.
        $this->assertEquals(340.0, (float) $batch->total_cost);

        // The service dispatched FeedBatchProduced — find it.
        $domainEvent = DomainEvent::query()
            ->where('event_type', FeedBatchProduced::TYPE)
            ->where('source_id', $batch->id)
            ->first();
        $this->assertNotNull($domainEvent);

        // Run subscriber.
        $this->app->make(FeedBatchProducedSubscriber::class)->handle($domainEvent->id);

        $batchPosting = JournalBatch::where('source_id', $batch->id)->first();
        $this->assertNotNull($batchPosting);

        $lines = JournalLine::query()
            ->whereIn('journal_id', $batchPosting->journals()->pluck('id'))
            ->get();

        // 1 debit (finished feed at 340) + 2 credits (maize 240 + soya 100) = 3 lines.
        $this->assertCount(3, $lines);

        $debit = $lines->where('debit', '!=', '0.00')->first();
        $this->assertEquals($this->ffAcct->id, $debit->account_id);
        $this->assertEquals('340.00', $debit->debit);

        $credits = $lines->where('credit', '!=', '0.00')->values();
        $total = $credits->sum(fn ($l) => (float) $l->credit);
        $this->assertEquals(340.0, $total);
    }

    public function test_idempotent_on_retry(): void
    {
        $maize = $this->mkRm('Maize');
        $invService = $this->app->make(\App\Services\Finance\Inventory\InventoryService::class);
        $maize->update(['quantity_in_stock' => 200, 'unit_cost' => 3]);
        $invService->recordReceive(InventoryMovement::ITEM_KIND_RAW_MATERIAL, $maize->id, 200.0, 3.0, InventoryMovement::SOURCE_STOCK_LOG, 100);

        $feedType = FeedType::create(['name' => 'Layer Feed']);
        $feedType->recipes()->create(['raw_material_id' => $maize->id, 'quantity_per_100kg' => 100]);

        $produceService = $this->app->make(ProduceFeedBatchService::class);
        $result = $produceService->execute($this->user, [
            'feed_type_id'  => $feedType->id,
            'batch_date'    => now()->toDateString(),
            'batch_size_kg' => 100.0,
            'notes'         => null,
        ]);

        $domainEvent = DomainEvent::where('event_type', FeedBatchProduced::TYPE)->first();
        $subscriber  = $this->app->make(FeedBatchProducedSubscriber::class);

        $subscriber->handle($domainEvent->id);
        $batchCount = JournalBatch::count();

        // Retry: re-mark pending and run again. Should not create another batch.
        $domainEvent->update(['status' => DomainEvent::STATUS_PENDING]);
        $subscriber->handle($domainEvent->id);

        $this->assertEquals($batchCount, JournalBatch::count());
    }

    protected function mkRm(string $name): RawMaterial
    {
        return RawMaterial::create([
            'name' => $name . '-' . uniqid(), 'unit' => 'kg',
            'quantity_in_stock' => 0, 'unit_cost' => 0, 'reorder_level' => 0,
        ]);
    }

    protected function mkAcct(string $code, string $name, string $type): Account
    {
        return Account::firstOrCreate(
            ['organization_id' => $this->org->id, 'code' => $code],
            ['name' => $name, 'account_type' => $type, 'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => true],
        );
    }
}
