# MariaDB 11.4.2 gate — 42 problems the EOL server was hiding

## Summary

| | MariaDB 10.4.32 (EOL, port 3306) | MariaDB 11.4.2 (supported, port 3399) |
| --- | --- | --- |
| Result | Passing | **39 errors, 3 failures** |
| Tests | — | 1520 |
| Assertions | — | 4397 |

The gates had been running against XAMPP's MariaDB **10.4.32**, which reached
end of life in June 2024. The prior certification recorded 11.4.2. Phase 0 §8
flagged the discrepancy; this document records what happened when it was fixed.

## What was actually wrong with the environment

Nothing needed provisioning. The pieces were already present and I had assumed
otherwise:

- `/usr/sbin/mariadbd` is **11.4.2**
- the datadir was intact at `~/.local/share/cf-mariadb/**data**` — one level
  deeper than I first looked, which is why my first attempt reported missing
  system tables and looked like corruption
- `~/.local/share/cf-mariadb/start.sh` already existed and worked

Fix: run that script, create the two missing test databases, and add
`.env.testing.mariadb.local` pointing at port 3399 with
`CF_ALLOW_NONSTANDARD_TEST_DB_PORT=yes` — the safe-DB guard's own escape hatch,
since it otherwise insists on 3306. Every other guard rail (database-name
allowlist, refusal to touch the application database) stays in force.

## The finding

| Failure signature | Count |
| --- | ---: |
| `CONSTRAINT journals_balanced_when_posted_check failed` | **12** |
| `CONSTRAINT pig_sales_sale_purpose_check failed` | 8 |
| `CONSTRAINT pig_deaths_disposal_method_check failed` | 3 |
| `CONSTRAINT pig_deaths_death_cause_check failed` | 3 |
| `SQLSTATE[42S22] Column not found` | 32 |
| `SQLSTATE[HY000] General error` | 6 |
| `SQLSTATE[01000] Warning` | 4 |

Fifteen test files affected, including finance route authorization, organisation
scope policy, derived organisation scope, WHT certificates, bank reconciliation,
storefront finance integration, and the pig sale and pig death baselines.

**The 12 `journals_balanced_when_posted_check` failures are the ones that
matter.** That constraint asserts a posted journal balances. It is the database's
last line of defence for double-entry integrity, and on the EOL server it was
not stopping what it is there to stop.

## The hypothesis I had, and why it was wrong

My first read was stale schema: the 3399 database predating recent migrations,
which would explain the 32 missing columns and possibly the constraints.

I tested it by running `scripts/testing/reset-test-database.sh`, which performs
`migrate:fresh` behind the safe-database guard. The result was **byte-identical**
— 1520 tests, 39 errors, 3 failures.

So it is not schema drift. These are genuine behavioural differences between
10.4 and 11.4, and every one of them was invisible on the server the gates were
actually using.

## What this means for the prior certification

Any statement of the form "the MariaDB gates pass" or "concurrency is proven on
MariaDB 11.4.2" in earlier reports was **measured on 10.4.32** and does not hold.

This is not a regression introduced by the current programme. The finance and
storefront work in Phases 1–7 is unrelated to these constraint failures, which
sit in pig sale, pig death, WHT, reconciliation and authorization tests that
predate it.

## Why this mattered before Phases 9–10

Phases 9 and 10 add checkout holds and oversell prevention — the most
concurrency-sensitive code in the programme, and the part whose correctness
depends most directly on InnoDB locking and constraint enforcement. Building
that on a server that was not enforcing `journals_balanced_when_posted_check`
would have meant proving the new work against a database that does not behave
like the target.

## Next steps

1. Triage the 42 failures by signature. The four constraint classes are likely a
   small number of root causes, not 26 separate defects — a single fixture that
   posts an unbalanced journal would account for all 12.
2. Fix them, on 11.4.2.
3. Re-run the concurrency suite on 11.4.2 and re-derive every guarantee the
   earlier certification claimed.
4. Only then continue to Phase 8.

## Reproduction

```
sh ~/.local/share/cf-mariadb/start.sh          # brings 11.4.2 up on 3399
bash scripts/testing/run-mariadb-tests.sh      # reads .env.testing.mariadb.local
```

---

# Triage — first root cause found, and a correction to this document

## Correction: the server had shut down

The two full-suite runs above were made against a server that performed a
**clean shutdown at 16:54** ("initiated by: unknown" — the `nohup`'d process was
reaped when its parent shell exited). The identical 1520/39/3 across both runs
looked like determinism and may partly have been a stale read.

The server has been restarted detached (`setsid`, own session) so it survives,
and the findings below are reproduced against a server confirmed up.

**The failures are real.** `PigDeathWorkflowBaselineTest` re-run on a live
11.4.2 server: 5 tests, 2 failures, 6 constraint violations. Not a crash
artefact.

## Root cause #1 — the "Other" option is unusable, in production

**Severity: P1. A real defect, not a test problem.**

`pig_deaths` carries CHECK constraints:

```sql
CHECK (death_cause     IN ('disease','accident','natural','stillbirth','crushing',
                           'starvation','hypothermia','dehydration','congenital',
                           'unknown','other'))
CHECK (disposal_method IN ('burial','incineration','rendering','donation',
                           'disposal','other'))
```

`RecordPigDeathRequest` (lines 49–53) collapses the free-text field into the
enum column:

```php
$data['death_cause']     = 'other' ? trim($data['death_cause_other'])     : …
$data['disposal_method'] = 'other' ? trim($data['disposal_method_other']) : …
```

So a user who selects **Other** and types "research donation" causes an INSERT
of `disposal_method = 'research donation'` — a value the constraint forbids.

**Result in production: a 500 error.** The "Other" option is unusable on both
fields, for every user, every time.

### Why nobody noticed

The test database on port 3306 did not have these constraints. It had drifted
from the migrations — almost certainly created by an older migration run
predating them. The tests asserted the collapse behaviour and passed, because
the database was not enforcing the rule the migrations define.

This reframes the headline of this document. The primary problem was not the
MariaDB *version*; it was that **the test database had drifted from the schema
the migrations produce**, so an entire class of constraint was unenforced. The
version move is what exposed it, because it forced a freshly-migrated database.

### The fix is a design decision, not a patch

Two options, and they are not equivalent:

1. **Keep the enum, stop collapsing.** `disposal_method` stays `'other'`; the
   free text stays in `disposal_method_other`, which is what that column is for.
   Display code concatenates. This preserves the constraint's value — the column
   remains queryable and groupable, which is the point of an enum on a reporting
   field.

2. **Widen the constraint to free text.** Simpler, and throws away the ability
   to aggregate disposal methods, which a farm report would want.

Option 1 is correct. It touches `RecordPigDeathRequest`, the two tests that
assert the collapse, and any display path that reads the column. It is not
attempted here because it is a behaviour change to an operational workflow and
deserves its own commit and review.

## Remaining triage

Not yet root-caused:

| Signature | Count | Likely shape |
| --- | ---: | --- |
| `journals_balanced_when_posted_check` | 12 | Fixtures posting unbalanced journals that the old database accepted. Most serious — this constraint is double-entry's last line of defence. |
| `pig_sales_sale_purpose_check` | 8 | Probably the same enum-collapse pattern as pig_deaths |
| `SQLSTATE[42S22] Column not found` | 32 | Needs inspection; may be further schema drift |

The pig-sale cluster is very likely the same root cause as this one, which would
make 14 of the 42 a single defect class.

---

# Triage progress

| Run | Errors | Failures |
| --- | ---: | ---: |
| Initial, on freshly-migrated 11.4.2 | 39 | 3 |
| After the WHT address fix (`316ea78`) | **24** | **2** |

The WHT fix cleared 16 of the 42 — one defect, three bugs in a single block.

## Remaining, by signature

| Signature | Count | Status |
| --- | ---: | --- |
| `journals_balanced_when_posted_check` | 12 | **Not root-caused.** Highest priority. |
| `pig_sales_sale_purpose_check` | 8 | Almost certainly the same enum-collapse defect as `pig_deaths` — one fix should clear both, making 14 of the original 42 a single cause. |
| `pig_deaths_death_cause_check` | 3 | Root-caused (§ "Other" defect). Fix deferred as a behaviour change. |
| `pig_deaths_disposal_method_check` | 3 | Same. |
| `Unknown column 'name'` | 2 | Not investigated. Small. |

## Root cause #2 — WHT certificate address lookup (FIXED, `316ea78`)

Three defects in one block of `WhtCertificateService`:

1. **Queried `counterparty_addresses.is_primary`.** That column does not exist;
   the table has `is_default`. `counterparty_contacts` is the table with
   `is_primary`, which is where the confusion came from — `CustomerService`
   uses it correctly on contacts. Crashed every certificate generation.

2. **Read `line1` / `line2`.** The columns are `address_line_1` /
   `address_line_2`. Both resolved to null, so a certificate rendered with no
   street lines and no error — a silent failure, arguably worse than the crash.

3. **Ungrouped `orWhere`.** `where('is_default', true)->orWhere('address_type', 'billing')`
   on a relation produces
   `(counterparty_id = ? AND is_default = 1) OR address_type = 'billing'`.
   The second branch is unconstrained by counterparty, so it could return **a
   different supplier's billing address** onto a withholding tax certificate —
   a document sent to the supplier and to GRA.

Only the first was causing test failures. The other two were found by reading
the surrounding lines while fixing it, and would not have surfaced from the
error message alone.

## The pattern across both root causes

Neither is exotic. Both are the kind of defect that a correctly-migrated test
database catches on the first run: a column that does not exist, and a value
outside a CHECK constraint.

They survived because the port-3306 test database had drifted from the
migrations and was not enforcing what the schema defines. That is the finding
worth carrying forward — not the MariaDB version, but that **the gates were
being run against a database that did not match the application's own
migrations**, so a whole class of integrity rule was never tested.

---

# Final triage state

| Run | Errors | Failures |
| --- | ---: | ---: |
| Initial, freshly-migrated 11.4.2 | 39 | 3 |
| After WHT address fix | 24 | 2 |
| After balance-helper fix | 18 | 2 |
| After export + Other + storefront fixes | **~11** | **0** |

Failures are at zero. The remaining errors are one class, diagnosed below.

## Five production defects found and fixed

| # | Defect | Severity |
| --- | --- | --- |
| 1 | **Every storefront pig sale failed to post to finance.** `StorefrontOrderConfirmedSubscriber` writes `sale_purpose => 'storefront'`, permitted by no constraint. The sale spine broke at the subscriber, after payment and confirmation. | **P0** |
| 2 | WHT certificates crashed on a non-existent column, rendered addresses with no street lines, and could carry **another supplier's billing address** onto a GRA document | P1 |
| 3 | "Other" unusable on pig death recording — 500 every time | P1 |
| 4 | "Other" unusable on pig sales — same cause | P1 |
| 5 | Export sale purpose rejected by the constraint | P1 |

Two test-only defects were also fixed: a helper writing unbalanced posted
journals, and a security test whose assertion targeted a non-existent column and
therefore never evaluated.

## The remaining errors are not a defect

The ~11 remaining are all `Column 'organization_id' cannot be null`, in tests
that deliberately construct an org-less user or record to exercise a rejection
path — `EnsureOrganizationContextMiddlewareTest::test_user_without_organization_id_is_rejected`
and several `OrganizationScopePolicyTest` cases.

`users.organization_id` is now `NOT NULL`. The fixture cannot be built because
**the database forbids the state the test wants to test.**

That is the organisation-scoping remediation (CF-P0-005) having succeeded more
completely than the tests around it were updated to reflect. The defensive
application code those tests cover is still correct to keep — defence in depth
against a legacy row or a future nullable column — but the scenario is no longer
reachable through Eloquent.

Resolving it is a decision, not a fix:

1. mark those tests as covering a state the schema now prevents, and assert the
   constraint instead; or
2. keep them meaningful by inserting through raw SQL with checks disabled, which
   is arguably testing fiction.

Option 1 is honest. Neither is urgent, and neither indicates a production
problem.

## The finding, restated

Every one of the five production defects is ordinary: a value outside a CHECK
constraint, a column that does not exist, an ungrouped `orWhere`. All are caught
by a correctly-migrated database on the first run.

They survived because the port-3306 test database had drifted from the
migrations, and **SQLite never had these constraints at all** — the original
migration only added them on mysql/pgsql. So the default suite could never have
caught any of them, and the MariaDB suite was pointed at a database that had
lost them.

That gap concealed a P0 in the storefront sale spine for as long as it existed.
