# Chipper Farms — Deployment Runbook

Addresses `CF-P0-012`, `CF-P1-022`, `CF-P2-025`.

This runbook assumes a single application host with MariaDB, Supervisor and
systemd available. Adapt the paths; do not adapt the ordering — several steps are
sequenced deliberately and the reasons are given inline.

---

## 1. Prerequisites

| Requirement | Version | Why this specific version |
|---|---|---|
| PHP | **8.4.1+** | Required by the locked Symfony 8 dependencies. Verify CLI and FPM versions before deployment. |
| MariaDB / MySQL | **MariaDB 11.4+ / MySQL 8.0+** | Row-level locking is load-bearing — order confirmation and document numbering rely on `SELECT ... FOR UPDATE`. SQLite cannot provide it. |
| Node | **20+** | Vite 6 build toolchain. |
| Composer | 2.x | |
| Supervisor | any | Queue workers (`deploy/supervisor/`). |
| systemd | any | Scheduler (`deploy/systemd/`). |
| logrotate | any | Rotates `/var/log/chipper-farms/*.log` (`deploy/logrotate/`). Without it the scheduler log grows forever. |

PHP extensions: `pdo_mysql`, `bcmath`, `gd`, `zip`, `mbstring`, `openssl`.

`bcmath` is not optional. Money and stock quantities are handled as
strings through `bcadd`/`bcsub`/`bccomp`; without it, arithmetic silently
falls back to float behaviour and inventory drifts.

---

## 2. Required environment variables

Start from `.env.example`, which documents every key the application reads.

**Must be set before first boot:**

```
APP_KEY=                 # php artisan key:generate
APP_ENV=production
APP_DEBUG=false          # see the warning below
APP_URL=https://…

DB_CONNECTION=mysql
DB_HOST= DB_PORT= DB_DATABASE= DB_USERNAME= DB_PASSWORD=

SESSION_SECURE_COOKIE=true    # if served over HTTPS
```

**Keep the server from filling up or hanging (October 2026 incident):**

```
LOG_STACK=daily          # or leave unset; "single" never rotates
LOG_DAILY_DAYS=14
LOG_LEVEL=warning        # "debug" in production multiplies log volume
MAIL_TIMEOUT=10          # seconds before a slow mail server is given up on
HEALTH_CHECK_TOKEN=…     # enables /health/metrics (disk, queue, outbox)
```

**Required for the storefront to function at all:**

```
PAYSTACK_SECRET_KEY=     # sk_live_… in production, sk_test_… everywhere else
PAYSTACK_PUBLIC_KEY=
PAYSTACK_CALLBACK_URL=
```

Without these, checkout and refunds raise
`PaymentException: Online payments are not currently available`. The application
starts perfectly and payments simply do not work — which is why they are called
out here rather than left to be discovered.

**Recommended:**

```
FINANCE_ALERT_EMAIL=     # scheduled finance job failure output
HEALTH_CHECK_TOKEN=      # unlocks detailed /health/ready and /health/metrics
```

### `APP_DEBUG=false` is a security control, not a preference

With debug enabled, an unhandled exception renders a stack trace including
environment variables, database credentials and query contents. Verify after
deploy:

```bash
php artisan about | grep -i debug     # expect: Debug Mode ... OFF
```

### Secret handling

- `.env` is in `.gitignore`; keep it that way.
- `.env.example` contains placeholders only — never real keys.
- Rotate `APP_KEY` only with a plan: it decrypts existing session and encrypted
  column data. Rotating it invalidates every session and makes previously
  encrypted values unreadable.

---

## 3. Deployment

### 3.1 Pre-flight (before touching the server)

```bash
# CI must be green on the commit being deployed.
# Locally, the same gates:
composer validate --no-check-publish --strict
composer audit
npm audit --audit-level=low
php artisan test
vendor/bin/phpunit -c phpunit.concurrency.xml   # needs a MySQL/MariaDB instance
```

### 3.2 Back up first

**Do not skip this.** See `BACKUP_AND_RESTORE_RUNBOOK.md`. A migration that
fails halfway is recoverable from a backup and, in the worst case, from nothing
else.

```bash
./deploy/scripts/backup-database.sh
```

Record the backup filename in the deployment ticket. A backup nobody can name is
a backup nobody can restore.

### 3.3 Maintenance mode

```bash
php artisan down --render="errors::503" --retry=60
```

`down` before migrating, not after. A schema change applied while requests are
in flight produces errors that look like application bugs.

### 3.4 Deploy the code

```bash
git fetch --all
git checkout <release-tag>

composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader
npm ci
npm run build
```

`--no-dev` in production; `--optimize-autoloader` builds a classmap. The
classmap matters beyond speed: Phase 2 found a mis-cased class reference
(`PIgLifecycleController`) that resolved only when something else had already
loaded the class. An exact classmap makes that class of bug deterministic.

### 3.5 Migrate

```bash
php artisan migrate --force
```

`--force` is required in production; it is a confirmation prompt, not a safety
mechanism, and the safety came from step 3.2.

If a migration fails, **stop**. Do not re-run blindly. See §6.

### 3.6 Rebuild caches

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

Order matters: `config:cache` first, because the others read configuration.

If `config:cache` fails with a closure serialisation error, a config file
contains a closure — find it rather than skipping the step. A production
deploy without cached config is measurably slower on every request.

### 3.7 Restart workers — after migrating, never before

```bash
sudo supervisorctl restart chipper-farms-worker:*
sudo systemctl restart chipper-farms-scheduler.timer
```

Workers hold the **previous release's code in memory**. A worker started before
the migration runs old code against a new schema. Restarting after means each
worker picks up new code and new schema together.

### 3.8 Bring the site up

```bash
php artisan up
```

---

## 4. Post-deployment verification

Run all of these. Each has caught a real class of defect in this codebase.

```bash
# 1. Boot and configuration
php artisan about
php artisan about | grep -i debug          # MUST be OFF

# 2. Readiness — expect HTTP 200
curl -fsS -o /dev/null -w '%{http_code}\n' https://<host>/health/ready

# 3. Detailed health, including outbox backlog
curl -fsS -H "X-Health-Token: $HEALTH_CHECK_TOKEN" https://<host>/health/metrics | jq

# 4. Every intended schedule is registered — expect SEVEN tasks.
#    Five of these previously sat in a class Laravel never loaded and silently
#    never ran (CF-P0-010). An empty or short list is the failure.
php artisan schedule:list

# 5. Migrations all applied
php artisan migrate:status | grep -i pending    # expect no output

# 6. Workers alive
sudo supervisorctl status chipper-farms-worker:*
systemctl list-timers chipper-farms-scheduler.timer
```

### Smoke tests (manual, ~5 minutes)

1. Log in as an administrator; the dashboard renders.
2. Log in as a non-finance user; `/finance` returns **403**, not a finance page.
3. Open a pig record, record a weight.
4. Storefront: add to cart, checkout as COD, confirm as staff — stock decrements
   by exactly the ordered quantity.
5. Confirm the same order a second time — stock must **not** decrement again.
6. `/health/ready` returns 200.

Step 5 is not padding: confirming twice used to decrement stock twice
(CF-P0-006), and a double-clicked button was enough to trigger it.

---

## 5. Rollback

### Triggers

Roll back if any of these hold after deploy:

- `/health/ready` returns 503 and does not recover within 5 minutes;
- the error rate exceeds its normal baseline;
- any finance posting is observed to be wrong;
- `schedule:list` shows fewer than seven tasks;
- workers restart-loop in `supervisorctl status`.

### Procedure

```bash
php artisan down

git checkout <previous-release-tag>
composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader
npm ci && npm run build

php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
sudo supervisorctl restart chipper-farms-worker:*
sudo systemctl restart chipper-farms-scheduler.timer

php artisan up
```

### Database rollback is NOT symmetric with code rollback

This is the part that most often goes wrong, so it is stated plainly:

- **Do not** reflexively run `migrate:rollback`. Several migrations in this
  codebase have deliberately non-reversible `down()` methods. The organisation
  backfill is one: restoring NULLs would reopen the cross-tenant gap the
  fail-closed policy layer depends on being closed.
- Additive migrations (new nullable columns, new indexes) are safe to leave in
  place. Old code ignores them.
- If a migration must be undone, **restore from the backup taken in §3.2**
  rather than rolling forward into an unknown state.

### What a rollback cannot undo

- **Externally initiated refunds.** Once submitted to Paystack, a refund is
  moving whatever the code does. Reconcile in the Paystack dashboard.
- **Emails and notifications already sent.**
- **Domain events already consumed** by the outbox, whose journals are posted.
  Reverse them through the finance reversal path, not by rolling back code.

---

## 6. When a migration fails mid-deploy

1. **Do not** run `migrate` again reflexively. Check what actually applied:
   ```bash
   php artisan migrate:status
   ```
2. Migrations in this codebase are written to be re-runnable — the raw-material
   valuation cutover was specifically fixed for this (CF-P1-016), because it
   previously aborted on a unique-key violation and left the deploy permanently
   stuck. So a second `migrate` is usually correct *once you know what state you
   are in*.
3. If the schema is inconsistent, restore from the §3.2 backup.
4. Leave maintenance mode ON until resolved.

---

## 7. Routine operations

### Outbox backlog

```bash
curl -H "X-Health-Token: $TOKEN" https://<host>/health/metrics | jq .outbox
```

- `pending` growing steadily → the scheduler is not running, or subscribers are
  throwing. Check `schedule:list` first.
- `failed` non-zero → events that exhausted their retries. These are
  **dead-lettered and will not retry on their own**; they need a human.
- `stale_processing` non-zero → workers died holding claims. Recovered
  automatically on the next drain, but a persistent count means workers are
  dying repeatedly.

### Replaying failed finance events

```bash
php artisan finance:process-events --replay-failed
```

Safe to run: journal postings carry a unique idempotency key, so a replayed
event that already posted is recognised rather than duplicated.

### Housekeeping (scheduled, nothing to do)

- `queue:prune-failed --hours=168` daily: failed jobs older than a week.
- `system:prune-housekeeping` daily: read notifications older than 90 days,
  unread older than 180, expired cache rows. Never touches audit, activity or
  finance records. `--dry-run` shows what it would remove.
- Laravel logs: daily files, `LOG_DAILY_DAYS` (14) kept. Set `LOG_STACK=daily`
  (or leave unset); `LOG_CHANNEL=single` or `LOG_STACK=single` turns rotation off.
- Server logs: `deploy/logrotate/chipper-farms`.

### Queue incident containment

Supervisor is the only automatic queue consumer. `notifications:process` is
manual recovery only, limited to 100 jobs or 60 seconds between jobs (an in-flight
job can take up to its timeout). Do not add it to cron or the scheduler.

During a serialization/disk incident, keep `chipper-farms-worker:*` stopped until
the corrected release and configuration are deployed. Verify no old cron entry,
`schedule:work`, manual worker or previous scheduler process still consumes jobs.
Stop any already-running scheduler-spawned queue worker as well.

Use `--timeout=60` with a queue `retry_after` greater than 60 (default 90).
Set `LOG_CHANNEL=stack`, `LOG_STACK=daily`, and rebuild cached configuration.
Install the logrotate file and confirm its timer runs. Daily rotation and record
truncation are safeguards, not a hard disk quota; monitor free space and growth.

Already queued payloads containing `role.permissions` are not repaired by the
new serialization code. Inspect affected pending/failed jobs before retrying;
archive or remove obsolete incident jobs only after operational review. Do not
blindly retry the entire failed-job backlog. Pruning database rows may not return
InnoDB file space to the operating system; plan any table rebuild separately.

### Failed queue jobs

```bash
php artisan queue:failed
php artisan queue:retry <id>
```

---

## 8. Monitoring and alerting

Not yet wired to a provider. `/health/metrics` exposes the values; something must
consume them. Alert on:

| Condition | Why it matters |
|---|---|
| `/health/ready` returns 503 | Instance cannot serve traffic |
| outbox `pending` rising for >15 min | Scheduler dead or subscribers failing |
| outbox `failed` > 0 | Finance postings dead-lettered — will not self-heal |
| `failed_jobs` rising | Queue work being lost — and, in October 2026, a job failing in a loop grew `failed_jobs` to 44 GB |
| `disk.free_percent` < 15 | A full disk stops the database, the queue and the logs at once (the October 2026 outage) |
| `disk.log_bytes` > 2 GB | Something is logging in a loop |
| No `schedule:run` in 5 min | Scheduler stopped; every timed control is inert |
| Refund `refund_pending` older than 24h | Provider refund submitted but never settled |
| COD settlement blocked events | Orders cannot complete; cash not recognised |
| Repeated Paystack failures | Gateway or credential problem |
| Backup job failure | The one that turns an incident into a disaster |
| Excessive login failures | Credential stuffing |

Application exceptions should go to a central collector (Sentry or equivalent).
`report()` is already used throughout the health checks, so the wiring point
exists.
