<?php

namespace App\Console\Commands;

use App\Jobs\ProcessPendingDomainEventsJob;
use App\Models\DomainEvent;
use Illuminate\Console\Command;

/**
 * Manual trigger for the domain event processor. Same logic the scheduler
 * runs every minute, but available for ad-hoc use:
 *
 *   php artisan finance:process-events                  Run once, default batch size
 *   php artisan finance:process-events --batch=200      Larger batch
 *   php artisan finance:process-events --dry-run        Show what WOULD be processed
 *   php artisan finance:process-events --replay-failed  Reset failed events back to pending and reprocess
 *
 * Useful during:
 *   - Phase 1c bring-up: process the backlog of pending events from the
 *     past few weeks before subscribers existed.
 *   - Investigation: when an event landed in `failed`, fix the underlying
 *     issue, then `--replay-failed` to retry.
 *   - Tests: invoked synchronously to assert subscriber behaviour.
 */
class ProcessDomainEvents extends Command
{
    protected $signature = 'finance:process-events
                            {--batch=50 : Number of events to process in this run}
                            {--dry-run : Show pending counts without processing}
                            {--replay-failed : Reset failed events to pending before processing}';

    protected $description = 'Process pending domain_events through registered finance subscribers.';

    public function handle(): int
    {
        $batchSize = (int) $this->option('batch');
        $dryRun = (bool) $this->option('dry-run');
        $replayFailed = (bool) $this->option('replay-failed');

        if ($replayFailed) {
            $reset = DomainEvent::where('status', DomainEvent::STATUS_FAILED)
                ->update([
                    'status'           => DomainEvent::STATUS_PENDING,
                    'processing_error' => null,
                ]);
            $this->info("Reset {$reset} failed event(s) to pending.");
        }

        $pending = DomainEvent::where('status', DomainEvent::STATUS_PENDING)->count();

        if ($pending === 0) {
            $this->info('No pending events. Nothing to do.');
            return self::SUCCESS;
        }

        $this->info("Pending events: {$pending}");

        if ($dryRun) {
            $byType = DomainEvent::where('status', DomainEvent::STATUS_PENDING)
                ->selectRaw('event_type, count(*) as c')
                ->groupBy('event_type')
                ->pluck('c', 'event_type')
                ->all();

            $this->table(
                ['Event Type', 'Count'],
                collect($byType)->map(fn ($c, $t) => [$t, $c])->values()->all()
            );

            return self::SUCCESS;
        }

        $this->info("Processing up to {$batchSize} events...");
        ProcessPendingDomainEventsJob::dispatchSync($batchSize);

        $newPending = DomainEvent::where('status', DomainEvent::STATUS_PENDING)->count();
        $this->info("Done. Pending events remaining: {$newPending}");

        return self::SUCCESS;
    }
}
