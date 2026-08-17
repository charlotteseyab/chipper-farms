# Chipper Farms — Finance Module Smoke Test & Walkthrough

This is a guided, end-to-end exercise of the **entire finance module**, in the
order finance actually flows in a pig farm: set up the books → buy inputs → grow
pigs → sell pigs and feed → handle money → report → close the period → handle
capital. For **each menu** you get:

- **Why it exists** — the business need in a pig-farm finance system, and why it
  was built (often before this smoke test, as part of an earlier phase).
- **What to do** — concrete actions with **real sample data** you can type in.
- **What to verify** — the ledger/where-the-money-went check.

Work top to bottom. Each section's data feeds the next, so by the end you will
have driven a complete farm finance cycle and can confirm every subledger ties to
the general ledger.

> **Currency:** GHS. **Roles:** log in as an admin/accountant user so every menu
> is visible. **Before you start:** apply the completion delta (see README),
> run `php artisan migrate --seed` on a test database, and make sure the domain
> event outbox is being processed (queue worker running, or your sync path),
> because most finance postings are event-driven.

---

## How the pieces fit (read this first)

A pig farm's money story has five chapters, and the menus map onto them:

1. **The books themselves** — a chart of accounts and a general ledger, so every
   cedi has a home and every transaction balances. *(Chart of Accounts, Journals,
   Trial Balance.)*
2. **Money owed to us / by us** — customers buy pigs on credit; we buy feed
   inputs on credit. *(Customers, Invoices, Receipts, Suppliers, Vendor
   Invoices, Payments, Ageing, Statements.)*
3. **The cost of the animals and feed** — what it actually costs to raise a pig,
   so margins are real, not guessed. *(Inventory, Feed Issues, Group Cost Pools,
   Biological Assets, Cost of Pigs Sold, Feed Sales.)*
4. **Cash and the bank** — where the cash physically is, reconciled to the bank.
   *(Bank Accounts, Bank Statements, Bank Reconciliation, Treasury.)*
5. **Telling the truth periodically** — statements, closing the month/year,
   financing the farm. *(Reports, Period Close, Year-End, Loans, Investors.)*

The smoke test walks these in order.

---

# CHAPTER 1 — THE BOOKS (General Ledger core)

## 1.1 Chart of Accounts — `Finance → Chart of Accounts`

**Why it exists.** Every accounting system starts here. The chart of accounts
(COA) is the list of "buckets" money can sit in — cash, receivables, live-pig
inventory, feed revenue, loan principal, investor capital, and so on. A pig farm
needs specific buckets a generic accounting package wouldn't have: *Live Pig
Inventory (1230)*, *Biological Assets by stage (1310–1370)*, *Cost of Feed Sold
(5100)*, *Cost of Pigs Sold (5110)*. Without a correct COA, nothing else can post
to the right place — this is why the completion delta's first job was to fix
`1230` and `5000/5110`.

**What to do.**
1. Open the menu. Confirm the tree shows the asset/liability/equity/revenue/
   expense groups.
2. Spot-check these accounts exist (search by code):
   - `1230` Live Pig Inventory
   - `1220` Finished Feed Inventory
   - `1110` Trade Receivables — Pig Sales, `1120` Trade Receivables — Feed Sales
   - `4010` Revenue — Live Pigs, `4110` Revenue — Feed (Direct)
   - `5000` Cost of Sales (parent), `5100` Cost of Feed Sold, `5110` Cost of Pigs Sold
   - `2110/2510` loan principal, `3100` Investor Capital
3. Create a throwaway account to prove the UI: code `9991`, name "SMOKE TEST
   Misc", type Expense. Then toggle it inactive.

**Verify.** `9991` appears, then shows inactive. The seeded system accounts are
marked "system" and cannot be set to allow manual journals.

## 1.2 Journals — `Finance → Journals`

**Why it exists.** The journal is the raw double-entry record: every financial
event becomes a balanced set of debits and credits. Most journals here are
**posted automatically** by the system (a pig sale, a feed issue, a loan
repayment each create one). But accountants also need to post **manual**
journals — opening balances, corrections, accruals — so this menu exists for
both. The approval workflow (post / approve / reject / reverse) exists because in
a real business not everyone who can draft a journal should be able to post it.

**What to do.**
1. `Create new`. Post a manual opening journal, dated the first day of your test
   period:
   - Line 1: **Debit** `1010 Cash on Hand` `5,000.00`
   - Line 2: **Credit** `3100 Investor Capital` `5,000.00`
   - Description: "Opening cash funded by owner".
2. Save and approve (or store-and-approve).
3. Open the posted journal; note its journal number and that it's balanced.

**Verify.** The journal shows status Posted, debits = credits = 5,000.00. This is
the farm's first cedi on the books.

## 1.3 Trial Balance — `Finance → Trial Balance`

**Why it exists.** The trial balance is the one-screen proof that the whole
ledger balances: total debits = total credits across every account. It's the
accountant's smoke detector — if it's ever out of balance, something posted
wrong. You'll return here after every chapter below.

**What to do.** Open it. Note total debits and total credits.

**Verify.** They are equal. Right now you should see Cash 5,000 (Dr) and Investor
Capital 5,000 (Cr).

---

# CHAPTER 2 — MONEY OWED (Receivables & Payables)

This chapter is "who owes us and whom do we owe". On a pig farm, customers
(abattoirs, traders, households) often take pigs on credit, and we buy feed
ingredients, drugs, and supplies from vendors on terms. The books must track both
sides.

## 2.1 Customers — `Finance → Customers`

**Why it exists.** A customer master is needed so receivables can be tracked
*per buyer* — who still owes us, who's over their credit limit, who to send a
statement to. The credit-hold feature exists because farms get burned by buyers
who keep taking stock without paying; putting a customer on hold blocks new credit
sales to them.

**What to do.**
1. `Create`. Add a customer: name "Accra Meat Traders", type customer, credit
   limit `10,000.00`, payment terms 30 days.
2. Add an address and a contact.
3. Open the customer; note the "Customer Ledger" link (you'll check it after the
   invoice).

**Verify.** Customer appears in the list with a zero balance.

## 2.2 Invoices — `Finance → Invoices`

**Why it exists.** When we sell on credit we issue an invoice: it recognises
revenue and creates a receivable (an IOU) in the same stroke. This menu is the
formal AR document; it's distinct from the operational "pig sale" record because
an invoice can cover multiple lines, carry tax, and be the thing the customer
actually receives.

**What to do.**
1. `Create new`. Customer = Accra Meat Traders.
2. Add a line: description "5 finisher pigs (deposit invoice)", quantity 1, unit
   price `4,000.00`.
3. Save as draft, then **Post** it.

**Verify.**
- Invoice status → Posted.
- In **Journals**, a new posted batch exists: **Dr `1110` Trade Receivables —
  Pig Sales 4,000 / Cr `4010` Revenue 4,000** (account codes may vary by your
  revenue mapping).
- **Customer Ledger** for Accra Meat Traders now shows a 4,000 debit (they owe
  us).
- **Trial Balance** still balances.

## 2.3 Receipts — `Finance → Receipts`

**Why it exists.** When the customer pays, we receive cash and must (a) record
the cash coming in and (b) knock the payment off the right invoice(s). The
**allocation** step (receipt → invoice) is what keeps the receivable accurate;
without it you'd know cash came in but not what it settled.

**What to do.**
1. `Create`. Customer = Accra Meat Traders, amount `2,500.00`, method "bank".
2. Save, then **allocate** 2,500 against the invoice from 2.2.

**Verify.**
- Journal: **Dr Bank 2,500 / Cr `1110` Receivables 2,500**.
- Customer ledger balance falls from 4,000 → 1,500.
- The invoice shows part-paid (1,500 outstanding).

## 2.4 Suppliers — `Finance → Suppliers`

**Why it exists.** The mirror of customers, for the people we buy from — feed
ingredient sellers, drug suppliers, contractors. Needed so payables are tracked
per vendor and so supplier statements can be reconciled (did the vendor's
records match ours?).

**What to do.** `Create` a supplier: "GrainCo Ghana", type supplier, terms 30
days.

**Verify.** Supplier appears with zero balance.

## 2.5 Vendor Invoices — `Finance → Vendor Invoices`

**Why it exists.** When a supplier bills us, we record a vendor invoice: it
recognises the expense/asset and creates a payable. The submit → post workflow
exists so bills can be entered by one person and approved by another before they
hit the ledger.

**What to do.**
1. `Create`. Supplier = GrainCo Ghana, amount `3,000.00`, line "Maize — 1.5
   tonnes".
2. Submit, then **Post**.

**Verify.**
- Journal: **Dr inventory/expense 3,000 / Cr `2100`-series Accounts Payable
  3,000**.
- Supplier balance shows 3,000 owed.
- Trial Balance balances.

## 2.6 Payments — `Finance → Payments`

**Why it exists.** Paying suppliers. Like receipts but outbound: cash/bank goes
out and the payable is cleared via allocation. The reverse/auto-allocate options
exist for real-world messiness (overpayments, paying several invoices at once).

**What to do.**
1. `Create`. Supplier = GrainCo Ghana, amount `3,000.00`, method "bank".
2. Allocate against the vendor invoice from 2.5.

**Verify.**
- Journal: **Dr `2100` Accounts Payable 3,000 / Cr Bank 3,000**.
- Supplier balance → 0.

## 2.7 Ageing — `Finance → Ageing (AR / AP)`

**Why it exists.** Cash-flow survival. Ageing buckets receivables and payables by
how overdue they are (current / 30 / 60 / 90+). A farm manager uses AR ageing to
chase slow-paying buyers and AP ageing to plan which suppliers to pay first. It's
the single most-used credit-control report.

**What to do.** Open AR ageing, then AP ageing. Export AR to confirm the export
permission works.

**Verify.** Accra Meat Traders' remaining 1,500 shows in the current (or
appropriate) bucket. AP is now empty (GrainCo paid).

## 2.8 Customer / Supplier Statements — `Finance → … → Statements`

**Why it exists.** A statement is the formal "here's everything between us"
document sent to a customer, or reconciled against a supplier's version. Supplier
statement reconciliation (match their lines to ours, flag discrepancies) catches
billing errors and fraud.

**What to do.** Generate a customer statement for Accra Meat Traders; download it.

**Verify.** It shows the invoice, the part-payment, and the 1,500 balance.

---

# CHAPTER 3 — THE COST OF ANIMALS & FEED (Costing & Inventory)

This is the part generic accounting can't do and the reason this finance module
was custom-built. It answers: *what did it actually cost to raise this pig?* —
by capturing feed, inputs, and lifecycle costs against groups of pigs, then
releasing that cost when the pig is sold or dies.

## 3.1 Inventory — `Finance → Inventory`

**Why it exists.** Feed ingredients and finished feed are **valued stock**, not
just quantities. The inventory ledger tracks on-hand quantity *and* weighted-
average cost (WAC) per item, so when feed is consumed or sold the system knows its
true cost. This is the foundation Phase 4 built; everything in costing depends on
it.

**What to do.**
1. Open Inventory. Review the on-hand list (the maize from 2.5 should appear if
   your vendor-invoice flow capitalised it to stock; otherwise use the opening
   balances tool).
2. `Inventory → Account Mappings`: confirm `finished_feed` maps to inventory
   `1220` / COGS `5100`. **Why this menu exists:** it lets finance decide which GL
   accounts each inventory kind posts to, without code changes.
3. `Inventory → Opening Balances`: **why it exists** — when you go live, existing
   stock must be loaded at cost so the books start truthful. (Skip entering data
   if you already have stock.)

**Verify.** Inventory items show a quantity and a unit cost (WAC).

## 3.2 GR/IR Ageing — `Finance → Inventory → GR/IR Ageing`

**Why it exists.** "Goods Received / Invoice Received" is the gap between
receiving stock and getting the bill. This report shows stock received but not yet
invoiced (or vice versa) — a classic place for money to leak in procurement.

**What to do.** Open it; note any open GR/IR items.

**Verify.** It loads and lists matched/unmatched receipts.

## 3.3 Purchase Request Receiving — `Finance → Procurement → Receive`

**Why it exists.** When a purchase request's goods physically arrive, receiving
them turns the order into valued inventory and starts the GR/IR clock. This is the
bridge from operations (someone ordered feed) to finance (we now own valued
stock).

**What to do.** If you have an open purchase request, receive it. Otherwise note
the menu and move on.

**Verify.** Received quantity lands in inventory at cost.

## 3.4 Feed Issues — `Finance → Feed → Issues`

**Why it exists.** This is the heart of pig costing. When feed is taken from store
and given to a group of pigs, that feed's cost must move **out of feed inventory**
and **into the cost of raising that group**. Phase 5 built this: each issue
decrements finished-feed inventory at WAC and adds the cost to the group's cost
pool.

**What to do.**
1. `Issues`. Pick a feed type that has stock and a pig group; issue, say, `200`
   kg.
2. Note the cost shown.

**Verify.**
- Journal: **Dr `1230` Live Pig Inventory (the group's stage account) / Cr `1220`
  Finished Feed Inventory** at the WAC cost of 200 kg.
- The group's cost pool (next menu) increased by that amount.
- Finished-feed inventory on-hand fell by 200 kg.

## 3.5 Group Cost Pools — `Finance → Costing → Group Pools`

**Why it exists.** A "group" is a batch of pigs managed together. Its **cost pool**
accumulates everything spent raising it — feed issues, allocated costs, opening
value — so that when pigs are sold or die, the system can release a *fair share*
of accumulated cost as COGS. Without pooling, you'd never know per-group
profitability.

**What to do.** Open the group you fed in 3.4. Inspect its total carrying value,
pig count, and weight.

**Verify.** Carrying value rose by the feed cost from 3.4. The pool's per-pig and
per-kg cost are shown.

## 3.6 Opening Pig Inventory — `Finance → Costing → Opening Pig Inventory`

**Why it exists.** At go-live, pigs already on the farm have value (you spent
money raising them). This tool seeds each group's opening cost so the books don't
start at zero for animals that clearly aren't free.

**What to do.** Review the menu (only enter data if going live fresh).

**Verify.** Any opening pools show as journal entries to `1230`.

## 3.7 Mortality Write-offs — `Finance → Costing → Mortality Write-offs`

**Why it exists.** Pigs die. When they do, the cost invested in that animal must
be written off — removed from live-pig inventory and expensed as a mortality loss
(`5320`). This keeps inventory honest (you can't carry value for a dead pig) and
makes mortality cost visible to management.

**What to do.** If a pig death has been recorded operationally, find its
write-off here. (Deaths are usually recorded in pig management; finance reacts.)

**Verify.** Journal: **Dr `5320` Mortality Loss / Cr `1230` Live Pig Inventory**
at the dead pig's cost basis. Group pool count and value fell.

## 3.8 Biological Assets — `Finance → Biological Assets`

**Why it exists.** Under accounting standards (IAS 41), live animals are
"biological assets" and should be shown by life stage. Phase 7 splits live-pig
value across stages — piglet, weaner, grower, finisher (current assets) and
breeding stock / sows / boars (non-current). This menu shows the carrying value by
stage and a fair-value disclosure note, which is what an auditor or investor
expects to see.

**What to do.**
1. Open the stage summary. Note carrying value by stage and the reconciliation
   banner.
2. Open the **Fair-value note**.

**Verify.** The reconciliation banner is **green** — the stage subledger ties to
the GL stage accounts and to the group cost pools. (If amber, a stage transfer or
restage is pending; that's expected mid-cycle.)

## 3.9 Feed Sales — `Finance → Feed Sales` *(completed by this delta)*

**Why it exists.** A farm doesn't only feed its own pigs — it sells surplus feed.
Selling feed must (a) recognise revenue, (b) record the cost of that feed (COGS),
and (c) decrement feed inventory at WAC — exactly like a pig sale. This was the
last finance gap; the completion delta wired the subscribers that make it post.

**What to do.**
1. Ensure some finished feed is on hand.
2. `Record sale`: feed type, quantity `40` kg, unit price `12.00`, payment status
   "pending" (credit), buyer "Nearby Farm".
3. Save. Let the outbox process.

**Verify (this is the critical post-delta check).**
- The feed-sales list shows the sale with **revenue = posted** and **cogs =
  posted**.
- Journals:
  - Revenue: **Dr `1120` Trade Receivables — Feed Sales 480.00 / Cr `4110`
    Revenue — Feed 480.00**.
  - COGS: **Dr `5100` Cost of Feed Sold (40 × WAC) / Cr `1220` Finished Feed
    Inventory (same)**.
- Feed inventory on-hand fell by 40 kg.
- **If revenue/cogs stay "pending", the delta wasn't applied or the outbox isn't
  processing — stop and fix that first.**

---

# CHAPTER 4 — CASH & BANK (Treasury)

## 4.1 Bank Accounts — `Finance → Bank Accounts`

**Why it exists.** Each real bank/mobile-money account the farm holds is mirrored
here and linked to a GL cash account, so "bank balance per the books" is tracked
per account. Needed before any reconciliation can happen.

**What to do.** Confirm at least one bank account exists and is linked to a GL
account (e.g. `1030` Bank — Main). Create one if needed.

**Verify.** The account lists with its linked GL code.

## 4.2 Bank Statements — `Finance → Bank Statements`

**Why it exists.** To reconcile, you need the bank's own record. This menu imports/
enters statement lines (what the bank says happened) to compare against what the
books say.

**What to do.** Create a statement for your bank account; add a couple of lines
matching the receipt (2.3, +2,500) and payment (2.6, −3,000).

**Verify.** Statement lines saved.

## 4.3 Bank Reconciliation — `Finance → Bank Reconciliation`

**Why it exists.** The monthly truth check on cash: match each book transaction to
a bank line, explain differences (uncleared cheques, fees). Unreconciled cash is
where errors and fraud hide, so this is non-negotiable for production.

**What to do.** Start a reconciliation; match the statement lines to the book
entries.

**Verify.** Matched items tie out; the unreconciled difference is explained or
zero.

## 4.4 Treasury — `Finance → Treasury`

**Why it exists.** A single dashboard of "how much cash do we have, across all
accounts, and what's coming due" (including upcoming loan instalments). Phase 8
built it so management can see liquidity at a glance instead of adding up accounts
by hand.

**What to do.** Open Treasury.

**Verify.** Total cash reflects the journals you've posted; upcoming loan
obligations show once you create a loan (Chapter 5).

---

# CHAPTER 5 — TRUTH & FINANCING (Reports, Close, Loans, Investors)

## 5.1 Reports — `Finance → Reports`

**Why it exists.** The three core financial statements — **Income Statement**
(did we make a profit?), **Balance Sheet** (what do we own and owe?), and **Cash
Flow** (where did cash move?). Phase 6 built these to pull straight from the GL, so
they're always consistent with the journals.

**What to do.** Open each of Income Statement, Balance Sheet, Cash Flow for your
test period.

**Verify.**
- Income Statement: shows the pig-sale and feed-sale revenue, and Cost of Sales
  (pigs + feed) — i.e. real margin.
- Balance Sheet: **balances** (Assets = Liabilities + Equity); biological assets
  appear by stage; receivables/payables reflect Chapter 2.
- Cash Flow: ties to the movement in cash you posted.

## 5.2 Period Close — `Finance → Close`

**Why it exists.** At month-end the books must be "locked" so no one back-dates
entries into a reported period. The close workflow runs reconciliation checks
(every subledger ties to the GL) and then soft-locks the period. This is what makes
the numbers trustworthy.

**What to do.**
1. Open Close, select your test period, run the **checks**.
2. Review the reconciliation results (inventory, pig pool, receivables, payables,
   bank).
3. If all green, **Close** the period.

**Verify.** The period shows Closed; posting into it is now blocked (try a
back-dated manual journal — it should be refused unless you have override
permission).

## 5.3 Year-End — `Finance → Year-End`

**Why it exists.** At year-end, profit must be swept into retained earnings so the
new year starts fresh. This is a special close that zeroes the income statement
into equity. It must run **before** investor profit allocation (5.5).

**What to do.** (Only if you're exercising a full year.) Open Year-End, review,
and run the close for the test year.

**Verify.** Revenue/expense accounts zero out; the net is in `3200` Retained
Earnings; the balance sheet still balances.

## 5.4 Loans — `Finance → Loans`  &  reclassification

**Why it exists.** Farms borrow — bank loans, equipment finance, investor loans.
The loan engine (Phase 8) tracks principal, generates an amortisation schedule,
accrues interest, and splits repayments into principal/interest/fees. Needed
because loan interest is a real cost and mis-tracking principal vs interest
misstates both the P&L and the balance sheet.

**What to do.**
1. `New loan`: bank loan, lender "AgriBank", principal `12,000.00`, annual rate
   `0.24`, term `12` months, reducing balance, first payment next month. Save.
2. Open it — confirm the 12-line amortisation schedule (principal sums to
   12,000; final balance 0.00).
3. **Disburse** it.
4. Record a **repayment** against period 1 (principal `894.72`, interest
   `240.00`).
5. Open **Reclassification** — see the current vs long-term split.

**Verify.**
- Disburse journal: **Dr Bank 12,000 / Cr `2110` Loan Principal 12,000**.
- Repayment journal: **Dr `2110` Principal 894.72 / Dr interest / Cr Bank**.
- Treasury (4.4) now shows the loan's upcoming instalments.

## 5.5 Investors — `Finance → Investors`  &  cap table  &  allocate

**Why it exists.** The farm has equity investors who put in capital and share in
profit. Phase 9 tracks each investor's capital account, contributions,
distributions, and **profit allocation** (their share of the year's profit). The
cap table shows ownership at a glance. Needed for any farm with outside owners.

**What to do.**
1. `New investor`: "Ama", equity, ownership `0.60`. Add "Kofi", ownership `0.40`.
2. Open Ama → record a **contribution** of `50,000.00`.
3. (After the year-end close in 5.3) `Allocate` profit: pick the year, ownership
   %, pool e.g. `100,000.00`.
4. Record a **distribution** of `5,000.00` to Ama.
5. Open **Cap table**.

**Verify.**
- Contribution journal: **Dr Bank 50,000 / Cr `3110` Contributions 50,000**.
- Allocation journal: **Dr `3200` Retained Earnings / Cr `3100` Investor Capital**
  (Ama 60,000, Kofi 40,000).
- Distribution journal: **Dr `3120` Distributions 5,000 / Cr Bank 5,000**.
- Cap table reconciliation banner is **green** (subledger ties to the 3100 block).

---

# FINAL: WHOLE-MODULE RECONCILIATION

After the full walk, do the closing proof:

1. **Trial Balance** (`Finance → Trial Balance`) — total debits = total credits.
2. **Balance Sheet** balances (Assets = Liabilities + Equity).
3. Each subledger ties to its control account:
   - Customers ledger total = AR control (`1110/1120`).
   - Suppliers total = AP control.
   - Finished-feed inventory ledger = `1220` GL.
   - Live-pig pools = `1230` / stage accounts (`13xx`).
   - Loans outstanding = `2110/2510`.
   - Investor capital subledger = `3100` block.
4. **Period Close checks** all green.

If all four hold, the finance module is functioning end-to-end and is production-
ready.

---

## Appendix — menu-to-phase map (why each was built)

| Menu | Built in | Business need |
|------|----------|---------------|
| Chart of Accounts, Journals, Trial Balance | Phase 1 (GL core) | Every cedi has a home; books balance |
| Customers, Invoices, Receipts, Ageing, Statements | Phase 2 (AR) | Track who owes us; collect credit sales |
| Suppliers, Vendor Invoices, Payments, Supplier Statements | Phase 3 (AP) | Track whom we owe; pay vendors |
| Inventory, Account Mappings, Opening Balances, GR/IR | Phase 4 | Value feed/inputs at cost |
| Feed Issues, Group Cost Pools, Opening Pig Inventory, Mortality | Phase 5 | What it costs to raise each pig group |
| Reports, Period Close, Year-End | Phase 6 | Truthful statements; lock periods |
| Biological Assets | Phase 7 | IAS 41 — live animals by stage |
| Bank Accounts, Statements, Reconciliation, Loans, Treasury | Phase 8 | Cash truth; financing |
| Investors, Cap Table, Profit Allocation | Phase 9 | Equity capital & profit sharing |
| Feed Sales | Feed-sale path (this delta) | Sell surplus feed with proper revenue + COGS |
