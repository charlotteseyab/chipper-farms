<?php

namespace App\Finance\Outbox;

use App\Models\DomainEvent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Atomic claiming, retry scheduling and stale-claim recovery for the finance
 * outbox (CF-P1-015).
 *
 * THE CLAIM
 * ---------
 * Claiming is a single guarded UPDATE per batch:
 *
 *   UPDATE domain_events
 *      SET status = 'processing', claimed_by = :token, ...
 *    WHERE id IN (:candidates) AND status = 'pending'
 *
 * The `status = 'pending'` predicate is what makes it atomic. Two workers may
 * select the same candidate ids — that read is deliberately unlocked — but only
 * one UPDATE can move a given row out of 'pending', because row-level locking
 * serialises the two statements and the loser's predicate no longer matches.
 * Each worker then re-selects by its own unique token, so it processes exactly
 * the rows it won and never a row another worker holds.
 *
 * This is portable in a way `SELECT ... FOR UPDATE SKIP LOCKED` is not.
 * SKIP LOCKED would be the idiomatic MySQL 8 / MariaDB 10.6+ choice, but it does
 * not exist in SQLite, which the default test suite runs on — so the claim would
 * be untestable there and would silently behave differently between test and
 * production. The guarded-UPDATE form has identical semantics on both.
 *
 * WHY THIS MATTERS LESS THAN THE REGISTER SUGGESTS, AND WHY IT STILL MATTERS
 * -------------------------------------------------------------------------
 * The register frames the missing claim as "multiple workers can duplicate
 * work". Duplicate *journals* were in fact already impossible: journal_postings
 * carries a UNIQUE idempotency key and Subscriber converts the resulting
 * DuplicatePostingException into success. So the pre-existing design was safe
 * against the worst outcome.
 *
 * What it was not safe against was FAILURE. With $tries = 1 and no retry
 * scheduling, any transient fault — lock-wait timeout, dropped connection,
 * deadlock — moved an event to 'failed' permanently. A momentary blip silently
 * and irrecoverably dropped a finance posting, and nothing surfaced it. That is
 * the defect this class exists to fix; the claim is what makes bounded retry
 * possible without workers fighting over the same row.
 */
class OutboxClaimer
{
    /**
     * Retry backoff in seconds, indexed by attempt number already made.
     *
     * Deliberately short at first (a lock-wait timeout clears in seconds) and
     * widening to minutes, so a genuinely broken subscriber is not hammered.
     */
    public const BACKOFF_SECONDS = [1 => 10, 2 => 60, 3 => 300, 4 => 900];

    /** Attempts after which an event is dead-lettered rather than retried. */
    public const MAX_ATTEMPTS = 5;

    /**
     * How long a claim may be held before it is considered abandoned.
     *
     * Must comfortably exceed the slowest realistic single-event processing
     * time; too low and a slow-but-healthy worker has its work stolen, which
     * would be safe (the idempotency key protects the journal) but wasteful.
     */
    public const STALE_CLAIM_SECONDS = 600;

    /** Stable per-worker prefix; aids operator triage ("which box holds these?"). */
    protected string $workerPrefix;

    /** Token of the most recent claim() call. */
    protected string $token;

    public function __construct()
    {
        $this->workerPrefix = substr(gethostname() ?: 'worker', 0, 40);
        $this->token        = $this->workerPrefix;
    }

    public function token(): string
    {
        return $this->token;
    }

    /**
     * Mint a token for a single claim() call.
     *
     * Deliberately PER CALL, not per instance. With a per-instance token, the
     * "re-select what I won" query matches every row this worker still holds,
     * including rows claimed by an earlier call that have not yet been released
     * — so a second claim() would hand back the first batch again and the worker
     * would reprocess it. A per-call token makes the returned set exactly the
     * rows that call won.
     */
    protected function mintToken(): string
    {
        return $this->token = $this->workerPrefix . ':' . Str::random(16);
    }

    /**
     * Atomically claim up to $limit eligible events.
     *
     * @return Collection<int, DomainEvent>
     */
    public function claim(int $limit): Collection
    {
        $candidates = DomainEvent::query()
            ->where('status', DomainEvent::STATUS_PENDING)
            ->where(function ($q) {
                $q->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', now());
            })
            ->orderBy('occurred_at')
            ->limit($limit)
            ->pluck('id');

        if ($candidates->isEmpty()) {
            return collect();
        }

        $token = $this->mintToken();

        // The guarded UPDATE. Only rows still 'pending' are taken, so a row
        // another worker already claimed is skipped rather than stolen.
        $claimed = DomainEvent::query()
            ->whereIn('id', $candidates)
            ->where('status', DomainEvent::STATUS_PENDING)
            ->update([
                'status'              => DomainEvent::STATUS_PROCESSING,
                'claimed_by'          => $token,
                'claimed_at'          => now(),
                'processing_attempts' => DB::raw('processing_attempts + 1'),
                'updated_at'          => now(),
            ]);

        if ($claimed === 0) {
            return collect();
        }

        // Matches exactly the rows this call won, because the token is unique
        // per call — see mintToken().
        return DomainEvent::query()
            ->where('claimed_by', $token)
            ->where('status', DomainEvent::STATUS_PROCESSING)
            ->orderBy('occurred_at')
            ->get();
    }

    /**
     * Claim one specific event, for posting it straight after the save that
     * created it. The same guarded UPDATE as claim(), so the event is
     * processed once whether the instant path or a worker gets it first.
     */
    public function claimOne(int $eventId): ?DomainEvent
    {
        $token = $this->mintToken();

        $claimed = DomainEvent::query()
            ->whereKey($eventId)
            ->where('status', DomainEvent::STATUS_PENDING)
            ->where(function ($q) {
                $q->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', now());
            })
            ->update([
                'status'              => DomainEvent::STATUS_PROCESSING,
                'claimed_by'          => $token,
                'claimed_at'          => now(),
                'processing_attempts' => DB::raw('processing_attempts + 1'),
                'updated_at'          => now(),
            ]);

        return $claimed === 1
            ? DomainEvent::query()->whereKey($eventId)->where('claimed_by', $token)->first()
            : null;
    }

    /**
     * Mark a claimed event as successfully processed.
     *
     * Subscribers already set 'processed' themselves; this releases the claim
     * fields so a processed row does not look perpetually claimed to an
     * operator reading the table.
     */
    public function release(DomainEvent $event): void
    {
        DomainEvent::query()->whereKey($event->id)->update([
            'claimed_by'    => null,
            'claimed_at'    => null,
            'next_retry_at' => null,
            'updated_at'    => now(),
        ]);
    }

    /**
     * Record a failure and decide between retry and dead-letter.
     *
     * Returns true when the event was re-queued for another attempt.
     */
    public function fail(DomainEvent $event, string $error): bool
    {
        $attempts = (int) ($event->processing_attempts ?? 0);

        if ($attempts >= self::MAX_ATTEMPTS) {
            DomainEvent::query()->whereKey($event->id)->update([
                'status'           => DomainEvent::STATUS_FAILED,
                'processing_error' => $this->truncate($error),
                'last_error_at'    => now(),
                'claimed_by'       => null,
                'claimed_at'       => null,
                'next_retry_at'    => null,
                'updated_at'       => now(),
            ]);

            Log::error('outbox event dead-lettered after max attempts', [
                'event_id'   => $event->id,
                'event_type' => $event->event_type,
                'attempts'   => $attempts,
                'error'      => $this->truncate($error),
            ]);

            return false;
        }

        $delay = self::BACKOFF_SECONDS[$attempts] ?? end(self::BACKOFF_SECONDS);

        DomainEvent::query()->whereKey($event->id)->update([
            // Back to pending, not failed: 'failed' is now reserved for
            // dead-lettered events that will not be retried automatically, so
            // that status finally means something actionable.
            'status'           => DomainEvent::STATUS_PENDING,
            'processing_error' => $this->truncate($error),
            'last_error_at'    => now(),
            'claimed_by'       => null,
            'claimed_at'       => null,
            'next_retry_at'    => now()->addSeconds($delay),
            'updated_at'       => now(),
        ]);

        Log::warning('outbox event failed, retry scheduled', [
            'event_id'    => $event->id,
            'event_type'  => $event->event_type,
            'attempts'    => $attempts,
            'retry_in_s'  => $delay,
            'error'       => $this->truncate($error),
        ]);

        return true;
    }

    /**
     * Return events abandoned by a dead worker to the pending pool.
     *
     * A worker killed mid-flight (OOM, deploy, SIGKILL) leaves its rows in
     * 'processing' with nobody working them. Without this they are invisible to
     * the claim query forever — the outbox equivalent of a lost message.
     *
     * Retried immediately rather than with backoff: the event never got a fair
     * attempt, and the attempt counter still bounds it.
     *
     * @return int number of events recovered
     */
    public function recoverStaleClaims(?int $olderThanSeconds = null): int
    {
        $cutoff = now()->subSeconds($olderThanSeconds ?? self::STALE_CLAIM_SECONDS);

        $stale = DomainEvent::query()
            ->where('status', DomainEvent::STATUS_PROCESSING)
            ->where(function ($q) use ($cutoff) {
                $q->where('claimed_at', '<=', $cutoff)->orWhereNull('claimed_at');
            })
            ->pluck('id');

        if ($stale->isEmpty()) {
            return 0;
        }

        $recovered = DomainEvent::query()
            ->whereIn('id', $stale)
            ->where('status', DomainEvent::STATUS_PROCESSING)
            ->update([
                'status'           => DomainEvent::STATUS_PENDING,
                'claimed_by'       => null,
                'claimed_at'       => null,
                'next_retry_at'    => null,
                'processing_error' => 'Recovered from a stale claim (worker presumed dead).',
                'updated_at'       => now(),
            ]);

        if ($recovered > 0) {
            Log::warning('recovered stale outbox claims', [
                'count'                => $recovered,
                'older_than_seconds'   => $olderThanSeconds ?? self::STALE_CLAIM_SECONDS,
            ]);
        }

        return $recovered;
    }

    /**
     * Operational counters for monitoring and the health endpoint.
     *
     * @return array<string,int>
     */
    public function backlog(): array
    {
        $byStatus = DomainEvent::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->all();

        return [
            'pending'            => (int) ($byStatus[DomainEvent::STATUS_PENDING] ?? 0),
            'processing'         => (int) ($byStatus[DomainEvent::STATUS_PROCESSING] ?? 0),
            'processed'          => (int) ($byStatus[DomainEvent::STATUS_PROCESSED] ?? 0),
            'failed'             => (int) ($byStatus[DomainEvent::STATUS_FAILED] ?? 0),
            'skipped'            => (int) ($byStatus[DomainEvent::STATUS_SKIPPED] ?? 0),
            'awaiting_retry'     => DomainEvent::query()
                ->where('status', DomainEvent::STATUS_PENDING)
                ->whereNotNull('next_retry_at')
                ->where('next_retry_at', '>', now())
                ->count(),
            'stale_processing'   => DomainEvent::query()
                ->where('status', DomainEvent::STATUS_PROCESSING)
                ->where('claimed_at', '<=', now()->subSeconds(self::STALE_CLAIM_SECONDS))
                ->count(),
        ];
    }

    protected function truncate(string $error): string
    {
        return mb_substr($error, 0, 1000);
    }
}
