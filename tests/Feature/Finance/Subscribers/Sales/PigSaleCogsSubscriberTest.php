<?php

namespace Tests\Feature\Finance\Subscribers\Sales;

use App\Domain\Events\Sales\PigSaleRecorded;
use App\Domain\Events\Support\SourceReference;
use App\Finance\Subscribers\Sales\PigSaleCogsSubscriber;
use App\Models\DomainEvent;
use App\Models\Finance\Account;
use App\Models\Finance\GroupOpeningPoolImport;
use App\Models\Finance\JournalBatch;
use App\Models\Finance\JournalLine;
use App\Models\Group;
use App\Models\PigManagement;
use App\Models\PigSale;
use App\Services\DomainEvents\DispatchDomainEvent;
use App\Services\Finance\Costing\GroupCostPoolService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class PigSaleCogsSubscriberTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    /**
     * NOTE: This test exercises the subscriber's update to pig_sales
     * COGS columns (cogs_amount, cogs_posting_status, etc.).
     *
     * Those columns are added to the pig_sales table by the
     * 130600 migration, but the PigSale model must ALSO have them
     * in $fillable for the subscriber's $sale->update([...]) calls
     * to actually persist.
     *
     * This depends on _pig_sale_phase_5_patch.php having been
     * hand-merged into the live PigSale model. If you see this test
     * failing with $sale->cogs_amount staying null even though the
     * subscriber ran, the patch hasn't been applied — see
     * deployment/PHASE_5_DEPLOY.md §6.1.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();
        $this->mkAcct('1230', 'Live Pig Inventory', Account::TYPE_ASSET);
        $this->mkAcct('5110', 'Cost of Pigs Sold',   Account::TYPE_EXPENSE);
    }

    public function test_posts_dr_5110_cr_1230_proportional_to_sale_weight(): void
    {
        [$group, $pig] = $this->seedPigInGroup();
        $this->seedPool($group, 50, '2000', '5000');  // basis 2.5

        $sale = PigSale::create([
            'pig_id'         => $pig->id,
            'sale_date'      => '2027-06-15',
            'sale_price'     => 1000.0,
            'sale_weight'    => 100.0,  // → COGS = 250
            'weight_type'    => 'live',
            'price_per_kg'   => 10.0,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'organization_id' => $this->org->id,
        ]);

        $event = $this->dispatchSaleEvent($sale, $pig);
        $this->app->make(PigSaleCogsSubscriber::class)->handle($event->id);

        $sale->refresh();
        $this->assertEquals('250.0000', (string) $sale->cogs_amount);
        $this->assertEquals('posted',   $sale->cogs_posting_status);
        $this->assertNotNull($sale->cogs_journal_batch_id);
        $this->assertEquals($group->id, $sale->cogs_group_id);

        $batch = JournalBatch::find($sale->cogs_journal_batch_id);
        $lines = JournalLine::query()->whereIn('journal_id', $batch->journals()->pluck('id'))->get();
        $this->assertCount(2, $lines);

        $dr = $lines->firstWhere('account_id', Account::where('code', '5110')->value('id'));
        $cr = $lines->firstWhere('account_id', Account::where('code', '1230')->value('id'));
        $this->assertEquals('250.00', $dr->debit);
        $this->assertEquals('250.00', $cr->credit);
    }

    public function test_skips_when_pig_has_no_group(): void
    {
        $pig = PigManagement::create([
            'ear_tag_number' => 'NG-' . uniqid(),
            'gender'         => 'male',
            'stock_type'     => 'fattening',
            'date_of_birth'  => '2027-01-01',
        ]);

        $sale = PigSale::create([
            'pig_id'      => $pig->id,
            'sale_date'   => '2027-06-15',
            'sale_price'  => 500.0,
            'sale_weight' => 60.0,
            'organization_id' => $this->org->id,
        ]);

        $event = $this->dispatchSaleEvent($sale, $pig);
        $this->app->make(PigSaleCogsSubscriber::class)->handle($event->id);

        $sale->refresh();
        $this->assertEquals('skipped', $sale->cogs_posting_status);
        $this->assertNull($sale->cogs_journal_batch_id);
        $this->assertEquals(0, JournalBatch::count());
    }

    public function test_skips_when_no_sale_weight_and_no_pig_cache(): void
    {
        [$group, $pig] = $this->seedPigInGroup();
        $this->seedPool($group, 50, '2000', '5000');

        $sale = PigSale::create([
            'pig_id'      => $pig->id,
            'sale_date'   => '2027-06-15',
            'sale_price'  => 100.0,
            'sale_weight' => null,
            'organization_id' => $this->org->id,
        ]);

        $event = $this->dispatchSaleEvent($sale, $pig);
        $this->app->make(PigSaleCogsSubscriber::class)->handle($event->id);

        $sale->refresh();
        $this->assertEquals('skipped', $sale->cogs_posting_status);
    }

    public function test_uses_pig_weight_cache_when_sale_weight_null(): void
    {
        [$group, $pig] = $this->seedPigInGroup();
        $this->seedPool($group, 50, '2000', '5000');
        $pig->current_weight_kg_cache = 90.0;
        $pig->save();

        $sale = PigSale::create([
            'pig_id'      => $pig->id,
            'sale_date'   => '2027-06-15',
            'sale_price'  => 800.0,
            'sale_weight' => null,
            'organization_id' => $this->org->id,
        ]);

        $event = $this->dispatchSaleEvent($sale, $pig);
        $this->app->make(PigSaleCogsSubscriber::class)->handle($event->id);

        $sale->refresh();
        $this->assertEquals('90.0000', (string) $sale->cogs_pig_weight_kg);
        $this->assertEquals('225.0000', (string) $sale->cogs_amount);  // 90 × 2.5
    }

    public function test_idempotent_on_retry(): void
    {
        [$group, $pig] = $this->seedPigInGroup();
        $this->seedPool($group, 50, '2000', '5000');

        $sale = PigSale::create([
            'pig_id'      => $pig->id,
            'sale_date'   => '2027-06-15',
            'sale_price'  => 1000.0,
            'sale_weight' => 100.0,
            'organization_id' => $this->org->id,
        ]);

        $event = $this->dispatchSaleEvent($sale, $pig);
        $subscriber = $this->app->make(PigSaleCogsSubscriber::class);

        $subscriber->handle($event->id);
        $batchCount = JournalBatch::count();

        $event->update(['status' => DomainEvent::STATUS_PENDING]);
        $subscriber->handle($event->id);

        $this->assertEquals($batchCount, JournalBatch::count());
    }

    // -------- helpers --------

    /** @return array{0: Group, 1: PigManagement} */
    protected function seedPigInGroup(): array
    {
        $group = Group::create(['name' => 'G-' . uniqid(), 'type' => 'pen']);
        $pig = PigManagement::create([
            'ear_tag_number' => 'P-' . uniqid(),
            'gender'         => 'male',
            'stock_type'     => 'fattening',
            'date_of_birth'  => '2027-01-01',
        ]);
        $pig->group_id = $group->id;
        $pig->save();
        return [$group, $pig->fresh()];
    }

    protected function seedPool(Group $group, int $count, string $weight, string $value): void
    {
        $import = GroupOpeningPoolImport::create([
            'organization_id'      => $this->org->id,
            'import_mode'          => 'manual',
            'import_hash'          => bin2hex(random_bytes(32)),
            'cutover_date'         => '2027-04-30',
            'row_count'            => 1,
            'status'               => 'pending',
            'performed_by_user_id' => $this->user->id,
        ]);
        $this->app->make(GroupCostPoolService::class)
            ->recordOpening($group->id, $count, $weight, $value, $import, 1);
    }

    protected function dispatchSaleEvent(PigSale $sale, PigManagement $pig): DomainEvent
    {
        $dispatcher = $this->app->make(DispatchDomainEvent::class);
        return $dispatcher->dispatch(new PigSaleRecorded(
            source: new SourceReference(
                module:         'sales',
                sourceType:     PigSale::class,
                sourceId:       (int) $sale->id,
                organizationId: $this->org->id,
            ),
            occurredAt:         CarbonImmutable::parse($sale->sale_date),
            dispatchedByUserId: $this->user->id,
            pigId:              (int) $pig->id,
            pigCode:            (string) ($pig->code ?? $pig->ear_tag_number ?? ''),
            saleWeight:         (float) ($sale->sale_weight ?? 0),
            weightType:         (string) ($sale->weight_type ?? 'live'),
            pricePerKg:         (float) ($sale->price_per_kg ?? 0),
            saleTotal:          (float) $sale->sale_price,
            salePurpose:        (string) ($sale->sale_purpose ?? 'commercial'),
            paymentStatus:      (string) ($sale->payment_status ?? 'paid'),
            paymentMethod:      $sale->payment_method,
            buyerName:          $sale->buyer_name,
            transportCost:      $sale->transport_cost !== null ? (float) $sale->transport_cost : null,
        ));
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
