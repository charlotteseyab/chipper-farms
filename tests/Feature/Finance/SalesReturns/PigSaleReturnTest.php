<?php

namespace Tests\Feature\Finance\SalesReturns;

use App\Domain\Events\Sales\PigSaleRecorded;
use App\Domain\Events\Sales\SalesReturnRecorded;
use App\Domain\Events\Support\SourceReference;
use App\Finance\Subscribers\Sales\PigSaleCogsSubscriber;
use App\Finance\Subscribers\Sales\PigSaleReturnCogsSubscriber;
use App\Models\DomainEvent;
use App\Models\Finance\Account;
use App\Models\Finance\BiologicalAssetStageBalance;
use App\Models\Finance\BiologicalAssetStageTransfer;
use App\Models\Finance\GroupCostMovement;
use App\Models\Finance\GroupCostPool;
use App\Models\Finance\GroupOpeningPoolImport;
use App\Models\Finance\JournalBatch;
use App\Models\Finance\JournalLine;
use App\Models\Group;
use App\Models\PigManagement;
use App\Models\PigSale;
use App\Models\SalesReturn;
use App\Services\DomainEvents\DispatchDomainEvent;
use App\Services\Finance\Costing\GroupCostPoolService;
use App\Services\Sales\RecordSalesReturnService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

/**
 * Pig sales returns — CF-P0-008, phase 3.
 *
 * A pig sale touches THREE subsystems and soft-deletes the animal:
 *
 *   1. the group cost pool
 *   2. the general ledger
 *   3. the biological-asset subledger
 *   4. pigs_management (soft delete)
 *
 * These tests assert that a return moves ALL of them, because reversing any
 * subset is what produces the divergence the original refund service refused to
 * risk. The most valuable assertion here is
 * test_return_restores_every_subsystem_together — a return that reverses the GL
 * but leaves the pig deleted gives a herd whose carrying value counts an animal
 * the livestock module says does not exist.
 */
class PigSaleReturnTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected function setUp(): void
    {
        parent::setUp();
        // These tests drive the outbox and individual finance subscribers by
        // hand, so events must stay pending until the test processes them.
        // Instant posting has its own coverage in InstantPostingTest.
        config(['finance.instant_posting.enabled' => false]);
        $this->setUpFinanceFixtures();
        $this->mkAcct('1230', 'Live Pig Inventory', Account::TYPE_ASSET);
        $this->mkAcct('5110', 'Cost of Pigs Sold', Account::TYPE_EXPENSE);
    }

    /* ------------------------------------------------------------------ */
    /* Fixtures (mirroring PigSaleCogsSubscriberTest so sale and return    */
    /* are built by identical machinery)                                   */
    /* ------------------------------------------------------------------ */

    protected function mkAcct(string $code, string $name, string $type): Account
    {
        return Account::firstOrCreate(
            ['organization_id' => $this->org->id, 'code' => $code],
            ['name' => $name, 'account_type' => $type, 'currency_code' => 'GHS',
             'is_active' => true, 'allow_manual_journal' => true],
        );
    }

    /** @return array{0:Group, 1:PigManagement} */
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

    /**
     * A fully-posted pig sale: pool decremented, GL posted, subledger exited,
     * pig soft-deleted.
     *
     * @return array{0:Group, 1:PigManagement, 2:PigSale}
     */
    protected function soldPig(): array
    {
        [$group, $pig] = $this->seedPigInGroup();
        $this->seedPool($group, 50, '2000', '5000'); // basis 2.5/kg

        $sale = PigSale::create([
            'pig_id'          => $pig->id,
            'sale_date'       => '2027-06-15',
            'sale_price'      => 1000.0,
            'sale_weight'     => 100.0,  // COGS = 100 x 2.5 = 250
            'weight_type'     => 'live',
            'price_per_kg'    => 10.0,
            'payment_method'  => 'cash',
            'payment_status'  => 'paid',
            'organization_id' => $this->org->id,
        ]);

        $event = $this->app->make(DispatchDomainEvent::class)->dispatch(new PigSaleRecorded(
            source: new SourceReference(
                module: 'sales', sourceType: PigSale::class,
                sourceId: (int) $sale->id, organizationId: $this->org->id,
            ),
            occurredAt: CarbonImmutable::parse($sale->sale_date),
            dispatchedByUserId: $this->user->id,
            pigId: (int) $pig->id,
            pigCode: (string) ($pig->code ?? $pig->ear_tag_number ?? ''),
            saleWeight: 100.0, weightType: 'live', pricePerKg: 10.0,
            saleTotal: 1000.0, salePurpose: 'commercial',
            paymentStatus: 'paid', paymentMethod: 'cash',
            buyerName: 'Buyer Ltd', transportCost: null,
        ));

        $this->app->make(PigSaleCogsSubscriber::class)->handle($event->id);

        // The sale spine soft-deletes the pig; this fixture builds the sale
        // directly, so replicate that here.
        $pig->fresh()->delete();

        return [$group, $pig, $sale->fresh()];
    }

    protected function runReturn(SalesReturn $return): SalesReturn
    {
        $event = DomainEvent::where('event_type', SalesReturnRecorded::TYPE)
            ->where('source_id', $return->id)->firstOrFail();

        $this->app->make(PigSaleReturnCogsSubscriber::class)->handle($event->id);

        return $return->fresh();
    }

    protected function poolValue(Group $group): string
    {
        return (string) (GroupCostPool::where('group_id', $group->id)->value('total_carrying_value') ?? '0');
    }

    protected function poolCount(Group $group): int
    {
        return (int) (GroupCostPool::where('group_id', $group->id)->value('on_hand_pig_count') ?? 0);
    }

    /* ------------------------------------------------------------------ */
    /* The headline test                                                   */
    /* ------------------------------------------------------------------ */

    /**
     * All four effects reverse together. This is the property the whole finding
     * is about — a partial reversal is worse than none, because it leaves the
     * herd's carrying value, the cost pool, the GL and the livestock records
     * disagreeing with each other and nothing reports it.
     */
    public function test_return_restores_every_subsystem_together(): void
    {
        [$group, $pig, $sale] = $this->soldPig();

        $this->assertSame('250.0000', (string) $sale->cogs_amount, 'Sale COGS should be 250.');
        $poolAfterSale  = $this->poolValue($group);
        $countAfterSale = $this->poolCount($group);
        $this->assertTrue(PigManagement::withTrashed()->find($pig->id)->trashed(), 'Sale soft-deletes the pig.');

        $return = app(RecordSalesReturnService::class)->record(
            sale: $sale, actor: $this->user, quantity: '1', reason: 'buyer returned the animal',
        );
        $return = $this->runReturn($return);

        // 1. Cost pool — value and headcount both back.
        $this->assertSame(
            bcadd($poolAfterSale, '250', 4),
            bcadd($this->poolValue($group), '0', 4),
            'The group cost pool did not get the 250 back.',
        );
        $this->assertSame(
            $countAfterSale + 1,
            $this->poolCount($group),
            'The group headcount did not increase on return.',
        );

        // 2. General ledger — Dr live-pig asset / Cr COGS.
        $this->assertNotNull($return->cogs_reversal_batch_id, 'No reversal batch was posted.');
        $lines = $this->batchLines($return->cogs_reversal_batch_id);
        $this->assertBalanced($lines);
        $this->assertTrue(
            $this->hasCredit($lines, '5110', '250.00'),
            'COGS (5110) must be credited 250.00 on return.',
        );

        // 3. Biological-asset subledger.
        $transfer = BiologicalAssetStageTransfer::query()
            ->where('reason', BiologicalAssetStageTransfer::REASON_SALES_RETURN)
            ->first();
        $this->assertNotNull($transfer, 'A biological-asset entry must be recorded on return.');
        $this->assertSame('250.0000', (string) $transfer->amount);
        $this->assertSame($return->cogs_reversal_batch_id, $transfer->journal_batch_id);

        // 4. The animal is back.
        $this->assertFalse(
            PigManagement::withTrashed()->find($pig->id)->trashed(),
            'The pig is still soft-deleted. The herd now carries value for an animal the '
            . 'livestock module says does not exist.',
        );
        $this->assertTrue($return->pig_restored);
        $this->assertSame(SalesReturn::STATUS_POSTED, $return->status);
    }

    /* ------------------------------------------------------------------ */
    /* Cost basis                                                          */
    /* ------------------------------------------------------------------ */

    /**
     * The return puts back exactly what the sale took, read from the sale —
     * not recomputed from the pool's current per-kg basis, which has moved.
     */
    public function test_return_uses_the_cost_the_sale_charged_not_the_current_basis(): void
    {
        [$group, , $sale] = $this->soldPig();

        // Move the pool's basis by adding cheap weight, so a recomputed COGS
        // would differ materially from the 250 originally charged.
        $this->app->make(GroupCostPoolService::class)->recordFeedIssue(
            groupId: $group->id, totalCost: '100', feedIssueId: 9911,
            occurredAt: CarbonImmutable::parse('2027-06-20'),
        );

        $return = app(RecordSalesReturnService::class)->record(
            sale: $sale, actor: $this->user, quantity: '1', reason: 'returned later',
        );
        $return = $this->runReturn($return);

        // Compared numerically: the stored decimal scale is a schema detail and
        // asserting its string form makes the test brittle to it.
        $this->assertSame(
            0,
            bccomp((string) $return->cogs_reversed_amount, '250', 4),
            'The return reversed a recomputed amount instead of the 250 the sale actually '
            . 'charged, leaving the pool permanently out by the difference.',
        );
    }

    /* ------------------------------------------------------------------ */
    /* restock = false                                                     */
    /* ------------------------------------------------------------------ */

    /**
     * The animal did not come back — died in transit, or the customer kept it.
     * Cost stays consumed and the pig stays sold; only revenue is refunded.
     */
    public function test_non_restock_return_leaves_cost_consumed_and_pig_sold(): void
    {
        [$group, $pig, $sale] = $this->soldPig();

        $poolAfterSale = $this->poolValue($group);

        $return = app(RecordSalesReturnService::class)->record(
            sale: $sale, actor: $this->user, quantity: '1',
            reason: 'animal died in transit', restock: false,
        );
        $return = $this->runReturn($return);

        $this->assertNull(
            $return->cogs_reversal_batch_id,
            'An animal that never came back must not credit COGS.',
        );
        $this->assertSame($poolAfterSale, $this->poolValue($group), 'The pool must be untouched.');
        $this->assertTrue(
            PigManagement::withTrashed()->find($pig->id)->trashed(),
            'A pig that did not return must stay sold.',
        );
        $this->assertFalse($return->pig_restored);
    }

    /* ------------------------------------------------------------------ */
    /* Idempotency                                                         */
    /* ------------------------------------------------------------------ */

    /**
     * The outbox can redeliver. A replayed return must not credit COGS twice,
     * add the animal to the pool twice, or double its carrying value.
     */
    public function test_replaying_a_pig_return_is_idempotent(): void
    {
        [$group, , $sale] = $this->soldPig();

        $return = app(RecordSalesReturnService::class)->record(
            sale: $sale, actor: $this->user, quantity: '1', reason: 'returned',
        );

        $event = DomainEvent::where('event_type', SalesReturnRecorded::TYPE)
            ->where('source_id', $return->id)->firstOrFail();

        $this->app->make(PigSaleReturnCogsSubscriber::class)->handle($event->id);

        $poolAfterFirst  = $this->poolValue($group);
        $countAfterFirst = $this->poolCount($group);
        $batchesAfter    = JournalBatch::count();

        $event->update(['status' => DomainEvent::STATUS_PENDING]);
        $this->app->make(PigSaleReturnCogsSubscriber::class)->handle($event->id);

        $this->assertSame($poolAfterFirst, $this->poolValue($group), 'Replay added cost twice.');
        $this->assertSame($countAfterFirst, $this->poolCount($group), 'Replay added the animal twice.');
        $this->assertSame($batchesAfter, JournalBatch::count(), 'Replay posted a second batch.');

        $this->assertSame(
            1,
            BiologicalAssetStageTransfer::where('reason', BiologicalAssetStageTransfer::REASON_SALES_RETURN)->count(),
            'Replay created a second biological-asset entry.',
        );
        $this->assertSame(
            1,
            GroupCostMovement::where('source_type', GroupCostMovement::SOURCE_SALES_RETURN)
                ->where('source_id', $return->id)->count(),
            'Replay created a second pool movement.',
        );
    }

    /* ------------------------------------------------------------------ */
    /* Guards                                                              */
    /* ------------------------------------------------------------------ */

    public function test_a_pig_cannot_be_returned_twice(): void
    {
        [, , $sale] = $this->soldPig();
        $svc = app(RecordSalesReturnService::class);

        $svc->record(sale: $sale, actor: $this->user, quantity: '1', reason: 'first');

        // A pig sale is one animal; a second return would put a second animal
        // into the herd that was never sold.
        $this->expectException(\InvalidArgumentException::class);
        $svc->record(sale: $sale, actor: $this->user, quantity: '1', reason: 'second');
    }

    public function test_return_is_marked_failed_when_the_sale_never_posted_cogs(): void
    {
        [, $pig] = $this->seedPigInGroup();

        // A sale with no COGS posting — the outbox has not drained.
        $sale = PigSale::create([
            'pig_id'          => $pig->id,
            'sale_date'       => '2027-06-15',
            'sale_price'      => 500.0,
            'sale_weight'     => 60.0,
            'organization_id' => $this->org->id,
        ]);

        $return = app(RecordSalesReturnService::class)->record(
            sale: $sale, actor: $this->user, quantity: '1', reason: 'early return',
        );
        $return = $this->runReturn($return);

        $this->assertSame(SalesReturn::STATUS_FAILED, $return->status);
        $this->assertSame('original_cogs_not_posted', $return->posting_error);
        $this->assertNull($return->cogs_reversal_batch_id);
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    protected function batchLines(?int $batchId)
    {
        $batch = JournalBatch::find($batchId);

        return $batch
            ? JournalLine::whereIn('journal_id', $batch->journals()->pluck('id'))->get()
            : collect();
    }

    protected function assertBalanced($lines): void
    {
        $this->assertEqualsWithDelta(
            $lines->sum(fn ($l) => (float) $l->debit),
            $lines->sum(fn ($l) => (float) $l->credit),
            0.001,
            'The reversal batch does not balance.',
        );
    }

    protected function hasCredit($lines, string $code, string $amount): bool
    {
        $acctId = (int) Account::where('organization_id', $this->org->id)
            ->where('code', $code)->value('id');

        foreach ($lines as $l) {
            if ((int) $l->account_id === $acctId
                && bccomp((string) $l->credit, $amount, 2) === 0) {
                return true;
            }
        }

        return false;
    }
}
