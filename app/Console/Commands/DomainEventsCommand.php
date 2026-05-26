<?php

namespace App\Console\Commands;

use App\Models\DomainEvent;
use Illuminate\Console\Command;

/**
 * Inspection + maintenance tooling for the domain_events outbox.
 *
 * Subcommands (implemented as options on a single command to keep the
 * surface tight):
 *
 *   --stats                  Summary counts by status and event_type.
 *   --list-pending           List pending events (id, type, occurred_at).
 *   --retry=<id>             Reset a failed event to pending so the next
 *                            consumer run will re-attempt it.
 *   --retry-all-failed       Reset every failed event to pending.
 *   --skip=<id> --reason=X   Mark an event as deliberately skipped.
 *
 * This command deliberately does NOT process (post) events — that is the
 * job of the Phase 1 finance consumer. Its role is diagnostic + operational.
 */
class DomainEventsCommand extends Command
{
    protected $signature = 'chipper:domain-events
                            {--stats : Show outbox status counts by event type}
                            {--list-pending : List pending events}
                            {--retry= : Retry a failed event by id}
                            {--retry-all-failed : Retry all failed events}
                            {--skip= : Mark an event as skipped by id}
                            {--reason= : Reason string for --skip}
                            {--limit=50 : Limit for listing commands}';

    protected $description = 'Inspect and manage the domain events outbox.';

    public function handle(): int
    {
        if ($this->option('stats')) {
            return $this->stats();
        }

        if ($this->option('list-pending')) {
            return $this->listPending();
        }

        if ($retryId = $this->option('retry')) {
            return $this->retry((int) $retryId);
        }

        if ($this->option('retry-all-failed')) {
            return $this->retryAllFailed();
        }

        if ($skipId = $this->option('skip')) {
            return $this->skip((int) $skipId);
        }

        $this->error('No action specified. Use --stats, --list-pending, --retry=<id>, --retry-all-failed, or --skip=<id> --reason=<text>.');
        return self::FAILURE;
    }

    protected function stats(): int
    {
        $statusCounts = DomainEvent::selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $this->info('By status:');
        foreach ($statusCounts as $status => $count) {
            $this->line("  {$status}: {$count}");
        }

        $this->newLine();
        $this->info('Top event types (pending):');
        $pendingByType = DomainEvent::pending()
            ->selectRaw('event_type, COUNT(*) as c')
            ->groupBy('event_type')
            ->orderByDesc('c')
            ->limit(20)
            ->pluck('c', 'event_type');

        foreach ($pendingByType as $type => $count) {
            $this->line("  {$type}: {$count}");
        }

        return self::SUCCESS;
    }

    protected function listPending(): int
    {
        $limit = (int) $this->option('limit');

        $rows = DomainEvent::pending()
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'event_type', 'source_type', 'source_id', 'occurred_at']);

        if ($rows->isEmpty()) {
            $this->info('No pending events.');
            return self::SUCCESS;
        }

        $this->table(
            ['id', 'event_type', 'source', 'occurred_at'],
            $rows->map(fn ($r) => [
                $r->id,
                $r->event_type,
                class_basename($r->source_type) . '#' . $r->source_id,
                (string) $r->occurred_at,
            ])->all()
        );

        return self::SUCCESS;
    }

    protected function retry(int $id): int
    {
        $event = DomainEvent::find($id);
        if (! $event) {
            $this->error("Event {$id} not found.");
            return self::FAILURE;
        }

        if ($event->status !== DomainEvent::STATUS_FAILED) {
            $this->error("Event {$id} is not in 'failed' status (current: {$event->status}).");
            return self::FAILURE;
        }

        $event->forceFill([
            'status' => DomainEvent::STATUS_PENDING,
            'processing_error' => null,
        ])->save();

        $this->info("Event {$id} reset to pending.");
        return self::SUCCESS;
    }

    protected function retryAllFailed(): int
    {
        $count = DomainEvent::where('status', DomainEvent::STATUS_FAILED)
            ->update([
                'status' => DomainEvent::STATUS_PENDING,
                'processing_error' => null,
            ]);

        $this->info("Reset {$count} failed event(s) to pending.");
        return self::SUCCESS;
    }

    protected function skip(int $id): int
    {
        $reason = (string) $this->option('reason');
        if ($reason === '') {
            $this->error('--reason is required when using --skip.');
            return self::FAILURE;
        }

        $event = DomainEvent::find($id);
        if (! $event) {
            $this->error("Event {$id} not found.");
            return self::FAILURE;
        }

        if ($event->status === DomainEvent::STATUS_PROCESSED) {
            $this->error("Event {$id} is already processed; cannot skip.");
            return self::FAILURE;
        }

        $event->markSkipped($reason);
        $this->info("Event {$id} marked as skipped.");
        return self::SUCCESS;
    }
}