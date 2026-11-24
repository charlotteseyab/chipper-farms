# Tag reuse review fixes — 15 October 2026

## Phase 1: assignment integrity and dates

Ordinary standard/breeding edits check tag identity after locking the organisation and animal. Animals without a current assignment can no longer acquire an arbitrary tag-number cache through an edit. Equivalent number formatting retains the stored cache. Blank tag fields are accepted for genuinely untagged animals, so unrelated animal details remain editable.

Assignment and audited correction paths reject starts in the future or before birth. Corrections also reject future releases, while retaining existing overlap, ownership, and open/closed-state checks. Explicit migration backfills retain their compatibility path. Historical tag tests now give their animals birth dates preceding the January 2026 assignments they exercise.

## Phase 2: first physical tag

Untagged active animals have an Assign Tag action on their profile and edit screens. The assignment form asks for colour, number and a note, without asking what happened to a nonexistent old tag. New GET/POST routes reuse animal-update authorization and farm-scoped active-colour validation.

First assignment runs through the locked, audited assignment service. Occupied tags and animals already holding a tag are refused; recovered available tags retain both holders' histories. A legacy number without assignment history must be reconciled before a new identity can be assigned. Replacement takes the organisation lock before animal locks, matching assignment lock order.

## Phase 3: distinct animal identity

Standard-stock parent choices show colour, number and permanent animal code. Breeding edit and all-stock registration parent choices use the same identity labels. Standard/breeding CSV and stock-list PDF exports include coloured tag labels, with permanent codes in parent identities. Queries preload tag histories and colours for the affected parent selectors and exports.

## Validation

Added 12 tests in `tests/Feature/Stock/TagReuseReviewRegressionTest.php` covering invalid and valid corrections, ordinary-edit bypass, first-assignment UI and authorization, tag availability, recovered-tag reuse, parent choices, and CSV/PDF output.

Combined checks: 125 passed, 513 assertions across stock, tag history, stock services, sale/death workflows, lifecycle routes, import/breed settings, and health history acceptance/data-model tests.

No database migration is required. Browser interaction and MariaDB concurrency were not exercised in this change.
