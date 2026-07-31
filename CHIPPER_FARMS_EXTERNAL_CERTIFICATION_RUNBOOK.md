# Chipper Farms External Certification Runbook

Date: 2026-07-31

## Required Inputs

- Production-like `.env` without live customer data unless explicitly approved.
- Paystack sandbox secret and public keys.
- MariaDB admin credential for a non-production certification host.
- Off-host backup destination.
- Deployment target and rollback artifact.

## Steps

1. Verify branch and commit.
   - `git status --short`
   - `git rev-parse HEAD`

2. Run dependency and build gates.
   - `composer install --no-interaction --prefer-dist --no-progress`
   - `composer validate --no-check-publish --strict`
   - `composer audit`
   - `npm ci`
   - `npm audit --audit-level=low`
   - `npm run build`

3. Provision isolated MariaDB databases.
   - Use `database/testing/create_isolated_test_databases.sql.example`.
   - Confirm only `chipper_farms_test`, `chipper_farms_concurrency_test`, `chipper_farms_upgrade_test`, and `chipper_farms_restore_test` are touched.

4. Run local guarded database gates.
   - `bash scripts/testing/test-mariadb-connection.sh`
   - `bash scripts/testing/reset-test-database.sh`
   - `bash scripts/testing/run-mariadb-tests.sh tests/Feature/Finance/Schema/JournalBalanceTest.php tests/Feature/Finance/SalesReturns/SalesReturnRevenueTest.php tests/Feature/Storefront/OrderHttpTest.php`
   - `bash scripts/testing/run-concurrency-tests.sh`

5. Run full default suite.
   - `php artisan test`

6. Exercise Paystack sandbox refunds.
   - Create and verify a Paystack order.
   - Submit a refund.
   - Replay a valid `refund.processed` webhook and confirm `status=refunded`, `payment_status=refunded`, payment row `status=refunded`, and non-null `refunded_at`.
   - Replay a valid `refund.failed` webhook against a pending refund and confirm the order remains pending for triage with a failure reason.
   - Replay an invalid-signature webhook and confirm HTTP 401.

7. Verify scheduler and queue runtime.
   - `php artisan schedule:list`
   - Run `php artisan finance:process-events` against test data.
   - Confirm queue/supervisor/cron entries in the deployment environment.

8. Prove backup and restore.
   - Take encrypted backup from production-like MariaDB.
   - Store off-host.
   - Restore into `chipper_farms_restore_test`.
   - Run `php artisan migrate --force` and targeted smoke tests against restored DB.

9. Prove deployment and rollback.
   - Deploy the candidate artifact.
   - Run health, login, storefront, finance dashboard, Paystack callback/webhook, and scheduler smoke checks.
   - Roll back to the previous artifact.
   - Re-run health and key smoke checks.

## Certification Output

Attach command logs, URLs/screenshots where applicable, backup object checksum, restored migration status, Paystack event IDs, remote CI URL, deployment ID, and rollback ID.
