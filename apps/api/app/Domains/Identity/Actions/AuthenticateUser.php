<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Data\AuthenticationOutcome;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Authenticate credentials and establish a session (FR-AUTH-005).
 *
 * Identity resolution goes through IdentityUserProvider, so `A@B.com` and
 * `a@b.com` reach the same account (INV-001). The password hash comes from
 * `password_credentials` via User::getAuthPassword().
 *
 * Failure semantics are deliberate: an unknown account and a wrong password
 * both return InvalidCredentials, so the result cannot be used to enumerate
 * registered addresses (FSD §9.3). Status refusals are distinguishable only
 * *after* the password has been verified, so they leak nothing about accounts
 * the caller cannot already authenticate as.
 */
final class AuthenticateUser
{
    /**
     * @return array{outcome: AuthenticationOutcome, user: User|null}
     */
    public function execute(string $email, string $password): array
    {
        $guard = $this->guard();

        // validate() checks credentials without establishing a session, so a
        // suspended account never gets one even momentarily.
        if (! $guard->validate(['email' => $email, 'password' => $password])) {
            // An unknown identity must still pay one adaptive-hash operation.
            // Without this comparable work, account existence is measurable by
            // timing even though the outward error is intentionally identical.
            if (! User::query()->byEmail($email)->exists()) {
                Hash::make($password);
            }

            return ['outcome' => AuthenticationOutcome::InvalidCredentials, 'user' => null];
        }

        /** @var User $user */
        $user = User::query()->byEmail($email)->firstOrFail();

        $refusal = match ($user->status) {
            UserStatus::Suspended => AuthenticationOutcome::AccountSuspended,
            UserStatus::Disabled => AuthenticationOutcome::AccountDisabled,
            default => null,
        };

        if ($refusal !== null) {
            return ['outcome' => $refusal, 'user' => null];
        }

        $guard->login($user);
        $this->regenerateSession();

        return ['outcome' => AuthenticationOutcome::Succeeded, 'user' => $user];
    }

    private function guard(): StatefulGuard
    {
        /** @var StatefulGuard $guard */
        $guard = Auth::guard('web');

        return $guard;
    }

    /** Defeats session fixation (SECURITY_ARCHITECTURE.md §1). */
    private function regenerateSession(): void
    {
        if (app()->bound('session') && app('session')->isStarted()) {
            app('session')->regenerate();
        }
    }
}
