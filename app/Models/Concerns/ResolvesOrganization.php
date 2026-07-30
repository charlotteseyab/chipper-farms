<?php

namespace App\Models\Concerns;

/**
 * Contract for resolving which organisation a record belongs to (CF-P0-005).
 *
 * Why derived resolution is necessary
 * -----------------------------------
 * 41 of 128 tables carry no `organization_id` column at all, and that set
 * includes the entire breeding and clinical domain: farrowing, matings,
 * weanings, health_records, medication_administrations, pig_weights,
 * piglet_deaths.
 *
 * EnforcesOrganizationScope::sameOrganization() previously returned TRUE for
 * any resource without the column. The register characterises that as a
 * transitional allowance for "legacy rows", but for these tables it is not
 * transitional — there is no column to populate, so the check passed
 * unconditionally and FarrowingPolicy, MatingPolicy and WeaningPolicy had no
 * tenant enforcement whatsoever. An Organization B user could act on an
 * Organization A farrowing record and every policy would agree.
 *
 * Adding organization_id to all of those tables plus backfilling them is a
 * larger migration than this phase can safely rehearse. But their tenancy is
 * not actually ambiguous: every one of them hangs off a pig, and
 * pigs_management does carry organization_id. So the organisation is derivable
 * today, without a schema change, by walking to the parent.
 *
 * Implementing classes declare that walk. Returning null means "cannot be
 * determined", which callers MUST treat as a denial — never as a pass.
 */
interface ResolvesOrganization
{
    /**
     * The organisation this record belongs to, or null if it cannot be
     * determined.
     *
     * Null is a denial signal, not an exemption. Implementations should return
     * null rather than guessing when the parent chain is broken or unloaded.
     */
    public function resolveOrganizationId(): ?int;
}
