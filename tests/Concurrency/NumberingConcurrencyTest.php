<?php

namespace Tests\Concurrency;

/**
 * Real multi-process concurrency evidence for document numbering
 * (CF-P1-013, CF-P1-014).
 *
 * The defect: NumberingService::next() locked an existing sequence row with
 * SELECT ... FOR UPDATE, but when the row did not yet exist it called
 * DocumentSequence::create() with no conflict handling. Two writers allocating
 * the FIRST number for a given (organization, type, year) both found no row and
 * both inserted; the unique key rejected one with a QueryException. The failure
 * window is the first document of a period — the first invoice of the year, the
 * first receipt after go-live — which is exactly when load is least forgiving.
 *
 * These tests use separate OS processes with independent connections, released
 * together. That distinction matters: the pre-existing
 * NumberingServiceTest::test_concurrent_allocations_produce_unique_numbers makes
 * 100 sequential calls in one process and says so in its own comment. It proves
 * monotonicity, not concurrency safety — no amount of sequential calling can
 * exercise a lost-update or duplicate-insert race.
 *
 * WHY READ COMMITTED, AND WHY IT IS NOT AN ARBITRARY CHOICE
 * --------------------------------------------------------
 * These tests were first written without setting an isolation level and PASSED
 * against the ORIGINAL, unfixed code — so they proved nothing. The reason is
 * InnoDB's default REPEATABLE READ: a `SELECT ... FOR UPDATE` matching no rows
 * takes a GAP LOCK over the index range, which blocks the competing INSERT until
 * the first transaction commits. The loser's locking read then sees the winner's
 * row. The engine serialises the race for free, so the missing conflict handling
 * is invisible.
 *
 * Under READ COMMITTED no gap locks are taken, and the defect is immediate.
 * Verified directly against MariaDB 11.4 with the original code and 8 workers:
 * 7 allocated numbers and 1 died with
 *
 *   Illuminate\Database\UniqueConstraintViolationException:
 *   SQLSTATE[23000]: 1062 Duplicate entry '1-<type>-2026'
 *      for key 'document_sequences_org_type_year_unique'
 *
 * With the fix, the same run produced 8 unique numbers and no failures.
 *
 * So CF-P1-013 is real but ISOLATION-DEPENDENT — a nuance the register does not
 * record, since it was reached by source inspection. It is not a theoretical
 * concern: READ COMMITTED is a common production setting (and the default on
 * several managed MySQL platforms), and the application does not pin an
 * isolation level in config/database.php, so it inherits whatever the server
 * gives it. Testing at the server's default alone would have certified the bug
 * as fixed while leaving it live on any deployment configured differently.
 */
class NumberingConcurrencyTest extends ConcurrencyTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure the schema exists, then clear the table under test.
        $migrate = $this->artisan('migrate', '--force');
        $this->assertSame(
            0,
            $migrate['exit'],
            "Could not migrate the concurrency database:\n" . $migrate['out'] . $migrate['err'],
        );

        $this->truncateSequences();
    }

    protected function truncateSequences(): void
    {
        $pdo = new \PDO(
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

        $pdo->exec("DELETE FROM document_sequences WHERE sequence_type LIKE 'conc_%'");
    }

    /**
     * The core first-use race: N workers all allocating the very first number
     * for the same brand-new sequence.
     */
    public function test_concurrent_first_use_allocations_are_unique(): void
    {
        $workers = 8;
        $type    = 'conc_first_' . bin2hex(random_bytes(3));

        $results = $this->runConcurrently($workers, <<<PHP
    \$number = app(App\\Services\\Numbering\\NumberingService::class)->next(
        sequenceType: '{$type}',
        prefix: 'CT',
        year: 2026,
        organizationId: 1,
    );
    echo \$number;
PHP, isolation: 'READ COMMITTED');

        $numbers = [];
        foreach ($results as $i => $r) {
            $this->assertSame(
                0,
                $r['exit'],
                "Worker {$i} failed instead of allocating a number. This is the CF-P1-013 "
                . "race: two first writers insert, the unique key rejects one.\n"
                . 'stderr: ' . ($r['err'] ?? ''),
            );
            $numbers[] = $r['out'];
        }

        $this->assertCount($workers, $numbers, 'Every worker must return a number.');
        $this->assertCount(
            $workers,
            array_unique($numbers),
            'Duplicate document numbers were allocated: ' . implode(', ', $numbers),
        );

        // Numbers must be exactly 1..N with no gaps — a gap would mean a lost
        // increment, which the row lock is there to prevent.
        sort($numbers);
        $expected = [];
        for ($n = 1; $n <= $workers; $n++) {
            $expected[] = sprintf('CT-2026-%06d', $n);
        }
        $this->assertSame(
            $expected,
            $numbers,
            'Allocated numbers are not the contiguous range 1..' . $workers
            . ', which indicates a lost increment under contention.',
        );
    }

    /**
     * Global (organisation-less) sequences.
     *
     * MySQL/MariaDB treat NULLs as DISTINCT in a unique index, verified directly
     * against MariaDB 11.4: two rows with organization_id = NULL, the same type
     * and the same year coexist. So the migration's unique key protects
     * org-scoped sequences but gives global ones NO duplicate protection — a
     * gap not recorded in the original register.
     *
     * lockSequence() therefore orders by id and takes one row, so every
     * allocator converges on the same row and serialises on one lock even if a
     * duplicate row exists. This test pins that: numbers must still be unique.
     */
    public function test_concurrent_first_use_allocations_are_unique_for_global_sequences(): void
    {
        $workers = 6;
        $type    = 'conc_global_' . bin2hex(random_bytes(3));

        $results = $this->runConcurrently($workers, <<<PHP
    \$number = app(App\\Services\\Numbering\\NumberingService::class)->next(
        sequenceType: '{$type}',
        prefix: 'GT',
        year: 2026,
        organizationId: null,
    );
    echo \$number;
PHP, isolation: 'READ COMMITTED');

        $numbers = [];
        foreach ($results as $i => $r) {
            $this->assertSame(0, $r['exit'], "Worker {$i} failed: " . ($r['err'] ?? ''));
            $numbers[] = $r['out'];
        }

        $this->assertCount(
            $workers,
            array_unique($numbers),
            'Duplicate global sequence numbers allocated: ' . implode(', ', $numbers)
            . '. NULL organization_id is not protected by the unique key, so the '
            . 'allocator must converge on one row by id.',
        );
    }

    /**
     * Sustained contention on an existing sequence — the lost-update case.
     */
    public function test_concurrent_sustained_allocations_have_no_lost_increments(): void
    {
        $workers        = 6;
        $perWorker      = 15;
        $type           = 'conc_sustained_' . bin2hex(random_bytes(3));
        $expectedTotal  = $workers * $perWorker;

        $results = $this->runConcurrently($workers, <<<PHP
    \$service = app(App\\Services\\Numbering\\NumberingService::class);
    \$mine = [];
    for (\$i = 0; \$i < {$perWorker}; \$i++) {
        \$mine[] = \$service->next(
            sequenceType: '{$type}',
            prefix: 'ST',
            year: 2026,
            organizationId: 1,
        );
    }
    echo implode(',', \$mine);
PHP, isolation: 'READ COMMITTED');

        $all = [];
        foreach ($results as $i => $r) {
            $this->assertSame(0, $r['exit'], "Worker {$i} failed: " . ($r['err'] ?? ''));
            $all = array_merge($all, explode(',', $r['out']));
        }

        $this->assertCount($expectedTotal, $all);
        $this->assertCount(
            $expectedTotal,
            array_unique($all),
            'Lost increment under sustained contention: '
            . (count($all) - count(array_unique($all))) . ' duplicate number(s) allocated.',
        );
    }
}
