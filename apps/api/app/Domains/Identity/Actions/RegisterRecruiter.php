<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use App\Domains\Identity\Support\EmailNormalizer;
use App\Domains\Notification\Support\OutboxWriter;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/** Registers a recruiter Identity only; company onboarding is intentionally absent. */
final class RegisterRecruiter
{
    public function __construct(
        private readonly SetPassword $setPassword,
        private readonly IssueEmailVerificationToken $issueVerification,
        private readonly OutboxWriter $outbox,
        private readonly AuditWriter $audit,
    ) {}

    /** @return array{user: User, created: bool} */
    public function execute(string $name, string $email, string $password): array
    {
        $normalized = EmailNormalizer::normalize($email);

        try {
            return DB::transaction(function () use ($name, $email, $password, $normalized): array {
                /** @var User|null $existing */
                $existing = User::query()->where('email_normalized', $normalized)->lockForUpdate()->first();
                if ($existing !== null) {
                    $this->queueExistingAccountNotice($existing);

                    return ['user' => $existing, 'created' => false];
                }

                $user = new User();
                $user->forceFill([
                    'name' => $name,
                    'email' => trim($email),
                    'email_normalized' => $normalized,
                    'status' => UserStatus::PendingEmailVerification,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->save();
                $this->setPassword->execute($user, $password);

                // FSD v1.1 Open Question #6 leaves the first recruiter's
                // default company role unresolved. Identity registration must
                // not pre-empt that business decision by assigning either
                // COMPANY_RECRUITER or COMPANY_ADMIN here. Company membership
                // and its role are created by the later company flow.
                $this->issueVerification->execute($user);
                $this->audit->record('registration', $user, 'user', (int) $user->getKey(), [
                    'account_type' => 'RECRUITER',
                ]);

                return ['user' => $user->refresh(), 'created' => true];
            });
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() !== '23505') {
                throw $exception;
            }

            /** @var User $existing */
            $existing = User::query()->where('email_normalized', $normalized)->firstOrFail();
            $this->queueExistingAccountNotice($existing);

            return ['user' => $existing, 'created' => false];
        }
    }

    private function queueExistingAccountNotice(User $user): void
    {
        $this->outbox->queue(
            recipient: $user->email,
            templateReference: 'identity.account-already-registered',
            payload: ['user_name' => $user->name],
            relatedObjectType: 'user',
            relatedObjectId: (int) $user->getKey(),
        );
    }
}
