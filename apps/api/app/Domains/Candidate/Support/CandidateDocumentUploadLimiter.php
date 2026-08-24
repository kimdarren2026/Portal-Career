<?php

declare(strict_types=1);

namespace App\Domains\Candidate\Support;

use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

/** Redis-backed, per-candidate rolling upload window. */
final class CandidateDocumentUploadLimiter
{
    public const LIMIT = 20;
    public const WINDOW_SECONDS = 3600;

    /** @return array{blocked: bool, retry_after: int} */
    public function checkAndRecord(User $actor): array
    {
        $subject = hash('sha256', (string) $actor->getKey());
        $lock = Cache::store('redis')->lock("candidate:document-upload:lock:{$subject}", 5);

        return $lock->block(2, function () use ($subject): array {
            $redis = Redis::connection('cache');
            $key = "candidate:document-upload:{$subject}";
            $now = now()->getTimestamp();

            $redis->command('ZREMRANGEBYSCORE', [$key, '-inf', (string) ($now - self::WINDOW_SECONDS)]);

            if ((int) $redis->command('ZCARD', [$key]) >= self::LIMIT) {
                return ['blocked' => true, 'retry_after' => $this->retryAfter($redis->command('ZRANGE', [$key, '0', '0', 'WITHSCORES']), $now)];
            }

            $redis->command('ZADD', [$key, $now, $now.'.'.Str::ulid()]);
            $redis->command('EXPIRE', [$key, self::WINDOW_SECONDS]);

            return ['blocked' => false, 'retry_after' => 0];
        });
    }

    public function clear(User $actor): void
    {
        Redis::connection('cache')->command('DEL', ["candidate:document-upload:".hash('sha256', (string) $actor->getKey())]);
    }

    /** @param array<mixed> $oldest */
    private function retryAfter(array $oldest, int $now): int
    {
        $values = array_values($oldest);
        $score = isset($values[1]) ? (int) $values[1] : (int) ($values[0] ?? $now);

        return max(1, ($score + self::WINDOW_SECONDS) - $now);
    }
}
