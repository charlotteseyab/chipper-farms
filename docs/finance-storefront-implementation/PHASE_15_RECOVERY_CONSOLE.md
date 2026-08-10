# Phase 15 — The recovery console (§46)

## Document control

| Field | Value |
| --- | --- |
| Phase | 15 — Responsive UX and no-dead-end audit |
| Date | 2026-08-10 |
| Branch | `staging` |
| HEAD at phase start | `1ab6eca` (Phase 14) |
| Blueprint anchor | §46 (no-dead-end state analysis), §55 (mobile/responsive) |
| Depends on | Phases 3–14 — all COMPLETE |
| Phase status | **COMPLETE for §46's order states.** Remaining §46/§55 items are listed in §5 |

## 1. The audit

§46 lists eleven states that each need a visible recovery path. Most were built
along the way — mainly by Phase 3's Needs Attention queue, which consolidated
failure states that had been scattered across four unrelated places.

| §46 state | Where it recovers | Built in |
| --- | --- | --- |
| Draft listing | Listing editor: publish, edit, archive | pre-existing |
| Held listing | Hold expiry sweep; confirm/cancel resolve it | Phase 10 |
| Reserved listing | Complete or cancel on the order | pre-existing |
| Payment pending | Needs Attention: "awaiting payment over a day" | Phase 12 |
| Payment confirmed | Confirm/cancel on the order | pre-existing |
| **Finance posting skipped** | **Nowhere** | **this phase** |
| **Finance posting failed** | Needs Attention counted it; the ORDER showed nothing | **this phase** |
| Refund pending | Needs Attention, with a stale-after-3-days escalation | Phase 3 |
| Refund failed | Needs Attention | Phase 3 |
| COD settlement blocked | Needs Attention | Phase 3 |
| Tax profile missing | Needs Attention, routed to settings | Phase 3 |

And the sentence under the table — "the admin order page should become the
recovery console for storefront states" — was the part not done.

## 2. What was actually wrong

The order has recorded `finance_posting_status` and `finance_posting_error`
since Phase 4, and **displayed neither**. A confirmed order whose sale never
reached the books looked identical to one that posted cleanly, on the one page a
staff member actually opens. Needs Attention counted such orders, but a count on
a dashboard does not tell you *which* order or *why*.

Three things now appear on the order:

- **Failed** — the error, and a retry.
- **Skipped** — explained rather than reported as a failure. Deferred lines are
  not errors: they are lines the sale spine could not route automatically (a pig
  with no weight, feed not sold in kg). Calling that a failure sends staff
  hunting for a fault that is not there.
- **Held stock** — what is held, until when, and what resolves it.

## 3. The retry, and why it is safe to press twice

§46: "finance posting failed → replay event after fixing cause."

`retryFinancePosting()` posts nothing. It returns the outbox event to `pending`
so the normal claimer picks it up, and every posting downstream is guarded by
`journal_postings.idempotency_key` — a replay of work that already succeeded
writes no second journal. The subscribers carry their own guards too: the
credit-note path explicitly refuses to refund twice on a replayed event.

The button says so, because a staff member who is unsure whether the first press
worked will press it again, and they should not have to guess.

It is offered **only** for an event that actually failed. Re-queueing a
processed event would be a no-op that looked like an action.

## 4. Seeing a failure and correcting it are different rights

The panel first went in under `@can('manage_storefront_orders')`, alongside the
existing action forms. That is wrong, and writing the permission test is what
showed it: an order clerk who can open an order but not act on one would see a
perfectly normal-looking order with a sale missing from the books — the exact
dead end the panel exists to close, recreated by the permission check.

So the split now follows what each thing is:

- **The state** is visible to anyone who can open the order
  (`view_storefront_orders`). Knowing something is wrong is not a privileged act.
- **The retry** needs `manage_storefront_orders`, the same permission every other
  corrective action on this page carries, enforced on the route.

The button is also hidden from someone who could not use it. A button that
answers 403 is a worse dead end than no button.

## 5. Tests

10 in `OrderRecoveryConsoleTest`.

### 5.1 Verified in both directions

| Defect reintroduced | Result |
| --- | --- |
| The recovery panel removed (the pre-Phase-15 state) | **3 tests fail** |
| The `STATUS_FAILED` filter removed from the replay lookup | **Fails** |
| The `@can` around the retry button removed | **Fails** — the viewer is offered an action that would refuse them |

### 5.2 The second control needed a fourth test

Removing the `STATUS_FAILED` filter left the suite green at first.
`replayableEventFor()` has two guards — the order must not be marked `posted`,
**and** the event must have failed — and the existing test only exercised the
first, because the `posted` short-circuit caught its case anyway.

The case only the filter protects is an order whose lines were **skipped** (not
an error, so not `posted` either) with an event that processed successfully.
There is nothing failed to replay. With that test, removing the filter fails.

This is the fourth time in five phases that reintroducing a defect exposed a
test weaker than it looked. Two guards in one method, one test — that shape is
worth watching for.

## 6. Carried forward

| Item | Note |
| --- | --- |
| **IMP-070** — `NavigationHelper::sidebarSections()` still builds a hardcoded role-based finance menu alongside `FinanceNavigation` | Two menu builders means two places a gating rule has to be remembered, and Phase 11 already tripped over it once. Removing one is a change to every page's chrome and wants its own commit and visual check |
| `storefront.customer_accounts` gating | Blocking the account area would hide existing customers' orders — a §46 dead end created by a gate. Needs a decision on what an existing account holder sees first |
| **IMP-114** — §55 responsive review | The screens this programme built are mobile-first (single column widening to two, large touch targets), and the older ledger screens are not. A viewport review across the whole app is a distinct piece of work from the state audit |
| **IMP-112** — cross-tenant misses answer 403 on `AdminOrderController`, 404 elsewhere | Prompt §118 prefers 404 so another farm's records stay undiscoverable. The retry follows the file's existing convention rather than diverging; changing it alters every action on that controller and belongs in its own change |

## 7. Commands run and exact results

```
$ ./vendor/bin/phpunit --filter OrderRecoveryConsoleTest
OK (10 tests, 35 assertions)

$ ./vendor/bin/phpunit
Tests: 1733, Assertions: 5087, Skipped: 13        # 0 failures, 0 errors

$ bash scripts/testing/run-mariadb-tests.sh        # MariaDB 11.4.2, port 3399
Tests: 1733, Assertions: 5097, Skipped: 7          # 0 failures, 0 errors
```

A first MariaDB run was killed part-way, because the permission fix above landed
while it was in flight and a gate that half-tested the old code proves nothing.
It exited **0 with no test counts** — which is exactly the IMP-096 failure shape,
and the reason this programme reads the gate's output rather than its exit code.

No migrations.

## 8. Status

**Phase 15: COMPLETE for §46's order states.** The admin order page is now the
recovery console the blueprint asked for. The remaining items in §5 are real and
named, not silently dropped.
