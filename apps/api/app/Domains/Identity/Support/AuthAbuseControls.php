<?php

declare(strict_types=1);

namespace App\Domains\Identity\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Redis;

/**
 * Redis-backed credential-adjacent rate limits and the temporary login lock.
 * All identifier fragments are digests so Redis key inspection cannot expose
 * an email address or bearer token.
 */
final class AuthAbuseControls
{
    /** @var array<string, array{ip: array{0:int,1:int}, subject: array{0:int,1:int}, subject_type: string}> */
    private const POLICIES = [
        'registration' => ['ip' => [5, 30], 'subject' => [3, 60], 'subject_type' => 'identity'],
        'login' => ['ip' => [20, 5], 'subject' => [10, 15], 'subject_type' => 'identity'],
        'resend-verification' => ['ip' => [10, 15], 'subject' => [3, 15], 'subject_type' => 'identity'],
        'verify-email' => ['ip' => [20, 10], 'subject' => [10, 10], 'subject_type' => 'token'],
        'forgot-password' => ['ip' => [20, 15], 'subject' => [5, 15], 'subject_type' => 'identity'],
        'reset-password' => ['ip' => [20, 15], 'subject' => [5, 15], 'subject_type' => 'token'],
    ];

    /** @return array{blocked: bool, retry_after: int} */
    public function checkAndRecord(string $operation, Request $request): array
    {
        $policy = self::POLICIES[$operation];
        $keys = $this->rateKeys($operation, $request, $policy['subject_type']);
        $retryAfter = 0;

        foreach ($keys as $key) {
            if (RateLimiter::tooManyAttempts($key['key'], $key['attempts'])) {
                $retryAfter = max($retryAfter, RateLimiter::availableIn($key['key']));
            }
        }

        if ($retryAfter > 0) {
            return ['blocked' => true, 'retry_after' => $retryAfter];
        }

        foreach ($keys as $key) {
            RateLimiter::hit($key['key'], $key['minutes'] * 60);
        }

        return ['blocked' => false, 'retry_after' => 0];
    }

    public function loginLockRetryAfter(string $email): int
    {
        $ttl = (int) Redis::connection('cache')->command('TTL', [$this->loginLockKey($email)]);

        return max(0, $ttl);
    }

    /** Returns remaining lock seconds; zero means the failure did not activate one. */
    public function recordCredentialFailure(string $email): int
    {
        $digest = $this->digest(EmailNormalizer::normalize($email));
        $mutex = Cache::store('redis')->lock("auth:login:failure-mutex:{$digest}", 5);

        return $mutex->block(2, function () use ($digest): int {
            $redis = Redis::connection('cache');
            $key = "auth:login:failures:{$digest}";
            $lockKey = "auth:login:lock:{$digest}";
            $now = now()->getTimestamp();

            $redis->command('ZREMRANGEBYSCORE', [$key, '-inf', (string) ($now - 900)]);
            $redis->command('ZADD', [$key, $now, $now.'.'.bin2hex(random_bytes(8))]);
            $redis->command('EXPIRE', [$key, 900]);
            $attempts = (int) $redis->command('ZCARD', [$key]);

            if ($attempts >= 8) {
                $redis->command('SET', [$lockKey, '1', 'EX', 900]);
                $redis->command('DEL', [$key]);

                return 900;
            }

            return 0;
        });
    }

    public function clearCredentialFailures(string $email): void
    {
        $digest = $this->digest(EmailNormalizer::normalize($email));
        $mutex = Cache::store('redis')->lock("auth:login:failure-mutex:{$digest}", 5);
        $mutex->block(2, function () use ($digest): void {
            Redis::connection('cache')->command('DEL', ["auth:login:failures:{$digest}", "auth:login:lock:{$digest}"]);
        });
    }

    /** @return list<array{key:string,attempts:int,minutes:int}> */
    private function rateKeys(string $operation, Request $request, string $subjectType): array
    {
        $policy = self::POLICIES[$operation];
        $subject = $subjectType === 'identity'
            ? EmailNormalizer::normalize((string) $request->input('email', ''))
            : (string) $request->input('token', '');
        $ip = (string) ($request->ip() ?? 'unknown');

        return [
            [
                'key' => "auth:{$operation}:ip:".$this->digest($ip),
                'attempts' => $policy['ip'][0],
                'minutes' => $policy['ip'][1],
            ],
            [
                'key' => "auth:{$operation}:{$subjectType}:".$this->digest($subject),
                'attempts' => $policy['subject'][0],
                'minutes' => $policy['subject'][1],
            ],
        ];
    }

    private function loginLockKey(string $email): string
    {
        return 'auth:login:lock:'.$this->digest(EmailNormalizer::normalize($email));
    }

    private function digest(string $value): string
    {
        return hash('sha256', $value);
    }
}
