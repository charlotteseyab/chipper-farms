# Chipper Farms Finance & Storefront Simplification Review

**Review date:** 7 August 2026  
**Purpose:** Define a production-standard, Ghana-localized finance and storefront experience for farm managers and finance officers while preserving the existing accounting, inventory, audit, idempotency and reconciliation backbone.

## 1. Executive decision

The current product should **not remove the finance engine**. It should **replace the default finance experience**.

The strongest parts of the system are underneath the UI: double-entry posting, invoices, receipts, vendor invoices, inventory valuation, biological-asset costing, loans, bank reconciliation, outbox/domain events, auditability and organization-aware records. Those controls should remain because they protect money and stock.

The problem is that the default Finance workspace exposes those internal accounting mechanics directly to farm users. A farm manager is currently confronted with concepts such as Trial Balance, Journal Entries, period locks, posting batches, outbox events, GR/IR ageing, payment runs, loan reclassification, biological-asset accounting and account mappings. That is an accountant/ERP workspace, not a farm-manager workspace.

The target product should therefore follow this principle:

> **Simple farm transactions on top; rigorous accounting underneath.**

A farm manager should record what happened — “bought feed”, “paid electricity”, “received cash for a pig”, “paid a loan instalment”, “sold this pig” — while the application determines the correct invoice, receipt, payable, inventory movement, journal, subledger and reconciliation effects automatically.

## 2. Release-source integrity finding

There is a critical baseline mismatch that must be resolved before implementation.

The attached `FarmSystemCodebasefinal.zip` inspected for this review contains `laravel/framework: ^11.31` and does not contain the later `SalesReturn` / `CreditNote` implementation or `refund.processed` handling described in the latest remediation closure evidence. The latest remediation evidence states that the working branch had already moved to Laravel 13.23.0 and added credit-note posting and authenticated Paystack refund webhook handling.

**Implementation must not start from this ZIP.** The design in this report should be implemented against the actual latest remediation HEAD (reported as `93ba1aeab09d3996e94b67a8ba441369232bc269`) or a fresh export of that exact repository state. Otherwise the redesign risks reintroducing already-remediated security, concurrency, refund and accounting defects.

The ZIP is still useful for understanding the existing module architecture and UX, which is what this review uses it for.

## 3. What exists today and what it means

### 3.1 Finance is functionally rich but operationally too complex

The current finance surface contains dedicated controllers/views for:

- Finance dashboard and trial balance
- Chart of accounts
- Manual journals
- Customers and customer ledgers
- Invoices
- Receipts
- Refunds
- AR ageing
- Suppliers and supplier statements
- Vendor invoices
- Payments and payment runs
- WHT certificates
- Bank accounts/statements/reconciliation
- Inventory valuation, mappings, opening balances and GR/IR ageing
- Feed issues and cost pools
- Biological assets
- Income statement, balance sheet and cash flow
- Period close and year-end close
- Loans and reclassification
- Treasury
- Investors/cap table/profit allocation
- Feed sales

This is much closer to a general ERP accounting suite than the daily finance experience a farm manager needs.

The existing Finance Dashboard confirms the UX mismatch: its first-level information is the open fiscal period, lock state, journal batch counts, draft journals, outbox events, trial balance debits/credits and recent journal batches. Those are important internal controls, but they should be hidden from a normal farm manager.

### 3.2 There is no first-class “Quick Expense” workflow

The backend has a detailed chart of accounts and vendor-invoice/payables system, but there is no obvious simple Expense document/workflow intended for a farm manager to record everyday costs such as fuel, electricity, veterinary services, casual labour or repairs.

The current chart already contains useful Ghana farm categories such as veterinary medicine/services, feed-mill energy, farm labour, ECG/NEDCo electricity, GWCL/borehole water, fuel, repairs, communications, marketing, delivery/transport, bank charges, mobile-money charges and loan interest. These should become friendly **expense categories**, not GL accounts that users must understand.

### 3.3 Purchases are capable but too workflow-heavy by default

The repository has Purchase Requests, purchase receiving, Vendor Invoices, payments, payment runs and GR/IR controls. That is excellent for farms that require approvals and segregation of duties.

For a smaller or owner-managed farm, forcing every purchase through PR → approval → receipt → vendor invoice → allocation → payment would create unnecessary work.

The same backend can support two modes:

- **Simple Purchase** — one form records supplier, items, quantity, cost, tax, receipt, payment status and stock location; backend automatically creates the correct inventory/AP/cash documents.
- **Procurement Workflow** — optional feature that activates the full PR/approval/receive/invoice/pay lifecycle.

### 3.4 Loans are strong but should be simplified

The current loan engine already handles schedules, disbursement, interest, principal repayments and GL posting. Keep it.

The current creation form is too accounting-oriented for a farm user. For example, it asks for an annual rate as `0.24 = 24%`, exposes amortisation terminology, and creates a “draft + schedule”. The user-facing flow should instead say:

- Lender
- Amount borrowed
- Interest rate (%)
- Date money was received
- Loan term
- Payment frequency
- First payment date
- Money received into

The backend can keep the existing amortisation and posting logic.

### 3.5 Cash management is fragmented

The system already has receipts, outgoing payments, payment methods, bank accounts, treasury and bank reconciliation. Mobile-money methods are seeded too.

The missing user experience is a single **Cash & Bank / Cashbook** page that answers:

- How much cash should we have?
- What is in each bank/MoMo account?
- What money came in today?
- What money went out today?
- Which cash sales are waiting for confirmation?
- Can I transfer money between cash, MoMo and bank?

The journal/receipt/payment objects should stay underneath this page.

### 3.6 Settings are not ready for per-farm feature configuration

`system_configs` currently has a globally unique `key` and no `organization_id`. That is not suitable for “Farm A enables bank reconciliation while Farm B does not”.

Feature enablement must be organization/farm scoped. Navigation hiding alone is insufficient; routes, actions, jobs and APIs must also check feature availability.

### 3.7 Storefront has a good core skeleton

The current storefront already has useful foundations:

- Pig/feed-backed listings
- Draft/published/unpublished/reserved/sold-out lifecycle
- Push-to-storefront service
- Public shop/listing pages
- Cart and checkout
- Pickup/delivery details including GhanaPost GPS
- COD and Paystack
- Orders/fulfilment/refunds
- Customer accounts/favourites/quotes
- Back-office listings/orders/reports
- Finance domain-event integration

The design should retain these foundations but simplify and harden the journey.

### 3.8 Storefront still needs product-grade simplification

From the ZIP reviewed:

- Checkout requires an authenticated user; guest checkout is not available.
- Tax is not represented in storefront order items/totals.
- Store settings exist in a typed accessor but are not presented as a complete polished store-configuration area.
- The old ZIP still contains earlier storefront/finance behaviour that later remediation reports say was corrected, reinforcing the need to implement against the true latest HEAD.

## 4. Market benchmark and design lessons

Current farm/swine systems support the proposed direction rather than the current ERP-heavy default.

### PigCHAMP Grow-Finish

PigCHAMP exposes feed budgets, ration/invoice management, feed-bin inventory and expenses by group, farm or individual pig, then uses that information for performance/profitability analysis. Its product positioning explicitly emphasizes simple navigation and data validation.

**Lesson for Chipper Farms:** link spending to farm/group/pig where useful, but do not make the farm manager understand the GL.

### Farmbrite

Farmbrite makes the core accounting action “record income or expense”, categorizes it, then derives P&L, cash flow, spending and receivable reports. Accounting can also be associated with livestock records/groups. Its market system links products, inventory, orders, payments, invoices/receipts, taxes and fulfilment.

**Lesson:** finance should begin with operational transactions; reports and accounting documents flow from them automatically.

### Agriness S2

Agriness packages pig production functions by farm need and includes economic events and performance reporting without forcing every farm into the same full feature set.

**Lesson:** modular feature profiles are appropriate.

### Cloudfarms

Cloudfarms emphasizes a user-friendly, customizable interface and reducing manual data-entry work so staff can focus on pig management.

**Lesson:** the best pig-management UX is task-oriented and low-click, not accounting-menu-oriented.

## 5. Target Finance module — default essentials

Rename the user-facing concept to **Farm Finance** or **Money & Finance**.

The default navigation should be:

1. **Overview**
2. **Sales & Money In**
3. **Expenses**
4. **Purchases**
5. **Cash & Bank**
6. **Loans**
7. **Customers & Suppliers**
8. **Reports**

Inventory remains a primary farm module, but Finance can link to its stock value and purchasing views.

### 5.1 Overview

The default dashboard should show business questions, not ledger mechanics:

- Cash & bank available
- Sales this month
- Expenses this month
- Net farm result this month
- Customers owing the farm
- Supplier bills due
- Outstanding loan balance / next repayment
- Inventory value
- Storefront cash payments awaiting confirmation
- Low-stock / finance-attention alerts

Quick actions:

- Record Expense
- Record Purchase
- Record Manual Sale
- Create Invoice
- Confirm Cash Received
- Record Loan Repayment
- Transfer Money

System/internal warnings such as failed outbox events should be translated into a single plain-language **Needs Attention** panel for authorized users.

### 5.2 Sales & Money In

One workspace should combine:

- Storefront sales
- Direct/manual pig sales
- Feed sales where enabled
- Invoices
- Customer payments/receipts
- Cash pending
- Refunds/credit notes

Farm users should not need to open “Invoices”, then “Receipts”, then “Customer Ledger” to understand one sale.

A sales record should display:

- Customer
- What was sold
- Amount
- Payment status
- Fulfilment status
- Invoice/receipt document
- Source (Storefront / Farm sale / Manual)
- Related pig/listing/order
- Simple activity timeline

### 5.3 Expenses

Add a first-class, farm-friendly expense workflow.

Minimum fields:

- Date
- Expense category
- Amount
- Payee/supplier (optional for cash purchases)
- Paid now / Pay later
- Payment method/account
- Tax profile where applicable
- Receipt/photo
- Optional allocation: Whole farm / pig group / individual pig / facility / feed operation
- Notes

Examples of default categories:

- Veterinary medicine & services
- Feed/farm consumables not tracked as inventory
- Labour
- Electricity
- Water
- Fuel & transport
- Repairs & maintenance
- Farm supplies/disinfectants/bedding
- Rent
- Communications
- Delivery/marketing
- Bank/MoMo/card charges
- Loan interest/fees
- Office/admin
- Other

Do not expose GL account codes. Each friendly category maps to the correct account internally.

For `Paid now`, post expense + cash/bank/MoMo immediately and idempotently.

For `Pay later`, create the appropriate supplier bill/payable behind the same simple workflow.

### 5.4 Purchases

Default workflow: **Record Purchase**.

Fields:

- Supplier
- Purchase date
- Stock/raw material/medicine/item
- Quantity & unit
- Unit cost
- Tax
- Destination/storage location
- Paid now / Pay later
- Payment method
- Supplier invoice/receipt/reference
- Attachment
- Notes

Backend automatically performs:

- Inventory receipt where stock-tracked
- Cost valuation
- Supplier bill/AP where applicable
- Cash/bank/MoMo payment if paid now
- Tax posting
- Audit trail
- Idempotency check

If **Procurement Approvals** is enabled, the same purchase domain uses the existing PR → approval → receive → bill → pay lifecycle instead.

### 5.5 Cash & Bank

Create a unified money-management page covering:

- Cash on Hand
- MTN Mobile Money
- AT Money
- Telecel Cash
- Bank accounts
- Paystack clearing/settlement

Actions:

- View cashbook
- Confirm cash received
- Record cash expense
- Transfer between accounts
- Record bank/MoMo reference
- Optional daily cash count

The page should use the existing GL/receipts/payments/bank data underneath rather than introduce an independent balance source.

For Paystack, consider a clearing-account approach so card/MoMo payments are recorded as received from the customer when verified and later moved to the bank when Paystack settles, with processing fees recorded separately.

### 5.6 Loans

Default loan list shows:

- Lender
- Original principal
- Outstanding principal
- Interest rate
- Next payment date
- Next amount due
- Overdue amount
- Status

Actions:

- Add Loan
- Record Funds Received
- Record Repayment
- View Schedule

Hide reclassification and manual loan journals unless Advanced Accounting is enabled.

### 5.7 Customers & Suppliers

Do not make farm users navigate separate accounting ledgers.

Customer page should show:

- Contact details
- Total sales
- Amount owed
- Overdue amount
- Recent invoices/payments
- Credit status where enabled

Supplier page should show:

- Purchases
- Amount owed
- Bills due
- Recent payments

Advanced statements/ageing remain available to finance/accounting users when enabled.

### 5.8 Essential reports

Default reports:

- Income vs Expenses / Profit & Loss
- Cash Flow
- Spending by Category
- Sales by product/pig/group
- Customer balances due
- Supplier balances due
- Loan position
- Inventory value
- Profitability by pig group/batch, where costs are allocated
- Storefront sales

Hide Trial Balance, raw journal reports and period-close diagnostics from ordinary users.

## 6. Advanced Finance features — settings controlled

Keep the existing backend features but make them optional UI modules.

Suggested organization-scoped toggles:

| Optional feature | Default | Purpose |
|---|---:|---|
| Advanced Accounting | Off | Chart of Accounts, Journals, Trial Balance, Balance Sheet, period close/year-end |
| Procurement Approvals | Off | PR → approval → receive → vendor invoice → payment |
| Advanced Accounts Payable | Off | Payment runs, supplier statements, detailed AP ageing |
| Bank Statement Reconciliation | Off | Import/match/reconcile bank statements |
| Withholding Tax | Off | WHT rules/certificates for farms that need it |
| Ghana E-VAT Integration | Off | GRA E-VAT integration for applicable registered businesses |
| Investor/Capital Management | Off | Investors, capital accounts, allocations, cap table |
| Advanced Biological Accounting | Off | Fair-value/cost-pool accounting screens; backend costing remains available |
| Advanced Treasury | Off | Detailed treasury position and controls |
| Customer Credit Control | Off | Credit limits/holds/advanced AR |
| Multi-currency | Off | Foreign-currency accounting |
| Budgets | Off | Farm budgets/variance reporting |

The toggle must affect routes/actions as well as navigation. Disabling a feature must never delete data.

If open records exist, the feature should enter **read-only/closing mode** instead of creating a dead end. For example, Bank Reconciliation cannot simply vanish while an active reconciliation is in progress.

## 7. Feature/settings architecture

Do not continue using only the existing global `system_configs` for per-farm feature control.

Recommended architecture:

### 7.1 Organization feature flags

Create an organization-scoped feature table with a unique key such as:

`organization_id + feature_key`

Examples:

- `finance.advanced_accounting`
- `finance.procurement_approvals`
- `finance.bank_reconciliation`
- `finance.wht`
- `finance.investors`
- `storefront.feed_sales`
- `storefront.manure_sales`
- `storefront.services`
- `storefront.quotes`
- `storefront.customer_accounts`

Every check is:

> Feature enabled **AND** user has permission.

Feature flags do not replace authorization.

### 7.2 Farm setup profiles

Offer presets during setup:

- **Essentials** — recommended default
- **Growing Farm** — essentials + procurement/bank reconciliation/customer credit
- **Full Accounting** — all accounting controls

The user can still adjust individual toggles.

## 8. Ghana localization — tax and payment corrections

### 8.1 Current tax seed data must be corrected

The ZIP's `TaxCodeSeeder` says it represents the Ghana tax stack “as of 2026” but still includes the 1% COVID-19 Health Recovery Levy and marks NHIL/GETFund as non-recoverable/compound.

That is outdated for 2026.

Under Ghana's 2026 VAT reforms, the standard taxable stack is:

- VAT 15%
- NHIL 2.5%
- GETFund 2.5%
- Effective combined amount 20%

COVID-19 Health Recovery Levy was abolished, VAT/NHIL/GETFund are computed on the same base, and NHIL/GETFund can be treated as input deductions by VAT-registered taxpayers.

### 8.2 Do not apply one global tax to every storefront product

The current GRA exemption information lists live swine among exempt live animals, and also lists qualifying feed/feed ingredients among agricultural inputs. Therefore a farm storefront can contain both exempt and taxable items.

Use product tax classes such as:

- Ghana — Live Animal Exempt
- Ghana — Agricultural Input / Feed Exempt
- Ghana — Standard 2026
- Zero Rated
- No Tax
- Custom

The farm administrator/accountant should confirm the appropriate classification during setup.

### 8.3 Generic tax engine

The user requirement for fixed/percentage taxes is compatible with the existing TaxCode model. Keep support for:

- Percent or fixed component
- Sales/purchase/both
- Inclusive/exclusive pricing
- Recoverable/non-recoverable
- Effective dates
- Multiple components
- Exempt/zero-rated

Add tax **profiles/groups** rather than making the user select multiple technical tax codes per transaction.

Every invoice/order must snapshot the actual tax components/rates used so future rate changes do not rewrite history.

### 8.4 VAT registration settings

Add:

- VAT registered? Yes/No
- TIN
- VAT registration number where applicable
- Tax effective date
- Default sales tax profile
- Default purchase tax profile
- Prices include tax? Yes/No
- E-VAT enabled? Yes/No

Do not automatically make a farm VAT registered based solely on turnover. Use current GRA guidance as setup information and require business confirmation.

### 8.5 Payment methods

The existing Ghana payment-method structure is useful. Update user-facing names:

- Cash
- MTN Mobile Money
- AT Money / AirtelTigo-compatible provider mapping
- Telecel Cash (rather than Vodafone Cash as the display name)
- Bank Transfer
- Card/Online via Paystack
- Credit Customer — optional
- Cheque — optional

Keep legacy provider codes where changing them would break historical records; update display names/mappings instead.

## 9. Target Storefront — essential default

The storefront should be as simple as the following customer journey:

**Farm pig → publish → customer chooses → checkout → reserve → pay/confirm cash → fulfil → finance → complete**

### 9.1 Push from pig record

On an eligible pig record, add a clear action:

**Sell / Put on Storefront**

Prepopulate:

- Ear tag / pig reference
- Breed
- Sex
- Age/stage
- Latest weight
- Existing photo
- Suggested price from farm settings

Staff supplies only:

- Public title
- Price basis: fixed / per kg / quote
- Price
- Short public description
- Pickup/delivery availability
- Primary photo
- Publish now? Yes/No

Do not mutate historical pig data when creating the listing.

### 9.2 Listing eligibility

A pig must not be publishable if it is:

- Dead
- Sold
- Archived/disposed
- Already on another active listing
- Already reserved
- In a state explicitly blocking sale
- Subject to a configured medication withdrawal restriction where sale is not permitted

The reason should be shown in plain language.

### 9.3 Storefront core product types

Default livestock storefront:

- Live pigs
- Piglets, if the source/quantity model supports safe reservation
- Breeding stock

Optional catalog types:

- Feed
- Manure
- Services
- Other farm products

Do not hard-code “this kind is always quote-only” if the inventory/source model can safely support fixed-price checkout. Make `sale_mode` a business configuration with safe constraints.

### 9.4 Guest checkout

The current ZIP requires an authenticated user for checkout. That is unnecessary friction for a public farm storefront.

Default to guest checkout:

- Name
- Phone
- Email (required for online payment; optional/validated as appropriate for cash)
- Pickup/delivery
- Delivery address
- GhanaPost GPS
- Notes

Create/update the customer/counterparty record behind the scenes. Offer account creation after checkout rather than requiring it first.

Customer accounts/favourites can remain an optional storefront feature.

### 9.5 Reservation before payment

For a unique pig, do not charge a customer first and then discover the pig is unavailable.

At checkout:

1. Lock/revalidate listing and pig.
2. Create a short-lived reservation/hold.
3. For Paystack, initiate payment while the hold is active.
4. Verified payment converts the hold to a committed reservation/order.
5. Failed/expired payment releases the hold automatically.

For quantity products, reserve the requested quantity using atomic stock checks.

### 9.6 Payment methods

Essential default:

- **Pay Online** — Paystack; cards and supported Ghana mobile money are offered through the provider.
- **Cash on Pickup / Delivery** — payment remains pending until staff confirms receipt.

Optional:

- Manual MoMo
- Bank transfer
- Customer credit

For manual MoMo/bank payments, transaction/reference number and staff verification are mandatory.

### 9.7 Separate statuses internally

Avoid one giant status list trying to represent order, payment and delivery simultaneously.

Maintain independent concepts:

**Order:** new / accepted / cancelled / complete / return-in-progress  
**Payment:** unpaid / pending / paid / refund-pending / refunded / failed / needs-attention  
**Fulfilment:** pending / ready-for-pickup / out-for-delivery / collected-delivered / returned

The user can still see one simple derived label such as “Paid — Ready for Pickup”.

### 9.8 Finance integration

A storefront sale should appear immediately in **Finance → Sales**, but the backend should post each accounting event at the correct point.

#### Online payment

- Reservation succeeds before payment.
- Paystack verifies payment via callback/webhook.
- Customer payment is recorded idempotently.
- Sale/order appears in Finance.
- Invoice/receipt is generated according to tax/payment status.
- Inventory/biological-asset and COGS effects happen once at the defined fulfilment/sale-control-transfer event.
- Paystack clearing/fees settle correctly into cash/bank.

#### Cash

- Order/sale appears in Finance with payment status **Cash Pending**.
- No receipt is posted merely because the customer selected cash.
- Staff chooses **Confirm Cash Received** and records:
  - Amount
  - Cash/MoMo/bank account
  - Received by
  - Date/time
  - Optional reference
- Only then does the payment become paid and the receipt/cash movement post.

This matches the requested behavior without falsifying cash balances.

### 9.9 Refund/return lifecycle

Preserve the newer remediated credit-note/refund architecture from the latest branch.

One return case must coordinate:

- Provider/manual refund
- Credit note
- Physical pig/feed return disposition
- Inventory/biological asset reversal
- COGS/revenue/AR reversal
- Reconciliation

No “refunded” status should be final while one required component remains incomplete.

## 10. Storefront settings

### Essential settings

- Storefront enabled
- Store name
- Logo/banner
- Support phone
- Support email
- WhatsApp contact
- Currency (GHS default)
- Pickup location/address
- Delivery enabled
- Default delivery fee or basic zones
- Accepted payment methods
- Tax profile / tax-inclusive pricing
- Guest checkout enabled
- Reservation timeout
- Order notification recipients
- Terms & conditions
- Returns/refund policy
- Default live-pig price / kg
- Default carcass price / kg where used

### Optional storefront features

- Feed store
- Manure sales
- Service listings
- Quotes/bulk orders
- Customer accounts
- Favourites
- Featured placements/promotions
- Discounts/coupons
- Advanced delivery zones
- Credit customers
- Wholesale/B2B pricing

## 11. No-dead-end design rules

Every non-terminal workflow status must have at least one explicit next action and one recovery action.

Examples:

- Payment pending → verify / expire / fail
- Reservation expired → release listing
- Cash pending → confirm received / cancel order
- Finance posting failed → retry / reconcile / operator action
- Refund provider succeeded but accounting failed → retry accounting only, never refund twice
- Purchase partially received → receive remainder / close short
- Loan overdue → record repayment / reschedule if authorized
- Optional feature disabled with open records → read-only close-out mode

All automated actions must be idempotent and keyed to a stable business identifier.

Critical transitions must use database transactions and row locks where stock, one-off pigs, cash, refunds or document sequences are involved.

## 12. What to keep, simplify, hide and add

| Current capability | Decision | Target behavior |
|---|---|---|
| Posting engine / double-entry GL | **Keep** | Invisible automatic accounting backbone |
| Domain events / outbox / idempotency | **Keep** | Invisible; failures become plain “Needs attention” alerts |
| Chart of Accounts | **Keep** | Advanced accounting only; categories map to it |
| Manual Journals | **Keep** | Accountant/admin optional feature |
| Trial Balance | **Keep** | Advanced accounting only |
| Period close/year-end | **Keep** | Advanced accounting only |
| Invoices | **Keep & simplify** | Generated from sales; easy manual invoice option |
| Receipts | **Keep & simplify** | “Record/Confirm payment”; auto allocate where safe |
| Vendor invoices | **Keep backend** | Surface as “Bills / Pay later” in simple workflow |
| Payment runs | **Optional** | Larger farms/finance teams only |
| Customers/Suppliers | **Keep & simplify** | Balance-centric contact pages |
| Bank reconciliation | **Optional** | Enable per farm |
| WHT certificates | **Optional** | Enable only where relevant |
| Loans | **Keep & simplify** | Plain loan register + repayments |
| Treasury | **Transform** | Cash & Bank page |
| Investors | **Optional** | Off by default |
| Inventory valuation/costing | **Keep** | Value and movements visible; account mappings hidden |
| Biological assets | **Keep backend** | Advanced report/UI optional |
| Finance dashboard | **Replace** | Farm money dashboard |
| Generic expense entry | **Add** | Core essential |
| Simple direct purchase | **Add** | Core essential; full procurement optional |
| Org-scoped feature flags | **Add** | Core settings architecture |
| Ghana tax profiles | **Add/fix** | Effective-dated, product-level tax classes |
| Storefront tax snapshot | **Add** | Order/line/invoice tax history |
| Guest checkout | **Add** | Default public purchase flow |
| Checkout reservation/hold | **Add/verify latest** | Prevent paid-but-unavailable pigs |
| Simple cashbook/transfers | **Add** | Core cash management |
| Pig/group cost allocation | **Expose simply** | Optional field, valuable profitability reporting |

## 13. Recommended implementation sequence

### Phase 0 — Correct source baseline

- Export/check out exact latest remediation HEAD.
- Re-run existing MariaDB/security/concurrency/refund tests.
- Do not implement on the Laravel 11 ZIP.

### Phase 1 — Feature architecture and UX shell

- Add organization-scoped feature flags and typed finance/storefront settings.
- Add Essentials/Growing Farm/Full Accounting presets.
- Rebuild navigation without deleting backend routes.
- Feature-gate optional routes/actions/jobs safely.

### Phase 2 — Farm Finance dashboard and simple transaction layer

- Replace finance dashboard.
- Add Quick Expense workflow.
- Add expense categories mapped to GL.
- Add unified Sales & Money In workspace.
- Add Cash & Bank/cashbook read model and transfer action.

### Phase 3 — Simplified purchases

- Build direct purchase workflow on top of existing inventory/vendor-payment services.
- Add Paid Now vs Pay Later.
- Add optional Procurement Approval mode using existing PR lifecycle.

### Phase 4 — Ghana tax localization

- Correct 2026 tax seed data.
- Add tax profiles/classes and effective dates.
- Add VAT registration settings.
- Add product-level tax mapping.
- Snapshot taxes on orders/invoices/credit notes.
- Keep E-VAT as optional integration.

### Phase 5 — Loans, customers and suppliers UX

- Simplify loan entry and repayment.
- Consolidate customer/supplier balances and documents.
- Remove accounting jargon from normal forms.

### Phase 6 — Storefront essentials redesign

- “Sell / Put on Storefront” from pig profile.
- Eligibility checks.
- Guest checkout.
- Checkout hold/reservation.
- Simplified payment and fulfilment state presentation.
- Store settings page.
- Optional product/catalog feature toggles.

### Phase 7 — Storefront ↔ Finance lifecycle

- Online payment and Paystack clearing.
- Cash-pending workflow.
- Invoice/receipt/tax generation.
- Inventory/biological COGS integration.
- Refund/credit-note/return reconciliation.

### Phase 8 — Advanced optional modules

- Advanced accounting
- Procurement approvals
- Bank reconciliation
- WHT
- Investors
- Advanced biological accounting
- Customer credit
- Quotes/bulk sales

### Phase 9 — UX, mobile and lifecycle hardening

- Responsive farm-manager screens.
- Plain-language statuses.
- Zero dead-end review.
- Empty states, errors, recovery actions.
- Accessibility.
- Audit trail.

### Phase 10 — Production certification

- MariaDB fresh/upgrade tests.
- Concurrent pig/order/payment/refund tests.
- Permission and organization-isolation matrix.
- Paystack sandbox.
- CI.
- Backup/restore.
- Deployment/rollback.

## 14. Acceptance standard

The redesigned product is ready only when a non-accountant farm manager can complete these scenarios without opening a journal, account mapping or trial balance:

1. Record an electricity expense paid by MTN MoMo.
2. Buy maize on cash and have inventory increase automatically.
3. Buy feed ingredients on credit and later pay the supplier.
4. Add a bank loan, see its balance and record a repayment.
5. Sell a pig directly for cash and confirm cash receipt.
6. Push an eligible pig to the storefront and publish it.
7. Buy that pig through Paystack without any second customer being able to buy it simultaneously.
8. Buy by cash/pickup and keep payment pending until staff confirms cash.
9. Complete delivery/pickup and see inventory/pig/finance update once.
10. Refund/return the order and have payment, credit note, stock/biological assets and GL reconcile.
11. See current cash, sales, expenses, debtors, bills, loans and stock value from one dashboard.
12. Enable Bank Reconciliation or Advanced Accounting for one farm without exposing it to farms that did not enable it.

## 15. Final recommendation

The product does not need a smaller accounting engine. It needs a smaller **accounting surface**.

Retain the robust posting, subledger, reconciliation, invoice, receipt, inventory, loan and audit infrastructure. Build a new farm-manager transaction layer above it, with simple forms and plain-language workflows. Use organization-level feature flags to selectively expose advanced ERP functionality.

For the storefront, retain traceability from the operational pig to the public listing and from the order back into finance, but add guest checkout, reservation-before-payment, product-level tax handling, simpler payment/fulfilment states and a complete store settings workspace.

This gives Chipper Farms the right market position: **a pig-farm management system with built-in farm finance**, rather than a general accounting ERP attached to pig records.
