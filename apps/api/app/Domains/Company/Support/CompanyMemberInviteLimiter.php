<?php

declare(strict_types=1);

namespace App\Domains\Company\Support;

use App\Domains\Identity\Models\User;
use App\Support\RateLimiting\RollingWindowLimiter;

/**
 * Company member invitation window — API_CONTRACT.md Part I §11.9.
 *
 * The subject is the acting Company Admin, not the company, so one admin
 * cannot spread the same probing budget across several companies they
 * administer. Attempts are counted, not successes: an account-probing loop
 * costs exactly what a working invite costs.
 */
final class CompanyMemberInviteLimiter
{
    public const LIMIT = 20;
    public const WINDOW_SECONDS = 3600;

    private const NAMESPACE = 'company:member-invite';

    public function __construct(private readonly RollingWindowLimiter $limiter) {}

    /** @return array{blocked: bool, retry_after: int} */
    public function checkAndRecord(User $actor): array
    {
        return $this->limiter->attempt(self::NAMESPACE, (string) $actor->getKey(), self::LIMIT, self::WINDOW_SECONDS);
    }

    public function clear(User $actor): void
    {
        $this->limiter->clear(self::NAMESPACE, (string) $actor->getKey());
    }
}
