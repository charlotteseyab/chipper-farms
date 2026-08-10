# Phase 12 — Storefront → finance integration

## Document control

| Field | Value |
| --- | --- |
| Phase | 12 — Storefront → finance integration |
| Date | 2026-08-10 |
| Branch | `staging` |
| HEAD at phase start | `fef21c4` (Phase 11) |
| Blueprint anchor | §41 |
| Depends on | Phases 10 and 11 — both COMPLETE |
| Phase status | **COMPLETE for §41.** One significant gap is deferred and named in §5 — read that section before treating storefront money as finished |

## 1. §41 is a preservation requirement, not a build one

The whole of §41 is a table of the existing integration and one instruction:

> Preserve the current integration pattern. […] Do not add direct GL posting
> inside storefront checkout, payment callback, or order controllers.

That pattern is already correct: order confirmed → `StorefrontOrderConfirmed`
domain event → `StorefrontOrderConfirmedSubscriber` → `RecordPigSaleService` or
`RecordFeedSaleService` → the existing sale spine. It was verified here and it
holds. The P0 that broke it (IMP-044, storefront pig sales failing at a CHECK
constraint) was fixed before this phase.

So the deliverable is not new machinery. It is making the rule enforceable and
closing the visibility gap Phase 5 left behind.

## 2. The rule, as a regression guard

A rule stated in a document constrains nobody. `StorefrontFinanceBoundaryTest`
reads every PHP file under `app/Storefront`, `app/Http/Controllers/Storefront`,
`app/Http/Requests/Storefront` and `app/Models/Storefront`, and fails if any of
them mentions `PostingEngine`, `JournalBatchDraft`, `JournalBatch::create` or
`JournalLine::create`.

Three details that make it worth having rather than decorative:

- **Comments are stripped before searching.** Otherwise the docblock explaining
  the rule would fail the test that enforces it.
- **The sweep asserts it found files.** A file-scanning test that silently
  matches nothing passes forever after a directory move. It requires more than
  twenty files before it will report success.
- **It fails with the reason, not just the fact.** The message says why direct
  posting is wrong — the subscriber posts too, so the effect lands twice.

The temptation this guards against is real and specific. A developer debugging
something like IMP-044 — where storefront sales silently failed to reach finance
— is being pushed toward exactly the wrong fix: post it here, where you can see
it work. That produces something which passes testing and double-posts the
moment the subscriber runs.

A second test exercises the pattern end to end: checkout writes no journal at
all, confirmation raises the domain event, and the sale spine does the posting.

## 3. Settlement discrepancies are now visible (closes IMP-027)

`finance:reconcile-storefront-settlements` has detected these since the prior
remediation programme, and it is a good command — it reports rather than acts,
because every discrepancy it finds is a decision with money attached and
automatic correction risks a worse outcome than the discrepancy.

But it only ever spoke to a cron log. A treasurer looking at the finance screen
had no way to know a payment had been verified at the gateway and never reached
the books.

`NeedsAttentionService` now surfaces the same conditions where somebody will see
them:

| Condition | Severity | Why |
| --- | --- | --- |
| Paid at the gateway, never posted to finance | **Critical** | The farm has been paid and its accounts do not show it |
| Awaiting payment for over a day | Info | A buyer wandered off mid-payment; untidy, not urgent |

Ranking the abandoned-payment case alongside the money-missing one would train
people to ignore both, so they are deliberately different. The detail text tells
the reader not to resubmit anything to the provider on the strength of it — a
discrepancy can mean the money never moved, and acting blindly risks paying
twice. That is the command's own reasoning, carried onto the screen.

Nothing here fixes anything automatically, for the reasons the command already
documents.

## 4. Tests

5 tests in `StorefrontFinanceBoundaryTest`.

### 4.1 Verified in both directions

| Defect reintroduced | Result |
| --- | --- |
| A `PostingEngine` import added to `CheckoutService` | **The boundary test fails**, naming the file and §41 |
| The settlement check removed from `NeedsAttentionService` | **2 tests fail** |

## 5. What is NOT in this phase, and why — read this

**Storefront orders carry no tax.**

A storefront order records subtotal, delivery fee and total. There is no tax
column, `StorefrontOrderConfirmedSubscriber` sets no `tax_code_id` on the sale it
creates, and the invoice that results therefore has `tax_total` of zero. A
VAT-registered farm selling online collects no VAT and under-declares it.

This is a real money and compliance defect, not a cosmetic one. It is logged as
**IMP-091**, severity P1, and it is **open**.

It is not in this phase for a reason worth stating rather than glossing:

- §41 does not ask for it. §41 asks that the existing integration be preserved
  and not bypassed, which is what this phase did.
- §38 does ask for it ("show subtotal, delivery, tax, total"), and I deferred it
  from Phase 9 *to here*. Deferring it a second time needs a better reason than
  the first, so here it is:
- **Doing it properly means changing the sale spine.** Phase 2 built Ghana tax
  as *profiles with components* — VAT, NHIL and GETFund on a shared base. The
  sale spine carries a single `tax_code_id`. Threading a multi-levy profile
  through to the invoice needs additive schema on `pig_sales` and `feed_sales`
  plus changes to `PigSaleInvoicingSubscriber` and its feed counterpart. That is
  finance-engine work with money consequences and it deserves its own phase with
  its own tests.
- **Half of it is worse than none.** Charging the buyer tax at checkout while the
  invoice shows none produces two documents that disagree about what was owed —
  a harder problem than the one it set out to solve.

The design is worked out and recorded so the next session does not start cold:

1. `storefront_orders` gains `tax_total`, `tax_profile_id` and a `tax_snapshot`
   JSON column. `TaxCalculation::toSnapshot()` already exists for exactly this
   and is what makes a credit note reverse the *original* regime rather than
   today's (IMP-014).
2. `CheckoutService` computes tax with the real `TaxCalculator` against the
   organisation's `default_tax_profile_id` — the setting Phase 8 built and
   nothing yet consumes. Base is subtotal plus delivery.
3. Inclusive versus exclusive comes from the **profile's** `price_mode`, which
   the calculator already honours. Phase 8's `tax_display_mode` setting overlaps
   with it and should become presentation-only or be dropped; two sources of
   truth for one arithmetic decision is the mistake Phase 8 spent effort
   avoiding elsewhere.
4. `pig_sales` and `feed_sales` gain `tax_profile_id` + `tax_snapshot`; the
   invoicing subscribers pass them through so the invoice matches what the buyer
   was shown.
5. The governing test: **what the checkout page displays and what the posted
   invoice totals must be the same number**, for inclusive and exclusive
   profiles, with no profile configured, and after a partial return.

## 6. Commands run and exact results

```
$ ./vendor/bin/phpunit --filter StorefrontFinanceBoundaryTest
OK (5 tests, 11 assertions)

$ ./vendor/bin/phpunit
Tests: 1673, Assertions: 4937, Skipped: 13        # 0 failures, 0 errors

$ bash scripts/testing/run-mariadb-tests.sh        # MariaDB 11.4.2, port 3399
Tests: 1673, Assertions: 4947, Skipped: 7          # 0 failures, 0 errors
```

No migrations in this phase.

## 7. Status

**Phase 12: COMPLETE for §41.** The integration pattern is preserved, the rule
that keeps it preserved is now a test, and IMP-027 is closed.

Storefront tax (IMP-091) is open, designed, and should be its own phase before
production. Phase 13 (refunds, returns and recovery UX) is unblocked.
