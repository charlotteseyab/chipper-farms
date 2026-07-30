<?php

namespace Tests\Feature\Console;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Asserts every intended scheduled task is actually registered (CF-P0-010).
 *
 * Five schedules lived in App\Console\Kernel::schedule(), which Laravel's
 * slimmed bootstrap never loads — bootstrap/app.php points `commands:` at
 * routes/console.php, so the framework's own ConsoleKernel is used and the
 * application Kernel class was dead code. `schedule:list` showed only the two
 * registered from service providers.
 *
 * Nothing errored. The work simply never ran: weight and gestation
 * notifications, notification dispatch, invoice reconciliation and monthly loan
 * interest accrual were all silently inert. That is the worst failure mode for
 * time-sensitive husbandry and finance controls, and it is exactly the class of
 * defect a test must pin, because there is no symptom to notice.
 *
 * This test asserts registration regardless of WHERE a task is declared, so
 * moving one between routes/console.php and a service provider is fine — losing
 * it is not.
 */
class ScheduleRegistrationTest extends TestCase
{
    /**
     * Every task that must be present, with the cron expression it must run on.
     *
     * @return array<string, array{0:string, 1:string}>
     */
    public static function expectedScheduleProvider(): array
    {
        return [
            // Livestock — every minute; a missed window is a welfare issue.
            'weight notifications'   => ['weights:notify', '* * * * *'],
            'gestation notifications' => ['gestation:notify', '* * * * *'],
            'notification dispatch'  => ['notifications:process', '*/5 * * * *'],

            // Finance.
            'invoice reconciliation' => ['finance:reconcile-invoice-aggregates', '30 2 * * *'],
            'loan interest accrual'  => ['finance:accrue-loan-interest', '0 2 1 * *'],

            // Registered in service providers — asserted here so a provider
            // refactor cannot drop them unnoticed.
            'storefront availability' => ['storefront:sync-availability', '0 * * * *'],
        ];
    }

    #[DataProvider('expectedScheduleProvider')]
    public function test_task_is_registered_with_expected_cron(string $command, string $expectedCron): void
    {
        $match = $this->findEventByCommand($command);

        $this->assertNotNull(
            $match,
            "Scheduled task '{$command}' is not registered. It will never run, silently. "
            . 'Check routes/console.php and the service providers.',
        );

        $this->assertSame(
            $expectedCron,
            $match->expression,
            "Scheduled task '{$command}' runs on '{$match->expression}', expected '{$expectedCron}'.",
        );
    }

    /**
     * The finance outbox drain is scheduled as a queued job rather than a
     * command, so it has no command string to match on. Asserted separately by
     * its registered name.
     */
    public function test_finance_outbox_drain_is_registered(): void
    {
        $names = collect(app(Schedule::class)->events())
            ->map(fn (Event $event) => $event->description)
            ->filter()
            ->values()
            ->all();

        $this->assertContains(
            'finance:process-events',
            $names,
            'The finance outbox drain is not scheduled. Domain events would accumulate '
            . 'unprocessed and no finance journals would post.',
        );
    }

    /**
     * Overlap protection is not cosmetic here: finance:accrue-loan-interest
     * posts journals, so a run stacking on a slow predecessor would
     * double-accrue interest.
     */
    public function test_mutating_finance_tasks_prevent_overlap(): void
    {
        foreach (['finance:accrue-loan-interest', 'finance:reconcile-invoice-aggregates'] as $command) {
            $event = $this->findEventByCommand($command);

            $this->assertNotNull($event, "Expected '{$command}' to be scheduled.");
            $this->assertTrue(
                $event->withoutOverlapping,
                "'{$command}' must use withoutOverlapping(): a second run stacking on a "
                . 'slow first run would post duplicate journals.',
            );
        }
    }

    /**
     * App\Console\Kernel was dead code that silently swallowed five schedules.
     * If it reappears, schedules written into it will not run and this test
     * should say so loudly rather than let the trap be reset.
     */
    public function test_dead_console_kernel_has_not_reappeared(): void
    {
        // Checked by file rather than class_exists(): a stale Composer classmap
        // entry makes class_exists() attempt an include and raise, so the file
        // check is both more robust and a more direct statement of the intent.
        $this->assertFileDoesNotExist(
            app_path('Console/Kernel.php'),
            'app/Console/Kernel.php exists again. Laravel\'s slimmed bootstrap does not load '
            . 'it, so any schedule() method on it is inert and its tasks will never run. '
            . 'Register schedules in routes/console.php instead.',
        );
    }

    protected function findEventByCommand(string $command): ?Event
    {
        foreach (app(Schedule::class)->events() as $event) {
            if (str_contains((string) $event->command, $command)
                || str_contains((string) $event->description, $command)) {
                return $event;
            }
        }

        return null;
    }
}
