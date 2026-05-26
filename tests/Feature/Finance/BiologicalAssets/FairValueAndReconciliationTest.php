<?php

namespace Tests\Feature\Finance\BiologicalAssets;

use App\Models\Finance\Account;
use App\Models\Finance\BiologicalAssetStageBalance;
use App\Models\Finance\GroupCostPool;
use App\Models\Group;
use App\Services\Finance\BiologicalAssets\BiologicalAssetReconciliationService;
use App\Services\Finance\BiologicalAssets\FairValueDisclosureService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class FairValueAndReconciliationTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected Group $group;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();
        $this->group = Group::create(['name' => 'G', 'type' => 'pen']);
        config()->set('biological_assets.fair_value.price_per_kg.grower', '15.00');
        config()->set('biological_assets.fair_value.costs_to_sell_pct', '0.05');
    }

    public function test_fair_value_computes_fvlcts(): void
    {
        BiologicalAssetStageBalance::create([
            'organization_id' => $this->org->id, 'group_id' => $this->group->id,
            'stage_key' => 'grower', 'carrying_value' => '500.0000', 'pig_count' => 5, 'weight_kg' => '100.0000',
        ]);

        $d = app(FairValueDisclosureService::class)->generate();

        // weight 100 × 15 × (1 - 0.05) = 1425.
        $grower = collect($d['lines'])->firstWhere('stage_key', 'grower');
        $this->assertNotNull($grower);
        $this->assertSame('1425.0000', $grower['fair_value']);
        $this->assertSame('500.0000', $grower['carrying_value']);
        // difference 1425 - 500 = 925.
        $this->assertSame('925.0000', $grower['difference']);
        $this->assertTrue($grower['reliable']);
    }

    public function test_unreliable_stage_flagged_when_no_price(): void
    {
        config()->set('biological_assets.fair_value.price_per_kg.grower', '0');

        BiologicalAssetStageBalance::create([
            'organization_id' => $this->org->id, 'group_id' => $this->group->id,
            'stage_key' => 'grower', 'carrying_value' => '500.0000', 'pig_count' => 5, 'weight_kg' => '100.0000',
        ]);

        $d = app(FairValueDisclosureService::class)->generate();
        $grower = collect($d['lines'])->firstWhere('stage_key', 'grower');
        $this->assertFalse($grower['reliable']);
        $this->assertTrue($d['meta']['has_unreliable']);
    }

    public function test_reconciliation_passes_when_subledger_matches_pools(): void
    {
        // Stage subledger 500; group cost pool 500; no GL stage postings, no
        // clearing balance => subledger_vs_gl will be 500 vs 0 (FAIL) unless
        // we also have GL. So this test asserts the pools tie + clearing.
        BiologicalAssetStageBalance::create([
            'organization_id' => $this->org->id, 'group_id' => $this->group->id,
            'stage_key' => 'grower', 'carrying_value' => '500.0000', 'pig_count' => 5, 'weight_kg' => '100',
        ]);
        GroupCostPool::create([
            'organization_id' => $this->org->id, 'group_id' => $this->group->id,
            'total_carrying_value' => '500.0000', 'on_hand_pig_count' => 5, 'total_weight_kg' => '100',
        ]);

        $r = app(BiologicalAssetReconciliationService::class)->run(CarbonImmutable::now());

        $this->assertTrue($r['subledger_vs_pools']['reconciled']);
        $this->assertTrue($r['clearing_drained']['reconciled']); // no 1230 postings
    }

    public function test_reconciliation_detects_pool_mismatch(): void
    {
        BiologicalAssetStageBalance::create([
            'organization_id' => $this->org->id, 'group_id' => $this->group->id,
            'stage_key' => 'grower', 'carrying_value' => '500.0000', 'pig_count' => 5, 'weight_kg' => '100',
        ]);
        GroupCostPool::create([
            'organization_id' => $this->org->id, 'group_id' => $this->group->id,
            'total_carrying_value' => '450.0000', 'on_hand_pig_count' => 5, 'total_weight_kg' => '100',
        ]);

        $r = app(BiologicalAssetReconciliationService::class)->run(CarbonImmutable::now());
        $this->assertFalse($r['subledger_vs_pools']['reconciled']);
        $this->assertSame('50.00', $r['subledger_vs_pools']['variance']);
    }
}
