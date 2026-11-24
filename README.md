# Chipper Farms

Farm management and storefront platform: pig lifecycle, breeding, health, feed,
inventory, procurement, a customer-facing storefront with Paystack payments, and
double-entry finance.

Built on Laravel 13 (PHP 8.3+), MariaDB/MySQL, Livewire 3 and Vite 6.

> The stock Laravel framework README is preserved at
> [`docs/LARAVEL_FRAMEWORK_README.md`](docs/LARAVEL_FRAMEWORK_README.md).

---

## Contents

- [Architecture](#architecture)
- [Local development](#local-development)
- [Testing](#testing)
- [Queues and the scheduler](#queues-and-the-scheduler)
- [Environment variables](#environment-variables)
- [Paystack](#paystack)
- [Deployment and operations](#deployment-and-operations)
- [Common recovery procedures](#common-recovery-procedures)

---

## Architecture

A Laravel monolith with distinct domains:

| Domain | Location | Notes |
|---|---|---|
| Livestock | `app/Http/Controllers/Pig*`, `app/Services/Stock` | `PigManagementController` is a **facade** delegating to focused controllers |
| Breeding | `Mating`, `Farrowing`, `Weaning` | These tables carry **no `organization_id`**; tenancy is derived through the parent pig |
| Feed & inventory | `app/Services/Feed`, `RawMaterial*` | Quantities use `bcmath` strings, never floats |
| Procurement | `PurchaseRequest*`, `PurchaseStockIntake*` | Policy-enforced segregation of duties |
| Storefront | `app/Storefront` | Cart, checkout, orders, quotes, listings |
| Finance | `app/Finance`, `app/Services/Finance` | Double-entry; posts **only** via the outbox |

### Two rules worth knowing before changing anything

**1. The storefront never posts to the general ledger directly.**
Order confirmation dispatches a domain event into the `domain_events` outbox;
finance subscribers consume it and post journals. This keeps posting out of the
request cycle and gives a replayable record. Journal postings carry a unique
idempotency key, so replaying an event cannot double-post.

**2. Tenancy fails closed.**
`EnforcesOrganizationScope::sameOrganization()` denies when a record's
organisation cannot be resolved. Models without an `organization_id` column
implement `ResolvesOrganization` to derive it from a parent. A new model that
policies will guard needs one or the other — otherwise every access is denied,
which is the safe direction but will look like a bug.

---

## Local development

### Requirements

PHP 8.3+ (`pdo_mysql`, `bcmath`, `gd`, `zip`, `mbstring`), Composer 2, Node 20+,
MariaDB 11.4+ or MySQL 8+.

`bcmath` is not optional — money and stock quantities are handled as strings.

### Setup

```bash
git clone <repo> && cd pig-management-system
composer install
npm ci

cp .env.example .env
php artisan key:generate
# edit .env: DB_DATABASE, DB_USERNAME, DB_PASSWORD

php artisan migrate --seed
npm run build          # or: npm run dev

php artisan serve
```

### Roles and permissions

Seeded roles: `superuser`, `admin`, `farmmanager`, `manager`, `dataentry`,
`accountant`, `treasurer`, `mill_operator`, `salesperson`, `customer`.

Permissions span six modules: `finance`, `livestock`, `facilities`, `feed`,
`sales`, `storefront`. Routes are **deny-by-default** — a route without a
`permission:` gate is a bug, not a convenience.

---

## Testing

```bash
php artisan test                                    # default suite (SQLite, fast)
vendor/bin/phpunit -c phpunit.concurrency.xml       # concurrency (needs MySQL/MariaDB)
```

### Why there are two suites

The default suite runs on SQLite `:memory:` with a sync queue. Fast and fine for
logic, but it **cannot demonstrate row locking** — `SELECT ... FOR UPDATE` is a
no-op there, and an in-memory database is invisible to a second process.
Concurrency defects pass silently.

`tests/Concurrency/` spawns real OS processes with independent connections
against MariaDB. It has already caught three genuine defects no single-process
test could: a first-use numbering race, a claim-token bug, and an InnoDB
deadlock under sustained load.

Those tests **fail rather than skip** when no database is reachable — a
self-skipping suite is how a project ends up with no concurrency evidence at all.

```bash
CF_CONCURRENCY_DB_HOST=127.0.0.1 \
CF_CONCURRENCY_DB_PORT=3306 \
CF_CONCURRENCY_DB_DATABASE=chipper_farms_test \
CF_CONCURRENCY_DB_USERNAME=root \
CF_CONCURRENCY_DB_PASSWORD=secret \
vendor/bin/phpunit -c phpunit.concurrency.xml
```

CI (`.github/workflows/ci.yml`) runs both, plus `composer audit`, `npm audit`, a
fresh MariaDB migration, a migration **re-run** for idempotency, and the
production cache build.

---

## Queues and the scheduler

Both are required. Neither fails loudly when absent, which is why they need
supervision.

```bash
php artisan queue:work --queue=high,default,low --tries=3
php artisan schedule:work        # local only
```

Production process definitions:

- `deploy/supervisor/chipper-farms-worker.conf`
- `deploy/systemd/chipper-farms-scheduler.{service,timer}`
- `deploy/logrotate/chipper-farms` (server log rotation)

### Thirteen scheduled tasks must be registered

```bash
php artisan schedule:list        # expect 13
```

Five of these previously lived in `App\Console\Kernel`, which Laravel's slimmed
bootstrap **never loads**. They silently never ran: no error, no symptom — just
notifications that never arrived and interest that never accrued. Schedules
belong in `routes/console.php` or a service provider. `ScheduleRegistrationTest`
asserts the critical ones and fails if `app/Console/Kernel.php` reappears.

### Finance outbox

```bash
php artisan finance:process-events                  # drain once
php artisan finance:process-events --replay-failed  # retry dead-lettered events
```

Events are claimed atomically, retried with backoff, and dead-lettered after five
attempts. `failed` means *give up and look* — it will not self-heal. Backlog is
exposed at `/health/metrics`.

---

## Environment variables

`.env.example` documents every key. The ones that matter most:

| Variable | Notes |
|---|---|
| `APP_KEY` | Decrypts sessions and encrypted columns. **Back it up** — losing it makes restored data unreadable |
| `APP_DEBUG` | **Must be `false` in production.** Debug output leaks credentials and query contents |
| `DB_*` | MySQL/MariaDB. SQLite is local-throwaway only |
| `PAYSTACK_SECRET_KEY` etc. | Without these the storefront raises `PaymentException` at checkout |
| `FINANCE_ALERT_EMAIL` | Scheduled finance job failure output |
| `HEALTH_CHECK_TOKEN` | Unlocks detailed `/health/ready` and `/health/metrics`. Unset means detail is **refused**, never open |
| `SESSION_SECURE_COOKIE` | `true` wherever served over HTTPS |

---

## Paystack

Use **test keys** (`sk_test_…`) everywhere except production.

```
PAYSTACK_SECRET_KEY=sk_test_xxx
PAYSTACK_PUBLIC_KEY=pk_test_xxx
PAYSTACK_CALLBACK_URL=https://your-host/shop/paystack/callback
```

Automated tests never touch the network — they use
`Tests\Feature\Storefront\Support\FakePaystackClient`.

### Refunds are asynchronous

Paystack accepts a refund and settles it later, so a successful submission
returns `pending`, not success. The order therefore moves to `refund_approved` /
`refund_pending` and only reaches `refunded` when the provider reports
settlement.

**Known gap:** the `refund.processed` webhook that would complete that transition
is not implemented, so a pending refund stays pending until reconciled manually.
See `CF-P0-007` in the register.

---

## Deployment and operations

| Document | Covers |
|---|---|
| [`docs/operations/DEPLOYMENT_RUNBOOK.md`](docs/operations/DEPLOYMENT_RUNBOOK.md) | Deploy, verify, roll back, migration-failure recovery |
| [`docs/operations/BACKUP_AND_RESTORE_RUNBOOK.md`](docs/operations/BACKUP_AND_RESTORE_RUNBOOK.md) | Backup, restore, secret recovery, rehearsal record |

```bash
./deploy/scripts/backup-database.sh
./deploy/scripts/restore-database.sh <dump.sql.gz> <target-database>
```

### Health endpoints

| Endpoint | Purpose |
|---|---|
| `/up` | **Liveness** — did PHP boot. Returns 200 even when the database is down |
| `/health/ready` | **Readiness** — 503 when a dependency is unavailable |
| `/health/metrics` | Outbox backlog, failed jobs, queue depth. Token required |

Use `/health/ready` for load-balancer and orchestrator probes. `/up` will happily
report a healthy instance that cannot serve a single request.

---

## Common recovery procedures

**Finance events stuck pending** — check the scheduler first:

```bash
php artisan schedule:list
php artisan finance:process-events
```

**Dead-lettered finance events** — replay is safe; idempotency keys prevent
double-posting:

```bash
php artisan finance:process-events --replay-failed
```

**A COD order will not complete** — settlement is refused when a line has no
posted sale. Usually the outbox has not caught up: drain it and retry. The
blocking reason is recorded as an order event.

**Stock looks wrong after a cancellation** — cancelling a confirmed order
restores the quantity and returns a sold-out listing to `published`.

**A migration failed mid-deploy** — do not re-run blindly. See §6 of the
deployment runbook.

---

## Remediation programme

This codebase is undergoing a tracked production-readiness remediation.

| File | Purpose |
|---|---|
| `CHIPPER_FARMS_REMEDIATION_STATUS.csv` | Live register: status, evidence and residual risk per finding |
| `CHIPPER_FARMS_REMEDIATION_MASTER_PLAN.md` | Phase plan and finding-to-phase mapping |
| `docs/production-remediation/` | Per-phase evidence reports |

**The system is not yet certified for production.** Open items are tracked in the
register — most significantly `CF-P0-008` (sales-return accounting) and the
external Paystack sandbox certification for `CF-P0-007`.
