# Phase 13 — Refunds, returns and recovery

## Document control

| Field | Value |
| --- | --- |
| Phase | 13 — Refunds, returns and recovery UX |
| Date | 2026-08-10 |
| Branch | `staging` |
| HEAD at phase start | `78fb2ad` (Phase 12b) |
| Blueprint anchor | §42 (refund lifecycle), §43 (sales-return lifecycle), §44 (feed return), §45 (pig return) |
| Depends on | Phase 12 / 12b — COMPLETE |
| Phase status | **COMPLETE for the accounting half.** The return-request UI is not built and is named in §5 |

## 1. What §42–§45 already had

The blueprint is explicit that this area is mostly built: §42 ends "current code
already implements most of this and should be wrapped with clearer admin UX",
and §45 ends "current code already treats pig quantity as one in
`RecordSalesReturnService`". That was verified and holds — the return lifecycle,
the COGS reversal, the inventory restore, the credit note, the Paystack refund
submission and the idempotency keys are all present and tested.

§44 names the one exception, and names it as future work:

> Current return support exists; **future implementation should add tax snapshot
> reversal.**

## 2. Phase 12b turned that from a gap into a defect

`CreditNoteService` hard-coded `tax_total => '0.00'`. That was *honest* while
sales carried no tax — there was nothing to reverse.

Phase 12b made storefront sales charge VAT. From that moment a zero here means
the farm collects tax on goods that come back and never reverses it, while the
customer is credited the net having paid the gross. A defect created by the
previous phase's own success, which is exactly the kind that gets missed.

## 3. Architecture decisions

### 3.1 The reversal scales the original, it does not recompute

`TaxCalculation::proportion()` scales the stored components. IMP-014 established
why: a sale taxed under Ghana's old cascading regime must be credited at *that*
regime's figure, whatever the farm charges today. Re-running today's profile
over the returned amount would strand the difference permanently.

A sale with no snapshot — every sale before Phase 12b, and every sale by a farm
that charges no tax — reverses nothing, which is correct.

### 3.2 Revenue comes back net; the tax comes off the liability

`buildPostingDraft()` previously debited the whole line total to revenue. With
tax now in that total, debiting all of it would reduce income by more than was
ever earned — the tax was never income, it was money held for the revenue
authority.

The posting is now:

```
Dr  Revenue         net returned
Dr  VAT control     tax returned        (only when there is tax)
    Cr  Customer offset      gross
```

Debiting `VAT_CONTROL` mirrors the way `InvoicePostingService` credits it on the
way out — the same account, the opposite direction — so a sale and its return
meet in one place. Untaxed returns do not touch the account at all, which is
tested, because a farm with no tax configured has no VAT control account to
resolve and a stray reference would fail its returns.

## 4. Tests

6 unit tests in `ReturnTaxReversalTest` and 1 end-to-end in
`SalesReturnRevenueTest`.

### 4.1 Verified in both directions

| Defect reintroduced | Result |
| --- | --- |
| The tax reversal hard-coded back to `'0.00'` | **Fails** — "the farm keeps VAT on goods that came back" |
| Revenue debited gross instead of net | **Fails** — the posting no longer balances the way it should |

### 4.2 A negative control that did not fail, and what it exposed

Reintroducing the first defect initially broke **nothing**. The unit tests
exercised `reversedTax()` and `buildPostingDraft()` separately and nothing
touched the line in `ensureForSalesReturn()` that joins them.

A guard nothing exercises is not a guard. The end-to-end test was written in
response, and it lives in `tests/Feature/Finance/SalesReturns/` where the fiscal
period and chart-of-accounts fixtures already exist rather than duplicating that
scaffolding into the storefront suite. With it, the same defect fails loudly.

This is the second time in two phases that reintroducing a defect proved a test
weaker than it looked. It is worth doing every time for exactly that reason.

### 4.3 A fixture whose arithmetic I got wrong

The "old regime" snapshot initially asserted a total of 21.90 against components
summing to 20.75. The components were right — NHIL 2.5 + GETFund 2.5 on 100,
then VAT 15% on 105 — and the total was mine. 21.9% is the programme's figure
for the old regime *including* the COVID-19 levy, which that profile does not
carry. Corrected to 20.75, with the reasoning left in the test so the next
reader does not repeat it.

### 4.4 A latent fatal caught by reading

The VAT line first referenced `$this->accountResolver`; the injected property is
`$this->accounts`. It would have thrown only when a taxed return was posted —
invisible in every existing test, since none of them carried tax.

## 5. What is NOT in this phase

**There is no screen for requesting a return.** §43 step 1 is "user requests
return/refund with reason and quantity", and `php artisan route:list` matches
nothing for "return". Returns are recorded by `RecordSalesReturnService`, which
today is reachable only from the storefront refund path and from tests.

Logged as **IMP-098**, open. It is UX over a service that already exists and is
now correct, which is the right order — a screen that recorded returns while the
credit note stranded the tax would have made the defect easier to hit, not
harder. §42's "wrap with clearer admin UX" is the same body of work and belongs
with it.

## 6. Commands run and exact results

```
$ ./vendor/bin/phpunit --filter 'SalesReturnRevenueTest|ReturnTaxReversalTest'
OK (13 tests, 35 assertions)

$ ./vendor/bin/phpunit
Tests: 1689, Assertions: 4973, Skipped: 13        # 0 failures, 0 errors

$ bash scripts/testing/run-mariadb-tests.sh        # MariaDB 11.4.2, port 3399
Tests: 1689, Assertions: 4983, Skipped: 7          # 0 failures, 0 errors
```

The server was confirmed up before the gate ran, after IMP-096.

No migrations in this phase — Phase 12b's snapshot columns are what made it
possible.

## 7. Status

**Phase 13: COMPLETE for the accounting half.** §44's named future item is
built: a return now credits the tax that was charged, at the rate that was
charged, and posts it against the liability rather than against income.

The return-request UI (IMP-098) is open and is the natural start of Phase 15's
UX pass.
