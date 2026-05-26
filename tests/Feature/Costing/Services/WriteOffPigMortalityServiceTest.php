<?php

namespace Tests\Feature\Costing\Services;

use App\Models\Finance\GroupOpeningPoolImport;
use App\Models\Finance\PigMortalityWriteoff;
use App\Models\Group;
use App\Models\PigDeath;
use App\Models\PigManagement;
use App\Services\Deaths\WriteOffPigMortalityService;
use App\Services\Finance\Costing\GroupCostPoolService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class WriteOffPigMortalityServiceTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected WriteOffPigMortalityService $service;
    protected GroupCostPoolService $poolService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();
        $this->service     = $this->app->make(WriteOffPigMortalityService::class);
        $this->poolService = $this->app->make(GroupCostPoolService::class);
    }

    public function test_uses_final_weight_when_present(): void
    {
        [$group, $pig] = $this->seedPigInGroup();
        $this->seedPool($group, 50, '2000', '5000');  // basis = 2.5

        $death = PigDeath::create([
            'pig_id'       => $pig->id,
            'death_date'   => '2027-06-01',
            'death_cause'  => 'disease',
            'final_weight' => 80.0,  // → cogs = 80 × 2.5 = 200
            'organization_id' => $this->org->id,
            'recorded_by'  => $this->user->id,
        ]);

        $w = $this->service->execute($death);

        $this->assertNotNull($w);
        $this->assertEquals('80.0000', (string) $w->pig_weight_at_death_kg);
        $this->assertEquals('200.0000', (string) $w->writeoff_amount);
        $this->assertFalse((bool) $w->used_group_average_weight);
    }

    public function test_falls_back_to_pig_weight_cache_when_final_weight_null(): void
    {
        [$group, $pig] = $this->seedPigInGroup();
        $this->seedPool($group, 50, '2000', '5000');

        $pig->current_weight_kg_cache = 75.0;
        $pig->save();

        $death = PigDeath::create([
            'pig_id'       => $pig->id,
            'death_date'   => '2027-06-01',
            'death_cause'  => 'accident',
            'final_weight' => null,
            'organization_id' => $this->org->id,
            'recorded_by'  => $this->user->id,
        ]);

        $w = $this->service->execute($death);

        $this->assertEquals('75.0000', (string) $w->pig_weight_at_death_kg);
        $this->assertFalse((bool) $w->used_group_average_weight);
    }

    public function test_falls_back_to_group_average_when_no_per_pig_weight(): void
    {
        [$group, $pig] = $this->seedPigInGroup();
        $this->seedPool($group, 50, '2000', '5000');  // avg = 2000/50 = 40

        // No final_weight, no cache.
        $death = PigDeath::create([
            'pig_id'       => $pig->id,
            'death_date'   => '2027-06-01',
            'death_cause'  => 'unknown',
            'final_weight' => null,
            'organization_id' => $this->org->id,
            'recorded_by'  => $this->user->id,
        ]);

        $w = $this->service->execute($death);

        $this->assertEquals('40.0000', (string) $w->pig_weight_at_death_kg);
        $this->assertTrue((bool) $w->used_group_average_weight);
        $this->assertEquals('100.0000', (string) $w->writeoff_amount);  // 40 × 2.5
    }

    public function test_returns_null_when_pig_has_no_group(): void
    {
        // Create a pig with NO group_id.
        $pig = PigManagement::create([
            'ear_tag_number' => 'NG-' . uniqid(),
            'gender'         => 'male',
            'stock_type'     => 'fattening',
            'date_of_birth'  => '2027-01-01',
        ]);

        $death = PigDeath::create([
            'pig_id'       => $pig->id,
            'death_date'   => '2027-06-01',
            'death_cause'  => 'natural',
            'final_weight' => 50.0,
            'organization_id' => $this->org->id,
            'recorded_by'  => $this->user->id,
        ]);

        $this->assertNull($this->service->execute($death));
        $this->assertEquals(0, PigMortalityWriteoff::count());
    }

    public function test_returns_null_when_pool_empty(): void
    {
        [$group, $pig] = $this->seedPigInGroup();
        // No opening — pool doesn't exist yet, so it's "empty".

        $death = PigDeath::create([
            'pig_id'       => $pig->id,
            'death_date'   => '2027-06-01',
            'death_cause'  => 'unknown',
            'final_weight' => 50.0,
            'organization_id' => $this->org->id,
            'recorded_by'  => $this->user->id,
        ]);

        $this->assertNull($this->service->execute($death));
    }

    public function test_idempotent_returns_existing_writeoff_on_retry(): void
    {
        [$group, $pig] = $this->seedPigInGroup();
        $this->seedPool($group, 50, '2000', '5000');

        $death = PigDeath::create([
            'pig_id'       => $pig->id,
            'death_date'   => '2027-06-01',
            'death_cause'  => 'disease',
            'final_weight' => 80.0,
            'organization_id' => $this->org->id,
            'recorded_by'  => $this->user->id,
        ]);

        $first  = $this->service->execute($death);
        $second = $this->service->execute($death);

        $this->assertEquals($first->id, $second->id);
        $this->assertEquals(1, PigMortalityWriteoff::count());
    }

    // -------- helpers --------

    /**
     * @return array{0: Group, 1: PigManagement}
     */
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
        $this->poolService->recordOpening($group->id, $count, $weight, $value, $import, 1);
    }
}
