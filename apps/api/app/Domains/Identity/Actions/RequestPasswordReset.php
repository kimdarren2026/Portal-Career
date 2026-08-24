<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;

/** Issues a reset token without leaking whether the identity matched. */
final class RequestPasswordReset
{
    public function __construct(
        private readonly IssuePasswordResetToken $issueReset,
        private readonly AuditWriter $audit,
    ) {}

    public function execute(string $email): void
    {
        /** @var User|null $user */
        $user = User::query()->byEmail($email)->first();
        $this->issueReset->execute($email);

        if ($user !== null) {
            $this->audit->record('password_reset_requested', $user, 'user', (int) $user->getKey());
        }
    }
}
