# Chipper Farms — Backup and Restore Runbook

Addresses `CF-P1-022`, `CF-P0-012`.

A backup that has never been restored is a hypothesis, not a backup. This
document records both the procedure **and an actual rehearsal**, because the
audit's finding was not "backups are configured badly" — it was that no backup,
restore or recovery evidence existed at all.

---

## 1. What must be backed up

| Asset | Contains | Recoverable without a backup? |
|---|---|---|
| **Database** | Every livestock, breeding, health, inventory, finance and order record | **No** |
| **`storage/app`** | Attachments — invoices, receipts, refund evidence, bank statements | **No** |
| **`.env`** | `APP_KEY`, database credentials, Paystack keys | **No** — see §5 |
| Application code | | Yes — it is in git |
| `public/build` | Compiled assets | Yes — `npm ci && npm run build` |
| `vendor/`, `node_modules/` | Dependencies | Yes — from lockfiles |

Two of those deserve emphasis:

- **`storage/app` is not optional.** Finance attachments are the evidence behind
  postings. A restored database referencing attachments that no longer exist
  produces an audit trail that points at nothing.
- **`APP_KEY` is not just a config value.** It decrypts sessions and any
  encrypted column. A database restored without its matching `APP_KEY` has
  unreadable encrypted data — the rows are there and the contents are lost.

---

## 2. Database backup

```bash
./deploy/scripts/backup-database.sh [output-directory]
```

Defaults to `/var/backups/chipper-farms`, overridable via `CF_BACKUP_DIR`.

The script deliberately:

- reads connection details **from `.env`**, so it cannot back up a different
  database from the one the application uses;
- passes credentials through a temporary defaults file rather than
  `--password=`, which would expose the password in `ps` output to every user on
  the host;
- uses `--single-transaction` for a consistent InnoDB snapshot **without**
  locking writers (`--lock-tables` would block the application for the duration);
- includes `--routines --triggers --events`; omitting these silently loses them;
- **verifies the dump before declaring success** — non-empty, passes `gzip -t`,
  and ends with mysqldump's completion marker. A truncated dump that satisfies a
  monitoring check while being unrestorable is worse than an obvious failure;
- `chmod 600` the output. Dumps contain customer data and password hashes.

### Scheduling

```cron
# Nightly at 01:30, before the 02:30 invoice reconciliation.
30 1 * * * /var/www/chipper-farms/deploy/scripts/backup-database.sh >> /var/log/chipper-farms/backup.log 2>&1
```

**Alert on failure.** A silently failing backup job is the single most common
way an incident becomes a disaster.

---

## 3. File backup

```bash
tar -czf /var/backups/chipper-farms/storage-$(date -u +%Y%m%dT%H%M%SZ).tar.gz \
    -C /var/www/chipper-farms storage/app
```

---

## 4. Offsite copies

**A backup on the same host as the database is not a backup.** It shares every
failure mode with the thing it protects: disk failure, ransomware, an accidental
`rm -rf`, or the host simply going away.

Copy to separate infrastructure, encrypted in transit and at rest, e.g.:

```bash
aws s3 cp "$DUMP" "s3://<bucket>/chipper-farms/db/" --sse AES256
```

**Retention:** 30 days locally (the script prunes older files), 90 days offsite,
12 monthly archives. Retention is a business decision — these are defaults, not
requirements.

---

## 5. Secret recovery

`.env` is deliberately excluded from git, which means it is **also excluded from
every code-based recovery path**. Store it in a password manager or secrets
service, and record:

- `APP_KEY`
- database credentials
- `PAYSTACK_SECRET_KEY` / `PAYSTACK_PUBLIC_KEY`
- `HEALTH_CHECK_TOKEN`
- mail credentials

Rehearse this too. A team that can restore the database but cannot produce the
`APP_KEY` has restored an encrypted archive it cannot read.

---

## 6. Restore

```bash
./deploy/scripts/restore-database.sh <dump.sql.gz> <target-database>
```

The target database is a **required explicit argument** and is not read from
`.env`. A restore script that defaults to the live database is one mistyped
command from destroying production during a rehearsal. Restoring over the
database named in `.env` additionally requires `CF_ALLOW_LIVE_RESTORE=yes`.

### Full recovery order

1. Provision the host; install PHP, MariaDB, Node, Supervisor.
2. `git clone` and check out the release tag.
3. **Restore `.env` from the secrets store** (§5).
4. `composer install --no-dev --optimize-autoloader && npm ci && npm run build`
5. Restore the database (above).
6. Restore `storage/app`.
7. `php artisan migrate --force` — expect no pending migrations; if there are
   any, the dump predates the release.
8. `php artisan config:cache route:cache view:cache event:cache`
9. Start workers and the scheduler.
10. Verify (§7).

---

## 7. Post-restore verification

```bash
php artisan migrate:status | grep -i pending     # expect no output
php artisan about
php artisan schedule:list                        # expect SEVEN tasks
curl -fsS -o /dev/null -w '%{http_code}\n' https://<host>/health/ready   # 200
```

Then compare record counts against the source, and run the integrity checks in
§8. A restore is not verified by the absence of errors during import; it is
verified by the data being right afterwards.

---

## 8. Rehearsal record

Performed as part of remediation Phase 8, against MariaDB 11.4.2.

| Item | Value |
|---|---|
| Backup taken | `2026-07-30T15:32:06Z` |
| Dump size | 48 KB gzipped |
| Tables in dump | 128 |
| Restore target | `chipper_farms_restore_drill` (throwaway) |
| **Restore duration** | **9 seconds** |
| Restore verified | Yes |

**Count comparison — source vs restored:**

| Metric | Source | Restored | Match |
|---|---:|---:|:--:|
| Tables | 128 | 128 | ✅ |
| Migrations applied | 191 | 191 | ✅ |
| Permissions | 84 | 84 | ✅ |
| Roles | 10 | 10 | ✅ |
| Pigs | 3 | 3 | ✅ |

**Application against the restored database:**

- `migrate:status` — 0 pending migrations
- `php artisan about` — boots, Laravel 13.23.0, MySQL driver

**Integrity checks on the restored copy:**

| Check | Result |
|---|---|
| Unbalanced journals | 0 |
| Orphan journal lines | 0 |

### Honest limitations of this rehearsal

Recorded so the evidence is not read as stronger than it is:

- The rehearsal database was **seeded, not transacted**. `journal_lines` was
  empty, so the balance check exercised the *query* rather than proving
  financial integrity at scale. Repeat against a production-sized dataset before
  relying on it.
- **48 KB is not a realistic dump size.** Restore duration scales with data;
  9 seconds tells you the procedure works, not what an outage would cost.
  Re-measure against production volume to obtain a real RTO.
- **`storage/app` was not part of this rehearsal.** Only the database was
  restored and verified.
- **Offsite copy and secret recovery were not rehearsed** — no external storage
  or secrets service exists in this environment.

---

## 9. Recovery objectives

These are **proposals**, not measurements. They need to be agreed with the
business and then verified against production-sized data.

| Objective | Proposed | Basis |
|---|---|---|
| **RPO** (max data loss) | 24 hours | Nightly backup. Tighten with binlog shipping or more frequent dumps if a day of livestock and order records is too much to re-enter. |
| **RTO** (max downtime) | 4 hours | Provision + restore + verify, with generous headroom over the 9 s measured on a small dataset. |

If a 24-hour RPO is unacceptable — and for a system holding payments and
receivables it may well be — enable binary logging and ship logs, which brings
RPO to minutes. That is an infrastructure decision outside this repository.

---

## 10. What restore cannot recover

- **Refunds already submitted to Paystack.** Money moves regardless of database
  state. Reconcile against the Paystack dashboard after any restore that rewinds
  past a refund.
- **Emails and notifications already sent.**
- **Anything written after the backup.** That is what the RPO measures.
