<?php

declare(strict_types=1);

namespace Tests\Feature\Company;

use App\Domains\Company\Enums\CompanyStatus;
use App\Domains\Company\Actions\CreateInitialCompanyMembership;
use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\CompanyMember;
use App\Domains\Company\Support\CompanyAdminProtection;
use App\Domains\Company\Exceptions\LastCompanyAdmin;
use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use App\Domains\Vacancy\Support\CompanyVacancyEligibility;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Feature\Identity\IdentityTestCase;

final class CompanyOnboardingFoundationTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_eligible_recruiter_creates_draft_company_with_exactly_one_active_company_admin(): void
    {
        $recruiter = $this->user('recruiter@example.test', RoleCode::CompanyRecruiter);
        $this->actingAs($recruiter)->postJson('/companies', [
            'name' => 'Example Company', 'company_role' => 'COMPANY_RECRUITER', 'user_id' => 999999,
        ])->assertCreated()->assertJsonPath('data.verification_status', 'DRAFT')->assertJsonMissing(['BUSINESS_OR_CONTRACT_DECISION_REQUIRED']);
        $companyId = (int) DB::table('companies')->where('created_by', $recruiter->id)->value('id');
        $this->assertSame(1, DB::table('company_members')->where('company_id', $companyId)->count());
        $this->assertDatabaseHas('company_members', ['company_id' => $companyId, 'user_id' => $recruiter->id, 'company_role' => 'COMPANY_ADMIN', 'status' => 'ACTIVE']);
        $this->assertDatabaseMissing('company_members', ['company_id' => $companyId, 'user_id' => 999999]);
        $this->assertDatabaseMissing('partnerships', ['company_id' => $companyId]);
    }

    public function test_company_and_initial_admin_are_atomic_when_membership_creation_fails(): void
    {
        $recruiter = $this->user('atomic@example.test', RoleCode::CompanyRecruiter);
        $mock = Mockery::mock(CreateInitialCompanyMembership::class);
        $mock->shouldReceive('execute')->once()->andThrow(new \RuntimeException('simulated membership failure'));
        $this->app->instance(CreateInitialCompanyMembership::class, $mock);

        $this->actingAs($recruiter)->postJson('/companies', ['name' => 'Atomic Company'])->assertServerError();
        $this->assertDatabaseMissing('companies', ['created_by' => $recruiter->id]);
        $this->assertDatabaseCount('company_members', 0);
    }

    public function test_last_admin_guard_protects_remove_demote_deactivate_and_cross_company_cases(): void
    {
        $admin = $this->user('admin@example.test', RoleCode::CompanyRecruiter);
        $company = $this->company($admin, 'Admin Company');
        DB::table('company_members')->where('company_id', $company->id)->where('user_id', $admin->id)->update(['company_role' => 'COMPANY_ADMIN']);
        $membership = CompanyMember::query()->where('company_id', $company->id)->where('user_id', $admin->id)->firstOrFail();
        $guard = app(CompanyAdminProtection::class);

        $this->expectException(LastCompanyAdmin::class);
        $guard->assertAdminMayBeReduced($membership, 'COMPANY_RECRUITER');
    }

    public function test_last_admin_guard_allows_reduction_when_another_active_admin_exists_but_not_inactive_or_other_company(): void
    {
        $admin = $this->user('admin-two@example.test', RoleCode::CompanyRecruiter);
        $otherAdmin = $this->user('admin-three@example.test', RoleCode::CompanyRecruiter);
        $company = $this->company($admin, 'Two Admin Company');
        DB::table('company_members')->where('company_id', $company->id)->where('user_id', $admin->id)->update(['company_role' => 'COMPANY_ADMIN']);
        DB::table('company_members')->insert(['company_id' => $company->id, 'user_id' => $otherAdmin->id, 'company_role' => 'COMPANY_ADMIN', 'status' => 'ACTIVE', 'joined_at' => now()]);
        $membership = CompanyMember::query()->where('company_id', $company->id)->where('user_id', $admin->id)->firstOrFail();
        app(CompanyAdminProtection::class)->assertAdminMayBeReduced($membership, 'COMPANY_RECRUITER');

        DB::table('company_members')->where('company_id', $company->id)->where('user_id', $otherAdmin->id)->update(['status' => 'INACTIVE']);
        $this->expectException(LastCompanyAdmin::class);
        app(CompanyAdminProtection::class)->assertAdminMayBeReduced($membership, null, true);
    }

    public function test_recruiter_company_scope_and_verified_email_gate_are_enforced(): void
    {
        $recruiter = $this->user('scope@example.test', RoleCode::CompanyRecruiter);
        $other = $this->user('other@example.test', RoleCode::CompanyRecruiter);
        $own = $this->company($recruiter, 'Own Company');
        $foreign = $this->company($other, 'Foreign Company');

        $this->actingAs($recruiter)->getJson('/companies/'.$foreign->id)->assertNotFound();
        $this->actingAs($recruiter)->patchJson('/companies/'.$foreign->id, ['name' => 'Injected'])->assertNotFound();
        $this->actingAs($recruiter)->postJson('/companies/'.$foreign->id.'/verify')->assertNotFound();
        $this->actingAs($recruiter)->patchJson('/companies/'.$own->id, ['company_id' => $foreign->id, 'name' => 'Changed'])->assertOk()->assertJsonPath('data.name', 'Changed');

        $pending = $this->user('pending@example.test', RoleCode::CompanyRecruiter, UserStatus::PendingEmailVerification);
        $pendingCompany = $this->company($pending, 'Pending Company');
        $this->actingAs($pending)->patchJson('/companies/'.$pendingCompany->id, ['name' => 'Blocked'])->assertForbidden()->assertJsonPath('error.code', 'AUTH_EMAIL_NOT_VERIFIED');
    }

    public function test_submission_revision_resubmission_and_review_preserve_same_company_history(): void
    {
        $recruiter = $this->user('flow@example.test', RoleCode::CompanyRecruiter);
        $reviewer = $this->user('reviewer@example.test', RoleCode::CareerCenterStaff);
        $company = $this->company($recruiter, 'Flow Company');
        $this->document($company);

        $this->actingAs($recruiter)->postJson('/companies/'.$company->id.'/submit-verification')->assertOk()->assertJsonPath('data.verification_status', 'PENDING_VERIFICATION');
        $this->actingAs($reviewer)->postJson('/companies/'.$company->id.'/request-revision', ['reason_category' => 'MISSING_DATA', 'recruiter_visible_note' => 'Please correct'])->assertOk()->assertJsonPath('data.verification_status', 'REVISION_REQUIRED');
        $this->actingAs($recruiter)->patchJson('/companies/'.$company->id, ['address' => 'Updated address'])->assertOk();
        $this->actingAs($recruiter)->postJson('/companies/'.$company->id.'/submit-verification')->assertOk()->assertJsonPath('data.verification_status', 'PENDING_VERIFICATION');
        $this->actingAs($reviewer)->postJson('/companies/'.$company->id.'/verify')->assertOk()->assertJsonPath('data.verification_status', 'VERIFIED');

        $this->assertSame(1, DB::table('companies')->where('id', $company->id)->count());
        $this->assertSame(4, DB::table('company_verification_reviews')->where('company_id', $company->id)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'company_submitted', 'object_id' => $company->id]);
        $this->assertTrue(CompanyVacancyEligibility::allowed(Company::findOrFail($company->id)));
    }

    public function test_review_boundaries_reasons_and_rejected_suspended_distinction(): void
    {
        $recruiter = $this->user('boundary@example.test', RoleCode::CompanyRecruiter);
        $reviewer = $this->user('boundary-reviewer@example.test', RoleCode::CareerCenterStaff);
        $company = $this->company($recruiter, 'Boundary Company');
        DB::table('companies')->where('id', $company->id)->update(['verification_status' => CompanyStatus::PendingVerification->value]);

        $this->actingAs($recruiter)->postJson('/companies/'.$company->id.'/verify')->assertForbidden();
        $this->actingAs($reviewer)->postJson('/companies/'.$company->id.'/reject')->assertStatus(422)->assertJsonPath('error.code', 'REVIEW_REASON_REQUIRED');
        $this->actingAs($reviewer)->postJson('/companies/'.$company->id.'/reject', ['reason_category' => 'INVALID', 'recruiter_visible_note' => 'Not accepted'])->assertOk()->assertJsonPath('data.verification_status', 'REJECTED');
        $this->assertFalse(CompanyVacancyEligibility::allowed(Company::findOrFail($company->id)));
        $this->actingAs($reviewer)->postJson('/companies/'.$company->id.'/suspend', ['reason_category' => 'NO', 'recruiter_visible_note' => 'No'])->assertStatus(409)->assertJsonPath('error.code', 'COMPANY_INVALID_TRANSITION');
    }

    public function test_partnership_is_not_created_by_verification_and_nonpartner_verified_company_is_eligible(): void
    {
        $recruiter = $this->user('partner@example.test', RoleCode::CompanyRecruiter);
        $reviewer = $this->user('partner-reviewer@example.test', RoleCode::CareerCenterStaff);
        $company = $this->company($recruiter, 'Non Partner Company');
        $this->document($company);
        $this->actingAs($recruiter)->postJson('/companies/'.$company->id.'/submit-verification');
        $this->actingAs($reviewer)->postJson('/companies/'.$company->id.'/verify')->assertOk()->assertJsonPath('data.mitra_kampus_active', false);
        $this->assertDatabaseCount('partnerships', 0);
    }

    private function user(string $email, RoleCode $role, UserStatus $status = UserStatus::Active): User
    {
        $user = $this->makeUser($email, $status);
        $this->assignRole($user, $role);
        return $user;
    }

    private function company(User $user, string $name): Company
    {
        $this->sequence++;
        $organizationType = DB::table('organization_types')->insertGetId(['code' => 'ORG-'.$this->sequence, 'name' => 'Organization', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $industry = DB::table('industries')->insertGetId(['code' => 'IND-'.$this->sequence, 'name' => 'Industry', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $province = DB::table('geographic_areas')->insertGetId(['name' => 'Province', 'area_type' => 'PROVINCE', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $city = DB::table('geographic_areas')->insertGetId(['name' => 'City', 'area_type' => 'CITY', 'parent_geographic_area_id' => $province, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $id = DB::table('companies')->insertGetId([
            'name' => $name, 'normalized_name' => strtolower($name), 'slug' => \App\Domains\Company\Support\CompanyIdentifier::slug($name),
            'organization_type_id' => $organizationType, 'industry_id' => $industry,
            'official_email' => $user->email, 'address' => 'Address', 'province_geographic_area_id' => $province, 'city_geographic_area_id' => $city,
            'verification_status' => 'DRAFT', 'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('company_members')->insert(['company_id' => $id, 'user_id' => $user->id, 'company_role' => 'COMPANY_RECRUITER', 'status' => 'ACTIVE', 'joined_at' => now()]);
        return Company::findOrFail($id);
    }

    private function document(Company $company): void
    {
        DB::table('company_documents')->insert(['company_id' => $company->id, 'document_type' => 'CUSTOM', 'storage_reference' => 'company/test.pdf', 'status' => 'CURRENT', 'created_at' => now(), 'updated_at' => now()]);
    }
}
