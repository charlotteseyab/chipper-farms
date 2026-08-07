# Phase 5 — Cash, Bank & MoMo

## Document control

| Field | Value |
| --- | --- |
| Phase | 5 — Cash, Bank & MoMo |
| Date | 2026-08-07 |
| Branch | `staging` |
| HEAD at phase start | `861d380` |
| Blueprint anchor | §25 (workspace), §39 (money accounts), §22 (cash available card) |
| Prompt anchor | §38–§44 |
| Phase status | COMPLETE for balances and transfers; workspace UI and cash confirmation deferred (§7) |

## 1. Scope

Money accounts with balances derived from the ledger, and internal transfers
between them. This phase also produces the "Cash available" figure that Phase 3
deferred.

## 2. Balances are derived, never stored

Prompt §38: *"Do not build a separate balance source."*

`MoneyAccountService` holds no balance column and computes nothing
incrementally. It sums posted debits and credits per account straight from
`journal_lines` — the same rows the trial balance reads — and subtracts.

A cached or incrementally-maintained cash balance is a number that can disagree
with the ledger, and when it does, both figures look equally plausible to whoever
is looking. Deriving it every time makes divergence impossible rather than
merely unlikely.

Mobile money providers stay as separate accounts. Blueprint §25: settlement
timing and reference formats differ per provider, so a merged balance cannot be
reconciled against any one statement.

## 3. A transfer is not an expense

Prompt §41 lists five properties. Each is enforced structurally rather than
assumed:

| Property | How |
| --- | --- |
| Reduce one account | Cr source |
| Increase another | Dr destination |
| **Never count as income** | Both legs must be configured *money* accounts |
| **Never count as expense** | — same constraint |
| Balance | One amount, two legs, built together |
| Idempotent | Unique key per organisation, checked before posting |
| Auditable | Numbered, attributed, journal-linked |

The income/expense guarantee is the one that matters. Moving GHS 2,000 from the
MTN wallet to the bank is the most commonly mis-recorded farm transaction:
entered as a payment out of MoMo it becomes an expense, and the month's profit is
understated by the whole amount while the bank balance rises with no matching
income.

Restricting both legs to the money-account list makes that mistake **not
expressible** through this workflow. `test_a_transfer_touches_no_revenue_or_expense_account`
asserts it directly by loading every account the journal touched and failing if
any is revenue or expense — rather than assuming the right accounts were chosen.

`test_a_transfer_leaves_total_money_unchanged` is the complementary check: moving
money between the farm's own accounts cannot change how much it has.

## 4. Two real defects found in my own code, by the tests

Both were found because three balance tests failed and I traced the cause rather
than adjusting the expectations.

### 4.1 `keyBy('code')` silently picked the wrong account

The chart of accounts can hold **both** a global account and an
organisation-scoped account with the same code. `Account::whereIn('code', …)->get()->keyBy('code')`
keeps whichever row the database happened to return last.

Diagnostic output showed the ledger had posted to account id **183** while the
cash screen resolved code `1010` to account id **2**. The screen reported a
balance of zero while the money was sitting in the other row.

This is precisely the failure mode this phase exists to prevent — two figures
that disagree, both plausible. Fixed by resolving one account per code with the
organisation-scoped row always winning over a global template.

### 4.2 The organisation argument was silently ignored

`MoneyAccountService::accounts()` accepts an `$organization` parameter, but the
balance lookup went through `LedgerBalanceQuery`, which resolves the tenant from
**ambient request context**.

That is correct behaviour for a report rendered for the signed-in user, and wrong
here: passing another organisation would have reported the *current* farm's
balances against the *other* farm's accounts. Fixed by querying journal lines
directly with the explicit organisation id — still the same single ledger source.

`test_balances_do_not_leak_across_organisations` covers it.

Neither defect would have been caught by a test that only asserted "a balance is
returned".

## 5. Code delivered

| File | Purpose |
| --- | --- |
| `database/migrations/2028_09_04_100000_create_cash_transfers_table.php` | Transfer record with unique idempotency key |
| `app/Models/Finance/CashTransfer.php` | Decimal-safe model |
| `app/Services/Finance/Cash/MoneyAccountService.php` | Ledger-derived balances, overdrawn detection, money-account membership |
| `app/Services/Finance/Cash/RecordCashTransferService.php` | Transfers, posted through `PostingEngine` |

Money-account codes are read from the **same config Quick Expense pays out of**.
That is deliberate: if an expense can be paid from an account, that account must
appear on the cash screen, or money leaves somewhere the treasurer cannot see.

## 6. Tests

17 tests in `tests/Feature/Finance/Cash/CashTransferTest.php`: the no-revenue/no-expense
guarantee, debit/credit direction, balance, conservation of total money,
six validation refusals, idempotency, per-provider MoMo separation, overdrawn
detection, and organisation isolation.

## 7. Deliberately deferred

| Not built | Why |
| --- | --- |
| The seven-tab workspace UI (Overview, Money In, Money Out, Transfers, Accounts, Pending Verification, Reconciliation) | Money In and Money Out are views over receipts and payments that Phase 7 reshapes; Reconciliation is an existing advanced module already gated behind `finance.bank_reconciliation`. Building tabs now over data Phase 7 changes is rework. |
| "Confirm Cash Received" | Belongs with the COD settlement path in Phase 12, where the order and the sale both exist. Prompt §42 requires it to be permission-gated so unprivileged salespeople cannot mark orders paid — that control is meaningless until there is an order to mark. |
| Paystack clearing account | Phase 12, with the storefront-to-finance integration that populates it. |

The services are the durable part and are complete and tested. The screens that
present them are cheap once their data settles.

## 8. Commands run and results

| Command | Result |
| --- | --- |
| `php -l` on all new files | No syntax errors |
| `php artisan migrate --force` | `create_cash_transfers_table` applied |
| `php artisan test tests/Feature/Finance/Cash/` | **17 passed** (27 assertions) |
| `php artisan test` (full) | Recorded in §10 |

## 9. Problems found and corrected

| # | Problem | Disposition |
| --- | --- | --- |
| 1 | `keyBy('code')` resolved a duplicated account code to the wrong row; cash screen showed zero while the ledger held the money | **Corrected** — organisation-scoped row now wins |
| 2 | `$organization` argument ignored; balances came from ambient context | **Corrected** — explicit organisation in the ledger query |
| 3 | My test helper built a `JournalBatchDraft` with an idempotency key but no posting purpose | **My error.** The draft pairs them because `journal_postings` stores them together. Corrected. |

## 10. Unresolved risks carried forward

| Risk | Carried to |
| --- | --- |
| Cash workspace UI not built | Phase 7, once receipts and payments settle |
| Cash confirmation not built | Phase 12 |
| Paystack clearing not modelled | Phase 12 |
| No concurrency test for simultaneous transfers | Phase 16 |
| Overview "Cash available" card not yet rendered | Phase 7, with the rest of the summary cards |

## 11. Exit criteria

| Prompt §44 criterion | Met |
| --- | --- |
| Receipts | Deferred to Phase 7 |
| Cash expense | Yes — via Phase 4, balance now visible |
| Transfer | Yes |
| Bank → MoMo | Yes |
| MoMo → cash | Yes |
| Cash → bank | Yes |
| Duplicate transfer | Yes |
| Invalid business conditions | Yes — same-account, non-money-account, zero, negative |
| Permissions | Deferred with the UI |
| Organisation isolation | Yes |
| Reconciliation behaviour | Existing module, gated |

**Phase 5 status: COMPLETE** for the money-account and transfer services;
workspace UI, cash confirmation and Paystack clearing deferred and tracked.
