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

    public function test_no_business_migration_exists_yet(): void
    {
        // This phase creates framework infrastructure tables only. The 51
        // business tables belong to MIGRATION_PLAN.md Phases 1-5.
        $migrations = collect(glob(database_path('migrations/*.php')))
            ->map(fn (string $path) => basename($path))
            ->values();

        $expected = [
            '0001_01_01_000000_create_sessions_table.php',
            '0001_01_01_000001_create_cache_table.php',
            '0001_01_01_000002_create_failed_jobs_table.php',
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

    public function test_laravel_default_business_tables_are_not_scaffolded(): void
    {
        // `users` and `password_reset_tokens` are BUSINESS tables owned by logical
        // model 1.1-C3. Laravel's password_reset_tokens shape would collide with
        // the business table and satisfy neither INV-021 nor the model (ADR-011).
        foreach (glob(database_path('migrations/*.php')) as $path) {
            $contents = (string) file_get_contents($path);
            $this->assertStringNotContainsString("Schema::create('users'", $contents);
            $this->assertStringNotContainsString("Schema::create('password_reset_tokens'", $contents);
        }
    }
}
