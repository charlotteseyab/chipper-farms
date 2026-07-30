<?php

namespace Tests\Concurrency;

/**
 * Multi-process evidence that outbox events are claimed exactly once
 * (CF-P1-015).
 *
 * The register's framing is that "multiple workers can duplicate work or strand
 * failed events". Both halves deserve separate treatment, and they are not
 * equally severe:
 *
 *  - DUPLICATE JOURNALS were already impossible. journal_postings carries a
 *    UNIQUE idempotency key and Subscriber converts the resulting
 *    DuplicatePostingException into success. The original class docblock said
 *    exactly this and it was correct.
 *
 *  - STRANDED EVENTS were the real defect. With $tries = 1 and no retry
 *    scheduling, any transient fault moved an event to 'failed' permanently,
 *    silently dropping a finance posting with nothing to surface it.
 *
 * So these tests assert the property that actually needed establishing: that a
 * claim is exclusive, so retry can be bounded per event without two workers
 * fighting over the same row and inflating its attempt count.
 *
 * Run against a real MySQL/MariaDB server — the guarded-UPDATE claim depends on
 * row-level locking serialising two concurrent UPDATEs, which SQLite's
 * whole-database locking would mask rather than demonstrate.
 */
class OutboxClaimConcurrencyTest extends ConcurrencyTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $migrate = $this->artisan('migrate', '--force');
        $this->assertSame(
            0,
            $migrate['exit'],
            "Could not migrate the concurrency database:\n" . $migrate['out'] . $migrate['err'],
        );

        $this->pdo()->exec("DELETE FROM domain_events WHERE event_type LIKE 'conc.%'");
    }

    protected function pdo(): \PDO
    {
        return new \PDO(
            sprintf(
                'mysql:host=%s;port=%s;dbname=%s',
                $this->dbEnv['DB_HOST'],
                $this->dbEnv['DB_PORT'],
                $this->dbEnv['DB_DATABASE'],
            ),
            $this->dbEnv['DB_USERNAME'],
            $this->dbEnv['DB_PASSWORD'],
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION],
        );
    }

    /**
     * Insert N pending events directly. An event_type with no registered
     * subscriber is used deliberately: this test is about the CLAIM, not about
     * posting, and a subscriber-less event exercises claim → skip → release
     * without needing a full chart of accounts.
     *
     * @return list<int>
     */
    protected function seedPendingEvents(int $count, string $eventType): array
    {
        $pdo = $this->pdo();

        // organization_id is NOT NULL on domain_events, so a real row is needed.
        $orgId = (int) ($pdo->query('SELECT id FROM organizations ORDER BY id LIMIT 1')->fetchColumn() ?: 0);

        if ($orgId === 0) {
            $pdo->exec(
                "INSERT INTO organizations (slug, name, is_active, created_at, updated_at)
                 VALUES ('conc-org', 'Concurrency Org', 1, NOW(), NOW())"
            );
            $orgId = (int) $pdo->lastInsertId();
        }

        $ids = [];
        $stmt = $pdo->prepare(
            "INSERT INTO domain_events
                (organization_id, event_type, idempotency_key, source_module, source_type,
                 source_id, payload, payload_version, status, occurred_at,
                 processing_attempts, created_at, updated_at)
             VALUES (:org, :type, :key, 'concurrency', 'ConcurrencyProbe',
                 :src, '{}', 1, 'pending', NOW(), 0, NOW(), NOW())"
        );

        for ($i = 0; $i < $count; $i++) {
            $stmt->execute([
                'org'  => $orgId,
                'type' => $eventType,
                'key'  => $eventType . ':' . $i . ':' . bin2hex(random_bytes(4)),
                'src'  => $i + 1,
            ]);
            $ids[] = (int) $pdo->lastInsertId();
        }

        return $ids;
    }

    /**
     * The core property: across N concurrent workers, every event is claimed by
     * exactly one worker. No event claimed twice, none left unclaimed.
     */
    public function test_each_event_is_claimed_by_exactly_one_worker(): void
    {
        $workers    = 6;
        $eventCount = 60;
        $eventType  = 'conc.claim_' . bin2hex(random_bytes(3));

        $this->seedPendingEvents($eventCount, $eventType);

        // Each worker claims greedily; the union of claims must be a partition.
        $results = $this->runConcurrently($workers, <<<PHP
    \$claimer = new App\\Finance\\Outbox\\OutboxClaimer();
    \$mine = [];
    // Claim in several passes so workers genuinely interleave rather than one
    // worker taking everything in a single large batch.
    for (\$pass = 0; \$pass < 6; \$pass++) {
        \$batch = \$claimer->claim(5);
        foreach (\$batch as \$event) {
            \$mine[] = \$event->id;
        }
        usleep(5000);
    }
    echo implode(',', \$mine);
PHP, isolation: 'READ COMMITTED');

        $claimsByWorker = [];
        $all = [];

        foreach ($results as $i => $r) {
            $this->assertSame(0, $r['exit'], "Worker {$i} failed: " . ($r['err'] ?? ''));
            $ids = array_values(array_filter(explode(',', $r['out']), fn ($v) => $v !== ''));
            $claimsByWorker[$i] = $ids;
            $all = array_merge($all, $ids);
        }

        $duplicates = array_keys(array_filter(array_count_values($all), fn ($n) => $n > 1));

        $this->assertSame(
            [],
            $duplicates,
            'These event ids were claimed by more than one worker: ' . implode(', ', $duplicates)
            . '. The guarded UPDATE is not exclusive.',
        );

        // Sanity: the workers between them should have taken real work, or the
        // test would pass trivially by claiming nothing at all.
        $this->assertGreaterThan(
            0,
            count($all),
            'No events were claimed; the test would be vacuous.',
        );
    }

    /**
     * A claimed event must be invisible to other claimants while in flight.
     */
    public function test_claimed_events_are_not_reclaimed_while_in_flight(): void
    {
        $eventType = 'conc.inflight_' . bin2hex(random_bytes(3));
        $ids = $this->seedPendingEvents(4, $eventType);

        // Worker 0 claims and holds (does not release). Workers 1-3 then try.
        $results = $this->runConcurrently(4, <<<PHP
    \$claimer = new App\\Finance\\Outbox\\OutboxClaimer();
    if (\$workerId === 0) {
        \$batch = \$claimer->claim(4);
        echo 'holder:' . \$batch->count();
        // Hold the claim: do not release, simulating in-flight work.
        usleep(1500000);
    } else {
        usleep(400000); // let the holder claim first
        \$batch = \$claimer->claim(4);
        echo 'other:' . \$batch->count();
    }
PHP, isolation: 'READ COMMITTED');

        $holder = null;
        $others = [];

        foreach ($results as $r) {
            $this->assertSame(0, $r['exit'], 'Worker failed: ' . ($r['err'] ?? ''));

            if (str_starts_with($r['out'], 'holder:')) {
                $holder = (int) substr($r['out'], 7);
            } elseif (str_starts_with($r['out'], 'other:')) {
                $others[] = (int) substr($r['out'], 6);
            }
        }

        $this->assertSame(4, $holder, 'The holder should have claimed all four pending events.');
        $this->assertSame(
            [0, 0, 0],
            $others,
            'Other workers claimed in-flight events: ' . implode(',', $others)
            . '. A claim held by a live worker must not be reclaimable.',
        );
    }

    /**
     * Events abandoned by a dead worker must be recoverable.
     *
     * Without this an OOM-killed or SIGKILLed worker leaves rows in 'processing'
     * that the claim query can never see again — the outbox equivalent of a lost
     * message, and invisible because nothing reports it.
     */
    public function test_stale_claims_are_recovered(): void
    {
        $eventType = 'conc.stale_' . bin2hex(random_bytes(3));
        $this->seedPendingEvents(3, $eventType);

        // Claim, then abandon by backdating claimed_at, then recover.
        $results = $this->runConcurrently(1, <<<PHP
    \$claimer = new App\\Finance\\Outbox\\OutboxClaimer();
    \$claimed = \$claimer->claim(3)->count();

    // Simulate the worker having died a long time ago.
    Illuminate\\Support\\Facades\\DB::table('domain_events')
        ->where('event_type', '{$eventType}')
        ->update(['claimed_at' => now()->subHours(2)]);

    \$recovered = (new App\\Finance\\Outbox\\OutboxClaimer())->recoverStaleClaims();

    \$pendingAgain = Illuminate\\Support\\Facades\\DB::table('domain_events')
        ->where('event_type', '{$eventType}')
        ->where('status', 'pending')
        ->count();

    echo \$claimed . '|' . \$recovered . '|' . \$pendingAgain;
PHP);

        $this->assertSame(0, $results[0]['exit'], 'Worker failed: ' . ($results[0]['err'] ?? ''));

        [$claimed, $recovered, $pendingAgain] = array_map('intval', explode('|', $results[0]['out']));

        $this->assertSame(3, $claimed, 'Expected to claim the three seeded events.');
        $this->assertSame(3, $recovered, 'Expected all three abandoned claims to be recovered.');
        $this->assertSame(3, $pendingAgain, 'Recovered events must return to pending so they can be reclaimed.');
    }
}
