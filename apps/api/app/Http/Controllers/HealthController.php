<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Operational health endpoints (DEPLOYMENT_ARCHITECTURE.md §5).
 *
 * - `GET /health/live`  — the process responds. Used by restart supervision.
 *   Never touches a dependency: a slow database must not trigger a pod restart.
 * - `GET /health/ready` — PostgreSQL, Redis and object storage are reachable.
 *   Used by the load balancer to pull an instance that cannot serve requests.
 *
 * Both are unauthenticated and disclose nothing beyond pass/fail per
 * dependency — no host, credential, path, driver name or exception text.
 */
final class HealthController extends Controller
{
    public function live(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }

    public function ready(): JsonResponse
    {
        $checks = [
            'database' => $this->ok(static fn () => DB::connection()->select('select 1')),
            'redis' => $this->ok(static fn () => Redis::connection('default')->ping()),
            'storage' => $this->ok(static fn () => Storage::disk(config('filesystems.default'))->directoryExists('')),
        ];

        $ready = ! in_array('error', $checks, true);

        return response()->json([
            'status' => $ready ? 'ready' : 'degraded',
            'checks' => $checks,
        ], $ready ? 200 : 503);
    }

    /** @param callable():mixed $probe */
    private function ok(callable $probe): string
    {
        try {
            $probe();

            return 'ok';
        } catch (Throwable) {
            // Deliberately no detail — a readiness probe is a boolean signal.
            return 'error';
        }
    }
}
