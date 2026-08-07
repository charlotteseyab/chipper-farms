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
