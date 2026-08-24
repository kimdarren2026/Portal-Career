<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Domains\Identity\Actions\AuthenticateUser;
use App\Domains\Identity\Actions\LogoutUser;
use App\Domains\Identity\Data\AuthenticationOutcome;
use App\Domains\Identity\Enums\UserStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AuthenticationTest extends IdentityTestCase
{
    public function test_valid_credentials_authenticate(): void
    {
        $user = $this->makeUser(password: 'correct-horse-battery');

        $result = app(AuthenticateUser::class)->execute('candidate@example.test', 'correct-horse-battery');

        $this->assertSame(AuthenticationOutcome::Succeeded, $result['outcome']);
        $this->assertTrue(Auth::guard('web')->check());
        $this->assertSame((int) $user->getKey(), (int) Auth::guard('web')->id());
    }

    public function test_case_variant_email_resolves_the_same_identity(): void
    {
        $user = $this->makeUser('Mixed.Case@Example.TEST', password: 'correct-horse-battery');

        $result = app(AuthenticateUser::class)->execute('MIXED.CASE@example.test', 'correct-horse-battery');

        $this->assertSame(AuthenticationOutcome::Succeeded, $result['outcome']);
        $this->assertSame((int) $user->getKey(), (int) $result['user']->getKey());
    }

    public function test_incorrect_password_fails(): void
    {
        $this->makeUser(password: 'correct-horse-battery');

        $result = app(AuthenticateUser::class)->execute('candidate@example.test', 'wrong-password');

        $this->assertSame(AuthenticationOutcome::InvalidCredentials, $result['outcome']);
        $this->assertFalse(Auth::guard('web')->check());
    }

    public function test_unknown_account_is_indistinguishable_from_a_wrong_password(): void
    {
        $this->makeUser(password: 'correct-horse-battery');

        $unknown = app(AuthenticateUser::class)->execute('nobody@example.test', 'correct-horse-battery');
        $wrong = app(AuthenticateUser::class)->execute('candidate@example.test', 'wrong-password');

        // Identical outcome — the result must not enumerate registered accounts.
        $this->assertSame($unknown['outcome'], $wrong['outcome']);
        $this->assertSame(AuthenticationOutcome::InvalidCredentials, $unknown['outcome']);
    }

    public function test_suspended_account_cannot_authenticate(): void
    {
        $this->makeUser(status: UserStatus::Suspended, password: 'correct-horse-battery');

        $result = app(AuthenticateUser::class)->execute('candidate@example.test', 'correct-horse-battery');

        $this->assertSame(AuthenticationOutcome::AccountSuspended, $result['outcome']);
        $this->assertFalse(Auth::guard('web')->check());
    }

    public function test_disabled_account_cannot_authenticate(): void
    {
        $this->makeUser(status: UserStatus::Disabled, password: 'correct-horse-battery');

        $result = app(AuthenticateUser::class)->execute('candidate@example.test', 'correct-horse-battery');

        $this->assertSame(AuthenticationOutcome::AccountDisabled, $result['outcome']);
        $this->assertFalse(Auth::guard('web')->check());
    }

    public function test_pending_email_verification_may_sign_in_but_may_not_act(): void
    {
        // FR-AUTH-005: verification is required to act, not to sign in.
        $user = $this->makeUser(status: UserStatus::PendingEmailVerification, verifiedAt: null);

        $result = app(AuthenticateUser::class)->execute($user->email, 'correct-horse-battery');

        $this->assertSame(AuthenticationOutcome::Succeeded, $result['outcome']);
        $this->assertTrue($user->status->canAuthenticate());
        $this->assertFalse($user->status->canAct());
        $this->assertFalse($user->hasVerifiedEmail());
    }

    public function test_logout_invalidates_the_session(): void
    {
        $this->makeUser(password: 'correct-horse-battery');
        app(AuthenticateUser::class)->execute('candidate@example.test', 'correct-horse-battery');
        $this->assertTrue(Auth::guard('web')->check());

        app(LogoutUser::class)->execute();

        $this->assertFalse(Auth::guard('web')->check());
        $this->assertNull(Auth::guard('web')->id());
    }

    public function test_password_hash_is_never_stored_on_the_users_table(): void
    {
        $user = $this->makeUser(password: 'correct-horse-battery');

        $this->assertFalse(Schema::hasColumn('users', 'password'));
        $this->assertFalse(Schema::hasColumn('users', 'remember_token'));

        $row = (array) DB::table('users')->where('id', $user->getKey())->first();
        foreach ($row as $value) {
            if (is_string($value)) {
                $this->assertStringNotContainsString('$2y$', $value);
            }
        }

        $hash = DB::table('password_credentials')->where('user_id', $user->getKey())->value('password_hash');
        $this->assertNotEmpty($hash);
        $this->assertTrue(password_verify('correct-horse-battery', $hash));
    }

    public function test_password_credential_hash_is_hidden_from_serialization(): void
    {
        $user = $this->makeUser();

        $this->assertArrayNotHasKey('password_hash', $user->passwordCredential->toArray());
        $this->assertArrayNotHasKey('email_normalized', $user->toArray());
    }
}
