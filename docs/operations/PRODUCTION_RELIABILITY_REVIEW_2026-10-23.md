# Production reliability review — 23 October 2026

Status: local remediation and verification; production certification pending.

This review covered queue consumption and serialization, retries and timeouts,
scheduled tasks, recurring database work, tenant context, logging and retention,
backup/restore scripts, readiness/metrics, dependency advisories, application
bootstrap/caches, and the full default automated suite. Existing working-tree
changes were preserved. No production host was accessed, workers restarted,
production data deleted, or deployment performed.

## Findings corrected in this review

| Priority | Finding | Correction |
| --- | --- | --- |
| High | `notifications:process` started a queue worker every five minutes even when Supervisor was stopped. | Removed automatic scheduling. Manual command uses finite attempts, timeout, runtime and job-count limits and propagates the worker exit code. Supervisor must own automatic consumption. |
| High | Worker timeout and default database retry interval were both 90 seconds, permitting a reserved job to be redelivered while still running. | Supervisor timeout is 60 seconds; retry interval remains 90. Effective server overrides must be checked. |
| High | `.env` and `.env.example` selected `LOG_STACK=single`, overriding daily rotation. | Changed both to `daily`. This requires deployment and configuration-cache rebuild on production. |
| High | Four PHP security advisories affected CommonMark, HttpFoundation and YAML, including CPU/process denial-of-service risks. | Compatible patches: CommonMark 2.10.4, HttpFoundation 8.1.9, YAML 8.1.9. Composer reports no remaining advisories after updating. |
| High | npm audit identified nine affected dependency nodes, including critical/high build-tool and Axios findings. | Applied compatible updates and a patched CSS parser override. npm installation now reports zero vulnerabilities. |
| High | Queued template emails swallowed transport exceptions and completed without queue retries. | Queued mail opts into propagating transport failures; disabled templates/invalid recipients remain deliberate skips. Synchronous operations retain best-effort mail. |
| High | Monthly loan-interest command selected only organization-less loans when invoked by the scheduler. | Runs per active farm, binds/restores tenant context, selects an active farm administrator or platform superuser, continues across farm failures with nonzero status. Regression checks posting and repeat-run idempotency. |
| High | Composer and deployment guidance allowed PHP 8.3 although locked Symfony packages require PHP >=8.4.1. | Aligned the root PHP requirement and runbook with the actual lockfile. |
| Medium | Livestock reminder commands materialized every pig/pregnancy before processing. | Read these records in pages. Weighing failures retain only five bounded samples and return failure status; recipient caches reset per run. This reduces peak model memory, but recipient and daily-deduplication maps still grow with workload. |
| Medium | Restore script interpolated an unrestricted database target into SQL. | Restrict target names to letters, digits and underscores before connection or destructive SQL. Existing live-database guard remains. |
| Medium | Public readiness requests calculated outbox aggregates that were not returned or used for readiness. | Calculate backlog only for authorized detail requests; public probes retain dependency checks. |

## Previous incident protections rechecked

New queued users exclude relations via `getQueueableRelations()`. Permission
checks and reminder recipients use `roleRecord.permissions`. Notification pig
models strip loaded relations; weight notifications carry three attempts and
backoff. In-app delivery is synchronous so daily duplicate checks can observe it.
Weighing and gestation schedules are hourly with single-server/overlap protection.
Failed-job pruning is registered for seven-day retention. Daily logs, large
message truncation and server-log rotation configuration are present.

The legacy `User::role()`/string-column collision still exists for compatibility.
New queue serialization bypasses it; old stored payloads are not rewritten.
Do not blindly retry historical jobs carrying `role.permissions`.

## Validation

- Initial default suite: 2,502 tests, 8,862 assertions, 14 skipped, no failures.
- Full rerun after the main code/PHP dependency changes: 2,503 tests,
  8,865 assertions, 14 skipped, no failures (6m19s, peak 335.5 MB). The skips
  comprise six MariaDB-specific journal constraint tests and eight disabled
  API-token/email-verification/registration feature checks.
- Targeted command, loan and safeguard regressions: 41 tests, 157 assertions,
  no failures. Later readiness regression run: 10 tests, 27 assertions, no
  failures. Later queued-email/serialization run: 9 tests, 36 assertions, no
  failures. The latter two runs cover changes added after the full rerun began.
- PHP syntax scan across app/bootstrap/config/database/routes/tests passed;
  changed health/mail files were checked again after their final edits.
- Composer manifest/lock strict validation and non-dev platform checks passed
  on local PHP 8.4.22. Final Composer audit: no advisories or abandoned packages.
- Final npm installation audit: zero vulnerabilities (189 dependencies audited).
  No forced Tailwind downgrade was applied.
- Final Vite production build passed. Both CSS builds contain 5,419 rules with
  identical selector order. CSS is not byte-identical: comparison found three
  changed selector/declaration pairs involving Typography keyboard shadows and
  transition vendor prefixes after the compatible tool updates. No browser
  visual regression or production load test was performed.
- Isolated config, route, event and Blade cache builds passed using temporary
  paths; normal application cache files were not replaced by test configuration.
- Backup/restore shell syntax passed. A target containing a backtick was rejected
  before any database connection. Actual backup/restore execution was not tested.
- Local logrotate debug could read the config but could not switch to www-data
  or read the root-owned state file; installation/rotation remains unverified.
- MariaDB connection check outside the sandbox reached the local service but
  access to the approved test database was denied. No database test reset,
  production migration, concurrency test or production-cache DB suite was run.

Raw command output and JUnit evidence for this session are in
`/tmp/chipper-production-review-*`; these are temporary local artifacts.

## Required before production sign-off

1. Deploy the corrected release and installed dependencies while incident workers
   remain stopped. Remove legacy cron/scheduler queue consumers and inspect any
   already-running scheduler-spawned workers. Confirm only Supervisor consumes
   the queue. Manual `notifications:process` deliberately consumes jobs.
2. Inspect affected historical pending/failed notification jobs before replay.
   Archive/remove only reviewed obsolete jobs. Do not blanket-retry the backlog.
3. Verify PHP CLI and FPM >=8.4.1, production debug disabled, HTTPS/session-cookie
   configuration, application webroot restricted to `public`, and cached queue,
   logging and mail settings. Check effective timeout < retry_after.
4. Verify Supervisor configuration, scheduler timer/cron and lock-capable shared
   cache. Removing the scheduled worker means Supervisor availability is required
   for queued delivery. Confirm default and custom queues are actually consumed.
5. Install and verify logrotate and scheduled failed-job pruning. Daily retention
   and per-message truncation are not hard disk quotas. Monitor DB/log/backup and
   upload growth, filesystem free space/inodes, and database space. InnoDB row
   deletion does not necessarily reclaim filesystem space.
6. Configure external alerts for disk capacity/growth, worker failure/restart
   loops, queue depth/oldest age, scheduler failure and outbox backlog. The repo
   exposes metrics, but external alert delivery was not verified.
7. Run the MariaDB engine-specific, concurrency, upgrade and production-cache
   release gates against approved disposable databases. Local access to
   `chipper_farms_test` was denied, so SQLite success is not evidence for row
   locking, triggers, concurrency or production migration behavior.
8. Rehearse backup restoration and rollback against a disposable database and
   verify offsite backup retention. Exercise realistic herd/order/report/import
   sizes against production-equivalent PHP-FPM/DB limits before capacity sign-off.

## CSS parser compatibility

The CSS parser security fix requires a major-version override because Tailwind
3 and Typography still constrain their parser to version 6. The upstream
[changelog](https://github.com/postcss/postcss-selector-parser/blob/master/CHANGELOG.md)
documents the iteration change in version 7 and the CPU-exhaustion fix in 7.1.6.
The override selects ^7.1.7; the production build and selector/declaration
comparison results are recorded in validation. Revisit this override when upgrading Tailwind/Typography.

No review or green test suite establishes that all production incidents are
impossible. The unresolved server checks above remain release requirements.
