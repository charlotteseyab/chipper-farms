# Phase 12 - Invalid Evidence Log

Invalid runs are recorded so they are not mistaken for certification evidence:

- A production-cache MariaDB run failed after an overlapping focused Laravel test used the same `chipper_farms_test` database. The failure contained missing tables and deadlocks. This was invalidated as test-run interference.
- A focused finance slice failed with two supplier-statement deadlocks during the same overlap. It passed when rerun alone.
- A frontend build failed while `npm ci` was concurrently rebuilding `node_modules`. The build passed when rerun after install completed.
- `php artisan optimize:clear` under normal `.env` failed on database-backed cache because the normal application DB was unavailable. Test-safe cache cleanup with `CACHE_STORE=array` passed.
