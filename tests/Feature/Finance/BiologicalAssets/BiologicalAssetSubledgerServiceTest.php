<?php

namespace Tests\Feature\Finance\BiologicalAssets;

use App\Models\Finance\Account;
use App\Models\Finance\BiologicalAssetStageBalance;
use App\Models\Finance\BiologicalAssetStageTransfer;
use App\Models\Finance\Journal;
use App\Models\Finance\JournalLine;
use App\Models\Group;
use App\Models\PigManagement;
use App\Services\Finance\BiologicalAssets\BiologicalAssetSubledgerService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class BiologicalAssetSubledgerServiceTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected Group $group;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();
        foreach (['1310','1320','1330','1340','1350','1360','1370'] as $code) {
            Account::create([
                'organization_id' => $this->org->id, 'code' => $code, 'name' => "Bio {$code}",
                'account_type' => Account::TYPE_ASSET, 'account_subtype' => 'current_asset',
                'currency_code' => 'GHS', 'is_active' => true, 'is_control' => true, 'allow_manual_journal' => false,
            ]);
        }
        $this->group = Group::create(['name' => 'G', 'type' => 'pen']);
        // Put a grower-age cohort in the group so stageForGroup -> grower (1360).
        foreach ([100, 105] as $age) {
            PigManagement::create([
                'ear_tag_number' => 'T'.uniqid(), 'gender' => 'male', 'stock_type' => 'standard',
                'date_of_birth' => now()->subDays($age)->toDateString(), 'group_id' => $this->group->id,
            ]);
        }
    }

    public function test_record_entry_increments_stage_balance(): void
    {
        $svc = app(BiologicalAssetSubledgerService::class);
        $svc->recordEntry(groupId: $this->group->id, amount: '250.00', weightDelta: '0', reason: 'feed_issue');

        $bal = BiologicalAssetStageBalance::where('group_id', $this->group->id)->where('stage_key', 'grower')->first();
        $this->assertNotNull($bal);
        $this->assertSame('250.0000', $bal->carrying_value);
    }

    public function test_record_exit_decrements_and_clamps_at_zero(): void
    {
        $svc = app(BiologicalAssetSubledgerService::class);
        $svc->recordEntry(groupId: $this->group->id, amount: '250.00');
        $svc->recordExit(groupId: $this->group->id, amount: '300.00', pigCountDelta: 1, weightDelta: '50'); // over-exit

        $bal = BiologicalAssetStageBalance::where('group_id', $this->group->id)->where('stage_key', 'grower')->first();
        $this->assertSame('0.0000', $bal->carrying_value); // clamped, never negative
    }

    public function test_transfer_stage_posts_balanced_journal_and_moves_value(): void
    {
        $svc = app(BiologicalAssetSubledgerService::class);
        // Seed cost in 'weaner' (1350) directly via the subledger model.
        BiologicalAssetStageBalance::create([
            'organization_id' => $this->org->id,
            'group_id'        => $this->group->id,
            'stage_key'       => 'weaner',
            'carrying_value'  => '400.00',
            'pig_count'       => 2,
            'weight_kg'       => '60',
        ]);

        $transfer = $svc->transferStage(
            groupId: $this->group->id, fromStage: 'weaner', toStage: 'grower', actor: $this->user,
        );

        $this->assertNotNull($transfer);

        // Balances moved.
        $weaner = BiologicalAssetStageBalance::where('group_id', $this->group->id)->where('stage_key', 'weaner')->first();
        $grower = BiologicalAssetStageBalance::where('group_id', $this->group->id)->where('stage_key', 'grower')->first();
        $this->assertSame('0.0000', $weaner->carrying_value);
        $this->assertSame('400.0000', $grower->carrying_value);

        // Journal balanced.
        $lines = JournalLine::whereIn('journal_id',
            Journal::whereIn('journal_batch_id', [$transfer->journal_batch_id])->pluck('id'))->get();
        $this->assertEqualsWithDelta(
            $lines->sum(fn ($l) => (float) $l->debit),
            $lines->sum(fn ($l) => (float) $l->credit),
            0.001,
        );
        // Dr 1360 / Cr 1350.
        $dr = $lines->firstWhere(fn ($l) => (float) $l->debit > 0);
        $cr = $lines->firstWhere(fn ($l) => (float) $l->credit > 0);
        $this->assertSame('1360', Account::find($dr->account_id)->code);
        $this->assertSame('1350', Account::find($cr->account_id)->code);
    }

    public function test_transfer_same_stage_is_noop(): void
    {
        $svc = app(BiologicalAssetSubledgerService::class);
        $this->assertNull($svc->transferStage(
            groupId: $this->group->id, fromStage: 'grower', toStage: 'grower', actor: $this->user,
        ));
    }
}
