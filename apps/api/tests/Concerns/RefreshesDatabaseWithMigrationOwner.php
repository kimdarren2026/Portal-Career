<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * Keeps test schema maintenance out of the application runtime connection.
 *
 * Laravel's RefreshDatabase normally runs migrate:fresh on the default
 * connection. HIGH-01 makes that default the restricted application login, so
 * tests intentionally create a separate owner connection for migrations and
 * then exercise all application code through the normal runtime connection.
 */
trait RefreshesDatabaseWithMigrationOwner
{
    use RefreshDatabase;

    private const RUNTIME_ROLE = 'portal_karir_app';

    private const APPEND_ONLY_TABLES = [
        'application_status_histories',
        'company_verification_reviews',
        'vacancy_moderation_reviews',
        'selection_schedule_histories',
        'vacancy_versions',
        'audit_logs',
    ];

    private const APPEND_ONLY_SEQUENCES = [
        'application_status_histories_id_seq',
        'company_verification_reviews_id_seq',
        'vacancy_moderation_reviews_id_seq',
        'selection_schedule_histories_id_seq',
        'vacancy_versions_id_seq',
        'audit_logs_id_seq',
    ];

    protected function migrateDatabases(): void
    {
        $migrationConnection = $this->migrationConnectionName();
        $this->configureMigrationOwnerConnection();

        DB::purge($migrationConnection);

        $options = $this->migrateFreshUsing();
        $options['--database'] = $migrationConnection;
        $this->artisan('migrate:fresh', $options);

        // Fresh migrations recreate database objects and therefore their ACLs.
        // Reapply the same object-level model as the deployment provisioning
        // artifact so every test's default connection is the real runtime role.
        $this->grantRuntimeRoleOnFreshSchema();
    }

    protected function migrationConnection(): Connection
    {
        $this->configureMigrationOwnerConnection();

        return DB::connection($this->migrationConnectionName());
    }

    private function migrationConnectionName(): string
    {
        return 'pgsql_migration_owner';
    }

    private function configureMigrationOwnerConnection(): void
    {
        $migrationConnection = $this->migrationConnectionName();
        $runtimeConnection = config('database.default');

        config(["database.connections.{$migrationConnection}" => array_replace(
            config("database.connections.{$runtimeConnection}"),
            [
                'username' => env('DB_MIGRATION_USERNAME', 'portal_karir'),
                'password' => env('DB_MIGRATION_PASSWORD', ''),
            ],
        )]);
    }

    private function grantRuntimeRoleOnFreshSchema(): void
    {
        $owner = $this->migrationConnection();

        $databaseStatements = $owner->select(
            "SELECT format(
                statement_template,
                current_database(),
                ?::text
            ) AS statement
            FROM (VALUES
                ('REVOKE CREATE, TEMPORARY ON DATABASE %I FROM PUBLIC'),
                ('REVOKE ALL PRIVILEGES ON DATABASE %I FROM %I'),
                ('GRANT CONNECT ON DATABASE %I TO %I')
            ) AS templates(statement_template)",
            [self::RUNTIME_ROLE],
        );

        foreach ($databaseStatements as $statement) {
            $owner->statement($statement->statement);
        }

        $owner->statement('REVOKE CREATE ON SCHEMA public FROM PUBLIC');
        $owner->statement('REVOKE ALL PRIVILEGES ON ALL TABLES IN SCHEMA public FROM '.self::RUNTIME_ROLE);
        $owner->statement('REVOKE ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public FROM '.self::RUNTIME_ROLE);
        $owner->statement('REVOKE ALL PRIVILEGES ON TABLE public.migrations FROM '.self::RUNTIME_ROLE);
        $owner->statement('REVOKE ALL PRIVILEGES ON SCHEMA public FROM '.self::RUNTIME_ROLE);
        $owner->statement('GRANT USAGE ON SCHEMA public TO '.self::RUNTIME_ROLE);

        $tableGrants = $owner->select(
            "SELECT format(
                'GRANT SELECT, INSERT, UPDATE, DELETE ON TABLE %I.%I TO %I',
                schemaname,
                tablename,
                ?::text
            ) AS statement
            FROM pg_catalog.pg_tables
            WHERE schemaname = 'public'
              AND tablename <> 'migrations'
              AND tablename <> ALL(?::text[])
            ORDER BY tablename",
            [self::RUNTIME_ROLE, '{'.implode(',', self::APPEND_ONLY_TABLES).'}'],
        );

        foreach ($tableGrants as $grant) {
            $owner->statement($grant->statement);
        }

        $sequenceGrants = $owner->select(
            "SELECT format(
                'GRANT USAGE, SELECT ON SEQUENCE %I.%I TO %I',
                namespace.nspname,
                relation.relname,
                ?::text
            ) AS statement
            FROM pg_class AS relation
            INNER JOIN pg_namespace AS namespace ON namespace.oid = relation.relnamespace
            WHERE relation.relkind = 'S'
              AND namespace.nspname = 'public'
              AND relation.relname <> ALL(?::text[])
            ORDER BY relation.relname",
            [self::RUNTIME_ROLE, '{'.implode(',', self::APPEND_ONLY_SEQUENCES).'}'],
        );

        foreach ($sequenceGrants as $grant) {
            $owner->statement($grant->statement);
        }

        $appendOnlyTables = implode(', public.', self::APPEND_ONLY_TABLES);
        $appendOnlySequences = implode(', public.', self::APPEND_ONLY_SEQUENCES);

        $owner->statement('GRANT SELECT, INSERT ON TABLE public.'.$appendOnlyTables.' TO '.self::RUNTIME_ROLE);
        $owner->statement('REVOKE UPDATE, DELETE ON TABLE public.'.$appendOnlyTables.' FROM '.self::RUNTIME_ROLE);
        $owner->statement('GRANT USAGE, SELECT ON SEQUENCE public.'.$appendOnlySequences.' TO '.self::RUNTIME_ROLE);
    }
}
