<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\UserRole;
use App\Domains\Identity\Services\RoleResolver;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class RoleResolutionTest extends IdentityTestCase
{
    public function test_active_assignment_grants_the_role(): void
    {
        $user = $this->makeUser();
        $this->assignRole($user, RoleCode::CandidateExternal);

        $this->assertTrue(app(RoleResolver::class)->hasRole($user, RoleCode::CandidateExternal));
        $this->assertFalse(app(RoleResolver::class)->hasRole($user, RoleCode::HrAdmin));
    }

    public function test_revoked_assignment_no_longer_grants_the_role_but_the_row_remains(): void
    {
        $user = $this->makeUser();
        $assignment = $this->assignRole($user, RoleCode::CandidateStudentFinalYear);

        $assignment->forceFill(['revoked_at' => now()])->save();

        $this->assertFalse(app(RoleResolver::class)->hasRole($user, RoleCode::CandidateStudentFinalYear));
        // INV-025: history is never deleted.
        $this->assertDatabaseHas('user_roles', ['id' => $assignment->getKey()]);
        $this->assertCount(1, app(RoleResolver::class)->assignmentHistory($user));
    }

    public function test_reassignment_after_revocation_is_permitted(): void
    {
        $user = $this->makeUser();
        $first = $this->assignRole($user, RoleCode::CandidateStudentFinalYear);
        $first->forceFill(['revoked_at' => now()])->save();

        // The FINAL_YEAR_STUDENT -> ALUMNI transition, keeping full history.
        $this->assignRole($user, RoleCode::CandidateAlumni);
        $second = $this->assignRole($user, RoleCode::CandidateStudentFinalYear);

        $this->assertTrue($second->isActive());
        $this->assertCount(3, app(RoleResolver::class)->assignmentHistory($user));
        $this->assertCount(2, app(RoleResolver::class)->activeRoles($user));
    }

    public function test_duplicate_active_assignment_is_refused_by_postgresql(): void
    {
        $user = $this->makeUser();
        $this->assignRole($user, RoleCode::Auditor);

        try {
            $this->assignRole($user, RoleCode::Auditor);
            $this->fail('Expected the partial unique index to refuse a second active assignment.');
        } catch (QueryException $e) {
            // uq_user_roles_user_role_active — the DB is the race guard, not the app.
            $this->assertSame('23505', $e->getCode());
        }
    }

    public function test_has_any_role_matches_a_set(): void
    {
        $user = $this->makeUser();
        $this->assignRole($user, RoleCode::CareerCenterStaff);

        $resolver = app(RoleResolver::class);
        $this->assertTrue($resolver->hasAnyRole($user, [RoleCode::CareerCenterStaff, RoleCode::HrAdmin]));
        $this->assertFalse($resolver->hasAnyRole($user, [RoleCode::HrAdmin, RoleCode::SuperAdmin]));
        $this->assertFalse($resolver->hasAnyRole($user, []));
    }

    public function test_public_is_not_a_persisted_role(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->assertSame(11, Role::query()->count());
        $this->assertDatabaseMissing('roles', ['code' => 'PUBLIC']);
        $this->assertNull(Role::query()->where('code', 'PUBLIC')->first());
    }

    public function test_seeded_role_codes_match_the_frozen_catalogue_exactly(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $persisted = DB::table('roles')->orderBy('code')->pluck('code')->all();
        $expected = array_map(static fn (RoleCode $c): string => $c->value, RoleCode::cases());
        sort($expected);

        $this->assertSame($expected, $persisted);
    }

    public function test_selector_role_alone_never_grants_stage_access(): void
    {
        $user = $this->makeUser();
        $this->assignRole($user, RoleCode::Selector);
        $resolver = app(RoleResolver::class);

        // The role is held...
        $this->assertTrue($resolver->hasRole($user, RoleCode::Selector));

        // ...but it authorises being ASSIGNED, never candidate access (INV-037).
        $this->assertFalse($resolver->grantsStageAccess($user, RoleCode::Selector));
        $this->assertFalse(RoleCode::Selector->grantsObjectAccessAlone());

        // And no assignment exists, so nothing is in scope.
        $this->assertSame(0, DB::table('selection_stage_assignments')
            ->where('selector_user_id', $user->getKey())
            ->whereNull('revoked_at')
            ->count());
    }

    public function test_active_roles_returns_role_code_enums(): void
    {
        $user = $this->makeUser();
        $this->assignRole($user, RoleCode::CompanyRecruiter);

        $roles = app(RoleResolver::class)->activeRoles($user);

        $this->assertCount(1, $roles);
        $this->assertSame(RoleCode::CompanyRecruiter, $roles->first());
    }

    public function test_user_role_scopes_separate_active_and_revoked(): void
    {
        $user = $this->makeUser();
        $active = $this->assignRole($user, RoleCode::CompanyAdmin);
        $revoked = $this->assignRole($user, RoleCode::Auditor);
        $revoked->forceFill(['revoked_at' => now()])->save();

        $this->assertSame(1, UserRole::query()->where('user_id', $user->getKey())->active()->count());
        $this->assertSame(1, UserRole::query()->where('user_id', $user->getKey())->revoked()->count());
        $this->assertTrue($active->isActive());
        $this->assertFalse($revoked->refresh()->isActive());
    }
}
