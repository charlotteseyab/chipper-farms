# Upgrade Guide

## 1. Pre-upgrade precautions

- Schedule a maintenance window if migrations will run against the live database.
- Confirm the current production release or commit.
- Freeze non-essential data entry during the upgrade.
- Read the changelog and release notes before deployment.

## 2. Back up before anything else

Create and verify:

- full database backup
- storage backup for uploaded files
- copy of the current `.env`
- copy of the current release package

## 3. Environment checks

Confirm:

- PHP and Composer versions are compatible
- database credentials are correct
- mail settings are correct
- queue worker is healthy
- scheduler is healthy
- `public/hot` is not present in production
- the application is using built frontend assets

## 4. Review these changes carefully

This package includes:

- route access hardening
- role validation changes
- purchase request workflow protection
- stock adjustment protection
- migration safety fixes
- a new migration for `farrowing.date_weaned`

## 5. Recommended deployment order

```bash
php artisan down
composer install --no-dev --optimize-autoloader
npm install
npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan up
```

Restart the queue worker after deployment.

## 6. Migration precautions

- Do not enable `CHIPPER_ALLOW_FEED_TABLE_DROP` in production.
- Run migrations on a staging copy first if possible.
- Confirm the `farrowing` table accepts the new `date_weaned` column before testing the weaning flow.

## 7. Post-upgrade verification checklist

Check all of the following immediately:

- login works for each role
- admin dashboard loads
- manager dashboard loads
- superuser dashboard loads
- data entry dashboard loads
- health pages load and update correctly
- purchase requests can be created and viewed
- only allowed roles can approve or mark requests as paid
- raw materials can be received and adjusted safely
- farrowing and weaning screens load without `date_weaned` errors
- feed production and planning pages load
- exports still work
- notifications still work

## 8. Rollback approach

If the release fails:

1. put the app back into maintenance mode
2. restore the previous code release
3. restore the database backup if the failure is schema or data related
4. clear caches and restart queue workers
5. verify the previous version before reopening access

## 9. Manual follow-up after release

- review logs for exceptions
- confirm no staff role lost access unexpectedly
- confirm dashboards and counts still match expected live data
- verify mobile/tablet pages used by farm staff
