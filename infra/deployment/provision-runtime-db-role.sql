\set ON_ERROR_STOP on

-- PostgreSQL 16+ deployment/bootstrap artifact for HIGH-01.
--
-- Run through psql as a database administrator against the target database.
-- This script provisions the application login and existing-object grants; it
-- intentionally does not replace the frozen Phase-7 migration's release-level
-- append-only grant/revoke semantics.
--
-- The password is supplied only through the process environment. It is never
-- stored, printed, or written to this repository.
\set runtime_role_name portal_karir_app
\getenv runtime_role_password PORTAL_KARIR_RUNTIME_DB_PASSWORD

\if :{?runtime_role_password}
SELECT (length(:'runtime_role_password') > 0)::text AS runtime_password_supplied \gset
\else
\set runtime_password_supplied false
\endif

SELECT EXISTS (
    SELECT 1
    FROM pg_roles
    WHERE rolname = :'runtime_role_name'
)::text AS runtime_role_exists \gset

\if :runtime_role_exists
    -- Preserve an existing credential when an operator deliberately omits the
    -- secret. Attributes are still corrected on every run.
    ALTER ROLE :"runtime_role_name"
        LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOREPLICATION NOBYPASSRLS;

    \if :runtime_password_supplied
        ALTER ROLE :"runtime_role_name" PASSWORD :'runtime_role_password';
    \else
        \echo 'Runtime role exists; password was not supplied and is unchanged.'
    \endif
\else
    \if :runtime_password_supplied
        CREATE ROLE :"runtime_role_name"
            LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOREPLICATION NOBYPASSRLS
            PASSWORD :'runtime_role_password';
    \else
        \echo 'ERROR: PORTAL_KARIR_RUNTIME_DB_PASSWORD is required to create portal_karir_app.'
        \quit 3
    \endif
\endif

-- A runtime principal that owns a schema object bypasses table ACLs. Fail
-- rather than making that ownership mistake invisible behind successful grants.
SELECT (
    NOT EXISTS (
        SELECT 1
        FROM pg_class AS relation
        INNER JOIN pg_namespace AS namespace ON namespace.oid = relation.relnamespace
        INNER JOIN pg_roles AS role ON role.oid = relation.relowner
        WHERE namespace.nspname = 'public'
          AND role.rolname = :'runtime_role_name'
    )
)::text AS runtime_owns_no_public_objects \gset

\if :runtime_owns_no_public_objects
\else
    \echo 'ERROR: portal_karir_app owns object(s) in public; transfer ownership before retrying.'
    \quit 3
\endif

SELECT current_database() AS target_database_name \gset

SELECT (
    NOT EXISTS (
        SELECT 1
        FROM pg_namespace AS namespace
        INNER JOIN pg_roles AS role ON role.oid = namespace.nspowner
        WHERE namespace.nspname = 'public'
          AND role.rolname = :'runtime_role_name'
    )
    AND NOT EXISTS (
        SELECT 1
        FROM pg_database AS database
        INNER JOIN pg_roles AS role ON role.oid = database.datdba
        WHERE database.datname = :'target_database_name'
          AND role.rolname = :'runtime_role_name'
    )
)::text AS runtime_owns_no_schema_or_database \gset

\if :runtime_owns_no_schema_or_database
\else
    \echo 'ERROR: portal_karir_app owns the target database or public schema; transfer ownership before retrying.'
    \quit 3
\endif

SELECT (
    NOT EXISTS (
        SELECT 1
        FROM pg_auth_members AS membership
        INNER JOIN pg_roles AS member_role ON member_role.oid = membership.member
        WHERE member_role.rolname = :'runtime_role_name'
    )
)::text AS runtime_has_no_role_memberships \gset

\if :runtime_has_no_role_memberships
\else
    \echo 'ERROR: portal_karir_app has role memberships; revoke them before retrying.'
    \quit 3
\endif

-- The application is a client of the database, never a schema author. Revoke
-- PUBLIC create/temp access too, otherwise the runtime login could inherit DDL
-- capability through the ambient PUBLIC role.
REVOKE CREATE, TEMPORARY ON DATABASE :"target_database_name" FROM PUBLIC;
REVOKE ALL PRIVILEGES ON DATABASE :"target_database_name" FROM :"runtime_role_name";
GRANT CONNECT ON DATABASE :"target_database_name" TO :"runtime_role_name";

REVOKE CREATE ON SCHEMA public FROM PUBLIC;
REVOKE ALL PRIVILEGES ON SCHEMA public FROM :"runtime_role_name";
GRANT USAGE ON SCHEMA public TO :"runtime_role_name";

-- Reset the role's direct table/sequence ACLs, then build the precise current
-- baseline. This makes reruns idempotent and removes stale broad grants.
REVOKE ALL PRIVILEGES ON ALL TABLES IN SCHEMA public FROM :"runtime_role_name";
REVOKE ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public FROM :"runtime_role_name";

-- Laravel's migration registry is deployment-owner-only. It is deliberately
-- excluded from normal runtime CRUD and explicitly revoked for clarity.
SELECT format(
    'REVOKE ALL PRIVILEGES ON TABLE %I.%I FROM %I;',
    'public',
    'migrations',
    :'runtime_role_name'
)
WHERE to_regclass('public.migrations') IS NOT NULL
\gexec

-- Ordinary application and operational tables require normal CRUD. The
-- excluded evidence tables are granted their smaller append-only surface below.
SELECT format(
    'GRANT SELECT, INSERT, UPDATE, DELETE ON TABLE %I.%I TO %I;',
    schemaname,
    tablename,
    :'runtime_role_name'
)
FROM pg_catalog.pg_tables
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
ORDER BY tablename
\gexec

-- Identity-backed inserts need USAGE (to advance) and SELECT (Laravel can
-- retrieve generated values). Only the protected tables' sequences are held
-- back for their explicit append-only grant below.
SELECT format(
    'GRANT USAGE, SELECT ON SEQUENCE %I.%I TO %I;',
    namespace.nspname,
    relation.relname,
    :'runtime_role_name'
)
FROM pg_class AS relation
INNER JOIN pg_namespace AS namespace ON namespace.oid = relation.relnamespace
WHERE relation.relkind = 'S'
  AND namespace.nspname = 'public'
  AND relation.relname NOT IN (
      'application_status_histories_id_seq',
      'company_verification_reviews_id_seq',
      'vacancy_moderation_reviews_id_seq',
      'selection_schedule_histories_id_seq',
      'vacancy_versions_id_seq',
      'audit_logs_id_seq'
  )
ORDER BY relation.relname
\gexec

-- Keep the six INV-016 evidence tables append-only at the runtime connection.
SELECT format(
    'GRANT SELECT, INSERT ON TABLE %I.%I TO %I;',
    schemaname,
    tablename,
    :'runtime_role_name'
)
FROM pg_catalog.pg_tables
WHERE schemaname = 'public'
  AND tablename IN (
      'application_status_histories',
      'company_verification_reviews',
      'vacancy_moderation_reviews',
      'selection_schedule_histories',
      'vacancy_versions',
      'audit_logs'
  )
ORDER BY tablename
\gexec

SELECT format(
    'REVOKE UPDATE, DELETE ON TABLE %I.%I FROM %I;',
    schemaname,
    tablename,
    :'runtime_role_name'
)
FROM pg_catalog.pg_tables
WHERE schemaname = 'public'
  AND tablename IN (
      'application_status_histories',
      'company_verification_reviews',
      'vacancy_moderation_reviews',
      'selection_schedule_histories',
      'vacancy_versions',
      'audit_logs'
  )
ORDER BY tablename
\gexec

SELECT format(
    'GRANT USAGE, SELECT ON SEQUENCE %I.%I TO %I;',
    namespace.nspname,
    relation.relname,
    :'runtime_role_name'
)
FROM pg_class AS relation
INNER JOIN pg_namespace AS namespace ON namespace.oid = relation.relnamespace
WHERE relation.relkind = 'S'
  AND namespace.nspname = 'public'
  AND relation.relname IN (
      'application_status_histories_id_seq',
      'company_verification_reviews_id_seq',
      'vacancy_moderation_reviews_id_seq',
      'selection_schedule_histories_id_seq',
      'vacancy_versions_id_seq',
      'audit_logs_id_seq'
  )
ORDER BY relation.relname
\gexec

\echo 'Runtime database role provisioning completed.'
