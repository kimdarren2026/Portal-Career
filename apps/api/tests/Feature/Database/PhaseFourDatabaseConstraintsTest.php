<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Direct PostgreSQL coverage for the Phase-4 recruitment foundation. Business
 * Actions intentionally remain absent; these tests prove only physical rules.
 */
final class PhaseFourDatabaseConstraintsTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    public function test_phase_four_tables_remain_present_with_only_later_business_tables_deferred(): void
    {
        $tables = collect(DB::select(
            "SELECT tablename FROM pg_tables WHERE schemaname = current_schema() ORDER BY tablename"
        ))->pluck('tablename')->all();

        $this->assertContains('cache', $tables);
        $this->assertContains('cache_locks', $tables);
        $this->assertContains('migrations', $tables);
        foreach ([
            'applications', 'application_status_histories', 'application_documents',
            'application_screening_answers', 'selection_stage_assignments',
            'selection_schedules', 'selection_schedule_histories', 'evaluations',
            'evaluation_items', 'offers', 'external_apply_events', 'consents',
            'recruitment_outcomes',
        ] as $table) {
            $this->assertContains($table, $tables);
        }

        $this->assertTrue(DB::getSchemaBuilder()->hasTable('idempotency_keys'));
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('export_jobs'));
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('user_roles'));
        $this->assertFalse(DB::getSchemaBuilder()->hasTable('personal_access_tokens'));
    }

    public function test_every_phase_four_primary_key_is_a_generated_always_bigint(): void
    {
        $identities = collect(DB::select(
            "SELECT table_name
             FROM information_schema.columns
             WHERE table_schema = current_schema()
               AND table_name IN (
                   'applications', 'application_status_histories', 'application_documents',
                   'application_screening_answers', 'selection_stage_assignments',
                   'selection_schedules', 'selection_schedule_histories', 'evaluations',
                   'evaluation_items', 'offers', 'external_apply_events', 'consents',
                   'recruitment_outcomes'
               )
               AND column_name = 'id'
               AND data_type = 'bigint'
               AND is_nullable = 'NO'
               AND is_identity = 'YES'
               AND identity_generation = 'ALWAYS'
             ORDER BY table_name"
        ))->pluck('table_name')->all();

        $this->assertSame([
            'application_documents', 'application_screening_answers', 'application_status_histories',
            'applications', 'consents', 'evaluation_items', 'evaluations', 'external_apply_events',
            'offers', 'recruitment_outcomes', 'selection_schedule_histories',
            'selection_schedules', 'selection_stage_assignments',
        ], $identities);
    }

    public function test_postgresql_exposes_the_phase_four_constraint_catalog_with_frozen_names(): void
    {
        $counts = collect(DB::select(
            "SELECT con.contype::text AS type, COUNT(*)::int AS count
             FROM pg_constraint con
             JOIN pg_class c ON c.oid = con.conrelid
             JOIN pg_namespace n ON n.oid = c.relnamespace
             WHERE n.nspname = current_schema()
               AND c.relname IN (
                   'applications', 'application_status_histories', 'application_documents',
                   'application_screening_answers', 'selection_stage_assignments',
                   'selection_schedules', 'selection_schedule_histories', 'evaluations',
                   'evaluation_items', 'offers', 'external_apply_events', 'consents',
                   'recruitment_outcomes'
               )
             GROUP BY con.contype
             ORDER BY con.contype"
        ))->map(fn (object $row): string => "{$row->type}:{$row->count}")->all();

        // Phase 5 completes the one deferred external-apply consent FK without
        // changing any Phase-4 table definition or same-row constraint.
        $this->assertSame(['c:17', 'f:38', 'p:13', 'u:3'], $counts);

        $names = collect(DB::select(
            "SELECT con.conname
             FROM pg_constraint con
             JOIN pg_class c ON c.oid = con.conrelid
             JOIN pg_namespace n ON n.oid = c.relnamespace
             WHERE n.nspname = current_schema()
               AND c.relname IN ('applications', 'application_screening_answers', 'selection_schedules', 'offers', 'consents', 'recruitment_outcomes')"
        ))->pluck('conname')->all();

        foreach ([
            'uq_applications_candidate_vacancy', 'uq_applications_application_code',
            'chk_applications_current_status', 'chk_applications_reopen_count',
            'chk_applications_withdrawn', 'uq_application_screening_answers_app_question',
            'chk_application_screening_answers_one_value', 'chk_selection_schedules_status',
            'chk_selection_schedules_time', 'chk_selection_schedules_revision',
            'chk_offers_status', 'chk_offers_accepted_at', 'chk_consents_receiver_xor',
            'chk_recruitment_outcomes_source_type', 'chk_recruitment_outcomes_reported_by_source',
            'chk_recruitment_outcomes_source_xor',
        ] as $name) {
            $this->assertContains($name, $names);
        }
    }

    public function test_application_lifecycle_is_unconditional_and_same_row_guards_are_physical(): void
    {
        $candidateProfileId = $this->candidateProfile($this->user('application-candidate'));
        $creatorId = $this->user('application-creator');
        $vacancyId = $this->vacancy($creatorId);
        $applicationId = $this->application($candidateProfileId, $vacancyId, 'WITHDRAWN', [
            'withdrawn_at' => now(),
        ]);

        foreach ([
            'APPLIED', 'UNDER_REVIEW', 'SHORTLISTED', 'ASSESSMENT', 'INTERVIEW',
            'OFFERED', 'HIRED', 'REJECTED', 'WITHDRAWN', 'NO_SHOW',
        ] as $status) {
            $this->application(
                $this->candidateProfile($this->user("application-valid-{$status}")),
                $vacancyId,
                $status,
                $status === 'WITHDRAWN' ? ['withdrawn_at' => now()] : []
            );
        }

        $this->assertConstraintViolation('23505', 'uq_applications_candidate_vacancy', fn () =>
            $this->application($candidateProfileId, $vacancyId, 'REJECTED')
        );
        $this->assertConstraintViolation('23514', 'chk_applications_current_status', fn () =>
            $this->application(
                $this->candidateProfile($this->user('application-status-candidate')),
                $vacancyId,
                'SUBMITTED'
            )
        );
        $this->assertConstraintViolation('23514', 'chk_applications_reopen_count', fn () =>
            $this->application(
                $this->candidateProfile($this->user('application-reopen-candidate')),
                $vacancyId,
                'APPLIED',
                ['reopen_count' => -1]
            )
        );
        $this->assertConstraintViolation('23514', 'chk_applications_withdrawn', fn () =>
            $this->application(
                $this->candidateProfile($this->user('application-withdrawn-candidate')),
                $vacancyId,
                'WITHDRAWN'
            )
        );
        $applicationCode = (string) DB::table('applications')->where('id', $applicationId)->value('application_code');
        $this->assertConstraintViolation('23505', 'uq_applications_application_code', fn () =>
            $this->application(
                $this->candidateProfile($this->user('application-code-candidate')),
                $vacancyId,
                'APPLIED',
                ['application_code' => $applicationCode]
            )
        );

        $this->assertSame('WITHDRAWN', DB::table('applications')->where('id', $applicationId)->value('current_status'));
    }

    public function test_application_method_gate_is_explicitly_deferred_to_the_application_layer(): void
    {
        $candidateProfileId = $this->candidateProfile($this->user('external-application-candidate'));
        $creatorId = $this->user('external-application-creator');
        $externalVacancyId = $this->vacancy($creatorId, 'EXTERNAL_ATS');

        // INV-024 reads vacancies.application_method. The frozen design rejects
        // a redundant composite FK and requires an Action-level row lock instead.
        $this->application($candidateProfileId, $externalVacancyId, 'APPLIED');

        $this->assertSame(1, DB::table('applications')->where('vacancy_id', $externalVacancyId)->count());
    }

    public function test_application_history_value_checks_and_actor_set_null_are_physical(): void
    {
        $candidateProfileId = $this->candidateProfile($this->user('history-candidate'));
        $creatorId = $this->user('history-creator');
        $vacancyId = $this->vacancy($creatorId);
        $stageId = $this->stage($vacancyId);
        $applicationId = $this->application($candidateProfileId, $vacancyId, 'APPLIED', ['current_stage_id' => $stageId]);
        $actorId = $this->user('history-actor');

        foreach ([
            'APPLICATION_CREATED', 'STATUS_CHANGED', 'STAGE_CHANGED', 'APPLICATION_REOPENED',
            'WITHDRAWN', 'REJECTED', 'OFFER_ACCEPTED', 'NO_SHOW',
        ] as $eventType) {
            $this->applicationHistory($applicationId, $eventType, $actorId, $stageId);
        }
        $this->applicationHistory($applicationId, 'STATUS_CHANGED', $actorId, $stageId, [
            'candidate_visibility' => 'INTERNAL',
        ]);

        $this->assertConstraintViolation('23514', 'chk_application_status_histories_event_type', fn () =>
            $this->applicationHistory($applicationId, 'RESCHEDULED', $actorId, $stageId)
        );
        $this->assertConstraintViolation('23514', 'chk_application_status_histories_candidate_visibility', fn () =>
            $this->applicationHistory($applicationId, 'STATUS_CHANGED', $actorId, $stageId, [
                'candidate_visibility' => 'PRIVATE',
            ])
        );

        DB::table('users')->where('id', $actorId)->delete();
        $this->assertSame(0, DB::table('application_status_histories')->whereNotNull('actor_user_id')->count());
    }

    public function test_application_document_snapshots_are_required_and_preserved_by_restrict(): void
    {
        $candidateUserId = $this->user('document-candidate');
        $candidateProfileId = $this->candidateProfile($candidateUserId);
        $documentId = $this->candidateDocument($candidateProfileId);
        $creatorId = $this->user('document-creator');
        $applicationId = $this->application($candidateProfileId, $this->vacancy($creatorId), 'APPLIED');
        $this->applicationDocument($applicationId, $documentId);

        $this->assertConstraintViolation('23502', 'snapshot_name', fn () =>
            DB::table('application_documents')->insert([
                'application_id' => $applicationId,
                'candidate_document_id' => $documentId,
                'shared_at' => now(),
                'snapshot_storage_reference' => 'immutable-object-version',
            ])
        );
        $this->assertConstraintViolation('23503', 'fk_application_documents_candidate_document_id', fn () =>
            DB::table('candidate_documents')->where('id', $documentId)->delete()
        );
    }

    public function test_screening_answers_use_typed_columns_with_one_value_and_one_answer_per_question(): void
    {
        $candidateProfileId = $this->candidateProfile($this->user('answer-candidate'));
        $creatorId = $this->user('answer-creator');
        $vacancyId = $this->vacancy($creatorId);
        $applicationId = $this->application($candidateProfileId, $vacancyId, 'APPLIED');
        $questionId = $this->screeningQuestion($vacancyId);

        $this->screeningAnswer($applicationId, $questionId, ['answer_text' => 'My answer']);
        $this->assertConstraintViolation('23505', 'uq_application_screening_answers_app_question', fn () =>
            $this->screeningAnswer($applicationId, $questionId, ['answer_boolean' => true])
        );
        $this->assertConstraintViolation('23514', 'chk_application_screening_answers_one_value', fn () =>
            $this->screeningAnswer($applicationId, $this->screeningQuestion($vacancyId), [])
        );
        $this->assertConstraintViolation('23514', 'chk_application_screening_answers_one_value', fn () =>
            $this->screeningAnswer($applicationId, $this->screeningQuestion($vacancyId), [
                'answer_text' => 'Two values', 'answer_boolean' => true,
            ])
        );

        $this->assertFalse(DB::getSchemaBuilder()->hasColumn('application_screening_answers', 'answer_value_reference'));
    }

    public function test_active_selector_assignment_partial_unique_and_set_null_rules_are_physical(): void
    {
        $creatorId = $this->user('selector-creator');
        $stageId = $this->stage($this->vacancy($creatorId));
        $selectorId = $this->user('selector-user');
        $revokerId = $this->user('selector-revoker');
        $assignmentId = $this->selectorAssignment($stageId, $selectorId, $creatorId);

        DB::table('selection_stage_assignments')->where('id', $assignmentId)->update([
            'revoked_at' => now(), 'revoked_by_user_id' => $revokerId,
        ]);
        $this->selectorAssignment($stageId, $selectorId, $creatorId);
        $this->assertConstraintViolation('23505', 'uq_selection_stage_assignments_stage_selector_active', fn () =>
            $this->selectorAssignment($stageId, $selectorId, $creatorId)
        );

        DB::table('users')->where('id', $revokerId)->delete();
        $this->assertNull(DB::table('selection_stage_assignments')->where('id', $assignmentId)->value('revoked_by_user_id'));
    }

    public function test_schedule_and_schedule_history_checks_preserve_rescheduling_as_an_event(): void
    {
        $candidateProfileId = $this->candidateProfile($this->user('schedule-candidate'));
        $creatorId = $this->user('schedule-creator');
        $vacancyId = $this->vacancy($creatorId);
        $stageId = $this->stage($vacancyId);
        $applicationId = $this->application($candidateProfileId, $vacancyId, 'APPLIED', ['current_stage_id' => $stageId]);
        $scheduleId = $this->schedule($applicationId, $stageId);

        foreach (['SCHEDULED', 'COMPLETED', 'CANCELLED', 'NO_SHOW'] as $status) {
            $this->schedule($applicationId, $stageId, ['status' => $status]);
        }

        foreach (['CREATED', 'RESCHEDULED', 'COMPLETED', 'CANCELLED', 'NO_SHOW'] as $eventType) {
            $this->scheduleHistory($scheduleId, $eventType);
        }
        $this->assertConstraintViolation('23514', 'chk_selection_schedules_status', fn () =>
            $this->schedule($applicationId, $stageId, ['status' => 'RESCHEDULED'])
        );
        $this->assertConstraintViolation('23514', 'chk_selection_schedules_time', fn () =>
            $this->schedule($applicationId, $stageId, [
                'starts_at' => '2026-08-25 10:00:00+07',
                'ends_at' => '2026-08-25 09:00:00+07',
            ])
        );
        $this->assertConstraintViolation('23514', 'chk_selection_schedules_revision', fn () =>
            $this->schedule($applicationId, $stageId, ['revision_number' => -1])
        );
        $this->assertConstraintViolation('23514', 'chk_selection_schedule_histories_event_type', fn () =>
            $this->scheduleHistory($scheduleId, 'STATUS_CHANGED')
        );
    }

    public function test_evaluation_items_cascade_only_with_their_parent_evaluation(): void
    {
        $candidateProfileId = $this->candidateProfile($this->user('evaluation-candidate'));
        $evaluatorId = $this->user('evaluator');
        $vacancyId = $this->vacancy($evaluatorId);
        $stageId = $this->stage($vacancyId);
        $applicationId = $this->application($candidateProfileId, $vacancyId, 'APPLIED');
        $evaluationId = $this->evaluation($applicationId, $stageId, $evaluatorId);
        $this->evaluationItem($evaluationId);

        DB::table('evaluations')->where('id', $evaluationId)->delete();
        $this->assertSame(0, DB::table('evaluation_items')->count());
    }

    public function test_offer_status_acceptance_guard_and_partial_unique_index_are_physical(): void
    {
        $candidateProfileId = $this->candidateProfile($this->user('offer-candidate'));
        $issuerId = $this->user('offer-issuer');
        $applicationId = $this->application($candidateProfileId, $this->vacancy($issuerId), 'APPLIED');

        foreach (['DRAFT', 'SENT', 'PENDING_RESPONSE', 'REJECTED', 'EXPIRED'] as $status) {
            $this->offer($applicationId, $issuerId, $status);
        }
        $this->offer($applicationId, $issuerId, 'ACCEPTED', ['offer_accepted_at' => now()]);
        $this->assertConstraintViolation('23514', 'chk_offers_accepted_at', fn () =>
            $this->offer($applicationId, $issuerId, 'ACCEPTED')
        );
        $this->assertConstraintViolation('23505', 'uq_offers_application_accepted', fn () =>
            $this->offer($applicationId, $issuerId, 'ACCEPTED', ['offer_accepted_at' => now()])
        );
        $this->assertConstraintViolation('23514', 'chk_offers_status', fn () =>
            $this->offer($applicationId, $issuerId, 'COUNTERSIGNED')
        );
    }

    public function test_external_apply_events_remain_independent_after_phase_five_completes_the_consent_foreign_key(): void
    {
        $candidateUserId = $this->user('external-event-candidate');
        $candidateProfileId = $this->candidateProfile($candidateUserId);
        $creatorId = $this->user('external-event-creator');
        $externalVacancyId = $this->vacancy($creatorId, 'EXTERNAL_ATS');
        $consentId = $this->consent($candidateUserId);
        $eventId = $this->externalApplyEvent($candidateProfileId, $externalVacancyId, ['consent_id' => $consentId]);

        $this->assertSame($externalVacancyId, (int) DB::table('external_apply_events')->where('id', $eventId)->value('vacancy_id'));
        $this->assertSame($consentId, (int) DB::table('external_apply_events')->where('id', $eventId)->value('consent_id'));
        $this->assertConstraintViolation('23514', 'chk_external_apply_events_event_type', fn () =>
            $this->externalApplyEvent($candidateProfileId, $externalVacancyId, ['event_type' => 'EXTERNAL_APPLY_CONFIRMED'])
        );
        $this->assertConstraintViolation('23503', 'fk_external_apply_events_consent_id', fn () =>
            $this->externalApplyEvent($candidateProfileId, $externalVacancyId, ['consent_id' => 999999])
        );
        $this->assertSame(1, (int) DB::selectOne(
            "SELECT COUNT(*)::int AS count
             FROM pg_constraint
             WHERE conname = 'fk_external_apply_events_consent_id'"
        )->count);
    }

    public function test_consent_receiver_check_prevents_both_receivers_and_allows_the_frozen_at_most_one_shape(): void
    {
        $userId = $this->user('consent-user');
        $companyId = $this->company($userId);
        $unitId = $this->organizationalUnit();

        $this->consent($userId, ['receiving_company_id' => $companyId]);
        $this->consent($userId, ['receiving_organizational_unit_id' => $unitId]);
        // DATABASE_SCHEMA.md §19 deliberately permits neither for a non-sharing consent type.
        $this->consent($userId);
        $this->assertConstraintViolation('23514', 'chk_consents_receiver_xor', fn () =>
            $this->consent($userId, [
                'receiving_company_id' => $companyId,
                'receiving_organizational_unit_id' => $unitId,
            ])
        );
    }

    public function test_recruitment_outcome_xor_value_checks_and_partial_uniques_are_physical(): void
    {
        $candidateProfileId = $this->candidateProfile($this->user('outcome-candidate'));
        $creatorId = $this->user('outcome-creator');
        $vacancyId = $this->vacancy($creatorId);
        $applicationId = $this->application($candidateProfileId, $vacancyId, 'APPLIED');
        $externalEventId = $this->externalApplyEvent($candidateProfileId, $this->vacancy($creatorId, 'EXTERNAL_ATS'));

        $this->outcome('INTERNAL_APPLICATION', $applicationId, null);
        $this->outcome('EXTERNAL_APPLY', null, $externalEventId);
        foreach (['CANDIDATE', 'COMPANY', 'CAMPUS_STAFF', 'INTEGRATION'] as $reportedBy) {
            $this->outcome('INTERNAL_APPLICATION', $this->application(
                $this->candidateProfile($this->user("outcome-reporter-{$reportedBy}")),
                $vacancyId,
                'APPLIED'
            ), null, ['reported_by_source' => $reportedBy]);
        }
        $this->assertConstraintViolation('23505', 'uq_recruitment_outcomes_application', fn () =>
            $this->outcome('INTERNAL_APPLICATION', $applicationId, null)
        );
        $this->assertConstraintViolation('23505', 'uq_recruitment_outcomes_external_event', fn () =>
            $this->outcome('EXTERNAL_APPLY', null, $externalEventId)
        );
        $this->assertConstraintViolation('23514', 'chk_recruitment_outcomes_source_xor', fn () =>
            $this->outcome('INTERNAL_APPLICATION', null, null)
        );
        $this->assertConstraintViolation('23514', 'chk_recruitment_outcomes_source_type', fn () =>
            $this->outcome('MANUAL_IMPORT', null, null)
        );
        $this->assertConstraintViolation('23514', 'chk_recruitment_outcomes_reported_by_source', fn () =>
            $this->outcome('INTERNAL_APPLICATION', $this->application(
                $this->candidateProfile($this->user('outcome-extra-candidate')),
                $vacancyId,
                'APPLIED'
            ), null, ['reported_by_source' => 'SYSTEM'])
        );
    }

    public function test_phase_four_fk_delete_semantics_cover_restrict_set_null_and_cascade(): void
    {
        $candidateProfileId = $this->candidateProfile($this->user('delete-candidate'));
        $creatorId = $this->user('delete-creator');
        $vacancyId = $this->vacancy($creatorId);
        $applicationId = $this->application($candidateProfileId, $vacancyId, 'APPLIED');
        $this->applicationHistory($applicationId, 'APPLICATION_CREATED', null, null);

        $this->assertConstraintViolation('23503', 'fk_application_status_histories_application_id', fn () =>
            DB::table('applications')->where('id', $applicationId)->delete()
        );

        $picUserId = $this->user('schedule-pic');
        $stageId = $this->stage($vacancyId);
        $scheduleId = $this->schedule($applicationId, $stageId, ['pic_user_id' => $picUserId]);
        DB::table('users')->where('id', $picUserId)->delete();
        $this->assertNull(DB::table('selection_schedules')->where('id', $scheduleId)->value('pic_user_id'));

        $evaluationId = $this->evaluation($applicationId, $stageId, $creatorId);
        $this->evaluationItem($evaluationId);
        DB::table('evaluations')->where('id', $evaluationId)->delete();
        $this->assertSame(0, DB::table('evaluation_items')->count());
    }

    public function test_phase_four_indexes_jsonb_and_timestamp_types_match_the_frozen_strategy(): void
    {
        $indexes = collect(DB::select(
            "SELECT indexname, indexdef
             FROM pg_indexes
             WHERE schemaname = current_schema()
               AND indexname IN (
                   'idx_applications_vacancy_id', 'idx_applications_candidate_profile_id',
                   'idx_applications_vacancy_status_applied', 'idx_applications_candidate_applied',
                   'idx_ash_application_occurred', 'idx_application_documents_application_id',
                   'idx_application_documents_candidate_document_id',
                   'uq_selection_stage_assignments_stage_selector_active', 'idx_ssa_selector_active',
                   'idx_selection_schedules_application_id', 'idx_selection_schedules_app_starts',
                   'idx_selection_schedules_upcoming', 'idx_ssh_schedule_occurred',
                   'idx_evaluations_application_id', 'idx_evaluation_items_evaluation_id',
                   'uq_offers_application_accepted', 'idx_offers_application_id', 'idx_offers_accepted',
                   'idx_eae_candidate_profile_id', 'idx_eae_vacancy_id', 'idx_eae_vacancy_confirmation',
                   'idx_consents_application_id', 'idx_consents_user_id',
                   'uq_recruitment_outcomes_application', 'uq_recruitment_outcomes_external_event',
                   'idx_recruitment_outcomes_created'
               )"
        ))->keyBy('indexname');

        $this->assertCount(26, $indexes);
        $this->assertStringContainsString('WHERE (revoked_at IS NULL)', $indexes['uq_selection_stage_assignments_stage_selector_active']->indexdef);
        $this->assertStringContainsString("WHERE ((status)::text = 'ACCEPTED'::text)", $indexes['uq_offers_application_accepted']->indexdef);
        $this->assertStringContainsString("WHERE ((status)::text = 'SCHEDULED'::text)", $indexes['idx_selection_schedules_upcoming']->indexdef);
        $this->assertStringContainsString('WHERE (application_id IS NOT NULL)', $indexes['uq_recruitment_outcomes_application']->indexdef);

        $jsonb = DB::selectOne(
            "SELECT data_type, is_nullable
             FROM information_schema.columns
             WHERE table_schema = current_schema()
               AND table_name = 'selection_schedule_histories'
               AND column_name = 'previous_snapshot'"
        );
        $this->assertSame('jsonb', $jsonb->data_type);
        $this->assertSame('YES', $jsonb->is_nullable);
        $this->assertSame(0, (int) DB::selectOne(
            "SELECT COUNT(*)::int AS count
             FROM information_schema.columns
             WHERE table_schema = current_schema()
               AND table_name IN (
                   'applications', 'application_status_histories', 'application_documents',
                   'application_screening_answers', 'selection_stage_assignments',
                   'selection_schedules', 'selection_schedule_histories', 'evaluations',
                   'evaluation_items', 'offers', 'external_apply_events', 'consents',
                   'recruitment_outcomes'
               )
               AND data_type = 'timestamp without time zone'"
        )->count);
    }

    private function user(string $name): int
    {
        $suffix = ++$this->sequence;
        $email = "{$name}-{$suffix}@example.test";

        return (int) DB::table('users')->insertGetId([
            'name' => 'Phase Four Test User',
            'email' => $email,
            'email_normalized' => $email,
            'status' => 'ACTIVE',
        ]);
    }

    private function candidateProfile(int $userId): int
    {
        return (int) DB::table('candidate_profiles')->insertGetId([
            'user_id' => $userId,
            'current_candidate_type' => 'EXTERNAL',
        ]);
    }

    private function company(int $creatorId): int
    {
        $suffix = ++$this->sequence;

        return (int) DB::table('companies')->insertGetId([
            'name' => "Phase Four Company {$suffix}",
            'normalized_name' => "phase-four-company-{$suffix}",
            'verification_status' => 'DRAFT',
            'created_by' => $creatorId,
        ]);
    }

    private function organizationalUnit(): int
    {
        $suffix = ++$this->sequence;

        return (int) DB::table('organizational_units')->insertGetId([
            'code' => "P4-UNIT-{$suffix}",
            'name' => "Phase Four Unit {$suffix}",
        ]);
    }

    private function vacancy(int $creatorId, string $applicationMethod = 'IN_PORTAL'): int
    {
        $suffix = ++$this->sequence;

        return (int) DB::table('vacancies')->insertGetId([
            'vacancy_code' => "P4-VAC-{$suffix}",
            'slug' => "phase-four-vacancy-{$suffix}",
            'vacancy_type' => 'COMPANY_EMPLOYMENT',
            'ownership_type' => 'COMPANY',
            'company_id' => $this->company($creatorId),
            'created_by' => $creatorId,
            'title' => "Phase Four Vacancy {$suffix}",
            'description' => 'A Phase Four test vacancy.',
            'employment_type' => 'FULL_TIME',
            'openings_count' => 1,
            'target_audience' => 'PUBLIC',
            'application_method' => $applicationMethod,
            'external_ats_url' => $applicationMethod === 'EXTERNAL_ATS' ? 'https://ats.example.test/apply' : null,
            'current_status' => 'DRAFT',
        ]);
    }

    private function stage(int $vacancyId): int
    {
        $suffix = ++$this->sequence;

        return (int) DB::table('recruitment_stages')->insertGetId([
            'vacancy_id' => $vacancyId,
            'name' => "Phase Four Stage {$suffix}",
            'stage_type' => 'INTERVIEW',
            'sort_order' => $suffix,
            'active' => true,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function application(int $candidateProfileId, int $vacancyId, string $status, array $overrides = []): int
    {
        $suffix = ++$this->sequence;

        return (int) DB::table('applications')->insertGetId(array_replace([
            'application_code' => "P4-APP-{$suffix}",
            'candidate_profile_id' => $candidateProfileId,
            'vacancy_id' => $vacancyId,
            'current_status' => $status,
            'first_applied_at' => now(),
            'reopen_count' => 0,
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function applicationHistory(?int $applicationId, string $eventType, ?int $actorId, ?int $stageId, array $overrides = []): int
    {
        return (int) DB::table('application_status_histories')->insertGetId(array_replace([
            'application_id' => $applicationId,
            'event_type' => $eventType,
            'actor_user_id' => $actorId,
            'to_stage_id' => $stageId,
            'candidate_visibility' => 'VISIBLE',
            'occurred_at' => now(),
        ], $overrides));
    }

    private function candidateDocument(int $candidateProfileId): int
    {
        $suffix = ++$this->sequence;

        return (int) DB::table('candidate_documents')->insertGetId([
            'candidate_profile_id' => $candidateProfileId,
            'document_type' => 'CV',
            'display_name' => "CV {$suffix}",
            'storage_reference' => "candidate-document-{$suffix}",
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'uploaded_at' => now(),
        ]);
    }

    private function applicationDocument(int $applicationId, int $candidateDocumentId): int
    {
        return (int) DB::table('application_documents')->insertGetId([
            'application_id' => $applicationId,
            'candidate_document_id' => $candidateDocumentId,
            'shared_at' => now(),
            'snapshot_name' => 'Snapshot CV',
            'snapshot_storage_reference' => 'immutable-object-version',
        ]);
    }

    private function screeningQuestion(int $vacancyId): int
    {
        $suffix = ++$this->sequence;

        return (int) DB::table('vacancy_screening_questions')->insertGetId([
            'vacancy_id' => $vacancyId,
            'question_text' => "Phase Four Question {$suffix}",
            'question_type' => 'SHORT_TEXT',
            'required' => true,
            'sort_order' => $suffix,
            'active' => true,
        ]);
    }

    /** @param array<string, mixed> $answers */
    private function screeningAnswer(int $applicationId, int $questionId, array $answers): int
    {
        return (int) DB::table('application_screening_answers')->insertGetId(array_replace([
            'application_id' => $applicationId,
            'screening_question_id' => $questionId,
            'answered_at' => now(),
        ], $answers));
    }

    private function selectorAssignment(int $stageId, int $selectorId, int $assignerId): int
    {
        return (int) DB::table('selection_stage_assignments')->insertGetId([
            'recruitment_stage_id' => $stageId,
            'selector_user_id' => $selectorId,
            'assigned_by_user_id' => $assignerId,
            'assigned_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function schedule(int $applicationId, int $stageId, array $overrides = []): int
    {
        return (int) DB::table('selection_schedules')->insertGetId(array_replace([
            'application_id' => $applicationId,
            'recruitment_stage_id' => $stageId,
            'selection_type' => 'INTERVIEW',
            'starts_at' => '2026-08-25 09:00:00+07',
            'timezone' => 'Asia/Jakarta',
            'method' => 'ONLINE',
            'status' => 'SCHEDULED',
            'revision_number' => 0,
        ], $overrides));
    }

    private function scheduleHistory(int $scheduleId, string $eventType): int
    {
        return (int) DB::table('selection_schedule_histories')->insertGetId([
            'selection_schedule_id' => $scheduleId,
            'event_type' => $eventType,
            'previous_snapshot' => json_encode(['starts_at' => '2026-08-25T02:00:00Z'], JSON_THROW_ON_ERROR),
            'occurred_at' => now(),
        ]);
    }

    private function evaluation(int $applicationId, int $stageId, int $evaluatorId): int
    {
        return (int) DB::table('evaluations')->insertGetId([
            'application_id' => $applicationId,
            'recruitment_stage_id' => $stageId,
            'evaluator_user_id' => $evaluatorId,
        ]);
    }

    private function evaluationItem(int $evaluationId): int
    {
        return (int) DB::table('evaluation_items')->insertGetId([
            'evaluation_id' => $evaluationId,
            'criterion' => 'Communication',
            'sort_order' => 0,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function offer(int $applicationId, int $issuerId, string $status, array $overrides = []): int
    {
        return (int) DB::table('offers')->insertGetId(array_replace([
            'application_id' => $applicationId,
            'offered_by_user_id' => $issuerId,
            'offered_at' => now(),
            'status' => $status,
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function externalApplyEvent(int $candidateProfileId, int $vacancyId, array $overrides = []): int
    {
        return (int) DB::table('external_apply_events')->insertGetId(array_replace([
            'candidate_profile_id' => $candidateProfileId,
            'vacancy_id' => $vacancyId,
            'event_type' => 'EXTERNAL_APPLY_STARTED',
            'destination_url_reference' => 'https://ats.example.test/apply',
            'started_at' => now(),
            'confirmation_status' => 'PENDING',
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function consent(int $userId, array $overrides = []): int
    {
        return (int) DB::table('consents')->insertGetId(array_replace([
            'user_id' => $userId,
            'consent_type' => 'APPLICATION_SHARING',
            'consent_version' => 'v1',
            'consent_text_hash_reference' => 'consent-text-hash',
            'purpose' => 'Recruitment data sharing.',
            'consented_at' => now(),
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function outcome(string $sourceType, ?int $applicationId, ?int $externalEventId, array $overrides = []): int
    {
        return (int) DB::table('recruitment_outcomes')->insertGetId(array_replace([
            'source_type' => $sourceType,
            'application_id' => $applicationId,
            'external_apply_event_id' => $externalEventId,
            'outcome' => 'HIRED',
            'reported_by_source' => 'CAMPUS_STAFF',
        ], $overrides));
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
