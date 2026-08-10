# Phase 11 — Pig to storefront

## Document control

| Field | Value |
| --- | --- |
| Phase | 11 — Pig to storefront |
| Date | 2026-08-10 |
| Branch | `staging` |
| HEAD at phase start | `fff24ae` (Phase 10) |
| Blueprint anchor | §35 (pig-to-storefront lifecycle) |
| Depends on | Phases 8 and 10 — both COMPLETE |
| Phase status | **COMPLETE** |

## 1. What §35 already had, and what it did not

§35's twelve steps are mostly built. Steps 3–6 (push, draft with source
snapshot, staff review, publish) exist as `PushToStorefrontService`,
`ListingEditorService` and the push wizard. Steps 7–8 are Phases 9 and 10.
Steps 9–11 are the sale spine and were built long before this programme.

Step 2 is the gap:

> Eligibility check confirms alive, unsold, not already actively listed, **not
> under withdrawal/health hold**, has latest weight if price-by-kg.

| Check | Before |
| --- | --- |
| Not already actively listed | Enforced (`guardAgainstDuplicate`) |
| Alive / unsold | Enforced late — only at publish, via the source soft-delete check |
| Under medicine withdrawal | **Could not be enforced: the data did not exist** |
| Health hold | **Not enforced** |
| Weight when price-by-kg | Not enforced; surfaced to the customer at the cart |

## 2. The withdrawal period is not a UX gap

`medication_administrations` recorded what was given and when, and nothing about
how long the animal must stay out of the food chain afterwards. So the check
§35 asks for could not be written at all.

This matters more than the others. Selling an animal inside its withdrawal
period puts drug residue into food; in Ghana as elsewhere that is a regulatory
matter for the farm, not a customer-service one. A storefront that can list such
an animal with one click makes it likelier, not less — which is exactly why §35
put the check at the push step.

So Phase 11 begins with a migration: `withdrawal_period_days` as the person
reads it off the bottle, and `withdrawal_ends_on` derived from
`administration_date + days` at write time.

**Both columns, deliberately.** Deriving the end date on read would put date
arithmetic inside every eligibility query, and the query that matters is asked
while a staff member waits. A stored, indexed date answers it directly. The pair
can only drift if someone edits a column by hand, and the model's `saving` hook
keeps them in step — including clearing the derived date when the days are
cleared. Tested.

**Nullable, and null is not "safe".** Vitamins and many routine treatments carry
no withdrawal at all, so a null cannot block a sale — farms would learn to
ignore the warning. But a null on a *recent* treatment might equally mean nobody
filled the field in. That is reported as a caution rather than a blocker, so the
gap is visible instead of silently permissive.

## 3. Architecture decisions

### 3.1 Reasons, not a boolean

"You cannot list this pig" sends a staff member hunting. `PigListingEligibilityService::check()`
returns every blocker and every caution, each naming the specific thing and,
where there is one, the date it clears.

The push wizard shows them when it **opens**, not after the form is submitted —
someone should learn why an animal cannot be sold before filling anything in.
Submit-time refusals render through the same block, so the two paths look
identical.

There is a test that a pig with a withdrawal, a severe health record and no
weight reports all three at once. Finding out about the withdrawal, waiting a
fortnight, and only then being told about the missing weight is the same
information delivered as badly as possible.

### 3.2 Checked at push AND at publish

A listing drafted on Monday and published on Friday may have had a treatment
recorded in between, and withdrawal periods start after the paperwork, not
before it. `ListingService::publish()` re-checks, and a test drafts a listing,
records a treatment, and asserts the publish is refused.

`checkListing()` filters out the "already on the shop" blocker, because the
listing being published is itself that listing. Tested — without it, nothing
pig-backed could ever be published.

### 3.3 What blocks and what does not

- **Severity `severe`/`high`, unresolved** — blocks. A healing scrape does not.
  Blocking on mild conditions would make the check something staff route around,
  and a check that is routed around protects nothing.
- **Treatment status `planned`/`ongoing`** — blocks. The animal is still under
  treatment.
- **Resolved conditions** — never block, whatever their severity was.

### 3.4 Reading the death status defensively

The herd model has carried several shapes of a life-status field over time.
`isDead()` reads whichever of `status`, `health_status` or `life_status` exists
rather than assuming one, so an unrelated schema change cannot silently stop the
check working. That is a deliberate trade of tidiness for not failing open.

## 4. Tests

18 tests in `PigListingEligibilityTest`, grouped as the ways an animal should
not reach the shop.

### 4.1 Verified in both directions

| Defect reintroduced | Result |
| --- | --- |
| Withdrawal check removed from `check()` | **5 tests fail** |
| `underWithdrawal` uses `>` instead of `>=` (the last day of the period stops blocking) | **1 test fails** — `test_the_final_day_of_the_withdrawal_period_still_blocks` |
| Publish-time re-check removed from `ListingService::publish()` | **1 test fails** — `test_a_treatment_recorded_after_drafting_stops_the_listing_publishing` |

The off-by-one is worth calling out: a 28-day withdrawal recorded on the 1st
clears on the 29th, not the 28th, and getting it wrong is a day of residue. It
has its own test because it is the kind of thing that survives review.

### 4.2 A mistake I made while verifying

The reverse-direction script used `git checkout` to undo one of the deliberate
defects, which reverted `MedicationAdministration` to `HEAD` — losing this
phase's changes to it and producing 17 errors in the next check. Restored from
the working copy and both re-run cleanly. Recorded because the misleading run is
in the session history and the 17 errors were my tooling, not the application.

## 5. Commands run and exact results

```
$ ./vendor/bin/phpunit --filter PigListingEligibilityTest
OK (18 tests, 31 assertions)

$ ./vendor/bin/phpunit
Tests: 1668, Assertions: 4926, Skipped: 13        # 0 failures, 0 errors

$ bash scripts/testing/run-mariadb-tests.sh
Tests: 1668, Assertions: 4936, Skipped: 7          # 0 failures, 0 errors
```

Migration verified up, down and up on both engines. On MariaDB 11.4.2:

```
`withdrawal_period_days` smallint(5) unsigned DEFAULT NULL,
`withdrawal_ends_on` date DEFAULT NULL,
KEY `medication_admin_withdrawal_idx` (`pig_id`,`withdrawal_ends_on`)
```

Purely additive: two nullable columns and an index. No existing row changes
meaning, and every existing treatment reads as "no withdrawal recorded" — which
is what it is.

## 6. Carried forward

| Item | Where it belongs |
| --- | --- |
| A field for `withdrawal_period_days` on the medication recording form | The column, model and checks are built; the operational UI for entering it is pig-operations work, not storefront. **Until it is added, every treatment reads as "no withdrawal recorded"** and the check reports the gap as a caution rather than blocking |
| A per-medication default withdrawal period (a medicine catalogue) | Would remove the retyping and the chance of a wrong number. Needs a medicines reference table this system does not have |
| Blocking severities configurable per farm | Hard-coded to `severe`/`high`. Worth revisiting if a farm's severity vocabulary differs |
| Listing becomes `sold_out` at fulfilment (§35 step 11) | Already built in `OrderService::complete()` |

**The first row is the important one.** The enforcement is real and tested, but
it only bites once someone records a withdrawal period, and there is no field on
the form yet. Anyone reading this phase as "withdrawal periods are now enforced"
should read it as "the enforcement exists and is waiting on one input field".

## 7. Status

**Phase 11: COMPLETE.** Phase 12 (storefront → finance integration, §41) is
next; it depends on Phases 10 and 11, both now done.
