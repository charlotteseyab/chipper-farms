# Phase 18c — IMP-134, the schema could not be rolled back

## Document control

| Field | Value |
| --- | --- |
| Phase | 18c — Migration reversibility |
| Date | 2026-08-11 |
| Branch | `staging` |
| HEAD at phase start | `d7f2fb7` (Phase 18b) |
| Scope | IMP-134 — the last open item in the register |
| Phase status | **COMPLETE**. Register: 137 items, **137 closed** |

## 1. The defect

A bad deploy had no way back. `migrate:rollback` aborted part-way on MariaDB, and
once it had aborted the schema could go neither forward nor back.

The cause is that **`down()` methods are almost never exercised**. This
application's had only ever run on SQLite, which rebuilds a table when a column
is dropped and therefore tolerates dropping a column that still carries a foreign
key or an index. MariaDB refuses. Nothing had ever rolled the schema back on the
engine production uses, so nothing had ever noticed.

It surfaced by accident: the browser suite added in Phase 18b used
`DatabaseMigrations`, which rolls back after every test.

## 2. Two migrations, not fifty-seven

A static scan for fragile `down()` methods flagged **57** migrations —
unconditional column drops, unconditional index drops, columns dropped without
their foreign key.

**55 of those were false positives.** An unconditional drop is perfectly fine on
a clean rollback, where the thing being dropped is certainly there. Rewriting all
57 would have been a large, risky diff in exchange for almost nothing, and would
have buried the two that mattered.

So the fix was driven empirically instead: roll the whole schema back, fix
whatever actually failed, repeat until it reached zero.

| Migration | What was wrong |
| --- | --- |
| `2028_05_01_100000_extend_activity_logs_for_audit_trail` | Dropped `organization_id` while its foreign key and index still existed. MariaDB: *"Cannot drop index … needed in a foreign key constraint"* |
| `2025_09_16_120500_add_decision_fields_to_feed_purchase_requests` | Dropped `users.last_seen`, which a **later** migration had already dropped, aborting the entire rollback |

### 2.1 The second one is worth reading twice

That migration's name says `feed_purchase_requests`. It adds `users.last_seen`.

`2025_09_30_110724_add_last_seen_to_users_table` adds the **same column**, and
guards both its add and its drop. Rolling back runs the later one first, so by
the time the earlier one ran the column was already gone and everything stopped.

It is now guarded to match its twin. **The file name is deliberately left
alone**: the `migrations` table records it, and renaming the file would make
every deployed database treat it as a new migration and run it again.

### 2.2 A `try/catch` around `dropForeign()` catches nothing

Worth recording because the first attempt at the `activity_logs` fix made exactly
this mistake and still failed:

```php
Schema::table('activity_logs', function (Blueprint $table) {
    try {
        $table->dropForeign(['organization_id']);   // only QUEUES the command
    } catch (\Throwable $e) {
        // never reached
    }
});                                                  // the SQL runs HERE
```

Inside a `Schema::table()` closure, `dropForeign()` queues a command; the SQL
executes after the closure returns, outside the `try`. Both fixes therefore ask
the schema — `information_schema.TABLE_CONSTRAINTS` and `STATISTICS` — whether
the constraint or index is actually present.

## 3. Proof

```
$ scripts/testing/assert-migrations-reversible.sh

  1. migrate up          ran=210  failures=0
  2. reset to zero       ran=209  failures=0
  3. re-apply            ran=209  failures=0

REVERSIBILITY: PASS — the schema rolls back to zero and comes back up.
```

Rolling back is not enough on its own — a schema that goes down but will not come
back up is no better than one that will not go down. All three stages are
required to pass.

## 4. The durable check

`scripts/testing/assert-migrations-reversible.sh`.

**Not** in the per-commit suite: the only honest check is the real round trip,
which takes minutes and needs a database it is allowed to destroy. It belongs in
the release checklist beside the MariaDB gate. Saying so plainly is better than
adding a fast test that would only catch the two patterns already fixed and would
imply coverage it does not have.

It refuses to run against a database whose name does not contain `test` or
`dusk` — verified, exit 64 — for the reason recorded in IMP-135.

It also treats a stage that ran **nothing** as a failure, not a pass. That is the
IMP-096 lesson applied here: no output and success must never look alike.

### 4.1 Verified in both directions

| Defect reintroduced | Result |
| --- | --- |
| The `users.last_seen` guard removed | **Fails at stage 2**, naming the migration and the SQL error |
| Run against `chipper-farms` | **Refused**, exit 64, before touching anything |

## 5. Commands run and exact results

```
$ scripts/testing/assert-migrations-reversible.sh
PASS — 210 up, 209 to zero, 209 back up, 0 failures   # MariaDB 11.4.2

$ ./vendor/bin/phpunit
Tests: 1789, Assertions: 5312, Skipped: 13             # 0 failures, 0 errors

$ bash scripts/testing/run-mariadb-tests.sh            # MariaDB 11.4.2, port 3399
Tests: 1789, Assertions: 5322, Skipped: 7              # 0 failures, 0 errors
```

## 6. Status

**IMP-134 closed. The register is 137 items, all 137 closed.**

The programme's remaining work is external and cannot be self-certified: the
§61 items, of which the binding one is that **Ghana tax rates must not go live
without accountant/GRA confirmation.**
