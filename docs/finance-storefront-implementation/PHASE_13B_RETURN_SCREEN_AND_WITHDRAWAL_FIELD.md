# Phase 13b — The return screen and the withdrawal field

## Document control

| Field | Value |
| --- | --- |
| Phase | 13b — closing two open input gaps |
| Date | 2026-08-10 |
| Branch | `staging` |
| HEAD at phase start | `8545ee7` (Phase 13) |
| Blueprint anchor | §43 step 1, §44, §45, §35 step 2 |
| Closes | **IMP-098**, **IMP-090** |
| Phase status | **COMPLETE** |

## 1. Two enforcement layers that had no way in

Both of these were logged by earlier phases as "the logic is built and correct;
there is no field for it". That is a specific and dangerous shape of
incompleteness: the tests pass, the guard is real, and in production it never
fires because nobody can give it a value.

| Item | Built in | What was missing |
| --- | --- | --- |
| IMP-090 | Phase 11 | No withdrawal-period field on the medication form, so **every treatment recorded read as "no withdrawal"** and the sale guard could never fire |
| IMP-098 | Phase 13 | No screen for §43 step 1, "user requests return/refund with reason and quantity" |

## 2. IMP-090 — the withdrawal period

One number input on the medication form, validated `nullable|integer|min:0|max:365`.

- **Nullable**, because most treatments genuinely carry no withdrawal. Guessing
  a default would block legal sales and teach people to ignore the warning.
- **Capped at 365**, because withdrawal periods are measured in days and a
  number beyond a year is a typo, not a medicine.
- The help text says what it does — "while the period is running the pig cannot
  be listed on the shop" — so the person entering it knows why it matters.

The test drives the real HTTP path rather than the model, because the defect was
never in the model: `MedicationAdministration` has derived
`withdrawal_ends_on` correctly since Phase 11. The gap was the form, so the test
posts the form.

## 3. IMP-098 — the return screen

`RecordSalesReturnService` already validated, guarded against cumulative
over-return, recorded, and dispatched the event that produces the credit note.
The screen records through it and posts nothing itself — §41's rule applies on
the way back as much as on the way out.

Three decisions the screen encodes:

**A pig has no quantity field.** §45 makes pig returns indivisible. Offering a
quantity and then rejecting anything but 1 would be a trap; the form does not
offer it, and the controller passes `'1'` without reading the request at all, so
a hand-posted `0.5` cannot make it divisible. Tested.

**A returned pig never restocks from this screen.** §45: "staff must decide
health/market state before relisting". Even an explicit `restock=1` is ignored
for a pig. Tested.

**Restock is asked, not assumed, for feed.** §44 makes it a flag, and getting it
wrong overstates stock the farm cannot sell.

The service's own refusals surface as the message — it knows how much has
already come back and this controller does not.

### 3.1 A new permission rather than a borrowed one

`refund_storefront_orders` was the closest fit and the wrong one: this screen
covers direct farm-gate feed and pig sales that never touched the storefront, so
borrowing it would tie a farm's ability to record a return to whether it sells
online.

`record_sales_returns` goes to admin, superuser and accountant — deliberately
not salesperson. The person who made the sale should not be the only one who can
reverse it, and a return is where a mistake becomes hard to see.

## 4. Tests

8 in `SalesReturnScreenTest`, 2 added to `PigListingEligibilityTest`.

### 4.1 Verified in both directions

| Defect reintroduced | Result |
| --- | --- |
| Withdrawal field dropped from validation (the IMP-090 state) | **Fails** — the guard stops firing |
| Pig return made divisible | **Fails** — §45 violated |
| Cross-organisation sale check removed | **Fails** — another farm's revenue reversible |

## 5. Commands run and exact results

```
$ ./vendor/bin/phpunit --filter 'SalesReturnScreenTest'
OK (8 tests, 22 assertions)

$ ./vendor/bin/phpunit
Tests: 1699, Assertions: 5004, Skipped: 13        # 0 failures, 0 errors

$ bash scripts/testing/run-mariadb-tests.sh        # MariaDB 11.4.2, port 3399
Tests: 1699, Assertions: 5014, Skipped: 7          # 0 failures, 0 errors
```

Server confirmed up before the gate ran (IMP-096). No migrations.

## 6. Status

**Phase 13b: COMPLETE.** Both guards can now be reached by the people they were
built for. Phase 14 (advanced feature gating) is next.
