# Colour-based tag identity — 23 September 2026

The farm clarified that Green 1, Yellow 1 and Orange 1 are different physical tags. This supersedes the original number-only uniqueness requirement and the duplicate-number blocker described in earlier reports.

Identity is now `(organization_id, ear_tag_color_id, normalized_tag_number)`. Assignment, allocation suggestions, Excel import duplicate checks and reconciliation use that scope. Duplicate numbers within the same colour remain prohibited. Changing a register's colour is rejected if that colour/number already exists. Permanent animal IDs and assignment ownership/history remain unchanged.

The previously blocked activation migration now enforces the colour-based identity. A forward migration (`2028_10_15_000000_scope_tag_identity_to_colour.php`) also replaces the old number-only index for installations where activation already succeeded. No physical tag numbers were renamed or merged.

Validation:

- Focused stock/tag tests: 56 passed, 182 assertions. Includes Green/Yellow/Orange 1, per-colour allocation and reuse, same-colour collision rejection and upgrade from the old index.
- Real MariaDB concurrency: 2 passed, 26 assertions.
- Full pending migration chain rehearsed on `chipper_farms_tag_rehearsal_981b1fc4` and reconciliation passed: 29 animals, 29 assignments, zero missing/mismatches.
- Pre-migration base-table snapshot: `/tmp/chipper_farms_tag_rehearsal_981b1fc4/backup.sql`; restore checked against all 148 source table row counts in `chipper_farms_tag_restore_981b1fc4_d2127d`.

The requested `php artisan migrate` then succeeded on the configured source database:

- `2028_10_13_000000_enforce_organization_tag_identity` — DONE
- `2028_10_14_000000_extend_feed_planning_and_history` — DONE
- `2028_10_15_000000_scope_tag_identity_to_colour` — DONE

The two previously audited date-only timestamp corrections remain intact. Source reconciliation after migration passed with no missing assignments or cache mismatches. The earlier tag blocker is resolved; configuring approved feeding standards and farm UAT are still required for feed operations.

Final full regression: **2,107 passed, 14 existing skips, 6,789 assertions** (237.33 seconds). A second source `php artisan migrate` returned **Nothing to migrate**. Source organization 1 reconciliation counted 26 animals/assignments with zero missing/mismatches; the rehearsal's 29 count was across all organizations.
