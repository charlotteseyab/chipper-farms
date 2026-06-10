<?php

namespace Tests\Feature\Finance\BiologicalAssets;

use App\Models\Finance\Account;
use App\Models\Group;
use App\Models\PigManagement;
use App\Services\Finance\BiologicalAssets\BiologicalAssetStageResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class BiologicalAssetStageResolverTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();
        // Stage accounts the resolver maps to.
        foreach (['1310','1320','1330','1340','1350','1360','1370'] as $code) {
            Account::firstOrCreate([
                'organization_id' => $this->org->id,
                'code' => $code,
            ], [
                'name' => "Bio {$code}",
                'account_type' => Account::TYPE_ASSET, 'account_subtype' => 'current_asset',
                'currency_code' => 'GHS', 'is_active' => true, 'is_control' => true, 'allow_manual_journal' => false,
            ]);
        }
    }

    public function test_pig_age_maps_to_fattening_stage(): void
    {
        $r = app(BiologicalAssetStageResolver::class);

        $this->assertSame('piglet',   $r->stageForPig($this->pig(stockType: 'standard', ageDays: 10)));
        $this->assertSame('weaner',   $r->stageForPig($this->pig(stockType: 'standard', ageDays: 50)));
        $this->assertSame('grower',   $r->stageForPig($this->pig(stockType: 'standard', ageDays: 100)));
        $this->assertSame('finisher', $r->stageForPig($this->pig(stockType: 'standard', ageDays: 200)));
    }

    public function test_breeding_pig_maps_by_gender(): void
    {
        $r = app(BiologicalAssetStageResolver::class);

        $this->assertSame('sow',  $r->stageForPig($this->pig(stockType: 'breeding', ageDays: 400, gender: 'female')));
        $this->assertSame('boar', $r->stageForPig($this->pig(stockType: 'breeding', ageDays: 400, gender: 'male')));
    }

    public function test_account_for_stage_resolves_code(): void
    {
        $r = app(BiologicalAssetStageResolver::class);
        $this->assertSame('1360', $r->accountForStage('grower')->code);
        $this->assertSame('1320', $r->accountForStage('sow')->code);
    }

    public function test_group_stage_uses_median_age_for_fattening(): void
    {
        $r = app(BiologicalAssetStageResolver::class);
        $group = Group::create(['name' => 'G', 'type' => 'pen']);

        // Three pigs: ages 100, 110, 120 -> median 110 -> grower.
        foreach ([100, 110, 120] as $age) {
            $this->pig(stockType: 'standard', ageDays: $age, groupId: $group->id);
        }

        $this->assertSame('grower', $r->stageForGroup($group->id));
    }

    public function test_empty_group_falls_back(): void
    {
        $r = app(BiologicalAssetStageResolver::class);
        $group = Group::create(['name' => 'Empty', 'type' => 'pen']);
        $this->assertSame(config('biological_assets.fallback_stage'), $r->stageForGroup($group->id));
    }

    protected function pig(string $stockType, int $ageDays, string $gender = 'male', ?int $groupId = null): PigManagement
    {
        return PigManagement::create([
            'ear_tag_number' => 'T' . uniqid(),
            'gender'         => $gender,
            'stock_type'     => $stockType,
            'date_of_birth'  => now()->subDays($ageDays)->toDateString(),
            'group_id'       => $groupId,
        ]);
    }
}
