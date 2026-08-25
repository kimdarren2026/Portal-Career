<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Domains\Candidate\Support\CandidateDocumentUploadLimiter;
use App\Domains\Company\Support\CompanyMemberInviteLimiter;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use App\Support\RateLimiting\RollingWindowLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use ReflectionClass;
use Tests\Feature\Identity\IdentityTestCase;
use Throwable;

/**
 * Mechanism-level proof for the shared limiter, exercised through both frozen
 * wrappers. The shared class implements the window; each wrapper owns its own
 * policy values, and neither may borrow the other's.
 */
final class RollingWindowLimiterTest extends IdentityTestCase
{
    public function test_both_wrappers_keep_their_own_policy_and_never_share_a_window(): void
    {
        $this->assertSame(20, CandidateDocumentUploadLimiter::LIMIT);
        $this->assertSame(3600, CandidateDocumentUploadLimiter::WINDOW_SECONDS);
        $this->assertSame(20, CompanyMemberInviteLimiter::LIMIT);
        $this->assertSame(3600, CompanyMemberInviteLimiter::WINDOW_SECONDS);
        $this->assertSame(
            [],
            (new ReflectionClass(RollingWindowLimiter::class))->getConstants(),
            'The shared limiter must hold no policy constants of its own.',
        );

        $actor = $this->actor('shared-window@example.test');
        $upload = app(CandidateDocumentUploadLimiter::class);
        $invite = app(CompanyMemberInviteLimiter::class);
        $upload->clear($actor);
        $invite->clear($actor);

        for ($i = 0; $i < CandidateDocumentUploadLimiter::LIMIT; $i++) {
            $this->assertFalse($upload->checkAndRecord($actor)['blocked']);
        }
        $this->assertTrue($upload->checkAndRecord($actor)['blocked']);
        $this->assertFalse($invite->checkAndRecord($actor)['blocked'], 'Namespaces must not share a window.');

        $upload->clear($actor);
        $invite->clear($actor);
    }

    public function test_retry_after_is_derived_from_the_oldest_attempt_still_inside_the_window(): void
    {
        $actor = $this->actor('retry-after@example.test');
        $limiter = app(CompanyMemberInviteLimiter::class);
        $limiter->clear($actor);

        $this->assertFalse($limiter->checkAndRecord($actor)['blocked']);
        $this->travel(600)->seconds();
        for ($i = 1; $i < CompanyMemberInviteLimiter::LIMIT; $i++) {
            $this->assertFalse($limiter->checkAndRecord($actor)['blocked']);
        }

        $this->travel(300)->seconds();
        $blocked = $limiter->checkAndRecord($actor);
        $this->assertTrue($blocked['blocked']);

        // oldest_active_attempt + 3600 - now  ==  0 + 3600 - 900  ==  2700
        $this->assertEqualsWithDelta(2700, $blocked['retry_after'], 2.0);

        $limiter->clear($actor);
        $this->travelBack();
    }

    public function test_window_rolls_partially_and_returns_exactly_one_slot(): void
    {
        $actor = $this->actor('partial-roll@example.test');
        $limiter = app(CompanyMemberInviteLimiter::class);
        $limiter->clear($actor);

        $limiter->checkAndRecord($actor);
        $this->travel(600)->seconds();
        for ($i = 1; $i < CompanyMemberInviteLimiter::LIMIT; $i++) {
            $limiter->checkAndRecord($actor);
        }
        $this->assertTrue($limiter->checkAndRecord($actor)['blocked']);

        $this->travel(CompanyMemberInviteLimiter::WINDOW_SECONDS + 1 - 600)->seconds();

        $this->assertFalse($limiter->checkAndRecord($actor)['blocked'], 'The freed slot must be usable.');
        $this->assertTrue($limiter->checkAndRecord($actor)['blocked'], 'Only one slot may have been freed.');

        $limiter->clear($actor);
        $this->travelBack();
    }

    public function test_key_is_non_sensitive_and_carries_a_finite_ttl(): void
    {
        $actor = $this->actor('key-privacy@example.test');
        $limiter = app(CompanyMemberInviteLimiter::class);
        $limiter->clear($actor);
        $limiter->checkAndRecord($actor);

        $key = 'company:member-invite:'.hash('sha256', (string) $actor->getKey());
        $redis = Redis::connection('cache');

        $this->assertSame(1, (int) $redis->command('ZCARD', [$key]));
        $ttl = (int) $redis->command('TTL', [$key]);
        $this->assertGreaterThan(0, $ttl, 'The window key must expire.');
        $this->assertLessThanOrEqual(CompanyMemberInviteLimiter::WINDOW_SECONDS, $ttl);

        $this->assertStringNotContainsString($actor->email, $key);
        $this->assertStringNotContainsString('@', $key);

        // Collision-resistant members: same-second attempts cannot overwrite.
        $limiter->checkAndRecord($actor);
        $limiter->checkAndRecord($actor);
        $this->assertSame(3, (int) $redis->command('ZCARD', [$key]));

        $limiter->clear($actor);
    }

    public function test_limiter_fails_closed_when_redis_is_unavailable(): void
    {
        $actor = $this->actor('redis-down@example.test');

        config(['database.redis.cache.port' => 1]);
        Redis::purge('cache');
        Cache::purge('redis');

        $admitted = null;
        try {
            $admitted = app(CompanyMemberInviteLimiter::class)->checkAndRecord($actor);
        } catch (Throwable) {
            // Fail closed: the attempt is never reported as admitted.
        }

        $this->assertNull($admitted, 'A Redis outage must not admit the protected operation.');
    }

    private function actor(string $email): User
    {
        return $this->makeUser($email, UserStatus::Active);
    }
}
