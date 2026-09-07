\set ON_ERROR_STOP on

-- ===========================================================================
-- Portal Career — runtime database privilege VERIFIER (READ-ONLY).
--
-- Asserts the effective PostgreSQL privileges of the restricted runtime login
--
--     portal_karir_app
--
-- against the frozen provisioning contract (infra/deployment/README.md,
-- infra/deployment/provision-runtime-db-role.sql, INV-016).
--
-- This script performs NO DDL/DML: no CREATE/ALTER/GRANT/REVOKE/DROP and no
-- INSERT/UPDATE/DELETE. It only SELECTs from catalogs and calls the
-- has_*_privilege() inspection functions.
--
-- Run through psql as the database owner / bootstrap principal:
--
--     psql -v ON_ERROR_STOP=1 -f infra/deployment/verify-runtime-db-role.sql
--
-- Exit status:
--   0  -> every invariant holds; prints PORTAL_CAREER_RUNTIME_DB_PRIVILEGES_VERIFIED
--   non-zero -> at least one invariant failed (a sanitized FAIL line is printed)
--
-- A failed check prints its sanitized FAIL line, then runs
--   SELECT 'RUNTIME_DB_PRIVILEGE_INVARIANT_FAILED'::int;
-- a pure read-only statement whose cast error makes psql abort non-zero under
-- ON_ERROR_STOP. \quit cannot carry an exit code, so it must not be relied on.
--
-- Never prints passwords or connection strings.
-- ===========================================================================

\set r portal_karir_app

-- --- 0. the role must exist, or nothing below is meaningful -----------------
SELECT EXISTS (
    SELECT 1 FROM pg_roles WHERE rolname = :'r'
)::text AS ok \gset
\if :ok
\else
    \echo 'FAIL: role portal_karir_app does not exist.'
    SELECT 'RUNTIME_DB_PRIVILEGE_INVARIANT_FAILED'::int;
\endif

-- --- 1. role attributes ----------------------------------------------------
--   rolcanlogin=t  rolsuper=f  rolcreatedb=f  rolcreaterole=f
--   rolinherit=f   rolreplication=f  rolbypassrls=f
SELECT (
        bool_and(rolcanlogin)
    AND bool_and(NOT rolsuper)
    AND bool_and(NOT rolcreatedb)
    AND bool_and(NOT rolcreaterole)
    AND bool_and(NOT rolinherit)
    AND bool_and(NOT rolreplication)
    AND bool_and(NOT rolbypassrls)
)::text AS ok
FROM pg_roles
WHERE rolname = :'r' \gset
\if :ok
\else
    \echo 'FAIL: portal_karir_app role attributes deviate from LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOREPLICATION NOBYPASSRLS.'
    SELECT 'RUNTIME_DB_PRIVILEGE_INVARIANT_FAILED'::int;
\endif

-- --- 2. the role must NOT own the current database ------------------------
SELECT (
    NOT EXISTS (
        SELECT 1
        FROM pg_database d
        JOIN pg_roles o ON o.oid = d.datdba
        WHERE d.datname = current_database()
          AND o.rolname = :'r'
    )
)::text AS ok \gset
\if :ok
\else
    \echo 'FAIL: portal_karir_app owns the current database.'
    SELECT 'RUNTIME_DB_PRIVILEGE_INVARIANT_FAILED'::int;
\endif

-- --- 3. the role must own ZERO tables in public -------------------------
SELECT (count(*) = 0)::text AS ok,
       coalesce(string_agg(c.relname, ', ' ORDER BY c.relname), '(none)') AS owned
FROM pg_class c
JOIN pg_namespace n ON n.oid = c.relnamespace
JOIN pg_roles o ON o.oid = c.relowner
WHERE n.nspname = 'public'
  AND c.relkind IN ('r', 'p')      -- ordinary + partitioned tables
  AND o.rolname = :'r' \gset
\if :ok
\else
    \echo 'FAIL: portal_karir_app owns table(s) in public:'
    \echo :'owned'
    SELECT 'RUNTIME_DB_PRIVILEGE_INVARIANT_FAILED'::int;
\endif

-- --- 4. no CREATE privilege on schema public ----------------------------
SELECT (has_schema_privilege(:'r', 'public', 'CREATE') = false)::text AS ok \gset
\if :ok
\else
    \echo 'FAIL: portal_karir_app has CREATE on schema public.'
    SELECT 'RUNTIME_DB_PRIVILEGE_INVARIANT_FAILED'::int;
\endif

-- --- 5. Laravel migrations registry: no runtime CRUD ------------------
SELECT (to_regclass('public.migrations') IS NOT NULL)::text AS ok \gset
\if :ok
\else
    \echo 'FAIL: public.migrations table is missing (schema not migrated?).'
    SELECT 'RUNTIME_DB_PRIVILEGE_INVARIANT_FAILED'::int;
\endif
SELECT (
        has_table_privilege(:'r', 'public.migrations', 'SELECT') = false
    AND has_table_privilege(:'r', 'public.migrations', 'INSERT') = false
    AND has_table_privilege(:'r', 'public.migrations', 'UPDATE') = false
    AND has_table_privilege(:'r', 'public.migrations', 'DELETE') = false
)::text AS ok \gset
\if :ok
\else
    \echo 'FAIL: portal_karir_app holds a privilege on public.migrations (expected none).'
    SELECT 'RUNTIME_DB_PRIVILEGE_INVARIANT_FAILED'::int;
\endif

-- --- 6. six append-only evidence tables (INV-016) --------------------
--   required: SELECT=t INSERT=t UPDATE=f DELETE=f ; all six must be present.
--   The MATERIALIZED CTE resolves the table set BEFORE has_table_privilege()
--   is called, so the privilege function never sees a non-public relation
--   (WHERE-clause qual order is not guaranteed in a flat query).
WITH ao(tablename) AS MATERIALIZED (
    SELECT tablename
    FROM pg_tables
    WHERE schemaname = 'public'
      AND tablename IN (
          'application_status_histories',
          'company_verification_reviews',
          'vacancy_moderation_reviews',
          'selection_schedule_histories',
          'vacancy_versions',
          'audit_logs'
      )
),
ao_bad AS MATERIALIZED (
    SELECT tablename
    FROM ao
    WHERE NOT (
                has_table_privilege(:'r', format('public.%I', tablename), 'SELECT')
            AND has_table_privilege(:'r', format('public.%I', tablename), 'INSERT')
            AND has_table_privilege(:'r', format('public.%I', tablename), 'UPDATE') = false
            AND has_table_privilege(:'r', format('public.%I', tablename), 'DELETE') = false
    )
)
SELECT ((SELECT count(*) FROM ao) = 6 AND (SELECT count(*) FROM ao_bad) = 0)::text AS ok,
       (SELECT count(*) FROM ao)::text AS found,
       coalesce((SELECT string_agg(tablename, ', ' ORDER BY tablename) FROM ao_bad), '(none)') AS offenders \gset
\if :ok
\else
    \echo 'FAIL: append-only invariant broken. Expected 6 tables with SELECT,INSERT only.'
    \echo 'append-only tables found:' :'found'
    \echo 'append-only tables not matching SELECT=t INSERT=t UPDATE=f DELETE=f:' :'offenders'
    SELECT 'RUNTIME_DB_PRIVILEGE_INVARIANT_FAILED'::int;
\endif

-- --- 7. ordinary application tables -----------------------------------
--   every public base table except `migrations` and the six append-only
--   tables must have full CRUD (SELECT=t INSERT=t UPDATE=t DELETE=t).
--   Also assert the set is non-empty, so an empty/wrong database cannot
--   produce a vacuous PASS.
SELECT (count(*) > 0)::text AS ok
FROM pg_tables
WHERE schemaname = 'public'
  AND tablename NOT IN (
      'migrations',
      'application_status_histories',
      'company_verification_reviews',
      'vacancy_moderation_reviews',
      'selection_schedule_histories',
      'vacancy_versions',
      'audit_logs'
  ) \gset
\if :ok
\else
    \echo 'FAIL: no ordinary application tables found in public (wrong database?).'
    SELECT 'RUNTIME_DB_PRIVILEGE_INVARIANT_FAILED'::int;
\endif

WITH ordinary(tablename) AS MATERIALIZED (
    SELECT tablename
    FROM pg_tables
    WHERE schemaname = 'public'
      AND tablename NOT IN (
          'migrations',
          'application_status_histories',
          'company_verification_reviews',
          'vacancy_moderation_reviews',
          'selection_schedule_histories',
          'vacancy_versions',
          'audit_logs'
      )
),
ordinary_bad AS MATERIALIZED (
    SELECT tablename
    FROM ordinary
    WHERE NOT (
                has_table_privilege(:'r', format('public.%I', tablename), 'SELECT')
            AND has_table_privilege(:'r', format('public.%I', tablename), 'INSERT')
            AND has_table_privilege(:'r', format('public.%I', tablename), 'UPDATE')
            AND has_table_privilege(:'r', format('public.%I', tablename), 'DELETE')
    )
)
SELECT ((SELECT count(*) FROM ordinary_bad) = 0)::text AS ok,
       coalesce((SELECT string_agg(tablename, ', ' ORDER BY tablename) FROM ordinary_bad), '(none)') AS offenders \gset
\if :ok
\else
    \echo 'FAIL: ordinary application tables missing full CRUD for portal_karir_app:'
    \echo :'offenders'
    SELECT 'RUNTIME_DB_PRIVILEGE_INVARIANT_FAILED'::int;
\endif

\echo 'PORTAL_CAREER_RUNTIME_DB_PRIVILEGES_VERIFIED'
