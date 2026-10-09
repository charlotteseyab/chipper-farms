> Update: the farm confirmed colour-based identity. The tag and feed migrations have now succeeded. See [colour-identity migration results](ear-tag-colour-identity-2026-09-23.md). Earlier blocker/deployment statements below describe the state before that clarification.

# Ear-tag reuse implementation and verification — 23 September 2026

## Status

**Not ready for production.** Code and automated verification have progressed, but the real-data migration is blocked by existing conflicts and manual UAT is outstanding. No source-database migration, backfill, renumbering or deployment was performed in this turn.

The user's instruction to proceed was implemented against the attached rule: **organisation + normalised tag number is unique**. Colour is descriptive. The workspace already contained substantial additional implementation at the start of this turn; this report covers that combined implementation and the safeguards added during this turn.

## Existing system and changes

Permanent animal database IDs and generated codes remain unchanged. Normal stock registration retains its existing `CFPG-{YM}-{sequence}` generation; the model also retains its `P-` random-code fallback. Operational ownership continues to use permanent animal foreign keys.

Previously, legacy allocation used the largest numeric animal tag plus one, including archived animals. The new central allocator prioritises Available registered tags, then unused numeric gaps. Lost, damaged, reserved, retired, pending-recovery and in-use tags are excluded. Numeric strings are normalised without integer truncation; non-numeric printed tags remain supported. Existing unreconciled animal tags reserve their numbers.

The combined implementation adds:

- Organisation-owned tag register, colours, dated assignment history and seven physical statuses.
- Database uniqueness for organisation/number, one open assignment per tag and animal, composite organisation foreign keys, and assignment state/ownership triggers.
- Transactional assignment, automatic allocation, release, replacement and administrative correction. Organisation row locks serialize allocation and physical-state changes; assignment creates a new history row instead of changing its animal.
- Current-tag compatibility cache clearing on release. During staged backfill, the old cache is retained while its legacy unique constraint still exists, then cleared at activation for closed assignments.
- Death and sale release outcomes, with unknown outcomes defaulting to Pending Recovery. Ordinary archive closes an existing assignment as Pending Recovery.
- Restore that reuses a genuinely Available former tag or allocates another without displacing its holder.
- Historical tag lookup, current/previous holder search, animal tag history and a physical recovery/status register.
- Existing role/policy authorization, tenant checks and audited corrections. Model-level history deletion is refused; ownership mutation is also rejected by database triggers.
- Fail-closed activation: reconciliation must pass before the old uniqueness constraint is relaxed; assignment remains disabled until the final organisation-number constraint is installed after the integrity guards.

This turn additionally makes status changes and historical corrections roll back if audit storage fails, closes historical display gaps in health, weight, litter, archive, sales-return and mortality-writeoff reports, and displays permanent identity in the animal-profile heading.

## Database changes

The three upgrade migrations in the working tree create `ear_tag_colors`, `ear_tags` and `ear_tag_assignments`, retain `pigs_management.ear_tag_number` as a nullable compatibility column, replace its legacy unique index with a lookup index, and add:

- `ear_tags_org_number_unique` for physical identity; the earlier colour-scoped index is retained but cannot permit duplicates past the stronger index.
- Unique `(ear_tag_id, is_current)` and `(pig_id, is_current)` guards, where closed rows use NULL.
- Composite tag/animal/colour plus organisation foreign keys and their supporting unique indexes.
- INSERT/UPDATE triggers checking dates, current marker consistency, tenant consistency, immutable ownership and refusal to reopen closed assignments.

The final migration is forward-only and refuses ordinary rollback to avoid discarding tag history. An old application version must not be restarted against a database already using reused tags. Recovery requires a verified pre-deployment backup and matching application version.

## Real-data rehearsal and reconciliation

A read-only snapshot of the configured source database was copied into `chipper_farms_tag_rehearsal_826d23f8` (148 base tables). Migration stopped before changing tag identity because organisation 1 contains duplicate printed number `1`:

| Physical tag ID | Colour | Number | Current animal |
| --- | --- | --- | --- |
| 1 | Green | 1 | CFPG-2407-1 |
| 29 | Yellow | 1 | CFPG-2509-21 |

Both are recorded In Use. The farm must confirm physical reality and which animal receives a different printed number, or identify the incorrect legacy entry. Do not merge holders, overwrite assignment ownership or automatically rename a printed tag merely to make migration pass. A clarification has been requested; source records remain unchanged.

The detailed read-only audit also identified two invalid assignment intervals:

| Assignment / tag ID | Animal | Assigned | Released |
| --- | --- | --- | --- |
| 2 | CFPG-2409-2 | 2026-07-31 11:52:04 | 2026-07-31 00:00:00 |
| 6 | CFPG-2507-6 | 2026-07-31 16:44:45 | 2026-07-31 00:00:00 |

Their release dates are earlier than assignment times on the same calendar day. These need reasoned, audited corrections; their existing Available physical status was not invented or changed by this turn. New release logic handles calendar-date lifecycle forms without creating this reversed interval.

The snapshot contains 29 registered tags and 29 displayed assignments. No new assignments were migrated in this turn. The detailed audit lists three Available registers and no Pending Recovery registers, but its critical conflicts prevent certification of those states. Validated open-assignment and animal reconciliation totals must be rechecked after corrections.

Backup location: `/tmp/chipper_farms_tag_rehearsal_826d23f8/backup.sql`. Restoration to `chipper_farms_tag_restore_826d23f8_73ebc9` succeeded with matching row counts for all 148 base tables. This verifies base-table snapshot recovery; it is not certification of a complete production backup including routines, events, views, triggers, files and exact-content checks. The production backup procedure must include those where present.

## Integrations and compatibility

| Area | Behaviour / evidence |
| --- | --- |
| Registration, imports, purchase intake, weaning | Central assignment integration; legacy suggestion entry point delegates to allocator. |
| Death / sale | Permanent animal foreign keys preserved; assignment closes with physical outcome; historical labels resolve event dates. |
| Archive / restore | Assignment history retained; former tag conflicts allocate another tag. |
| Weight / health / medication | Archived animal relationships remain accessible; historical report rows and PDFs use event-date identity. |
| Breeding / mating / gestation / farrowing / weaning | Existing animal IDs retained; historical views use dated identities. |
| Feed / inventory / finance | Existing permanent animal or room/group references retained; no operational records are transferred. Existing broad regression suite covers these modules. |
| Search | Tag history lists current holder first and previous holders with their permanent codes; historical-tag search is included in affected animal searches. |
| Permissions / audit | Existing policies and admin correction authorization; mandatory correction reason; before/after audit; audit failure rolls back tag changes. |

Compatibility remains intentional: current selectors and forms still read `ear_tag_number`. It is never the historical ownership key. The 22 September audit contains the direct-reference inventory; the remaining display cache is not scheduled for immediate removal.

Date-only historical records cannot distinguish multiple same-day replacements. The current resolver uses the latest applicable assignment that day; timestamped records use their timestamp. This limitation needs explicit UAT review before production where same-day replacement histories exist.

## Verification

- Initial full regression: **2,073 passed, 14 skipped, zero failed; 6,608 assertions**.
- Focused tag, system-report and health tests after audit/backfill safeguards: **42 passed, zero failed; 161 assertions**.
- Real MariaDB multi-process concurrency: **2 passed, zero failed; 26 assertions**. Four automatic requests received different numbers; four requests for number 5 produced exactly one assignment and three safe rejections.
- Real-data migration rehearsal: **blocked as designed**, by duplicate legacy identity. This is not a successful migration rehearsal.
- Base-table backup recovery: passed row-count comparison for 148 tables.
- Final full regression: see evidence update below. Final reconciliation checks: **30 passed, 103 assertions**; counters now include only committed backfill work.

Added coverage includes death/recovered-tag reuse without moving death records, sale/Retired allocation exclusion, cross-organisation database rejection, unauthorized corrections/status changes, rollback on audit failures, two archived animals backfilled under the legacy unique constraint, activation refusal for unreconciled animals, and simultaneous manual requests for the same tag. Existing tests cover replacement rollback, history preservation for weight/health/medication, restore conflicts, status exclusion, search ordering and assignment marker constraints.

These automated HTTP/service/database scenarios are not claimed as manual farm UAT. Manual UAT completed in this turn: **none**.

## Regression defect found and fixed

An intermediate full run reported **2,080 passed, 14 skipped and 1 failed**. The storefront piglet test resolved a tag at the soft-delete timestamp, which can occur after the sale closed the assignment. The fix keeps event-date lookup strict, uses the actual sale date in sale verification, and adds `lastRecordedIdentity()` for archive displays explicitly marked Previous holder. A deterministic test verifies a lookup just after release returns no current tag while the archive label retains the correct old tag even after reuse.

Focused follow-up: **38 passed, 138 assertions** across tag lifecycle and storefront piglet tests. Finance return and mortality screens: **11 passed, 28 assertions**. Final migration and system-report checks: **33 passed, 116 assertions**. No assertion was removed to suppress the discovered failure. The stock form also permits unknown colour, consistent with colour being optional descriptive information.

## Deployment and recovery steps

1. Resolve the duplicate printed number and dated-history conflicts with an authorised farm administrator. Record reasons and preserve old/new audit evidence. Confirm what each physical tag actually is before changing its number.
2. Take a complete verified production backup and matching application artifact; place writes under maintenance control.
3. Rehearse on a fresh production-like copy. For older installations, install the foundation first, run `ear-tags:reconcile --backfill`, and inspect `ear-tags:reconcile --details` before activation. Never bypass failing checks.
4. Run pending migrations. Re-run detailed reconciliation, compare animal/code counts, verify open assignments, review all Pending Recovery tags and compare representative historical reports.
5. Run the eleven supplied UAT scenarios, including physical recovery decisions, restore conflicts, search, gaps, and replacement. Obtain farm signoff before production consideration.
6. If migration fails, retain maintenance mode and restore the complete verified snapshot and matching application artifact. Do not rely on destructive `migrate:rollback` or remove constraints to force deployment.
7. After release, monitor reconciliation failures, assignment errors, Pending Recovery queue, archive/restore conflicts and historical-report discrepancies. Keep the compatibility field until stable.

## Files

See [the complete combined working-tree file inventory](ear-tag-reuse-files-2026-09-23.md). No files were removed. Existing unrelated working-tree changes were preserved.

## Final evidence update

Final full regression completed successfully: **2,082 passed, 14 skipped, zero failed; 6,642 assertions; 237.52 seconds**. Skips are existing disabled optional features and SQLite-incompatible finance constraint checks, not skipped tag tests. The real MariaDB tag concurrency suite passed separately. `git diff --check` and syntax checks for the changed tag/animal services passed.

The final result supersedes the intermediate storefront failure described above. Manual UAT and successful migration of reconciled production-like data remain outstanding. Final status remains **Not ready for production**, due to the identified duplicate physical identity, two invalid legacy intervals, incomplete production-backup certification and pending UAT.
