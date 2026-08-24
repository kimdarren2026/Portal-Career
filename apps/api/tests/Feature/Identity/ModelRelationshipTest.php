<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Domains\Identity\Actions\IssueEmailVerificationToken;
use App\Domains\Identity\Actions\IssuePasswordResetToken;
use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Models\PasswordCredential;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\DB;

final class ModelRelationshipTest extends IdentityTestCase
{
    public function test_user_maps_to_the_frozen_users_table_shape(): void
    {
        $user = $this->makeUser();

        $row = DB::table('users')->where('id', $user->getKey())->first();

        $this->assertSame('candidate@example.test', $row->email);
        $this->assertSame('candidate@example.test', $row->email_normalized);
        $this->assertSame('ACTIVE', $row->status);
        // The frozen users table has neither of these columns.
        $this->assertObjectNotHasProperty('password', $row);
        $this->assertObjectNotHasProperty('remember_token', $row);
    }

    public function test_user_has_one_password_credential(): void
    {
        $user = $this->makeUser();

        $this->assertInstanceOf(PasswordCredential::class, $user->passwordCredential);
        $this->assertSame((int) $user->getKey(), (int) $user->passwordCredential->user_id);
        $this->assertNotEmpty($user->passwordCredential->password_hash);
    }

    public function test_user_role_relationships_separate_active_from_history(): void
    {
        $user = $this->makeUser();
        $active = $this->assignRole($user, RoleCode::CandidateExternal);
        $revoked = $this->assignRole($user, RoleCode::Auditor);
        $revoked->forceFill(['revoked_at' => now()])->save();

        $user->refresh();

        $this->assertCount(2, $user->userRoles);
        $this->assertCount(1, $user->activeUserRoles);
        $this->assertSame((int) $active->getKey(), (int) $user->activeUserRoles->first()->getKey());
        $this->assertSame(RoleCode::CandidateExternal, $user->activeUserRoles->first()->role->code);
    }

    public function test_user_has_many_email_verification_tokens(): void
    {
        $user = $this->makeUser();
        app(IssueEmailVerificationToken::class)->execute($user, queueEmail: false);

        $this->assertCount(1, $user->refresh()->emailVerificationTokens);
        $this->assertSame((int) $user->getKey(), (int) $user->emailVerificationTokens->first()->user_id);
    }

    public function test_user_has_many_password_reset_tokens(): void
    {
        $user = $this->makeUser();
        app(IssuePasswordResetToken::class)->execute($user->email, queueEmail: false);

        $this->assertCount(1, $user->refresh()->passwordResetTokens);
    }

    public function test_user_role_exposes_actor_relationships(): void
    {
        $admin = $this->makeUser('admin@example.test');
        $user = $this->makeUser('member@example.test');
        $assignment = $this->assignRole($user, RoleCode::HrAdmin);
        $assignment->forceFill(['assigned_by' => $admin->getKey()])->save();

        $this->assertSame((int) $admin->getKey(), (int) $assignment->refresh()->assignedBy->getKey());
    }

    public function test_by_email_scope_resolves_through_normalized_column(): void
    {
        $this->makeUser('Mixed.Case@Example.TEST');

        $found = User::query()->byEmail('mixed.case@example.test')->first();

        $this->assertNotNull($found);
        $this->assertSame('Mixed.Case@Example.TEST', $found->email);
    }
}
