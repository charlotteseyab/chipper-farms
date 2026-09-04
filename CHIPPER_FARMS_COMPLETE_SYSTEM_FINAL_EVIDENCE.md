# Chipper Farms Complete System Final Evidence

Audit date: 2026-09-04  
Branch: `staging`  
Commit: `0702680c86497d5a899b622db5460f8f60b338bf`

## Baseline

- `git status --short`: only final audit artifact files untracked after cleanup.
- `git diff --check`: no whitespace errors.
- `git ls-files`: `.env` is not tracked; `.env.example`, `FarmSystemCodebasefinal.zip`, and `pig management final codes.zip` are tracked.
- `php -v`: PHP 8.4.22.
- `composer --version`: Composer 2.8.1.
- `node --version`: v20.19.0.
- `npm --version`: 10.8.2.
- `php artisan about`: Laravel 13.23.0, Livewire 3.8.2, local environment, debug enabled, MySQL/database queue/session/cache drivers.

## Dependency And Build Evidence

- `composer validate --no-check-publish`: PASS.
- `composer install --no-interaction --prefer-dist`: PASS.
- `composer audit`: FAIL.
  - `league/commonmark`: high severity advisories including DoS and XSS classes.
  - `livewire/livewire`: medium DOM XSS advisory affecting installed range.
- `npm ci`: PASS, but reported `2 vulnerabilities (1 low, 1 high)`.
- `npm audit --audit-level=low`: FAIL.
  - `browserslist <=4.28.6`: high severity advisories.
  - `postcss-selector-parser 6.1.0 - 6.1.2`: low severity advisory.
- `npm run build`: PASS. Vite built CSS/JS assets successfully. Warning: Browserslist/caniuse-lite outdated.

## Xampp/MariaDB Evidence

The user stated Xampp is active. Direct DB probes confirmed:

- MariaDB/MySQL on `127.0.0.1:3306`: reachable with `root` and no password.
- Port `3399`: not reachable.
- Disposable DBs prepared on `3306`:
  - `chipper_farms_test`
  - `chipper_farms_concurrency_test`
  - `chipper_farms_upgrade_test`
  - `chipper_farms_restore_test`

## Default Test Suite

Command: `php artisan test`

Result: FAIL

- `20 failed`
- `14 skipped`
- `2029 passed`
- `6500 assertions`
- Duration: `385.85s`

Representative failures:

- `Tests\Feature\Authorization\ControllerAuthorizationTest::test_user_from_different_org_cannot_view_sale`: expected `403`, got `404`.
- `Tests\Feature\DeleteAccountTest::test_user_accounts_can_be_deleted`: user still exists after deletion flow.
- `Tests\Feature\ExampleTest::the application returns a successful response`: `500`, missing `organizations` table under default SQLite path.
- `Tests\Feature\Features\ModuleSettingsScreenTest`: `Class "App\Http\Controllers\Settings\Organization" not found`.
- `Tests\Feature\Finance\Expenses\QuickExpenseAttachmentTest::another_organisation_cannot_attach_to_this_expense`: expected `403`, got `404`.
- `Tests\Feature\Finance\Overview\NeedsAttentionTest`: missing cross-org dashboard keys and null offset.
- `Tests\Feature\Navigation\HumanQaSidebarNavigationTest`: unexpected `Settings` label in storefront sidebar.
- `Tests\Feature\Policies\PurchaseRequestPolicyTest::test_accountant_can_mark_approved_request_paid`: gate denied.
- `Tests\Feature\Stock\PurchaseStockIntakeTest`: create row missing and cross-org action expected `403`, got `404`.
- `Tests\Feature\Storefront\FinanceIntegrationTest::test_manual_line_posts_a_real_finance_invoice_not_a_fake_sale`: `PeriodNotOpenException` for `2026-09-04`.
- `Tests\Feature\Storefront\ListingReferenceUniquenessTest`: same reference across two organisations count mismatch.
- `Tests\Feature\Storefront\ListingServiceTest::test_superuser_can_delete_a_listing_and_release_unsold_pigs`: expected redirect, got `403`.
- `Tests\Feature\Storefront\QuoteServiceTest`: quote notification not sent.
- `Tests\Feature\Storefront\StorefrontSettingsMigrationTest`: delivery zone uniqueness count mismatch.
- `Tests\Feature\Weights\WeightStandardsAuditTest`: expected standard audit text missing.

## MariaDB Suite

Command:

`CF_SKIP_MARIADB_LOCAL_ENV=yes DB_HOST=127.0.0.1 DB_PORT=3306 DB_DATABASE=chipper_farms_test DB_USERNAME=root DB_PASSWORD= scripts/testing/run-mariadb-tests.sh`

Result: FAIL

- `2063 tests`
- `6505 assertions`
- `3 errors`
- `17 failures`
- `8 skipped`
- Duration: `27:26.949`

Key failures/errors included:

- Finance overview null offset at `tests/Feature/Finance/Overview/NeedsAttentionTest.php:285`.
- Storefront manual finance invoice fails because no open fiscal period covers `2026-09-04`.
- Storefront stock search attempted string `LOCAL-777` into integer `pigs_management.local_id`.
- Module settings 500 due missing `Organization` import.
- Delete-account flow did not delete user.
- Authorization and tenant-scope tests expected `403`, got `404`.

## Concurrency Suite

Command:

`CF_SKIP_MARIADB_LOCAL_ENV=yes DB_HOST=127.0.0.1 DB_PORT=3306 DB_DATABASE=chipper_farms_concurrency_test DB_USERNAME=root DB_PASSWORD= CF_CONCURRENCY_DB_USERNAME=root CF_CONCURRENCY_DB_PASSWORD= scripts/testing/run-concurrency-tests.sh`

Result: FAIL

- Fresh migrations on disposable DB: PASS.
- `25 tests`
- `429 assertions`
- `1 failure`
- Duration: `01:34.014`

Failure:

`Tests\Concurrency\CarcassAndLivePurchaseConcurrencyTest::test_live_and_carcass_buyers_cannot_between_them_oversell_the_pool`

Message:

`200.0000 kg of meat is promised, but 1 animal(s) are spoken for live, leaving only 100.0000 kg to cut.`

## Production Cache Suite

Command:

`CF_SKIP_MARIADB_LOCAL_ENV=yes DB_HOST=127.0.0.1 DB_PORT=3306 DB_DATABASE=chipper_farms_test DB_USERNAME=root DB_PASSWORD= scripts/testing/run-production-cache-tests.sh`

Result: FAIL

- `optimize:clear`: PASS.
- `config:cache`: PASS.
- `route:cache`: PASS.
- `view:cache`: PASS.
- Cached test run: FAIL.
- `2063 tests`
- `6505 assertions`
- `3 errors`
- `17 failures`
- `8 skipped`
- Duration: `15:42.414`

Post-audit cleanup:

- `php artisan optimize:clear` rerun successfully.
- Temporary `storage/framework/testing-production-cache.lock` was removed.

## Browser/Dusk Evidence

Disposable Dusk DB prep:

`APP_ENV=testing APP_URL=http://127.0.0.1:8321 DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306 DB_DATABASE=chipper_farms_upgrade_test DB_USERNAME=root DB_PASSWORD= CACHE_STORE=array SESSION_DRIVER=file QUEUE_CONNECTION=sync php artisan migrate:fresh --seed --force --env=dusk.local`

Result: PASS. Full migration chain and seeders completed.

Temporary server:

`php artisan serve --host=127.0.0.1 --port=8322 --env=dusk.local`

Result: PASS; server started and was stopped after test attempt.

Dusk command:

`APP_ENV=testing APP_URL=http://127.0.0.1:8322 DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306 DB_DATABASE=chipper_farms_upgrade_test DB_USERNAME=root DB_PASSWORD= CACHE_STORE=array SESSION_DRIVER=file QUEUE_CONNECTION=sync PAYSTACK_ENABLED=false php artisan dusk --env=dusk.local`

Result: FAIL before assertions:

- `3 failed`
- `10 pending`
- `0 assertions`
- Missing Chromedriver path: `vendor/bin/chromedriver-linux`.

## Scheduler And Queue Evidence

`CACHE_STORE=array php artisan schedule:list`: PASS.

Scheduled commands:

- `* * * * * finance:process-events`
- `0 * * * * storefront:sync-availability`
- `* * * * * storefront:expire-holds`
- `* * * * * weights:notify`
- `* * * * * gestation:notify`
- `*/5 * * * * notifications:process`
- `30 2 * * * finance:reconcile-invoice-aggregates`
- `0 2 1 * * finance:accrue-loan-interest`

`php artisan list finance`: PASS and confirms `finance:process-events` exists.

`php artisan finance:process-events --help`: PASS. Options include `--batch`, `--dry-run`, and `--replay-failed`.

Important operational note: `queue:work` is not the scheduler. Production needs both queue workers and a scheduler/cron process.

## Security And Hygiene Evidence

- Secret-pattern scan did not find tracked `.env`, but did flag many token/secret placeholders and comments in tracked docs/tests.
- `README_PHASE_3.md` contains placeholder live key examples (`sk_live_xxx`, `pk_live_xxx`), and tracked ZIP archives matched binary scans. These must be reviewed or removed before production.
- Debug filename scan across `app`, `resources`, `routes`, `config`, `database`, and `tests` returned no obvious files from the selected debug markers.

## Final Evidence Conclusion

The release candidate is **not production ready**. The highest-risk confirmed issue is the concurrent live/carcass overcommit. The fastest path to a clean re-audit is to fix P0/P1 items, upgrade vulnerable dependencies, install/pin Chromedriver, rerun all suites, and certify Paystack sandbox/live-mode separation.
