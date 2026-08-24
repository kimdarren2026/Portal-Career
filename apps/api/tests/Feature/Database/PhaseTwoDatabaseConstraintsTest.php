<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Exercises Phase-2 constraints in PostgreSQL, including the conditional
 * membership and document-evidence rules that application validation cannot
 * safely replace.
 */
final class PhaseTwoDatabaseConstraintsTest extends TestCase
{
    use RefreshDatabase;

    public function test_phase_two_tables_remain_present_with_only_later_business_tables_deferred(): void
    {
        $tables = collect(DB::select(
            "SELECT tablename FROM pg_tables WHERE schemaname = current_schema() ORDER BY tablename"
        ))->pluck('tablename')->all();

        $this->assertContains('cache', $tables);
        $this->assertContains('cache_locks', $tables);
        $this->assertContains('migrations', $tables);

        foreach ([
            'candidate_certifications',
            'candidate_documents',
            'candidate_educations',
            'candidate_links',
            'candidate_organizations',
            'candidate_profiles',
            'candidate_skills',
            'candidate_verifications',
            'candidate_work_experiences',
            'companies',
            'company_documents',
            'company_members',
            'company_verification_reviews',
            'partnerships',
        ] as $table) {
            $this->assertContains($table, $tables);
        }

        $this->assertTrue(DB::getSchemaBuilder()->hasTable('idempotency_keys'));
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('export_jobs'));
        $this->assertFalse(DB::getSchemaBuilder()->hasTable('user_roles'));
        $this->assertFalse(DB::getSchemaBuilder()->hasTable('personal_access_tokens'));
    }

    public function test_every_phase_two_primary_key_is_a_generated_always_bigint(): void
    {
        $identities = collect(DB::select(
            "SELECT table_name
             FROM information_schema.columns
             WHERE table_schema = current_schema()
               AND table_name IN (
                   'candidate_profiles', 'candidate_verifications', 'candidate_documents',
                   'candidate_educations', 'candidate_work_experiences', 'candidate_organizations',
                   'candidate_skills', 'candidate_certifications', 'candidate_links', 'companies',
                   'company_members', 'company_documents', 'company_verification_reviews', 'partnerships'
               )
               AND column_name = 'id'
               AND data_type = 'bigint'
               AND is_nullable = 'NO'
               AND is_identity = 'YES'
               AND identity_generation = 'ALWAYS'
             ORDER BY table_name"
        ))->pluck('table_name')->all();

        $this->assertSame([
            'candidate_certifications', 'candidate_documents', 'candidate_educations',
            'candidate_links', 'candidate_organizations', 'candidate_profiles', 'candidate_skills',
            'candidate_verifications', 'candidate_work_experiences', 'companies', 'company_documents',
            'company_members', 'company_verification_reviews', 'partnerships',
        ], $identities);
    }

    public function test_candidate_profile_type_check_accepts_valid_and_rejects_invalid_values(): void
    {
        $this->candidateProfile($this->user('candidate-type-valid'), 'ALUMNI');

        $this->assertConstraintViolation('23514', 'chk_candidate_profiles_current_candidate_type', fn () =>
            $this->candidateProfile($this->user('candidate-type-invalid'), 'UNKNOWN')
        );
    }

    public function test_candidate_verification_type_and_status_checks_reject_invalid_values(): void
    {
        $profileId = $this->candidateProfile($this->user('verification-valid'));
        DB::table('candidate_verifications')->insert([
            'candidate_profile_id' => $profileId,
            'verification_type' => 'ALUMNI',
            'status' => 'PENDING',
        ]);

        $this->assertConstraintViolation('23514', 'chk_candidate_verifications_verification_type', fn () =>
            DB::table('candidate_verifications')->insert([
                'candidate_profile_id' => $profileId,
                'verification_type' => 'OTHER',
                'status' => 'PENDING',
            ])
        );
    }

    public function test_candidate_verification_status_check_rejects_invalid_values(): void
    {
        $profileId = $this->candidateProfile($this->user('verification-status'));

        $this->assertConstraintViolation('23514', 'chk_candidate_verifications_status', fn () =>
            DB::table('candidate_verifications')->insert([
                'candidate_profile_id' => $profileId,
                'verification_type' => 'ALUMNI',
                'status' => 'REJECTED',
            ])
        );
    }

    public function test_candidate_verification_manual_mismatch_requires_a_reason(): void
    {
        $profileId = $this->candidateProfile($this->user('verification-reason-valid'));
        DB::table('candidate_verifications')->insert([
            'candidate_profile_id' => $profileId,
            'verification_type' => 'ALUMNI',
            'status' => 'MISMATCH_MANUAL_REVIEW',
            'rejection_reason' => 'Data source needs manual review.',
        ]);

        $profileId = $this->candidateProfile($this->user('verification-reason-invalid'));
        $this->assertConstraintViolation('23514', 'chk_candidate_verifications_rejection_reason', fn () =>
            DB::table('candidate_verifications')->insert([
                'candidate_profile_id' => $profileId,
                'verification_type' => 'ALUMNI',
                'status' => 'MISMATCH_MANUAL_REVIEW',
            ])
        );
    }

    public function test_candidate_link_type_check_and_profile_url_uniqueness_are_physical(): void
    {
        $profileId = $this->candidateProfile($this->user('link-valid'));
        DB::table('candidate_links')->insert([
            'candidate_profile_id' => $profileId,
            'link_type' => 'PORTFOLIO',
            'url' => 'https://example.test/portfolio',
            'sort_order' => 0,
        ]);

        $this->assertConstraintViolation('23505', 'uq_candidate_links_profile_url', fn () =>
            DB::table('candidate_links')->insert([
                'candidate_profile_id' => $profileId,
                'link_type' => 'PORTFOLIO',
                'url' => 'https://example.test/portfolio',
                'sort_order' => 1,
            ])
        );
    }

    public function test_candidate_link_type_check_rejects_invalid_values(): void
    {
        $profileId = $this->candidateProfile($this->user('link-invalid'));

        $this->assertConstraintViolation('23514', 'chk_candidate_links_link_type', fn () =>
            DB::table('candidate_links')->insert([
                'candidate_profile_id' => $profileId,
                'link_type' => 'SOCIAL',
                'url' => 'https://example.test/other',
                'sort_order' => 0,
            ])
        );
    }

    public function test_candidate_profile_and_skill_unique_keys_reject_duplicates(): void
    {
        $userId = $this->user('profile-unique');
        $profileId = $this->candidateProfile($userId);
        $skillId = DB::table('skills')->insertGetId([
            'name' => 'PostgreSQL',
            'normalized_name' => 'postgresql',
        ]);
        DB::table('candidate_skills')->insert([
            'candidate_profile_id' => $profileId,
            'skill_id' => $skillId,
        ]);

        $this->assertConstraintViolation('23505', 'uq_candidate_skills_profile_skill', fn () =>
            DB::table('candidate_skills')->insert([
                'candidate_profile_id' => $profileId,
                'skill_id' => $skillId,
            ])
        );
    }

    public function test_candidate_profile_unique_user_constraint_rejects_second_profile(): void
    {
        $userId = $this->user('profile-unique-user');
        $this->candidateProfile($userId);

        $this->assertConstraintViolation('23505', 'uq_candidate_profiles_user', fn () =>
            $this->candidateProfile($userId)
        );
    }

    public function test_candidate_date_and_current_record_guards_reject_invalid_rows(): void
    {
        $profileId = $this->candidateProfile($this->user('candidate-guards'));
        DB::table('candidate_work_experiences')->insert([
            'candidate_profile_id' => $profileId,
            'employer_name' => 'Former Employer',
            'position_title' => 'Developer',
            'is_current' => false,
            'end_date' => '2026-01-01',
        ]);

        $this->assertConstraintViolation('23514', 'chk_candidate_work_experiences_current', fn () =>
            DB::table('candidate_work_experiences')->insert([
                'candidate_profile_id' => $profileId,
                'employer_name' => 'Current Employer',
                'position_title' => 'Developer',
                'is_current' => true,
                'end_date' => '2026-01-01',
            ])
        );
    }

    public function test_candidate_organization_current_guard_rejects_an_end_date(): void
    {
        $profileId = $this->candidateProfile($this->user('organization-guard'));
        DB::table('candidate_organizations')->insert([
            'candidate_profile_id' => $profileId,
            'organization_name' => 'Former Organization',
            'role_title' => 'Secretary',
            'is_current' => false,
            'end_date' => '2026-01-01',
        ]);

        $this->assertConstraintViolation('23514', 'chk_candidate_organizations_current', fn () =>
            DB::table('candidate_organizations')->insert([
                'candidate_profile_id' => $profileId,
                'organization_name' => 'Student Organization',
                'role_title' => 'Secretary',
                'is_current' => true,
                'end_date' => '2026-01-01',
            ])
        );
    }

    public function test_candidate_certification_expiry_guard_rejects_a_reverse_date_range(): void
    {
        $profileId = $this->candidateProfile($this->user('certification-guard'));
        DB::table('candidate_certifications')->insert([
            'candidate_profile_id' => $profileId,
            'certification_name' => 'Valid Certificate',
            'issuer_name' => 'Issuer',
            'issued_at' => '2025-01-01',
            'expires_at' => '2026-01-01',
        ]);

        $this->assertConstraintViolation('23514', 'chk_candidate_certifications_expiry', fn () =>
            DB::table('candidate_certifications')->insert([
                'candidate_profile_id' => $profileId,
                'certification_name' => 'Invalid Certificate',
                'issuer_name' => 'Issuer',
                'issued_at' => '2026-01-02',
                'expires_at' => '2026-01-01',
            ])
        );
    }

    public function test_company_status_check_and_normalized_name_non_uniqueness_follow_the_specification(): void
    {
        $creatorId = $this->user('company-status');
        $this->company($creatorId, 'First Company', 'same-normalized-name');
        $this->company($creatorId, 'Second Company', 'same-normalized-name');

        $this->assertConstraintViolation('23514', 'chk_companies_verification_status', fn () =>
            DB::table('companies')->insert([
                'name' => 'Invalid Company',
                'normalized_name' => 'invalid-company',
                'verification_status' => 'APPROVED',
                'created_by' => $creatorId,
            ])
        );
    }

    public function test_company_member_role_check_and_active_membership_partial_unique_index(): void
    {
        $creatorId = $this->user('member-creator');
        $memberId = $this->user('member-user');
        $companyId = $this->company($creatorId, 'Membership Company', 'membership-company');
        $membershipId = $this->companyMember($companyId, $memberId);

        DB::table('company_members')->where('id', $membershipId)->update(['revoked_at' => now()]);
        $this->companyMember($companyId, $memberId);

        $this->assertConstraintViolation('23505', 'uq_company_members_company_user_active', fn () =>
            $this->companyMember($companyId, $memberId)
        );
    }

    public function test_company_member_role_check_rejects_invalid_values(): void
    {
        $creatorId = $this->user('member-role-creator');
        $memberId = $this->user('member-role-user');
        $companyId = $this->company($creatorId, 'Role Company', 'role-company');

        $this->assertConstraintViolation('23514', 'chk_company_members_company_role', fn () =>
            DB::table('company_members')->insert([
                'company_id' => $companyId,
                'user_id' => $memberId,
                'company_role' => 'OWNER',
                'status' => 'ACTIVE',
                'joined_at' => now(),
            ])
        );
    }

    public function test_company_document_guards_and_successor_uniqueness_are_physical(): void
    {
        $creatorId = $this->user('document-creator');
        $companyId = $this->company($creatorId, 'Evidence Company', 'evidence-company');
        $successorId = $this->companyDocument($companyId, 'successor-object');
        $predecessorId = $this->companyDocument($companyId, 'predecessor-object');
        DB::table('company_documents')->where('id', $predecessorId)->update([
            'superseded_at' => now(),
            'superseded_by_document_id' => $successorId,
        ]);

        $otherPredecessorId = $this->companyDocument($companyId, 'other-predecessor-object');
        $this->assertConstraintViolation('23505', 'uq_company_documents_superseded_by_document', fn () =>
            DB::table('company_documents')->where('id', $otherPredecessorId)->update([
                'superseded_at' => now(),
                'superseded_by_document_id' => $successorId,
            ])
        );
    }

    public function test_company_document_same_row_guards_reject_invalid_dates_and_supersede_pairs(): void
    {
        $creatorId = $this->user('document-guards');
        $companyId = $this->company($creatorId, 'Guard Company', 'guard-company');

        $this->assertConstraintViolation('23514', 'chk_company_documents_expiry', fn () =>
            DB::table('company_documents')->insert([
                'company_id' => $companyId,
                'document_type' => 'LEGAL',
                'storage_reference' => 'invalid-expiry',
                'status' => 'ACTIVE',
                'issued_at' => '2026-01-02',
                'expires_at' => '2026-01-01',
            ])
        );
    }

    public function test_company_document_supersede_pair_and_no_self_guard_reject_invalid_states(): void
    {
        $creatorId = $this->user('document-supersede-guards');
        $companyId = $this->company($creatorId, 'Supersede Company', 'supersede-company');
        $documentId = $this->companyDocument($companyId, 'self-document');

        $this->assertConstraintViolation('23514', 'chk_company_documents_supersede', fn () =>
            DB::table('company_documents')->where('id', $documentId)->update(['superseded_at' => now()])
        );
    }

    public function test_company_document_no_self_guard_rejects_self_reference(): void
    {
        $creatorId = $this->user('document-self-guard');
        $companyId = $this->company($creatorId, 'Self Company', 'self-company');
        $documentId = $this->companyDocument($companyId, 'self-reference-document');

        $this->assertConstraintViolation('23514', 'chk_company_documents_no_self_supersede', fn () =>
            DB::table('company_documents')->where('id', $documentId)->update([
                'superseded_at' => now(),
                'superseded_by_document_id' => $documentId,
            ])
        );
    }

    public function test_company_review_action_and_conditional_reason_checks_are_physical(): void
    {
        $reviewerId = $this->user('reviewer-valid');
        $companyId = $this->company($reviewerId, 'Reviewed Company', 'reviewed-company');
        DB::table('company_verification_reviews')->insert([
            'company_id' => $companyId,
            'reviewer_user_id' => $reviewerId,
            'action' => 'REQUEST_REVISION',
            'from_status' => 'PENDING_VERIFICATION',
            'to_status' => 'REVISION_REQUIRED',
            'reason_category' => 'DOCUMENT_INCOMPLETE',
            'recruiter_visible_note' => 'Please provide the required document.',
            'reviewed_at' => now(),
        ]);

        $this->assertConstraintViolation('23514', 'chk_company_verification_reviews_reason_required', fn () =>
            DB::table('company_verification_reviews')->insert([
                'company_id' => $companyId,
                'reviewer_user_id' => $reviewerId,
                'action' => 'REJECT',
                'from_status' => 'PENDING_VERIFICATION',
                'to_status' => 'REJECTED',
                'reviewed_at' => now(),
            ])
        );
    }

    public function test_company_review_action_check_rejects_invalid_values(): void
    {
        $reviewerId = $this->user('reviewer-invalid');
        $companyId = $this->company($reviewerId, 'Invalid Review Company', 'invalid-review-company');

        $this->assertConstraintViolation('23514', 'chk_company_verification_reviews_action', fn () =>
            DB::table('company_verification_reviews')->insert([
                'company_id' => $companyId,
                'reviewer_user_id' => $reviewerId,
                'action' => 'APPROVE',
                'from_status' => 'PENDING_VERIFICATION',
                'to_status' => 'VERIFIED',
                'reviewed_at' => now(),
            ])
        );
    }

    public function test_phase_two_fk_rejection_and_delete_semantics_are_enforced(): void
    {
        $profileId = $this->candidateProfile($this->user('fk-rejection'));
        $this->assertConstraintViolation('23503', 'fk_candidate_verifications_program_study_id', fn () =>
            DB::table('candidate_verifications')->insert([
                'candidate_profile_id' => $profileId,
                'verification_type' => 'ALUMNI',
                'status' => 'PENDING',
                'program_study_id' => 999999,
            ])
        );
    }

    public function test_candidate_verification_reviewer_is_set_null_when_the_actor_is_deleted(): void
    {
        $profileId = $this->candidateProfile($this->user('verified-candidate'));
        $reviewerId = $this->user('verified-by');
        $verificationId = DB::table('candidate_verifications')->insertGetId([
            'candidate_profile_id' => $profileId,
            'verification_type' => 'ALUMNI',
            'status' => 'VERIFIED',
            'verified_by' => $reviewerId,
        ]);

        DB::table('users')->where('id', $reviewerId)->delete();

        $this->assertNull(DB::table('candidate_verifications')->where('id', $verificationId)->value('verified_by'));
    }

    public function test_candidate_profile_and_company_document_deletes_are_restricted_when_referenced(): void
    {
        $profileId = $this->candidateProfile($this->user('candidate-delete-restrict'));
        DB::table('candidate_documents')->insert([
            'candidate_profile_id' => $profileId,
            'document_type' => 'CV',
            'display_name' => 'CV',
            'storage_reference' => 'candidate-cv',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'uploaded_at' => now(),
        ]);

        $this->assertConstraintViolation('23503', 'fk_candidate_documents_candidate_profile_id', fn () =>
            DB::table('candidate_profiles')->where('id', $profileId)->delete()
        );
    }

    public function test_company_document_successor_delete_is_restricted(): void
    {
        $creatorId = $this->user('successor-delete');
        $companyId = $this->company($creatorId, 'Successor Restrict Company', 'successor-restrict-company');
        $successorId = $this->companyDocument($companyId, 'successor-delete-object');
        $predecessorId = $this->companyDocument($companyId, 'predecessor-delete-object');
        DB::table('company_documents')->where('id', $predecessorId)->update([
            'superseded_at' => now(),
            'superseded_by_document_id' => $successorId,
        ]);

        $this->assertConstraintViolation('23503', 'fk_company_documents_superseded_by_document_id', fn () =>
            DB::table('company_documents')->where('id', $successorId)->delete()
        );
    }

    public function test_phase_two_partial_and_lookup_indexes_exist_in_postgresql(): void
    {
        $indexes = collect(DB::select(
            "SELECT indexname, indexdef
             FROM pg_indexes
             WHERE schemaname = current_schema()
               AND indexname IN (
                   'uq_company_members_company_user_active',
                   'idx_candidate_verifications_profile_type_status',
                   'idx_candidate_documents_profile_type',
                   'idx_company_documents_company_current',
                   'idx_companies_verification_queue',
                   'idx_companies_legal_identifier',
                   'idx_partnerships_company_status'
               )"
        ))->keyBy('indexname');

        $this->assertCount(7, $indexes);
        $this->assertStringContainsString('WHERE (revoked_at IS NULL)', $indexes['uq_company_members_company_user_active']->indexdef);
        $this->assertStringContainsString('WHERE (superseded_at IS NULL)', $indexes['idx_company_documents_company_current']->indexdef);
        $this->assertStringContainsString('WHERE (legal_identifier IS NOT NULL)', $indexes['idx_companies_legal_identifier']->indexdef);
    }

    private function user(string $localPart): int
    {
        $email = $localPart.'@example.test';

        return (int) DB::table('users')->insertGetId([
            'name' => 'Phase Two Test User',
            'email' => $email,
            'email_normalized' => $email,
            'status' => 'ACTIVE',
        ]);
    }

    private function candidateProfile(int $userId, string $candidateType = 'EXTERNAL'): int
    {
        return (int) DB::table('candidate_profiles')->insertGetId([
            'user_id' => $userId,
            'current_candidate_type' => $candidateType,
        ]);
    }

    private function company(int $creatorId, string $name, string $normalizedName): int
    {
        return (int) DB::table('companies')->insertGetId([
            'name' => $name,
            'normalized_name' => $normalizedName,
            'verification_status' => 'DRAFT',
            'created_by' => $creatorId,
        ]);
    }

    private function companyMember(int $companyId, int $userId): int
    {
        return (int) DB::table('company_members')->insertGetId([
            'company_id' => $companyId,
            'user_id' => $userId,
            'company_role' => 'COMPANY_RECRUITER',
            'status' => 'ACTIVE',
            'joined_at' => now(),
        ]);
    }

    private function companyDocument(int $companyId, string $storageReference): int
    {
        return (int) DB::table('company_documents')->insertGetId([
            'company_id' => $companyId,
            'document_type' => 'LEGAL',
            'storage_reference' => $storageReference,
            'status' => 'ACTIVE',
        ]);
    }

    private function assertConstraintViolation(string $sqlState, string $constraint, Closure $operation): void
    {
        try {
            DB::transaction($operation);
            $this->fail("Expected PostgreSQL to reject data via {$constraint}.");
        } catch (QueryException $exception) {
            $this->assertSame($sqlState, $exception->getCode());
            $this->assertStringContainsString($constraint, $exception->getMessage());
        }
    }
}
