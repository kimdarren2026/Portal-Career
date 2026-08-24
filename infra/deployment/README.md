# infra/deployment — Deployment Configuration

## Runtime database role provisioning

`provision-runtime-db-role.sql` establishes the non-owner PostgreSQL login used
by Laravel web processes, queue workers, and the scheduler:

```text
portal_karir_app
```

It is intentionally a deployment/bootstrap artifact, not a Laravel migration.
Cluster principals and their credentials outlive an individual schema release;
Laravel migrations remain the responsibility of the separate schema/migration
owner.

### Inputs and safety

Run the script with a database-administrator connection. Supply the runtime
role password from the deployment secret manager as an environment variable;
the script never contains, prints, or records a password.

```sh
export PORTAL_KARIR_DATABASE_ADMIN_URL='postgresql://<database-admin>@<host>:5432/portal_karir'
export PORTAL_KARIR_RUNTIME_DB_PASSWORD='<runtime-role-secret-from-secret-manager>'

psql "$PORTAL_KARIR_DATABASE_ADMIN_URL" -X -v ON_ERROR_STOP=1 \
  -f infra/deployment/provision-runtime-db-role.sql
```

`PORTAL_KARIR_RUNTIME_DB_PASSWORD` is required the first time the login is
created. On a later idempotent run it is optional: when omitted, the existing
password is deliberately preserved; when supplied, it is rotated. An empty
value is rejected. Never use `echo`, shell history, a committed file, or a
Laravel `.env.example` to pass a production value.

The script fails before granting anything if the runtime role is found to own
an object in `public`. Correct ownership with a database-administrator change
before retrying; granting around ownership would defeat the append-only
control.

### Release order

1. Bootstrap or rotate `portal_karir_app` with the provisioning script.
2. Run `php artisan migrate --force` once with the **separate migration-owner
   credential** injected only into the migration job.
3. Run the provisioning script again after migrations complete.
4. Start or replace the Laravel web, worker, and scheduler processes with only
   `DB_USERNAME=portal_karir_app` and that role's externally injected password.

Step 3 explicitly refreshes grants for newly-created ordinary tables and
sequences. This project deliberately does **not** use `ALTER DEFAULT
PRIVILEGES`: a broad default `UPDATE`/`DELETE` grant could silently weaken a
future append-only evidence table. A new table is therefore inaccessible to
the runtime role until this reviewed refresh runs (fail closed).

The artifact grants normal CRUD only to existing ordinary application and
operational tables. `sessions`, `cache`, `cache_locks`, `failed_jobs`,
`idempotency_keys`, and `export_jobs` are ordinary runtime tables. The six
append-only history/audit tables receive only `SELECT, INSERT`; their
release-coupled table-specific grants remain defined by the frozen Phase-7
migration. The Laravel `migrations` registry receives no runtime privilege.

No deployment secret may be committed in this directory.
