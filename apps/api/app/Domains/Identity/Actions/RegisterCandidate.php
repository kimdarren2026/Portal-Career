<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserRole;
use App\Domains\Identity\Support\AuditWriter;
use App\Domains\Identity\Support\EmailNormalizer;
use App\Domains\Notification\Support\OutboxWriter;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/** Registers the Identity-side candidate minimum; onboarding remains separate. */
final class RegisterCandidate
{
    public function __construct(
        private readonly SetPassword $setPassword,
        private readonly IssueEmailVerificationToken $issueVerification,
        private readonly OutboxWriter $outbox,
        private readonly AuditWriter $audit,
    ) {}

    /** @return array{user: User, created: bool} */
    public function execute(string $name, string $email, string $password, string $candidateType): array
    {
        $normalized = EmailNormalizer::normalize($email);

        try {
            return DB::transaction(function () use ($name, $email, $password, $candidateType, $normalized): array {
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
                DB::table('candidate_profiles')->insert([
                    'user_id' => $user->getKey(),
                    'current_candidate_type' => $candidateType,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->assignRole($user, match ($candidateType) {
                    'EXTERNAL' => RoleCode::CandidateExternal,
                    'FINAL_YEAR_STUDENT' => RoleCode::CandidateStudentFinalYear,
                    'ALUMNI' => RoleCode::CandidateAlumni,
                });
                $this->issueVerification->execute($user);
                $this->audit->record('registration', $user, 'user', (int) $user->getKey(), [
                    'account_type' => 'CANDIDATE',
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

    private function assignRole(User $user, RoleCode $code): void
    {
        $roleId = Role::query()->where('code', $code->value)->value('id');

        if ($roleId === null) {
            throw new \LogicException('The required role catalogue has not been seeded.');
        }

        (new UserRole())->forceFill([
            'user_id' => $user->getKey(),
            'role_id' => $roleId,
            'assigned_at' => now(),
        ])->save();
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
