<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Final PostgreSQL-only verification for the deferred Phase-7 integrity layer.
 * These tests use direct writes and a separate runtime role so application code
 * cannot conceal a missing physical guarantee.
 */
final class PhaseSevenDatabaseConstraintsTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    public function test_phase_seven_completes_the_frozen_table_inventory(): void
    {
        $tables = collect(DB::select(
            "SELECT tablename FROM pg_tables WHERE schemaname = current_schema() ORDER BY tablename"
        ))->pluck('tablename')->all();

        $this->assertCount(58, $tables);
        $this->assertCount(51, array_diff($tables, [
            'migrations', 'sessions', 'cache', 'cache_locks', 'failed_jobs',
            'idempotency_keys', 'export_jobs',
        ]));
        $this->assertContains('user_roles', $tables);

        foreach (['personal_access_tokens', 'jobs', 'job_batches'] as $deferredTable) {
            $this->assertFalse(DB::getSchemaBuilder()->hasTable($deferredTable));
        }
    }

    public function test_user_roles_has_the_exact_frozen_shape_constraints_and_partial_unique_index(): void
    {
        $columns = collect(DB::select(
            "SELECT column_name, data_type, is_nullable, is_identity, identity_generation
             FROM information_schema.columns
             WHERE table_schema = current_schema() AND table_name = 'user_roles'
             ORDER BY ordinal_position"
        ))->map(fn (object $column): string => implode(':', [
            $column->column_name,
            $column->data_type,
            $column->is_nullable,
            $column->is_identity,
            $column->identity_generation ?? '',
        ]))->all();

        $this->assertSame([
            'id:bigint:NO:YES:ALWAYS',
            'user_id:bigint:NO:NO:',
            'role_id:bigint:NO:NO:',
            'assigned_at:timestamp with time zone:NO:NO:',
            'assigned_by:bigint:YES:NO:',
            'revoked_at:timestamp with time zone:YES:NO:',
            'revoked_by:bigint:YES:NO:',
        ], $columns);

        $foreignKeys = collect(DB::select(
            "SELECT con.conname, con.confupdtype, con.confdeltype
             FROM pg_constraint con
             JOIN pg_class c ON c.oid = con.conrelid
             JOIN pg_namespace n ON n.oid = c.relnamespace
             WHERE n.nspname = current_schema() AND c.relname = 'user_roles' AND con.contype = 'f'
             ORDER BY con.conname"
        ))->map(fn (object $key): string => "{$key->conname}:{$key->confupdtype}:{$key->confdeltype}")->all();

        $this->assertSame([
            'fk_user_roles_assigned_by:a:n',
            'fk_user_roles_revoked_by:a:n',
            'fk_user_roles_role_id:a:r',
            'fk_user_roles_user_id:a:r',
        ], $foreignKeys);

        $index = DB::selectOne(
            "SELECT indexdef FROM pg_indexes
             WHERE schemaname = current_schema()
               AND tablename = 'user_roles'
               AND indexname = 'uq_user_roles_user_role_active'"
        );
        $this->assertStringContainsString('UNIQUE', $index->indexdef);
        $this->assertStringContainsString('(user_id, role_id)', $index->indexdef);
        $this->assertStringContainsString('WHERE (revoked_at IS NULL)', $index->indexdef);
    }

    public function test_user_role_history_allows_reassignment_after_revocation_but_rejects_two_active_rows(): void
    {
        $userId = $this->user('role-holder');
        $roleId = $this->role('ROLE_HISTORY');
        $assignedBy = $this->user('role-assigner');
        $revokedBy = $this->user('role-revoker');

        $firstAssignment = $this->assignment($userId, $roleId, $assignedBy);
        $this->assertConstraintViolation('23505', 'uq_user_roles_user_role_active', fn () =>
            $this->assignment($userId, $roleId, $assignedBy)
        );

        DB::table('user_roles')->where('id', $firstAssignment)->update([
            'revoked_at' => now(),
            'revoked_by' => $revokedBy,
        ]);
        $secondAssignment = $this->assignment($userId, $roleId, $assignedBy);

        $this->assertNotSame($firstAssignment, $secondAssignment);
        $this->assertSame(2, DB::table('user_roles')->where('user_id', $userId)->where('role_id', $roleId)->count());

        DB::table('users')->where('id', $assignedBy)->delete();
        DB::table('users')->where('id', $revokedBy)->delete();

        $this->assertNull(DB::table('user_roles')->where('id', $firstAssignment)->value('assigned_by'));
        $this->assertNull(DB::table('user_roles')->where('id', $firstAssignment)->value('revoked_by'));
        $this->assertConstraintViolation('23503', 'fk_user_roles_user_id', fn () =>
            DB::table('users')->where('id', $userId)->delete()
        );
        $this->assertConstraintViolation('23503', 'fk_user_roles_role_id', fn () =>
            DB::table('roles')->where('id', $roleId)->delete()
        );
    }

    public function test_all_five_p0_partial_unique_indexes_and_phase_seven_index_classes_are_present(): void
    {
        $indexes = collect(DB::select(
            "SELECT indexname, indexdef
             FROM pg_indexes
             WHERE schemaname = current_schema()"
        ))->mapWithKeys(fn (object $index): array => [$index->indexname => $index->indexdef]);

        foreach ([
            'uq_user_roles_user_role_active' => 'revoked_at IS NULL',
            'uq_company_members_company_user_active' => 'revoked_at IS NULL',
            'uq_offers_application_accepted' => "status)::text = 'ACCEPTED'",
            'uq_smtp_configurations_active' => 'WHERE is_active',
            'uq_selection_stage_assignments_stage_selector_active' => 'revoked_at IS NULL',
        ] as $name => $fragment) {
            $this->assertArrayHasKey($name, $indexes->all());
            $this->assertStringContainsString($fragment, $indexes[$name]);
        }

        foreach ([
            // Class B — approved reverse-lookup and RESTRICT support.
            'idx_applications_vacancy_id', 'idx_applications_candidate_profile_id', 'idx_ash_application_occurred',
            'idx_application_documents_application_id', 'idx_application_documents_candidate_document_id',
            'idx_selection_schedules_application_id', 'idx_ssh_schedule_occurred', 'idx_evaluations_application_id',
            'idx_evaluation_items_evaluation_id', 'idx_offers_application_id', 'idx_consents_application_id',
            'idx_consents_user_id', 'idx_eae_candidate_profile_id', 'idx_eae_vacancy_id', 'idx_vacancies_company_id',
            'idx_vacancies_organizational_unit_id', 'idx_vacancy_requirements_vacancy_sort',
            'idx_vsq_vacancy_sort_active', 'idx_vacancy_documents_vacancy_id', 'idx_vmr_vacancy_reviewed',
            'idx_recruitment_stages_vacancy_sort', 'idx_company_members_user_id', 'idx_company_documents_company_id',
            'idx_company_documents_superseded_by', 'idx_company_documents_company_current', 'idx_cvr_company_reviewed',
            'idx_partnerships_company_status', 'idx_candidate_documents_profile_type',
            'idx_candidate_verifications_profile_type_status', 'idx_candidate_educations_candidate_profile_id',
            'idx_candidate_work_experiences_candidate_profile_id', 'idx_candidate_skills_candidate_profile_id',
            'idx_candidate_organizations_candidate_profile_id', 'idx_candidate_certifications_candidate_profile_id',
            'idx_candidate_links_candidate_profile_id',
            // Classes C, D, and E.
            'idx_email_outbox_due', 'idx_email_outbox_dead_letter', 'idx_idempotency_keys_expires_at',
            'idx_export_jobs_status_requested', 'idx_export_jobs_requested_by', 'idx_export_jobs_expires_at',
            'idx_sessions_last_activity', 'idx_vacancies_public_listing', 'idx_vacancies_public_filters',
            'idx_vacancies_close_at', 'idx_vacancies_open_at', 'idx_companies_verification_queue',
            'idx_vacancies_moderation_queue', 'idx_applications_vacancy_status_applied',
            'idx_applications_candidate_applied', 'idx_selection_schedules_app_starts',
            'idx_selection_schedules_upcoming', 'idx_notifications_user_unread', 'idx_ssa_selector_active',
            'idx_offers_accepted', 'idx_eae_vacancy_confirmation', 'idx_recruitment_outcomes_created',
        ] as $name) {
            $this->assertArrayHasKey($name, $indexes->all());
        }

        $ginCount = DB::selectOne(
            "SELECT COUNT(*)::int AS count
             FROM pg_index i
             JOIN pg_class c ON c.oid = i.indexrelid
             JOIN pg_am am ON am.oid = c.relam
             JOIN pg_namespace n ON n.oid = c.relnamespace
             WHERE n.nspname = current_schema() AND am.amname = 'gin'"
        );
        $this->assertSame(0, $ginCount->count);
    }

    public function test_append_only_runtime_role_cannot_update_or_delete_and_schema_owner_can_maintain(): void
    {
        $tables = [
            'application_status_histories' => 'occurred_at',
            'company_verification_reviews' => 'reviewed_at',
            'vacancy_moderation_reviews' => 'reviewed_at',
            'selection_schedule_histories' => 'occurred_at',
            'vacancy_versions' => 'created_at',
            'audit_logs' => 'created_at',
        ];

        $runtime = $this->applicationConnection();
        $this->assertSame('portal_karir_app', $runtime->selectOne('SELECT current_user')->current_user);

        foreach ($tables as $table => $column) {
            $this->assertSame(1, (int) $runtime->selectOne("SELECT has_table_privilege(current_user, '{$table}', 'SELECT') AS allowed")->allowed);
            $this->assertSame(1, (int) $runtime->selectOne("SELECT has_table_privilege(current_user, '{$table}', 'INSERT') AS allowed")->allowed);
            $this->assertPermissionDenied(fn () => $runtime->statement("UPDATE {$table} SET {$column} = {$column} WHERE FALSE"));
            $this->assertPermissionDenied(fn () => $runtime->statement("DELETE FROM {$table} WHERE FALSE"));
        }

        foreach ([
            'application_status_histories_id_seq',
            'company_verification_reviews_id_seq',
            'vacancy_moderation_reviews_id_seq',
            'selection_schedule_histories_id_seq',
            'vacancy_versions_id_seq',
            'audit_logs_id_seq',
        ] as $sequence) {
            $this->assertSame(1, (int) $runtime->selectOne(
                "SELECT has_sequence_privilege(current_user, '{$sequence}', 'USAGE') AS allowed"
            )->allowed);
        }

        // The test connection is the migration/schema owner. A zero-row write
        // proves it retains maintenance capability without changing evidence.
        $this->assertSame('portal_karir', DB::selectOne('SELECT current_user')->current_user);
        foreach ($tables as $table => $column) {
            DB::statement("UPDATE {$table} SET {$column} = {$column} WHERE FALSE");
            DB::statement("DELETE FROM {$table} WHERE FALSE");
        }
    }

    public function test_postgresql_session_timezone_is_utc(): void
    {
        $this->assertSame('UTC', DB::selectOne('SHOW TimeZone')->TimeZone);
    }

    private function applicationConnection(): Connection
    {
        config(['database.connections.append_only_application' => array_replace(
            config('database.connections.pgsql'),
            ['username' => 'portal_karir_app']
        )]);
        DB::purge('append_only_application');

        return DB::connection('append_only_application');
    }

    private function user(string $suffix): int
    {
        $this->sequence++;
        $email = "phase-seven-{$suffix}-{$this->sequence}@example.test";

        return (int) DB::table('users')->insertGetId([
            'name' => 'Phase Seven User',
            'email' => $email,
            'email_normalized' => $email,
            'status' => 'ACTIVE',
        ]);
    }

    private function role(string $code): int
    {
        return (int) DB::table('roles')->insertGetId([
            'code' => $code,
            'name' => 'Phase Seven Role',
        ]);
    }

    private function assignment(int $userId, int $roleId, ?int $assignedBy): int
    {
        return (int) DB::table('user_roles')->insertGetId([
            'user_id' => $userId,
            'role_id' => $roleId,
            'assigned_at' => now(),
            'assigned_by' => $assignedBy,
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

    private function assertPermissionDenied(Closure $operation): void
    {
        try {
            $operation();
            $this->fail('Expected the restricted application role to be denied.');
        } catch (QueryException $exception) {
            $this->assertSame('42501', $exception->getCode());
        }
    }
}
