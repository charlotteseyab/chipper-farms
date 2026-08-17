# Chipper Farms Finance + Storefront Final Architecture And Implementation Blueprint

## 1. Document Control

Review date: 2026-08-07
Branch: `staging`
Head: `9cb8863f5d3aa3be570ebf270830906f7c57da8a`
Mode: review and design only
Application source modified: No
Artifact produced: `CHIPPER_FARMS_FINANCE_STOREFRONT_FINAL_ARCHITECTURE_AND_IMPLEMENTATION_BLUEPRINT.md`

This document is an implementation blueprint, not a production go/no-go certificate. It reconciles the prior simplification review against the current codebase and defines the target finance and storefront product architecture before implementation begins.

## 2. Repository Baseline

The working branch is `staging`, and `origin/staging` points to the same commit. The current head includes the remediation branch commits through `9cb8863`.

Observed working tree before this document was created:

| Path | Status | Treatment |
| --- | --- | --- |
| `chipper farms.zip` | deleted | Pre-existing, not touched |
| `CHIPPER_FARMS_FINAL_GO_NO_GO_ASSESSMENT.md` | untracked | Pre-existing, not touched |
| `CHIPPER_FARMS_FINAL_GO_NO_GO_EVIDENCE.md` | untracked | Pre-existing, not touched |
| `CHIPPER_FARMS_FINAL_GO_NO_GO_ISSUE_REGISTER.csv` | untracked | Pre-existing, not touched |
| `CHIPPER_FARMS_FINANCE_STOREFRONT_SIMPLIFICATION_REVIEW.md` | untracked | Prior review input |
| `FINANCE_SMOKE_TEST(1).md` | untracked | Pre-existing, not touched |
| `FarmSystemCodebasefinal.zip` | untracked | Pre-existing, not touched |
| `pig management final codes.zip` | untracked | Pre-existing, not touched |

The route inventory shows 207 finance routes, 33 storefront-admin routes, 7 public shop routes, and authenticated account/cart/checkout routes. This confirms the codebase already has deep finance and storefront mechanics; the problem is mainly product surface, settings, defaults, and missing customer/farm-operator flows.

## 3. Executive Product Verdict

The finance and storefront architecture is strong enough to plan implementation. It is not ready for direct implementation without this scope reset because the current default finance UX exposes too much accounting machinery, while the storefront still depends on authenticated checkout and lacks a complete settings/tax/reservation product layer.

The recommendation is to preserve the ledger, outbox, posting, refund, sales-return, permission, and concurrency foundations, then build a simplified farm-facing experience on top. The system should not become a smaller accounting engine. It should become a clearer farm money product with optional advanced accounting behind feature flags.

Decision: READY FOR IMPLEMENTATION PLANNING.

## 4. Review Methodology

Reviewed inputs:

| Source | Purpose |
| --- | --- |
| Prior simplification review | Reconcile earlier recommendations |
| `routes/web.php` | Confirm reachable finance/storefront surface |
| `app/Helpers/NavigationHelper.php` | Assess default navigation |
| `app/Finance`, `app/Services/Finance`, `app/Storefront` | Assess domain architecture |
| `resources/views/finance`, `resources/views/storefront` | Assess product UX |
| `database/migrations`, `database/seeders` | Assess data model and settings gaps |
| `tests/Finance`, `tests/Feature/Storefront`, `tests/Concurrency` | Confirm safety coverage direction |
| Official external sources | Validate Ghana tax, Paystack behavior, and market patterns |

External sources used:

| Source | Findings used |
| --- | --- |
| https://gra.gov.gh/domestic-tax/tax-types/vat/ | 2026 VAT structure, COVID levy abolished, VAT/NHIL/GETFund same-base computation, goods VAT threshold |
| https://paystack.com/docs/payments/payment-channels/ | Ghana mobile money support and webhook dependence |
| https://paystack.com/docs/payments/refunds/ | Refund statuses and `refund.processed` webhook requirement |
| https://paystack.com/docs/payments/webhooks/ | Signature validation requirements |
| https://help.farmbrite.com/help/recording-transactions | Farm accounting UX favors quick transactions and category-based reporting |
| https://agriness.com/en/s2-en/ | Swine systems combine zootechnical and economic events |
| https://en.cloudfarms.com/services/wean-to-finish-management/ | Pig farm tools emphasize feed, growth, selling, and mobile-friendly farm input |

## 5. Prior Simplification Review Reconciliation

| Prior recommendation | Decision | Reason |
| --- | --- | --- |
| Keep the finance engine | Confirmed | PostingEngine, outbox, permissions, and idempotency are valuable and should remain |
| Simplify the finance default experience | Confirmed | Current dashboard and nav are GL/accounting-first |
| Add Quick Expense | Confirmed | No first-class quick expense route/service/view exists |
| Simplify purchases | Confirmed | Vendor invoice and procurement flows are powerful but too heavy for daily farm spend |
| Preserve loans but rewrite UX | Confirmed | Loan engine is useful; UI should speak "loan received" and "loan payment" |
| Add cash/bank/MoMo as a practical workspace | Confirmed | Bank accounts, statements, receipts, payments, and treasury exist but are fragmented |
| Add per-organisation feature flags and presets | Confirmed | Current settings are global key/value and permissions, not organisation product presets |
| Keep storefront push-to-listing | Confirmed | Current PushToStorefrontService is a good boundary |
| Add guest checkout | Confirmed | Current cart/checkout routes are authenticated under `/account` |
| Reserve stock before payment | Modified | Current code reserves on staff confirmation. Target should add temporary holds at checkout while preserving explicit confirmation |
| Storefront tax profiles | Confirmed | Listings and order lines do not snapshot tax basis |
| Replace refund shortcuts with sales returns/credit notes | Already implemented | Current refunds raise sales returns and credit notes for posted lines |
| Treat Paystack refund as async | Already implemented | Refund service and webhook support pending/processed states |
| Remove COVID levy from Ghana tax defaults | Confirmed | Current seeder still includes `COVID_LEVY`; GRA says it is abolished from 2026 |
| Implement directly from prior review | Rejected | This prompt forbids implementation and requires this blueprint first |

Prior review summary:

| Bucket | Count |
| --- | ---: |
| Confirmed | 11 |
| Modified | 1 |
| Rejected | 1 |
| Already Implemented | 2 |

## 6. Current Finance Architecture

Finance has a strong internal architecture:

| Layer | Current state |
| --- | --- |
| Ledger | `PostingEngine` validates periods, locks, accounts, idempotency, numbering, journals, and journal postings atomically |
| Outbox | `DispatchDomainEvent`, `OutboxClaimer`, `ProcessDomainEvents`, subscriber registry, retry, stale-claim recovery |
| AR | Customers, invoices, receipts, allocations, statements, ageing, refunds |
| AP | Suppliers, vendor invoices, payments, payment runs, supplier statements, AP ageing |
| Treasury | Bank accounts, bank statements, bank reconciliation, treasury position |
| Loans | Draft, disbursement, amortisation, accruals, repayments, reclassification |
| Inventory/costing | Inventory valuation, GR/IR, feed issues, group pools, biological assets, mortality writeoffs |
| Reporting/close | Trial balance, income statement, balance sheet, cash flow, period close, year end |
| Returns | Sales returns, credit notes, COGS/revenue reversal subscribers |

The architecture should be preserved. The issue is that most of this is presented directly to farm operators instead of being wrapped into simpler workflows.

## 7. Current Finance UX Assessment

The finance dashboard currently says "Live snapshot of the general ledger and posting activity" and emphasizes journal batches, trial balance, outbox events, and draft journals. That is good for accountants and support engineers but not for a farm owner asking, "How much cash did we receive? What did we spend? Who owes us? What bills are due?"

Current navigation exposes many advanced concepts by default: Chart of Accounts, Journal Entries, Trial Balance, AP Ageing, Supplier Statements, WHT Certificates, Bank Statements, Bank Reconciliation, Close, Year End, Treasury, Investors, Inventory & Costing, Biological Assets, and more. A normal farm user needs a smaller default set and an "Advanced Accounting" area for the rest.

## 8. Current Storefront Architecture

Storefront has a credible commerce foundation:

| Area | Current state |
| --- | --- |
| Public shop | `/shop`, browse, detail, quote request |
| Back office | `storefront-admin` dashboard, listings, push wizard, placements, quotes, orders, reports, refunds |
| Listings | Source-linked listings for pigs/feed/products, status machine, duplicate guards |
| Cart | Authenticated DB-backed cart with price snapshots |
| Checkout | Authenticated checkout, COD or Paystack, pickup or delivery |
| Payments | Paystack initialize/verify, signed webhook validation, payment rows |
| Orders | Explicit staff confirmation before inventory reservation |
| Finance integration | Confirmed order emits domain event and routes through pig/feed sale services |
| Refunds | Provider submission, pending/processed state, sales returns, credit notes, operational availability restore |

The storefront should remain source-of-truth for commerce orders and should keep routing finance consequences through the sale spine rather than posting directly to the GL.

## 9. Current Storefront UX Assessment

The customer-facing shop works as a catalog and quote/order entry point, but direct purchase requires sign-in because cart and checkout live under authenticated `/account` routes. That raises friction for first-time buyers and makes the public storefront less like a normal ecommerce flow.

The public nav exposes every category regardless of organisation settings. The checkout does not show tax breakdowns, delivery configuration, pickup windows, or payment channel availability driven by a storefront settings screen. The admin push form is functional but presents technical options such as pricing basis and source types without a guided farm-friendly path.

## 10. Existing Strengths to Preserve

Preserve these components:

| Component | Preserve because |
| --- | --- |
| `PostingEngine` | Central accounting safety boundary |
| `JournalPosting` idempotency | Prevents duplicate automatic journals |
| `NumberingService` | Avoids count-derived references under concurrency |
| Domain outbox | Keeps source transactions coupled to async finance processing |
| Storefront order confirmation rule | Prevents payment success from becoming automatic inventory acceptance |
| Listing state machine | Keeps listing lifecycle auditable |
| Sales return service | Provides return anchor for feed and pig sales |
| Credit note service | Gives customer-facing revenue reversal documents |
| Paystack signature validation | Required for public webhook integrity |
| COD settlement failure gate | Prevents completed/paid orders without finance settlement |
| Permission catalogue | Useful base for role-specific simplified navigation |
| Organisation scoping | Critical for multi-farm data boundaries |

## 11. Current Complexity Problems

| Problem | Impact |
| --- | --- |
| Finance nav is too broad | Daily operators see auditor/accountant screens |
| Dashboard is GL-health-first | Owners do not immediately see farm cash, income, expense, debts, and bills |
| No Quick Expense flow | Routine cash/MoMo spend requires too much AP/accounting structure |
| Purchases split between operations, procurement, receiving, vendor invoices, payments | Useful but hard to learn |
| Cash, bank, MoMo, receipts, payments, and reconciliation are scattered | Users cannot easily answer "what money moved today?" |
| Settings are global key/value | No organisation presets or farm-level module configuration |
| Storefront direct checkout is authenticated-only | Public buyer friction remains high |
| Reservation starts at staff confirmation | Paid-but-unavailable risk remains for Paystack orders |
| Ghana tax seed data is outdated for 2026 | Current tax defaults can overstate VAT/levy handling |
| Storefront categories are not feature-controlled | Optional categories may appear even when not used |

## 12. Market Benchmark Findings

Farm accounting products typically make "record a transaction" the entry point, then feed dashboards and reports behind the scenes. Farmbrite describes transactions as being added from the accounting dashboard, in bulk, from associated farm pages, or as optional byproducts of farm actions. Chipper Farms should mirror that pattern: every operational action can create correct accounting, but the first screen should be quick farm records, not journal batches.

Swine-focused products such as Agriness combine production and economic events rather than forcing finance into a separate mental world. Cloudfarms emphasizes on-farm input, feed consumption, buying, selling, mortality, and mobile access. Chipper Farms already has these data streams; the finance UX should translate them into owner decisions.

Market conclusion: keep enterprise-grade accounting underneath, but default to farm-language workflows.

## 13. Ghana Localisation Findings

As of the GRA 2026 VAT reforms, the current tax default in `TaxCodeSeeder` needs review before implementation. GRA states that the COVID-19 Health Recovery Levy was abolished, VAT remains 15%, NHIL is 2.5%, GETFund is 2.5%, and the levies are calculated on the same base rather than the old cascading calculation. GRA also states the goods VAT registration threshold increased to GHS 750,000 and the VAT flat-rate scheme was removed.

Implication:

| Area | Required design response |
| --- | --- |
| Tax defaults | Remove COVID levy from default 2026 profiles |
| Tax calculation | Support same-base VAT/NHIL/GETFund profile |
| Historical data | Keep effective dates so older transactions remain explainable |
| Farm exemptions | Allow non-VAT registered and exempt product modes |
| Storefront | Snapshot tax profile, rate components, and tax-inclusive/exclusive display per order line |
| Compliance | Require accountant/GRA confirmation before production rollout |

## 14. Target User Profiles

| Profile | Needs | Default product surface |
| --- | --- | --- |
| Owner/Admin | Cash position, profit, bills, receivables, tax, storefront status | Simplified finance overview plus settings |
| Farm Manager | Purchases, feed costs, sales, storefront listing push, COD completion | Quick workflows, no ledger jargon |
| Data Entry | Record expenses, purchases, receipts, weights, feed use | Narrow forms and upload/attachment flows |
| Accountant | GL, invoices, AP/AR, tax, close, reports, reversals | Advanced Accounting |
| Treasurer | Cash, bank, MoMo, payments, loans, settlement issues | Cash & Bank, Loans, payment approval |
| Salesperson | Listings, quotes, orders, customer communication | Storefront workspace |
| Customer | Browse, quote, cart, checkout, order status, refund status | Public storefront and account/guest order lookup |

## 15. Essential Finance Scope

Essential finance must include:

| Capability | Target behavior |
| --- | --- |
| Finance overview | Farm money dashboard with cash, sales, expenses, receivables, payables, loans, alerts |
| Quick expense | One-screen expense with category, supplier optional, payment method, tax, attachment |
| Simple purchase | Buy feed/supplies/equipment with optional supplier bill and inventory linkage |
| Money in | Record pig/feed/storefront receipts without manual journal exposure |
| Cash/Bank/MoMo | Unified workspace for cash in, cash out, transfers, pending verification |
| Loans | Simple loan received, due schedule, repayment, overdue alerts |
| Customers/Suppliers | Lightweight contacts plus optional credit/payment terms |
| Invoices/Receipts | Customer documents and payment allocation, simplified by default |
| Credit notes | Return/refund document workflow preserved |
| Tax profiles | Ghana-ready profiles with effective dates |
| Reports | Owner P&L, cash flow, receivables, payables, tax summary |
| Settings presets | Essentials, Growing Farm, Full Accounting |

## 16. Optional Advanced Finance Scope

Optional finance features should remain available only through feature flags:

| Optional feature | Default |
| --- | --- |
| Manual journals | Off except accountant/admin |
| Chart of accounts management | Advanced Accounting |
| Trial balance | Advanced Accounting |
| Bank statement import | Growing Farm or Full Accounting |
| Bank reconciliation | Growing Farm or Full Accounting |
| Vendor invoice approval | Growing Farm or Full Accounting |
| Payment runs | Full Accounting |
| WHT certificates | Ghana Tax Advanced |
| Period close/year end | Full Accounting |
| Investors/capital accounts | Investor module |
| Biological asset reporting | Full Accounting |
| GR/IR ageing | Procurement Advanced |
| Supplier statements | AP Advanced |
| Multi-currency | Advanced |
| Budgets | Optional later phase |

## 17. Essential Storefront Scope

Essential storefront must include:

| Capability | Target behavior |
| --- | --- |
| Public catalog | Live pigs, piglets, breeding stock, feed if enabled |
| Guest cart/checkout | Buyer can order without creating account first |
| Quote requests | Bulk and non-fixed-price inquiries |
| Push from pig profile | Guided flow from operational record to listing |
| Listing eligibility | Health, sale status, duplicate listing, withdrawal/lock checks |
| Listing lifecycle | Draft, published, held, reserved, sold out, unpublished, archived |
| Reservation hold | Short-lived hold at checkout/payment initiation |
| Staff confirmation | Still required before final acceptance and sale posting |
| Paystack | Online payment with callback and signed webhook |
| Cash/COD | Order accepted only after staff confirmation, cash settled at fulfilment |
| Finance integration | Confirmed orders route through existing pig/feed sale spine |
| Refunds/returns | Preserve provider refund, sales return, credit note, and stock restore logic |

## 18. Optional Storefront Scope

| Optional feature | Default |
| --- | --- |
| Manure sales | Off unless farm sells manure |
| Services | Off unless farm offers services |
| Breeding service bookings | Future |
| Delivery zones/rates | Optional in Growing Store |
| Promotions/coupons | Future |
| Customer loyalty | Future |
| Online partial deposits | Future |
| Inventory bundles | Future |
| Multi-location pickup | Optional |
| Storefront analytics beyond sales | Optional |

## 19. Essential vs Optional Master Matrix

| Domain | Essential | Optional |
| --- | --- | --- |
| Finance input | Quick expense, simple purchase, money in, loan payment | Manual journal, payment runs, supplier statement import |
| Finance control | Idempotent posting, permissions, period locks, tax profiles | Full period close, year end, investor allocations |
| Finance reports | Owner P&L, cash flow, receivables, payables, tax summary | Trial balance, balance sheet, biological asset reports |
| Storefront catalog | Pigs, piglets, breeding stock, feed | Manure, services, bundles |
| Storefront checkout | Guest checkout, Paystack, COD, pickup | Delivery zones, deposits, coupons |
| Storefront operations | Staff confirmation, order timeline, refund workflow | Advanced analytics |
| Settings | Organisation flags and presets | Per-role custom dashboards |

## 20. Recommended Finance Navigation

Default finance navigation:

1. Overview
2. Record Expense
3. Purchases
4. Sales & Money In
5. Cash, Bank & MoMo
6. Loans
7. Customers & Suppliers
8. Reports
9. Settings
10. Advanced Accounting

Advanced Accounting should contain the current GL-heavy items: Chart of Accounts, Journal Entries, Trial Balance, Bank Statements, Bank Reconciliation, AP Ageing, AR Ageing, Supplier Statements, WHT Certificates, Close, Year End, Investors, Inventory & Costing, Biological Assets, GR/IR.

## 21. Recommended Storefront Navigation

Public:

1. Shop
2. Live Pigs
3. Piglets
4. Breeding Stock
5. Feed, if enabled
6. Request Quote
7. Cart
8. Order Lookup / Account

Back office:

1. Storefront Overview
2. Listings
3. Push to Storefront
4. Orders
5. Quotes
6. Payments & Refunds
7. Reports
8. Settings

Category links must be generated from enabled catalog modes, not hard-coded links.

## 22. Finance Dashboard Specification

The default dashboard should show:

| Widget | Source |
| --- | --- |
| Cash available | Treasury/cash accounts |
| Cash received today/week/month | Receipts, paid sales, storefront payments |
| Expenses today/week/month | Payments, quick expenses |
| Sales this month | Pig sales, feed sales, storefront sales |
| Customers owing | AR ageing |
| Bills to pay | AP/vendor invoices |
| Loan payments due | Loan schedule lines |
| Storefront pending confirmation | Storefront orders |
| Finance needs attention | Outbox failures, settlement discrepancies, deferred storefront lines |
| Tax estimate | Tax profile applied to sales/purchases |

Advanced dashboard cards for outbox, journal batches, and trial balance can remain visible to accountant/admin in Advanced Accounting.

## 23. Quick Expense Specification

Target workflow:

1. Date
2. Paid from: Cash, Bank, MTN MoMo, Telecel Cash, AirtelTigo Money, other
3. Amount
4. Expense category
5. Supplier optional
6. Tax profile optional
7. Reference optional but required for MoMo/bank methods
8. Attach receipt photo/PDF
9. Save

Accounting behavior:

| Case | Posting |
| --- | --- |
| Cash expense | Dr expense/tax input as applicable, Cr cash |
| MoMo/bank expense | Dr expense/tax input as applicable, Cr mapped account |
| Unknown category | Route to suspense expense and show needs-attention |
| Supplier bill needed | Offer "save as bill instead" |

## 24. Purchase and Procurement Specification

Provide two tracks:

| Track | User-facing name | Engine mapping |
| --- | --- | --- |
| Simple purchase | Buy feed/supplies | Quick vendor invoice/payment wrapper |
| Procurement | Purchase request/receiving/vendor invoice | Existing PR, GR/IR, vendor invoice, AP workflow |

Simple purchase must support paid-now and pay-later. Paid-now creates the purchase and payment in one workflow. Pay-later creates a supplier bill. Feed purchases should optionally update raw material inventory or link to existing receiving when enabled.

## 25. Cash/Bank/MoMo Specification

Create one workspace named Cash, Bank & MoMo:

| Tab | Purpose |
| --- | --- |
| Money In | Customer receipts, storefront COD, MoMo confirmations |
| Money Out | Expenses, supplier payments, loan repayments |
| Transfers | Cash to bank, bank to MoMo, MoMo cashout |
| Accounts | Cash tills, banks, MoMo wallets |
| Verification | Unmatched references, pending Paystack/COD issues |
| Reconciliation | Advanced statement import and matching |

MoMo providers should remain separate accounts because settlements and references differ.

## 26. Loan Management Specification

Default loan UX:

| User action | Current engine mapping |
| --- | --- |
| Add loan | `LoanService::createDraft` |
| Receive loan money | `LoanService::disburse` |
| Record repayment | `LoanService::repay` |
| View upcoming payments | loan schedule lines |
| Accrue interest | scheduled command or accountant action |
| Reclassify current/non-current | advanced accounting |

Hide amortisation and liability terminology from owner view unless the user opens details.

## 27. Customer and Supplier Specification

Customers and suppliers should become one "People & Businesses" experience with role-filtered tabs:

| Tab | Purpose |
| --- | --- |
| Customers | Buyers, AR, credit holds |
| Suppliers | Feed vendors, services, AP |
| Balances | Who owes us / who we owe |
| Statements | Customer/supplier statements |
| Settings | Terms, categories, tax/WHT settings |

Keep existing `Counterparty` model approach. Add guided creation and minimum required fields.

## 28. Invoice and Receipt Specification

Invoices and receipts should be simplified:

| Workflow | Target UX |
| --- | --- |
| Create invoice | Customer, items, tax, due date |
| Record payment | One button from invoice/customer/order |
| Allocate payment | Automatic by default, manual advanced |
| Reverse/cancel | Guided reason and effects preview |
| Receipt | Customer-facing printable receipt |

Manual allocation screens stay available to accountant roles.

## 29. Credit-Note Specification

Preserve the current credit note architecture:

| Requirement | Reason |
| --- | --- |
| Credit note created from sales return | Gives numbered customer document |
| Idempotency key per sales return | Prevents duplicate revenue reversal |
| Link to invoice/order/return | Auditability |
| Paid sale credits cash, credit sale credits AR | Prevents wrong balances |
| Partial return stays partial | Do not mark invoice reversed unless full |
| Customer-facing document | Needed for refunds/returns |

## 30. Tax Profile Specification

Tax profiles should be effective-dated and organisation-scoped:

| Profile | Components | Use |
| --- | --- | --- |
| Ghana VAT 2026 standard | VAT 15%, NHIL 2.5%, GETFund 2.5%, same base | Standard taxable sales/purchases |
| Ghana zero-rated | 0% VAT | Zero-rated goods |
| Ghana exempt | 0%, non-recoverable | Exempt supplies |
| No tax | 0% | Non-registered or non-taxable |
| Historical Ghana VAT pre-2026 | VAT/NHIL/GETFund/COVID as configured | Historical reporting only |
| WHT goods/services/rent | Effective-dated withholding | Supplier payments |

Storefront orders must snapshot tax components and display totals consistently. The current `COVID_LEVY` seed should not be part of the default 2026 profile.

## 31. Finance Reporting Specification

Default reports:

| Report | Audience |
| --- | --- |
| Farm Profit & Loss | Owner/admin |
| Cash Flow | Owner/admin/treasurer |
| Sales by Product | Owner/manager |
| Feed Cost Summary | Owner/manager |
| Expenses by Category | Owner/admin |
| Customers Owing | Owner/accountant |
| Bills to Pay | Owner/accountant/treasurer |
| Loan Schedule | Owner/treasurer |
| Tax Summary | Accountant/admin |

Advanced reports remain: trial balance, balance sheet, bank reconciliation, biological assets, GR/IR, period close pack.

## 32. Organisation Feature-Flag Architecture

Add an organisation-level feature flag layer before changing navigation:

| Concept | Required behavior |
| --- | --- |
| Flag storage | Organisation scoped, effective immediately |
| Presets | Apply multiple flags with one admin action |
| Authorization | Flags hide/disable routes but permissions still enforce access |
| UI navigation | Generated from flags and permissions |
| Data integrity | Existing data remains accessible through Advanced/Archived views |
| Audit | Changes record actor/date/old/new |

Do not use feature flags as the only security control. Permissions and policy checks must remain authoritative.

## 33. Module Presets

| Preset | Intended farm | Enabled by default |
| --- | --- | --- |
| Essentials | Small farm, cash basis | Overview, Quick Expense, Simple Purchase, Sales & Money In, Cash/MoMo, basic reports, storefront pigs/feed |
| Growing Farm | More staff and supplier credit | Essentials plus customers/suppliers, invoices, AP, bank accounts, loans, basic reconciliation |
| Full Accounting | Accountant-led farm | Growing plus GL, journals, COA, close, WHT, statements, biological assets, advanced reporting |
| Storefront Seller | Farm selling online | Public shop, guest checkout, listings, orders, quotes, Paystack, COD |
| Procurement Advanced | Formal feed purchasing | PR approvals, receiving, GR/IR, vendor invoice approvals |

## 34. Storefront Settings Specification

Required settings:

| Setting | Notes |
| --- | --- |
| Store enabled | Public shop on/off |
| Store name | Already supported in `StorefrontSettings` |
| Support email/phone | Already supported |
| Currency | Already supported, default GHS |
| Pickup address | Already supported |
| Delivery enabled | Already supported but needs UI and route behavior |
| Delivery zones/fees | New |
| Public categories enabled | New |
| Payment methods enabled | New, Paystack/COD/manual MoMo/bank |
| Paystack mode/status | Config health display only, never expose secret |
| Tax display mode | Inclusive/exclusive |
| Default tax profile | New |
| Order hold duration | New |
| Guest checkout enabled | New |
| Refund policy text | New |

Existing `StorefrontSettings` is a good typed accessor but needs an admin UI and organisation scoping.

## 35. Pig-to-Storefront Lifecycle

Target lifecycle:

1. Pig exists in operations.
2. Eligibility check confirms alive, unsold, not already actively listed, not under withdrawal/health hold, has latest weight if price-by-kg.
3. Staff clicks Push to Storefront.
4. System drafts listing with source snapshot.
5. Staff reviews title, weight, price, media, category, fulfilment.
6. Listing is published.
7. Buyer adds to cart and checkout creates a temporary hold.
8. Payment and staff confirmation happen.
9. Confirmation emits storefront order event.
10. Subscriber records pig sale through sale spine.
11. Fulfilment completes and listing becomes sold out.
12. Refund/return uses sales return and credit note, not journal shortcuts.

## 36. Listing Lifecycle

Target listing states:

| State | Meaning | Exits |
| --- | --- | --- |
| draft | Internal preparation | published, archived |
| published | Public and orderable/quotable | held, unpublished, archived, sold_out |
| held | Temporary checkout/payment hold | published, reserved, sold_out |
| reserved | Staff-confirmed order has claim | sold_out, published on cancellation |
| sold_out | No longer sellable | archived, unpublished if restocked product |
| unpublished | Staff hidden but recoverable | published, archived |
| archived | Closed | none except admin restore if explicitly built |

Current code has draft/published/unpublished/archived/sold_out/reserved. Add `held` or a separate `storefront_inventory_holds` table rather than overloading `reserved`.

## 37. Reservation and Concurrency Design

Current confirmation locking is good and must remain. The missing layer is pre-confirmation holds:

| Moment | Target behavior |
| --- | --- |
| Add to cart | No inventory decrement, revalidate availability |
| Checkout start | Create hold rows under transaction and lock listings |
| Paystack initialize | Hold expires after configured minutes |
| Paystack success | Order moves payment_confirmed, hold remains until staff decision |
| Staff confirm | Convert hold to reserved/sold_out using current locked confirmation logic |
| Payment failure/timeout | Release hold |
| Staff cancel | Release hold and reverse any accepted payment path as needed |

This prevents a customer paying for stock that another customer bought in the same window.

## 38. Checkout Experience

Target checkout:

| Step | Requirement |
| --- | --- |
| Buyer identity | Guest checkout with email/phone; optional account creation after order |
| Cart | Works before login through session/cart token |
| Fulfilment | Pickup/delivery options driven by settings and listing eligibility |
| Payment | Paystack and COD shown only when enabled |
| Tax | Show subtotal, delivery, tax, total |
| Availability | Hold inventory before Paystack redirect |
| Confirmation copy | Make clear that farm confirms live-animal availability |
| Recovery | Expired payment, failed payment, unavailable item, and pending payment states all lead to action |

## 39. Paystack Payment Lifecycle

Paystack lifecycle:

1. Checkout validates cart and creates order plus hold.
2. Paystack transaction is initialized with GHS minor amount and order metadata.
3. Customer authorizes card/mobile money/payment channel.
4. Browser callback verifies server-side.
5. Webhook verifies `x-paystack-signature` and also verifies transaction server-side.
6. Payment success moves order to `payment_confirmed`, not `confirmed`.
7. Staff confirms order to reserve/fulfill and emit finance event.
8. Refund request submits to Paystack refund endpoint.
9. Refund stays pending until `refund.processed` or equivalent settled state.

Paystack supports Ghana mobile money, but mobile money completion depends on webhook status. The design must keep webhooks mandatory, not optional.

## 40. Cash Payment Lifecycle

COD/cash lifecycle:

1. Customer places order with COD.
2. Order starts `pending_confirmation`.
3. Staff confirms availability and inventory is reserved.
4. On pickup/delivery, staff records cash collected.
5. Completion settles underlying sale lines through the sale spine.
6. If settlement cannot happen, completion is blocked and a visible order event is recorded.
7. Cash refund uses internal refund/sales return flow and must require treasurer/admin permission.

## 41. Storefront-to-Finance Integration

Preserve the current integration pattern:

| Source | Event | Subscriber | Result |
| --- | --- | --- | --- |
| Storefront order confirmation | `StorefrontOrderConfirmed` | `StorefrontOrderConfirmedSubscriber` | Routes lines into `RecordPigSaleService` or `RecordFeedSaleService` |
| Pig sale | sale events | Pig sale subscribers | Revenue, AR/cash, COGS, inventory, biological effects |
| Feed sale | sale events | Feed sale subscribers | Revenue, AR/cash, COGS, inventory |
| Sales return | `SalesReturnRecorded` | Return subscribers | COGS reversal, inventory restore, credit note revenue reversal |

Do not add direct GL posting inside storefront checkout, payment callback, or order controllers.

## 42. Refund Lifecycle

Refund lifecycle:

| Step | Target behavior |
| --- | --- |
| Eligibility | Only confirmed/fulfilled/refund-approved states |
| Provider submission | Required for verified Paystack payments |
| Idempotency | Stable per order/refund |
| Internal reversal | Sales return per posted line |
| Customer status | Pending until provider settles |
| Credit note | Generated and posted through finance |
| Product availability | Product quantities can be republished |
| Pig availability | Returned pig should require staff decision before relisting |
| Failure | Visible retry/attention state, no silent "refunded" label |

Current code already implements most of this and should be wrapped with clearer admin UX.

## 43. Sales-Return Lifecycle

Sales return lifecycle:

1. User requests return/refund with reason and quantity.
2. Service validates sale exists, organisation matches, quantity positive, and cumulative returns do not exceed sale quantity.
3. SalesReturn row is created.
4. Domain event is dispatched inside transaction.
5. COGS subscribers reverse inventory/cost effects.
6. Revenue subscriber creates credit note and posts revenue/AR/cash adjustment.
7. Return status moves to posted or failed with actionable error.

This flow should be used for storefront refunds, direct feed returns, and pig sale returns.

## 44. Feed Return

Feed returns are divisible:

| Requirement | Design |
| --- | --- |
| Partial quantity | Allowed up to remaining sold quantity |
| Restock flag | True if goods return to inventory, false if spoiled/kept |
| Revenue reversal | Proportional to returned quantity |
| COGS reversal | Proportional weighted-average cost |
| Storefront listing | Product quantity may be restored and republished if sellable |
| Tax | Credit note should reverse tax according to original tax snapshot |

Current return support exists; future implementation should add tax snapshot reversal.

## 45. Pig Return

Pig returns are indivisible:

| Requirement | Design |
| --- | --- |
| Quantity | Always one |
| Partial return | Not allowed |
| Restock decision | Staff must decide health/market state before relisting |
| Biological asset | Restore through sales return subscriber |
| Listing | Do not automatically republish live animal |
| Credit note | Full sale reversal unless future partial financial adjustment is intentionally designed |

Current code already treats pig quantity as one in `RecordSalesReturnService`.

## 46. No-Dead-End State Analysis

Every non-final state needs a visible recovery path:

| State | Required action |
| --- | --- |
| Draft listing | Publish, edit, archive |
| Held listing | Expire/release, confirm, cancel |
| Reserved listing | Complete sale, cancel/release |
| Payment pending | Verify, retry, expire, cancel |
| Payment confirmed | Confirm order, cancel/refund |
| Finance posting skipped | Review deferred lines and manually post/resolve |
| Finance posting failed | Replay event after fixing cause |
| Refund pending | Poll/fetch provider state or await webhook |
| Refund failed | Retry with same idempotency key or manual resolution |
| COD settlement blocked | Retry after outbox sale posting catches up |
| Tax profile missing | Route to settings, block taxable posting if required |

The admin order page should become the recovery console for storefront states.

## 47. Idempotency Matrix

| Operation | Idempotency key/control |
| --- | --- |
| Domain event dispatch | Source idempotency key |
| Outbox claim | Guarded pending-to-processing update |
| Auto journal posting | `journal_postings.idempotency_key` |
| Number allocation | `NumberingService` |
| Storefront order reference | `StorefrontReferenceAllocator` |
| Listing reference | `StorefrontReferenceAllocator` |
| Quote/order conversion | Shared order reference allocation |
| Paystack payment reference | Provider reference unique index |
| Paystack verification | Payment status no-op if already verified |
| Order confirmation | Lock order and listing rows, no-op when already confirmed |
| Refund submission | Stored refund idempotency key |
| Sales return | Return quantity guard plus event idempotency |
| Credit note | Sales-return credit-note idempotency |

## 48. Transaction/Locking Matrix

| Flow | Lock/transaction requirement |
| --- | --- |
| Posting journal | Single transaction in `PostingEngine` |
| Outbox dispatch | Inside source transaction |
| Outbox claim | Atomic guarded update |
| Checkout hold | Transaction plus listing/hold lock |
| Staff confirm | Current order and listing `lockForUpdate` pattern |
| Product quantity decrement | Lock listing, aggregate by listing |
| Pig reservation | Lock listing and enforce published state |
| COD completion | Preflight blockers before completion transaction |
| Refund | Provider call outside DB transaction; internal reversal inside transaction |
| Sales return | Create return and event in one transaction |
| Credit note | Lock sales return before note creation |
| Bank transfer | Lock source/destination cash accounts or post atomically through ledger |

## 49. Permission Matrix

| Capability | Owner/Admin | Manager | Data Entry | Accountant | Treasurer | Salesperson | Customer |
| --- | --- | --- | --- | --- | --- | --- | --- |
| View overview | Yes | Yes | Limited | Yes | Yes | No | No |
| Quick expense | Yes | Yes | Yes | Yes | Yes | No | No |
| Approve/post advanced finance | Yes | No | No | Yes | Limited | No | No |
| Manual journal | Admin only | No | No | Yes | No | No | No |
| Cash/Bank/MoMo | Yes | Limited | Record only | View | Yes | No | No |
| Loans | Yes | View | No | View | Manage | No | No |
| Push listing | Yes | Yes | No | No | No | Yes | No |
| Manage orders | Yes | Limited | No | View | Payment view | Yes | Own only |
| Refund orders | Admin | No | No | View | Yes | No | Own request only |
| Storefront settings | Admin | No | No | No | No | No | No |

Implementation must reconcile current seeded permissions with these product roles, especially separate `confirm_storefront_orders` from broad `manage_storefront_orders`.

## 50. Navigation by Role

| Role | Primary nav |
| --- | --- |
| Owner/Admin | Overview, Pig Operations, Finance, Storefront, Reports, Settings |
| Farm Manager | Pig Operations, Feed, Quick Expense, Purchases, Storefront Orders/Listings |
| Data Entry | Record operational data, Quick Expense, limited purchases |
| Accountant | Finance Overview, Advanced Accounting, Reports, Tax, Close |
| Treasurer | Cash/Bank/MoMo, Payments, Loans, Settlement issues |
| Salesperson | Storefront Overview, Listings, Quotes, Orders |
| Customer | Shop, Cart, Orders, Profile |

The current single sidebar should remain but become data-driven from permissions plus organisation feature flags.

## 51. Database/Data-Model Gap Analysis

| Gap | Required data |
| --- | --- |
| Organisation feature flags | `organization_feature_flags` or organisation-scoped settings |
| Presets | preset code, enabled modules, audit |
| Quick expense | Expense wrapper or vendor invoice/payment shortcut metadata |
| Tax profiles | Tax group/profile, components, effective dates, recoverability, storefront default |
| Storefront tax snapshot | Order item tax profile, tax components, inclusive/exclusive amounts |
| Guest cart | Session/customer token and later user linkage |
| Guest order | Customer email/phone, lookup token, optional user association |
| Inventory holds | Listing hold rows with expiry, quantity, cart/order/payment reference |
| Storefront settings UI | Organisation-scoped values and validation |
| Delivery settings | Zones, fees, minimums, allowed categories |
| Payment method settings | Enabled customer-facing methods and mapped finance accounts |
| Deferred storefront lines | Better structured resolution table instead of text-only error |
| Settlement reconciliation | Update stale comments and expose UI queue |

## 52. Components to Keep / Wrap / Refactor / Replace

| Component | Decision |
| --- | --- |
| `PostingEngine` | Keep |
| Domain outbox/subscribers | Keep |
| `ReceiptService`, `PaymentService` | Keep and wrap |
| Vendor invoice services | Keep and wrap |
| Loan services | Keep and wrap |
| Credit note/sales return services | Keep |
| Finance dashboard view | Refactor into owner dashboard plus advanced ledger health |
| Finance navigation | Refactor behind presets/flags |
| Storefront listing/order services | Keep |
| Storefront checkout | Refactor for guest checkout and holds |
| Storefront settings accessor | Keep and extend |
| Tax seed defaults | Replace/update for 2026 Ghana profile |
| Hard-coded storefront category nav | Replace with enabled category nav |
| Text-only deferred posting errors | Wrap with resolution UX |

## 53. Migration and Backward Compatibility Strategy

Implementation must be additive:

1. Add new tables/columns without dropping old ones.
2. Backfill feature flags with Full Accounting enabled for existing organisations to avoid hiding current functionality.
3. Add tax profiles with effective dates; do not rewrite historical transactions.
4. Keep old finance routes reachable through Advanced Accounting until new wrappers are verified.
5. Add guest cart/order fields alongside current user cart.
6. Add holds without changing current confirmation code first.
7. Move nav after flags and tests are in place.
8. Provide rollback path by disabling new simplified surfaces.

## 54. Error and Recovery UX

Error messages should be practical:

| Failure | User message | Admin detail |
| --- | --- | --- |
| Paystack initialize failed | Online payment unavailable, try again or choose COD | Correlation id and provider context in logs |
| Payment pending too long | Payment still pending | Verify/fetch provider state |
| Stock hold expired | Item is no longer held | Recheck cart |
| Order cannot confirm | Item no longer available | Listing/order conflict details |
| COD cannot complete | Finance posting not ready | Blocker line list |
| Refund pending | Refund submitted, awaiting provider | Provider status and reference |
| Tax profile missing | Tax setup needed before posting | Link to settings |
| Outbox failed | Finance action needs attention | Event id and subscriber error |

No state with money attached should auto-correct silently.

## 55. Mobile/Responsive UX Requirements

Mobile requirements:

| Area | Requirement |
| --- | --- |
| Quick expense | Fully usable on phone, camera attachment first-class |
| Storefront public | Fast browse/detail/cart/checkout on mobile |
| Admin order page | Confirmation, COD collection, notes, refund action usable on phone |
| Tables | Collapse to cards or horizontal scroll with fixed actions |
| Forms | Short sections, sticky primary action where useful |
| Navigation | Role-filtered, short labels, no giant accounting list by default |
| Validation | Inline, no hidden errors below fold |
| Payment | Paystack return and pending states readable on mobile |

## 56. Gap Register

| ID | Category | Gap | Priority |
| --- | --- | --- | --- |
| FIN-001 | Finance | No first-class Quick Expense | P1 |
| FIN-002 | Finance | No simplified paid-now/pay-later purchase wrapper | P1 |
| FIN-003 | Finance | Dashboard is ledger-health-first | P1 |
| FIN-004 | Finance | Cash/Bank/MoMo workspace fragmented | P1 |
| FIN-005 | Finance | Loan UX is accounting-heavy | P2 |
| FIN-006 | Finance | Customer/supplier UX too master-data-heavy | P2 |
| FIN-007 | Finance | Invoice/receipt UX needs guided owner flow | P2 |
| FIN-008 | Finance | Ghana tax defaults outdated for 2026 | P1 |
| FIN-009 | Finance | Owner reports not packaged as farm reports | P2 |
| FIN-010 | Finance | Farm expense categories not default first-class | P1 |
| FIN-011 | Finance | Internal cash/bank/MoMo transfers need workflow | P2 |
| FIN-012 | Finance | Pending cash/payment verification queue missing | P2 |
| FIN-013 | Finance | Money-in workflow split across receipts/sales | P2 |
| FIN-014 | Finance | Finance needs-attention queue not a UI surface | P1 |
| STORE-001 | Storefront | Direct checkout requires authenticated account | P1 |
| STORE-002 | Storefront | No temporary checkout/payment holds | P1 |
| STORE-003 | Storefront | No tax snapshot on listings/order lines | P1 |
| STORE-004 | Storefront | Storefront settings lack admin UI and org scope | P1 |
| STORE-005 | Storefront | Order/payment/fulfilment statuses need clearer UX | P2 |
| STORE-006 | Storefront | Catalog categories not feature-controlled | P2 |
| STORE-007 | Storefront | Checkout lacks tax/delivery/payment settings integration | P1 |
| STORE-008 | Storefront | Paystack settlement queue needs admin surface | P2 |
| STORE-009 | Storefront | Manual MoMo/bank payment verification not productized | P2 |
| STORE-010 | Storefront | Listing eligibility lacks health/withdrawal checks | P1 |
| STORE-011 | Storefront | Listing snapshot quality/media requirements weak | P3 |
| STORE-012 | Storefront | Customer refund status UX incomplete | P2 |
| CFG-001 | Settings | Organisation feature flags missing | P1 |
| CFG-002 | Settings | Module presets missing | P1 |
| CFG-003 | Settings | Feature flags not connected to permission/nav | P1 |
| CFG-004 | Settings | Tax settings not surfaced | P1 |
| CFG-005 | Settings | Storefront settings persistence is not org scoped | P1 |
| CFG-006 | Settings | Payment account mapping not productized | P2 |
| UX-001 | UX | Finance nav overloaded | P1 |
| UX-002 | UX | Accounting jargon exposed by default | P1 |
| UX-003 | UX | Dashboard does not answer owner questions first | P1 |
| UX-004 | UX | Large forms need guided modes | P2 |
| UX-005 | UX | Mobile admin/customer flows need tightening | P2 |
| UX-006 | UX | Recovery actions are scattered | P2 |
| UX-007 | UX | Role-based nav is role/permission only, not feature-aware | P1 |
| UX-008 | UX | Status names need plain-language mapping | P2 |
| SEC-001 | Security/Integrity | Feature flags could be mistaken for auth if not designed carefully | P1 |
| SEC-002 | Security/Integrity | Quick purchase/expense can double-post if it bypasses posting engine | P1 |
| SEC-003 | Security/Integrity | Refund provider/accounting split needs recovery controls | P1 |
| SEC-004 | Security/Integrity | Cash confirmation needs tighter permissions/audit | P1 |
| SEC-005 | Security/Integrity | Tax/Paystack assumptions require external certification before production | P1 |

Gap counts:

| Category | Count |
| --- | ---: |
| Finance | 14 |
| Storefront | 12 |
| Settings | 6 |
| UX | 8 |
| Security/Integrity | 5 |

## 57. Risk Register

| Risk | Severity | Mitigation |
| --- | --- | --- |
| Hiding advanced finance breaks existing accountant workflow | High | Add feature flags defaulting to current full visibility |
| Quick expense bypasses accounting controls | High | Implement only as wrapper over existing posting services |
| Ghana tax defaults remain outdated | High | Accountant/GRA validation and effective-dated tax profile migration |
| Guest checkout weakens customer ownership | Medium | Use signed lookup token and optional account claim |
| Holds introduce deadlocks | High | Lock listing rows in deterministic order and add expiry job |
| Paystack refund status misread | High | Keep pending/processed distinction and webhook/poll reconciliation |
| Storefront deferred lines hide revenue | High | Build admin resolution queue |
| Cash/COD misuse | High | Separate collect, verify, complete permissions |
| Settings global values leak across organisations | High | Organisation-scoped settings/flags |
| Navigation change confuses trained users | Medium | Keep Advanced Accounting and phased rollout |

## 58. Implementation Roadmap

| Phase | Work |
| --- | --- |
| 0 | Confirm business decisions: tax registration, categories, payments, delivery, presets |
| 1 | Add organisation feature flags, presets, feature-aware navigation |
| 2 | Add Ghana 2026 tax profiles and storefront tax snapshot model |
| 3 | Build simplified Finance Overview and needs-attention queue |
| 4 | Build Quick Expense and simple purchase wrappers |
| 5 | Build Cash/Bank/MoMo workspace and transfer flow |
| 6 | Refactor loans/customers/suppliers/invoices into simplified default UX |
| 7 | Add storefront settings UI and category/payment settings |
| 8 | Add guest cart/checkout and order lookup |
| 9 | Add checkout holds and expiry/release processing |
| 10 | Polish storefront admin order/refund/payment recovery UX |
| 11 | Mobile/responsive pass |
| 12 | Full regression, concurrency, MariaDB, Paystack sandbox, tax certification |

## 59. Phase-by-Phase Acceptance Criteria

| Phase | Acceptance |
| --- | --- |
| 1 | Admin can apply Essentials/Growing/Full presets; nav changes without permission leaks |
| 2 | New transactions can use Ghana 2026 profile; COVID levy absent from default 2026 profile |
| 3 | Owner dashboard answers cash/sales/expense/owing/due/questions without GL jargon |
| 4 | Quick expense posts balanced journals and stores receipt attachments |
| 5 | Cash/Bank/MoMo workspace shows in/out/transfers/pending verification |
| 6 | Loans and contacts are usable from simplified forms |
| 7 | Storefront settings control public category/payment/delivery behavior |
| 8 | Guest buyer can cart, checkout, pay/COD, and later claim account |
| 9 | Concurrent checkout/confirmation cannot oversell |
| 10 | Refund and payment recovery states are visible and actionable |
| 11 | Core flows pass mobile viewport review |
| 12 | All tests and external certification checks pass |

## 60. Complete Testing Strategy

Required tests:

| Area | Test type |
| --- | --- |
| Feature flags | Unit/feature tests for nav, route access, preset application |
| Quick expense | Posting, idempotency, tax, attachment auth |
| Simple purchase | Paid-now/pay-later, supplier optional, inventory link |
| Tax | Ghana 2026 same-base calculation, effective dates, historical profile |
| Dashboard | Aggregation correctness and role visibility |
| Cash transfers | Balanced posting and account mapping |
| Guest checkout | Session cart, order creation, account claim, lookup token |
| Holds | Expiry, release, confirmation conversion |
| Concurrency | Simultaneous checkout/confirmation, duplicate Paystack webhook |
| Refunds | Pending, processed, failed, needs-attention, idempotent retry |
| Storefront finance | Deferred line queue and successful pig/feed posting |
| Permissions | Roles cannot access hidden or unauthorized actions |
| Mobile | Browser/screenshot checks for key flows |
| MariaDB | Migration and concurrency suite against MariaDB |

Do not rely on SQLite alone for final certification because lock/concurrency behavior is money-critical.

## 61. External Certification Requirements

Before production implementation/release:

| Certification | Required evidence |
| --- | --- |
| Ghana tax | Accountant/GRA-aligned confirmation of VAT, NHIL, GETFund, WHT, exemptions, effective dates |
| Paystack | Sandbox success for card/MoMo, callback, webhook, refund pending/processed/failed |
| Webhook security | Signature validation and optional IP allowlist reviewed |
| MariaDB | Full migration/test run against target MariaDB version |
| Queue/scheduler | Domain event processor and storefront availability sync supervised |
| Storage | Receipt/finance attachment disk and signed download behavior verified |
| Operational UAT | Farm manager can record expense, purchase feed, list pig, confirm order, collect COD |
| Accounting UAT | Accountant can reconcile postings, returns, credit notes, tax reports |

## 62. Open Business Decisions

| Decision | Needed from business |
| --- | --- |
| VAT status | Is each organisation VAT registered? |
| Tax display | Show prices inclusive or exclusive of VAT/levies? |
| Product taxability | Which pig/feed/manure/service products are taxable, exempt, or zero-rated? |
| Payment methods | Paystack only, COD, manual MoMo/bank, or all? |
| Delivery | Pickup only or delivery zones/fees? |
| Hold duration | Recommended 10 to 20 minutes for Paystack checkout |
| Guest checkout | Required buyer fields and account-claim policy |
| Pig eligibility | Health/withdrawal/weight/age rules for listing |
| Refund policy | Who approves, what reasons, when relist returned pigs |
| Preset default | Which preset should existing farms start with after rollout? |

## 63. Final Recommended Scope

Recommended implementation scope:

| Include now | Defer |
| --- | --- |
| Feature flags and presets | Promotions/coupons |
| Simplified finance nav/dashboard | Multi-currency |
| Quick expense | Budgets |
| Simple purchase | Loyalty |
| Cash/Bank/MoMo workspace | Breeding service bookings |
| Ghana 2026 tax profiles | Complex delivery optimization |
| Storefront settings UI | Full marketplace/multi-vendor |
| Guest checkout | Deposits/partial payments |
| Checkout holds | Advanced storefront analytics |
| Order/refund recovery UX | Custom report builder |
| Mobile polish | Native mobile app |

## 64. Final Sign-Off / Ready for Implementation Decision

The system is ready for implementation planning because the underlying remediation work gives it a credible accounting and storefront backbone: idempotent posting, outbox processing, guarded order confirmation, Paystack verification, refund settlement handling, sales returns, credit notes, and permission-gated admin surfaces.

Implementation should proceed only as a phased product simplification and settings architecture project. Do not replace the finance engine. Do not bypass the sale spine. Do not treat feature flags as authorization. Do not launch Ghana tax changes without external accountant/GRA confirmation.

READY FOR IMPLEMENTATION PLANNING
