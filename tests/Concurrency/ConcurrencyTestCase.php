<?php

namespace Tests\Concurrency;

use PHPUnit\Framework\TestCase;

/**
 * Base class for tests that need genuine multi-process concurrency.
 *
 * Deliberately extends PHPUnit's TestCase rather than Laravel's: booting the
 * framework here would drag in RefreshDatabase-style transaction wrapping and
 * the SQLite :memory: connection from phpunit.xml, both of which make real
 * lock contention impossible to observe.
 *
 * Instead each worker is a separate `php artisan tinker`-free PHP process that
 * boots the application itself against a real MySQL/MariaDB database. Workers
 * are released as close to simultaneously as the OS allows by having them all
 * spin on a shared start file, so the contention window is real rather than
 * theatrical.
 */
abstract class ConcurrencyTestCase extends TestCase
{
    protected string $workDir;

    /** @var array<string,string> */
    protected array $dbEnv = [];

    protected function setUp(): void
    {
        parent::setUp();

        $host = getenv('CF_CONCURRENCY_DB_HOST') ?: '127.0.0.1';
        $port = getenv('CF_CONCURRENCY_DB_PORT') ?: '3399';
        $name = getenv('CF_CONCURRENCY_DB_DATABASE') ?: 'chipper_farms_test';
        $user = getenv('CF_CONCURRENCY_DB_USERNAME') ?: 'root';
        $pass = getenv('CF_CONCURRENCY_DB_PASSWORD');

        $this->dbEnv = [
            'DB_CONNECTION'     => 'mysql',
            'DB_HOST'           => $host,
            'DB_PORT'           => $port,
            'DB_DATABASE'       => $name,
            'DB_USERNAME'       => $user,
            'DB_PASSWORD'       => $pass === false ? '' : $pass,
            // A real queue/cache is not needed, but the DB cache store would
            // require tables that may not exist yet.
            'CACHE_STORE'       => 'array',
            'SESSION_DRIVER'    => 'array',
            'QUEUE_CONNECTION'  => 'sync',
            'APP_ENV'           => 'testing',
        ];

        $this->assertDatabaseReachable();

        $this->workDir = sys_get_temp_dir() . '/cf-concurrency-' . getmypid();
        if (! is_dir($this->workDir)) {
            mkdir($this->workDir, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->workDir) && is_dir($this->workDir)) {
            foreach (glob($this->workDir . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->workDir);
        }

        parent::tearDown();
    }

    /**
     * Fail with an actionable message when no server is reachable.
     *
     * This is an assertion rather than markTestSkipped() on purpose. The
     * original audit produced no concurrency evidence at all; a suite that
     * quietly skips itself would recreate exactly that blind spot.
     */
    protected function assertDatabaseReachable(): void
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s',
            $this->dbEnv['DB_HOST'],
            $this->dbEnv['DB_PORT'],
            $this->dbEnv['DB_DATABASE'],
        );

        try {
            new \PDO($dsn, $this->dbEnv['DB_USERNAME'], $this->dbEnv['DB_PASSWORD'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 5,
            ]);
        } catch (\PDOException $e) {
            $this->fail(
                "The Concurrency suite requires a reachable MySQL/MariaDB instance.\n"
                . "Tried: {$dsn} as {$this->dbEnv['DB_USERNAME']}\n"
                . "Error: {$e->getMessage()}\n"
                . 'See tests/Concurrency/README.md. This fails rather than skips by design.',
            );
        }
    }

    /**
     * Run a PHP snippet in N concurrent processes, released together.
     *
     * The snippet is executed with the application booted and $workerId in
     * scope. Whatever it echoes is captured and returned per worker.
     *
     * $isolation optionally sets the session transaction isolation level in every
     * worker before the snippet runs. This is not a detail — it decides whether
     * some races are observable at all. See runConcurrently()'s callers and
     * NumberingConcurrencyTest for why READ COMMITTED matters here.
     *
     * @return array<int, array{exit:int, out:string}>
     */
    protected function runConcurrently(int $workers, string $snippet, ?string $isolation = null): array
    {
        if ($isolation !== null) {
            // Whitelisted rather than interpolated freely: this string reaches SQL.
            $allowed = ['READ COMMITTED', 'REPEATABLE READ', 'SERIALIZABLE', 'READ UNCOMMITTED'];

            if (! in_array($isolation, $allowed, true)) {
                $this->fail("Unsupported isolation level '{$isolation}'.");
            }
        }

        $basePath  = dirname(__DIR__, 2);
        $startFile = $this->workDir . '/start';

        $scripts = [];
        for ($i = 0; $i < $workers; $i++) {
            $script = $this->workDir . "/worker{$i}.php";
            file_put_contents($script, $this->buildWorkerScript($basePath, $startFile, $i, $snippet, $isolation));
            $scripts[$i] = $script;
        }

        // Launch every worker first; they block until the start file appears, so
        // the contention window is genuine rather than staggered by process
        // startup cost (which is tens of milliseconds and would otherwise
        // serialise the very race under test).
        $procs = [];
        foreach ($scripts as $i => $script) {
            $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
            $env = array_merge(getenv(), $this->dbEnv);
            $proc = proc_open(['php', $script], $descriptors, $pipes, $basePath, $env);

            if (! is_resource($proc)) {
                $this->fail("Failed to launch concurrency worker {$i}.");
            }

            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
            $procs[$i] = ['proc' => $proc, 'pipes' => $pipes, 'out' => '', 'err' => ''];
        }

        // Wait for EVERY worker to signal readiness before releasing them.
        //
        // A fixed sleep here is not good enough and quietly defeats the whole
        // suite: booting Laravel takes a few hundred milliseconds, and with N
        // concurrent boots on limited cores some workers are still starting when
        // the start file appears. Those find it already present, skip the spin
        // and run staggered — so the processes never actually contend and the
        // test passes against broken code. An earlier revision of this harness
        // did exactly that and passed against the unfixed NumberingService.
        $readyDeadline = microtime(true) + 60;
        while (microtime(true) < $readyDeadline) {
            if (count(glob($this->workDir . '/ready-*') ?: []) >= $workers) {
                break;
            }
            usleep(20_000);
        }

        $ready = count(glob($this->workDir . '/ready-*') ?: []);
        if ($ready < $workers) {
            foreach ($procs as $p) {
                proc_terminate($p['proc']);
            }
            $this->fail("Only {$ready} of {$workers} concurrency workers became ready; cannot assert contention.");
        }

        // Every worker is parked on the spin loop. Release them together.
        file_put_contents($startFile, '1');

        $results = [];
        $deadline = microtime(true) + 60;

        while ($procs !== [] && microtime(true) < $deadline) {
            foreach ($procs as $i => &$p) {
                $p['out'] .= (string) stream_get_contents($p['pipes'][1]);
                $p['err'] .= (string) stream_get_contents($p['pipes'][2]);

                $status = proc_get_status($p['proc']);
                if (! $status['running']) {
                    $p['out'] .= (string) stream_get_contents($p['pipes'][1]);
                    $p['err'] .= (string) stream_get_contents($p['pipes'][2]);
                    fclose($p['pipes'][1]);
                    fclose($p['pipes'][2]);
                    proc_close($p['proc']);

                    $results[$i] = [
                        'exit' => $status['exitcode'],
                        'out'  => trim($p['out']),
                        'err'  => trim($p['err']),
                    ];
                    unset($procs[$i]);
                }
            }
            unset($p);
            usleep(20_000);
        }

        foreach ($procs as $i => $p) {
            proc_terminate($p['proc']);
            $results[$i] = ['exit' => -1, 'out' => trim($p['out']), 'err' => 'TIMED OUT'];
        }

        ksort($results);

        return $results;
    }

    protected function buildWorkerScript(
        string $basePath,
        string $startFile,
        int $workerId,
        string $snippet,
        ?string $isolation = null,
    ): string {
        $escapedStart = var_export($startFile, true);

        // Set isolation BEFORE the spin loop. Doing it after the release would
        // add a round-trip per worker and stagger them by exactly the interval
        // the race needs to be observable.
        $isolationStatement = $isolation === null
            ? ''
            : "Illuminate\\Support\\Facades\\DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL {$isolation}');";

        return <<<PHP
<?php
require {$this->exportPath($basePath)} . '/vendor/autoload.php';
\$app = require {$this->exportPath($basePath)} . '/bootstrap/app.php';
\$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();

\$workerId = {$workerId};

{$isolationStatement}

// Signal readiness AFTER the framework is booted, so the parent releases us
// only once every worker has paid its startup cost. Then spin on the start
// file so all workers contend at the same instant.
\$start = {$escapedStart};
file_put_contents(dirname(\$start) . '/ready-' . {$workerId}, '1');

\$limit = microtime(true) + 60;
while (! file_exists(\$start) && microtime(true) < \$limit) {
    usleep(500);
}

try {
{$snippet}
} catch (\\Throwable \$e) {
    fwrite(STDERR, get_class(\$e) . ': ' . \$e->getMessage());
    exit(1);
}
PHP;
    }

    protected function exportPath(string $path): string
    {
        return var_export($path, true);
    }

    /**
     * Run an artisan command against the concurrency database.
     */
    protected function artisan(string ...$args): array
    {
        $basePath = dirname(__DIR__, 2);
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $env = array_merge(getenv(), $this->dbEnv);

        $proc = proc_open(
            array_merge(['php', 'artisan'], $args),
            $descriptors,
            $pipes,
            $basePath,
            $env,
        );

        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($proc);

        return ['exit' => $exit, 'out' => $out, 'err' => $err];
    }
}
