<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;

/** Keeps resend verification enumeration-resistant at the HTTP boundary. */
final class ResendEmailVerification
{
    public function __construct(
        private readonly IssueEmailVerificationToken $issueVerification,
        private readonly AuditWriter $audit,
    ) {}

    public function execute(string $email): void
    {
        /** @var User|null $user */
        $user = User::query()->byEmail($email)->first();

        if ($user === null || $user->hasVerifiedEmail()) {
            return;
        }

        $this->issueVerification->execute($user);
        $this->audit->record('email_verification_resend', $user, 'user', (int) $user->getKey());
    }
}
