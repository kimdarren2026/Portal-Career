<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Direct PostgreSQL coverage for Phase 5 communication, audit, and SMTP
 * configuration storage. No mail, notification, audit, or configuration
 * application behaviour is introduced here.
 */
final class PhaseFiveDatabaseConstraintsTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    public function test_phase_five_tables_remain_present_with_phase_six_operational_tables(): void
    {
        $tables = collect(DB::select(
            "SELECT tablename FROM pg_tables WHERE schemaname = current_schema() ORDER BY tablename"
        ))->pluck('tablename')->all();

        $this->assertCount(57, $tables);
        foreach (['notifications', 'email_outbox', 'audit_logs', 'smtp_configurations'] as $table) {
            $this->assertContains($table, $tables);
        }

        $this->assertTrue(DB::getSchemaBuilder()->hasTable('idempotency_keys'));
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('export_jobs'));
        $this->assertFalse(DB::getSchemaBuilder()->hasTable('user_roles'));
        $this->assertFalse(DB::getSchemaBuilder()->hasTable('personal_access_tokens'));
    }

    public function test_every_phase_five_primary_key_is_a_generated_always_bigint(): void
    {
        $identities = collect(DB::select(
            "SELECT table_name
             FROM information_schema.columns
             WHERE table_schema = current_schema()
               AND table_name IN ('notifications', 'email_outbox', 'audit_logs', 'smtp_configurations')
               AND column_name = 'id'
               AND data_type = 'bigint'
               AND is_nullable = 'NO'
               AND is_identity = 'YES'
               AND identity_generation = 'ALWAYS'
             ORDER BY table_name"
        ))->pluck('table_name')->all();

        $this->assertSame(['audit_logs', 'email_outbox', 'notifications', 'smtp_configurations'], $identities);
    }

    public function test_postgresql_exposes_the_phase_five_constraint_and_index_catalog_with_frozen_names(): void
    {
        $counts = collect(DB::select(
            "SELECT con.contype::text AS type, COUNT(*)::int AS count
             FROM pg_constraint con
             JOIN pg_class c ON c.oid = con.conrelid
             JOIN pg_namespace n ON n.oid = c.relnamespace
             WHERE n.nspname = current_schema()
               AND c.relname IN ('notifications', 'email_outbox', 'audit_logs', 'smtp_configurations')
             GROUP BY con.contype
             ORDER BY con.contype"
        ))->map(fn (object $row): string => "{$row->type}:{$row->count}")->all();

        $this->assertSame(['c:8', 'f:3', 'p:4'], $counts);

        $constraints = collect(DB::select(
            "SELECT con.conname
             FROM pg_constraint con
             JOIN pg_class c ON c.oid = con.conrelid
             JOIN pg_namespace n ON n.oid = c.relnamespace
             WHERE n.nspname = current_schema()
               AND c.relname IN ('notifications', 'email_outbox', 'audit_logs', 'smtp_configurations')"
        ))->pluck('conname')->all();

        foreach ([
            'fk_notifications_user_id', 'pk_notifications',
            'pk_email_outbox', 'chk_email_outbox_status', 'chk_email_outbox_attempts',
            'fk_audit_logs_actor_user_id', 'pk_audit_logs',
            'fk_smtp_configurations_updated_by_user_id', 'pk_smtp_configurations',
            'chk_smtp_configurations_encryption_mode', 'chk_smtp_configurations_last_test_result',
            'chk_smtp_configurations_port', 'chk_smtp_configurations_attempts',
            'chk_smtp_configurations_backoff', 'chk_smtp_configurations_timeout',
        ] as $constraint) {
            $this->assertContains($constraint, $constraints);
        }

        $indexes = collect(DB::select(
            "SELECT indexname
             FROM pg_indexes
             WHERE schemaname = current_schema()
               AND tablename IN ('notifications', 'email_outbox', 'audit_logs', 'smtp_configurations')"
        ))->pluck('indexname')->all();

        foreach ([
            'idx_notifications_user_unread',
            'idx_email_outbox_due', 'idx_email_outbox_dead_letter',
            'idx_audit_logs_created_at', 'idx_audit_logs_actor', 'idx_audit_logs_object',
            'idx_audit_logs_action', 'idx_audit_logs_correlation',
            'uq_smtp_configurations_active',
        ] as $index) {
            $this->assertContains($index, $indexes);
        }
    }

    public function test_notifications_have_the_unread_lookup_shape_and_restrict_the_recipient(): void
    {
        $userId = $this->user('notification-recipient');
        $notificationId = (int) DB::table('notifications')->insertGetId([
            'user_id' => $userId,
            'type' => 'APPLICATION_STATUS_CHANGED',
            'title' => 'Application update',
            'body_reference' => 'Your application has progressed.',
            'related_object_type' => 'applications',
            'related_object_id' => 999,
        ]);

        $this->assertNull(DB::table('notifications')->where('id', $notificationId)->value('read_at'));
        $this->assertConstraintViolation('23503', 'fk_notifications_user_id', fn () =>
            DB::table('users')->where('id', $userId)->delete()
        );
    }

    public function test_email_outbox_status_and_attempt_guards_are_physical(): void
    {
        foreach (['PENDING', 'PROCESSING', 'SENT', 'FAILED_RETRYABLE', 'DEAD_LETTER'] as $status) {
            $this->outbox($status);
        }

        $defaultId = $this->outbox('PENDING', ['attempt_count' => null]);
        $this->assertSame(0, (int) DB::table('email_outbox')->where('id', $defaultId)->value('attempt_count'));
        $this->assertConstraintViolation('23514', 'chk_email_outbox_status', fn () => $this->outbox('FAILED'));
        $this->assertConstraintViolation('23514', 'chk_email_outbox_attempts', fn () =>
            $this->outbox('PENDING', ['attempt_count' => -1])
        );
        $this->assertConstraintViolation('23502', 'recipient', fn () =>
            DB::table('email_outbox')->insert([
                'template_reference' => 'template',
                'status' => 'PENDING',
                'attempt_count' => 0,
            ])
        );
    }

    public function test_email_outbox_payload_is_jsonb_and_has_no_smtp_configuration_columns(): void
    {
        $this->outbox('PENDING', [
            'payload_reference' => json_encode(['candidate_name' => 'Alya'], JSON_THROW_ON_ERROR),
        ]);

        $columns = collect(DB::select(
            "SELECT column_name, data_type
             FROM information_schema.columns
             WHERE table_schema = current_schema() AND table_name = 'email_outbox'"
        ))->mapWithKeys(fn (object $column): array => [$column->column_name => $column->data_type]);

        $this->assertSame('jsonb', $columns['payload_reference']);
        $this->assertArrayNotHasKey('encrypted_password', $columns->all());
        $this->assertArrayNotHasKey('host', $columns->all());
    }

    public function test_audit_logs_use_native_inet_jsonb_and_preserve_history_when_actor_is_removed(): void
    {
        $actorId = $this->user('audit-actor');
        $auditId = (int) DB::table('audit_logs')->insertGetId([
            'actor_user_id' => $actorId,
            'action' => 'APPLICATION_CREATED',
            'object_type' => 'applications',
            'object_id' => 123,
            'change_summary' => json_encode(['status' => 'APPLIED'], JSON_THROW_ON_ERROR),
            'correlation_id' => 'request-phase-five-audit',
            'ip_address' => '203.0.113.10',
            'user_agent_device_metadata' => json_encode(['browser' => 'test'], JSON_THROW_ON_ERROR),
        ]);

        $types = collect(DB::select(
            "SELECT column_name, data_type
             FROM information_schema.columns
             WHERE table_schema = current_schema()
               AND table_name = 'audit_logs'
               AND column_name IN ('ip_address', 'change_summary', 'user_agent_device_metadata')"
        ))->mapWithKeys(fn (object $column): array => [$column->column_name => $column->data_type]);

        $this->assertSame('inet', $types['ip_address']);
        $this->assertSame('jsonb', $types['change_summary']);
        $this->assertSame('jsonb', $types['user_agent_device_metadata']);

        DB::table('users')->where('id', $actorId)->delete();
        $this->assertNull(DB::table('audit_logs')->where('id', $auditId)->value('actor_user_id'));
    }

    public function test_smtp_configuration_checks_and_single_active_partial_unique_index_are_physical(): void
    {
        $updatedBy = $this->user('smtp-updated-by');
        foreach (['NONE', 'STARTTLS', 'TLS'] as $encryptionMode) {
            $this->smtpConfiguration($updatedBy, ['encryption_mode' => $encryptionMode]);
        }

        $defaultId = $this->smtpConfiguration($updatedBy, ['is_active' => null]);
        $this->assertFalse((bool) DB::table('smtp_configurations')->where('id', $defaultId)->value('is_active'));
        $this->smtpConfiguration($updatedBy, ['is_active' => true]);

        $this->assertConstraintViolation('23505', 'uq_smtp_configurations_active', fn () =>
            $this->smtpConfiguration($updatedBy, ['is_active' => true])
        );
        $this->assertConstraintViolation('23514', 'chk_smtp_configurations_encryption_mode', fn () =>
            $this->smtpConfiguration($updatedBy, ['encryption_mode' => 'SSL'])
        );
        $this->assertConstraintViolation('23514', 'chk_smtp_configurations_last_test_result', fn () =>
            $this->smtpConfiguration($updatedBy, ['last_test_result' => 'UNKNOWN'])
        );
        $this->assertConstraintViolation('23514', 'chk_smtp_configurations_port', fn () =>
            $this->smtpConfiguration($updatedBy, ['port' => 0])
        );
        $this->assertConstraintViolation('23514', 'chk_smtp_configurations_attempts', fn () =>
            $this->smtpConfiguration($updatedBy, ['max_attempts' => 21])
        );
        $this->assertConstraintViolation('23514', 'chk_smtp_configurations_backoff', fn () =>
            $this->smtpConfiguration($updatedBy, ['retry_backoff_seconds' => 0])
        );
        $this->assertConstraintViolation('23514', 'chk_smtp_configurations_timeout', fn () =>
            $this->smtpConfiguration($updatedBy, ['timeout_seconds' => 601])
        );
    }

    public function test_smtp_ciphertext_is_nullable_text_and_not_indexed(): void
    {
        $updatedBy = $this->user('smtp-secret-metadata');
        $configurationId = $this->smtpConfiguration($updatedBy, [
            'encrypted_password' => 'ciphertext-only-placeholder',
        ]);

        $column = DB::selectOne(
            "SELECT data_type, is_nullable
             FROM information_schema.columns
             WHERE table_schema = current_schema()
               AND table_name = 'smtp_configurations'
               AND column_name = 'encrypted_password'"
        );
        $definitions = collect(DB::select(
            "SELECT indexdef FROM pg_indexes
             WHERE schemaname = current_schema() AND tablename = 'smtp_configurations'"
        ))->pluck('indexdef')->implode("\n");

        $this->assertSame('text', $column->data_type);
        $this->assertSame('YES', $column->is_nullable);
        $this->assertSame('ciphertext-only-placeholder', DB::table('smtp_configurations')->where('id', $configurationId)->value('encrypted_password'));
        $this->assertStringNotContainsString('encrypted_password', $definitions);
    }

    public function test_smtp_configuration_attribution_is_restrict_not_set_null(): void
    {
        $updatedBy = $this->user('smtp-attribution');
        $this->smtpConfiguration($updatedBy);

        $this->assertConstraintViolation('23503', 'fk_smtp_configurations_updated_by_user_id', fn () =>
            DB::table('users')->where('id', $updatedBy)->delete()
        );
    }

    public function test_phase_five_completes_the_deferred_external_apply_consent_foreign_key(): void
    {
        $userId = $this->user('external-consent-user');
        $candidateProfileId = $this->candidateProfile($userId);
        $vacancyId = $this->vacancy($userId);
        $consentId = $this->consent($userId);

        $eventId = $this->externalApplyEvent($candidateProfileId, $vacancyId, ['consent_id' => $consentId]);
        $this->assertSame($consentId, (int) DB::table('external_apply_events')->where('id', $eventId)->value('consent_id'));
        $this->assertConstraintViolation('23503', 'fk_external_apply_events_consent_id', fn () =>
            $this->externalApplyEvent($candidateProfileId, $vacancyId, ['consent_id' => 999999])
        );
        $this->assertConstraintViolation('23503', 'fk_external_apply_events_consent_id', fn () =>
            DB::table('consents')->where('id', $consentId)->delete()
        );

        $foreignKey = DB::selectOne(
            "SELECT con.confdeltype::text AS delete_action, con.confupdtype::text AS update_action
             FROM pg_constraint con
             JOIN pg_class c ON c.oid = con.conrelid
             JOIN pg_namespace n ON n.oid = c.relnamespace
             WHERE n.nspname = current_schema()
               AND c.relname = 'external_apply_events'
               AND con.conname = 'fk_external_apply_events_consent_id'"
        );
        $this->assertSame('r', $foreignKey->delete_action);
        $this->assertSame('a', $foreignKey->update_action);
    }

    /** @param array<string, mixed> $overrides */
    private function outbox(string $status, array $overrides = []): int
    {
        $suffix = ++$this->sequence;
        $values = array_filter(array_replace([
            'recipient' => "phase-five-{$suffix}@example.test",
            'template_reference' => "phase-five-template-{$suffix}",
            'status' => $status,
            'attempt_count' => 0,
        ], $overrides), static fn (mixed $value): bool => $value !== null);

        return (int) DB::table('email_outbox')->insertGetId($values);
    }

    /** @param array<string, mixed> $overrides */
    private function smtpConfiguration(int $updatedBy, array $overrides = []): int
    {
        $suffix = ++$this->sequence;
        $values = array_filter(array_replace([
            'host' => "smtp-{$suffix}.example.test",
            'port' => 587,
            'encryption_mode' => 'STARTTLS',
            'from_address' => "no-reply-{$suffix}@example.test",
            'max_attempts' => 3,
            'retry_backoff_seconds' => 60,
            'updated_by_user_id' => $updatedBy,
            'is_active' => false,
        ], $overrides), static fn (mixed $value): bool => $value !== null);

        return (int) DB::table('smtp_configurations')->insertGetId($values);
    }

    private function user(string $label): int
    {
        $suffix = ++$this->sequence;

        return (int) DB::table('users')->insertGetId([
            'name' => "Phase Five {$label}",
            'email' => "phase-five-{$suffix}@example.test",
            'email_normalized' => "phase-five-{$suffix}@example.test",
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

    private function vacancy(int $creatorId): int
    {
        $suffix = ++$this->sequence;
        $companyId = (int) DB::table('companies')->insertGetId([
            'name' => "Phase Five Company {$suffix}",
            'normalized_name' => "phase-five-company-{$suffix}",
            'verification_status' => 'DRAFT',
            'created_by' => $creatorId,
        ]);

        return (int) DB::table('vacancies')->insertGetId([
            'vacancy_code' => "P5-VAC-{$suffix}",
            'slug' => "phase-five-vacancy-{$suffix}",
            'vacancy_type' => 'COMPANY_EMPLOYMENT',
            'ownership_type' => 'COMPANY',
            'company_id' => $companyId,
            'created_by' => $creatorId,
            'title' => "Phase Five Vacancy {$suffix}",
            'description' => 'A Phase Five test vacancy.',
            'employment_type' => 'FULL_TIME',
            'openings_count' => 1,
            'target_audience' => 'PUBLIC',
            'application_method' => 'EXTERNAL_ATS',
            'external_ats_url' => 'https://ats.example.test/apply',
            'current_status' => 'DRAFT',
        ]);
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

    private function consent(int $userId): int
    {
        return (int) DB::table('consents')->insertGetId([
            'user_id' => $userId,
            'consent_type' => 'EXTERNAL_TRACKING',
            'consent_version' => 'v1',
            'consent_text_hash_reference' => 'phase-five-consent-text-hash',
            'purpose' => 'External application tracking.',
            'consented_at' => now(),
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
