<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshesDatabaseWithMigrationOwner;
use Tests\TestCase;

/**
 * Direct PostgreSQL coverage for the Phase-6 operational schema. Application
 * middleware, export workers, cleanup jobs, and routes remain intentionally absent.
 */
final class PhaseSixDatabaseConstraintsTest extends TestCase
{
    use RefreshesDatabaseWithMigrationOwner;

    private int $sequence = 0;

    public function test_phase_six_adds_only_the_two_operational_tables(): void
    {
        $tables = collect(DB::select(
            "SELECT tablename FROM pg_tables WHERE schemaname = current_schema() ORDER BY tablename"
        ))->pluck('tablename')->all();

        $this->assertCount(58, $tables);
        $this->assertCount(51, array_diff($tables, [
            'migrations', 'sessions', 'cache', 'cache_locks', 'failed_jobs',
            'idempotency_keys', 'export_jobs',
        ]));
        $this->assertContains('idempotency_keys', $tables);
        $this->assertContains('export_jobs', $tables);

        foreach (['personal_access_tokens', 'jobs', 'job_batches'] as $deferredTable) {
            $this->assertFalse(DB::getSchemaBuilder()->hasTable($deferredTable));
        }
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('user_roles'));
    }

    public function test_phase_six_primary_keys_are_generated_always_bigints(): void
    {
        $identities = collect(DB::select(
            "SELECT table_name
             FROM information_schema.columns
             WHERE table_schema = current_schema()
               AND table_name IN ('idempotency_keys', 'export_jobs')
               AND column_name = 'id'
               AND data_type = 'bigint'
               AND is_nullable = 'NO'
               AND is_identity = 'YES'
               AND identity_generation = 'ALWAYS'
             ORDER BY table_name"
        ))->pluck('table_name')->all();

        $this->assertSame(['export_jobs', 'idempotency_keys'], $identities);
    }

    public function test_postgresql_exposes_the_phase_six_constraint_and_index_catalog_with_frozen_names(): void
    {
        $counts = collect(DB::select(
            "SELECT con.contype::text AS type, COUNT(*)::int AS count
             FROM pg_constraint con
             JOIN pg_class c ON c.oid = con.conrelid
             JOIN pg_namespace n ON n.oid = c.relnamespace
             WHERE n.nspname = current_schema()
               AND c.relname IN ('idempotency_keys', 'export_jobs')
             GROUP BY con.contype
             ORDER BY con.contype"
        ))->map(fn (object $row): string => "{$row->type}:{$row->count}")->all();

        $this->assertSame(['c:2', 'f:2', 'p:2'], $counts);

        $constraints = collect(DB::select(
            "SELECT con.conname
             FROM pg_constraint con
             JOIN pg_class c ON c.oid = con.conrelid
             JOIN pg_namespace n ON n.oid = c.relnamespace
             WHERE n.nspname = current_schema()
               AND c.relname IN ('idempotency_keys', 'export_jobs')"
        ))->pluck('conname')->all();

        foreach ([
            'pk_idempotency_keys', 'fk_idempotency_keys_actor_user_id', 'chk_idempotency_keys_state',
            'pk_export_jobs', 'fk_export_jobs_requested_by_user_id', 'chk_export_jobs_status',
        ] as $constraint) {
            $this->assertContains($constraint, $constraints);
        }

        $indexes = collect(DB::select(
            "SELECT indexname, indexdef
             FROM pg_indexes
             WHERE schemaname = current_schema()
               AND tablename IN ('idempotency_keys', 'export_jobs', 'sessions')"
        ))->mapWithKeys(fn (object $index): array => [$index->indexname => $index->indexdef]);

        foreach ([
            'uq_idempotency_keys_scope', 'idx_idempotency_keys_expires_at',
            'idx_export_jobs_status_requested', 'idx_export_jobs_requested_by',
            'idx_export_jobs_expires_at', 'idx_sessions_last_activity',
        ] as $index) {
            $this->assertArrayHasKey($index, $indexes->all());
        }

        $this->assertArrayNotHasKey('sessions_last_activity_index', $indexes->all());
        $this->assertStringContainsString('COALESCE(actor_user_id', $indexes['uq_idempotency_keys_scope']);
        $this->assertStringContainsString('requested_at DESC', $indexes['idx_export_jobs_requested_by']);
        $this->assertStringContainsString('WHERE', $indexes['idx_export_jobs_expires_at']);
        $this->assertStringContainsString('COMPLETED', $indexes['idx_export_jobs_expires_at']);
    }

    public function test_operational_columns_use_the_frozen_postgresql_types_and_defaults(): void
    {
        $columns = collect(DB::select(
            "SELECT table_name, column_name, data_type, character_maximum_length, is_nullable, column_default
             FROM information_schema.columns
             WHERE table_schema = current_schema()
               AND table_name IN ('idempotency_keys', 'export_jobs')"
        ))->keyBy(fn (object $column): string => "{$column->table_name}.{$column->column_name}");

        $this->assertSame(255, $columns['idempotency_keys.idempotency_key']->character_maximum_length);
        $this->assertSame(191, $columns['idempotency_keys.operation']->character_maximum_length);
        $this->assertSame(64, $columns['idempotency_keys.actor_context']->character_maximum_length);
        $this->assertSame(64, $columns['idempotency_keys.request_fingerprint']->character_maximum_length);
        $this->assertSame('smallint', $columns['idempotency_keys.response_status']->data_type);
        $this->assertSame('jsonb', $columns['idempotency_keys.response_body']->data_type);
        $this->assertSame('YES', $columns['idempotency_keys.actor_user_id']->is_nullable);
        $this->assertSame('YES', $columns['idempotency_keys.response_body']->is_nullable);

        foreach ([
            'idempotency_keys.created_at', 'idempotency_keys.completed_at', 'idempotency_keys.expires_at',
            'export_jobs.requested_at', 'export_jobs.started_at', 'export_jobs.completed_at',
            'export_jobs.expires_at', 'export_jobs.downloaded_at',
        ] as $timestampColumn) {
            $this->assertSame('timestamp with time zone', $columns[$timestampColumn]->data_type);
        }

        $this->assertSame('jsonb', $columns['export_jobs.parameters']->data_type);
        $this->assertSame('jsonb', $columns['export_jobs.authorized_scope']->data_type);
        $this->assertSame('NO', $columns['export_jobs.parameters']->is_nullable);
        $this->assertSame('NO', $columns['export_jobs.authorized_scope']->is_nullable);
        $this->assertStringContainsString('0', (string) $columns['export_jobs.download_count']->column_default);

        $idempotencyId = $this->idempotencyKey();
        $lifetime = DB::selectOne(
            "SELECT EXTRACT(EPOCH FROM expires_at - created_at) AS seconds
             FROM idempotency_keys WHERE id = ?",
            [$idempotencyId]
        );
        $this->assertSame(86400.0, (float) $lifetime->seconds);

        $names = $columns->keys()->implode("\n");
        $this->assertStringNotContainsString('request_body', $names);
        $this->assertStringNotContainsString('request_payload', $names);
    }

    public function test_idempotency_state_check_and_required_values_are_physical(): void
    {
        foreach (['PROCESSING', 'COMPLETED', 'FAILED'] as $state) {
            $this->idempotencyKey(['state' => $state]);
        }

        $this->assertConstraintViolation('23514', 'chk_idempotency_keys_state', fn () =>
            $this->idempotencyKey(['state' => 'QUEUED'])
        );
        $this->assertConstraintViolation('23502', 'idempotency_key', fn () =>
            DB::table('idempotency_keys')->insert([
                'actor_context' => 'INERTIA_WEB:session',
                'operation' => 'applications.submit',
                'request_fingerprint' => str_repeat('a', 64),
                'state' => 'PROCESSING',
            ])
        );
    }

    public function test_idempotency_scope_is_key_operation_and_actor_user_with_null_actor_coalescing(): void
    {
        $sameKey = 'phase-six-replay-key';
        $this->idempotencyKey([
            'idempotency_key' => $sameKey,
            'operation' => 'applications.submit',
            'actor_user_id' => null,
        ]);

        $this->assertConstraintViolation('23505', 'uq_idempotency_keys_scope', fn () =>
            $this->idempotencyKey([
                'idempotency_key' => $sameKey,
                'operation' => 'applications.submit',
                'actor_user_id' => null,
                'actor_context' => 'VERSIONED_API:token',
            ])
        );

        $actorOne = $this->user('idempotency-actor-one');
        $actorTwo = $this->user('idempotency-actor-two');
        $this->idempotencyKey([
            'idempotency_key' => $sameKey,
            'operation' => 'applications.submit',
            'actor_user_id' => $actorOne,
        ]);
        $this->idempotencyKey([
            'idempotency_key' => $sameKey,
            'operation' => 'applications.submit',
            'actor_user_id' => $actorTwo,
        ]);
        $this->idempotencyKey([
            'idempotency_key' => $sameKey,
            'operation' => 'applications.withdraw',
            'actor_user_id' => $actorOne,
        ]);
    }

    public function test_idempotency_response_is_jsonb_and_actor_foreign_key_cascades(): void
    {
        $actorId = $this->user('idempotency-cascade');
        $keyId = $this->idempotencyKey([
            'actor_user_id' => $actorId,
            'state' => 'COMPLETED',
            'response_status' => 201,
            'response_body' => json_encode(['data' => ['id' => 42]], JSON_THROW_ON_ERROR),
        ]);

        $this->assertSame(201, (int) DB::table('idempotency_keys')->where('id', $keyId)->value('response_status'));
        $this->assertSame('{"data": {"id": 42}}', DB::table('idempotency_keys')->where('id', $keyId)->value('response_body'));

        DB::table('users')->where('id', $actorId)->delete();
        $this->assertFalse(DB::table('idempotency_keys')->where('id', $keyId)->exists());
    }

    public function test_export_job_status_check_required_snapshots_and_default_are_physical(): void
    {
        $requesterId = $this->user('export-requester');
        foreach (['PENDING', 'PROCESSING', 'COMPLETED', 'FAILED', 'EXPIRED'] as $status) {
            $this->exportJob($requesterId, ['status' => $status]);
        }

        $defaultId = $this->exportJob($requesterId, ['download_count' => null]);
        $this->assertSame(0, (int) DB::table('export_jobs')->where('id', $defaultId)->value('download_count'));
        $this->assertConstraintViolation('23514', 'chk_export_jobs_status', fn () =>
            $this->exportJob($requesterId, ['status' => 'QUEUED'])
        );
        $this->assertConstraintViolation('23502', 'parameters', fn () =>
            $this->exportJob($requesterId, ['parameters' => null])
        );
        $this->assertConstraintViolation('23502', 'authorized_scope', fn () =>
            $this->exportJob($requesterId, ['authorized_scope' => null])
        );
    }

    public function test_export_job_scope_snapshot_is_jsonb_and_requester_is_restricted(): void
    {
        $requesterId = $this->user('export-scope');
        $jobId = $this->exportJob($requesterId, [
            'parameters' => json_encode(['status' => 'PUBLISHED'], JSON_THROW_ON_ERROR),
            'authorized_scope' => json_encode(['company_ids' => [17]], JSON_THROW_ON_ERROR),
            'status' => 'COMPLETED',
            'row_count' => 7,
        ]);

        $job = DB::table('export_jobs')->where('id', $jobId)->first(['parameters', 'authorized_scope', 'row_count']);
        $this->assertSame('{"status": "PUBLISHED"}', $job->parameters);
        $this->assertSame('{"company_ids": [17]}', $job->authorized_scope);
        $this->assertSame(7, (int) $job->row_count);
        $this->assertConstraintViolation('23503', 'fk_export_jobs_requested_by_user_id', fn () =>
            DB::table('users')->where('id', $requesterId)->delete()
        );
    }

    /** @param array<string, mixed> $overrides */
    private function idempotencyKey(array $overrides = []): int
    {
        $suffix = ++$this->sequence;
        $values = array_filter(array_replace([
            'idempotency_key' => "phase-six-key-{$suffix}",
            'actor_context' => 'INERTIA_WEB:session',
            'operation' => 'applications.submit',
            'request_fingerprint' => hash('sha256', "phase-six-request-{$suffix}"),
            'state' => 'PROCESSING',
            'actor_user_id' => null,
            'response_status' => null,
            'response_body' => null,
            'response_reference' => null,
            'completed_at' => null,
            'expires_at' => null,
        ], $overrides), static fn (mixed $value): bool => $value !== null);

        return (int) DB::table('idempotency_keys')->insertGetId($values);
    }

    /** @param array<string, mixed> $overrides */
    private function exportJob(int $requesterId, array $overrides = []): int
    {
        $suffix = ++$this->sequence;
        $values = array_filter(array_replace([
            'requested_by_user_id' => $requesterId,
            'export_type' => "application-funnel-{$suffix}",
            'parameters' => json_encode(['period' => '2026-08'], JSON_THROW_ON_ERROR),
            'authorized_scope' => json_encode(['organization_unit_ids' => [1]], JSON_THROW_ON_ERROR),
            'status' => 'PENDING',
            'requested_at' => now(),
            'storage_reference' => null,
            'row_count' => null,
            'failure_summary' => null,
            'started_at' => null,
            'completed_at' => null,
            'expires_at' => null,
            'downloaded_at' => null,
            'download_count' => 0,
        ], $overrides), static fn (mixed $value): bool => $value !== null);

        return (int) DB::table('export_jobs')->insertGetId($values);
    }

    private function user(string $label): int
    {
        $suffix = ++$this->sequence;

        return (int) DB::table('users')->insertGetId([
            'name' => "Phase Six {$label}",
            'email' => "phase-six-{$suffix}@example.test",
            'email_normalized' => "phase-six-{$suffix}@example.test",
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
