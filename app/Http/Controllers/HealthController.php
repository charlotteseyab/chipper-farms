<?php

namespace App\Http\Controllers;

use App\Finance\Outbox\OutboxClaimer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Liveness and readiness endpoints (CF-P1-022).
 *
 * Laravel's built-in health route (`/up`, wired in bootstrap/app.php) answers
 * one question: did PHP boot? That is liveness. It returns 200 while the
 * database is unreachable, the queue is stalled and the outbox is thousands of
 * events behind — so a load balancer keeps sending traffic to an instance that
 * cannot serve a single useful request. /up is kept as-is because that is
 * exactly what a liveness probe should be; readiness is a different question and
 * needs its own endpoint.
 *
 * WHAT IS DELIBERATELY NOT EXPOSED
 * --------------------------------
 * The remediation brief is explicit that health output must not leak
 * credentials, stack traces, internal addresses or sensitive counts to
 * unauthorised callers. So:
 *
 *   - the PUBLIC response is a bare ok/degraded plus which checks failed BY
 *     NAME. "database: fail" tells an operator where to look and tells an
 *     attacker nothing they could not learn by watching the service be down.
 *   - exception messages are NEVER included publicly. A driver error routinely
 *     contains the host, port, database name and username.
 *   - numeric detail (outbox backlog, failed job counts) requires a token,
 *     because a queue-depth graph is a genuine operational signal about when
 *     the system is weakest.
 *
 * The token is compared with hash_equals() so the endpoint cannot be used as a
 * timing oracle to recover it.
 */
class HealthController extends Controller
{
    /**
     * Readiness: can this instance actually serve traffic?
     *
     * Returns 503 when any critical dependency is unavailable, which is what
     * makes it usable as a load-balancer or orchestrator readiness probe —
     * returning 200 with a "degraded" body would keep traffic flowing to a
     * broken instance.
     */
    public function ready(Request $request): JsonResponse
    {
        $detailed = $this->authorisedForDetail($request);

        $checks = [
            'database' => $this->checkDatabase(),
            'cache'    => $this->checkCache(),
            'storage'  => $this->checkStorage(),
        ];

        // The outbox is reported but is NOT a readiness failure. A backlog means
        // finance postings are lagging, which needs an alert — not the removal
        // of this instance from the load balancer, which would make it worse.
        $outbox = $this->checkOutbox();

        $failed = array_keys(array_filter($checks, fn (array $c) => $c['ok'] === false));
        $healthy = $failed === [];

        $body = [
            'status'    => $healthy ? 'ok' : 'degraded',
            'checks'    => array_map(fn (array $c) => $c['ok'] ? 'ok' : 'fail', $checks),
            'timestamp' => now()->toIso8601String(),
        ];

        if ($detailed) {
            $body['detail'] = [
                'checks' => $checks,
                'outbox' => $outbox,
                'app'    => [
                    'environment' => app()->environment(),
                    'debug'       => (bool) config('app.debug'),
                    'version'     => app()->version(),
                ],
            ];
        }

        return response()->json($body, $healthy ? 200 : 503);
    }

    /**
     * Operational metrics for monitoring. Always requires the token — every
     * value here is a number an attacker would find useful.
     */
    public function metrics(Request $request): JsonResponse
    {
        if (! $this->authorisedForDetail($request)) {
            // 404 rather than 401: an unauthenticated caller should not learn
            // that a metrics endpoint exists here at all.
            abort(404);
        }

        return response()->json([
            'outbox'      => $this->checkOutbox(),
            'failed_jobs' => $this->failedJobCount(),
            'queue'       => [
                'connection' => config('queue.default'),
                'pending'    => $this->pendingJobCount(),
            ],
            'timestamp'   => now()->toIso8601String(),
        ]);
    }

    /**
     * Detail requires a shared secret supplied out of band.
     *
     * When HEALTH_CHECK_TOKEN is unset, detail is refused entirely rather than
     * defaulting to open. An unset secret must never mean "no authentication
     * required" — that is how monitoring endpoints become public dashboards.
     */
    protected function authorisedForDetail(Request $request): bool
    {
        $expected = (string) config('app.health_check_token', '');

        if ($expected === '') {
            return false;
        }

        $provided = (string) ($request->header('X-Health-Token') ?? $request->query('token', ''));

        if ($provided === '') {
            return false;
        }

        return hash_equals($expected, $provided);
    }

    /** @return array{ok:bool, note?:string} */
    protected function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            DB::select('select 1');

            return ['ok' => true];
        } catch (\Throwable $e) {
            // The message is logged, never returned: driver errors routinely
            // contain host, port, database name and username.
            report($e);

            return ['ok' => false, 'note' => 'connection failed'];
        }
    }

    /** @return array{ok:bool, note?:string} */
    protected function checkCache(): array
    {
        try {
            $key = 'health:probe:' . bin2hex(random_bytes(4));
            Cache::put($key, '1', 10);
            $ok = Cache::get($key) === '1';
            Cache::forget($key);

            return $ok ? ['ok' => true] : ['ok' => false, 'note' => 'write succeeded but read did not'];
        } catch (\Throwable $e) {
            report($e);

            return ['ok' => false, 'note' => 'cache unavailable'];
        }
    }

    /**
     * Storage is checked by WRITING, not by checking existence. A read-only
     * mount or a full disk passes an existence check and then fails on the
     * first attachment upload.
     *
     * @return array{ok:bool, note?:string}
     */
    protected function checkStorage(): array
    {
        try {
            $disk = Storage::disk(config('filesystems.default'));
            $path = 'health/probe-' . bin2hex(random_bytes(4)) . '.txt';

            $disk->put($path, 'ok');
            $ok = $disk->get($path) === 'ok';
            $disk->delete($path);

            return $ok ? ['ok' => true] : ['ok' => false, 'note' => 'write succeeded but read did not'];
        } catch (\Throwable $e) {
            report($e);

            return ['ok' => false, 'note' => 'storage not writable'];
        }
    }

    /** @return array<string,int|string> */
    protected function checkOutbox(): array
    {
        try {
            return app(OutboxClaimer::class)->backlog();
        } catch (\Throwable $e) {
            report($e);

            return ['error' => 'unavailable'];
        }
    }

    protected function failedJobCount(): int
    {
        try {
            return DB::table('failed_jobs')->count();
        } catch (\Throwable) {
            return -1;
        }
    }

    protected function pendingJobCount(): int
    {
        try {
            return DB::table('jobs')->count();
        } catch (\Throwable) {
            return -1;
        }
    }
}
