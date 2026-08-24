<?php

declare(strict_types=1);

namespace Tests\Feature\Bootstrap;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Throwable;

/**
 * Asserts the frozen infrastructure decisions are actually configured, not merely
 * documented. These are the choices most easily lost to a framework default.
 */
final class InfrastructureConfigurationTest extends TestCase
{
    public function test_database_is_postgresql_and_never_sqlite(): void
    {
        // ADR-003. SQLite shares neither partial unique indexes nor CHECK
        // semantics, so a SQLite run would skip the guarantees that matter most.
        $this->assertSame('pgsql', config('database.default'));
        $this->assertNotSame('sqlite', config('database.default'));
    }

    public function test_sessions_are_stored_in_postgresql_not_redis(): void
    {
        // ADR-006: a Redis eviction would log out every user simultaneously.
        // The test runner deliberately overrides SESSION_DRIVER to `array`, so
        // this asserts the SHIPPED configuration rather than the runtime value.
        $config = (string) file_get_contents(config_path('session.php'));
        $this->assertStringContainsString("env('SESSION_DRIVER', 'database')", $config);

        $env = (string) file_get_contents(base_path('.env.example'));
        $this->assertStringContainsString('SESSION_DRIVER=database', $env);
        $this->assertStringNotContainsString('SESSION_DRIVER=redis', $env);
    }

    public function test_cache_queue_and_locks_use_redis(): void
    {
        // ADR-006: one Redis instance serves cache, queue, locks and rate limiting,
        // on separate logical databases so a cache flush cannot destroy queued work.
        $env = (string) file_get_contents(base_path('.env.example'));
        $this->assertStringContainsString('CACHE_STORE=redis', $env);
        $this->assertStringContainsString('QUEUE_CONNECTION=redis', $env);
        $this->assertStringContainsString('REDIS_CACHE_DB=0', $env);
        $this->assertStringContainsString('REDIS_QUEUE_DB=1', $env);
        $this->assertStringContainsString('REDIS_LOCK_DB=2', $env);
    }

    public function test_database_connection_is_reachable(): void
    {
        try {
            DB::connection()->getPdo();
        } catch (Throwable $e) {
            $this->markTestSkipped(
                'PostgreSQL is not reachable in this environment: '.$e->getMessage()
            );
        }

        $this->assertSame('pgsql', DB::connection()->getDriverName());
    }

    public function test_only_framework_and_approved_phase_one_through_five_migrations_exist(): void
    {
        // Phase 5 adds only communication, audit, and configuration data. Every later
        // business phase remains absent at this checkpoint.
        $migrations = collect(glob(database_path('migrations/*.php')))
            ->map(fn (string $path) => basename($path))
            ->values();

        $expected = [
            '0001_01_01_000000_create_sessions_table.php',
            '0001_01_01_000001_create_cache_table.php',
            '0001_01_01_000002_create_failed_jobs_table.php',
            '2026_08_24_000100_create_geographic_areas_table.php',
            '2026_08_24_000101_create_organizational_units_table.php',
            '2026_08_24_000102_create_industries_table.php',
            '2026_08_24_000103_create_organization_types_table.php',
            '2026_08_24_000104_create_skills_table.php',
            '2026_08_24_000105_create_study_programs_table.php',
            '2026_08_24_000106_create_roles_table.php',
            '2026_08_24_000107_create_users_table.php',
            '2026_08_24_000108_create_password_credentials_table.php',
            '2026_08_24_000109_create_email_verification_tokens_table.php',
            '2026_08_24_000110_create_password_reset_tokens_table.php',
            '2026_08_24_000200_create_candidate_profiles_table.php',
            '2026_08_24_000201_create_candidate_verifications_table.php',
            '2026_08_24_000202_create_candidate_documents_table.php',
            '2026_08_24_000203_create_candidate_educations_table.php',
            '2026_08_24_000204_create_candidate_work_experiences_table.php',
            '2026_08_24_000205_create_candidate_organizations_table.php',
            '2026_08_24_000206_create_candidate_skills_table.php',
            '2026_08_24_000207_create_candidate_certifications_table.php',
            '2026_08_24_000208_create_candidate_links_table.php',
            '2026_08_24_000209_create_companies_table.php',
            '2026_08_24_000210_create_company_members_table.php',
            '2026_08_24_000211_create_company_documents_table.php',
            '2026_08_24_000212_create_company_verification_reviews_table.php',
            '2026_08_24_000213_create_partnerships_table.php',
            '2026_08_24_000300_create_vacancies_table.php',
            '2026_08_24_000301_create_vacancy_versions_table.php',
            '2026_08_24_000302_create_vacancy_requirements_table.php',
            '2026_08_24_000303_create_vacancy_documents_table.php',
            '2026_08_24_000304_create_vacancy_screening_questions_table.php',
            '2026_08_24_000305_create_vacancy_moderation_reviews_table.php',
            '2026_08_24_000306_create_recruitment_stages_table.php',
            '2026_08_24_000307_create_candidate_saved_vacancies_table.php',
            '2026_08_24_000400_create_applications_table.php',
            '2026_08_24_000401_create_application_status_histories_table.php',
            '2026_08_24_000402_create_application_documents_table.php',
            '2026_08_24_000403_create_application_screening_answers_table.php',
            '2026_08_24_000404_create_selection_stage_assignments_table.php',
            '2026_08_24_000405_create_selection_schedules_table.php',
            '2026_08_24_000406_create_selection_schedule_histories_table.php',
            '2026_08_24_000407_create_evaluations_table.php',
            '2026_08_24_000408_create_evaluation_items_table.php',
            '2026_08_24_000409_create_offers_table.php',
            '2026_08_24_000410_create_external_apply_events_table.php',
            '2026_08_24_000411_create_consents_table.php',
            '2026_08_24_000412_create_recruitment_outcomes_table.php',
            '2026_08_24_000500_create_notifications_table.php',
            '2026_08_24_000501_create_email_outbox_table.php',
            '2026_08_24_000502_create_audit_logs_table.php',
            '2026_08_24_000503_create_smtp_configurations_table.php',
            '2026_08_24_000504_add_consent_foreign_key_to_external_apply_events_table.php',
        ];

        $this->assertSame($expected, $migrations->all());
    }

    public function test_queue_and_job_tables_are_not_created_in_database(): void
    {
        // The queue is Redis (ADR-006); `jobs` and `job_batches` must not exist.
        foreach (glob(database_path('migrations/*.php')) as $path) {
            $contents = (string) file_get_contents($path);
            $this->assertStringNotContainsString("Schema::create('jobs'", $contents);
            $this->assertStringNotContainsString("Schema::create('job_batches'", $contents);
        }
    }

    public function test_identity_uses_the_frozen_business_shape_not_laravel_defaults(): void
    {
        // Laravel's stock token table has an email primary key. The frozen
        // business table is user-owned and records use/revocation state
        // (ADR-011, INV-021).
        $migration = (string) file_get_contents(
            database_path('migrations/2026_08_24_000110_create_password_reset_tokens_table.php')
        );

        $this->assertStringContainsString("Schema::create('password_reset_tokens'", $migration);
        $this->assertStringContainsString('$table->bigInteger(\'user_id\')', $migration);
        $this->assertStringContainsString('$table->timestampTz(\'used_at\')->nullable()', $migration);
        $this->assertStringContainsString('$table->timestampTz(\'revoked_at\')->nullable()', $migration);
        $this->assertStringNotContainsString('$table->string(\'email\')', $migration);
    }
}
