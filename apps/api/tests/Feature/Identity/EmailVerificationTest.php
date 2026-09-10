<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Domains\Identity\Actions\IssueEmailVerificationToken;
use App\Domains\Identity\Actions\RevokeEmailVerificationTokens;
use App\Domains\Identity\Actions\VerifyEmailToken;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Exceptions\InvalidTokenException;
use App\Domains\Identity\Models\EmailVerificationToken;
use App\Domains\Identity\Support\TokenHasher;
use Illuminate\Support\Facades\DB;

final class EmailVerificationTest extends IdentityTestCase
{
    public function test_issuing_persists_only_the_hash(): void
    {
        $user = $this->makeUser(status: UserStatus::PendingEmailVerification, verifiedAt: null);

        $raw = app(IssueEmailVerificationToken::class)->execute($user, queueEmail: false);

        $stored = DB::table('email_verification_tokens')->where('user_id', $user->getKey())->first();
        $this->assertNotSame($raw, $stored->token_hash);
        $this->assertSame(TokenHasher::hash($raw), $stored->token_hash);
        $this->assertSame(64, strlen($raw));

        // The raw token appears nowhere in the row.
        foreach ((array) $stored as $value) {
            if (is_string($value)) {
                $this->assertStringNotContainsString($raw, $value);
            }
        }
    }

    public function test_valid_token_verifies_and_activates_the_account(): void
    {
        $user = $this->makeUser(status: UserStatus::PendingEmailVerification, verifiedAt: null);
        $raw = app(IssueEmailVerificationToken::class)->execute($user, queueEmail: false);

        $verified = app(VerifyEmailToken::class)->execute($raw);

        $this->assertSame(UserStatus::Active, $verified->status);
        $this->assertNotNull($verified->email_verified_at);
        $this->assertNotNull(EmailVerificationToken::query()
            ->where('user_id', $user->getKey())->value('used_at'));
    }

    public function test_the_same_token_cannot_be_used_twice(): void
    {
        $user = $this->makeUser(status: UserStatus::PendingEmailVerification, verifiedAt: null);
        $raw = app(IssueEmailVerificationToken::class)->execute($user, queueEmail: false);
        app(VerifyEmailToken::class)->execute($raw);

        $this->expectException(InvalidTokenException::class);

        try {
            app(VerifyEmailToken::class)->execute($raw);
        } catch (InvalidTokenException $e) {
            $this->assertSame('AUTH_TOKEN_ALREADY_USED', $e->errorCode);
            throw $e;
        }
    }

    public function test_expired_token_is_rejected(): void
    {
        $user = $this->makeUser(status: UserStatus::PendingEmailVerification, verifiedAt: null);
        $raw = app(IssueEmailVerificationToken::class)->execute($user, ttlMinutes: 60, queueEmail: false);

        $this->travel(61)->minutes();

        try {
            app(VerifyEmailToken::class)->execute($raw);
            $this->fail('Expected the expired token to be rejected.');
        } catch (InvalidTokenException $e) {
            $this->assertSame('AUTH_TOKEN_EXPIRED', $e->errorCode);
        }
    }

    public function test_revoked_token_is_rejected(): void
    {
        $user = $this->makeUser(status: UserStatus::PendingEmailVerification, verifiedAt: null);
        $raw = app(IssueEmailVerificationToken::class)->execute($user, queueEmail: false);

        app(RevokeEmailVerificationTokens::class)->execute($user);

        try {
            app(VerifyEmailToken::class)->execute($raw);
            $this->fail('Expected the revoked token to be rejected.');
        } catch (InvalidTokenException $e) {
            $this->assertSame('AUTH_TOKEN_REVOKED', $e->errorCode);
        }
    }

    public function test_reissuing_revokes_the_previous_token(): void
    {
        $user = $this->makeUser(status: UserStatus::PendingEmailVerification, verifiedAt: null);
        $first = app(IssueEmailVerificationToken::class)->execute($user, queueEmail: false);
        $second = app(IssueEmailVerificationToken::class)->execute($user, queueEmail: false);

        try {
            app(VerifyEmailToken::class)->execute($first);
            $this->fail('Expected the superseded token to be rejected.');
        } catch (InvalidTokenException $e) {
            $this->assertSame('AUTH_TOKEN_REVOKED', $e->errorCode);
        }

        $this->assertSame(UserStatus::Active, app(VerifyEmailToken::class)->execute($second)->status);
    }

    public function test_unknown_token_is_rejected_without_disclosing_anything(): void
    {
        try {
            app(VerifyEmailToken::class)->execute(TokenHasher::generateRawToken());
            $this->fail('Expected an unknown token to be rejected.');
        } catch (InvalidTokenException $e) {
            $this->assertSame('AUTH_TOKEN_INVALID', $e->errorCode);
            $this->assertStringNotContainsString('user', strtolower($e->getMessage()));
        }
    }

    public function test_verification_writes_an_outbox_row_but_never_the_token(): void
    {
        $user = $this->makeUser(status: UserStatus::PendingEmailVerification, verifiedAt: null);

        $raw = app(IssueEmailVerificationToken::class)->execute($user, queueEmail: true);

        $outbox = DB::table('email_outbox')->where('recipient', $user->email)->first();
        $this->assertNotNull($outbox);
        // The row is written PENDING inside the business transaction; the
        // PGC-V1 / PD-B delivery worker may then advance it to SENT.
        $this->assertContains($outbox->status, ['PENDING', 'SENT']);
        // INV-015/INV-021: no raw token may travel in the outbox payload.
        $this->assertStringNotContainsString($raw, (string) $outbox->payload_reference);
    }

    public function test_suspended_user_verifying_does_not_become_active(): void
    {
        $user = $this->makeUser(status: UserStatus::Suspended, verifiedAt: null);
        $raw = app(IssueEmailVerificationToken::class)->execute($user, queueEmail: false);

        $verified = app(VerifyEmailToken::class)->execute($raw);

        // Verification records the email, it does not resurrect the account.
        $this->assertSame(UserStatus::Suspended, $verified->status);
        $this->assertNotNull($verified->email_verified_at);
    }
}
