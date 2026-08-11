# Phase 17 — Local production certification (§60)

## Document control

| Field | Value |
| --- | --- |
| Phase | 17 — Local production certification |
| Date | 2026-08-11 |
| Branch | `staging` |
| HEAD at phase start | `238c483` (Phase 16) |
| Blueprint anchor | §60 (complete testing strategy) |
| Depends on | Phase 16 — COMPLETE |
| Phase status | **COMPLETE for everything verifiable locally.** The Mobile row is not done — see §5 |

## 1. The audit

§60 lists fourteen areas that must be tested before certification. Twelve were
already covered by the phases that built them. Two were not.

| §60 area | Covered by | Verdict |
| --- | --- | --- |
| Feature flags | `FeatureRouteGateTest`, `ModulePresetTest`, `OrganizationFeatureServiceTest`, `AdvancedFeatureGatingTest`, `FinanceSidebarTest` | ✅ |
| Quick expense | `QuickExpenseTest`, `QuickExpenseAttachmentTest` | ✅ |
| Simple purchase | `SimplePurchaseTest`, `SimplePurchaseScreenTest` | ✅ |
| Tax | `GhanaTaxProfileTest`, `StorefrontTaxTest`, `ReturnTaxReversalTest` | ✅ |
| Dashboard | `FinanceOverviewScreenTest`, `FinanceSummaryTest`, `NeedsAttentionTest` | ✅ |
| Cash transfers | `CashTransferTest` | ✅ |
| Guest checkout | `GuestCheckoutTest`, `CartCheckoutTest` | ✅ |
| Holds | `HoldServiceTest`, `HoldConcurrencyTest` | ✅ |
| **Concurrency — duplicate Paystack webhook** | **nothing** | **built here** |
| Refunds | `RefundExecutionTest`, `RefundHardeningTest`, `RefundServiceTest` | ✅ |
| Storefront finance | `FinanceIntegrationTest`, `StorefrontFinanceBoundaryTest` | ✅ |
| Permissions | `PermissionMatrixTest`, `FinanceRouteAuthorizationTest` | ✅ |
| **Mobile** | **nothing** | **partially addressed — §5** |
| MariaDB | `scripts/testing/run-mariadb-tests.sh` | ✅ |

## 2. Duplicate Paystack webhook

§60 names this beside simultaneous checkout and confirmation, and nothing tested
it. Signature rejection, refund events and the happy path were all covered;
re-delivery was not.

This is routine, not exotic. Paystack retries a webhook until it receives a 2xx
and re-delivers on its own schedule besides, so the same `charge.success` for the
same reference arrives more than once as a matter of course.

### 2.1 Three guards, none of them locked

The path has an idempotent check at three levels — `PaystackService::verify()`,
`PaymentService::markPaystackVerified()`, and
`OrderService::markPaymentConfirmed()`. Every one is a **read-then-act with no
lock between the read and the write**, so two overlapping deliveries can pass all
three.

Both halves are now tested. `DuplicateWebhookTest` covers sequential
re-delivery — the common case, and safe. `DuplicateWebhookConcurrencyTest` runs
six real processes delivering the same charge at once, on MariaDB, and it is
also safe.

### 2.2 The concurrency test is a genuine control

Phase 10 learned that a concurrency test can pass for reasons unrelated to the
control being tested. So the guards were removed and the test re-run:

```
The order's timeline records 2 payment entries for one charge, so the audit
trail claims the customer paid more than once.
Expected '1', got '2'
```

Note which assertion caught it. The count of *verified payments* stayed at one,
because there is only one payment row and it is updated rather than inserted —
so an assertion on that alone would have passed against a double-processing
implementation. The order's timeline is what actually records the double work.

## 3. §55 — classes chosen at runtime were never generated

Five templates build class names by interpolation:

```blade
bg-{{ $row['weighingStatus']['color'] }}-100
```

Tailwind's scanner reads source files as text and looks for complete class
names. It never sees these, so it generates nothing, and the element renders
**correctly classed and entirely unstyled**. No error is raised anywhere.

This is difficult to catch any other way: it is not a layout fault, so a viewport
review may not notice it; a markup assertion passes because the class attribute
is exactly what the template says; and nothing throws. The colour is simply
absent.

### 3.1 Proved against the compiled stylesheet, not inferred

The fix is a safelist in `tailwind.config.js` covering the eight colour names the
controllers actually emit and the utility/shade pairs the views interpolate. It
was verified by building the CSS both ways:

| | `bg-pink-100` | `bg-orange-100` | `text-purple-600` |
| --- | --- | --- | --- |
| with safelist | present | present | present |
| without safelist | **MISSING** | present | present |

`bg-pink-100` is the female-pig indicator on the weight standards screen. It had
no background colour at all.

The other two survive without the safelist only because some *other* template
spells them out literally — which is exactly why checking one class, or reasoning
about it rather than building it, would have been misleading.

### 3.2 The guard

`ResponsiveClassSafetyTest` sweeps every Blade template for interpolated colour
classes and fails when one uses a utility/shade pair the safelist does not cover,
naming the file and line. It reads the pattern out of `tailwind.config.js` rather
than restating it, so the two cannot drift.

Narrowing the safelist by one shade makes it fail with the five exact locations,
which is the reverse check.

## 4. The concurrency suite, and a README that was wrong

The full suite runs green on MariaDB 11.4.2:

```
OK (14 tests, 256 assertions)
```

`tests/Concurrency/README.md` gave `chipper_farms_test` as the database name.
`assertSafeDatabaseSelection()` requires `chipper_farms_concurrency_test`
precisely so this suite can never be pointed at the application database and
truncate it — so following the README produced a confusing failure. Corrected,
with a note explaining why the name is not free-form.

## 5. What is NOT done

**§60's Mobile row — "browser/screenshot checks for key flows" — has not been
done.** Laravel Dusk is not installed, and adding a browser-driver harness is a
larger change than this phase warrants; Chromium is present on the machine but
nothing is wired to drive it.

§3 above is not a substitute. It catches a real defect class that screenshots
would probably have missed, but it says nothing about whether a checkout screen
is usable on a 390px viewport. That remains **IMP-114, open**, with the scope
now stated precisely: a viewport review of the older ledger and dashboard
screens, which predate this programme's mobile-first work.

§61's external certification items are all still external by nature — Ghana tax
confirmation, Paystack sandbox runs, supervised queue/scheduler, storage and
signed downloads, and both UAT tracks. None can be self-certified here, and this
phase does not claim them.

## 6. Commands run and exact results

```
$ ./vendor/bin/phpunit
Tests: 1778, Assertions: 5287, Skipped: 13         # 0 failures, 0 errors

$ CF_CONCURRENCY_DB_HOST=127.0.0.1 CF_CONCURRENCY_DB_PORT=3399 \
  CF_CONCURRENCY_DB_DATABASE=chipper_farms_concurrency_test \
  CF_CONCURRENCY_DB_USERNAME=root CF_CONCURRENCY_DB_PASSWORD= \
  ./vendor/bin/phpunit -c phpunit.concurrency.xml
OK (14 tests, 256 assertions)                      # MariaDB 11.4.2

$ npx tailwindcss -i resources/css/app.css -o /tmp/tw-out.css
Done in 4052ms.                                    # safelisted classes verified present

$ bash scripts/testing/run-mariadb-tests.sh        # MariaDB 11.4.2, port 3399
Tests: 1778, Assertions: 5297, Skipped: 7          # 0 failures, 0 errors
```

No migrations.

## 7. Status

**Phase 17: COMPLETE for everything verifiable on this machine.** Thirteen of
§60's fourteen rows are covered and green on both engines plus real
multi-process concurrency. The Mobile row is named as not done rather than
quietly counted.

Phase 18 is the final implementation review (§63, §64).
