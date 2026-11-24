<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled tasks (CF-P0-010)
|--------------------------------------------------------------------------
|
| These five schedules previously lived in App\Console\Kernel::schedule().
| That file is NEVER LOADED: bootstrap/app.php uses Laravel's slimmed
| bootstrap and points `commands:` at this file, so the framework's own
| ConsoleKernel is used and the application's Kernel class is dead code. The
| schedules in it had no effect whatsoever, and `schedule:list` listed only the
| two registered from service providers.
|
| The consequence was silent: weight and gestation notifications never fired,
| notification dispatch was inert, invoice aggregates were never
| reconciled, and loan interest was never accrued. Nothing errored — the work
| simply never ran, which is the worst failure mode for time-sensitive
| livestock and finance controls.
|
| Registered here rather than in a provider so there is one obvious place to
| look. The two provider-registered schedules (finance:process-events in
| FinanceServiceProvider, storefront:sync-availability in
| StorefrontServiceProvider) are left where they are: they are correctly wired,
| they do appear in `schedule:list`, and moving working finance-outbox wiring
| for tidiness would be gratuitous risk. ScheduleRegistrationTest asserts all
| seven are present regardless of where they are declared.
|
| Conventions applied to every task below:
|   - withoutOverlapping(60): a slow run must not stack on itself. The lock
|     expires after 60 minutes: the default is 24 hours, so a run killed
|     mid-way (out of memory, disk full, a restart) silenced the task for a
|     day.
|   - onOneServer(): safe when the app is scaled horizontally. Requires a
|     lock-capable cache store; the database store used in production supports
|     locks.
|   - onFailureWithOutput(): failures are logged, with the tail of the
|     command's output, rather than vanishing into cron's stdout, which is
|     where the original schedules would have died unseen.
|   - Explicit timezone: config('app.timezone') so a server clock change
|     cannot silently move a financial cutoff.
*/

$timezone = config('app.timezone', 'UTC');

/**
 * Log a scheduled task failure with enough context to act on.
 *
 * Used with onFailureWithOutput(), which keeps each task's output in one
 * storage/logs/schedule-<hash>.log file overwritten on every run, so the
 * output is on hand when a run fails without a file that grows forever.
 */
$reportFailure = static function (string $task): Closure {
    return static function (Stringable $output) use ($task): void {
        Log::error('scheduled task failed', [
            'task' => $task,
            // The end of the output is where the error is; cap it so a noisy
            // command cannot flood the log.
            'output' => Str::substr(trim((string) $output), -2000),
            'hint' => 'Run the command manually to reproduce; check queue workers and DB connectivity.',
        ]);
    };
};

// --- Livestock notifications ------------------------------------------------
// Hourly: weighing and gestation reminders are by the day (one per pig per
// day), so running every minute only repeated the work 60 times an hour, and
// when sending failed it re-sent everything every minute (the October 2026
// failed_jobs / disk-full incident). Hourly still flags a new day within the hour.
Schedule::command('weights:notify')
    ->hourly()
    ->withoutOverlapping(60)
    ->onOneServer()
    ->timezone($timezone)
    ->description('Notify staff of pigs due for weighing')
    ->onFailureWithOutput($reportFailure('weights:notify'));

Schedule::command('gestation:notify')
    ->hourly()
    ->withoutOverlapping(60)
    ->onOneServer()
    ->timezone($timezone)
    ->description('Notify staff of gestation milestones and overdue farrowings')
    ->onFailureWithOutput($reportFailure('gestation:notify'));

// Health reminders (D9): doses due and overdue, follow-ups, withdrawal ending,
// expiring batches, low medicine stock. Each is sent once, so the frequency
// only sets how quickly an overdue dose is noticed.
Schedule::command('health:notify')
    ->everyFifteenMinutes()
    ->withoutOverlapping(60)
    ->onOneServer()
    ->timezone($timezone)
    ->description('Send health and medication reminders')
    ->onFailureWithOutput($reportFailure('health:notify'));

// Herd health programmes: schedule routine vaccinations and deworming for
// animals that have reached the age, sows newly confirmed pregnant or
// farrowed, and herd-wide dates coming round. Hourly, so a sow farrowed this
// morning has her post-farrowing dose on today's list.
Schedule::command('health:schedule-programmes')
    ->hourly()
    ->withoutOverlapping(60)
    ->onOneServer()
    ->timezone($timezone)
    ->description('Schedule herd health programme doses')
    ->onFailureWithOutput($reportFailure('health:schedule-programmes'));

// Feed reminders: feed running low, feedings not recorded by 10:00,
// ingredients low or short, plans waiting. Each is sent once.
Schedule::command('feed:notify')
    ->everyFifteenMinutes()
    ->withoutOverlapping(60)
    ->onOneServer()
    ->timezone($timezone)
    ->description('Send feed reminders')
    ->onFailureWithOutput($reportFailure('feed:notify'));

// Queue consumption belongs to Supervisor. Starting a worker here bypasses
// an operator's emergency stop and gives it different retry/timeout settings.
// notifications:process remains available as a bounded manual recovery command.

// --- Finance ----------------------------------------------------------------
// Nightly reconciliation of invoice aggregate totals against their lines.
// Read-only by default (no --fix), so it reports drift rather than mutating
// balances unattended.
$invoiceReconciliation = Schedule::command('finance:reconcile-invoice-aggregates')
    ->dailyAt('02:30')
    ->withoutOverlapping(60)
    ->onOneServer()
    ->timezone($timezone)
    ->description('Reconcile invoice aggregate totals against invoice lines')
    ->onFailureWithOutput($reportFailure('finance:reconcile-invoice-aggregates'));

// The original Kernel called emailOutputOnFailure(config('finance.alert_email'))
// unconditionally. That key is unset by default, and passing null would fail at
// runtime, so the alert is attached only when an address is actually configured.
if (! empty(config('finance.alert_email'))) {
    $invoiceReconciliation->emailOutputOnFailure(config('finance.alert_email'));
}

// Monthly on the 1st: accrues interest on outstanding loans. Posts journals, so
// overlap protection matters — a double run would double-accrue.
Schedule::command('finance:accrue-loan-interest')
    ->monthlyOn(1, '02:00')
    ->withoutOverlapping(60)
    ->onOneServer()
    ->timezone($timezone)
    ->description('Accrue monthly interest on outstanding loans')
    ->onFailureWithOutput($reportFailure('finance:accrue-loan-interest'));

// Failed jobs are kept a week, then removed: a job failing in a loop once
// grew failed_jobs to 44 GB.
Schedule::command('queue:prune-failed', ['--hours' => 168])
    ->dailyAt('03:30')
    ->onOneServer()
    ->timezone($timezone)
    ->description('Remove failed queue jobs older than a week');

// Old in-app notifications and expired cache rows, which otherwise only grow.
Schedule::command('system:prune-housekeeping')
    ->dailyAt('03:45')
    ->withoutOverlapping(60)
    ->onOneServer()
    ->timezone($timezone)
    ->description('Remove old notifications and expired cache entries')
    ->onFailureWithOutput($reportFailure('system:prune-housekeeping'));
