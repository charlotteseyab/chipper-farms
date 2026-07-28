# Phase 1 — Supported Platform and Dependency Baseline

**Date:** 28 July 2026
**Branch:** `remediation/chipper-farms-production-go`
**Findings addressed:** `CF-P0-011`, `CF-P1-021`, `CF-NEW-026`, dependency portion of `CF-P0-012`
**Phase verdict:** PASS — all exit criteria met, zero test regressions.

---

## 1. Phase Objective

Move the application onto a currently supported framework release and clear the
dependency advisory backlog, so that every later phase generates its evidence on
the framework the system will actually ship with.

---

## 2. Framework Upgrade (CF-P0-011)

### 2.1 Root cause

`composer.json` pinned `laravel/framework: ^11.31`, resolving to v11.45.1.
Laravel 11 left security support on **12 March 2026**; the assessment date is
**28 July 2026**.

### 2.2 Choosing the target — evidence, not assumption

The support policy was fetched from the official release notes at implementation
time rather than assumed:

| Version | Release | Bug fixes until | Security fixes until | PHP |
|---|---|---|---|---|
| 11 | 12 Mar 2024 | 3 Sep 2025 | **12 Mar 2026** (expired) | 8.2–8.4 |
| 12 | 24 Feb 2025 | **13 Aug 2026** | 24 Feb 2027 | 8.2–8.5 |
| 13 | 17 Mar 2026 | Q3 2027 | 17 Mar 2028 | 8.3–8.5 |

**Laravel 12 was rejected.** It leaves bug-fix support on 13 August 2026 — 16
days after this work — and security support in ~7 months. Launching a new
production system onto a branch that stops receiving bug fixes within a
fortnight would satisfy the letter of CF-P0-011 while defeating its purpose.

**Laravel 13 was selected.** Bug fixes to Q3 2027, security to March 2028. Its
minimum PHP is 8.3; the host runs **8.4.22**, so no runtime change was needed.

### 2.3 What changed

| Package | From | To | Note |
|---|---|---|---|
| `php` (constraint) | `^8.2` | `^8.3` | L13 minimum |
| `laravel/framework` | v11.45.1 | **v13.23.0** | two majors |
| `laravel/jetstream` | v5.3.8 | v5.5.3 | |
| `laravel/sanctum` | v4.2.0 | v4.3.3 | |
| `laravel/tinker` | v2.9 → | v3.0.2 | major |
| `phpunit/phpunit` | 11.5.27 | 13.2.6 | two majors |
| `livewire/livewire` | v3.6.4 | v3.8.2 | **minor only** |
| `symfony/http-foundation` | v7.3.1 | v8.1.1 | clears CVE-2025-64500 |

The single most important outcome for risk: **Composer resolved Livewire within
3.x**. A forced Livewire 4 migration would have touched the Blade/Livewire
template layer across 290 views. It was not required, so the entire presentation
layer was left untouched.

`--ignore-platform-reqs` was **not** used at any point.

### 2.4 The one real breakage, and how it was fixed

The first full run on Laravel 13 produced:

```
Tests: 5 failed, 13 skipped, 1141 passed (3422 assertions)
```

All five failures shared one root cause and one file:

```
ArgumentCountError: Too few arguments to function
Tests\Feature\Policies\PigSalePolicyTest::test_export_role_matrix(),
0 passed ... and exactly 2 expected
```

**PHPUnit 13 removed support for the `/** @dataProvider */` doc-comment
annotation.** The provider was silently not invoked, so each test method was
called with zero arguments.

A repository-wide sweep confirmed the blast radius was exactly one file:

| Annotation | Occurrences |
|---|---:|
| `@dataProvider` | 5 (all in `tests/Feature/Policies/PigSalePolicyTest.php`) |
| `@test`, `@depends`, `@group`, `@covers`, `@testWith`, `@requires`, `@backupGlobals` | 0 |

**Correction:** converted each to the `#[DataProvider('...')]` attribute and
added the `PHPUnit\Framework\Attributes\DataProvider` import. The provider
methods were already `public static`, so they were already v13-compatible.

This is a genuine forward-port, not a workaround. No assertion was relaxed, no
test was skipped or deleted, and all 20 data-set cases (5 methods × 4 roles)
still execute and still assert the same role matrix.

### 2.5 Result — exact baseline parity

```
php artisan test
Tests:    13 skipped, 1161 passed (3442 assertions)
Duration: 182.38s
```

| Metric | Pre-upgrade baseline | Post-upgrade | Delta |
|---|---:|---:|:--:|
| Passed | 1,161 | 1,161 | 0 |
| Skipped | 13 | 13 | 0 |
| Assertions | 3,442 | 3,442 | 0 |
| Routes | 472 | 472 | 0 |

Arithmetic check on the intermediate failure state: 1,141 passed + 20 recovered
data-set cases = 1,161. The recovery is fully accounted for; nothing was masked.

**No new test skip was introduced.** The skip count is unchanged at 13.

---

## 3. Composer Advisories (CF-NEW-026)

`composer audit` was blocked during the original audit and became runnable in
Phase 0, where it reported 36 advisories across 14 packages (4 high).

After the upgrade:

```
composer audit
No security vulnerability advisories found.
```

| | Before | After |
|---|---:|---:|
| Packages affected | 14 | **0** |
| Advisories | 36 | **0** |
| High severity | 4 | **0** |

The two that mattered most for this system:

- `symfony/http-foundation` **CVE-2025-64500** — incorrect `PATH_INFO` parsing
  enabling *limited authentication bypass*. This directly compounded the Phase 2
  broken-access-control findings; clearing it removes a framework-level
  amplifier of those defects.
- `symfony/mime` **CVE-2026-45067** — email header / SMTP command injection.

---

## 4. NPM Advisories (CF-P1-021)

### 4.1 Root cause

The lockfile pinned build tooling predating published advisories. Baseline:
13 advisories (1 critical, 10 high, 2 moderate).

### 4.2 Controlled remediation — no `--force`

`npm audit fix` (plain, as the brief requires) resolved **9 of 13**.

The remaining four — `brace-expansion`, `minimatch`, `glob`, `sucrase` — were
not four problems. They were **one** upstream defect reached through a single
chain:

```
tailwindcss@3.4.17 → sucrase@3.35.0 → glob@10.5.0 → minimatch@9.0.9 → brace-expansion@2.1.3
                                                                       └─ advisory: <= 5.0.7 (unbounded-expansion DoS)
```

`npm audit fix` could not resolve it because Tailwind 3.4.17 is the last 3.x
release and only Tailwind **4** drops `sucrase` — a major upgrade that would
have meant reworking utility classes across 290 Blade views.

**Correction:** a surgical `overrides` entry in `package.json`:

```json
"overrides": { "brace-expansion": "^5.0.8" }
```

This resolves the advisory at its root without the Tailwind major upgrade.

### 4.3 Result — and the build was verified, not assumed

```
npm audit --audit-level=low   → found 0 vulnerabilities   (was 13)

npm run build
vite v6.4.3 building for production...
✓ 56 modules transformed.
public/build/manifest.json   0.27 kB
public/build/assets/app-*.css  105.15 kB
public/build/assets/app-*.js    48.01 kB
✓ built in 4.88s
```

An override that silences an auditor but breaks the build is worse than the
advisory. The production build was re-run from scratch (`rm -rf public/build`)
and verified to produce a valid manifest.

### 4.4 Honest severity note

**All 13 advisories were in `devDependencies`** — build tooling, never shipped
to production runtime. The single "critical" (`shell-quote`) sat beneath
`concurrently`, a developer task runner. They were worth fixing and are fixed,
but the register's implied production exposure was overstated. This is recorded
as residual-risk context rather than being claimed as a larger win than it was.

---

## 5. Configuration Safety Review (§7.4)

`.env.example` was still the stock Laravel skeleton. Findings and corrections:

| Issue | Correction |
|---|---|
| **Every required Paystack key undocumented** — app reads 5 `PAYSTACK_*` keys from `config/services.php`; none appeared in the template | Added all 5 with placeholder values and a "use test keys / never commit real keys" warning |
| `FINANCE_ALERT_EMAIL` undocumented — read by `config/finance.php` and used by scheduled finance job failure alerts | Added with explanatory comment |
| `APP_NAME=Laravel` | → `"Chipper Farms"` |
| `APP_DEBUG=true` with no warning | Retained for local default, with an explicit MUST-be-false-in-production comment |
| `DB_CONNECTION=sqlite` while production targets MySQL | → `mysql`, with a note that SQLite does not reproduce the locking/decimal/isolation behaviour the finance and storefront paths depend on |
| No secure-cookie settings | Added `SESSION_SECURE_COOKIE`, `SESSION_SAME_SITE` |

The missing Paystack documentation is a genuine operational defect, not
cosmetic: an operator deploying from `.env.example` would get a silently
non-functional payment system whose only symptom is a runtime
`PaymentException: Paystack secret key is not configured`.

**No secrets were placed in `.env.example`** — placeholders only.

---

## 6. Commands Executed and Results

| Command | Result |
|---|---|
| `composer validate --no-check-publish` | `./composer.json is valid` |
| `composer install` (from lockfile) | Success (see §8 note) |
| `php artisan about` | Laravel **13.23.0**, PHP 8.4.22 |
| `php artisan route:list --except-vendor` | **472 routes** (unchanged) |
| `php artisan event:list` | Observers intact |
| `php artisan config:cache` | OK |
| `php artisan route:cache` | OK |
| `php artisan view:cache` | OK |
| `php artisan event:cache` | OK |
| `php artisan optimize:clear` | OK — **this failed in the original audit** |
| `php artisan test` | 1,161 passed / 13 skipped / 3,442 assertions |
| `php artisan migrate:fresh` (MariaDB 11.4.2) | **188/188 migrations** |
| `composer audit` | 0 advisories |
| `npm ci` / `npm run build` | Success, valid manifest |
| `npm audit --audit-level=low` | 0 vulnerabilities |
| `php -l` over app/bootstrap/config/database/routes/tests | **942 files, 0 errors** |

Note on the lint count: the original audit reported 944 files for the same
paths; this run counts 942. No application file was deleted in this phase — the
difference predates it and is recorded rather than papered over.

`php artisan optimize:clear` is worth calling out: it was listed among the
original audit's *failed* commands because it required the database cache store
that MySQL was unavailable to serve. With the Phase 0 MariaDB instance it now
succeeds.

---

## 7. Failures Encountered and Corrections Made

1. **5 test failures on first Laravel 13 run** — PHPUnit 13 dropped
   `@dataProvider` annotations. Fixed by attribute conversion (§2.4). Re-run:
   full parity.
2. **Composer dist downloads failed repeatedly** — `api.github.com` returned
   auth failures and curl error 28 (SSL/connection timeouts), leaving the lock
   at 13.23.0 while `vendor/` still held 11.45.1. Resolved by re-running with
   `--prefer-source`, which cloned git mirrors for ~74 packages. Slow
   (~50 min) but successful. This is an environment constraint, not an
   application defect.

---

## 8. Exit Criteria

| Criterion | Result |
|---|---|
| Application uses a currently supported Laravel release | ✅ 13.23.0 (security to Mar 2028) |
| Composer install succeeds from the lockfile | ✅ |
| Application bootstrap succeeds | ✅ `php artisan about` |
| Config, routes and views cache successfully | ✅ all four, plus `optimize:clear` |
| Frontend build succeeds | ✅ Vite 6.4.3, valid manifest |
| No unresolved critical/high production advisory without a documented decision | ✅ Composer 0, npm 0 |
| Full existing test suite passes | ✅ 1,161 / 13 skipped — exact parity |
| No new test skip added | ✅ skip count unchanged at 13 |
| Tracker and phase report updated | ✅ |

**Phase 1 verdict: PASS.**

---

## 9. Residual Risks Carried Forward

1. **Livewire remains on 3.x.** Supported and advisory-free today; a Livewire 4
   migration is a separate scoped effort, not a release blocker.
2. **Deprecations not surfaced by the suite.** The test suite exercises a large
   surface but not every path. Laravel 13 runtime deprecation warnings should be
   watched in staging logs.
3. **No dependency audit gate yet.** Both audits are clean *now*; without the
   Phase 8 CI gate they will drift. `CF-NEW-026` cannot close as fully durable
   until that gate exists.
4. **`CF-P0-012` remains open** — only its dependency portion is satisfied here.
   Its database and operational portions belong to Phases 3 and 8.
