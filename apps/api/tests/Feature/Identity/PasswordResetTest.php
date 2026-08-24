<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Domains\Identity\Actions\AuthenticateUser;
use App\Domains\Identity\Actions\IssuePasswordResetToken;
use App\Domains\Identity\Actions\ResetPasswordWithToken;
use App\Domains\Identity\Data\AuthenticationOutcome;
use App\Domains\Identity\Exceptions\InvalidTokenException;
use App\Domains\Identity\Support\TokenHasher;
use Illuminate\Support\Facades\DB;

final class PasswordResetTest extends IdentityTestCase
{
    public function test_issuing_persists_only_the_hash(): void
    {
        $user = $this->makeUser();

        $raw = app(IssuePasswordResetToken::class)->execute($user->email, queueEmail: false);

        $stored = DB::table('password_reset_tokens')->where('user_id', $user->getKey())->first();
        $this->assertNotSame($raw, $stored->token_hash);
        $this->assertSame(TokenHasher::hash($raw), $stored->token_hash);
    }

    public function test_unknown_address_returns_null_without_revealing_existence(): void
    {
        $this->makeUser();

        $result = app(IssuePasswordResetToken::class)->execute('nobody@example.test', queueEmail: false);

        $this->assertNull($result);
        $this->assertSame(0, DB::table('password_reset_tokens')->count());
    }

    public function test_valid_token_replaces_the_password_credential_hash(): void
    {
        $user = $this->makeUser(password: 'old-password-value');
        $before = DB::table('password_credentials')->where('user_id', $user->getKey())->value('password_hash');

        $raw = app(IssuePasswordResetToken::class)->execute($user->email, queueEmail: false);
        app(ResetPasswordWithToken::class)->execute($raw, 'new-password-value');

        $after = DB::table('password_credentials')->where('user_id', $user->getKey())->value('password_hash');
        $this->assertNotSame($before, $after);
        $this->assertTrue(password_verify('new-password-value', $after));
    }

    public function test_old_password_fails_and_new_password_succeeds(): void
    {
        $user = $this->makeUser(password: 'old-password-value');
        $raw = app(IssuePasswordResetToken::class)->execute($user->email, queueEmail: false);
        app(ResetPasswordWithToken::class)->execute($raw, 'new-password-value');

        $old = app(AuthenticateUser::class)->execute($user->email, 'old-password-value');
        $this->assertSame(AuthenticationOutcome::InvalidCredentials, $old['outcome']);

        $new = app(AuthenticateUser::class)->execute($user->email, 'new-password-value');
        $this->assertSame(AuthenticationOutcome::Succeeded, $new['outcome']);
    }

    public function test_second_consumption_is_rejected(): void
    {
        $user = $this->makeUser();
        $raw = app(IssuePasswordResetToken::class)->execute($user->email, queueEmail: false);
        app(ResetPasswordWithToken::class)->execute($raw, 'new-password-value');

        try {
            app(ResetPasswordWithToken::class)->execute($raw, 'another-password');
            $this->fail('Expected the consumed token to be rejected.');
        } catch (InvalidTokenException $e) {
            $this->assertSame('AUTH_TOKEN_ALREADY_USED', $e->errorCode);
        }
    }

    public function test_expired_token_is_rejected(): void
    {
        $user = $this->makeUser();
        $raw = app(IssuePasswordResetToken::class)->execute($user->email, ttlMinutes: 60, queueEmail: false);

        $this->travel(61)->minutes();

        try {
            app(ResetPasswordWithToken::class)->execute($raw, 'new-password-value');
            $this->fail('Expected the expired token to be rejected.');
        } catch (InvalidTokenException $e) {
            $this->assertSame('AUTH_TOKEN_EXPIRED', $e->errorCode);
        }
    }

    public function test_reissuing_revokes_the_previous_reset_token(): void
    {
        $user = $this->makeUser();
        $first = app(IssuePasswordResetToken::class)->execute($user->email, queueEmail: false);
        app(IssuePasswordResetToken::class)->execute($user->email, queueEmail: false);

        try {
            app(ResetPasswordWithToken::class)->execute($first, 'new-password-value');
            $this->fail('Expected the superseded token to be rejected.');
        } catch (InvalidTokenException $e) {
            $this->assertSame('AUTH_TOKEN_REVOKED', $e->errorCode);
        }
    }

    public function test_successful_reset_revokes_sibling_tokens(): void
    {
        $user = $this->makeUser();
        $raw = app(IssuePasswordResetToken::class)->execute($user->email, queueEmail: false);

        // A sibling issued out of band must not survive a successful reset.
        DB::table('password_reset_tokens')->insert([
            'user_id' => $user->getKey(),
            'token_hash' => TokenHasher::hash('sibling-token-value'),
            'expires_at' => now()->addHour(),
            'created_at' => now(),
        ]);

        app(ResetPasswordWithToken::class)->execute($raw, 'new-password-value');

        $siblingRevoked = DB::table('password_reset_tokens')
            ->where('user_id', $user->getKey())
            ->where('token_hash', TokenHasher::hash('sibling-token-value'))
            ->value('revoked_at');

        $this->assertNotNull($siblingRevoked);
    }

    public function test_reset_never_writes_a_password_column_to_users(): void
    {
        $user = $this->makeUser();
        $raw = app(IssuePasswordResetToken::class)->execute($user->email, queueEmail: false);
        app(ResetPasswordWithToken::class)->execute($raw, 'new-password-value');

        $row = (array) DB::table('users')->where('id', $user->getKey())->first();
        $this->assertArrayNotHasKey('password', $row);
        foreach ($row as $value) {
            if (is_string($value)) {
                $this->assertStringNotContainsString('$2y$', $value);
            }
        }
    }
}
