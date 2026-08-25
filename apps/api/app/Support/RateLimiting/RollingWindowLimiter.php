<?php

declare(strict_types=1);

namespace App\Support\RateLimiting;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

/**
 * Redis-backed rolling-window attempt limiter (API_CONTRACT.md Part I §11.7).
 *
 * One algorithm, one home. Every abuse-control window in the application keys
 * on a deterministic non-sensitive subject — never a raw email address (§11.4)
 * — counts *attempts* rather than successes, and reports `Retry-After` from the
 * oldest attempt still inside the window (§11.5).
 */
final class RollingWindowLimiter
{
    /** @return array{blocked: bool, retry_after: int} */
    public function attempt(string $namespace, string $subject, int $limit, int $windowSeconds): array
    {
        $key = $this->key($namespace, $subject);
        $lock = Cache::store('redis')->lock("{$key}:lock", 5);

        return $lock->block(2, function () use ($key, $limit, $windowSeconds): array {
            $redis = Redis::connection('cache');
            $now = now()->getTimestamp();

            $redis->command('ZREMRANGEBYSCORE', [$key, '-inf', (string) ($now - $windowSeconds)]);

            if ((int) $redis->command('ZCARD', [$key]) >= $limit) {
                return [
                    'blocked' => true,
                    'retry_after' => $this->retryAfter($redis->command('ZRANGE', [$key, '0', '0', 'WITHSCORES']), $now, $windowSeconds),
                ];
            }

            $redis->command('ZADD', [$key, $now, $now.'.'.Str::ulid()]);
            $redis->command('EXPIRE', [$key, $windowSeconds]);

            return ['blocked' => false, 'retry_after' => 0];
        });
    }

    public function clear(string $namespace, string $subject): void
    {
        Redis::connection('cache')->command('DEL', [$this->key($namespace, $subject)]);
    }

    private function key(string $namespace, string $subject): string
    {
        return $namespace.':'.hash('sha256', $subject);
    }

    /** @param array<mixed> $oldest */
    private function retryAfter(array $oldest, int $now, int $windowSeconds): int
    {
        $values = array_values($oldest);
        $score = isset($values[1]) ? (int) $values[1] : (int) ($values[0] ?? $now);

        return max(1, ($score + $windowSeconds) - $now);
    }
}
