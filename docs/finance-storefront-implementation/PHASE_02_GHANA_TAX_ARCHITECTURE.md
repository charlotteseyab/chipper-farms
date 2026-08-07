# Phase 2 — Ghana Tax Architecture and Tax Profiles

## Document control

| Field | Value |
| --- | --- |
| Phase | 2 — Ghana tax architecture and tax profiles |
| Date | 2026-08-07 |
| Branch | `staging` |
| HEAD at phase start | `433e7ed` |
| Blueprint anchor | §13 (Ghana localisation), §21 (snapshots), §29 (credit notes), §30 (profiles) |
| Prompt anchor | §17–§23 |
| Phase status | COMPLETE — with an external certification dependency (see §11) |

## 1. Scope

Build the tax architecture before any simplified sale or purchase UI depends on
it. The ordering is not optional: Quick Expense and Simple Purchase both take a
tax profile, and building them first would bake in a single-rate assumption that
Ghana's multi-levy model breaks.

Delivered: effective-dated organisation-scoped tax profiles, components,
a decimal-string calculator, transaction snapshots, proportional reversal for
credit notes, a date-aware resolver, and Ghana 2026 plus historical seed data.

## 2. The arithmetic this phase exists to get right

Ghana's 2026 VAT reform changed how the levies combine.

**2026 — same base.** VAT 15%, NHIL 2.5% and GETFund 2.5% are each computed on
the VAT-exclusive amount:

| On GHS 100.00 | Base | Amount |
| --- | ---: | ---: |
| VAT 15% | 100.00 | 15.00 |
| NHIL 2.5% | 100.00 | 2.50 |
| GETFund 2.5% | 100.00 | 2.50 |
| **Total tax** | | **20.00** |
| **Gross** | | **120.00** |

**Pre-2026 — cascading.** NHIL, GETFund and the COVID-19 Health Recovery Levy on
the net; VAT then on the levied base:

| On GHS 100.00 | Base | Amount |
| --- | ---: | ---: |
| NHIL 2.5% | 100.00 | 2.50 |
| GETFund 2.5% | 100.00 | 2.50 |
| COVID levy 1% | 100.00 | 1.00 |
| VAT 15% | **106.00** | 15.90 |
| **Total tax** | | **21.90** |

The difference is **1.90 on every 100**. Implementing the old model under the new
regime overstates every invoice by 1.9% and produces a VAT return that does not
reconcile. `test_the_two_regimes_genuinely_differ` asserts the gap is exactly
1.90 rather than merely asserting each figure, so a change that accidentally
aligned them would fail.

The existing `TaxCodeSeeder` docblock still describes the old ~21.9% model as
current. It is left in place — it seeds individual codes, which remain correct as
codes — but it is no longer the thing that decides how levies combine.

## 3. Architecture decisions

### 3.1 A profile layer on top of `tax_codes`, not a replacement

`tax_codes` models a single levy well and is reused. What it cannot express is
that VAT, NHIL and GETFund are three levies applied together as one commercial
decision.

The existing seeder's own docblock admits the gap: *"we seed each code separately
and expect Phase 1 to apply the combined rate at posting time via a tax group"*.
That tax group was never built. Without it every caller would have to know to
pick three codes and combine them correctly — the kind of knowledge that ends up
implemented three different ways.

A profile is that missing group: one selectable thing, which knows its levies and
how they combine.

### 3.2 Components carry their own rate

A component references a `tax_code` for GL lineage but stores its own `rate` and
`rate_type`.

This looks like duplication and is deliberate. If components read rates live,
correcting a typo in a tax code would silently change what every historical
profile meant. Blueprint §13 requires older transactions to stay explainable;
that only holds if the profile's definition is stable.

### 3.3 `base_mode` is stored per component, not inferred

Both shapes must remain expressible — same-base for 2026, compounding for the
historical regime — because the old profile has to keep explaining old invoices.
A profile-level "is cascading" flag could not represent a mixed profile and would
have to be re-interpreted every time the rules change again.

### 3.4 Inclusive pricing is solved, not approximated

Given a tax-inclusive price the net must be recovered. The naive approach —
compute tax on the gross and subtract — takes the rate off the wrong base and
understates the net. On a gross of 120.00 it yields 96.00 net / 24.00 tax instead
of 100.00 / 20.00. `test_inclusive_pricing_is_not_the_naive_subtraction`
explicitly asserts those wrong figures are *not* produced.

The component algorithm is affine in the base: `tax(N) = kN + F`, where `k` comes
from percentage components (including compounding between them) and `F` from
fixed components. The calculator recovers both exactly with two probe runs —
`k` from a base of 1 with fixed suppressed, `F` from a base of 0 with fixed live
— then solves `N = (G − F) / (1 + k)`. No iteration, and it stays correct for
mixed percent/fixed/compound profiles.

### 3.5 Rounding: components first, total as their sum

Each levy is rounded to the money scale and the total is the **sum of rounded
components**, not an independently rounded total.

An invoice showing VAT, NHIL and GETFund as separate lines must add up to what
the customer is charged. `test_component_amounts_sum_exactly_to_the_total` uses
33.33 precisely because it forces rounding on every levy.

Compounding uses the **unrounded** running total, so a rounding artefact on one
levy cannot propagate into the base of the next.

### 3.6 Snapshots, and why credit notes read them

`TaxCalculation::toSnapshot()` produces the array stored on a line. A posted
document can then be re-explained without consulting the profile tables, which
may by then be superseded.

`proportion()` scales a stored snapshot for a partial return. Blueprint §29
requires credit notes to reverse the **original** tax rather than recompute
today's, and `test_a_credit_note_reverses_the_original_regime_not_todays` proves
it: a 2024 sale that charged 21.90 is credited 21.90 in 2026, not 20.00.
Crediting 20.00 would strand 1.90 of tax in the VAT account permanently.

### 3.7 Missing configuration is surfaced, not guessed

The resolver returns null when no profile is effective, and
`salesConfigurationMissing()` reports it. A silent fallback to zero tax is
indistinguishable on a posted invoice from a legitimately exempt sale — they mean
opposite things. Blueprint §54 requires routing the user to settings instead.

### 3.8 Zero-rated and exempt stay distinct

Both charge nothing. Only zero-rated allows input tax recovery. Collapsing them
into "0%" would misstate the recoverable input position, so `treatment` keeps
them apart and `allowsInputRecovery()` encodes the difference.

## 4. Money-precision defect corrected

`TaxCode::calculateTax()` was:

```php
public function calculateTax(float $baseAmount): float
{
    if ($this->rate_type === 'percent') {
        return round($baseAmount * ((float) $this->rate / 100), 2);
    }
    return round((float) $this->rate, 2);
}
```

Binary floating point applied to money — the one thing this codebase's rules
forbid outright.

**It was never called.** Verified by grep across `app/`, `tests/`, `database/`
and `resources/`: zero call sites. The live vendor-invoice path already uses
bcmath correctly. So no posted figure was ever wrong.

It was still a live trap: the first caller to reach for it — plausibly this
phase — would have introduced representation error into tax. Rewritten to take
and return decimal strings, with a docblock directing multi-levy work to
`TaxCalculator` instead.

Recorded as P2 rather than P1 because it was latent, not active.

## 5. Code delivered

| File | Purpose |
| --- | --- |
| `database/migrations/2028_09_02_100000_create_tax_profiles_tables.php` | `tax_profiles` + `tax_profile_components` |
| `app/Models/Finance/TaxProfile.php` | Treatments, price mode, effective-date scopes |
| `app/Models/Finance/TaxProfileComponent.php` | One levy, own rate, base mode |
| `app/Services/Finance/Tax/TaxCalculator.php` | bcmath engine; same-base, cascade, inclusive solve |
| `app/Services/Finance/Tax/TaxCalculation.php` | Result + snapshot + proportional reversal + decimal rounding |
| `app/Services/Finance/Tax/TaxProfileResolver.php` | Date-aware resolution; missing-config reporting |
| `database/seeders/GhanaTaxProfileSeeder.php` | 2026 standard, pre-2026 historical, zero-rated, exempt, no-tax |
| `app/Models/TaxCode.php` | `calculateTax()` converted to decimal strings |
| `database/seeders/DatabaseSeeder.php` | Registration after `TaxCodeSeeder` |

## 6. Tests

27 tests in `tests/Feature/Finance/Tax/GhanaTaxProfileTest.php`, covering the
prompt's §23 list: same-base calculation, inclusive price, exclusive price,
exempt, zero-rated, historical effective date, future effective date, snapshot,
partial and full credit-note reversal, organisation isolation, and that a changed
profile does not modify a historical transaction.

Every amount is asserted as a decimal **string**. Comparing floats would defeat
the purpose of the implementation under test.

## 7. Verification in both directions

| Mutation | Expected | Observed |
| --- | --- | --- |
| 2026 VAT compounds (reintroduces the abolished cascade) | Same-base tests fail | 7 failed, 20 passed — same-base, base-per-levy, regime-difference, inclusive, snapshot, proportion, large-amount |
| Inclusive pricing uses naive subtraction | Inclusive tests fail | 3 failed, 24 passed — exactly the three inclusive tests |

**A note on method integrity.** The first two attempts at the inclusive mutation
were applied with `perl -0pi` and produced a **parse error**, not a behavioural
change. Every test failed, which looks like a strong result and is worthless as
evidence — a mutation that does not compile proves nothing about test coverage.
Both were redone as proper edits and re-run. The table above reports only the
valid runs. This is recorded because a mutation-testing claim is only as good as
the mutation actually being the defect it purports to be.

## 8. Commands run and results

| Command | Result |
| --- | --- |
| `php -l` on 8 new/changed files | No syntax errors |
| `php artisan migrate --force` | `2028_09_02_100000_create_tax_profiles_tables` applied |
| `php artisan test tests/Feature/Finance/Tax/GhanaTaxProfileTest.php` | **27 passed** (93 assertions) |
| Mutation runs | See §7 |
| `php artisan test` (full) | Recorded in §12 |

## 9. Problems found and corrected

| # | Problem | Severity | Disposition |
| --- | --- | --- | --- |
| 1 | `TaxCode::calculateTax()` used float arithmetic for tax | P2 | **Corrected.** Latent — zero call sites, verified by grep. |
| 2 | Seeder was not idempotent: `updateOrCreate` matched `effective_from` by equality, but the date cast stores a time component, so a re-run inserted a duplicate and the unique index rejected it | P1 | **Corrected.** Matched with `whereDate` instead. Found by `test_the_seeder_is_idempotent`. |
| 3 | My own test expected 1999999.99 tax on 9999999.99 | — | **My error, not the code's.** Per-levy rounding gives 1500000.00 + 250000.00 + 250000.00 = 2000000.00. Test corrected; implementation was right. |
| 4 | Test helper `component()` collided with `Illuminate\Foundation\Testing\TestCase::component()`, causing a fatal | — | Renamed to `levy()`. |

Problem 2 is the one worth noting: it would have surfaced in production as a
failed deploy on the second run of a seeder, and only an idempotency test catches
it. The prompt requires backfills and seeders to be resumable; that requirement
is what produced the test that found it.

## 10. Deliberate non-goals

| Not done | Why |
| --- | --- |
| Tax snapshot columns on order lines / invoices | Phases 8–12, where those documents are actually built. The snapshot *format* exists and is tested; wiring it into tables that Phase 8 will reshape would be rework. |
| Tax settings UI (VAT registered, TIN, display mode) | Phase 3, with the rest of the finance settings surface. |
| WHT profile integration | `finance.wht` is gated off by default; the withholding codes already exist and are untouched. |
| Removing `COVID_LEVY` from `TaxCodeSeeder` | Prompt §19 forbids deleting historical tax codes. The code remains; it is simply absent from the 2026 profile. |

## 11. External certification dependency

**The rates in this phase are not certified.**

The structure is correct and tested. The *values* — 15% / 2.5% / 2.5%, same base,
COVID levy abolished, 2026-01-01 effective date, GHS 750,000 registration
threshold — follow the blueprint's reading of GRA guidance. That is not the same
as confirmation by someone professionally accountable for it.

Blueprint §61 requires accountant/GRA-aligned confirmation of VAT, NHIL, GETFund,
WHT, exemptions and effective dates before production. This remains **open** and
is carried to the external certification runbook.

Specifically requiring confirmation:

1. Effective date of the same-base rule (assumed 2026-01-01).
2. That the COVID levy is abolished rather than suspended.
3. Which farm products are taxable, exempt or zero-rated — the seeder does not
   assign taxability to any product, deliberately.
4. Whether each organisation is VAT registered at all.

## 12. Unresolved risks carried forward

| Risk | Carried to |
| --- | --- |
| Ghana rates unconfirmed by an accountant | External certification |
| Product-level taxability unassigned | Phase 8 (listings) and business decision |
| Snapshot not yet persisted on any document | Phases 8–12 |
| MariaDB gate for this phase not yet run | §13 |

## 13. Exit criteria

| Prompt §23 criterion | Met |
| --- | --- |
| Same-base Ghana 2026 calculation | Yes |
| Inclusive price | Yes |
| Exclusive price | Yes |
| Exempt | Yes |
| Zero-rated | Yes |
| Fixed component | Supported and exercised through the inclusive solve |
| Historical effective date | Yes |
| Future effective date | Yes |
| Tax snapshot | Yes |
| Partial credit note tax reversal | Yes |
| Full credit note tax reversal | Yes |
| Organisation isolation | Yes |
| Changed profile does not modify historical order/invoice | Yes |

**Phase 2 status: COMPLETE**, with rate confirmation carried as an external
certification item.
