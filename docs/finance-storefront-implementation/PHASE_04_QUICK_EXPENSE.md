# Phase 4 — Quick Expense

## Document control

| Field | Value |
| --- | --- |
| Phase | 4 — Quick Expense |
| Date | 2026-08-07 |
| Branch | `staging` |
| HEAD at phase start | `8783595` |
| Blueprint anchor | §23 (quick expense), SEC-002 (bypass risk), §49 (permissions) |
| Prompt anchor | §31–§37 |
| Phase status | COMPLETE — attachments deferred (§8) |

## 1. Scope

The first phase in this programme that **writes money**. A farm operator records
that the farm paid for something, in farm language, and correct double-entry
happens underneath without them selecting an account.

## 2. The central design constraint

Blueprint SEC-002 states the risk plainly: *"Quick purchase/expense can
double-post if it bypasses posting engine."*

`RecordQuickExpenseService` therefore writes **no** journals, journal lines or
postings itself. It builds a `JournalBatchDraft` and hands it to
`PostingEngine::post()` — the same boundary every finance subscriber uses, which
owns period resolution, lock checks, account validation, idempotency and
numbering.

What the service adds is translation. *"Electricity, GHS 400, paid from MTN
MoMo"* becomes `Dr 6310 / Cr 1060`. That mapping is the entire product of this
phase; everything else is plumbing.

## 3. Idempotency at two layers, on purpose

Prompt §36 requires no duplication on double-click, retry, network retry or
browser resubmit. The realistic entry device is a phone at a fuel station with
one bar of signal, so this is the normal case rather than an edge case.

`PostingEngine` already refuses a duplicate automatic posting through
`journal_postings.idempotency_key`. That protects the ledger — but it protects it
by **throwing**, and a farm manager who double-tapped Save should see their
expense, not an error about duplicate postings.

So there are two layers:

1. `quick_expenses.idempotency_key`, unique per organisation, checked first. The
   second submission returns the original record.
2. The engine's guard, unchanged, as the backstop it was designed to be.

Three tests cover this, including one asserting the electricity account is
charged 400.00 and not 800.00 after a duplicate submission.

The form also mints a per-render UUID token. Relying on the derived key alone
would be *nearly* as good, but a farm that genuinely buys diesel twice in one day
for the same amount would have the second entry rejected as a duplicate. An
explicit per-render token removes that false positive.

## 4. Decisions worth recording

### 4.1 Pay-later requires a supplier

Paid-now works without a named payee — cash to a casual labourer is ordinary farm
spend. Pay-later does not: a payable is money owed to *someone*, and a liability
with no counterparty cannot be aged, chased or settled. Rather than inventing a
placeholder supplier, the service refuses and explains why.

### 4.2 An unmapped category goes to suspense, not to a blocked form

Blueprint §23 requires this. Money left the farm whether or not the chart of
accounts has the right bucket. Refusing the entry makes the books *less*
accurate, not more. The expense posts to `2910 Suspense — Payments` and is
flagged `needs_reclassification` for an accountant.

### 4.3 Bank and MoMo payments require a reference

Blueprint §23 item 7. Without a transaction id these cannot be matched against
the provider statement, and reconciliation is where mistakes are found. Cash
needs none.

### 4.4 The expense record is not a second ledger

The journal batch remains the source of truth. `quick_expenses` exists because a
journal cannot answer the questions a farm operator asks: which friendly category,
which receipt, entered by whom, paid now or on account. Reconstructing
"Electricity, paid from MTN MoMo" from accounts 6310 and 1060 is possible but
lossy.

The expense stores `expense_account_code` as resolved **at posting time**, so a
later change to the category mapping cannot make the record disagree with the
journal it produced.

### 4.5 `record_expenses` is deliberately a wide permission

Granted to data entry and farm manager as well as admin, accountant and
treasurer — broader than most finance permissions here.

Blueprint §14 puts "record expenses" in the Data Entry profile and §49 marks
Quick Expense as available to every operational role. Recording that money left
the farm is operational, not accounting. Routing it through an accountant
guarantees the expense is entered late, from memory, or not at all.

The controls that matter remain: posting goes through `PostingEngine` with its
period locks and account validation, submissions are idempotent, every expense
records who entered it, and nobody can post to an arbitrary account — they choose
a farm category and the mapping decides the ledger account. Not granted to
salesperson.

## 5. Code delivered

| File | Purpose |
| --- | --- |
| `config/expense_categories.php` | 21 farm-language categories → CoA codes; payment accounts; reference rules; suspense |
| `database/migrations/2028_09_03_100000_create_quick_expenses_table.php` | Farm-facing record with unique idempotency key |
| `app/Models/Finance/QuickExpense.php` | Decimal-safe model, friendly labels |
| `app/Services/Finance/Expenses/RecordQuickExpenseService.php` | Translation + validation; posts via `PostingEngine` |
| `app/Http/Controllers/Finance/QuickExpenseController.php` | List, form, store; per-render idempotency token |
| `resources/views/finance/expenses/{index,create}.blade.php` | Mobile-first screens |
| `database/seeders/QuickExpensePermissionsSeeder.php` | `record_expenses` |
| `routes/web.php` | Three routes, feature-gated and permission-gated |

## 6. Tests

22 tests in `tests/Feature/Finance/Expenses/QuickExpenseTest.php`: posting and
balance, category→account mapping, MoMo vs cash, pay-later to payables,
idempotency (4), validation (7), suspense routing, and two configuration-integrity
tests.

The configuration tests are worth calling out. They walk every category and
payment method in config and assert the account exists in
`ChartOfAccountsSeeder`. A category pointing at a missing account fails at
posting time — *after* the user has filled in the form — which is the worst
possible moment to discover a config error.

## 7. Verification in both directions

| Mutation | Expected | Observed |
| --- | --- | --- |
| Idempotency short-circuit disabled | The four duplicate-submission tests fail | 4 failed, 18 passed — exactly those four |

Reverted; suite green.

## 8. Deliberately deferred — attachments

Blueprint §23 item 8 and prompt §35 ask for a receipt photo or PDF on the
expense.

Not built here. The codebase already has a finance attachment subsystem with
private-disk storage and signed downloads (`finance/attachments`, and CF-P1-019
in the prior programme concerned its parent-type coverage). Wiring Quick Expense
into it means extending that subsystem's polymorphic parent support and its
authorization to a new parent type — a change to a *security-sensitive shared
component*, not a bolt-on.

Doing that properly needs its own tests for cross-organisation download refusal
on the new parent type. Bundling it into this phase would either rush that or
inflate the phase. It is recorded as open in the register and is the first item
of Phase 6, which also needs attachments for purchase invoices.

Also deferred: allocation to a specific pig, group or facility. The columns exist
and are written; the UI selects "whole farm". Meaningful allocation needs the
costing integration that Phase 6 brings.

## 9. Commands run and results

| Command | Result |
| --- | --- |
| `php -l` on all new PHP files | No syntax errors |
| `php artisan migrate --force` | `create_quick_expenses_table` applied |
| `php artisan route:list --path=finance/expenses` | 3 routes registered |
| `php artisan test .../QuickExpenseTest.php` | **22 passed** (67 assertions) |
| Idempotency mutation | 4 failed / 18 passed — correct four |
| `php artisan test` (full) | Recorded in §12 |

## 10. Problems found and corrected

| # | Problem | Disposition |
| --- | --- | --- |
| 1 | `NumberingService` imported from `App\Services\Finance\` — it lives in `App\Services\Numbering\` | Corrected. Would have been a fatal on first call. |
| 2 | My test called `Counterparty::factory()`, which does not exist | Corrected to `forceCreate`. |
| 3 | My balance assertion was malformed — `assertSame` with four arguments, and it would have passed on an all-zero journal | **My error.** Rewritten to assert the journal both balances *and* moved 400.00. A balanced journal of zero and zero is still balanced. |

Problem 3 is the instructive one: the original assertion would have passed
against a service that posted nothing at all.

## 11. Unresolved risks carried forward

| Risk | Carried to |
| --- | --- |
| Receipt attachments not wired | Phase 6, with the shared attachment subsystem's authorization tests |
| Allocation UI not built | Phase 6 |
| Recoverable input tax posts to the expense rather than a tax asset account | Phase 6 — needs the input-VAT account mapping that purchases also require |
| No concurrency test for simultaneous duplicate submissions | Phase 16. The unique index makes double-insert structurally impossible, but that is an argument, not evidence. |

## 12. Exit criteria

| Prompt §37 criterion | Met |
| --- | --- |
| Cash expense | Yes |
| MoMo expense | Yes |
| Bank expense | Yes |
| Pay-later expense | Yes |
| Supplier optional | Yes — optional for paid-now, required for pay-later by design |
| Tax | Yes — Phase 2 profile applied and snapshotted |
| Attachment | **No** — deferred, §8 |
| Allocation | Partial — stored, not selectable |
| Duplicate submission | Yes |
| Invalid account | Yes — suspense routing |
| Closed period | Inherited from `PostingEngine`; not separately tested here |
| Organisation access | Yes |
| Permissions | Yes |
| Reversal/correction | **No** — Phase 13 owns reversal UX |

**Phase 4 status: COMPLETE** for the recording workflow; attachments and
allocation UI deferred to Phase 6 and recorded.
