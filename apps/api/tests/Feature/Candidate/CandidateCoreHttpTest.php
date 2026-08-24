<?php

declare(strict_types=1);

namespace Tests\Feature\Candidate;

use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Identity\IdentityTestCase;

final class CandidateCoreHttpTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_owner_reads_and_updates_the_registration_shell_without_creating_or_completing_a_second_profile(): void
    {
        [$candidate, $profileId] = $this->candidate();

        $this->actingAs($candidate)->getJson('/candidate/profile')
            ->assertOk()->assertJsonPath('data.id', $profileId)
            ->assertJsonPath('data.profile_completed_at', null)
            ->assertJsonPath('data.collections.counts.educations', 0);

        $this->actingAs($candidate)->patchJson('/candidate/profile', [
            'headline' => 'Entry-level engineer',
            'preferred_employment_type' => 'Short-term contract',
            'preferred_workplace_mode' => 'Field-based',
            'open_to_opportunities' => true,
        ])->assertOk()
            ->assertJsonPath('data.headline', 'Entry-level engineer')
            ->assertJsonPath('data.profile_completed_at', null);

        $this->assertSame(1, DB::table('candidate_profiles')->where('user_id', $candidate->id)->count());
        $this->assertSame($profileId, (int) DB::table('candidate_profiles')->where('user_id', $candidate->id)->value('id'));
        $this->assertSame(0, DB::table('candidate_verifications')->where('candidate_profile_id', $profileId)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'candidate_profile_updated', 'object_id' => $profileId]);
    }

    public function test_profile_keeps_an_existing_completion_timestamp_and_rejects_deferred_candidate_type_updates(): void
    {
        [$candidate, $profileId] = $this->candidate();
        $timestamp = now()->subDay();
        DB::table('candidate_profiles')->where('id', $profileId)->update(['profile_completed_at' => $timestamp]);

        $this->actingAs($candidate)->patchJson('/candidate/profile', ['headline' => 'Updated'])
            ->assertOk()->assertJsonPath('data.profile_completed_at', $timestamp->toIso8601String());
        $this->actingAs($candidate)->patchJson('/candidate/profile', ['current_candidate_type' => 'ALUMNI'])
            ->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->assertSame(0, DB::table('candidate_verifications')->where('candidate_profile_id', $profileId)->count());
    }

    public function test_candidate_profile_is_required_and_guests_are_denied(): void
    {
        $noShell = $this->makeUser('no-shell@example.test');
        $this->assignRole($noShell, RoleCode::CandidateExternal);
        $this->actingAs($noShell)->getJson('/candidate/profile')->assertStatus(422)->assertJsonPath('error.code', 'CANDIDATE_PROFILE_REQUIRED');
        $this->app['auth']->forgetGuards();
        $this->getJson('/candidate/profile')->assertUnauthorized()->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_pending_email_can_read_but_cannot_mutate_candidate_profile_or_collections(): void
    {
        [$candidate] = $this->candidate(UserStatus::PendingEmailVerification);
        $this->actingAs($candidate)->getJson('/candidate/profile')->assertOk();
        $this->actingAs($candidate)->patchJson('/candidate/profile', ['headline' => 'Blocked'])->assertForbidden()->assertJsonPath('error.code', 'AUTH_EMAIL_NOT_VERIFIED');
        $this->actingAs($candidate)->putJson('/candidate/educations', ['items' => []])->assertForbidden()->assertJsonPath('error.code', 'AUTH_EMAIL_NOT_VERIFIED');
    }

    public function test_education_replacement_is_atomic_accepts_pending_levels_and_scopes_supplied_identifiers(): void
    {
        [$candidate, $profileId] = $this->candidate();
        $this->actingAs($candidate)->putJson('/candidate/educations', ['items' => [[
            'institution_name' => 'Politeknik Contoh', 'education_level' => 'Micro-credential custom',
            'start_date' => '2022-08-01', 'graduation_date' => '2025-08-01',
        ]]])->assertOk()->assertJsonPath('data.items.0.education_level', 'Micro-credential custom');
        $existingId = (int) DB::table('candidate_educations')->where('candidate_profile_id', $profileId)->value('id');

        $this->actingAs($candidate)->putJson('/candidate/educations', ['items' => [[
            'id' => $existingId, 'institution_name' => 'Changed', 'education_level' => 'Anything',
            'start_date' => '2025-01-01', 'graduation_date' => '2024-01-01',
        ]]])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->assertSame('Politeknik Contoh', DB::table('candidate_educations')->where('id', $existingId)->value('institution_name'));

        [, $otherProfile] = $this->candidate();
        $otherEducation = DB::table('candidate_educations')->insertGetId(['candidate_profile_id' => $otherProfile, 'institution_name' => 'Private', 'education_level' => 'Custom', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($candidate)->putJson('/candidate/educations', ['items' => [[
            'id' => $otherEducation, 'institution_name' => 'Probe', 'education_level' => 'Custom',
        ]]])->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
        $this->assertSame(0, DB::table('candidate_verifications')->where('candidate_profile_id', $profileId)->count());
    }

    public function test_empty_collection_replacement_clears_only_the_owners_collection(): void
    {
        [$candidate, $profileId] = $this->candidate();
        DB::table('candidate_organizations')->insert(['candidate_profile_id' => $profileId, 'organization_name' => 'Campus group', 'role_title' => 'Chair', 'is_current' => false, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($candidate)->putJson('/candidate/organizations', ['items' => []])->assertOk()->assertJsonCount(0, 'data.items');
        $this->assertSame(0, DB::table('candidate_organizations')->where('candidate_profile_id', $profileId)->count());
    }

    public function test_work_experience_and_organization_dates_are_validated_as_full_collection_updates(): void
    {
        [$candidate] = $this->candidate();
        $this->actingAs($candidate)->putJson('/candidate/work-experiences', ['items' => [[
            'employer_name' => 'Example Ltd', 'position_title' => 'Intern', 'is_current' => true, 'end_date' => '2026-01-01',
        ]]])->assertStatus(422);
        $this->actingAs($candidate)->putJson('/candidate/organizations', ['items' => [[
            'organization_name' => 'Volunteer Club', 'role_title' => 'Member', 'is_current' => false,
            'start_date' => '2025-01-01', 'end_date' => '2024-01-01',
        ]]])->assertStatus(422);
        $this->actingAs($candidate)->getJson('/candidate/work-experiences')->assertOk()->assertJsonCount(0, 'data.items');
        $this->actingAs($candidate)->getJson('/candidate/organizations')->assertOk()->assertJsonCount(0, 'data.items');
    }

    public function test_certifications_can_store_metadata_but_enforce_private_document_ownership(): void
    {
        [$candidate, $profileId] = $this->candidate();
        [, $otherProfile] = $this->candidate();
        $otherDocument = $this->document($otherProfile, 'other-certificate.pdf');

        $this->actingAs($candidate)->putJson('/candidate/certifications', ['items' => [[
            'certification_name' => 'Laravel Fundamentals', 'issuer_name' => 'Example Academy', 'credential_url' => 'https://example.test/credential', 'document_id' => $otherDocument,
        ]]])->assertForbidden()->assertJsonPath('error.code', 'DOCUMENT_NOT_OWNED');
        $this->assertSame(0, DB::table('candidate_certifications')->where('candidate_profile_id', $profileId)->count());

        $this->actingAs($candidate)->putJson('/candidate/certifications', ['items' => [[
            'certification_name' => 'Laravel Fundamentals', 'issuer_name' => 'Example Academy', 'credential_url' => 'https://example.test/credential',
        ]]])->assertOk()->assertJsonPath('data.items.0.issuer_name', 'Example Academy');
    }

    public function test_all_approved_link_types_are_accepted_and_unsafe_or_duplicate_links_are_rejected(): void
    {
        [$candidate] = $this->candidate();
        $types = ['LINKEDIN', 'PORTFOLIO', 'PERSONAL_WEBSITE', 'PUBLICATION', 'OTHER'];
        $items = array_map(fn (string $type, int $index): array => ['link_type' => $type, 'url' => "https://example.test/$index", 'sort_order' => $index], $types, array_keys($types));
        $this->actingAs($candidate)->putJson('/candidate/links', ['items' => $items])->assertOk()->assertJsonCount(5, 'data.items');
        $this->actingAs($candidate)->putJson('/candidate/links', ['items' => [[
            'link_type' => 'UNSUPPORTED', 'url' => 'javascript:alert(1)', 'sort_order' => 1,
        ]]])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_skills_reference_only_active_master_data_and_cannot_be_duplicated(): void
    {
        [$candidate] = $this->candidate();
        $active = DB::table('skills')->insertGetId(['name' => 'PHP', 'normalized_name' => 'php', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $inactive = DB::table('skills')->insertGetId(['name' => 'Retired', 'normalized_name' => 'retired', 'active' => false, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($candidate)->putJson('/candidate/skills', ['items' => [['skill_id' => $active, 'proficiency_level' => 'Self-assessed']]])->assertOk();
        $this->actingAs($candidate)->putJson('/candidate/skills', ['items' => [['skill_id' => $active], ['skill_id' => $active]]])->assertStatus(422);
        $this->actingAs($candidate)->putJson('/candidate/skills', ['items' => [['skill_id' => $inactive]]])->assertStatus(422);
    }

    public function test_verification_status_is_read_only_and_no_submission_route_exists(): void
    {
        [$candidate, $profileId] = $this->candidate();
        DB::table('candidate_verifications')->insert(['candidate_profile_id' => $profileId, 'verification_type' => 'ALUMNI', 'status' => 'VERIFIED', 'verified_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($candidate)->getJson('/candidate/verifications')->assertOk()
            ->assertJsonPath('data.items.0.verification_type', 'ALUMNI')->assertJsonPath('data.items.0.status', 'VERIFIED');
        $this->actingAs($candidate)->postJson('/candidate/verifications', ['verification_type' => 'ALUMNI'])->assertMethodNotAllowed();
    }

    public function test_document_metadata_is_private_and_upload_is_not_active(): void
    {
        [$candidate, $profileId] = $this->candidate();
        [, $otherProfile] = $this->candidate();
        $ownDocument = $this->document($profileId, 'cv.pdf');
        $otherDocument = $this->document($otherProfile, 'private.pdf');

        $this->actingAs($candidate)->getJson('/candidate/documents')->assertOk()
            ->assertJsonPath('data.items.0.id', $ownDocument)
            ->assertJsonMissingPath('data.items.0.storage_reference');
        $this->actingAs($candidate)->patchJson('/candidate/documents/'.$ownDocument, ['display_name' => 'Updated CV.pdf'])->assertOk()
            ->assertJsonPath('data.display_name', 'Updated CV.pdf');
        $this->actingAs($candidate)->patchJson('/candidate/documents/'.$otherDocument, ['display_name' => 'Probe.pdf'])->assertForbidden()->assertJsonPath('error.code', 'DOCUMENT_NOT_OWNED');
        $this->actingAs($candidate)->deleteJson('/candidate/documents/'.$ownDocument)->assertNoContent();
        $this->assertNotNull(DB::table('candidate_documents')->where('id', $ownDocument)->value('archived_at'));
        $this->actingAs($candidate)->postJson('/candidate/documents', ['document_type' => 'CV'])->assertMethodNotAllowed();
        $this->assertSame(0, DB::table('application_documents')->count());
    }

    public function test_me_handles_populated_candidate_context_without_sensitive_fields_or_null_relation_crashes(): void
    {
        [$candidate, $profileId] = $this->candidate();
        DB::table('candidate_verifications')->insert(['candidate_profile_id' => $profileId, 'verification_type' => 'FINAL_YEAR_STUDENT', 'status' => 'VERIFIED', 'verified_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($candidate)->getJson('/me')->assertOk()
            ->assertJsonPath('data.candidate_profile.id', $profileId)
            ->assertJsonPath('data.candidate_profile.profile_completed_at', null)
            ->assertJsonPath('data.candidate_profile.verified_eligibility.0', 'FINAL_YEAR_STUDENT')
            ->assertJsonMissingPath('data.candidate_profile.storage_reference');
    }

    /** @return array{User, int} */
    private function candidate(UserStatus $status = UserStatus::Active): array
    {
        $this->sequence++;
        $candidate = $this->makeUser("candidate-{$this->sequence}@example.test", $status);
        $this->assignRole($candidate, RoleCode::CandidateExternal);
        $profileId = DB::table('candidate_profiles')->insertGetId(['user_id' => $candidate->id, 'current_candidate_type' => 'EXTERNAL', 'created_at' => now(), 'updated_at' => now()]);
        return [$candidate, $profileId];
    }

    private function document(int $profileId, string $name): int
    {
        return DB::table('candidate_documents')->insertGetId([
            'candidate_profile_id' => $profileId, 'document_type' => 'Custom type', 'display_name' => $name,
            'storage_reference' => 'synthetic/'.$name, 'mime_type' => 'application/pdf', 'size' => 42,
            'uploaded_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
