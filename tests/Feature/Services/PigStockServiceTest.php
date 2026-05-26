<?php

namespace Tests\Feature\Services;

use App\Models\Organization;
use App\Models\PigManagement;
use App\Services\Stock\PigStockService;
use App\Services\Stock\StockValidationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers PigStockService — the extracted write path for stock CRUD.
 *
 *   - create() generates code CFPG-{YM}-{id}, computes age, persists.
 *   - create() rejects parent-child pairings (critical inbreeding).
 *   - updateStandard() rejects non-standard pigs with stock_type error field.
 *   - updateStandard() recomputes age on DOB change.
 *   - updateBreeding() only recomputes age if DOB changed.
 */
class PigStockServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PigStockService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Organization::create(['slug' => 'chipper-farms', 'name' => 'Chipper Farms']);
        $this->service = $this->app->make(PigStockService::class);
    }

    public function test_create_produces_expected_code_format(): void
    {
        $pig = $this->service->create([
            'breed'          => 'LW',
            'gender'         => 'M',
            'stock_type'     => 'standard',
            'date_of_birth'  => '2026-03-15',
            'ear_tag_number' => 'CODE-TEST-001',
        ]);

        $this->assertMatchesRegularExpression('/^CFPG-2603-\d+$/', $pig->code);
        $this->assertSame('standard', $pig->stock_type);
        $this->assertGreaterThan(0, $pig->age_in_days);
    }

    public function test_create_rejects_parent_child_pairing(): void
    {
        $father = PigManagement::create([
            'ear_tag_number' => 'FATHER',
            'code'           => 'PIG-F',
            'breed'          => 'LW',
            'gender'         => 'M',
            'stock_type'     => 'breeding',
            'date_of_birth'  => now()->subYears(3)->toDateString(),
        ]);

        $existingChild = PigManagement::create([
            'ear_tag_number' => 'CHILD',
            'code'           => 'PIG-C',
            'breed'          => 'LW',
            'gender'         => 'F',
            'stock_type'     => 'breeding',
            'date_of_birth'  => now()->subYears(2)->toDateString(),
            'father_id'      => $father->id,
        ]);

        // Now try to create a new pig with father=existingChild, mother=father
        // (i.e., the parents of this new pig are themselves in a parent-child
        // relationship).
        $this->expectException(StockValidationException::class);
        $this->expectExceptionMessage('Inbreeding risk detected.');

        $this->service->create([
            'breed'          => 'LW',
            'gender'         => 'M',
            'stock_type'     => 'standard',
            'date_of_birth'  => now()->toDateString(),
            'ear_tag_number' => 'NEWPIG',
            'father_id'      => $existingChild->id,
            'mother_id'      => $father->id,
        ]);
    }

    public function test_update_standard_rejects_breeding_pig(): void
    {
        $pig = PigManagement::create([
            'ear_tag_number' => 'B1', 'code' => 'PIG-B1', 'breed' => 'LW',
            'gender' => 'F', 'stock_type' => 'breeding',
            'date_of_birth' => now()->subYear()->toDateString(),
        ]);

        try {
            $this->service->updateStandard($pig, [
                'ear_tag_number' => 'B1-updated',
                'breed'          => 'LW',
                'gender'         => 'F',
                'date_of_birth'  => $pig->date_of_birth->toDateString(),
            ]);
            $this->fail('Expected StockValidationException');
        } catch (StockValidationException $e) {
            $this->assertSame('stock_type', $e->field);
            $this->assertStringContainsString('Only standard stock', $e->getMessage());
        }
    }

    public function test_update_standard_recomputes_age_on_dob_change(): void
    {
        $pig = PigManagement::create([
            'ear_tag_number' => 'S1', 'code' => 'PIG-S1', 'breed' => 'LW',
            'gender' => 'F', 'stock_type' => 'standard',
            'date_of_birth' => now()->subYears(2)->toDateString(),
            'age_in_days' => 730,
        ]);

        $newDob = now()->subYear()->toDateString();

        $updated = $this->service->updateStandard($pig, [
            'ear_tag_number' => 'S1',
            'breed'          => 'LW',
            'gender'         => 'F',
            'date_of_birth'  => $newDob,
        ]);

        $this->assertLessThan(400, $updated->age_in_days);
        $this->assertGreaterThan(300, $updated->age_in_days);
    }

    public function test_update_breeding_preserves_age_when_dob_unchanged(): void
    {
        $pig = PigManagement::create([
            'ear_tag_number' => 'BR1', 'code' => 'PIG-BR1', 'breed' => 'LW',
            'gender' => 'M', 'stock_type' => 'breeding',
            'date_of_birth' => now()->subYears(2)->toDateString(),
            'age_in_days' => 730,
        ]);

        $originalAge = $pig->age_in_days;

        $updated = $this->service->updateBreeding($pig, [
            'ear_tag_number' => 'BR1',
            'breed'          => 'LW',
            'gender'         => 'M',
            'stock_type'     => 'breeding',
            'date_of_birth'  => $pig->date_of_birth->format('Y-m-d'),
        ]);

        // age_in_days field is NOT in the update array when DOB is unchanged.
        $this->assertSame($originalAge, $updated->age_in_days);
    }
}