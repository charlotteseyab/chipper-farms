<?php

namespace App\Services\Stock;

use App\Models\PigManagement;

/**
 * Analyses the relationship between two pigs for inbreeding detection.
 *
 * Extracted from PigManagementController's private helpers (analyzeRelationship,
 * isDirectParentChild, getParentChildDetails, checkSiblingRelationship,
 * checkGrandparentRelationship, isGrandparent, isAncestor, hasInbreedingRisk,
 * hasInbreedingRiskForUpdate — originally lines 344–599).
 *
 * Public API:
 *   - analyze(fatherId, motherId): array    Full analysis with risk_level, message, details.
 *   - hasCriticalRisk(fatherId, motherId): bool  For create path.
 *   - hasCriticalRiskForUpdate(fatherId, motherId, currentPigId): bool  For update path
 *                                                                      (skips self-references).
 *
 * Kept as a service (not a trait on PigManagement) because it operates on TWO
 * pigs, not one, and because MatingController calls it at plan time too.
 *
 * Risk levels returned by analyze():
 *   - 'none'     : no close relationship found
 *   - 'caution'  : half-siblings, grandparent, or distant ancestor
 *   - 'warning'  : full siblings
 *   - 'critical' : same pig, or direct parent-child — blocks creation
 */
class PigRelationshipAnalysisService
{
    /**
     * Depth limit for ancestor recursion. Preserved from the original
     * isAncestor($ancestorId, $pigId, $depth = 0) with depth cap at 3.
     */
    protected const MAX_ANCESTOR_DEPTH = 3;

    /**
     * Full relationship analysis between a putative father and mother.
     *
     * @return array{
     *   risk_level: string,
     *   relationship: string|null,
     *   message: string,
     *   can_proceed?: bool,
     *   details?: array
     * }
     */
    public function analyze(int|string|null $fatherId, int|string|null $motherId): array
    {
        if (! $fatherId || ! $motherId) {
            return ['risk_level' => 'none', 'relationship' => null, 'message' => ''];
        }

        if ($fatherId == $motherId) {
            return [
                'risk_level'   => 'critical',
                'relationship' => 'same_pig',
                'message'      => 'Cannot select the same pig as both father and mother.',
                'can_proceed'  => false,
            ];
        }

        $father = PigManagement::find($fatherId);
        $mother = PigManagement::find($motherId);

        if (! $father || ! $mother) {
            return ['risk_level' => 'none', 'relationship' => null, 'message' => ''];
        }

        if ($this->isDirectParentChild($father, $mother)) {
            return [
                'risk_level'   => 'critical',
                'relationship' => 'parent_child',
                'message'      => 'Direct parent-child relationship detected. This mating is not allowed.',
                'can_proceed'  => false,
                'details'      => $this->getParentChildDetails($father, $mother),
            ];
        }

        $siblingCheck = $this->checkSiblingRelationship($father, $mother);
        if ($siblingCheck['is_sibling']) {
            return [
                'risk_level'   => $siblingCheck['type'] === 'full' ? 'warning' : 'caution',
                'relationship' => $siblingCheck['type'] . '_siblings',
                'message'      => $siblingCheck['message'],
                'can_proceed'  => true,
                'details'      => $siblingCheck['details'],
            ];
        }

        $grandparentCheck = $this->checkGrandparentRelationship($father, $mother);
        if ($grandparentCheck['is_related']) {
            return [
                'risk_level'   => 'caution',
                'relationship' => 'grandparent_grandchild',
                'message'      => $grandparentCheck['message'],
                'can_proceed'  => true,
                'details'      => $grandparentCheck['details'],
            ];
        }

        if ($this->isAncestor($fatherId, $motherId) || $this->isAncestor($motherId, $fatherId)) {
            return [
                'risk_level'   => 'caution',
                'relationship' => 'distant_relatives',
                'message'      => 'These pigs are distantly related. Consider genetic diversity.',
                'can_proceed'  => true,
            ];
        }

        return [
            'risk_level'   => 'none',
            'relationship' => null,
            'message'      => 'No close relationships detected.',
            'can_proceed'  => true,
        ];
    }

    /**
     * Quick check used by stock create path.
     */
    public function hasCriticalRisk(int|string|null $fatherId, int|string|null $motherId): bool
    {
        return $this->analyze($fatherId, $motherId)['risk_level'] === 'critical';
    }

    /**
     * Update-path check. A pig that is being updated should not be treated as
     * its own ancestor (defensive guard preserved from the original logic at
     * line 680–686).
     */
    public function hasCriticalRiskForUpdate(
        int|string|null $fatherId,
        int|string|null $motherId,
        int|string $currentPigId
    ): bool {
        if ($fatherId == $currentPigId || $motherId == $currentPigId) {
            return false;
        }

        // For update we only re-run the ancestor loop check. The same-pig
        // guard above prevents recursive self-reference; the direct-parent
        // and sibling checks are intentionally omitted here to match the
        // historical behaviour of hasInbreedingRiskForUpdate().
        return $this->isAncestor($fatherId, $motherId)
            || $this->isAncestor($motherId, $fatherId);
    }

    // ================================================================
    // Internal helpers (preserved verbatim from the original private methods).
    // ================================================================

    protected function isDirectParentChild(PigManagement $pig1, PigManagement $pig2): bool
    {
        if ($pig2->father_id == $pig1->id || $pig2->mother_id == $pig1->id) {
            return true;
        }
        if ($pig1->father_id == $pig2->id || $pig1->mother_id == $pig2->id) {
            return true;
        }
        return false;
    }

    protected function getParentChildDetails(PigManagement $pig1, PigManagement $pig2): array
    {
        if ($pig2->father_id == $pig1->id) {
            return ['relationship' => $pig1->ear_tag_number . ' is the father of ' . $pig2->ear_tag_number];
        }
        if ($pig2->mother_id == $pig1->id) {
            return ['relationship' => $pig1->ear_tag_number . ' is the mother of ' . $pig2->ear_tag_number];
        }
        if ($pig1->father_id == $pig2->id) {
            return ['relationship' => $pig2->ear_tag_number . ' is the father of ' . $pig1->ear_tag_number];
        }
        if ($pig1->mother_id == $pig2->id) {
            return ['relationship' => $pig2->ear_tag_number . ' is the mother of ' . $pig1->ear_tag_number];
        }
        return [];
    }

    protected function checkSiblingRelationship(PigManagement $pig1, PigManagement $pig2): array
    {
        $sharedFather = $pig1->father_id && $pig2->father_id && $pig1->father_id == $pig2->father_id;
        $sharedMother = $pig1->mother_id && $pig2->mother_id && $pig1->mother_id == $pig2->mother_id;

        if ($sharedFather && $sharedMother) {
            return [
                'is_sibling' => true,
                'type'       => 'full',
                'message'    => 'These pigs are full siblings (same parents). High inbreeding risk.',
                'details'    => [
                    'shared_father' => $pig1->father ? $pig1->father->ear_tag_number : 'Unknown',
                    'shared_mother' => $pig1->mother ? $pig1->mother->ear_tag_number : 'Unknown',
                ],
            ];
        }

        if ($sharedFather || $sharedMother) {
            $sharedParent = $sharedFather ? 'father' : 'mother';
            $parentTag    = $sharedFather
                ? ($pig1->father ? $pig1->father->ear_tag_number : 'Unknown')
                : ($pig1->mother ? $pig1->mother->ear_tag_number : 'Unknown');

            return [
                'is_sibling' => true,
                'type'       => 'half',
                'message'    => "These pigs are half-siblings (shared $sharedParent). Moderate inbreeding risk.",
                'details'    => [
                    'shared_parent' => $sharedParent,
                    'parent_tag'    => $parentTag,
                ],
            ];
        }

        return ['is_sibling' => false];
    }

    protected function checkGrandparentRelationship(PigManagement $pig1, PigManagement $pig2): array
    {
        if ($this->isGrandparent($pig1->id, $pig2)) {
            return [
                'is_related' => true,
                'message'    => $pig1->ear_tag_number . ' is a grandparent of ' . $pig2->ear_tag_number . '. Moderate inbreeding risk.',
                'details'    => ['type' => 'grandparent_grandchild'],
            ];
        }

        if ($this->isGrandparent($pig2->id, $pig1)) {
            return [
                'is_related' => true,
                'message'    => $pig2->ear_tag_number . ' is a grandparent of ' . $pig1->ear_tag_number . '. Moderate inbreeding risk.',
                'details'    => ['type' => 'grandparent_grandchild'],
            ];
        }

        return ['is_related' => false];
    }

    protected function isGrandparent(int|string|null $grandparentId, PigManagement $pig): bool
    {
        if (! $pig->father_id && ! $pig->mother_id) {
            return false;
        }

        if ($pig->father_id) {
            $father = PigManagement::find($pig->father_id);
            if ($father && ($father->father_id == $grandparentId || $father->mother_id == $grandparentId)) {
                return true;
            }
        }

        if ($pig->mother_id) {
            $mother = PigManagement::find($pig->mother_id);
            if ($mother && ($mother->father_id == $grandparentId || $mother->mother_id == $grandparentId)) {
                return true;
            }
        }

        return false;
    }

    protected function isAncestor(int|string|null $ancestorId, int|string|null $pigId, int $depth = 0): bool
    {
        if (! $ancestorId || ! $pigId || $depth > self::MAX_ANCESTOR_DEPTH) {
            return false;
        }

        $pig = PigManagement::find($pigId);
        if (! $pig) {
            return false;
        }

        if ($pig->father_id === $ancestorId || $pig->mother_id === $ancestorId) {
            return true;
        }

        return $this->isAncestor($ancestorId, $pig->father_id, $depth + 1)
            || $this->isAncestor($ancestorId, $pig->mother_id, $depth + 1);
    }
}