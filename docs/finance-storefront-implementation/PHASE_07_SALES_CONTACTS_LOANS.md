# Phase 7 — Sales, customers, suppliers, invoices, loans

## Document control

| Field | Value |
| --- | --- |
| Phase | 7 — Sales, customers, suppliers, invoices, loans |
| Date | 2026-08-09 |
| Branch | `staging` |
| HEAD at phase start | `7c249b3` (Phase 8) |
| Blueprint anchor | §26 (loans), §27 (customers & suppliers), §28 (invoices & receipts), §29 (credit notes) |
| Depends on | Phase 1 (feature flags), Phase 2 (tax profiles) — both COMPLETE |
| Phase status | **COMPLETE** |

## 1. Why this phase came after Phase 8

Phase 7 was carried as **PARTIAL**: commit `daa461a` landed under its number but
delivered the Overview money cards, which were Phase 3's deferred work. The
blueprint's §26–§29 — the simplified layer over loans, contacts, invoices,
receipts and credit notes — had not been started. Phase 8 did not depend on it
(§34 depends on Phases 1 and 2), so it went first. This closes the gap.

## 2. What this phase found

Two things, and the first one is the same finding Phase 8 made about the
storefront.

### 2.1 Finance feature flags were enforced on three routes out of twenty

```
$ grep -rn "'feature:" routes/web.php
finance.overview / finance.quick_expense / finance.simple_purchase
```

`finance.sales_money_in`, `finance.contacts` and `finance.loans` — the flags for
this phase's surfaces — gated nothing. `FinanceNavigation` hid the menu entries
and every URL stayed live, which is precisely the "hiding a screen is not
protecting it" case prompt §110 names.

All three are now enforced on their route groups: invoices, receipts,
feed-sales, credit notes and the new Money In workspace behind
`finance.sales_money_in`; customers, suppliers and statements behind
`finance.contacts`; loans behind `finance.loans`. Tested in all three cases.

The **advanced** finance flags are still unenforced. That is Phase 14's scope
(§16, §18) and doing it piecemeal here would leave an inconsistent half-state.
One exception is taken and recorded in §3.4.

### 2.2 Credit notes had no user surface at all

`CreditNoteService`, `credit_notes`, `credit_note_lines` and
`SalesReturnRevenueSubscriber` have existed since the sales-return work. The
subscriber creates a numbered credit note on every recorded return. **Nothing in
the application ever displayed one.** A farm could reverse revenue, credit a
customer's account, and have no way to see that it had happened — let alone give
the customer the document §29 requires.

## 3. What was built

| § | Surface | Delivered |
| --- | --- | --- |
| §27 | Finance → People & Businesses | One workspace over `counterparties` with Customers / Suppliers / Balances tabs, search, and guided creation |
| §28 | Finance → Sales & Money In | Owed / received headline, invoices awaiting payment, and the "one button" record-payment with targeted allocation |
| §28 | Customer-facing documents | Printable invoice and receipt PDFs |
| §29 | Finance → Credits | Credit note list, detail and customer-facing document |
| §26 | Finance → Loans | Owner-language list: still to pay back, payments coming up |
| — | Feature gating | §2.1 |
| IMP-027 | Money In / Money Out | Closed by the Sales & Money In workspace |

Every one of these is an orchestration layer. `ReceiptService`,
`AllocationService`, `CustomerService`, `SupplierService`, `ArAgeingService`,
`ApAgeingService`, `LoanService` and `CreditNoteService` are all unchanged. No
new journal is posted anywhere in this phase.

### 3.1 The one button, and what it deliberately does not do

Blueprint §28 asks for "record payment: one button from invoice/customer/order"
and "allocate payment: automatic by default". To take money against an invoice a
farm previously had to open Receipts, start a new receipt, pick the customer,
type the amount, remember to tick auto-allocate, save, and trust that FIFO landed
it on the invoice they had in mind.

`MoneyInController::recordPayment()` calls `ReceiptService::createPosted()` and
then `AllocationService::allocate()`. Two decisions are worth stating:

**Allocation is targeted, not FIFO.** The receipt screen's auto-allocate walks a
customer's open invoices oldest-first, which is right when someone hands over
money against no particular bill. It is wrong here: the farmer pressed the button
on a specific row, and silently paying an older invoice instead would be the
software disagreeing with the person about what just happened. There is a test
with two invoices that fails if this is changed to FIFO.

**Overpayment is refused, not absorbed.** The amount is capped at the invoice
balance. Money in excess of a bill is a customer credit — a real thing this
system models through unallocated receipts, and a decision with consequences. It
should not be a rounding accident on a one-button form. The full receipt screen
is one click away and the error message says so.

### 3.2 People & Businesses, and a permission boundary it could have crossed

§27 asks for customers and suppliers to "become one People & Businesses
experience with role-filtered tabs". They were already one table —
`counterparties` has `type` with a `both` value — so the split was never in the
data, only in the UI.

Two boundaries had to survive the merge:

1. **Role decides permission.** Guided creation asks whether someone buys, sells
   or both, and the *chosen role* decides which permission is required. Someone
   holding `manage_customers` must not gain the ability to add suppliers by
   picking a different option — a supplier is who the farm pays. Tested in all
   three combinations.

2. **Payables are a narrower permission than receivables.** `finance/ageing/ap`
   requires `view_ap_ageing`; `finance/ageing/ar` requires only `view_finance`.
   That distinction already existed and a friendly "who owes what" summary
   showing both to everyone would have routed around it. The Balances tab builds
   the AP side only for users who hold `view_ap_ageing`, and says so plainly to
   those who do not.

Guided creation is deliberately narrower than the existing form, which asks for
currency, discount, payment terms, credit limit, category, tax id and
registration number. Correct for an accountant onboarding a distributor; wrong
for adding the man who buys two pigs a month. Name, role, and a way to reach
them — everything else takes the defaults `CustomerService` and `SupplierService`
already apply, including code allocation through `NumberingService`. The full
form is linked from the same panel.

### 3.3 Customer-facing documents read the organisation, not the environment

`StatementPdfRenderer` reads its company block from
`config('finance.statements.company_*')` — environment configuration, one company
name for the whole installation. That is the `system_configs` defect (IMP-001)
wearing a different hat: on a second tenant, one farm's invoices would carry
another farm's name and TIN.

`CustomerDocumentRenderer` reads the `organizations` row instead — name, legal
name, address, phone, email, tax identifier — with the env values kept as
fallback so a single-tenant installation configured the old way still prints the
same header. Both behaviours are tested.

Three documents: invoice, receipt, credit note. Each says on its face when it is
not what it appears to be — a cancelled invoice carries "this invoice has been
cancelled, it is not payable", a non-posted receipt says it is not evidence of
payment. Handing someone a document that looks payable when it is not is worse
than handing them nothing.

### 3.4 Credits are read-only, on purpose

There is no "create credit note" action, and that is design rather than
omission. §29's first requirement is "credit note created from sales return" and
its second is "idempotency key per sales return" — the numbered document is an
*effect* of a return, and its uniqueness guarantee is anchored to the return that
caused it. A screen that minted credit notes directly would step around both:
revenue reversed with no return to explain it, and a second reversal available
for the same goods.

`test_there_is_no_route_to_raise_a_credit_note_directly` walks the registered
route table and asserts every `finance.credit-notes.*` route is GET-only. The
sales-return UX that creates them is §43, which the plan assigns to Phase 13.

### 3.5 Loans, and the one Phase 14 exception

§26: "hide amortisation and liability terminology from owner view unless the user
opens details", and "view upcoming payments". The list was a register —
reference, lender, type, principal, outstanding — with the schedule reachable
only by opening a loan and scrolling past an amortisation table. The one thing an
owner needs first, *when is the next payment due*, was the hardest thing on the
screen to find.

The list now leads with what is still to pay back across active loans and the
next six scheduled payments, with overdue ones flagged. The detail page is
unchanged: amortisation, accrued interest and the transaction history stay where
an accountant will look for them.

**The exception:** §26 lists "reclassify current/non-current" under advanced
accounting, so the reclassification link is shown only when
`finance.advanced_accounting` is on. That is one presentation-level advanced gate
taken inside Phase 7 because §26 names it. The *route* is not gated — that stays
Phase 14's, along with the rest.

## 4. Tests

39 new tests across five files.

| File | Tests | What it guards |
| --- | ---: | --- |
| `MoneyInTest` | 12 | The money path: one journal, targeted allocation, overpayment, permissions, cross-organisation |
| `ContactsWorkspaceTest` | 10 | Tab filtering, `both`, guided creation, and the two permission boundaries |
| `CustomerDocumentTest` | 7 | PDFs render, header source, cross-organisation document access |
| `CreditNoteScreenTest` | 6 | List, totals, detail, and that the surface stays read-only |
| `LoansOwnerViewTest` | 4 | Still-owed total, upcoming payments, reclassification gating, feature gate |

### 4.1 Verified in both directions

| Defect reintroduced | Result |
| --- | --- |
| `recordPayment()` allocates FIFO instead of to the chosen invoice | `test_the_money_lands_on_the_chosen_invoice_not_the_oldest` fails |
| `recordPayment()` posts a second receipt alongside the first | `test_the_button_posts_exactly_one_journal_batch` fails |

Both were restored from backup and the suite re-run green.

### 4.2 Two test defects found and corrected

**A vacuous permission test.** `$this->user` from `FinanceTestSetup` carries the
`admin` role but no permission grants, because the permission seeders had not
run. Eleven of twelve `MoneyInTest` tests were failing with 403 — including the
ones asserting a *refusal*, which would have passed for the wrong reason had the
assertions been written the other way round. Fixed by seeding the real permission
seeders and using role-backed users, so the tests exercise the same
role→permission map production ships with.

**An assertion that matched the wrong element.** `assertDontSee('reclassification')`
failed because the legacy sidebar (`NavigationHelper`) carries its own
reclassification link, unconditionally. Narrowed to the page's own wording rather
than absorbing the sidebar into the assertion — and the sidebar's own ungated
link is recorded below rather than quietly fixed.

## 5. Commands run and exact results

```
$ ./vendor/bin/phpunit --filter Phase7
OK (39 tests, 120 assertions)

$ ./vendor/bin/phpunit
Tests: 1614, Assertions: 4792, Skipped: 13        # 0 failures, 0 errors

$ bash scripts/testing/run-mariadb-tests.sh        # MariaDB 11.4.2, port 3399
Tests: 1614, Assertions: 4802, Skipped: 7          # 0 failures, 0 errors
```

No migrations in this phase — every surface reads tables that already exist.

## 6. Carried forward

| Item | Where it belongs |
| --- | --- |
| Advanced finance flags (`advanced_accounting`, `wht`, `investor_management`, `bank_reconciliation`, `period_close`, `advanced_ap`, `customer_credit`, `procurement_approvals`, `advanced_biological`, `advanced_treasury`) enforced on routes | Phase 14 (§16, §18) |
| `NavigationHelper::sidebarSections()` still builds a hardcoded finance menu by role, in parallel with `FinanceNavigation` | Phase 14 / Phase 15. Found via §4.2; it is why the legacy sidebar links to loan reclassification regardless of the feature |
| Sales-return UX that creates credit notes | Phase 13 (§43) |
| Reverse/cancel "effects preview" (§28) | Reverse and cancel exist and are guarded; the *preview* is UX polish for Phase 15 (§54 error and recovery UX) |
| `sales.pricing.*` still global across tenants | The remainder of IMP-001; operational pricing work |

## 7. Status

**Phase 7: COMPLETE.** No phase in the plan is now PARTIAL.
