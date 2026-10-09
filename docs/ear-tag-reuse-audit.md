> Historical foundation audit. The current implementation and activation gates are documented in [the 23 September report](ear-tag-reuse-implementation-2026-09-23.md).

# Reusable ear-tag audit and rollout boundary

## Current identity model

- `pigs_management.id` is the relational identity used by lifecycle, health,
  medication, weight, breeding, sales and finance records.
- `pigs_management.code` is generated independently and remains the permanent
  visible animal identifier.
- `pigs_management.ear_tag_number` is currently both a display field and the
  source used by validation, search, exports and automatic suggestions.
- The current database unique index covers organisation and ear-tag number and
  includes soft-deleted animals. It therefore prevents reuse today.

## Direct-tag dependency audit

The following areas still read or validate the legacy field directly and must
be migrated before reuse can be activated:

- manual stock creation and both stock edit paths;
- All Stock Excel import and exports;
- purchased-stock quarantine release;
- weaning-to-main-stock batch creation;
- mortality, sales and archive restoration;
- animal, health, medication and weight search/selectors;
- mating, farrowing, parent labels and relationship messages;
- dashboards, alerts, notifications and audit labels;
- storefront selection and listing displays;
- stock, sales, mortality, lifecycle, finance and platform reports.

Most underlying relationships already use `pig_id`, so historical ownership is
safe. The principal risk is ambiguous display/search after a number is reused.

## Foundation introduced

- Organisation-owned colour catalogue (`ear_tag_colors`).
- Organisation/colour/normalised-number register (`ear_tags`).
- Immutable dated ownership records (`ear_tag_assignments`).
- Portable unique guards allowing only one open assignment per tag and animal.
- Transactional allocation, assignment and release services.

## Activation boundary

This foundation does **not** enable reuse. Do not remove the legacy
`pigs_management` unique index until all write paths dual-write through the tag
service, existing records are backfilled and reconciled, and historical
search/reporting resolves the assignment applicable at the event date.

Archived tags must enter reconciliation as `pending_recovery`; they must never
be assumed physically available.
