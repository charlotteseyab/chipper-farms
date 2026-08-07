# Phase 6 — Simple Purchase

## Document control

| Field | Value |
| --- | --- |
| Phase | 6 — Simple Purchase |
| Date | 2026-08-07 |
| Branch | `staging` |
| HEAD at phase start | `463e1e5` |
| Blueprint anchor | §24 (purchase tracks), §23 item 8 (attachments) |
| Prompt anchor | §45–§52 |
| Phase status | **6a COMPLETE** (attachments). **6b NOT STARTED** — blocked on a design question, see §3 |

## 1. Phase 6a — receipt attachments (COMPLETE)

Delivered in commit `d34b033`. Closes the item Phase 4 deferred.

Quick Expense can now hold its receipt. This required adding a parent type to
`AttachmentController::PARENT_MAP`, the shared map that also gates payment,
receipt, invoice, vendor-invoice and refund evidence — and the site of both
CF-P0-004 and CF-P1-019 in the prior remediation programme.

That map proved well built. The route pattern derives from it via
`supportedTypePattern()`, so a single entry propagates to routes, upload,
download and delete with no possibility of the three disagreeing. That was
precisely the CF-P1-019 fix, doing its job.

**Permission choice.** `write` is `record_expenses`, not a finance-module
permission. The purpose of Quick Expense is that a store officer photographs the
receipt where they are standing; requiring `view_finance` to attach it would put
the evidence out of reach of the person holding it. Download accepts view **or**
write, so that officer sees what they uploaded without being granted the finance
module, and an accountant sees it without being able to record expenses.

**Tests.** 9 tests, written as the four ways this goes wrong rather than as
"can a file be attached": attaching without permission, downloading without
permission, downloading from another organisation, and route/resolver
disagreement. Both cross-organisation cases are covered because CF-P0-004 was
exactly a same-organisation user streaming any attachment by id.

All 17 attachment tests across the whole subsystem pass, so the existing parent
types are unaffected. Full suite 1468 passed / 13 skipped / 0 failed.

**One correction, mine not the application's.** Three download tests initially
returned 302 to `/login`. Diagnosed by printing the redirect target rather than
assuming an authorization defect: switching `actingAs` identity mid-test leaves
the previous user's session, which survives the next `actingAs`. Fixed with
`flushSession()` in the tests. The application behaved correctly throughout.

## 2. Phase 6b — Simple Purchase (NOT STARTED)

Not built. This section records why, and exactly what the next session needs to
resolve first, so the investigation is scoped rather than repeated.

## 3. The blocking question

Blueprint §24 requires Simple Purchase to support a **stock-tracked** purchase:
buy maize, inventory rises once, cash or payables falls once, valuation updates,
tax handled, accounting balanced.

The obvious implementation is:

1. `InventoryService::recordReceive()` for the subledger, and
2. a `JournalBatchDraft` posting `Dr Inventory / Cr Cash-or-AP` for the GL.

**Step 2 may be a double-post.** Evidence gathered:

| Observation | Source |
| --- | --- |
| `InventoryService` contains no reference to `PostingEngine`, `JournalBatchDraft` or `postingEngine` | grep across the service |
| `recordReceive()` calls `commit()`, which writes an `InventoryMovement` | `InventoryService.php:139–196` |
| GL posting for inventory events happens in subscribers | `app/Finance/Subscribers/Inventory/` contains `RawMaterialReceivedSubscriber`, `FeedBatchProducedSubscriber`, `RawMaterialAdjustedSubscriber` |

So the established architecture is **source action → domain event → subscriber →
GL**, and the subledger write is separate from the ledger write.

If `RawMaterialReceivedSubscriber` (or an equivalent for the item kinds Simple
Purchase would receive) already posts `Dr Inventory / Cr GR-IR-or-AP`, then a
Simple Purchase service that also posts its own journal would debit inventory
twice for one delivery. Inventory would be overstated by the value of every
purchase, and the subledger-to-control-account reconciliation — which this
codebase already runs — would drift by exactly that amount.

This is blueprint risk **SEC-002** ("Quick purchase/expense can double-post if
it bypasses posting engine") in its most literal form.

## 4. Why this was not resolved by guessing

The prompt forbids duplicating accounting effects, and this programme has
already corrected several defects that came from assuming rather than checking:
an `event_unit_cost` column assumed to be populated that never was, a
`fail()` method assumed to be free that collided with a framework signature,
status constants assumed to exist that did not.

Building a money path on an unverified assumption about who owns the GL posting
would be the same mistake with a larger blast radius. An expense recorded to the
wrong account is a reclassification; inventory debited twice is a valuation
error that propagates into COGS on every subsequent sale.

## 5. RESOLVED — the GL is already owned by the subscriber

Investigated rather than left open. The answer is unambiguous:

| Component | Behaviour | Evidence |
| --- | --- | --- |
| `ReceiveRawMaterialService` | Dispatches `RawMaterialReceived` | `ReceiveRawMaterialService.php:220` |
| `RawMaterialReceivedSubscriber` | Subscribes to `RawMaterialReceived::TYPE` and posts the GL | `:76`, `:147–161` |
| The posting | `Dr Inventory / Cr GR-IR` when a purchase request exists; `Dr Inventory / Cr Inventory Suspense` for a direct receipt with no PR | `:28–44`, `:130–134` |

**So a Simple Purchase service must NOT post `Dr Inventory / Cr AP` itself.**
Doing so would debit inventory twice for one delivery — once from the subscriber
and once from the purchase — overstating inventory by the value of every stock
purchase and drifting the subledger-to-control reconciliation by exactly that
amount, with the error propagating into COGS on every subsequent sale.

### 5.1 The correct design

Simple Purchase is an orchestration layer over services that already exist, and
the credit side is a **clearing account**, not the final one. This is the
standard GR/IR three-way match, which this codebase already implements:

```
Stock line, receive:      ReceiveRawMaterialService
                          → Dr Inventory / Cr GR-IR (subscriber posts this)

Stock line, pay later:    VendorInvoiceService
                          → Dr GR-IR / Cr Accounts Payable

Stock line, paid now:     the above, then a payment
                          → Dr Accounts Payable / Cr Cash
```

The GR/IR account is the join. Receipt without invoice leaves a credit balance
there (goods received, not yet billed); invoice without receipt leaves a debit.
That is what GR/IR ageing exists to surface, and `finance/ageing` already reports
it.

Non-stock lines do not touch inventory and follow the Quick Expense shape:
`Dr Expense / Cr Cash-or-AP`, posted directly through `PostingEngine`.

### 5.2 What to build

1. `simple_purchases` + `simple_purchase_lines`, with the unique idempotency key
   pattern used by `quick_expenses` and `cash_transfers`.
2. A service that, per line, either calls `ReceiveRawMaterialService` (stock) or
   builds an expense leg (non-stock) — and **never** posts an inventory journal
   of its own.
3. Pay-later wraps `VendorInvoiceService::createDraft()`, which requires a
   supplier — the same constraint Quick Expense already enforces, for the same
   reason.
4. A test asserting the **inventory control account is debited exactly once** for
   one stock purchase. That is the direct guard against §3, and it is the single
   most important test in the phase.
5. A test asserting the GR/IR balance nets to zero once the bill is recorded, so
   the clearing account does not silently accumulate.

The non-stock path should not ship alone. A purchase form handling only non-stock
lines is not what blueprint §24 asks for, and shipping half invites the stock
half to be bolted on later without this ownership question being re-asked.

## 6. Also outstanding for Phase 6b

| Item | Note |
| --- | --- |
| Multi-line purchases | Blueprint §46: support without creating an unusable form |
| Pay-later → supplier bill | Must wrap `VendorInvoiceService::createDraft()`, which requires a supplier — same constraint Quick Expense already enforces |
| Advanced procurement passthrough | When `finance.procurement_approvals` is on, expose the existing PR → approval → receive → vendor invoice lifecycle rather than duplicating it |
| Purchase attachments | The `PARENT_MAP` pattern from 6a applies directly |
| Quick Expense allocation UI | Deferred from Phase 4; the columns exist and are written |

## 7. Status

**Phase 6a: COMPLETE.**
**Phase 6b: NOT STARTED**, blocked on the GL-ownership question in §3, with the
investigation scoped in §5.
