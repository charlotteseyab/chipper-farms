# Chipper Farms Human-QA Remediation Report

## 1. Document Control

- Date: 2026-08-12
- Scope: Human-QA remediation after early smoke-test stoppage.
- Decision: HUMAN-QA REMEDIATION COMPLETE - READY TO RESTART MANUAL SMOKE TEST

## 2. Starting Branch/Commit

- Branch: remediation/finance-storefront-final-blockers
- Commit: 64b8a29c643fdec8c649058b06dd3c03ef6e5f2a

## 3. Final Branch/Commit

- Branch: remediation/finance-storefront-final-blockers
- Commit: uncommitted local remediation changes on top of 64b8a29c643fdec8c649058b06dd3c03ef6e5f2a

## 4. Human QA Findings

- QA-001: Modules & Features existed at `/settings/modules` but was absent from sidebar and insufficiently narrowed to Superuser.
- QA-002: Storefront back-office surfaces existed but were absent from the main sidebar.
- QA-003: Public storefront used a visually separate shell.
- QA-004: Storefront Listings index showed records without actionable row controls.

## 5. Root-Cause Summary

The previous remediation focused on repository-level behavior and missed the visible product surface: navigation builders, public shell consistency, and table-level workflow affordances. The permission layer existed for most workflows, but the sidebar did not expose valid entry points and the listings table did not surface lifecycle actions.

## 6. Navigation Review

Navigation was reviewed through `NavigationHelper::sidebarSections()`, route existence checks, seeded role permissions, and Dusk mobile browsing. Storefront links now come from route existence plus permissions, with Quotes additionally gated by the storefront quotes feature flag.

## 7. Modules & Features

`Modules & Features` is now under System Settings for Superuser only. Direct GET, PUT, and preset POST actions abort with 403 for non-superusers even when a user has the seeded `manage_feature_flags` permission.

## 8. Storefront Sidebar

A Storefront sidebar section now exposes Overview, Listings, Push to Storefront, Orders, Quotes, Placements, Reports, Settings, and View Store only when the route exists and the user has the relevant permission. No menu-only access was granted.

## 9. Unified Shop Layout

The public shop layout now uses the Chipper Farms brand, logo, white application-style header/footer, consistent spacing, and guest-safe navigation. Back-office links are not exposed to guests.

## 10. Listings Actions

The listings index now includes an Actions column with View/Edit plus status-aware Publish, Unpublish, and Archive controls. Archived listings show `Restore unavailable` because the current listing lifecycle treats archived as terminal. No unsafe hard delete was added.

## 11. CRUD/Table Audit

Major operational tables were reviewed by route surface, feature tests, and smoke-test mapping. CRUD/workflow surfaces have route-backed actions; read-only/reporting tables are documented as intentional. The listing table defect that triggered this audit is fixed and regression-tested.

## 12. Pig Module Review

Pig stock, breeding stock, death, sale, mating, farrowing, weaning, weights, weight alerts, and lifecycle routes are covered by default PHPUnit and MariaDB runs. Critical pig workflow regression remained green.

## 13. Feed/Inventory Review

Feed issues, raw materials, feed production, purchase requests, purchase stock intakes, inventory value, GR/IR ageing, and opening balances are present in route and test coverage. Workflow actions include create, receive, approve, adjust, issue, and report surfaces as applicable.

## 14. Finance Review

Finance overview, money-in, expenses, contacts, invoices, payments, refunds, returns, reports, bank reconciliation, close, COA, tax, and posting services were covered in default and MariaDB suites. Finance navigation continues to be built from the feature-aware finance navigation service.

## 15. Storefront Review

Storefront browse, cart, checkout, guest checkout, COD, Paystack duplicate webhook, holds, listings, push workflow, orders, refunds, returns, quotes, settings, and reports are covered by feature tests, browser mobile coverage, and concurrency tests.

## 16. Settings Review

Modules & Features, general settings, setup, email settings, activity log, reports, and user management were checked through route/navigation review. QA-001 added a controller-level superuser guard for module flags.

## 17. Feature Flag Review

Feature flags remain authoritative for advanced surfaces. Quotes disappear from the Storefront sidebar when the storefront quotes feature is not usable. The public shop category nav uses enabled storefront catalog kinds.

## 18. Permission Review

Sidebar visibility follows role or permission rules but does not replace controller, route middleware, or policy checks. Storefront rows use policies for view/update actions and permission middleware remains on all storefront-admin route groups.

## 19. Organisation Isolation

Organisation-scoped feature service and existing organisation isolation tests remained green in default and MariaDB suites. Storefront and finance tests include multi-organisation price/visibility and boundary checks.

## 20. No-Dead-End Review

Known missing entry points were added. Storefront sidebar links are route-existence checked. Listings empty state provides a push link when the user has permission. Public shop guests see only guest-safe navigation.

## 21. Guest UX

Guest shop browsing, cart visibility, sign-in affordance, and no back-office link leakage were regression-tested.

## 22. Role Walkthrough

Superuser sees Modules & Features. Admin does not see Modules & Features but can see permitted Storefront items. Farm Manager sees permitted Storefront workflow links. Data Entry does not see Storefront when not granted storefront permissions. Read-only accountant listing actions are limited to View.

## 23. Responsive Review

Dusk checked the 390px viewport against dashboard, finance overview, expenses, money-in, contacts, storefront listings, storefront orders, public shop, all stock, and sales. Result: pass.

## 24. JavaScript/Console Review

Dusk browser run completed without application console/network failures in the tested critical pages. A PHP 8.4 deprecation surfaced during browser evidence and was remediated in `WeightAlert::acknowledge()`.

## 25. Browser Automation

`php artisan dusk tests/Browser/MobileViewportTest.php` passed against `APP_URL=http://127.0.0.1:8899` and `DB_DATABASE=chipper_farms_upgrade_test`.

## 26. Playwright/Dusk Changes

The mobile viewport Dusk screen list was corrected from stale `/storefront/orders` to `/storefront-admin/orders`, and expanded to `/storefront-admin/listings` plus `/shop/browse`.

## 27. PHPUnit Results

- Targeted QA regression: 18 tests, 75 assertions, passed.
- Default full suite: 1,785 passed, 13 skipped, 5,506 assertions, passed.
- Weight regression after PHP 8.4 fix: 10 tests, 44 assertions, passed.

## 28. MariaDB Results

- Targeted MariaDB regression: 28 tests, 119 assertions, passed.
- Full MariaDB suite: 1,798 tests, 5,516 assertions, 7 skipped, passed.

## 29. Concurrency Results

- `./vendor/bin/phpunit -c phpunit.concurrency.xml`: 15 tests, 270 assertions, passed against the real local MariaDB concurrency database.

## 30. Production-Cache Results

- `php artisan optimize`: passed; config, events, routes, and views cached successfully.
- Generated cache state was cleared after certification with `config:clear`, `route:clear`, and `view:clear`.
- `optimize:clear` against the normal `.env` database-backed cache was not run with escalation because it would mutate the local app database cache table.

## 31. NPM/Build

- `npm run build`: passed.
- `npm audit --audit-level=high`: found 0 vulnerabilities.
- Non-blocking warning: Browserslist/caniuse-lite data is stale.

## 32. Core Regression

Core farm, finance, storefront, feature, permission, weight, and concurrency regressions passed in the test gates above.

## 33. Smoke-Test Simulation

The smoke-test flow was mapped to tested coverage: shop/guest checkout, money-in and finance overview, failed posting visibility, feature-gated menu behavior, multi-farm storefront pricing, mobile viewport, duplicate Paystack webhook, and return/refund handling.

## 34. Open External Certifications

External live payment provider certification, production infrastructure monitoring, and human visual sign-off remain outside local automation.

## 35. Remaining Issues

No locally reproducible P0/P1 blockers remain from the human QA findings. See the issue register for remediated items and residual notes.

## 36. Final Pre-Smoke-Test Decision

HUMAN-QA REMEDIATION COMPLETE - READY TO RESTART MANUAL SMOKE TEST
