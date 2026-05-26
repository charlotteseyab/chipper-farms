<?php

namespace App\Finance\Posting;

/**
 * Phase 5 — string constants for the new posting purposes introduced
 * by pig cost accounting.
 *
 * Convention only — JournalPosting accepts any string purpose. These
 * constants exist for refactor safety. Future phases can add their
 * purposes here too.
 */
final class PigCostingPurposes
{
    public const FEED_ISSUE              = 'feed_issue';
    public const PIG_SALE_COGS           = 'pig_sale_cogs';
    public const PIG_MORTALITY_WRITEOFF  = 'pig_mortality_writeoff';
    public const PIG_GROUP_OPENING       = 'pig_group_opening';

    private function __construct() {}
}
