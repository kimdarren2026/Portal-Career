<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Exercises the Phase-3 vacancy foundation against PostgreSQL itself. These
 * assertions intentionally use direct writes so application validation cannot
 * mask a missing physical guarantee.
 */
final class PhaseThreeDatabaseConstraintsTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    public function test_only_phase_one_through_three_business_tables_exist(): void
    {
        $tables = collect(DB::select(
            "SELECT tablename FROM pg_tables WHERE schemaname = current_schema() ORDER BY tablename"
        ))->pluck('tablename')->all();

        $this->assertSame([
            'cache',
            'cache_locks',
            'candidate_certifications',
            'candidate_documents',
            'candidate_educations',
            'candidate_links',
            'candidate_organizations',
            'candidate_profiles',
            'candidate_saved_vacancies',
            'candidate_skills',
            'candidate_verifications',
            'candidate_work_experiences',
            'companies',
            'company_documents',
            'company_members',
            'company_verification_reviews',
            'email_verification_tokens',
            'failed_jobs',
            'geographic_areas',
            'industries',
            'migrations',
            'organization_types',
            'organizational_units',
            'partnerships',
            'password_credentials',
            'password_reset_tokens',
            'recruitment_stages',
            'roles',
            'sessions',
            'skills',
            'study_programs',
            'users',
            'vacancies',
            'vacancy_documents',
            'vacancy_moderation_reviews',
            'vacancy_requirements',
            'vacancy_screening_questions',
            'vacancy_versions',
        ], $tables);

        $this->assertFalse(DB::getSchemaBuilder()->hasTable('applications'));
        $this->assertFalse(DB::getSchemaBuilder()->hasTable('user_roles'));
    }

    public function test_every_phase_three_primary_key_is_a_generated_always_bigint(): void
    {
        $identities = collect(DB::select(
            "SELECT table_name
             FROM information_schema.columns
             WHERE table_schema = current_schema()
               AND table_name IN (
                   'vacancies', 'vacancy_versions', 'vacancy_requirements', 'vacancy_documents',
                   'vacancy_screening_questions', 'vacancy_moderation_reviews', 'recruitment_stages',
                   'candidate_saved_vacancies'
               )
               AND column_name = 'id'
               AND data_type = 'bigint'
               AND is_nullable = 'NO'
               AND is_identity = 'YES'
               AND identity_generation = 'ALWAYS'
             ORDER BY table_name"
        ))->pluck('table_name')->all();

        $this->assertSame([
            'candidate_saved_vacancies',
            'recruitment_stages',
            'vacancies',
            'vacancy_documents',
            'vacancy_moderation_reviews',
            'vacancy_requirements',
            'vacancy_screening_questions',
            'vacancy_versions',
        ], $identities);
    }

    public function test_postgresql_exposes_every_phase_three_constraint_with_its_frozen_name(): void
    {
        $constraints = collect(DB::select(
            "SELECT c.relname AS table_name, con.contype::text AS type, con.conname AS name
             FROM pg_constraint con
             JOIN pg_class c ON c.oid = con.conrelid
             JOIN pg_namespace n ON n.oid = c.relnamespace
             WHERE n.nspname = current_schema()
               AND c.relname IN (
                   'vacancies', 'vacancy_versions', 'vacancy_requirements', 'vacancy_documents',
                   'vacancy_screening_questions', 'vacancy_moderation_reviews', 'recruitment_stages',
                   'candidate_saved_vacancies'
               )
             ORDER BY c.relname, con.contype::text, con.conname"
        ))->map(fn (object $constraint): string => "{$constraint->table_name}:{$constraint->type}:{$constraint->name}")
            ->all();

        $this->assertSame([
            'candidate_saved_vacancies:f:fk_candidate_saved_vacancies_candidate_profile_id',
            'candidate_saved_vacancies:f:fk_candidate_saved_vacancies_vacancy_id',
            'candidate_saved_vacancies:p:pk_candidate_saved_vacancies',
            'candidate_saved_vacancies:u:uq_candidate_saved_vacancies_profile_vacancy',
            'recruitment_stages:f:fk_recruitment_stages_vacancy_id',
            'recruitment_stages:p:pk_recruitment_stages',
            'vacancies:c:chk_vacancies_application_method',
            'vacancies:c:chk_vacancies_campus_in_portal',
            'vacancies:c:chk_vacancies_campus_status',
            'vacancies:c:chk_vacancies_close_after_open',
            'vacancies:c:chk_vacancies_current_status',
            'vacancies:c:chk_vacancies_external_url',
            'vacancies:c:chk_vacancies_openings_positive',
            'vacancies:c:chk_vacancies_ownership_type',
            'vacancies:c:chk_vacancies_ownership_xor',
            'vacancies:c:chk_vacancies_salary_range',
            'vacancies:c:chk_vacancies_target_audience',
            'vacancies:c:chk_vacancies_vacancy_type',
            'vacancies:f:fk_vacancies_city_geographic_area_id',
            'vacancies:f:fk_vacancies_company_id',
            'vacancies:f:fk_vacancies_created_by',
            'vacancies:f:fk_vacancies_organizational_unit_id',
            'vacancies:f:fk_vacancies_province_geographic_area_id',
            'vacancies:p:pk_vacancies',
            'vacancies:u:uq_vacancies_slug',
            'vacancies:u:uq_vacancies_vacancy_code',
            'vacancy_documents:f:fk_vacancy_documents_uploaded_by',
            'vacancy_documents:f:fk_vacancy_documents_vacancy_id',
            'vacancy_documents:p:pk_vacancy_documents',
            'vacancy_moderation_reviews:c:chk_vacancy_moderation_reviews_action',
            'vacancy_moderation_reviews:c:chk_vacancy_moderation_reviews_reason_required',
            'vacancy_moderation_reviews:f:fk_vacancy_moderation_reviews_reviewer_user_id',
            'vacancy_moderation_reviews:f:fk_vacancy_moderation_reviews_vacancy_id',
            'vacancy_moderation_reviews:p:pk_vacancy_moderation_reviews',
            'vacancy_requirements:c:chk_vacancy_requirements_requirement_type',
            'vacancy_requirements:c:chk_vacancy_requirements_typed_value',
            'vacancy_requirements:f:fk_vacancy_requirements_skill_id',
            'vacancy_requirements:f:fk_vacancy_requirements_study_program_id',
            'vacancy_requirements:f:fk_vacancy_requirements_vacancy_id',
            'vacancy_requirements:p:pk_vacancy_requirements',
            'vacancy_screening_questions:c:chk_vacancy_screening_questions_question_type',
            'vacancy_screening_questions:f:fk_vacancy_screening_questions_vacancy_id',
            'vacancy_screening_questions:p:pk_vacancy_screening_questions',
            'vacancy_versions:f:fk_vacancy_versions_created_by',
            'vacancy_versions:f:fk_vacancy_versions_vacancy_id',
            'vacancy_versions:p:pk_vacancy_versions',
            'vacancy_versions:u:uq_vacancy_versions_vacancy_version',
        ], $constraints);
    }

    public function test_vacancy_value_checks_accept_every_frozen_value(): void
    {
        $creatorId = $this->user('vacancy-values');
        $companyId = $this->company($creatorId);
        $unitId = $this->organizationalUnit();

        foreach (['CAMPUS_EMPLOYMENT', 'COMPANY_EMPLOYMENT', 'INTERNSHIP'] as $vacancyType) {
            $this->vacancy($creatorId, ['company_id' => $companyId, 'vacancy_type' => $vacancyType]);
        }

        foreach (['PUBLIC', 'ALUMNI_ONLY', 'FINAL_YEAR_AND_ALUMNI', 'INTERNAL'] as $audience) {
            $this->vacancy($creatorId, ['company_id' => $companyId, 'target_audience' => $audience]);
        }

        foreach (['DRAFT', 'PENDING_REVIEW', 'REVISION_REQUIRED', 'APPROVED', 'SCHEDULED', 'PUBLISHED', 'REJECTED', 'CLOSED', 'EXPIRED', 'SUSPENDED'] as $status) {
            $this->vacancy($creatorId, ['company_id' => $companyId, 'current_status' => $status]);
        }

        $this->vacancy($creatorId, ['company_id' => $companyId, 'application_method' => 'IN_PORTAL']);
        $this->vacancy($creatorId, [
            'company_id' => $companyId,
            'application_method' => 'EXTERNAL_ATS',
            'external_ats_url' => 'https://ats.example.test/apply',
        ]);
        $this->vacancy($creatorId, [
            'ownership_type' => 'CAMPUS',
            'company_id' => null,
            'organizational_unit_id' => $unitId,
            'vacancy_type' => 'CAMPUS_EMPLOYMENT',
        ]);

        $this->assertSame(20, DB::table('vacancies')->count());
    }

    public function test_vacancy_value_checks_reject_unknown_values(): void
    {
        $creatorId = $this->user('vacancy-invalid-values');
        $companyId = $this->company($creatorId);

        $this->assertConstraintViolation('23514', 'chk_vacancies_vacancy_type', fn () =>
            $this->vacancy($creatorId, ['company_id' => $companyId, 'vacancy_type' => 'FREELANCE'])
        );
        $this->assertConstraintViolation('23514', 'chk_vacancies_ownership_type', fn () =>
            $this->vacancy($creatorId, ['company_id' => $companyId, 'ownership_type' => 'PARTNER'])
        );
        $this->assertConstraintViolation('23514', 'chk_vacancies_target_audience', fn () =>
            $this->vacancy($creatorId, ['company_id' => $companyId, 'target_audience' => 'STUDENTS'])
        );
        $this->assertConstraintViolation('23514', 'chk_vacancies_application_method', fn () =>
            $this->vacancy($creatorId, ['company_id' => $companyId, 'application_method' => 'EMAIL'])
        );
        $this->assertConstraintViolation('23514', 'chk_vacancies_current_status', fn () =>
            $this->vacancy($creatorId, ['company_id' => $companyId, 'current_status' => 'SUBMITTED'])
        );
    }

    public function test_vacancy_ownership_xor_and_campus_rules_are_physical(): void
    {
        $creatorId = $this->user('ownership-rules');
        $companyId = $this->company($creatorId);
        $unitId = $this->organizationalUnit();

        $this->assertConstraintViolation('23514', 'chk_vacancies_ownership_xor', fn () =>
            $this->vacancy($creatorId, [
                'company_id' => $companyId,
                'organizational_unit_id' => $unitId,
            ])
        );
        $this->assertConstraintViolation('23514', 'chk_vacancies_ownership_xor', fn () =>
            $this->vacancy($creatorId, ['company_id' => null, 'organizational_unit_id' => null])
        );
        $this->assertConstraintViolation('23514', 'chk_vacancies_campus_in_portal', fn () =>
            $this->vacancy($creatorId, [
                'ownership_type' => 'CAMPUS',
                'company_id' => null,
                'organizational_unit_id' => $unitId,
                'application_method' => 'EXTERNAL_ATS',
                'external_ats_url' => 'https://ats.example.test/apply',
            ])
        );
        $this->assertConstraintViolation('23514', 'chk_vacancies_campus_status', fn () =>
            $this->vacancy($creatorId, [
                'ownership_type' => 'CAMPUS',
                'company_id' => null,
                'organizational_unit_id' => $unitId,
                'current_status' => 'PENDING_REVIEW',
            ])
        );
    }

    public function test_vacancy_cross_field_guards_reject_invalid_combinations(): void
    {
        $creatorId = $this->user('vacancy-cross-field');
        $companyId = $this->company($creatorId);

        $this->vacancy($creatorId, [
            'company_id' => $companyId,
            'application_method' => 'EXTERNAL_ATS',
            'external_ats_url' => 'https://ats.example.test/apply',
            'open_at' => '2026-08-24 08:00:00+07',
            'close_at' => '2026-08-25 08:00:00+07',
            'salary_min' => 5000000,
            'salary_max' => 6000000,
        ]);

        $this->assertConstraintViolation('23514', 'chk_vacancies_external_url', fn () =>
            $this->vacancy($creatorId, ['company_id' => $companyId, 'application_method' => 'EXTERNAL_ATS'])
        );
        $this->assertConstraintViolation('23514', 'chk_vacancies_close_after_open', fn () =>
            $this->vacancy($creatorId, [
                'company_id' => $companyId,
                'open_at' => '2026-08-25 08:00:00+07',
                'close_at' => '2026-08-24 08:00:00+07',
            ])
        );
        $this->assertConstraintViolation('23514', 'chk_vacancies_salary_range', fn () =>
            $this->vacancy($creatorId, ['company_id' => $companyId, 'salary_min' => 6000000, 'salary_max' => 5000000])
        );
        $this->assertConstraintViolation('23514', 'chk_vacancies_openings_positive', fn () =>
            $this->vacancy($creatorId, ['company_id' => $companyId, 'openings_count' => 0])
        );
    }

    public function test_vacancy_code_and_slug_uniqueness_are_physical(): void
    {
        $creatorId = $this->user('vacancy-unique');
        $companyId = $this->company($creatorId);
        $vacancyId = $this->vacancy($creatorId, ['company_id' => $companyId]);
        $vacancy = DB::table('vacancies')->where('id', $vacancyId)->first(['vacancy_code', 'slug']);

        $this->assertConstraintViolation('23505', 'uq_vacancies_vacancy_code', fn () =>
            $this->vacancy($creatorId, ['company_id' => $companyId, 'vacancy_code' => $vacancy->vacancy_code])
        );
        $this->assertConstraintViolation('23505', 'uq_vacancies_slug', fn () =>
            $this->vacancy($creatorId, ['company_id' => $companyId, 'slug' => $vacancy->slug])
        );
    }

    public function test_vacancy_versions_use_a_non_nullable_jsonb_snapshot_and_unique_parent_version(): void
    {
        $creatorId = $this->user('version-snapshot');
        $vacancyId = $this->vacancy($creatorId);
        $this->version($vacancyId, $creatorId, 1);

        $column = DB::selectOne(
            "SELECT data_type, is_nullable
             FROM information_schema.columns
             WHERE table_schema = current_schema()
               AND table_name = 'vacancy_versions'
               AND column_name = 'snapshot'"
        );
        $this->assertSame('jsonb', $column->data_type);
        $this->assertSame('NO', $column->is_nullable);

        $this->assertConstraintViolation('23505', 'uq_vacancy_versions_vacancy_version', fn () =>
            $this->version($vacancyId, $creatorId, 1)
        );
        $this->assertConstraintViolation('23502', 'snapshot', fn () =>
            DB::table('vacancy_versions')->insert([
                'vacancy_id' => $vacancyId,
                'version_number' => 2,
                'created_by' => $creatorId,
            ])
        );
    }

    public function test_vacancy_requirement_types_and_typed_values_are_enforced(): void
    {
        $creatorId = $this->user('requirement-values');
        $vacancyId = $this->vacancy($creatorId);
        $studyProgramId = $this->studyProgram();
        $skillId = $this->skill();

        $this->requirement($vacancyId, 'EDUCATION', ['education_level' => 'BACHELOR']);
        $this->requirement($vacancyId, 'STUDY_PROGRAM', ['study_program_id' => $studyProgramId]);
        $this->requirement($vacancyId, 'EXPERIENCE', ['minimum_years_experience' => 2]);
        $this->requirement($vacancyId, 'SKILL', ['skill_id' => $skillId]);
        $this->requirement($vacancyId, 'CERTIFICATION', ['value_text' => 'AWS Certified']);
        $this->requirement($vacancyId, 'OTHER_QUALIFICATION', ['value_text' => 'Willing to travel']);

        $this->assertConstraintViolation('23514', 'chk_vacancy_requirements_requirement_type', fn () =>
            $this->requirement($vacancyId, 'LICENSE', ['value_text' => 'Invalid type'])
        );
        $this->assertConstraintViolation('23514', 'chk_vacancy_requirements_typed_value', fn () =>
            $this->requirement($vacancyId, 'STUDY_PROGRAM')
        );
    }

    public function test_screening_question_types_and_jsonb_options_are_enforced(): void
    {
        $creatorId = $this->user('screening-values');
        $vacancyId = $this->vacancy($creatorId);

        foreach (['SHORT_TEXT', 'LONG_TEXT', 'YES_NO', 'SINGLE_CHOICE', 'NUMBER'] as $questionType) {
            $this->screeningQuestion($vacancyId, $questionType);
        }

        $column = DB::selectOne(
            "SELECT data_type, is_nullable
             FROM information_schema.columns
             WHERE table_schema = current_schema()
               AND table_name = 'vacancy_screening_questions'
               AND column_name = 'options_definition'"
        );
        $this->assertSame('jsonb', $column->data_type);
        $this->assertSame('YES', $column->is_nullable);

        $this->assertConstraintViolation('23514', 'chk_vacancy_screening_questions_question_type', fn () =>
            $this->screeningQuestion($vacancyId, 'MULTI_CHOICE')
        );
    }

    public function test_moderation_actions_and_conditional_reasons_are_enforced(): void
    {
        $reviewerId = $this->user('moderation-actions');
        $vacancyId = $this->vacancy($reviewerId);

        foreach (['SUBMIT', 'REQUEST_REVISION', 'APPROVE', 'REJECT', 'SUSPEND', 'RESTORE', 'CLOSE'] as $action) {
            $this->moderationReview($vacancyId, $reviewerId, $action);
        }

        $this->assertConstraintViolation('23514', 'chk_vacancy_moderation_reviews_reason_required', fn () =>
            $this->moderationReview($vacancyId, $reviewerId, 'REJECT', [
                'reason_category' => null,
                'recruiter_visible_note' => null,
            ])
        );
        $this->assertConstraintViolation('23514', 'chk_vacancy_moderation_reviews_action', fn () =>
            $this->moderationReview($vacancyId, $reviewerId, 'VERIFY')
        );
    }

    public function test_all_phase_three_foreign_keys_reject_missing_references(): void
    {
        $creatorId = $this->user('foreign-keys');
        $companyId = $this->company($creatorId);
        $unitId = $this->organizationalUnit();
        $areaId = $this->geographicArea();
        $vacancyId = $this->vacancy($creatorId, ['company_id' => $companyId]);
        $studyProgramId = $this->studyProgram();
        $profileId = $this->candidateProfile($this->user('foreign-keys-profile'));

        $this->assertConstraintViolation('23503', 'fk_vacancies_company_id', fn () =>
            $this->vacancy($creatorId, ['company_id' => 999999])
        );
        $this->assertConstraintViolation('23503', 'fk_vacancies_organizational_unit_id', fn () =>
            $this->vacancy($creatorId, [
                'ownership_type' => 'CAMPUS', 'company_id' => null, 'organizational_unit_id' => 999999,
            ])
        );
        $this->assertConstraintViolation('23503', 'fk_vacancies_created_by', fn () =>
            $this->vacancy($creatorId, ['company_id' => $companyId, 'created_by' => 999999])
        );
        $this->assertConstraintViolation('23503', 'fk_vacancies_province_geographic_area_id', fn () =>
            $this->vacancy($creatorId, ['company_id' => $companyId, 'province_geographic_area_id' => 999999])
        );
        $this->assertConstraintViolation('23503', 'fk_vacancies_city_geographic_area_id', fn () =>
            $this->vacancy($creatorId, ['company_id' => $companyId, 'city_geographic_area_id' => 999999])
        );
        $this->assertConstraintViolation('23503', 'fk_vacancy_versions_vacancy_id', fn () =>
            $this->version(999999, $creatorId, 1)
        );
        $this->assertConstraintViolation('23503', 'fk_vacancy_versions_created_by', fn () =>
            $this->version($vacancyId, 999999, 1)
        );
        $this->assertConstraintViolation('23503', 'fk_vacancy_requirements_vacancy_id', fn () =>
            $this->requirement(999999, 'EDUCATION', ['education_level' => 'BACHELOR'])
        );
        $this->assertConstraintViolation('23503', 'fk_vacancy_requirements_study_program_id', fn () =>
            $this->requirement($vacancyId, 'STUDY_PROGRAM', ['study_program_id' => 999999])
        );
        $this->assertConstraintViolation('23503', 'fk_vacancy_requirements_skill_id', fn () =>
            $this->requirement($vacancyId, 'SKILL', ['skill_id' => 999999])
        );
        $this->assertConstraintViolation('23503', 'fk_vacancy_documents_vacancy_id', fn () =>
            $this->vacancyDocument(999999, $creatorId)
        );
        $this->assertConstraintViolation('23503', 'fk_vacancy_documents_uploaded_by', fn () =>
            $this->vacancyDocument($vacancyId, 999999)
        );
        $this->assertConstraintViolation('23503', 'fk_vacancy_screening_questions_vacancy_id', fn () =>
            $this->screeningQuestion(999999, 'SHORT_TEXT')
        );
        $this->assertConstraintViolation('23503', 'fk_vacancy_moderation_reviews_vacancy_id', fn () =>
            $this->moderationReview(999999, $creatorId, 'SUBMIT')
        );
        $this->assertConstraintViolation('23503', 'fk_vacancy_moderation_reviews_reviewer_user_id', fn () =>
            $this->moderationReview($vacancyId, 999999, 'SUBMIT')
        );
        $this->assertConstraintViolation('23503', 'fk_recruitment_stages_vacancy_id', fn () =>
            $this->recruitmentStage(999999)
        );
        $this->assertConstraintViolation('23503', 'fk_candidate_saved_vacancies_candidate_profile_id', fn () =>
            $this->saveVacancy(999999, $vacancyId)
        );
        $this->assertConstraintViolation('23503', 'fk_candidate_saved_vacancies_vacancy_id', fn () =>
            $this->saveVacancy($profileId, 999999)
        );

        $this->assertNotNull($unitId);
        $this->assertNotNull($areaId);
        $this->assertNotNull($studyProgramId);
    }

    public function test_saved_vacancy_uniqueness_and_delete_policies_are_physical(): void
    {
        $candidateUserId = $this->user('saved-vacancy-candidate');
        $profileId = $this->candidateProfile($candidateUserId);
        $creatorId = $this->user('saved-vacancy-creator');
        $vacancyId = $this->vacancy($creatorId);
        $this->saveVacancy($profileId, $vacancyId);

        $this->assertConstraintViolation('23505', 'uq_candidate_saved_vacancies_profile_vacancy', fn () =>
            $this->saveVacancy($profileId, $vacancyId)
        );
        $this->assertConstraintViolation('23503', 'fk_candidate_saved_vacancies_vacancy_id', fn () =>
            DB::table('vacancies')->where('id', $vacancyId)->delete()
        );

        DB::table('candidate_profiles')->where('id', $profileId)->delete();
        $this->assertSame(0, DB::table('candidate_saved_vacancies')->where('vacancy_id', $vacancyId)->count());
    }

    public function test_vacancy_history_and_evidence_restrict_parent_deletes(): void
    {
        $creatorId = $this->user('vacancy-retention');
        $vacancyId = $this->vacancy($creatorId);
        $this->version($vacancyId, $creatorId, 1);

        $this->assertConstraintViolation('23503', 'fk_vacancy_versions_vacancy_id', fn () =>
            DB::table('vacancies')->where('id', $vacancyId)->delete()
        );

        $vacancyWithDocumentId = $this->vacancy($creatorId);
        $uploaderId = $this->user('vacancy-document-uploader');
        $this->vacancyDocument($vacancyWithDocumentId, $uploaderId);
        $this->assertConstraintViolation('23503', 'fk_vacancy_documents_uploaded_by', fn () =>
            DB::table('users')->where('id', $uploaderId)->delete()
        );
    }

    public function test_phase_three_lookup_and_partial_indexes_exist_without_jsonb_gin_indexes(): void
    {
        $indexes = collect(DB::select(
            "SELECT indexname, indexdef
             FROM pg_indexes
             WHERE schemaname = current_schema()
               AND indexname IN (
                   'idx_vacancies_company_id', 'idx_vacancies_organizational_unit_id',
                   'idx_vacancies_public_listing', 'idx_vacancies_public_filters',
                   'idx_vacancies_close_at', 'idx_vacancies_open_at', 'idx_vacancies_moderation_queue',
                   'idx_vacancy_requirements_vacancy_sort', 'idx_vacancy_documents_vacancy_id',
                   'idx_vsq_vacancy_sort_active', 'idx_vmr_vacancy_reviewed',
                   'idx_recruitment_stages_vacancy_sort'
               )"
        ))->keyBy('indexname');

        $this->assertCount(12, $indexes);
        $this->assertStringContainsString("WHERE ((current_status)::text = 'PUBLISHED'::text)", $indexes['idx_vacancies_public_filters']->indexdef);
        $this->assertStringContainsString('target_audience', $indexes['idx_vacancies_public_listing']->indexdef);
        $this->assertStringContainsString("WHERE ((current_status)::text = 'SCHEDULED'::text)", $indexes['idx_vacancies_open_at']->indexdef);
        $this->assertStringContainsString("WHERE ((ownership_type)::text = 'COMPANY'::text)", $indexes['idx_vacancies_moderation_queue']->indexdef);
        $this->assertStringContainsString('WHERE active', $indexes['idx_vsq_vacancy_sort_active']->indexdef);

        $jsonbGinIndexes = DB::select(
            "SELECT indexname
             FROM pg_indexes
             WHERE schemaname = current_schema()
               AND tablename IN ('vacancy_versions', 'vacancy_screening_questions')
               AND indexdef ILIKE '% USING gin %'"
        );
        $this->assertSame([], $jsonbGinIndexes);
    }

    public function test_phase_three_timestamps_are_timestamptz(): void
    {
        $timestamps = collect(DB::select(
            "SELECT table_name, column_name
             FROM information_schema.columns
             WHERE table_schema = current_schema()
               AND table_name IN (
                   'vacancies', 'vacancy_versions', 'vacancy_documents',
                   'vacancy_moderation_reviews', 'recruitment_stages', 'candidate_saved_vacancies'
               )
               AND data_type = 'timestamp with time zone'
             ORDER BY table_name, column_name"
        ))->map(fn (object $column): string => "{$column->table_name}.{$column->column_name}")->all();

        $this->assertSame([
            'candidate_saved_vacancies.saved_at',
            'recruitment_stages.created_at',
            'vacancies.close_at',
            'vacancies.closed_at',
            'vacancies.created_at',
            'vacancies.open_at',
            'vacancies.published_at',
            'vacancies.suspended_at',
            'vacancies.updated_at',
            'vacancy_documents.archived_at',
            'vacancy_documents.created_at',
            'vacancy_moderation_reviews.reviewed_at',
            'vacancy_versions.created_at',
        ], $timestamps);
    }

    private function user(string $name): int
    {
        $suffix = ++$this->sequence;
        $email = "{$name}-{$suffix}@example.test";

        return (int) DB::table('users')->insertGetId([
            'name' => 'Phase Three Test User',
            'email' => $email,
            'email_normalized' => $email,
            'status' => 'ACTIVE',
        ]);
    }

    private function company(int $creatorId): int
    {
        $suffix = ++$this->sequence;

        return (int) DB::table('companies')->insertGetId([
            'name' => "Phase Three Company {$suffix}",
            'normalized_name' => "phase-three-company-{$suffix}",
            'verification_status' => 'DRAFT',
            'created_by' => $creatorId,
        ]);
    }

    private function organizationalUnit(): int
    {
        $suffix = ++$this->sequence;

        return (int) DB::table('organizational_units')->insertGetId([
            'code' => "UNIT-{$suffix}",
            'name' => "Phase Three Unit {$suffix}",
        ]);
    }

    private function geographicArea(): int
    {
        $suffix = ++$this->sequence;

        return (int) DB::table('geographic_areas')->insertGetId([
            'code' => "AREA-{$suffix}",
            'name' => "Phase Three Area {$suffix}",
            'area_type' => 'PROVINCE',
        ]);
    }

    private function studyProgram(): int
    {
        $suffix = ++$this->sequence;

        return (int) DB::table('study_programs')->insertGetId([
            'code' => "STUDY-{$suffix}",
            'name' => "Phase Three Study {$suffix}",
        ]);
    }

    private function skill(): int
    {
        $suffix = ++$this->sequence;

        return (int) DB::table('skills')->insertGetId([
            'name' => "Phase Three Skill {$suffix}",
            'normalized_name' => "phase-three-skill-{$suffix}",
        ]);
    }

    private function candidateProfile(int $userId): int
    {
        return (int) DB::table('candidate_profiles')->insertGetId([
            'user_id' => $userId,
            'current_candidate_type' => 'EXTERNAL',
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function vacancy(int $creatorId, array $overrides = []): int
    {
        $suffix = ++$this->sequence;
        $companyId = $this->company($creatorId);

        return (int) DB::table('vacancies')->insertGetId(array_replace([
            'vacancy_code' => "VAC-{$suffix}",
            'slug' => "phase-three-vacancy-{$suffix}",
            'vacancy_type' => 'COMPANY_EMPLOYMENT',
            'ownership_type' => 'COMPANY',
            'company_id' => $companyId,
            'organizational_unit_id' => null,
            'created_by' => $creatorId,
            'title' => "Phase Three Vacancy {$suffix}",
            'description' => 'A test vacancy description.',
            'employment_type' => 'FULL_TIME',
            'openings_count' => 1,
            'target_audience' => 'PUBLIC',
            'application_method' => 'IN_PORTAL',
            'current_status' => 'DRAFT',
        ], $overrides));
    }

    private function version(int $vacancyId, int $creatorId, int $number): int
    {
        return (int) DB::table('vacancy_versions')->insertGetId([
            'vacancy_id' => $vacancyId,
            'version_number' => $number,
            'snapshot' => json_encode(['title' => 'Snapshot vacancy'], JSON_THROW_ON_ERROR),
            'created_by' => $creatorId,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function requirement(int $vacancyId, string $type, array $overrides = []): int
    {
        $suffix = ++$this->sequence;

        return (int) DB::table('vacancy_requirements')->insertGetId(array_replace([
            'vacancy_id' => $vacancyId,
            'requirement_type' => $type,
            'required' => true,
            'sort_order' => $suffix,
        ], $overrides));
    }

    private function vacancyDocument(int $vacancyId, int $uploadedBy): int
    {
        $suffix = ++$this->sequence;

        return (int) DB::table('vacancy_documents')->insertGetId([
            'vacancy_id' => $vacancyId,
            'document_type' => 'TERMS',
            'display_name' => "Supporting Document {$suffix}",
            'storage_reference' => "vacancy-document-{$suffix}",
            'uploaded_by' => $uploadedBy,
        ]);
    }

    private function screeningQuestion(int $vacancyId, string $type): int
    {
        $suffix = ++$this->sequence;

        return (int) DB::table('vacancy_screening_questions')->insertGetId([
            'vacancy_id' => $vacancyId,
            'question_text' => "Question {$suffix}",
            'question_type' => $type,
            'required' => true,
            'options_definition' => $type === 'SINGLE_CHOICE'
                ? json_encode(['Yes', 'No'], JSON_THROW_ON_ERROR)
                : null,
            'sort_order' => $suffix,
            'active' => true,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function moderationReview(int $vacancyId, int $reviewerId, string $action, array $overrides = []): int
    {
        $adverse = in_array($action, ['REQUEST_REVISION', 'REJECT', 'SUSPEND'], true);

        return (int) DB::table('vacancy_moderation_reviews')->insertGetId(array_replace([
            'vacancy_id' => $vacancyId,
            'reviewer_user_id' => $reviewerId,
            'action' => $action,
            'from_status' => 'DRAFT',
            'to_status' => 'PENDING_REVIEW',
            'reason_category' => $adverse ? 'CONTENT_INCOMPLETE' : null,
            'recruiter_visible_note' => $adverse ? 'Please revise the vacancy.' : null,
            'reviewed_at' => now(),
        ], $overrides));
    }

    private function recruitmentStage(int $vacancyId): int
    {
        $suffix = ++$this->sequence;

        return (int) DB::table('recruitment_stages')->insertGetId([
            'vacancy_id' => $vacancyId,
            'name' => "Stage {$suffix}",
            'stage_type' => 'INTERVIEW',
            'sort_order' => $suffix,
            'active' => true,
        ]);
    }

    private function saveVacancy(int $profileId, int $vacancyId): int
    {
        return (int) DB::table('candidate_saved_vacancies')->insertGetId([
            'candidate_profile_id' => $profileId,
            'vacancy_id' => $vacancyId,
            'saved_at' => now(),
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
