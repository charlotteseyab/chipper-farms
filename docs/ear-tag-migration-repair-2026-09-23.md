> Update: the farm confirmed colour-based identity. The tag and feed migrations have now succeeded. See [colour-identity migration results](ear-tag-colour-identity-2026-09-23.md). Earlier blocker/deployment statements below describe the state before that clarification.

# Migration repair attempt — 23 September 2026

Requested action: fix the failed tag identity migration and rerun `php artisan migrate`.

Read-only inspection confirmed two open assignments for printed number 1 in organization 1: Green tag ID 1 / animal CFPG-2407-1 and Yellow tag ID 29 / animal CFPG-2509-21. Both original animal tag fields also say 1; there is no evidence establishing a replacement printed number. The original specification requires organization-wide number uniqueness regardless of colour. Neither physical identity nor that requirement was changed.

Two independent invalid legacy intervals were repaired using `scripts/remediation/repair-tag-release-times.php --apply`, first verified on the isolated rehearsal copy, then applied to the configured source:

| Assignment | Previous release | Corrected recorded-event release | Evidence |
| --- | --- | --- | --- |
| 2 | 2026-07-31 00:00:00 | 2026-07-31 13:06:18 | Same-day sale #1 creation timestamp |
| 6 | 2026-07-31 00:00:00 | 2026-07-31 16:46:05 | Same-day sale #2 creation timestamp |

These timestamps repair a date-only midnight backfill convention; they do not certify physical tag recovery time. Ownership, printed numbers, animal records, sales and physical availability statuses were unchanged. The repair rejects ambiguous sale matches, non-backfill intervals and overlapping assignments. Both updates and existing activity-log audit records commit in one transaction. CLI maintenance is recorded as the actor description without impersonating a farm user.

Source before-values: `/tmp/tag-date-repair-72a45c3e107b/before.json`. Isolated-test before-values: `/tmp/tag-date-repair-40424df22e1c/before.json`.

`php artisan migrate` was rerun and exited 1 at the existing duplicate-number check. The tag activation migration and subsequent feed migration remain pending. Reconciliation confirms zero invalid tag states; the duplicate number 1 remains the sole reported critical conflict.

Required farm fact: the correct printed number for one of these two animals, or confirmation that both physically use number 1 and an explicit decision to revise the original organization-wide uniqueness requirement. No arbitrary renumbering, holder merge or disabled integrity guard was performed.
