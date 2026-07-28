# Chipper Farms Final Production Readiness Report

## 1. Document Control

Assessment date: 28 July 2026  
Repository reviewed: `/home/davidcharway/Documents/pig-management-system`  
Reviewer role: independent production readiness reviewer  
Review mode: evidence-only audit; no corrective source changes made  
Final verdict: **NO-GO**

## 2. Executive Verdict

**NO-GO.** The current system must not be deployed to production for live livestock, commerce, payment or finance operations.

The codebase is substantially more complete than a prototype and the cleared-cache automated test suite passed locally. However, unresolved P0 release blockers remain in user provisioning, farm-route authorization, finance confidentiality, organisation isolation, storefront stock concurrency, Paystack refunds, refund accounting, cash-on-delivery settlement, scheduler registration, framework support and production operational certification.

## 3. Review Scope

The review covered Laravel application structure, routes, middleware, policies, controllers, services, models, migrations, seeders, tests, storefront checkout, Paystack verification, refunds, cash on delivery, finance posting, inventory, procurement, livestock workflows, scheduler/queues, dependency state, build reproducibility, and operational readiness.

## 4. Materials Reviewed

- Current repository at `/home/davidcharway/Documents/pig-management-system`.
- Prior Markdown assessment: `/home/davidcharway/Downloads/Chipper_Farms_Production_Readiness_Assessment.md`.
- Prior CSV issue register: `/home/davidcharway/Downloads/Chipper_Farms_Production_Readiness_Issue_Register.csv`.
- Master audit prompt: `/home/davidcharway/.codex/attachments/8af3c741-3f0f-48b7-8460-74bde09b26f8/pasted-text.txt`.
- Official Laravel release/support policy at `https://laravel.com/docs/12.x/releases`.

## 5. Review Methodology

I first reconciled the prior findings against the current codebase, then independently inspected the repository, route definitions, middleware, high-risk controllers, state-machine services, Paystack integration, refund flow, finance outbox, migrations, dependency locks and tests. I executed syntax, build, route, schedule, migration and test commands where safe in the workspace.

## 6. Environment And Execution Limitations

The local `.env` points to MySQL, but the review environment could not connect to that MySQL service. Therefore `php artisan migrate:status` and the first `schedule:list` attempt failed on database connectivity. MySQL/MariaDB fresh, upgrade, rollback, locking and concurrency behavior remains unverified.

`composer audit` could not be completed because the escalation required to query Packagist was rejected due dependency metadata egress risk. `npm audit` did execute and reported vulnerabilities.

Paystack live/sandbox payment and refund calls were not executed. The refund finding is based on source inspection: no refund method exists in the Paystack abstraction.

## 7. System Baseline

- Framework: Laravel 11.45.1 from `composer.lock`.
- PHP runtime used: PHP 8.4.22.
- PHP requirement: `^8.2`.
- Composer: 2.8.1.
- Frontend: Vite 6.3.5, Tailwind CSS 3.4, Livewire 3.6.4.
- Node runtime used: v20.19.0; npm 10.8.2.
- Database assumption: MySQL in current `.env`; SQLite in `phpunit.xml`.
- Cache/session/queue assumptions: database in local config; array/sync in tests.
- Mail: log locally; array in tests.
- Routes: 472 non-vendor routes.
- Migrations: 188.
- App PHP files: 509.
- PHP files linted across app/bootstrap/config/database/routes/tests: 944.
- Blade views: 290.
- Controllers: 86.
- Models: 120.
- Policies: 11.
- Middleware classes: 6.
- Console commands: 19.
- Jobs: 1.
- Test PHP files: 182; 1 Unit and 179 Feature files.
- Git status before report creation showed only pre-existing untracked `FINANCE_SMOKE_TEST(1).md` and `FarmSystemCodebasefinal.zip`.

## 8. Architecture Overview

The application is a Laravel monolith with livestock, feed, procurement, storefront and finance modules. Recent finance and storefront code uses service classes, domain events and a database outbox. Older operational routes remain controller-heavy and inconsistently protected.

The codebase uses Laravel 11's simplified `bootstrap/app.php` routing path. That matters because schedules left in `App\Console\Kernel` are not active unless explicitly wired.

## 9. Test And Command Execution Results

Commands completed with usable output:

- `composer validate --no-check-publish`: valid.
- `composer install --no-interaction --prefer-dist`: lock install verified; nothing to install.
- `php artisan about`: booted; Laravel 11.45.1; local debug enabled; MySQL/database cache/session/queue configured.
- `php artisan route:list --except-vendor`: 472 routes.
- `php artisan event:list`: listed framework events and `PigWeightObserver`.
- `env CACHE_STORE=array php artisan schedule:list`: showed only `finance:process-events` and `storefront:sync-availability`.
- `php artisan config:cache`, `route:cache`, `view:cache`: passed.
- PHP lint over 944 PHP files: no syntax errors.
- `npm ci`: installed 187 packages successfully.
- `npm run build`: Vite production build succeeded.
- `npm audit --audit-level=low`: completed and failed the audit gate with 13 advisories.
- `php artisan test` after clearing caches: **1,161 passed, 13 skipped, 3,442 assertions, 172.90s**.
- Fresh SQLite migration rehearsal in `/tmp`: all 188 migrations completed.

Failed or limited commands:

- `php artisan migrate:status`: failed because MySQL was unavailable.
- `php artisan schedule:list` without cache override: failed because database cache lock required MySQL.
- First `php artisan test` after cache commands: cache-tainted run failed with local MySQL/session leakage; superseded by cleared-cache run.
- `composer audit`: blocked by network/metadata egress policy.

## 10. Findings Summary

| Severity | Count | Release blockers |
|---|---:|---:|
| P0 | 12 | 12 |
| P1 | 10 | 0 |
| P2 | 3 | 0 |
| P3 | 0 | 0 |
| Total | 25 | 12 |

## 11. P0 Findings

### CF-P0-001 Settings User Creation Produces Orgless Accounts

Status: Confirmed. Previous: PR-001.  
Evidence: `SettingsController::storeUser()` creates a `User` without `organization_id` at lines 654-679. `EnsureOrganizationContext` aborts orgless authenticated users at lines 43-45. Fortify `CreateNewUser` contains the intended organization-aware logic at lines 51-82 but is not reused by settings.

Impact: A superuser can create an account that cannot make a first authenticated request.

Required correction: Use a single organization-aware provisioning service/action, require or inherit organization, set active status deliberately, and test creation-to-first-login for every role.

### CF-P0-002 Core Farm Writes Are Not Deny-By-Default

Status: Confirmed. Previous: PR-002.  
Evidence: `routes/web.php` lines 71-94, 129-247 and 271-317 expose health, medication, breeding, mating, farrowing, weaning, pig, weight, structure, room, raw-material and feed-production writes inside only the broad protected middleware group. Sampled controllers showed no `authorize`, `hasPermission`, `abort_unless` or controller middleware calls.

Impact: Any authenticated, verified, active user with an organization can directly call many operational write endpoints.

Required correction: Define a route/action permission matrix, enforce permissions or policies at every controller boundary, and add direct-request role tests.

### CF-P0-003 Finance Confidentiality Is Incomplete

Status: Confirmed with amendments. Previous: PR-003.  
Evidence: `routes/web.php` lines 360-372 document an outer finance role layer, but the actual `Route::prefix('finance')` group has no such middleware. Several read/write clusters remain without `view_finance`, including customer ledgers/statements, refunds index/show, suppliers index/show, supplier statements, vendor invoice index/show, payments index/show, payment runs index/show, WHT certificates and bank account/statement/reconciliation groups.

Impact: Same-organization users without finance permission may reach sensitive balances, suppliers, payments, reconciliations and reports by URL.

Required correction: Apply a finance-wide `view_finance` or equivalent gate, then narrower action permissions.

### CF-P0-004 Finance Attachment Downloads Lack Finance Permission

Status: Confirmed. Previous: PR-004.  
Evidence: `routes/web.php` lines 650-658 exposes download without middleware. `AttachmentController::download()` lines 75-98 checks organization and file existence but does not check `view_finance`, despite the class comment lines 23-27.

Impact: A same-organization user with an attachment ID can download financial evidence without module permission.

Required correction: Require parent-specific view policy or `view_finance`; test enumeration and cross-role access.

### CF-P0-005 Organisation Isolation Is Transitional And Permissive

Status: Confirmed. Previous: PR-005.  
Evidence: `BelongsToOrganization` lines 16-21 deliberately avoids a global scope. `bootstrap/app.php` lines 23-37 binds pigs, farrowing, mating and weaning with global `findOrFail`. `EnforcesOrganizationScope` lines 55-72 passes resources whose `organization_id` is absent/null. Many migrations keep `organization_id` nullable.

Impact: Cross-organisation direct access remains plausible in unguarded or legacy paths.

Required correction: Complete backfills, make required organization keys non-null, scope route bindings, fail closed on null organization and run cross-tenant tests for every workflow.

### CF-P0-006 Order Confirmation Can Oversell Or Double-Reserve

Status: Confirmed. Previous: PR-006.  
Evidence: `OrderService::confirm()` lines 82-107 does not lock/reload the order. `applyInventoryEffect()` lines 375-393 uses `StorefrontListing::find()` without `lockForUpdate`. `reservePigListing()` lines 397-403 silently succeeds if the pig listing is no longer published. `adjustProductQuantity()` lines 412-423 clamps negative stock to zero instead of rejecting.

Impact: Concurrent confirmations can confirm competing orders for one pig or oversell product stock.

Required correction: Lock order and listing rows, revalidate under lock, reject conflicts, and test with true multi-connection concurrency.

### CF-P0-007 Paystack Refunds Are Not Executed

Status: Confirmed. Previous: PR-007.  
Evidence: `PaystackClient` lines 21-35 exposes only initialize and verify. `HttpPaystackClient` lines 21-69 implements only those methods. `StorefrontOrderRefundService` lines 45-90 marks orders/payments refunded without any Paystack refund API call or provider refund status.

Impact: Customer-visible refund state can be set without returning customer funds.

Required correction: Add gateway refund initiation, idempotency, pending/failed/confirmed states, webhook or polling reconciliation and operator handling.

### CF-P0-008 Refund Accounting And Sales Return Are Incomplete

Status: Confirmed. Previous: PR-008.  
Evidence: `StorefrontOrderRefundService` lines 18-29 and 133-149 explicitly hand off COGS, inventory, biological-asset and pig restoration work to finance/manual sales return while still setting order status to refunded at lines 78-85.

Impact: Cash, revenue, inventory, COGS, biological assets and pig status can diverge after a refund.

Required correction: Implement an atomic sales-return workflow across gateway cash, GL, inventory, pig/listing state and audit approval.

### CF-P0-009 COD Completion Can Mark Orders Paid With Skipped Finance Lines

Status: Confirmed. Previous: PR-009.  
Evidence: `OrderService::complete()` lines 268-281 calls `settleCodPayments()` and then unconditionally sets payment status to paid. `settleCodPayments()` lines 303-330 skips unlinked order items and only records a note.

Impact: COD order state can report paid/completed while linked sales, receipts or receivables are absent.

Required correction: Block completion unless all finance-applicable lines are linked and settled, or introduce a settlement state machine that cannot reach paid until finance succeeds.

### CF-P0-010 Key Schedules Are Not Registered

Status: Confirmed with amendments. Previous: PR-010.  
Evidence: Active `schedule:list` with `CACHE_STORE=array` lists only `finance:process-events` and `storefront:sync-availability`. `App\Console\Kernel` lines 10-25 contains additional weight, gestation, notification, invoice reconciliation and loan interest schedules, but Laravel 11 bootstrap uses `routes/console.php`, which only defines `inspire`.

Impact: Important livestock and finance scheduled work may never run in production.

Required correction: Move schedules to `routes/console.php` or `withSchedule`, or bind the custom kernel, then verify `schedule:list`.

### CF-P0-011 Laravel 11 Is Outside Security Support

Status: Confirmed. Previous: PR-011.  
Evidence: `composer.lock` pins `laravel/framework` v11.45.1. Official Laravel support policy lists Laravel 11 security fixes until 12 March 2026. This assessment date is 28 July 2026.

Impact: A new production launch would begin on an unsupported framework branch.

Required correction: Upgrade to a supported Laravel release and retest all integrations.

### CF-P0-012 Production Release Certification Is Incomplete

Status: Confirmed with amendments. Previous: PR-012.  
Evidence: This checkout has `.env.example`, `.gitignore`, `vendor`, `node_modules`, successful `composer install`, `npm ci`, build, SQLite migrations and tests. However, `.github` CI workflows are absent, no Docker/process supervisor/worker/scheduler definitions were found, MySQL migration status could not run, `composer audit` could not run, and no backup/restore/runbook evidence was supplied.

Impact: The system is not yet a reproducible, certifiable production release for the intended database and operations environment.

Required correction: Add CI, production DB migration rehearsal, dependency audits, worker/scheduler process definitions, deployment/rollback and backup/restore evidence.

## 12. P1 Findings

### CF-P1-013 First-Use Document Sequence Allocation Can Race

Status: Confirmed. Previous: PR-013.  
Evidence: `NumberingService` lines 65-94 locks an existing row but creates the missing row without unique-conflict retry. The migration has a unique key, so concurrent first writers can collide. The test at `NumberingServiceTest` lines 122-137 explicitly simulates sequential calls, not true concurrency.

### CF-P1-014 Storefront References Use Count Plus One

Status: Confirmed. Previous: PR-014.  
Evidence: `CheckoutService::nextReference()` lines 210-218 and `CustomerAccountService::nextCustomerCode()` lines 87-95 derive identifiers from row counts.

### CF-P1-015 Finance Outbox Lacks Atomic Claiming And Retries

Status: Confirmed. Previous: PR-015.  
Evidence: `ProcessPendingDomainEventsJob` lines 69-86 selects pending events without lock/claim and sets `$tries = 1`.

### CF-P1-016 Migration Recovery Risks Remain

Status: Confirmed with amendments. Previous: PR-016.  
Evidence: Fresh SQLite migration passed. Still, `2026_04_25_120000_add_legacy_reporting_indexes.php` checks `farrowings` while the table is `farrowing`; `2027_03_01_120800...` uses plain `INSERT ... SELECT` before dropping the source table, despite idempotency claims.

### CF-P1-017 Automated Evidence Is Not A Production Release Gate

Status: Confirmed with amendments. Previous: PR-017.  
Evidence: Cleared-cache tests passed, but `phpunit.xml` uses SQLite `:memory:`, array cache/session and sync queue. There are 13 runtime skips and 42 skip calls in source. MySQL locking and worker behavior were not exercised.

### CF-P1-018 Core Livestock Workflow Test Coverage Is Thin

Status: Confirmed with amendments. Previous: PR-018.  
Evidence: There are baseline operation tests and an uncertain-pregnancy test, but no dedicated test files named for health, mating, gestation, farrowing, weaning or breeding. High-risk routes still lack permissions.

### CF-P1-019 Finance Attachment Parent Support Is Inconsistent

Status: Confirmed. Previous: PR-019.  
Evidence: Routes accept only `receipts|invoices|refunds`; `AttachmentController::resolveParent()` also supports `vendor-invoices` and `payments`, but `authorizeWriteFor()` omits `Payment`.

### CF-P1-020 Gateway Error Bodies Can Reach Customers

Status: Confirmed. Previous: PR-020.  
Evidence: `HttpPaystackClient` lines 40-42 and 57-59 include raw response bodies in exceptions. `PaystackWebhookController::callback()` lines 48-56 flashes the exception message to the browser.

### CF-P1-021 Locked NPM Dependencies Have Advisories

Status: Confirmed with amendments. Previous: PR-021.  
Evidence: `npm audit --audit-level=low` reported 13 vulnerabilities: 1 critical, 10 high, 2 moderate, including `shell-quote`, `axios`, `vite`, `rollup`, `postcss` and related packages.

### CF-P1-022 Operations, Monitoring And Recovery Are Insufficient

Status: Confirmed. Previous: PR-022.  
Evidence: `bootstrap/app.php` exposes only Laravel health `/up`; no CI, supervisor definitions, queue/scheduler service files, alert routing, backup/restore runbook or recovery proof were found.

## 13. P2 Findings

### CF-P2-023 Cancellation Restores Product Stock To Unpublished

Status: Confirmed. Previous: PR-023.  
Evidence: `OrderService::adjustProductQuantity()` lines 427-433 says “re-publish” but transitions `sold_out` to `unpublished`.

### CF-P2-024 Duplicate Dashboard Route

Status: Confirmed. Previous: PR-024.  
Evidence: `routes/web.php` lines 96-98 and 125-127 both define `GET /dashboard` with name `dashboard`.

### CF-P2-025 Documentation Is Not Production Operations Documentation

Status: Confirmed with amendments. Previous: PR-025.  
Evidence: The repository now includes `.env.example` and `.gitignore`, but `README.md` remains the stock Laravel README and no deployment/runbook/backup/restore documentation was found.

## 14. P3 Findings

No P3 findings were carried in this final register. Lower-severity observations were kept out to avoid diluting release blockers.

## 15. Module-By-Module Assessment

- Identity: RED because settings-created users can be orgless and inactive by default.
- Roles and permissions: RED because several core farm and finance routes are not deny-by-default.
- Organisation isolation: RED because binding and null org handling are incomplete.
- Livestock: RED because direct write routes remain under-protected.
- Breeding/farrowing/weaning: RED because routes are under-protected and schedules are not registered.
- Health/medication: RED because direct create/update/delete routes lack permission boundaries.
- Inventory/feed: AMBER/RED because service tests pass but storefront confirmation can oversell.
- Procurement: AMBER because service tests pass but segregation of duties requires a complete permission matrix.
- Storefront: RED because confirmation concurrency and refund completion remain blockers.
- Paystack: RED because verification is defensive but refunds are not implemented.
- Finance: AMBER/RED because core service tests pass but confidentiality, outbox, COD settlement and refunds remain incomplete.
- Operations: RED because production database, worker/scheduler, monitoring and recovery evidence are missing.

## 16. Roles And Permissions Assessment

Dashboard role tests pass, and many newer finance/storefront permission tests pass. The issue is coverage consistency: important operational route groups still lack route middleware or controller authorization. Hidden UI controls do not protect direct requests.

## 17. Organisation Isolation Assessment

The application has an organization model, middleware and scoped tests, but isolation is not yet enforced globally. Route binding remains global for several livestock resources. Null organization IDs are still accepted by policy helper code and many schema columns.

## 18. Livestock Workflow Assessment

Livestock CRUD, weights, sales, deaths and stock intake flows exist and have several passing tests. Production approval is blocked by unguarded write routes, global model binding and insufficient cross-role/cross-organisation coverage across all lifecycle endpoints.

## 19. Breeding Workflow Assessment

Mating, gestation, farrowing and weaning functionality exists. The active scheduler does not include the gestation and notification schedules left in `App\Console\Kernel`, and the route layer is not consistently permission-gated.

## 20. Health Workflow Assessment

Health and medication controllers validate basic fields but do not enforce role/permission checks. Health and medication routes include create, update, delete and export endpoints accessible through the broad authenticated group.

## 21. Inventory Assessment

Inventory services and tests show meaningful validation and accounting integration. The storefront order confirmation path remains unsafe because it modifies listing quantities without row locks and clamps oversold quantities to zero.

## 22. Procurement Assessment

Purchase request and receive-against-PR service tests passed. Production still requires a complete segregation-of-duties matrix showing who can request, approve, receive, invoice and pay.

## 23. Storefront Assessment

Cart, checkout, order lifecycle, quote and access tests passed in SQLite. Static review confirmed checkout revalidates stock but does not reserve it, while staff confirmation lacks row locks. Refund and COD completion state can outrun real payment/finance state.

## 24. Paystack Assessment

Positive controls: HMAC webhook verification, server-side transaction verification, amount and currency checks, and no auto-confirm after payment. Blocker: no Paystack refund API integration exists.

## 25. Refund Assessment

Refund tests cover permission gating and internal handoff notes. The implementation still marks orders and payments refunded without executing Paystack refunds and without completing inventory/COGS/biological-asset reversals.

## 26. Cash-On-Delivery Assessment

COD completion tries to settle linked sales, but skipped unlinked lines do not block `payment_status = paid`. Completion therefore can misrepresent cash collection and finance settlement.

## 27. Finance Assessment

Finance services are broad and test-backed. Confidentiality and release safety remain blockers because several finance route clusters lack an outer finance gate, attachments lack `view_finance`, and the outbox does not atomically claim work.

## 28. Scheduler And Queue Assessment

Active schedule list contains only:

- `finance:process-events` every minute.
- `storefront:sync-availability` hourly.

Kernel-only schedules for weights, gestation, notifications, invoice reconciliation and loan interest are not active in the Laravel 11 bootstrap path. Queue config uses database locally and sync in tests; worker supervision evidence is absent.

## 29. Security Assessment

Primary security blockers are broken access control, incomplete finance confidentiality, permissive organization handling and unsupported framework status. NPM dependency advisories remain unresolved. Paystack callback error handling may expose provider response bodies.

## 30. Database And Migration Assessment

Fresh SQLite migration passed for all 188 migrations. MySQL/MariaDB fresh, upgrade and rollback could not be verified. Migration source still contains a wrong `farrowings` table check and a destructive raw-material valuation cutover whose idempotency claim is not implemented with conflict-safe insert semantics.

## 31. Testing Quality Assessment

Cleared-cache test execution was strong: 1,161 passed and 13 skipped. Limitations remain material: SQLite in-memory database, sync queue, array cache/session, no browser tests, no Paystack sandbox, no true concurrency, no production database engine, and skipped SQLite constraint tests.

## 32. Performance Assessment

The review found performance risk in unscoped/global queries, dashboard/report aggregation, route-binding lookups, count-based identifiers and lack of locking around high-contention commerce confirmation. No production load test evidence was supplied.

## 33. Deployment And Operational Readiness

The repository includes dependency locks, `.env.example`, `.gitignore` and build assets. It lacks CI workflows, process supervision definitions, worker/scheduler deployment files, production database migration evidence, monitoring/alerting design, backup/restore runbook, disaster-recovery proof and Composer audit evidence.

## 34. Comparison With Previous Assessment

Previous findings reconciled: 25.  
Confirmed without material change: 15.  
Confirmed with amendments: 10.  
Rejected: 0.  
Duplicates removed: 0.  
Environment-limited only: 0.  
New unique findings: 0.

Amendments:

- PR-003: some newer finance routes are protected, but older/unwrapped routes remain exposed.
- PR-010: active schedule does include finance outbox and storefront sync; Kernel-only schedules remain missing.
- PR-012: the current checkout is more reproducible than the prior archive, but production certification remains incomplete.
- PR-016: fresh SQLite migration passed, but MySQL/upgrade and specific migration risks remain.
- PR-017: cleared-cache test suite passed, but it is not a production release gate.
- PR-018: some baseline livestock tests exist, but dedicated lifecycle coverage remains incomplete.
- PR-021: npm audit now directly confirms 13 advisories.
- PR-025: `.env.example` and `.gitignore` are present, but operational docs are still missing.
- PR-006 and PR-009: tests cover single-process happy paths, but static concurrency/settlement defects remain.

## 35. Confirmed Strengths

- Large functional surface across livestock, feed, procurement, storefront and finance.
- Clean PHP syntax across 944 checked PHP files.
- Passing cleared-cache automated test run.
- Passing Node install/build from lockfile.
- Passing fresh SQLite migration.
- Paystack verification includes signature, server-side verification, amount and currency checks.
- Finance services include double-entry, idempotency and many focused tests.

## 36. Release Blockers

All 12 P0 findings block release. The strongest blockers are unauthorized farm writes, incomplete organization isolation, unsafe order confirmation concurrency, missing Paystack refunds, incomplete refund accounting, COD settlement misstatement, inactive schedules and unsupported Laravel 11.

## 37. Required Route To GO

1. Fix org-aware user provisioning.
2. Enforce deny-by-default permissions on every farm, finance, download and export route.
3. Complete organization isolation and fail closed on null scope.
4. Make storefront confirmation/reservation concurrency-safe.
5. Implement Paystack refunds and complete sales-return accounting.
6. Prevent COD paid/completed state until finance settlement succeeds.
7. Register and verify all schedules.
8. Upgrade Laravel to a supported release.
9. Add atomic outbox claiming/retry/replay/monitoring.
10. Replace count-based references with atomic sequences.
11. Rehearse MySQL/MariaDB fresh, upgrade and rollback migrations.
12. Add CI, audits, worker/scheduler supervision, monitoring, backups and restore evidence.

## 38. Final GO, CONDITIONAL GO Or NO-GO Decision

**NO-GO.** A production deployment is not approved. The system may continue in controlled development or isolated demonstration with synthetic data and no real payment/refund obligations.

## 39. Sign-Off Statement

This review is evidence-based and review-only. I did not modify application source, migrations, configuration, package manifests or tests. The verdict is based on the current repository, command execution results, source inspection and the decision rule that any unresolved P0 requires NO-GO.
