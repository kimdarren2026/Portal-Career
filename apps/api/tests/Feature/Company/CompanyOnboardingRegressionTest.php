<?php

declare(strict_types=1);

namespace Tests\Feature\Company;

use App\Domains\Company\Actions\ChangeCompanyMemberRole;
use App\Domains\Company\Enums\CompanyStatus;
use App\Domains\Company\Exceptions\LastCompanyAdmin;
use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\CompanyMember;
use App\Domains\Company\Support\CompanyScope;
use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Identity\IdentityTestCase;

/** Regressions for defects found while reviewing the onboarding handoff. */
final class CompanyOnboardingRegressionTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_editing_a_company_in_a_locked_status_is_conflict_not_server_error(): void
    {
        [$recruiter, $company] = $this->companyFor('locked@example.test');
        DB::table('companies')->where('id', $company->id)->update(['verification_status' => CompanyStatus::PendingVerification->value]);

        $this->actingAs($recruiter)->patchJson("/companies/{$company->id}", ['name' => 'Renamed'])
            ->assertStatus(409)->assertJsonPath('error.code', 'COMPANY_INVALID_TRANSITION');
    }

    public function test_deactivated_membership_grants_no_company_scope(): void
    {
        [$recruiter, $company] = $this->companyFor('deactivated@example.test');
        DB::table('company_members')->where('company_id', $company->id)->where('user_id', $recruiter->id)
            ->update(['status' => 'INACTIVE']);

        // OL-1: a non-active membership grants nothing, and the row must not be
        // confirmed to exist by a 403.
        $this->assertNull(CompanyScope::findFor($recruiter->fresh(), (int) $company->id));
        $this->actingAs($recruiter)->getJson("/companies/{$company->id}")
            ->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
        $this->actingAs($recruiter)->patchJson("/companies/{$company->id}", ['name' => 'Nope'])->assertNotFound();
    }

    public function test_unknown_company_returns_the_contract_error_envelope(): void
    {
        $recruiter = $this->recruiter('envelope@example.test');

        $this->actingAs($recruiter)->getJson('/companies/987654')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND')
            ->assertJsonMissingPath('exception');
    }

    public function test_verification_notification_reaches_active_members_only(): void
    {
        [$recruiter, $company] = $this->companyFor('notify@example.test');
        $inactive = $this->recruiter('inactive-member@example.test');
        DB::table('company_members')->insert([
            'company_id' => $company->id, 'user_id' => $inactive->id, 'company_role' => 'COMPANY_RECRUITER',
            'status' => 'INACTIVE', 'joined_at' => now(),
        ]);
        $this->completeProfile($recruiter, $company);
        DB::table('company_documents')->insert([
            'company_id' => $company->id, 'document_type' => 'CUSTOM', 'storage_reference' => 'company/x.pdf',
            'status' => 'CURRENT', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($recruiter)->postJson("/companies/{$company->id}/submit-verification")->assertOk();

        $this->assertDatabaseHas('email_outbox', ['recipient' => $recruiter->email]);
        $this->assertDatabaseMissing('email_outbox', ['recipient' => $inactive->email]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $inactive->id]);
    }

    public function test_super_admin_may_review_per_authorization_matrix(): void
    {
        [$recruiter, $company] = $this->companyFor('superadmin-flow@example.test');
        $superAdmin = $this->makeUser('super@example.test', UserStatus::Active);
        $this->assignRole($superAdmin, RoleCode::SuperAdmin);
        DB::table('companies')->where('id', $company->id)->update(['verification_status' => CompanyStatus::PendingVerification->value]);

        $this->actingAs($superAdmin)->postJson("/companies/{$company->id}/verify")
            ->assertOk()->assertJsonPath('data.verification_status', 'VERIFIED');
    }

    public function test_reviewer_who_is_a_member_may_not_review_their_own_company(): void
    {
        [$recruiter, $company] = $this->companyFor('conflicted@example.test');
        $this->assignRole($recruiter, RoleCode::CareerCenterStaff);
        DB::table('companies')->where('id', $company->id)->update(['verification_status' => CompanyStatus::PendingVerification->value]);

        $this->actingAs($recruiter->fresh())->postJson("/companies/{$company->id}/verify")
            ->assertForbidden()->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_potential_duplicate_is_flagged_for_review_and_never_rejected(): void
    {
        $first = $this->recruiter('dup-one@example.test');
        $this->actingAs($first)->postJson('/companies', ['name' => 'Duplicate Signal Co'])->assertCreated();

        $second = $this->recruiter('dup-two@example.test');
        $this->actingAs($second)->postJson('/companies', ['name' => '  duplicate   signal co '])
            ->assertCreated()
            ->assertJsonPath('meta.warnings.0', 'COMPANY_DUPLICATE_REVIEW_SUGGESTED');

        $this->assertSame(2, DB::table('companies')->count());
    }

    public function test_concurrent_demotions_cannot_empty_the_admin_seat(): void
    {
        [$adminOne, $company] = $this->companyFor('race-one@example.test');
        $adminTwo = $this->recruiter('race-two@example.test');
        DB::table('company_members')->insert([
            'company_id' => $company->id, 'user_id' => $adminTwo->id, 'company_role' => 'COMPANY_ADMIN',
            'status' => 'ACTIVE', 'joined_at' => now(),
        ]);

        $action = app(ChangeCompanyMemberRole::class);
        $one = CompanyMember::query()->where('company_id', $company->id)->where('user_id', $adminOne->id)->firstOrFail();
        $two = CompanyMember::query()->where('company_id', $company->id)->where('user_id', $adminTwo->id)->firstOrFail();

        // Serialized by the company lock inside each transaction: the first
        // demotion succeeds, the second must be refused.
        $action->execute($adminOne, $company, $one, 'COMPANY_RECRUITER');
        try {
            $action->execute($adminOne, $company, $two, 'COMPANY_RECRUITER');
            $this->fail('The second demotion must not be allowed to empty the admin seat.');
        } catch (LastCompanyAdmin) {
            // expected
        }

        $this->assertSame(1, CompanyMember::query()->active()
            ->where('company_id', $company->id)->where('company_role', 'COMPANY_ADMIN')->count());
    }

    /** Fills the INV-030 completeness gate through the real update route. */
    private function completeProfile(User $recruiter, Company $company): void
    {
        $this->sequence++;
        $organizationType = DB::table('organization_types')->insertGetId(['code' => 'ROT-'.$this->sequence, 'name' => 'Org', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $industry = DB::table('industries')->insertGetId(['code' => 'RIN-'.$this->sequence, 'name' => 'Ind', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $province = DB::table('geographic_areas')->insertGetId(['name' => 'Prov', 'area_type' => 'PROVINCE', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $city = DB::table('geographic_areas')->insertGetId(['name' => 'City', 'area_type' => 'CITY', 'parent_geographic_area_id' => $province, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($recruiter)->patchJson("/companies/{$company->id}", [
            'organization_type_id' => $organizationType, 'industry_id' => $industry,
            'official_email' => $recruiter->email, 'address' => 'Address',
            'province_geographic_area_id' => $province, 'city_geographic_area_id' => $city,
        ])->assertOk();
    }

    /** @return array{User, Company} */
    private function companyFor(string $email): array
    {
        $recruiter = $this->recruiter($email);
        $this->actingAs($recruiter)->postJson('/companies', ['name' => 'Regression '.$this->sequence])->assertCreated();

        return [$recruiter, Company::query()->where('created_by', $recruiter->id)->firstOrFail()];
    }

    private function recruiter(string $email): User
    {
        $user = $this->makeUser($email, UserStatus::Active);
        $this->assignRole($user, RoleCode::CompanyRecruiter);

        return $user;
    }
}
