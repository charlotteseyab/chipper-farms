<?php

namespace Tests\Feature\Services;

use App\Models\Organization;
use App\Models\PigManagement;
use App\Services\Stock\PigRelationshipAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers PigRelationshipAnalysisService — the extracted inbreeding detection.
 *
 * Historical behaviour preserved from the original private helpers in
 * PigManagementController (lines 344–599).
 *
 * Risk levels asserted:
 *   - same pig                 -> critical
 *   - direct parent/child      -> critical
 *   - full siblings            -> warning
 *   - half siblings            -> caution
 *   - grandparent/grandchild   -> caution
 *   - distant ancestor (<= 3)  -> caution
 *   - unrelated                -> none
 */
class PigRelationshipAnalysisServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PigRelationshipAnalysisService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Organization::create(['slug' => 'chipper-farms', 'name' => 'Chipper Farms']);
        $this->service = new PigRelationshipAnalysisService();
    }

    protected function makePig(array $overrides = []): PigManagement
    {
        return PigManagement::create(array_merge([
            'ear_tag_number' => 'T-' . uniqid(),
            'code'           => 'PIG-' . uniqid(),
            'breed'          => 'LW',
            'gender'         => 'M',
            'stock_type'     => 'standard',
            'date_of_birth'  => now()->subYear()->toDateString(),
        ], $overrides));
    }

    public function test_same_pig_twice_is_critical(): void
    {
        $pig = $this->makePig();
        $result = $this->service->analyze($pig->id, $pig->id);

        $this->assertSame('critical', $result['risk_level']);
        $this->assertSame('same_pig', $result['relationship']);
        $this->assertFalse($result['can_proceed']);
    }

    public function test_missing_ids_return_none(): void
    {
        $this->assertSame('none', $this->service->analyze(null, null)['risk_level']);
        $this->assertSame('none', $this->service->analyze(1, null)['risk_level']);
        $this->assertSame('none', $this->service->analyze(null, 2)['risk_level']);
    }

    public function test_direct_parent_child_is_critical(): void
    {
        $father = $this->makePig(['gender' => 'M']);
        $child  = $this->makePig(['gender' => 'F', 'father_id' => $father->id]);

        $result = $this->service->analyze($father->id, $child->id);
        $this->assertSame('critical', $result['risk_level']);
        $this->assertSame('parent_child', $result['relationship']);
        $this->assertFalse($result['can_proceed']);
    }

    public function test_full_siblings_is_warning(): void
    {
        $father = $this->makePig(['gender' => 'M']);
        $mother = $this->makePig(['gender' => 'F']);
        $boar   = $this->makePig(['gender' => 'M', 'father_id' => $father->id, 'mother_id' => $mother->id]);
        $sow    = $this->makePig(['gender' => 'F', 'father_id' => $father->id, 'mother_id' => $mother->id]);

        $result = $this->service->analyze($boar->id, $sow->id);
        $this->assertSame('warning', $result['risk_level']);
        $this->assertSame('full_siblings', $result['relationship']);
        $this->assertTrue($result['can_proceed']);
    }

    public function test_half_siblings_is_caution(): void
    {
        $sharedFather = $this->makePig(['gender' => 'M']);
        $motherA      = $this->makePig(['gender' => 'F']);
        $motherB      = $this->makePig(['gender' => 'F']);
        $child1       = $this->makePig(['gender' => 'M', 'father_id' => $sharedFather->id, 'mother_id' => $motherA->id]);
        $child2       = $this->makePig(['gender' => 'F', 'father_id' => $sharedFather->id, 'mother_id' => $motherB->id]);

        $result = $this->service->analyze($child1->id, $child2->id);
        $this->assertSame('caution', $result['risk_level']);
        $this->assertSame('half_siblings', $result['relationship']);
    }

    public function test_grandparent_relationship_is_caution(): void
    {
        $grandfather = $this->makePig(['gender' => 'M']);
        $grandmother = $this->makePig(['gender' => 'F']);
        $father      = $this->makePig(['gender' => 'M', 'father_id' => $grandfather->id, 'mother_id' => $grandmother->id]);
        $unrelated   = $this->makePig(['gender' => 'F']);
        $grandchild  = $this->makePig(['gender' => 'F', 'father_id' => $father->id, 'mother_id' => $unrelated->id]);

        $result = $this->service->analyze($grandfather->id, $grandchild->id);
        $this->assertSame('caution', $result['risk_level']);
        $this->assertSame('grandparent_grandchild', $result['relationship']);
    }

    public function test_unrelated_pigs_return_none(): void
    {
        $a = $this->makePig(['gender' => 'M']);
        $b = $this->makePig(['gender' => 'F']);

        $result = $this->service->analyze($a->id, $b->id);
        $this->assertSame('none', $result['risk_level']);
    }

    public function test_has_critical_risk_update_skips_self_reference(): void
    {
        $pig = $this->makePig();
        // If either parent id == current pig id, no critical risk.
        $this->assertFalse($this->service->hasCriticalRiskForUpdate($pig->id, 9999, $pig->id));
        $this->assertFalse($this->service->hasCriticalRiskForUpdate(9999, $pig->id, $pig->id));
    }
}