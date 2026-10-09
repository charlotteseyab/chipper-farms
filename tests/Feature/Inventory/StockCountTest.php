<?php

namespace Tests\Feature\Inventory;

use App\Finance\Subscribers\Inventory\FinishedFeedCountedSubscriber;
use App\Models\DomainEvent;
use App\Models\FeedType;
use App\Models\Finance\Account;
use App\Models\Finance\InventoryAccountMapping;
use App\Models\Finance\JournalBatch;
use App\Models\Finance\JournalLine;
use App\Models\Permission;
use App\Models\RawMaterial;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Services\Feed\FeedPlanningService;
use App\Services\Finance\Inventory\InventoryService;
use App\Services\Inventory\AdjustRawMaterialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class StockCountTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    private int $source = 910000;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();
        $this->account('1220', 'Finished Feed Inventory', Account::TYPE_ASSET);
        $this->account('5300', 'Inventory Adjustments', Account::TYPE_EXPENSE);
        InventoryAccountMapping::create([
            'organization_id'      => $this->org->id,
            'item_kind'            => 'finished_feed',
            'category'             => InventoryAccountMapping::DEFAULT_CATEGORY_TOKEN,
            'is_default'           => true,
            'inventory_account_id' => Account::where('organization_id', $this->org->id)->where('code', '1220')->value('id'),
        ]);
    }

    public function test_ledger_movements_keep_the_raw_material_stock_column_in_step(): void
    {
        $maize = RawMaterial::create(['name' => 'Maize', 'unit' => 'kg', 'quantity_in_stock' => 999, 'unit_cost' => 2]);

        $this->receive('raw_material', $maize->id, 100, 2);
        $this->assertEquals(100, $maize->fresh()->quantity_in_stock, 'The ledger quantity replaces any drifted column value');

        DB::transaction(fn () => app(InventoryService::class)->recordUsage('raw_material', $maize->id, 30, 'stock_log', ++$this->source));
        $this->assertEquals(70, $maize->fresh()->quantity_in_stock);
    }

    public function test_adjustment_is_measured_and_valued_against_the_ledger(): void
    {
        $maize = RawMaterial::create(['name' => 'Maize', 'unit' => 'kg', 'quantity_in_stock' => 0, 'unit_cost' => 9]);
        $this->receive('raw_material', $maize->id, 100, 2);
        DB::table('raw_materials')->where('id', $maize->id)->update(['quantity_in_stock' => 500]); // simulate old drift

        $result = app(AdjustRawMaterialService::class)->execute($maize->fresh(), $this->user, ['adjustment_type' => 'deduct', 'quantity' => 10]);

        $this->assertEquals(-10, $result['delta']);
        $this->assertEquals(90, $result['newQuantity']);
        $this->assertEquals(2, $result['log']->unit_cost, 'Valued at average cost, not the last price on the material');
        $this->assertEquals(90, $maize->fresh()->quantity_in_stock);
    }

    public function test_count_sets_stock_to_counted_and_records_each_line(): void
    {
        $this->grant('manage_raw_materials', 'manage_feed_production');
        $maize = RawMaterial::create(['name' => 'Maize', 'unit' => 'kg', 'quantity_in_stock' => 0, 'unit_cost' => 2]);
        $salt = RawMaterial::create(['name' => 'Salt', 'unit' => 'kg', 'quantity_in_stock' => 0, 'unit_cost' => 1]);
        $grower = FeedType::create(['name' => 'Grower']);
        $this->receive('raw_material', $maize->id, 100, 2);
        $this->receive('raw_material', $salt->id, 10, 1);
        $this->receive('finished_feed', $grower->id, 50, 4);

        $response = $this->actingAs($this->user)->post(route('stock-counts.store'), [
            'submission_key' => (string) Str::uuid(),
            'reference'      => 'Month-end',
            'counts'         => [
                'raw_material'  => [$maize->id => '95', $salt->id => '10'],
                'finished_feed' => [$grower->id => '48.5'],
            ],
        ]);

        $count = StockCount::firstOrFail();
        $response->assertRedirect(route('stock-counts.show', $count))->assertSessionHas('success', 'Count saved. Stock updated for 2 items that differed.');

        $planning = app(FeedPlanningService::class);
        $this->assertEquals(95, $planning->available($this->org->id, 'raw_material', $maize->id));
        $this->assertEquals(48.5, $planning->available($this->org->id, 'finished_feed', $grower->id));
        $this->assertEquals(95, $maize->fresh()->quantity_in_stock);

        $lines = $count->lines()->get()->keyBy('item_name');
        $this->assertEquals(-5, $lines['Maize']->difference_qty);
        $this->assertEquals(-10, $lines['Maize']->value_delta);
        $this->assertNotNull($lines['Maize']->raw_material_stock_log_id);
        $this->assertEquals(0, $lines['Salt']->difference_qty);
        $this->assertSame(StockCountLine::POSTING_NOT_REQUIRED, $lines['Salt']->posting_status);
        $this->assertEquals(-6, $lines['Grower']->value_delta, '1.5 kg short at GH₵ 4 average cost');

        $this->actingAs($this->user)->get(route('stock-counts.show', $count))->assertOk()->assertSee('Matches')->assertSee('-5 kg');
    }

    public function test_finished_feed_shortage_posts_a_loss_to_inventory_adjustments(): void
    {
        $this->grant('manage_feed_production');
        $grower = FeedType::create(['name' => 'Grower']);
        $this->receive('finished_feed', $grower->id, 50, 4);

        $this->actingAs($this->user)->post(route('stock-counts.store'), [
            'submission_key' => (string) Str::uuid(),
            'counts'         => ['finished_feed' => [$grower->id => '45']],
        ])->assertSessionHas('success');

        $line = StockCountLine::firstOrFail();
        $event = DomainEvent::where('event_type', 'finished_feed.counted')->where('source_id', $line->id)->firstOrFail();
        app(FinishedFeedCountedSubscriber::class)->handle($event->id);

        $batch = JournalBatch::where('source_id', $line->id)->firstOrFail();
        $lines = JournalLine::whereIn('journal_id', $batch->journals()->pluck('id'))->get();
        $adjustment = Account::where('organization_id', $this->org->id)->where('code', '5300')->value('id');
        $inventory = Account::where('organization_id', $this->org->id)->where('code', '1220')->value('id');
        $this->assertEquals('20.00', $lines->firstWhere('account_id', $adjustment)->debit);
        $this->assertEquals('20.00', $lines->firstWhere('account_id', $inventory)->credit);
        $this->assertSame(StockCountLine::POSTING_POSTED, $line->fresh()->posting_status);
    }

    public function test_resubmitting_the_same_count_does_not_adjust_twice(): void
    {
        $this->grant('manage_feed_production');
        $grower = FeedType::create(['name' => 'Grower']);
        $this->receive('finished_feed', $grower->id, 50, 4);
        $payload = ['submission_key' => (string) Str::uuid(), 'counts' => ['finished_feed' => [$grower->id => '40']]];

        $this->actingAs($this->user)->post(route('stock-counts.store'), $payload);
        $this->actingAs($this->user)->post(route('stock-counts.store'), $payload);

        $this->assertSame(1, StockCount::count());
        $this->assertEquals(40, app(FeedPlanningService::class)->available($this->org->id, 'finished_feed', $grower->id));
    }

    public function test_each_section_needs_its_own_permission(): void
    {
        $this->grant('manage_feed_production');
        $maize = RawMaterial::create(['name' => 'Maize', 'unit' => 'kg', 'quantity_in_stock' => 0, 'unit_cost' => 2]);

        $this->actingAs($this->user)->get(route('stock-counts.create'))
            ->assertOk()->assertSee('Finished feed')->assertDontSee('Raw materials');

        $this->actingAs($this->user)->post(route('stock-counts.store'), [
            'submission_key' => (string) Str::uuid(),
            'counts'         => ['raw_material' => [$maize->id => '5']],
        ])->assertForbidden();
    }

    public function test_blank_count_is_refused(): void
    {
        $this->grant('manage_feed_production');
        $grower = FeedType::create(['name' => 'Grower']);

        $this->actingAs($this->user)->from(route('stock-counts.create'))->post(route('stock-counts.store'), [
            'submission_key' => (string) Str::uuid(),
            'counts'         => ['finished_feed' => [$grower->id => '']],
        ])->assertSessionHasErrors('lines');
        $this->assertSame(0, StockCount::count());
    }

    public function test_sync_command_reports_then_aligns_drifted_stock_and_leaves_unledgered_stock_alone(): void
    {
        $maize = RawMaterial::create(['name' => 'Maize', 'unit' => 'kg', 'quantity_in_stock' => 0, 'unit_cost' => 2]);
        $old = RawMaterial::create(['name' => 'Old bran', 'unit' => 'kg', 'quantity_in_stock' => 12, 'unit_cost' => 2]);
        $this->receive('raw_material', $maize->id, 100, 2);
        DB::table('raw_materials')->where('id', $maize->id)->update(['quantity_in_stock' => 140]);

        $this->artisan('inventory:sync-raw-material-stock')->expectsOutputToContain('Dry run')->assertSuccessful();
        $this->assertEquals(140, $maize->fresh()->quantity_in_stock);

        $this->artisan('inventory:sync-raw-material-stock', ['--apply' => true])->expectsOutputToContain('Aligned 1 material')->assertSuccessful();
        $this->assertEquals(100, $maize->fresh()->quantity_in_stock);
        $this->assertEquals(12, $old->fresh()->quantity_in_stock, 'Stock with no ledger history needs an opening balance, not a silent zero');
    }

    private function receive(string $kind, int $id, float $qty, float $cost): void
    {
        DB::transaction(fn () => app(InventoryService::class)->recordReceive($kind, $id, $qty, $cost, 'stock_log', ++$this->source));
    }

    private function grant(string ...$names): void
    {
        foreach ($names as $name) {
            $permission = Permission::firstOrCreate(['name' => $name], ['description' => $name, 'module' => 'feed']);
            $this->user->role()->first()->permissions()->syncWithoutDetaching([$permission->id]);
        }
    }

    private function account(string $code, string $name, string $type): void
    {
        Account::firstOrCreate(['organization_id' => $this->org->id, 'code' => $code], [
            'name' => $name, 'account_type' => $type, 'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => true,
        ]);
    }
}
