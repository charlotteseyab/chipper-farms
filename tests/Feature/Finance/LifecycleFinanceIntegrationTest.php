<?php

namespace Tests\Feature\Finance;

use App\Domain\Events\Lifecycle\LitterWeaned;
use App\Domain\Events\Lifecycle\PigBirthRecorded;
use App\Domain\Events\Lifecycle\PigLifecycleStageChanged;
use App\Domain\Events\Sales\PigSaleRecorded;
use App\Domain\Events\Support\SourceReference;
use App\Finance\Subscribers\Sales\PigSaleCogsSubscriber;
use App\Jobs\ProcessPendingDomainEventsJob;
use App\Models\DomainEvent as DomainEventRecord;
use App\Models\Farrowing;
use App\Models\Finance\Account;
use App\Models\Finance\BiologicalAssetStageBalance;
use App\Models\Finance\BiologicalAssetStageTransfer;
use App\Models\Finance\GroupCostMovement;
use App\Models\Finance\GroupCostPool;
use App\Models\Finance\JournalBatch;
use App\Models\Finance\JournalLine;
use App\Models\Group;
use App\Models\PigManagement;
use App\Models\PigSale;
use App\Services\DomainEvents\DispatchDomainEvent;
use App\Services\Finance\BiologicalAssets\BiologicalAssetStageResolver;
use App\Services\Stock\PigStockService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LifecycleFinanceIntegrationTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        Account::firstOrCreate([
            'organization_id' => $this->org->id,
            'code' => '3010',
        ], [
            'name' => 'Owner Capital',
            'account_type' => Account::TYPE_EQUITY,
            'currency_code' => 'GHS',
            'is_active' => true,
            'allow_manual_journal' => true,
        ]);
    }

    public function test_pig_creation_with_acquisition_cost_posts_finance_and_subledger_once(): void
    {
        $this->actingAs($this->user);
        $group = Group::create(['name' => 'Acquisition Group', 'type' => 'pen']);

        $pig = $this->app->make(PigStockService::class)->create([
            'ear_tag_number' => 'ACQ-' . uniqid(),
            'breed' => 'Large White',
            'gender' => 'male',
            'stock_type' => 'standard',
            'date_of_birth' => '2026-01-01',
            'father_id' => null,
            'mother_id' => null,
            'room_id' => null,
            'group_id' => $group->id,
            'current_weight_kg_cache' => 45.0,
            'acquisition_cost' => 300.00,
            'acquisition_date' => '2026-06-15',
            'acquisition_source' => 'purchase',
        ]);

        $event = DomainEventRecord::query()
            ->where('event_type', 'pig.acquired')
            ->where('source_id', $pig->id)
            ->first();
        $this->assertNotNull($event);

        ProcessPendingDomainEventsJob::dispatchSync(10);

        $event->refresh();
        $pig->refresh();
        $this->assertSame(DomainEventRecord::STATUS_PROCESSED, $event->status);
        $this->assertNotNull($pig->acquisition_journal_batch_id);

        $batch = JournalBatch::find($pig->acquisition_journal_batch_id);
        $this->assertNotNull($batch);
        $this->assertSame('auto_pig_acquisition', $batch->batch_type);

        $stage = $this->app->make(BiologicalAssetStageResolver::class)->stageForPig($pig);
        $stageAccountId = Account::query()
            ->where('organization_id', $this->org->id)
            ->where('code', config("biological_assets.stages.{$stage}.account_code"))
            ->value('id');
        $capitalAccountId = Account::query()
            ->where('organization_id', $this->org->id)
            ->where('code', '3010')
            ->value('id');

        $lines = JournalLine::query()
            ->whereIn('journal_id', $batch->journals()->pluck('id'))
            ->get();
        $this->assertSame('300.00', (string) $lines->firstWhere('account_id', $stageAccountId)->debit);
        $this->assertSame('300.00', (string) $lines->firstWhere('account_id', $capitalAccountId)->credit);

        $transfer = BiologicalAssetStageTransfer::query()
            ->where('source_domain_event_id', $event->id)
            ->first();
        $this->assertNotNull($transfer);
        $this->assertSame(BiologicalAssetStageTransfer::REASON_ACQUISITION, $transfer->reason);
        $this->assertSame('300.0000', (string) $transfer->amount);
        $this->assertSame(1, $transfer->pig_count);

        $balance = BiologicalAssetStageBalance::query()
            ->where('group_id', $group->id)
            ->where('stage_key', $stage)
            ->first();
        $this->assertNotNull($balance);
        $this->assertSame('300.0000', (string) $balance->carrying_value);

        $pool = GroupCostPool::query()->where('group_id', $group->id)->first();
        $this->assertNotNull($pool, 'Acquisition should seed the group cost pool used by sale COGS');
        $this->assertSame(1, (int) $pool->on_hand_pig_count);
        $this->assertSame('300.0000', (string) $pool->total_carrying_value);
        $this->assertSame('45.0000', (string) $pool->total_weight_kg);

        $movement = GroupCostMovement::query()
            ->where('source_type', GroupCostMovement::SOURCE_PIG_ACQUISITION)
            ->where('source_id', $pig->id)
            ->first();
        $this->assertNotNull($movement);
        $this->assertSame(GroupCostMovement::EVENT_ACQUISITION, $movement->event_type);
        $this->assertSame($batch->id, $movement->journal_batch_id);

        $batchCount = JournalBatch::count();
        $transferCount = BiologicalAssetStageTransfer::query()
            ->where('source_domain_event_id', $event->id)
            ->count();
        $movementCount = GroupCostMovement::query()
            ->where('source_type', GroupCostMovement::SOURCE_PIG_ACQUISITION)
            ->where('source_id', $pig->id)
            ->count();

        $event->update(['status' => DomainEventRecord::STATUS_PENDING]);
        ProcessPendingDomainEventsJob::dispatchSync(10);

        $this->assertSame($batchCount, JournalBatch::count());
        $this->assertSame(
            $transferCount,
            BiologicalAssetStageTransfer::query()->where('source_domain_event_id', $event->id)->count(),
        );
        $this->assertSame(
            $movementCount,
            GroupCostMovement::query()
                ->where('source_type', GroupCostMovement::SOURCE_PIG_ACQUISITION)
                ->where('source_id', $pig->id)
                ->count(),
            'Acquisition retry must not duplicate group cost pool movement',
        );

        $sale = PigSale::create([
            'organization_id' => $this->org->id,
            'pig_id' => $pig->id,
            'sale_date' => '2026-06-16',
            'sale_weight' => 45.0,
            'weight_type' => 'live',
            'sale_price' => 1350.0,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);
        $saleEvent = $this->dispatch(new PigSaleRecorded(
            source: new SourceReference('sales', PigSale::class, $sale->id, $this->org->id),
            occurredAt: CarbonImmutable::parse('2026-06-16'),
            dispatchedByUserId: $this->user->id,
            pigId: $pig->id,
            pigCode: (string) $pig->code,
            saleWeight: 45.0,
            weightType: 'live',
            pricePerKg: 30.0,
            saleTotal: (float) $sale->sale_price,
            salePurpose: 'market',
            paymentStatus: 'paid',
            paymentMethod: 'cash',
            buyerName: null,
            transportCost: null,
        ));

        $this->app->make(PigSaleCogsSubscriber::class)->handle($saleEvent->id);

        $sale->refresh();
        $this->assertSame('posted', $sale->cogs_posting_status);
        $this->assertSame('300.0000', (string) $sale->cogs_amount);
        $this->assertSame('0.0000', (string) $pool->fresh()->total_carrying_value);
        $this->assertSame(0, (int) $pool->fresh()->on_hand_pig_count);
    }

    public function test_birth_records_new_piglets_in_piglet_stage_not_sow_stage(): void
    {
        $group = Group::create(['name' => 'Farrowing Group', 'type' => 'pen']);
        $sow = PigManagement::create([
            'organization_id' => $this->org->id,
            'ear_tag_number' => 'SOW-' . uniqid(),
            'gender' => 'female',
            'stock_type' => 'breeding',
            'date_of_birth' => '2024-01-01',
            'group_id' => $group->id,
        ]);
        $farrowing = Farrowing::create([
            'sow_id' => $sow->id,
            'farrowing_date' => '2026-06-15',
            'total_piglets_born' => 9,
            'live_births' => 8,
            'stillbirths' => 1,
            'average_birth_weight' => 1.25,
            'recorded_by' => $this->user->id,
            'status' => 'active',
        ]);

        $event = $this->dispatch(new PigBirthRecorded(
            source: new SourceReference('lifecycle', Farrowing::class, $farrowing->id, $this->org->id),
            occurredAt: CarbonImmutable::parse('2026-06-15'),
            dispatchedByUserId: $this->user->id,
            farrowingId: $farrowing->id,
            sowId: $sow->id,
            groupId: $group->id,
            liveBirths: 8,
            stillbirths: 1,
            averageBirthWeightKg: 1.25,
        ));

        ProcessPendingDomainEventsJob::dispatchSync(10);

        $event->refresh();
        $this->assertSame(DomainEventRecord::STATUS_PROCESSED, $event->status);

        $piglet = BiologicalAssetStageBalance::query()
            ->where('group_id', $group->id)
            ->where('stage_key', 'piglet')
            ->first();
        $this->assertNotNull($piglet, 'Birth finance should explicitly recognise newborn piglets in the piglet stage');
        $this->assertSame(8, $piglet->pig_count);
        $this->assertSame('10.0000', (string) $piglet->weight_kg);

        $sowStage = BiologicalAssetStageBalance::query()
            ->where('group_id', $group->id)
            ->where('stage_key', 'sow')
            ->first();
        $this->assertNull($sowStage, 'New piglet count must not be added to the sow stage');

        $transferCount = BiologicalAssetStageTransfer::query()
            ->where('source_domain_event_id', $event->id)
            ->count();

        $event->update(['status' => DomainEventRecord::STATUS_PENDING]);
        ProcessPendingDomainEventsJob::dispatchSync(10);

        $this->assertSame(
            $transferCount,
            BiologicalAssetStageTransfer::query()->where('source_domain_event_id', $event->id)->count(),
            'Birth retry must not duplicate piglet-stage recognition',
        );
        $this->assertSame(8, $piglet->fresh()->pig_count);
        $this->assertSame('10.0000', (string) $piglet->fresh()->weight_kg);
    }

    public function test_litter_weaning_moves_value_from_piglet_to_weaner_once(): void
    {
        $group = Group::create(['name' => 'Weaning Group', 'type' => 'pen']);
        $farrowing = Farrowing::create([
            'sow_id' => PigManagement::create([
                'organization_id' => $this->org->id,
                'ear_tag_number' => 'SOW-' . uniqid(),
                'gender' => 'female',
                'stock_type' => 'breeding',
                'date_of_birth' => '2024-01-01',
                'group_id' => $group->id,
            ])->id,
            'farrowing_date' => '2026-06-01',
            'total_piglets_born' => 8,
            'live_births' => 8,
            'stillbirths' => 0,
            'average_birth_weight' => 1.5,
            'recorded_by' => $this->user->id,
            'status' => 'active',
        ]);

        BiologicalAssetStageBalance::create([
            'organization_id' => $this->org->id,
            'group_id' => $group->id,
            'stage_key' => 'piglet',
            'carrying_value' => '800.0000',
            'pig_count' => 8,
            'weight_kg' => '12.0000',
        ]);

        $event = $this->dispatch(new LitterWeaned(
            source: new SourceReference('lifecycle', Farrowing::class, $farrowing->id, $this->org->id),
            occurredAt: CarbonImmutable::parse('2026-06-20'),
            dispatchedByUserId: $this->user->id,
            farrowingId: $farrowing->id,
            sowId: $farrowing->sow_id,
            groupId: $group->id,
            survivingPiglets: 6,
            averageBirthWeightKg: 1.5,
        ));

        ProcessPendingDomainEventsJob::dispatchSync(10);

        $event->refresh();
        $this->assertSame(DomainEventRecord::STATUS_PROCESSED, $event->status);

        $transfer = BiologicalAssetStageTransfer::query()
            ->where('source_domain_event_id', $event->id)
            ->first();
        $this->assertNotNull($transfer);
        $this->assertSame(BiologicalAssetStageTransfer::REASON_WEANING, $transfer->reason);
        $this->assertSame('piglet', $transfer->from_stage_key);
        $this->assertSame('weaner', $transfer->to_stage_key);
        $this->assertSame('600.0000', (string) $transfer->amount);
        $this->assertNotNull($transfer->journal_batch_id);

        $piglet = BiologicalAssetStageBalance::query()
            ->where('group_id', $group->id)
            ->where('stage_key', 'piglet')
            ->first();
        $weaner = BiologicalAssetStageBalance::query()
            ->where('group_id', $group->id)
            ->where('stage_key', 'weaner')
            ->first();
        $this->assertSame('200.0000', (string) $piglet->carrying_value);
        $this->assertSame(2, $piglet->pig_count);
        $this->assertSame('600.0000', (string) $weaner->carrying_value);
        $this->assertSame(6, $weaner->pig_count);

        $batchCount = JournalBatch::count();
        $transferCount = BiologicalAssetStageTransfer::query()
            ->where('source_domain_event_id', $event->id)
            ->count();

        $event->update(['status' => DomainEventRecord::STATUS_PENDING]);
        ProcessPendingDomainEventsJob::dispatchSync(10);

        $this->assertSame($batchCount, JournalBatch::count());
        $this->assertSame(
            $transferCount,
            BiologicalAssetStageTransfer::query()->where('source_domain_event_id', $event->id)->count(),
        );
    }

    public function test_lifecycle_stage_change_moves_one_pig_value_and_retry_is_safe(): void
    {
        $group = Group::create(['name' => 'Promotion Group', 'type' => 'pen']);
        $pig = PigManagement::create([
            'organization_id' => $this->org->id,
            'ear_tag_number' => 'PROMO-' . uniqid(),
            'gender' => 'female',
            'stock_type' => 'breeding',
            'date_of_birth' => '2025-01-01',
            'group_id' => $group->id,
        ]);

        BiologicalAssetStageBalance::create([
            'organization_id' => $this->org->id,
            'group_id' => $group->id,
            'stage_key' => 'finisher',
            'carrying_value' => '1000.0000',
            'pig_count' => 2,
            'weight_kg' => '180.0000',
        ]);

        $event = $this->dispatch(new PigLifecycleStageChanged(
            source: new SourceReference('lifecycle', PigManagement::class, $pig->id, $this->org->id),
            occurredAt: CarbonImmutable::parse('2026-06-25'),
            dispatchedByUserId: $this->user->id,
            pigId: $pig->id,
            pigCode: (string) $pig->code,
            fromStockType: 'standard',
            toStockType: 'breeding',
            reason: 'Promotion test',
        ));

        ProcessPendingDomainEventsJob::dispatchSync(10);

        $event->refresh();
        $this->assertSame(DomainEventRecord::STATUS_PROCESSED, $event->status);

        $transfer = BiologicalAssetStageTransfer::query()
            ->where('source_domain_event_id', $event->id)
            ->first();
        $this->assertNotNull($transfer);
        $this->assertSame('finisher', $transfer->from_stage_key);
        $this->assertSame('sow', $transfer->to_stage_key);
        $this->assertSame('500.0000', (string) $transfer->amount);
        $this->assertNotNull($transfer->journal_batch_id);

        $finisher = BiologicalAssetStageBalance::query()
            ->where('group_id', $group->id)
            ->where('stage_key', 'finisher')
            ->first();
        $sow = BiologicalAssetStageBalance::query()
            ->where('group_id', $group->id)
            ->where('stage_key', 'sow')
            ->first();
        $this->assertSame('500.0000', (string) $finisher->carrying_value);
        $this->assertSame(1, $finisher->pig_count);
        $this->assertSame('500.0000', (string) $sow->carrying_value);
        $this->assertSame(1, $sow->pig_count);

        $batchCount = JournalBatch::count();
        $transferCount = BiologicalAssetStageTransfer::query()
            ->where('source_domain_event_id', $event->id)
            ->count();

        $event->update(['status' => DomainEventRecord::STATUS_PENDING]);
        ProcessPendingDomainEventsJob::dispatchSync(10);

        $this->assertSame($batchCount, JournalBatch::count());
        $this->assertSame(
            $transferCount,
            BiologicalAssetStageTransfer::query()->where('source_domain_event_id', $event->id)->count(),
            'Duplicate event retry must not create a second stage-transfer side effect',
        );
        $this->assertSame('500.0000', (string) $finisher->fresh()->carrying_value);
        $this->assertSame('500.0000', (string) $sow->fresh()->carrying_value);
    }

    private function dispatch(\App\Domain\Events\Contracts\DomainEvent $event): DomainEventRecord
    {
        return $this->app->make(DispatchDomainEvent::class)->dispatch($event);
    }
}
