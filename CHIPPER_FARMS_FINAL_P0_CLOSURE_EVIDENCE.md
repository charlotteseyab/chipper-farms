# Chipper Farms Final P0 Closure Evidence

Date: 2026-07-30

## Baseline

- Starting commit: 8828a7a33dd79f28c51516388ba6761d8c1c9872
- Branch: remediation/final-p0-closure
- Pre-edit tag: pre-final-p0-closure
- PHP: 8.4.22
- Composer: 2.8.1
- Node: v20.19.0
- npm: 10.8.2
- Laravel: 13.23.0

## Commands And Results

| Command | Result |
| --- | --- |
| composer validate --no-check-publish | PASS |
| composer install --no-interaction --prefer-dist | PASS |
| composer audit | PASS, no security vulnerability advisories |
| npm ci | PASS, 0 vulnerabilities, deprecated glob warning |
| npm audit --audit-level=low | PASS, 0 vulnerabilities |
| npm run build | PASS, Browserslist/caniuse-lite freshness warning |
| php artisan config:clear | PASS |
| php artisan route:clear | PASS |
| php artisan view:clear | PASS |
| php artisan config:cache | PASS |
| php artisan route:cache | PASS |
| php artisan view:cache | PASS |
| php artisan route:list | PASS, 507 routes |
| php artisan event:list | PASS |
| php artisan schedule:list | FAIL, MySQL 127.0.0.1:3306 unavailable for database cache lock |
| php artisan migrate:status | FAIL, MySQL 127.0.0.1:3306 unavailable |
| vendor/bin/phpunit -c phpunit.concurrency.xml | FAIL, MariaDB 127.0.0.1:3399 unavailable |

## Route-Cache Tenancy Evidence

Before the code correction, the isolated cross-tenant pig route test passed with route cache cleared and failed with route cache enabled. The cached failure returned 403 where the expected tenant-safe response was 404.

After the code correction:

| Command | Result |
| --- | --- |
| php artisan route:clear; php artisan test tests/Feature/Security/DerivedOrganizationScopeTest.php --filter=cross_tenant_pig | PASS, 1 passed, 2 assertions |
| php artisan route:cache; php artisan test tests/Feature/Security/DerivedOrganizationScopeTest.php --filter=cross_tenant_pig | PASS, 1 passed, 2 assertions |
| php artisan route:cache; php artisan test tests/Feature/Security | PASS, 71 passed, 84 assertions |
| php artisan route:clear; php artisan test tests/Feature/Security | PASS, 71 passed, 84 assertions |

## Full Suite Evidence

Normal cleared-cache suite after the route binding fix:

- Command: php artisan test
- Result: PASS
- Tests: 1307 passed, 13 skipped
- Assertions: 3716
- Duration: 130.93s

Production-cache full suite baseline during this closure pass:

- Command: php artisan test after config:cache, route:cache, and view:cache
- Result: FAIL
- Tests: 1155 passed, 158 failed, 7 skipped
- Assertions: 3426
- Duration: 697.62s

## Remaining Evidence Gaps

- No MariaDB migration or concurrency proof was produced because required services were unavailable.
- No live Paystack sandbox payment, refund, or webhook proof was produced.
- No CI run, production deployment, monitoring alert, backup, or restore rehearsal was independently executed.
- Skipped tests remain and require disposition before a production GO.

