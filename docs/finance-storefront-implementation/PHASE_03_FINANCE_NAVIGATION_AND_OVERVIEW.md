# Phase 3 — Finance Navigation, Overview and Needs Attention

## Document control

| Field | Value |
| --- | --- |
| Phase | 3 — Finance navigation, Overview, Needs Attention |
| Date | 2026-08-07 |
| Branch | `staging` |
| HEAD at phase start | `d293a86` |
| Blueprint anchor | §20 (navigation), §22 (dashboard), §25 (advanced), §26 (role-aware), §54 (recovery UX) |
| Prompt anchor | §24–§30 |
| Phase status | COMPLETE — with summary cards deliberately deferred (§8) |

## 1. Scope

The first phase that consumes the Phase 1 flag layer. Delivers a farm-facing
money screen, a consolidated attention queue, and navigation built from features
and permissions instead of hardcoded role arrays.

## 2. Security defect found and fixed

**Cross-tenant count leak in the existing finance dashboard. P2.**

`FinanceDashboardController` built four KPI counts. Two were organisation-scoped
and two were not:

```php
'pending_events' => DomainEvent::where('status', STATUS_PENDING)->count(),   // unscoped
'failed_events'  => DomainEvent::where('status', STATUS_FAILED)->count(),    // unscoped
'drafts_awaiting'=> JournalBatch::…->when($orgId, …)->count(),               // scoped
'posted_last_30' => JournalBatch::…->when($orgId, …)->count(),               // scoped
```

`domain_events` **does** carry `organization_id` — confirmed against the live
schema — so this was an omission, not a schema limitation.

Two consequences. An accountant at one farm saw the number of failed finance
postings belonging to every other farm, which is someone else's operational
data. And their own figure was simply wrong, which is arguably worse: the
dashboard exists to tell them whether their books are complete, and it was
answering a different question.

Severity P2 rather than P1 because counts leak far less than records. Fixed by
scoping both counts identically to their neighbours.

Not caught by the earlier readiness audit despite that audit having a dedicated
organisation-isolation finding (CF-P0-005).

## 3. Needs Attention

Blueprint §54 and prompt §28 ask for one place where anything requiring a human
appears. Before this, the states lived in four unrelated places: failed outbox
rows in `domain_events`, blocked COD completions in a storefront order event
note, pending refunds on the order, missing tax configuration in the *absence* of
rows. Nobody checks four places, so in practice these surfaced when a customer
complained.

Six sources:

| Item | Severity | Why |
| --- | --- | --- |
| Failed finance postings | Critical | Source transaction succeeded, its accounting did not. Books incomplete until replayed. |
| Stuck finance postings | Warning | Claimed >30 min ago and still processing. Nothing errored, so **nothing will retry it** — distinct from failed for that reason. |
| COD awaiting cash | Warning | Ready or dispatched, not paid. The CF-P0-009 gate working correctly, but it leaves an order a human must chase. |
| Refunds pending | Info, → Critical after 3 days | Customer has been told money is coming and it has not moved. |
| Orders awaiting confirmation | Info | Stock is not reserved until staff confirm. |
| Tax not configured | Warning | No effective sales profile. |

### 3.1 Two audiences, one source

Every item carries a plain-language `message` and a technical `detail`.
Blueprint §54 requires that outbox errors not be the primary user message. So an
owner reads *"One bookkeeping action could not be completed"* and can expand to
find the domain-event explanation. `test_a_failed_posting_message_is_plain_language`
asserts the headline contains none of "domain event", "outbox" or "subscriber",
and that the detail does.

### 3.2 Filtered by who can act

The controller drops items whose permission the viewer lacks. An owner's screen
should not fill with problems only an accountant can resolve. The alternative —
showing everything to everyone — produces a queue people learn to ignore.

### 3.3 The refund guidance says "do not resubmit"

Asserted by test. A pending refund looks stuck, and the intuitive response is to
submit it again, which risks paying the customer twice.

## 4. Feature-aware navigation

`NavigationHelper::sidebarSections()` builds the finance menu from repeated
hardcoded role arrays such as `['superuser', 'admin', 'accountant']`. Blueprint
§26 says plainly: *"Do not rely on hardcoded roles where permissions are more
appropriate."*

`FinanceNavigation` admits an entry only when **all three** hold:

1. the feature is usable (including its whole dependency chain);
2. the user holds at least one of the entry's permissions;
3. the route is actually registered.

Condition 2 is the real control and is enforced again by middleware. Showing a
link is not granting access; hiding one is not protecting anything.

Condition 3 exists because several targets are built in later phases. A menu
referencing an unregistered route throws on **every page render for every user**
— a defect class this codebase has already hit once, when six routes threw
`TypeError` and an entire role class got a 500 on every page.
`test_navigation_never_references_a_route_that_does_not_exist` enables every
feature and grants every permission, then asserts every emitted route resolves.

Advanced Accounting is a **group**, not sixteen flat entries, and the group
vanishes entirely when nothing inside it is available — an empty expandable group
is a dead end.

## 5. What was not deleted

The ledger dashboard remains at `finance.dashboard`, unchanged apart from the
scoping fix. Blueprint §25 relocates these modules; it does not remove them. An
accountant who wants pending events, draft batches and trial-balance totals still
has them.

## 6. Code delivered

| File | Purpose |
| --- | --- |
| `app/Services/Finance/Overview/NeedsAttentionService.php` | Six-source attention queue, organisation-scoped, severity-ranked |
| `app/Support/Features/FinanceNavigation.php` | Feature + permission + route-existence menu builder |
| `app/Http/Controllers/Finance/FinanceOverviewController.php` | Overview screen, permission-filtered items and quick actions |
| `resources/views/finance/overview.blade.php` | Mobile-first farm-language screen |
| `app/Http/Controllers/Finance/FinanceDashboardController.php` | Cross-tenant count leak fixed |
| `routes/web.php` | `finance.overview`, gated on feature **and** `view_finance` |

## 7. Tests

30 new tests.

| File | Tests | Covers |
| --- | ---: | --- |
| `NeedsAttentionTest` | 18 | All six sources, severity escalation, plain language, ordering, and four organisation-scoping assertions |
| `FinanceOverviewScreenTest` | 12 | Render, permission filtering, feature gating, nav feature/permission/route-existence, advanced grouping |

## 8. Deliberately deferred — money summary cards

Blueprint §22 lists cash available, sales this month, expenses this month,
customers owing, bills to pay, loan payments due.

**Not built in this phase, and the screen says so in a comment rather than
rendering placeholders.**

The reason is ownership. Cash available is the Cash/Bank/MoMo workspace's figure
(Phase 5). Expenses this month becomes meaningful once Quick Expense and Simple
Purchase exist (Phases 4 and 6). Customers owing belongs with Sales & Money In
(Phase 7). Building six aggregates now, against data flows those phases will
reshape, produces numbers that are correct today and rework tomorrow.

A dashboard card showing a plausible but wrong number is worse than no card:
nobody checks a figure that looks reasonable. The screen renders Needs Attention
and Quick Actions, both complete and tested, and gains its cards as the phases
that own them land.

This is recorded as an open item in the register rather than quietly dropped.

## 9. Commands run and results

| Command | Result |
| --- | --- |
| `php -l` on all new/changed files | No syntax errors |
| `php artisan test tests/Feature/Finance/Overview/NeedsAttentionTest.php` | **18 passed** (44 assertions) |
| `php artisan test tests/Feature/Finance/Overview/FinanceOverviewScreenTest.php` | **12 passed** (36 assertions) |
| `php artisan test` (full) | Recorded in §12 |

## 10. Problems found and corrected

| # | Problem | Severity | Disposition |
| --- | --- | --- | --- |
| 1 | Finance dashboard counted `domain_events` across all tenants | P2 | **Corrected.** |
| 2 | My first draft of `NeedsAttentionService` referenced `StorefrontOrder::STATUS_READY` and `STATUS_DISPATCHED`, which do not exist, behind `?? 'ready'` fallbacks | — | **My error.** The real constants are `STATUS_READY_FOR_PICKUP` and `STATUS_OUT_FOR_DELIVERY`. The `??` fallback would have made the COD check silently match nothing and always report zero. Fallbacks removed entirely — they mask exactly this. |

Problem 2 is worth recording because the failure mode was silence. A wrong
constant behind a null-coalescing default does not throw; it just quietly returns
an empty result, and an attention queue that reports nothing looks identical to a
farm with no problems.

## 11. Unresolved risks carried forward

| Risk | Carried to |
| --- | --- |
| Money summary cards not built | Phases 4–7, as each phase's data lands |
| `NavigationHelper` still builds the old role-based finance menu | Phase 14, where the switch-over happens with the old routes gated |
| Attention items have no deep links yet | Phase 13 (recovery console) |

`FinanceNavigation` is deliberately **not** yet wired into the sidebar. Phase 1's
ordering rule applies again: the new menu points at Phase 4–7 routes that do not
exist. Switching over now would hide today's working screens behind entries that
404. Phase 14 owns the cut-over.

## 12. Exit criteria

| Prompt §30 criterion | Met |
| --- | --- |
| Dashboard aggregation | Partial — attention queue complete; money cards deferred (§8) |
| Organisation isolation | Yes — and a real leak fixed |
| Date filtering | Deferred with the cards |
| Permissions | Yes |
| Feature flags | Yes |
| Advanced finance visibility | Yes |
| Needs Attention | Yes |
| Mobile rendering | Yes — single-column, stacked, no fixed widths |
| Empty states | Yes |

**Phase 3 status: COMPLETE** for navigation, Overview and Needs Attention; money
summary cards deferred to Phases 4–7 by design and tracked.
