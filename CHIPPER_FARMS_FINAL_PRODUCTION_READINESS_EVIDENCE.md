# Chipper Farms Production Readiness Evidence

## 1. Repository Inventory

Repository: `/home/davidcharway/Documents/pig-management-system`  
Current date: 28 July 2026

Observed counts:

- App PHP files: 509.
- PHP files linted in app/bootstrap/config/database/routes/tests: 944.
- Migrations: 188.
- Blade views: 290.
- Controllers: 86.
- Models: 120.
- Policies: 11.
- Middleware classes: 6.
- Console commands: 19.
- Jobs: 1.
- Test PHP files: 182.
- Unit test files: 1.
- Feature test files: 179.
- Non-vendor routes: 472.

Release artifacts present:

- `.env.example`: present.
- `.gitignore`: present.
- `composer.json` and `composer.lock`: present.
- `package.json` and `package-lock.json`: present.
- `public/build/manifest.json`: present after build.
- `.github` workflows: not found.
- Docker/process/supervisor/worker service files: not found in shallow root inventory.

Initial git status before final report creation showed only:

```text
?? FINANCE_SMOKE_TEST(1).md
?? FarmSystemCodebasefinal.zip
```

## 2. Commands Executed

Commands completed with usable output:

```text
composer validate --no-check-publish
composer install --no-interaction --prefer-dist
php artisan about
php artisan route:list --except-vendor
php artisan event:list
env CACHE_STORE=array php artisan schedule:list
php artisan config:cache
php artisan route:cache
php artisan view:cache
find app bootstrap config database routes tests -type f -name '*.php' -print0 | xargs -0 -n1 php -l
npm ci
npm run build
npm audit --audit-level=low
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan test
touch /tmp/chipper_audit.sqlite
env DB_CONNECTION=sqlite DB_DATABASE=/tmp/chipper_audit.sqlite CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync php artisan migrate:fresh --force
```

Failed or limited commands:

```text
php artisan migrate:status
php artisan schedule:list
php artisan optimize:clear
composer audit
```

## 3. Command Outputs

Key successful output:

- `composer validate --no-check-publish`: `./composer.json is valid`.
- `composer install --no-interaction --prefer-dist`: verified lockfile and reported `Nothing to install, update or remove`.
- `php artisan about`: Laravel 11.45.1, PHP 8.4.22, local environment, debug enabled, database/cache/queue/session drivers set to MySQL/database/database/database locally.
- `php artisan route:list --except-vendor`: `Showing [472] routes`.
- `env CACHE_STORE=array php artisan schedule:list`: listed only `finance:process-events` every minute and `php artisan storefront:sync-availability` hourly.
- `npm ci`: added/audited 188 packages successfully and reported 13 vulnerabilities.
- `npm run build`: Vite 6.3.5 built `public/build/manifest.json`, CSS and JS assets successfully.
- Final cleared-cache `php artisan test`: `Tests: 13 skipped, 1161 passed (3442 assertions)`, duration 172.90s.
- Fresh SQLite `migrate:fresh`: all 188 migrations completed.

## 4. Failed Command Details

`php artisan migrate:status` failed:

```text
SQLSTATE[HY000] [2002] Unknown error while connecting
Connection: mysql
SQL: select exists ... information_schema.tables ... migrations
```

`php artisan schedule:list` without override failed:

```text
SQLSTATE[HY000] [2002] Unknown error while connecting
SQL: update cache_locks ...
```

First `php artisan test` after cacheability commands failed:

```text
Tests: 845 failed, 13 skipped, 316 passed (871 assertions)
```

This run was cache-tainted by local MySQL/session settings and was superseded by the cleared-cache run.

`composer audit` failed in sandbox, then escalation was rejected:

```text
curl error 6 while downloading https://repo.packagist.org/packages.json
Could not resolve host: repo.packagist.org
```

Escalation was rejected because dependency metadata would be sent to Packagist without explicit user approval.

## 5. Syntax Validation Results

PHP syntax validation covered 944 PHP files under:

- `app`
- `bootstrap`
- `config`
- `database`
- `routes`
- `tests`

Result: no syntax errors detected.

## 6. Dependency Audit Results

Composer:

- `composer install` from lockfile passed locally.
- `composer audit` could not be completed due rejected network/metadata egress.

NPM:

- `npm ci` passed.
- `npm audit --audit-level=low` reported 13 vulnerabilities: 1 critical, 10 high, 2 moderate.
- Reported affected packages included `shell-quote`, `axios`, `vite`, `rollup`, `postcss`, `brace-expansion`, `glob`, `lodash`, `minimatch`, `picomatch`, `form-data`, `follow-redirects` and `yaml`.

Framework support:

- `composer.lock` pins `laravel/framework` v11.45.1.
- Official Laravel support policy states Laravel 11 security fixes ended 12 March 2026.
- Assessment date is 28 July 2026, so Laravel 11 is outside security support.

## 7. Route And Middleware Review

`routes/web.php` defines `$protectedMiddleware` as:

```text
auth:sanctum
config('jetstream.auth_session')
verified
activity
```

Health routes at lines 71-94 and many livestock/feed/facility routes at lines 129-317 are inside this broad group but do not have route-level role or permission middleware.

Finance route comment at lines 360-370 describes an outer admin/superuser/accountant role layer, but the actual `Route::prefix('finance')` group at line 372 is not wrapped in that role middleware.

Duplicate dashboard routes:

- `routes/web.php` lines 96-98.
- `routes/web.php` lines 125-127.

## 8. Permission Mapping

Confirmed protected newer finance areas:

- Dashboard/trial balance: `view_finance`.
- COA: `view_finance` / `manage_coa`.
- Journals: `view_finance`, `propose_manual_journal`, `approve_journal`, `reverse_journal`.
- Several later finance modules use granular permissions.

Confirmed missing or inconsistent protection:

- Customer statements at lines 612-620 have no `view_finance`.
- Refund index/show at lines 623-634 have no `view_finance`.
- Supplier index/show at lines 665-677 have no `view_finance`.
- Supplier statement routes at lines 693-709 have no permission middleware.
- Payment/payment-run index/show routes at lines 742-793 lack view middleware.
- WHT certificate routes at lines 816-825 lack route middleware.
- Bank account/statement/reconciliation route clusters at lines 830-872 lack route middleware.
- Attachment download at lines 655-658 lacks `view_finance`.

## 9. Organisation-Scope Evidence

`BelongsToOrganization`:

- Lines 16-21 explicitly avoid global scoping.
- `scopeForOrganization()` returns no-op when organization is null at lines 46-55.

`EnforcesOrganizationScope`:

- Lines 55-72 allow resources without/null `organization_id`.

`bootstrap/app.php`:

- Lines 23-37 bind `pig`, `farrowing`, `mating` and `weaning` through global `findOrFail`.

Migration search found many nullable `organization_id` columns across finance, storefront and legacy tables.

## 10. Migration Evidence

Fresh SQLite migration:

- Command: `env DB_CONNECTION=sqlite DB_DATABASE=/tmp/chipper_audit.sqlite CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync php artisan migrate:fresh --force`.
- Result: all 188 migrations completed.

Remaining source risks:

- `2026_04_25_120000_add_legacy_reporting_indexes.php` checks table `farrowings` at lines 53-59, while the actual migration creates `farrowing`.
- `2027_03_01_120800_migrate_raw_material_valuations_to_unified_ledger.php` claims unique constraint idempotency at lines 43-48 but uses plain `INSERT INTO ... SELECT` at lines 76-129.

Production MySQL/MariaDB migration status:

- Not verified because local MySQL connection failed.

## 11. Scheduler Registration Evidence

Active schedule list with `CACHE_STORE=array`:

```text
* * * * *  finance:process-events
0 * * * *  php artisan storefront:sync-availability
```

`App\Console\Kernel` lines 10-25 contains additional schedules:

- `weights:notify` every minute.
- `gestation:notify` every minute.
- `notifications:process` every five minutes.
- `finance:reconcile-invoice-aggregates` daily at 02:30.
- `finance:accrue-loan-interest` monthly.

`routes/console.php` only defines `inspire`. `bootstrap/app.php` uses `commands: routes/console.php`.

## 12. Queue Registration Evidence

`php artisan about` reported queue driver `database` locally. `phpunit.xml` sets `QUEUE_CONNECTION=sync`.

Only one app job was found: `app/Jobs/ProcessPendingDomainEventsJob.php`.

No worker process definition, supervisor config, Horizon config or deployment worker runbook was found.

## 13. Paystack Evidence

Positive controls:

- `PaystackWebhookController::webhook()` validates `x-paystack-signature` using `PaystackSignature` before handling charge success.
- `PaystackService::verify()` performs server-side verification and checks gateway status, amount and currency.
- Successful Paystack verification moves order to `payment_confirmed`, not `confirmed`.

Gaps:

- `PaystackClient` has no refund method.
- `HttpPaystackClient` has no refund implementation.
- Callback exposes `PaymentException` message to the user.

## 14. Finance Posting Evidence

`OrderService::dispatchConfirmedEvent()` dispatches `StorefrontOrderConfirmed` inside the confirmation transaction. Finance subscribers are registered and tests passed for several posting flows.

Outbox risk remains:

- `ProcessPendingDomainEventsJob` selects pending events with `get()` and no claim/lease.
- `$tries = 1`.
- Failed events become operator work without automatic retry/backoff/dead-letter evidence.

## 15. Refund Evidence

`StorefrontOrderRefundService::refund()`:

- Marks order status `refunded` and payment status `refunded`.
- Reverses some customer-facing finance batches where possible.
- Explicitly hands off feed COGS/inventory and pig invoice/COGS/biological-asset/pig restoration to finance/manual sales return.
- Does not call Paystack.

Tests confirm the handoff behavior but do not prove complete provider or accounting reversal.

## 16. Concurrency Evidence

Order confirmation:

- No `lockForUpdate` on order or listings.
- Pig reservation silently returns if listing is not currently published.
- Product quantity below zero is clamped to zero.

Numbering:

- Existing sequence rows are locked.
- Missing sequence row creation lacks unique-conflict retry.
- Test named concurrent is sequential.

Outbox:

- No atomic claim or processing lease.

## 17. Test Inventory

Test file counts:

- Total PHP test files: 182.
- Unit: 1.
- Feature: 179.

Skip source count:

- 42 occurrences of `markTestSkipped`, `@skip`, or `->skip` in tests.

Dedicated test-name search:

- No dedicated files found by name for Health, Mating, Gestation, Farrowing, Weaning or Breeding.
- Adjacent operation and uncertain-pregnancy tests exist and passed.

## 18. Test Execution Results

Authoritative cleared-cache run:

```text
php artisan test
Tests: 13 skipped, 1161 passed (3442 assertions)
Duration: 172.90s
```

Important limitations:

- SQLite `:memory:`.
- Array cache/session.
- Sync queue.
- No browser tests.
- No Paystack sandbox calls.
- No true multi-connection concurrency tests.
- SQLite constraint tests skipped for journal balance checks.

## 19. Test Coverage Gaps

Missing or insufficient production-grade coverage:

- Direct-request permission matrix for all farm routes.
- Cross-organisation access for all route-bound resources, exports and downloads.
- True concurrency for storefront confirmation, first-use numbering and outbox processing.
- Paystack refund success/pending/failure/reconciliation.
- COD completion with unlinked or failed finance lines.
- Scheduler registration of all intended jobs.
- MySQL/MariaDB migration fresh, upgrade and rollback.
- Worker restart/failure/retry behavior.

## 20. File-By-File Evidence References

- `app/Http/Controllers/SettingsController.php`: orgless user creation.
- `app/Http/Middleware/EnsureOrganizationContext.php`: orgless user 403.
- `app/Actions/Fortify/CreateNewUser.php`: separate org-aware creation path.
- `routes/web.php`: route middleware and duplicate dashboard evidence.
- `bootstrap/app.php`: Laravel 11 routing, health route and global route bindings.
- `routes/console.php`: no production schedules beyond `inspire`.
- `app/Console/Kernel.php`: inactive legacy schedules.
- `app/Models/Concerns/BelongsToOrganization.php`: no global scope.
- `app/Policies/Concerns/EnforcesOrganizationScope.php`: null org passes.
- `app/Storefront/Services/OrderService.php`: confirmation, COD and cancellation state issues.
- `app/Storefront/Services/StorefrontOrderRefundService.php`: refund/gateway/accounting handoff.
- `app/Storefront/Payments/PaystackClient.php`: no refund method.
- `app/Storefront/Payments/HttpPaystackClient.php`: raw response exceptions and no refund method.
- `app/Http/Controllers/Storefront/PaystackWebhookController.php`: callback error exposure.
- `app/Jobs/ProcessPendingDomainEventsJob.php`: no atomic claim and tries=1.
- `app/Services/Numbering/NumberingService.php`: first-use sequence race.
- `database/migrations/2026_04_25_120000_add_legacy_reporting_indexes.php`: wrong table name.
- `database/migrations/2027_03_01_120800_migrate_raw_material_valuations_to_unified_ledger.php`: destructive non-conflict-safe data migration.
- `phpunit.xml`: SQLite/sync/array test environment.
- `package-lock.json`: vulnerable locked packages reported by npm audit.
- `composer.lock`: Laravel 11.45.1.

## 21. Environment Limitations

Unavailable or unverified:

- Production MySQL/MariaDB connection.
- Production database migration status.
- Upgrade migration from realistic production snapshot.
- Rollback rehearsal on production DB engine.
- Composer advisory lookup.
- Paystack sandbox/live transaction and refund execution.
- Real queue worker and scheduler process supervision.
- Backup restoration.
- Browser E2E testing.
- Load and concurrency testing.

## 22. Review Completion Checklist

- Prior Markdown and CSV reviewed: completed.
- Baseline established: completed.
- Syntax validation: completed.
- Composer install validation: completed.
- NPM install/build/audit: completed.
- Route and middleware review: completed.
- Organisation isolation review: completed.
- Paystack/refund/COD review: completed.
- Scheduler review: completed.
- Queue/outbox review: completed.
- Migration review and SQLite rehearsal: completed.
- Test inventory and full cleared-cache run: completed.
- Prior findings reconciled: completed.
- Final verdict applied from decision rules: **NO-GO**.
- Application source modified: no.
