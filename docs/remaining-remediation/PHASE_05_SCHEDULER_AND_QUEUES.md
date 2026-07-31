# Phase 05 - Scheduler And Queues

Verified scheduler registration locally:

`APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync MAIL_MAILER=array php artisan schedule:list`

Registered tasks included:

- `finance:process-events` every minute;
- `storefront:sync-availability` hourly;
- `weights:notify` every minute;
- `gestation:notify` every minute;
- `notifications:process` every five minutes;
- `finance:reconcile-invoice-aggregates` daily;
- `finance:accrue-loan-interest` monthly.

Default suite also passed `Tests\Feature\Console\ScheduleRegistrationTest`.
