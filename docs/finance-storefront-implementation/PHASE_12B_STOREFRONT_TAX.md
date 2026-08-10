# Phase 12b — Storefront tax (IMP-091)

## Document control

| Field | Value |
| --- | --- |
| Phase | 12b — Storefront tax |
| Date | 2026-08-10 |
| Branch | `staging` |
| HEAD at phase start | `cb71d8e` (Phase 12) |
| Blueprint anchor | §38 ("show subtotal, delivery, tax, total"), §30 (tax profiles), §41 (integration preserved) |
| Closes | **IMP-091** |
| Phase status | **COMPLETE** |

## 1. Why this exists as its own phase

Phase 12 recorded IMP-091 and deferred it, having already deferred it once from
Phase 9. Deferring a P1 money defect a third time would have been drift, and
§42 — the next phase in plan order — is described by the blueprint itself as
"current code already implements most of this and should be wrapped with clearer
admin UX". Closing a live compliance defect beats UX polish.

## 2. The defect

A storefront order recorded subtotal, delivery fee and total. There was no tax
column. `StorefrontOrderConfirmedSubscriber` set no tax on the sale it created,
so the invoice that resulted totalled zero tax.

A VAT-registered farm selling online therefore collected no VAT and
under-declared it. §38's requirement to "show subtotal, delivery, tax, total"
could not be met because there was nothing to show.

## 3. Architecture decisions

### 3.1 The real calculator, not an effective rate

`StorefrontTaxService` calls `TaxCalculator` — the engine finance posts from —
not a simplified copy.

Ghana's 2026 regime charges VAT, NHIL and GETFund on a *shared* base, which is
what makes 15 + 2.5 + 2.5 come to exactly 20% of the net rather than the old
cascade's 21.9%. A single "combined rate" can imitate that one case and breaks
on a fixed levy, on a component that compounds on the others, and on rounding. A
shop computing tax its own way would disagree with its own invoices — worse than
charging none.

### 3.2 Inclusive versus exclusive belongs to the profile

`TaxCalculator` already honours `TaxProfile::price_mode` and solves the net back
out of a tax-inclusive price. Phase 8's storefront `tax_display_mode` setting
overlaps with it; letting both decide would be a second source of truth for one
arithmetic decision — the mistake Phase 8 spent effort avoiding for payment
methods and categories.

So the profile decides the arithmetic and the setting is presentation only.
There is a test that an inclusive profile does **not** grow the total, because
adding tax to a price that already contains it charges the buyer twice.

### 3.3 Delivery is in the taxable base

Carriage is part of the consideration for the supply. Taxing the goods but not
the delivery under-declares on every delivered order. Tested: 100 + 50 delivery
at 20% is 30.00 of tax and a total of 180.00.

### 3.4 The snapshot, not just a number

`TaxCalculation::toSnapshot()` is stored on the order and on the sale. Phase 2
established why (IMP-014): a credit note must reverse the regime that applied at
the time, not today's. A 2024 sale taxed under the old cascade is credited at
that regime's figure in 2026, or 1.90 of tax is stranded permanently. That needs
the component breakdown, not one total.

### 3.5 Apportionment, and why it is not a loop of proportions

Each order line becomes its own sale and its own invoice, so the order's tax has
to be split. Scaling per line and rounding each independently does not add up:
three lines of 33.33 at 20% round to 6.67 each — 20.01 against an order tax of
20.00. The buyer was charged one number and the invoices would total another,
which is the precise failure this change exists to prevent.

`StorefrontTaxService::allocate()` gives the last line the residual so the parts
sum to the whole exactly. There is a test on that boundary, and reintroducing
independent rounding fails it.

Delivery's share of the tax rides on the lines proportionally rather than
becoming a phantom delivery line on one invoice — a charge on a document that
never mentions it would be harder to explain than the apportionment.

### 3.6 Two posting paths, because there are two

- **Pig sales** invoice, and `InvoicePostingService` already credits a tax
  account from `invoice.tax_total`. So the pig path needed only the line to
  carry the amount.
- **Feed sales** post revenue straight to the ledger with no invoice. Without an
  explicit tax line the buyer would have been charged tax that never reached the
  books — the farm holding money it had not recorded as owed. `Dr` the customer
  for the gross, `Cr` revenue for the net, `Cr` VAT control for the difference,
  and only when there is tax, so untaxed farms are untouched.

Both credit the same `VAT_CONTROL` account, so a pig sale and a feed sale under
one regime land in one place.

### 3.7 `InvoiceLineDraft` takes an amount, and refuses both

`taxRatePercent` can only express a single percentage. A caller holding a
`TaxCalculation` passes `taxAmount` instead, and supplying both throws rather
than one quietly winning — a caller with two different ideas about the tax has
only one right answer and picking silently is how the invoice ends up
disagreeing with the receipt.

## 4. Tests

9 tests in `StorefrontTaxTest`, including the end-to-end one that matters: an
order is placed with tax, confirmed, routed through the sale spine, and the
posted journal credits the tax control account with **exactly** what the buyer
was charged.

### 4.1 Verified in both directions

| Defect reintroduced | Result |
| --- | --- |
| The VAT line removed from the feed revenue posting | **Fails** — "the farm has taken money it has not recorded as owed" |
| Allocation rounds each share independently | **Fails** — the apportioned tax no longer sums to what was charged |

## 5. A defect I introduced and caught

The migration's `down()` dropped the sale-table foreign key by passing a
hand-built constraint name where Laravel expects a column list. That produced
`<table>_<table>_tax_profile_id_foreign_foreign`, which does not exist, so the
drop silently did nothing and SQLite then refused the column drop with "unknown
column in foreign key definition".

Found by running the rollback rather than assuming it worked. Fixed by dropping
by column, and by separating the constraint drop from the column drop into two
statements. Rollback and re-apply now verified on both engines.

## 6. An infrastructure note worth recording

The MariaDB 11.4.2 server shut down mid-session, and a backgrounded gate run
reported exit 0 with **no output at all** — it had never connected. That is the
same failure `MARIADB_11_4_GATE_FINDING.md` documents: the server process is
reaped when its parent shell exits.

The empty output is what gave it away. A gate that reports success without
running is worse than one that fails, so it is recorded here: **check the gate
produced test counts, not just an exit code.** The server was restarted with
the documented script and every result below was measured against a live server.

## 7. Commands run and exact results

```
$ ./vendor/bin/phpunit --filter StorefrontTaxTest
OK (9 tests, 25 assertions)

$ ./vendor/bin/phpunit
Tests: 1682, Assertions: 4962, Skipped: 13        # 0 failures, 0 errors

$ bash scripts/testing/run-mariadb-tests.sh        # MariaDB 11.4.2, port 3399
Tests: 1682, Assertions: 4972, Skipped: 7          # 0 failures, 0 errors

$ phpunit -c phpunit.concurrency.xml                # multi-process, 11.4.2
OK (13 tests, 235 assertions)
```

Migration verified up, rollback and up again on SQLite and MariaDB 11.4.2.
Purely additive: nullable columns on three tables. Every existing sale reads as
"no profile-based tax", which is what it is — the single-code `tax_code_id` path
is untouched and still works.

## 8. Carried forward

| Item | Note |
| --- | --- |
| Phase 8's `tax_display_mode` setting is now redundant with the profile's `price_mode` | It governs presentation only. Worth removing or relabelling in Phase 15's UX pass so a farm cannot set two things that mean the same |
| Per-component GL routing (NHIL and GETFund to their own accounts) | All components credit `VAT_CONTROL`, which is what `InvoicePostingService` already did — its own comment says "Phase 3+ will route per `tax_code.gl_account_hint`". Unchanged here, so the storefront matches the rest of the system rather than diverging from it |
| Tax on the guest tracking page | The order carries it; the guest page shows subtotal, delivery and total. A line there is a small Phase 15 addition |

## 9. Status

**Phase 12b: COMPLETE. IMP-091 closed.** What the buyer is charged and what the
farm's books record are now the same number, proved end to end.
