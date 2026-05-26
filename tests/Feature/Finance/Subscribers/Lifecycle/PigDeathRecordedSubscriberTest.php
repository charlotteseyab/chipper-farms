<?php

namespace Tests\Feature\Finance\Subscribers\Lifecycle;

use App\Domain\Events\Lifecycle\PigDeathRecorded;
use App\Domain\Events\Support\SourceReference;
use App\Finance\Subscribers\Lifecycle\PigDeathRecordedSubscriber;
use App\Models\DomainEvent;
use App\Models\Finance\Account;
use App\Models\Finance\GroupOpeningPoolImport;
use App\Models\Finance\JournalBatch;
use App\Models\Finance\JournalLine;
use App\Models\Finance\PigMortalityWriteoff;
use App\Models\Group;
use App\Models\PigDeath;
use App\Models\PigManagement;
use App\Services\DomainEvents\DispatchDomainEvent;
use App\Services\Finance\Costing\GroupCostPoolService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class PigDeathRecordedSubscriberTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        $this->mkAcct('1230', 'Live Pig Inventory', Account::TYPE_ASSET);
        $this->mkAcct('5320', 'Mortality Loss',     Account::TYPE_EXPENSE);
    }

    public function test_posts_dr_5320_cr_1230_at_writeoff_amount(): void
    {
        [$group, $pig] = $this->seedPigInGroup();
        $this->seedPool($group, 50, '2000', '5000');  // basis 2.5

        $death = PigDeath::create([
            'pig_id'          => $pig->id,
            'death_date'      => '2027-06-01',
            'death_cause'     => 'disease',
            'final_weight'    => 80.0,  // → writeoff = 200
            'organization_id' => $this->org->id,
            'recorded_by'     => $this->user->id,
        ]);

        $event = $this->dispatchDeathEvent($death, $pig);
        $this->app->make(PigDeathRecordedSubscriber::class)->handle($event->id);

        // Writeoff row created.
        $w = PigMortalityWriteoff::where('pig_death_id', $death->id)->first();
        $this->assertNotNull($w);
        $this->assertEquals('200.0000', (string) $w->writeoff_amount);
        $this->assertFalse((bool) $w->used_group_average_weight);

        // GL posted.
        $batch = JournalBatch::query()->where('source_id', $death->id)->first();
        $this->assertNotNull($batch);

        $lines = JournalLine::query()->whereIn('journal_id', $batch->journals()->pluck('id'))->get();
        $this->assertCount(2, $lines);
        $dr = $lines->firstWhere('account_id', Account::where('code', '5320')->value('id'));
        $cr = $lines->firstWhere('account_id', Account::where('code', '1230')->value('id'));
        $this->assertEquals('200.00', $dr->debit);
        $this->assertEquals('200.00', $cr->credit);

        // PigDeath stamped.
        $death->refresh();
        $this->assertTrue((bool) $death->is_posted);
    }

    public function test_skips_when_pig_has_no_group(): void
    {
        // Create pig without group_id.
        $pig = PigManagement::create([
            'ear_tag_number' => 'NG-' . uniqid(),
            'gender'         => 'male',
            'stock_type'     => 'fattening',
            'date_of_birth'  => '2027-01-01',
        ]);

        $death = PigDeath::create([
            'pig_id'          => $pig->id,
            'death_date'      => '2027-06-01',
            'death_cause'     => 'natural',
            'final_weight'    => 50.0,
            'organization_id' => $this->org->id,
            'recorded_by'     => $this->user->id,
        ]);

        $event = $this->dispatchDeathEvent($death, $pig);
        $this->app->make(PigDeathRecordedSubscriber::class)->handle($event->id);

        $this->assertEquals(0, PigMortalityWriteoff::count());
        $this->assertEquals(0, JournalBatch::count());
        $this->assertEquals(DomainEvent::STATUS_SKIPPED, $event->fresh()->status);
    }

    public function test_falls_back_to_group_average_when_final_weight_null(): void
    {
        [$group, $pig] = $this->seedPigInGroup();
        $this->seedPool($group, 50, '2000', '5000');  // avg weight 40, basis 2.5

        $death = PigDeath::create([
            'pig_id'          => $pig->id,
            'death_date'      => '2027-06-01',
            'death_cause'     => 'unknown',
            'final_weight'    => null,
            'organization_id' => $this->org->id,
            'recorded_by'     => $this->user->id,
        ]);

        $event = $this->dispatchDeathEvent($death, $pig);
        $this->app->make(PigDeathRecordedSubscriber::class)->handle($event->id);

        $w = PigMortalityWriteoff::where('pig_death_id', $death->id)->first();
        $this->assertNotNull($w);
        $this->assertTrue((bool) $w->used_group_average_weight);
        $this->assertEquals('40.0000', (string) $w->pig_weight_at_death_kg);
        $this->assertEquals('100.0000', (string) $w->writeoff_amount);  // 40 × 2.5
    }

    public function test_idempotent_on_retry(): void
    {
        [$group, $pig] = $this->seedPigInGroup();
        $this->seedPool($group, 50, '2000', '5000');

        $death = PigDeath::create([
            'pig_id'          => $pig->id,
            'death_date'      => '2027-06-01',
            'death_cause'     => 'disease',
            'final_weight'    => 80.0,
            'organization_id' => $this->org->id,
            'recorded_by'     => $this->user->id,
        ]);

        $event = $this->dispatchDeathEvent($death, $pig);
        $subscriber = $this->app->make(PigDeathRecordedSubscriber::class);

        $subscriber->handle($event->id);
        $batchCount = JournalBatch::count();
        $woCount    = PigMortalityWriteoff::count();

        $event->update(['status' => DomainEvent::STATUS_PENDING]);
        $subscriber->handle($event->id);

        $this->assertEquals($batchCount, JournalBatch::count());
        $this->assertEquals($woCount,    PigMortalityWriteoff::count());
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

    protected function dispatchDeathEvent(PigDeath $death, PigManagement $pig): DomainEvent
    {
        $dispatcher = $this->app->make(DispatchDomainEvent::class);
        return $dispatcher->dispatch(new PigDeathRecorded(
            source: new SourceReference(
                module:         'lifecycle',
                sourceType:     PigDeath::class,
                sourceId:       (int) $death->id,
                organizationId: $this->org->id,
            ),
            occurredAt:          CarbonImmutable::parse($death->death_date),
            dispatchedByUserId:  $this->user->id,
            pigId:               (int) $pig->id,
            pigCode:             (string) ($pig->code ?? $pig->ear_tag_number ?? ''),
            ageAtDeathDays:      null,
            deathCause:          (string) $death->death_cause,
            finalWeight:         $death->final_weight !== null ? (float) $death->final_weight : null,
            disposalMethod:      'burial',
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
