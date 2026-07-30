# Concurrency test suite

These tests exercise **real** concurrency: multiple operating-system processes,
each with its own database connection, contending for the same rows at the same
moment.

## Why a separate suite

The default suite runs on SQLite `:memory:` with a sync queue (see
`phpunit.xml`). That is fast and fine for logic, but it cannot demonstrate
anything about locking:

- an in-memory SQLite database is private to the process that opened it, so a
  second process cannot even see the same data;
- SQLite's locking model is not MySQL's — `SELECT ... FOR UPDATE` is a no-op
  there, so a row-lock bug passes silently;
- `RefreshDatabase` wraps each test in a transaction, which by construction
  hides the interleaving that concurrency bugs live in.

This is precisely the gap `CF-P1-017` describes: a green suite that cannot fail
for the reasons that matter in production.

The register also notes (`CF-P1-013`) that the existing
`NumberingServiceTest::test_concurrent_allocations_produce_unique_numbers` is
honest about being sequential — it says so in its own comment. Sequential calls
in one process are not concurrency evidence, so that test is left in place as a
monotonicity check and the concurrency claim is made here instead.

## Requirements

A reachable MySQL/MariaDB instance. These tests **fail** rather than skip when
one is absent, deliberately: a silent skip is how the original audit ended up
with no concurrency evidence at all.

## Running

```bash
CF_CONCURRENCY_DB_HOST=127.0.0.1 \
CF_CONCURRENCY_DB_PORT=3399 \
CF_CONCURRENCY_DB_DATABASE=chipper_farms_test \
CF_CONCURRENCY_DB_USERNAME=root \
CF_CONCURRENCY_DB_PASSWORD= \
vendor/bin/phpunit --testsuite=Concurrency
```

The suite migrates the target database itself on first use. Point it at a
throwaway database — it truncates the tables it works with.
