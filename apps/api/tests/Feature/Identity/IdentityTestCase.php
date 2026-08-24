<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Domains\Identity\Actions\SetPassword;
use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Shared fixtures for Identity tests. Every test runs against real PostgreSQL
 * (RefreshDatabase), never SQLite — the schema depends on partial unique
 * indexes and CHECK constraints SQLite does not share.
 */
abstract class IdentityTestCase extends TestCase
{
    use RefreshDatabase;

    protected int $sequence = 0;

    protected function makeUser(
        string $email = 'candidate@example.test',
        UserStatus $status = UserStatus::Active,
        ?string $password = 'correct-horse-battery',
        ?string $verifiedAt = 'now',
    ): User {
        $normalizer = \App\Domains\Identity\Support\EmailNormalizer::normalize($email);

        $user = new User();
        $user->forceFill([
            'name' => 'Test User',
            'email' => $email,
            'email_normalized' => $normalizer,
            'status' => $status,
            'email_verified_at' => $verifiedAt === null ? null : now(),
            'created_at' => now(),
            'updated_at' => now(),
        ])->save();

        if ($password !== null) {
            app(SetPassword::class)->execute($user, $password);
        }

        return $user->refresh();
    }

    protected function role(RoleCode $code): Role
    {
        return Role::query()->firstOrCreate(
            ['code' => $code->value],
            ['name' => $code->value, 'created_at' => now(), 'updated_at' => now()],
        );
    }

    protected function assignRole(User $user, RoleCode $code): UserRole
    {
        $assignment = new UserRole();
        $assignment->forceFill([
            'user_id' => $user->getKey(),
            'role_id' => $this->role($code)->getKey(),
            'assigned_at' => now(),
        ])->save();

        return $assignment;
    }
}
