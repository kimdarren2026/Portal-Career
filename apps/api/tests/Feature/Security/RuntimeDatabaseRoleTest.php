<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshesDatabaseWithMigrationOwner;
use Tests\TestCase;

/**
 * HIGH-01 integration coverage: this connection is copied from Laravel's
 * configured default, not supplied with an owner credential by the test.
 */
final class RuntimeDatabaseRoleTest extends TestCase
{
    use RefreshesDatabaseWithMigrationOwner;

    public function test_configured_runtime_connection_has_ordinary_dml_and_append_only_access(): void
    {
        $runtime = $this->runtimeConnection();

        $this->assertSame('portal_karir_app', $runtime->selectOne('SELECT current_user')->current_user);
        $this->assertSame('portal_karir_app', config('database.connections.'.config('database.default').'.username'));

        $suffix = bin2hex(random_bytes(8));
        $email = "high01-runtime-{$suffix}@example.test";
        $runtime->beginTransaction();

        try {
            $userId = (int) $runtime->table('users')->insertGetId([
                'name' => 'HIGH-01 Runtime Connection',
                'email' => $email,
                'email_normalized' => $email,
                'status' => 'ACTIVE',
            ]);

            $this->assertSame(1, $runtime->table('users')->where('id', $userId)->update([
                'name' => 'HIGH-01 Runtime Updated',
                'updated_at' => now(),
            ]));
            $this->assertSame('HIGH-01 Runtime Updated', $runtime->table('users')->where('id', $userId)->value('name'));

            // PostgreSQL-backed sessions are a production runtime responsibility
            // (ADR-006), so prove their normal write/update/delete lifecycle.
            $sessionId = 'high01-'.$suffix;
            $runtime->table('sessions')->insert([
                'id' => $sessionId,
                'user_id' => $userId,
                'payload' => 'initial-payload',
                'last_activity' => now()->timestamp,
            ]);
            $this->assertSame(1, $runtime->table('sessions')->where('id', $sessionId)->update([
                'payload' => 'updated-payload',
            ]));
            $this->assertSame(1, $runtime->table('sessions')->where('id', $sessionId)->delete());

            // Redis is the active cache store, but these database tables are
            // provisioned fallback infrastructure and retain ordinary DML.
            $runtime->table('cache')->insert([
                'key' => "high01-cache-{$suffix}",
                'value' => 'initial-cache-value',
                'expiration' => now()->addHour()->timestamp,
            ]);
            $this->assertSame(1, $runtime->table('cache')->where('key', "high01-cache-{$suffix}")->update([
                'value' => 'updated-cache-value',
            ]));
            $this->assertSame(1, $runtime->table('cache')->where('key', "high01-cache-{$suffix}")->delete());

            $runtime->table('cache_locks')->insert([
                'key' => "high01-lock-{$suffix}",
                'owner' => 'initial-owner',
                'expiration' => now()->addHour()->timestamp,
            ]);
            $this->assertSame(1, $runtime->table('cache_locks')->where('key', "high01-lock-{$suffix}")->update([
                'owner' => 'updated-owner',
            ]));
            $this->assertSame(1, $runtime->table('cache_locks')->where('key', "high01-lock-{$suffix}")->delete());

            $failedJobId = (int) $runtime->table('failed_jobs')->insertGetId([
                'uuid' => "high01-{$suffix}",
                'connection' => 'redis',
                'queue' => 'maintenance',
                'payload' => '{}',
                'exception' => 'initial failure',
            ]);
            $this->assertSame(1, $runtime->table('failed_jobs')->where('id', $failedJobId)->update([
                'exception' => 'updated failure',
            ]));
            $this->assertSame(1, $runtime->table('failed_jobs')->where('id', $failedJobId)->delete());

            $idempotencyId = (int) $runtime->table('idempotency_keys')->insertGetId([
                'idempotency_key' => "high01-{$suffix}",
                'actor_user_id' => $userId,
                'actor_context' => 'WEB',
                'operation' => 'HIGH01_RUNTIME_ROLE_TEST',
                'request_fingerprint' => str_repeat('a', 64),
                'state' => 'PROCESSING',
            ]);
            $this->assertSame(1, $runtime->table('idempotency_keys')->where('id', $idempotencyId)->update([
                'state' => 'COMPLETED',
                'response_status' => 200,
                'response_body' => json_encode(['ok' => true], JSON_THROW_ON_ERROR),
            ]));
            $this->assertSame(1, $runtime->table('idempotency_keys')->where('id', $idempotencyId)->delete());

            $exportId = (int) $runtime->table('export_jobs')->insertGetId([
                'requested_by_user_id' => $userId,
                'export_type' => 'HIGH01_RUNTIME_ROLE_TEST',
                'parameters' => json_encode([], JSON_THROW_ON_ERROR),
                'authorized_scope' => json_encode(['user_id' => $userId], JSON_THROW_ON_ERROR),
                'status' => 'PENDING',
                'requested_at' => now(),
            ]);
            $this->assertSame(1, $runtime->table('export_jobs')->where('id', $exportId)->update([
                'status' => 'PROCESSING',
                'started_at' => now(),
            ]));
            $this->assertSame(1, $runtime->table('export_jobs')->where('id', $exportId)->delete());

            foreach (['sessions', 'cache', 'cache_locks', 'failed_jobs', 'idempotency_keys', 'export_jobs'] as $table) {
                foreach (['SELECT', 'INSERT', 'UPDATE', 'DELETE'] as $privilege) {
                    $allowed = $runtime->selectOne(
                        "SELECT has_table_privilege(current_user, ?, '{$privilege}') AS allowed",
                        [$table],
                    )->allowed;

                    $this->assertTrue($allowed, "Expected {$privilege} on runtime table {$table}.");
                }
            }

            $auditId = (int) $runtime->table('audit_logs')->insertGetId([
                'action' => 'HIGH01_RUNTIME_ROLE_TEST',
                'object_type' => 'runtime_database_role_test',
                'object_id' => $userId,
                'change_summary' => json_encode(['runtime_role' => true], JSON_THROW_ON_ERROR),
            ]);

            $this->assertSame('HIGH01_RUNTIME_ROLE_TEST', $runtime->table('audit_logs')->where('id', $auditId)->value('action'));
        } finally {
            $runtime->rollBack();
        }

        $this->assertPermissionDenied(fn () => $runtime->statement(
            "UPDATE public.audit_logs SET action = 'MUTATION_MUST_FAIL' WHERE id = {$auditId}"
        ));
        $this->assertPermissionDenied(fn () => $runtime->statement(
            "DELETE FROM public.audit_logs WHERE id = {$auditId}"
        ));
    }

    public function test_runtime_role_has_no_ownership_ddl_or_privilege_escalation_path(): void
    {
        $runtime = $this->runtimeConnection();

        $attributes = $runtime->selectOne(
            'SELECT rolcanlogin, rolsuper, rolcreatedb, rolcreaterole, rolinherit, rolreplication, rolbypassrls
             FROM pg_roles
             WHERE rolname = current_user'
        );

        $this->assertTrue($attributes->rolcanlogin);
        $this->assertFalse($attributes->rolsuper);
        $this->assertFalse($attributes->rolcreatedb);
        $this->assertFalse($attributes->rolcreaterole);
        $this->assertFalse($attributes->rolinherit);
        $this->assertFalse($attributes->rolreplication);
        $this->assertFalse($attributes->rolbypassrls);

        $this->assertSame(0, (int) $runtime->selectOne(
            "SELECT count(*) AS count
             FROM pg_class AS relation
             INNER JOIN pg_namespace AS namespace ON namespace.oid = relation.relnamespace
             WHERE namespace.nspname = 'public'
               AND relation.relowner = (SELECT oid FROM pg_roles WHERE rolname = current_user)"
        )->count);
        $this->assertFalse((bool) $runtime->selectOne(
            "SELECT EXISTS (
                SELECT 1
                FROM pg_namespace
                WHERE nspname = 'public'
                  AND nspowner = (SELECT oid FROM pg_roles WHERE rolname = current_user)
            ) AS owns_public_schema"
        )->owns_public_schema);
        $this->assertFalse((bool) $runtime->selectOne(
            "SELECT EXISTS (
                SELECT 1
                FROM pg_database
                WHERE datname = current_database()
                  AND datdba = (SELECT oid FROM pg_roles WHERE rolname = current_user)
            ) AS owns_database"
        )->owns_database);
        $this->assertSame(0, (int) $runtime->selectOne(
            "SELECT count(*) AS count
             FROM pg_auth_members AS membership
             INNER JOIN pg_roles AS member ON member.oid = membership.member
             WHERE member.rolname = current_user"
        )->count);

        $this->assertFalse((bool) $runtime->selectOne(
            "SELECT has_schema_privilege(current_user, 'public', 'CREATE') AS allowed"
        )->allowed);
        $this->assertFalse((bool) $runtime->selectOne(
            "SELECT has_database_privilege(current_user, current_database(), 'CREATE') AS allowed"
        )->allowed);
        $this->assertFalse((bool) $runtime->selectOne(
            "SELECT has_database_privilege(current_user, current_database(), 'TEMPORARY') AS allowed"
        )->allowed);

        foreach (['SELECT', 'INSERT', 'UPDATE', 'DELETE'] as $privilege) {
            $this->assertFalse((bool) $runtime->selectOne(
                "SELECT has_table_privilege(current_user, 'migrations', '{$privilege}') AS allowed"
            )->allowed);
        }

        $this->assertPermissionDenied(fn () => $runtime->statement(
            'CREATE TABLE public.high01_runtime_ddl_denied (id bigint)'
        ));
        $this->assertPermissionDenied(fn () => $runtime->statement(
            'CREATE TEMPORARY TABLE high01_runtime_temp_ddl_denied (id bigint)'
        ));
        // PostgreSQL emits a warning (rather than SQLSTATE 42501) when a role
        // without GRANT OPTION tries to grant a privilege it does not hold. The
        // effective privilege must remain absent.
        $runtime->statement('GRANT UPDATE ON TABLE public.audit_logs TO portal_karir_app');
        $this->assertFalse((bool) $runtime->selectOne(
            "SELECT has_table_privilege(current_user, 'audit_logs', 'UPDATE') AS allowed"
        )->allowed);
        $this->assertPermissionDenied(fn () => $runtime->statement(
            'ALTER ROLE portal_karir_app CREATEDB'
        ));
    }

    private function runtimeConnection(): Connection
    {
        $runtimeConnection = config('database.default');

        config(['database.connections.high01_runtime' => config("database.connections.{$runtimeConnection}")]);
        DB::purge('high01_runtime');

        return DB::connection('high01_runtime');
    }

    private function assertPermissionDenied(Closure $operation): void
    {
        try {
            $operation();
            $this->fail('Expected the restricted runtime role to be denied.');
        } catch (QueryException $exception) {
            $this->assertSame('42501', $exception->getCode());
        }
    }
}
