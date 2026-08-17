# Chipper Farms Storefront Pig Sale Flow Post-Remediation Evidence

## Environment

- Starting branch: `remediation/finance-storefront-final-blockers`
- Starting commit: `05ebe6e550c64be1c10e10b17573301ec160dd02`
- Final branch: `remediation/finance-storefront-final-blockers`
- Final commit: `05ebe6e550c64be1c10e10b17573301ec160dd02` plus uncommitted remediation changes
- Normal `.env` database used for destructive testing: No

## Gate Results

| Category | Evidence |
|---|---|
| Default suite | `php artisan test` passed: 1836 passed, 13 skipped, 5669 assertions |
| MariaDB suite | `./vendor/bin/phpunit -c phpunit.mariadb.xml` passed: 1849 tests, 5679 assertions, 7 skipped |
| Concurrency suite | `./vendor/bin/phpunit -c phpunit.concurrency.xml` passed: 19 tests, 324 assertions |
| Browser flow | `php artisan dusk tests/Browser/PigPoolFlowTest.php` passed: 5 tests, 24 assertions |
| Full browser suite | `php artisan dusk` passed: 6 tests, 25 assertions |
| Frontend audit | `npm audit` passed: 0 vulnerabilities |
| Frontend build | `npm run build` passed |
| Composer audit | `composer audit` passed: no security vulnerability advisories |
| Composer validation | `composer validate --no-check-publish` passed |
| Production cache | `php artisan optimize` passed |
| Whitespace | `git diff --check` passed |

## Functional Proof Points

- Partial 5-head pooled listing with 3-head order remains `published`, visible, orderable, and reports `2.0000` available.
- Completion of the partial order keeps the remaining 2-head pool public and orderable.
- Sale of the final 2 linked pigs changes listing availability to `0.0000` and status to `sold_out`.
- A direct `/sales` sale of a remaining linked pig reduces storefront pool availability and keeps the pool public until exhausted.
- A direct `/sales` sale cannot consume a pig already selected for a storefront order.
- A direct `/sales` sale cannot undercut active storefront holds.
- Dead/sold/ineligible linked pigs do not count as available even if their pool-row status is stale.
- Pooled allocation approval creates real `PigSale` records and soft-deletes sold pigs from active Standard Stock.
- COD completion settles every pooled allocation sale.

## Browser / Mobile Evidence

- `PigPoolFlowTest` verifies the end-to-end pooled browser flow from public shop visibility, exact pig selection, approval, remaining public quantity, fulfilment completion, and final sold-out state.
- `PigPoolFlowTest` includes a 390px approval-screen overflow check.
- Existing `MobileViewportTest` includes a 390px public shop route check and is part of the browser test file set.

## Notes

The Dusk framework was used for browser automation. No Playwright test runner is present in this Laravel application.
