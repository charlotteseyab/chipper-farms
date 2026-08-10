# Phase 10 — Checkout holds and reservations

## Document control

| Field | Value |
| --- | --- |
| Phase | 10 — Checkout holds and reservations |
| Date | 2026-08-10 |
| Branch | `staging` |
| HEAD at phase start | `a193f19` (Phase 9) |
| Blueprint anchor | §36 (listing lifecycle), §37 (reservation and concurrency) |
| Depends on | Phase 9 — COMPLETE |
| Phase status | **COMPLETE** |

## 1. The gap

§37: "this prevents a customer paying for stock that another customer bought in
the same window."

Nothing happened to inventory between checkout and staff confirmation. Two
buyers could both check out the last boar, both be sent to Paystack, and both
pay. The second found out at confirmation — after their money had left — and the
farm was then refunding a customer for stock it never had.

Confirmation locking was already correct and is untouched. It protects the
farm's *inventory*. It does not protect the buyer's *money*, because by the time
it refuses, the buyer has already paid.

## 2. Architecture decisions

### 2.1 A table, not a `held` listing state

§36 offers either: "add `held` or a separate `storefront_inventory_holds` table
rather than overloading `reserved`".

The table, because of quantities. A listing with ten bags of feed and a hold on
two is not "held" — it is eight sellable and two spoken for. A status column can
only say the whole listing is or is not available, so it would either block the
other eight or protect neither. Rows carrying a quantity say exactly what is
spoken for and by whom, and the listing state machine stays untouched.

### 2.2 Holds do not move inventory

`quantity_available` is still decremented once, by
`OrderService::applyInventoryEffect()` at confirmation. A hold that also
decremented would take the same stock twice — once softly and once for real —
and the release path would then hand back stock that was never taken.

What a hold does is subtract from what the shop will *sell*.
`HoldService::availableToSell()` is the column minus live holds, and every path
that offers stock now asks that question instead of reading the column. There
are tests that a hold does not move the column, and that a confirmation
decrements exactly once and does not leave the units subtracted twice.

### 2.3 Lock order is the order the confirmation path already used

`applyInventoryEffect()` aggregates per listing, `ksort`s numerically and locks
ascending. `HoldService` does the same, deliberately: two transactions taking
the same two listings in opposite orders deadlock. Every path that locks
listings in this codebase now locks them by ascending id, and there is a
concurrency test with ten workers over two shared listings in deliberately
opposite cart order.

### 2.4 A null expiry means "waiting on a person"

§37 puts the clock on payment: a buyer who wanders off mid-payment must not park
stock forever. A cash order is different — §37's own table says the hold
"remains until staff decision", and expiring those after thirty minutes would
fail routine COD orders because the farm answered the phone slowly.

So `expires_at` is set for orders in `pending_payment` and left null otherwise.
The sweep skips nulls by construction, and `scopeLive()` handles the null branch
explicitly, because `expires_at > now()` alone silently drops every null in SQL
and would quietly stop protecting every cash order.

### 2.5 The expiry sweep does not cancel orders

An unpaid order whose hold lapsed is still a real enquiry — the buyer may ring,
or their bank may be slow. Cancelling on a timer would destroy that, and the
order state machine belongs to `OrderService`, not to a sweep. The hold expires,
the order stays where it is and is now competing for stock like anyone else, and
staff see the lapse on the order timeline.

The sweep is a single conditional `UPDATE ... WHERE status = 'active'`. That is
what makes it safe alongside a confirmation: a hold confirmation has already
consumed is no longer active, so the sweep cannot reopen it; and if the sweep
wins, confirmation finds no hold and re-validates stock under its own lock,
which it does anyway.

Scheduled every minute rather than hourly, because the unit being returned is
often the only one.

### 2.6 Cancelling a confirmed order must not release its consumed hold

Cancellation reverses stock through `applyInventoryEffect()`. If it also turned
the consumed hold back into a released one, the unit would be handed back twice.
`resolve()` is scoped to `status = active`, so a consumed hold stays consumed.
Tested.

## 3. Tests

17 in-process tests (`HoldServiceTest`) and 4 multi-process contention tests
(`tests/Concurrency/HoldConcurrencyTest`).

The in-process tests prove the logic; they cannot prove the locking, because
nothing interleaves and SQLite has no `SELECT ... FOR UPDATE`. The plan's
ordering constraint 5 says exactly this, so the contention tests live in the
existing concurrency suite and run against MariaDB 11.4.2 in separate processes
released together.

### 3.1 What the concurrency tests establish

| Test | Result |
| --- | --- |
| 4 buyers, 1 animal, concurrent checkout | Exactly 1 placed, 3 refused, 1 live hold, column untouched |
| 12 workers, 1 animal, `placeForOrder()` directly, 4 rounds | Exactly 1 hold per round |
| 5 buyers, 3 units, concurrent checkout | Exactly 3 placed |
| 10 workers, 2 shared listings, opposite line order | No deadlock, all 10 placed |

### 3.2 Two findings about the tests themselves, both worth recording

**The end-to-end checkout test passes with the lock removed, for a reason that
is not the lock.** Discovered by reintroducing the defect and watching it stay
green. `CheckoutService` allocates a reference through `NumberingService`, which
takes `SELECT ... FOR UPDATE` on a per-organisation sequence row — so every
concurrent checkout for one farm serialises on that row and each worker sees the
previous hold committed. That is real protection but entirely incidental; it
would vanish the day numbering changed. A second test therefore races
`placeForOrder()` directly against pre-created orders, with no numbering in the
path, so the listing lock is the only thing standing between the workers.

**The negative control is partial, and that is stated rather than tuned away.**
With `lockForUpdate()` removed, twelve workers *did* produce two live holds on
one animal — a genuine oversell, observed. But it did not reproduce on demand:
repeated four-round runs against the unlocked code stayed green on an idle
machine. The workers that overlapped in the failing run were the slow ones
(124ms and 159ms against 12–17ms for the rest) — the window opened because the
machine was busy. The unguarded gap between the availability read and the insert
is microseconds wide, and process scheduling usually separates workers by more
than that.

So the contention suite is supporting evidence, not a deterministic control.
What the guarantee rests on is construction: the same `lockForUpdate()` on the
same rows in the same ascending order that `applyInventoryEffect()` has used
since CF-P0-006, whose own oversell *was* reliably demonstrable. Four rounds are
run rather than one because more attempts catch more; widening the target to six
units did not improve it either, and that was tried.

### 3.3 Two existing tests changed, and why

Both were creating more orders than stock — which checkout now refuses.

- `OrderConfirmationConcurrencyTest::seedOrders()` releases each hold as it
  builds the fixture. That file tests the *confirmation* layer, and the
  situation it must survive is real: holds that lapsed while several unpaid
  orders were outstanding, then staff confirming them all.
- `OrderConfirmationIntegrityTest::test_confirming_an_already_reserved_pig_listing_is_rejected`
  likewise. Its point — `reservePigListing()` must refuse the second
  confirmation — is unchanged; only the setup moved, and the test now
  demonstrates defence in depth rather than a single layer.

Neither was weakened to pass. In both cases the new first line of defence is
proved separately in `HoldConcurrencyTest`.

## 4. Commands run and exact results

```
$ ./vendor/bin/phpunit --filter HoldServiceTest
OK (17 tests, 39 assertions)

$ CF_CONCURRENCY_DB_PORT=3399 ... phpunit -c phpunit.concurrency.xml
OK (13 tests, 235 assertions)          # MariaDB 11.4.2, multi-process

$ ./vendor/bin/phpunit
Tests: 1650, Assertions: 4895, Skipped: 13        # 0 failures, 0 errors

$ bash scripts/testing/run-mariadb-tests.sh
Tests: 1650, Assertions: 4905, Skipped: 7          # 0 failures, 0 errors
```

## 5. Carried forward

| Item | Where it belongs |
| --- | --- |
| `held` shown on the listing state machine (§36 lists it as a state) | Not needed — the table carries it. Recorded so a later reader does not think §36 was missed |
| Buyer-facing countdown on the checkout page | Phase 15 UX; the setting and the expiry exist |
| Holds released when a Paystack payment is abandoned rather than failed | The sweep covers it on a timer. An explicit abandon signal needs Paystack webhook work — Phase 12 |

## 6. Status

**Phase 10: COMPLETE.** Phase 11 (pig to storefront, §35) is unblocked; it
depends on Phases 8 and 10, both now done.
