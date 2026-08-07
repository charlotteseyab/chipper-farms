# Chipper Farms Finance + Storefront Implementation Plan

## Document control

| Field | Value |
| --- | --- |
| Created | 2026-08-07 |
| Branch | `staging` |
| Blueprint baseline | `9cb8863f5d3aa3be570ebf270830906f7c57da8a` |
| Implementation start HEAD | `e9bc329c3a33eb128e191a6a720354af300a5a95` |
| Authority | `CHIPPER_FARMS_FINANCE_STOREFRONT_FINAL_ARCHITECTURE_AND_IMPLEMENTATION_BLUEPRINT.md` |
| Supporting context | `CHIPPER_FARMS_FINANCE_STOREFRONT_SIMPLIFICATION_REVIEW.md` |

Where the blueprint and the simplification review disagree, the blueprint wins.
Where any document and current source disagree, **current source wins**.

## Governing principle

> Simple farm transactions on top; rigorous accounting underneath.

Simplify the experience. Never simplify by weakening accounting.

## Preservation rule

The finance and commerce engine is not being rebuilt. New simplified workflows are
orchestration layers **above** existing services. Preserved without duplication:
`PostingEngine`, journal posting idempotency, `NumberingService`, the domain
outbox and `OutboxClaimer`, finance subscribers, invoice/receipt/payment
services, vendor invoice and loan services, the inventory and biological
subledgers, sales-return and credit-note services, Paystack verification,
signature handling and refund processing, COD settlement controls,
`StorefrontReferenceAllocator`, order confirmation locking, the listing state
machine, organisation scoping, permissions and policies, and audit
infrastructure.

## Phase plan

| # | Phase | Blueprint anchor | Depends on | Status |
| --- | --- | --- | --- | --- |
| 0 | Baseline, architecture verification, scope lock | §2, §6, §8 | — | COMPLETE |
| 1 | Organisation feature flags and module presets | §32, §33 | 0 | COMPLETE |
| 2 | Ghana tax architecture and tax profiles | §13, §30 | 0 | NOT STARTED |
| 3 | Finance navigation, Overview, Needs Attention | §20, §22 | 1 | NOT STARTED |
| 4 | Quick Expense | §23 | 1, 2 | NOT STARTED |
| 5 | Cash, Bank & MoMo workspace | §25 | 1 | NOT STARTED |
| 6 | Simple Purchase | §24 | 1, 2, 5 | NOT STARTED |
| 7 | Sales, customers, suppliers, invoices, loans | §26–§29 | 1, 2 | NOT STARTED |
| 8 | Storefront settings and catalog configuration | §34 | 1, 2 | NOT STARTED |
| 9 | Guest cart and checkout | §38 | 8 | NOT STARTED |
| 10 | Checkout holds and reservations | §36, §37 | 9 | NOT STARTED |
| 11 | Pig to storefront | §35 | 8, 10 | NOT STARTED |
| 12 | Storefront → finance integration | §41 | 10, 11 | NOT STARTED |
| 13 | Refunds, returns and recovery UX | §42–§45 | 12 | NOT STARTED |
| 14 | Advanced feature gating | §16, §18 | 1, 3–13 | NOT STARTED |
| 15 | Responsive UX and no-dead-end audit | §46, §55 | 3–14 | NOT STARTED |
| 16 | Full security and regression | §47–§49 | 1–15 | NOT STARTED |
| 17 | Local production certification | §60 | 16 | NOT STARTED |
| 18 | Final implementation review | §63, §64 | 17 | NOT STARTED |

Phase reports live in `docs/finance-storefront-implementation/`.
Per-item tracking lives in `CHIPPER_FARMS_FINANCE_STOREFRONT_IMPLEMENTATION_STATUS.csv`.

## Ordering constraints that are not negotiable

1. **Feature flags precede navigation changes.** The blueprint says so (§32) and
   the prompt repeats it. Hiding screens before the flag layer exists produces
   dead ends with no recovery path.
2. **Tax architecture precedes any simplified sale or purchase UI.** Quick Expense
   and Simple Purchase both take a tax profile; building them first would bake in
   a single-rate assumption that Ghana's same-base VAT/NHIL/GETFund model breaks.
3. **Storefront settings precede guest checkout.** Checkout must show only enabled
   payment methods and configured delivery, both of which are settings.
4. **Holds precede pig-to-storefront completion and finance integration.** The
   oversell guarantee depends on holds existing.
5. **Concurrency-sensitive phases are validated on MariaDB, not SQLite.**
   Blueprint §60: SQLite alone is not acceptable for money-critical lock
   behaviour.

## Standing engineering constraints

Carried from the prompt and from the prior remediation programme. These are not
advisory:

- No disabling routes to hide defects, weakening validations, bypassing
  permissions, removing failing tests, weakening assertions, converting failures
  to warnings, swallowing exceptions, silently clamping invalid values, or
  skipping tests to reach green.
- No `--ignore-platform-reqs`, no unsafe mass updates, no fake success responses,
  no hard-coded production credentials.
- **Never call the live Paystack environment** from development or automated
  tests. Sandbox only, through environment configuration.
- No committing `.env`, secrets, keys, database dumps with real data, customer
  personal information, or production logs.
- No destructive rewriting of deployed migration history. Prefer new corrective
  migrations. Backfills must be resumable and idempotent. Validate before adding
  non-null constraints.
- No force-push, no history rewrite. Each completed phase commits separately.
- Money and quantities use decimal strings and `bcmath`. Never binary floating
  point for money, tax, unit cost, valuation, interest, refund or return value.
- Server calculates all totals. Client totals are never trusted.

## Known risks carried into implementation

| Risk | Origin | Mitigation |
| --- | --- | --- |
| Global `system_configs` has no `organization_id`; live sale pricing is shared across tenants | Phase 0 §6 | Flags get their own scoped table; settings migration is a separate tracked workstream |
| MariaDB gates run on EOL 10.4.32, not the certified 11.4.2 | Phase 0 §8 | Re-point and re-run in Phase 17; all concurrency claims re-derived there |
| Hiding advanced finance breaks accountant workflow | Blueprint §57 | Existing organisations backfill to Full Accounting; nothing hidden by default |
| Quick Expense could bypass accounting controls | Blueprint SEC-002 | Implemented strictly as a wrapper over existing posting services |
| Holds could deadlock | Blueprint §57 | Deterministic lock ordering plus expiry job; proven under real contention |
| Ghana tax defaults cannot be self-certified | Blueprint §61 | Accountant/GRA confirmation required before production |
| Paystack sandbox credentials unavailable | Prior programme | External certification runbook |
| CI has never run on a real runner | Prior programme | External certification runbook |

## Definition of done for a phase

A phase is complete only when all of the following hold:

1. Blueprint requirements for the phase are implemented in source.
2. Migrations are additive and reversible where the blueprint requires it.
3. Permissions and organisation scope are enforced, and feature flags are not
   used as the authorization control.
4. Tests exist, are executed, and pass — including MariaDB where DB semantics
   matter and concurrency where money or stock is at stake.
5. Fixes are verified in both directions: reintroducing the defect makes the test
   fail.
6. The phase report records commands run and exact results, including failures
   found and corrected.
7. Work is committed separately with the phase named.
