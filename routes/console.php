<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

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
| queued notifications were never drained, invoice aggregates were never
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
|   - withoutOverlapping(): a slow run must not stack on itself.
|   - onOneServer(): safe when the app is scaled horizontally. Requires a
|     lock-capable cache store; the database store used in production supports
|     locks.
|   - onFailure(): failures are logged rather than vanishing into cron's
|     stdout, which is where the original schedules would have died unseen.
|   - Explicit timezone: config('app.timezone') so a server clock change
|     cannot silently move a financial cutoff.
*/

$timezone = config('app.timezone', 'UTC');

/**
 * Log a scheduled task failure with enough context to act on.
 */
$reportFailure = static function (string $task): callable {
    return static function () use ($task): void {
        Log::error('scheduled task failed', [
            'task' => $task,
            'hint' => 'Run the command manually to reproduce; check queue workers and DB connectivity.',
        ]);
    };
};

// --- Livestock notifications ------------------------------------------------
// Every minute: these drive time-sensitive husbandry reminders (weighing due,
// gestation milestones). A missed window is an animal-welfare issue, not just a
// stale dashboard.
Schedule::command('weights:notify')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer()
    ->timezone($timezone)
    ->description('Notify staff of pigs due for weighing')
    ->onFailure($reportFailure('weights:notify'));

Schedule::command('gestation:notify')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer()
    ->timezone($timezone)
    ->description('Notify staff of gestation milestones and overdue farrowings')
    ->onFailure($reportFailure('gestation:notify'));

Schedule::command('notifications:process')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer()
    ->timezone($timezone)
    ->description('Dispatch pending notifications')
    ->onFailure($reportFailure('notifications:process'));

// --- Finance ----------------------------------------------------------------
// Nightly reconciliation of invoice aggregate totals against their lines.
// Read-only by default (no --fix), so it reports drift rather than mutating
// balances unattended.
$invoiceReconciliation = Schedule::command('finance:reconcile-invoice-aggregates')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->onOneServer()
    ->timezone($timezone)
    ->description('Reconcile invoice aggregate totals against invoice lines')
    ->onFailure($reportFailure('finance:reconcile-invoice-aggregates'));

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
    ->withoutOverlapping()
    ->onOneServer()
    ->timezone($timezone)
    ->description('Accrue monthly interest on outstanding loans')
    ->onFailure($reportFailure('finance:accrue-loan-interest'));
