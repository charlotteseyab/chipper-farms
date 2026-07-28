<?php

namespace App\Console\Commands\Finance;

use App\Finance\Outbox\SubscriberRegistry;
use App\Models\DomainEvent;
use Illuminate\Console\Command;

/**
 * Phase 4 — diagnostics command for the outbox/subscriber pipeline.
 *
 * Usage:
 *   php artisan finance:check-outbox-wiring
 *
 * Output sections:
 *   1. Subscriber registry — every event_type → subscriber class
 *      mapping currently active.
 *   2. Class existence — confirms each subscriber class can be
 *      resolved by the container.
 *   3. Pending events backlog — count of domain_events in 'pending'
 *      status per event_type. Anything large here is a sign the queue
 *      worker hasn't been processing.
 *   4. Skipped events — count of 'skipped' events per event_type,
 *      typically from the previous deployment when subscribers weren't
 *      wired. Operators can manually flip these back to 'pending' for
 *      reprocessing after confirming the wiring is correct.
 *   5. Failed events — count of 'failed' events. These need review.
 *
 * Returns 0 on healthy state, 1 if any issue was detected (missing
 * class, orphan event types in DB with no subscriber, etc.).
 */
class CheckOutboxWiringCommand extends Command
{
    protected $signature = 'finance:check-outbox-wiring';

    protected $description = 'Diagnose the domain event subscriber pipeline (subscribers registered, backlog, failures).';

    public function handle(SubscriberRegistry $registry): int
    {
        $this->info('Phase 4 — Outbox wiring check');
        $this->newLine();

        $unhealthy = false;

        // 1. Subscriber registry.
        $this->info('1. Registered subscribers');
        $types = $registry->registeredTypes();

        if (empty($types)) {
            $this->error('   NO subscribers are registered. The finance pipeline is dead.');
            $unhealthy = true;
        } else {
            foreach ($types as $type) {
                $subs = $registry->subscribersFor($type);
                $this->line(sprintf('   %-50s → %d subscriber(s)', $type, count($subs)));
                foreach ($subs as $sub) {
                    $exists = class_exists($sub);
                    $marker = $exists ? 'OK' : 'No (MISSING)';
                    $this->line(sprintf('       %s %s', $marker, $sub));
                    if (! $exists) {
                        $unhealthy = true;
                    }
                }
            }
        }

        $this->newLine();

        // 2. Pending backlog.
        $this->info('2. Pending domain events');
        $pending = DomainEvent::query()
            ->where('status', DomainEvent::STATUS_PENDING)
            ->selectRaw('event_type, COUNT(*) as count')
            ->groupBy('event_type')
            ->orderByDesc('count')
            ->pluck('count', 'event_type');

        if ($pending->isEmpty()) {
            $this->line('   (none)');
        } else {
            foreach ($pending as $type => $count) {
                $hasSub = ! empty($registry->subscribersFor((string) $type));
                $marker = $hasSub ? '' : ' No NO SUBSCRIBER';
                $this->line(sprintf('   %-50s %6d%s', $type, (int) $count, $marker));
                if (! $hasSub) {
                    $unhealthy = true;
                }
            }
        }

        $this->newLine();

        // 3. Skipped backlog.
        $this->info('3. Skipped domain events (review — may need reprocessing)');
        $skipped = DomainEvent::query()
            ->where('status', DomainEvent::STATUS_SKIPPED)
            ->selectRaw('event_type, COUNT(*) as count')
            ->groupBy('event_type')
            ->orderByDesc('count')
            ->pluck('count', 'event_type');

        if ($skipped->isEmpty()) {
            $this->line('   (none)');
        } else {
            foreach ($skipped as $type => $count) {
                $hasSub = ! empty($registry->subscribersFor((string) $type));
                $hint = $hasSub
                    ? ' ← subscriber now registered; consider re-queueing'
                    : '';
                $this->line(sprintf('   %-50s %6d%s', $type, (int) $count, $hint));
            }
        }

        $this->newLine();

        // 4. Failed backlog.
        $this->info('4. Failed domain events');
        $failed = DomainEvent::query()
            ->where('status', DomainEvent::STATUS_FAILED)
            ->selectRaw('event_type, COUNT(*) as count')
            ->groupBy('event_type')
            ->orderByDesc('count')
            ->pluck('count', 'event_type');

        if ($failed->isEmpty()) {
            $this->line('   (none)');
        } else {
            foreach ($failed as $type => $count) {
                $this->line(sprintf('   %-50s %6d', $type, (int) $count));
            }
            $this->line('   Inspect individual rows: SELECT id, event_type, processing_error FROM domain_events WHERE status = \'failed\' ORDER BY id DESC LIMIT 20;');
        }

        $this->newLine();
        if ($unhealthy) {
            $this->error('Outbox wiring check found issues. See output above.');
            return 1;
        }

        $this->info('Outbox wiring check passed.');
        return 0;
    }
}
