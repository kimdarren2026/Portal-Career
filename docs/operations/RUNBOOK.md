# Operations Runbook — Portal Karir Kampus

**Status:** production readiness baseline. Covers the frozen MVP core
(`portal-career-mvp-core-v1`). No cloud provider is chosen here — commands are
provider-neutral. Authorities: `docs/architecture/DEPLOYMENT_ARCHITECTURE.md`,
`SECURITY_ARCHITECTURE.md`, `infra/deployment/README.md`, ADR-014/-015/-017.

---

## 1. Runtime topology

One immutable image (`infra/docker/Dockerfile`), four process roles from the
same release:

| Role | Command | Scaling | Notes |
| --- | --- | --- | --- |
| **web** | `php-fpm` behind nginx (`infra/docker/nginx.conf`) | horizontal, stateless | sessions in PostgreSQL |
| **worker** | `php artisan queue:work --queue=mail,notifications,default --max-time=3600 --tries=1 --backoff=0` | horizontal per queue | restart on every deploy |
| **scheduler** | `php artisan schedule:work` (or `schedule:run` every minute) | **exactly one instance** | `withoutOverlapping` Redis lock is defence in depth |
| **ssr** | `node bootstrap/ssr/ssr.js` (Node 22) | horizontal, small | **restart on every deploy**; no DB, no API |

Queue worker retry policy is driven by `smtp_configurations.max_attempts` /
`retry_backoff_seconds` once the email-outbox delivery worker ships (see §9 —
it is a documented product blocker, B-5). Today no job class is dispatched, so
the worker is idle; keep it deployed so it is ready.

---

## 2. Environment variables

Names and placeholders: `apps/api/.env.example`. Real values come from the
platform secret manager, never the repository.

| Variable | Class | Production value |
| --- | --- | --- |
| `APP_ENV` | required | `production` (or `staging`) |
| `APP_DEBUG` | required | `false` — an uncaught error renders the generic page, no stack trace / SQL / path / secret |
| `APP_KEY` | required secret | `base64:…` from the secret manager, generated once, **stable** — see §8 |
| `APP_URL` | required | `https://<host>` |
| `TRUSTED_PROXIES` | required behind a proxy | proxy IP/CIDR list, or `*` if only reachable via the proxy |
| `DB_USERNAME` / `DB_PASSWORD` | required secret | the **restricted** `portal_karir_app` role only (never the migration owner) |
| `DB_SSLMODE` | required | `require` or stricter in production |
| `SESSION_SECURE_COOKIE` | required | `true` |
| `SESSION_ENCRYPT` | required | `true` |
| `REDIS_HOST` / `REDIS_PASSWORD` | required secret | managed Redis |
| `REDIS_CACHE_DB` / `REDIS_QUEUE_DB` / `REDIS_LOCK_DB` | required | `0` / `1` / `2` — cache and queue MUST be separate DBs |
| `CACHE_STORE` | required | `redis` |
| `QUEUE_CONNECTION` | required | `redis` |
| `FILESYSTEM_DISK` | required | `s3` — inheriting `local` puts candidate documents on instance-local disk, outside backup |
| `AWS_*` | required secret | S3-compatible endpoint, bucket (**private**), region, key, secret; `AWS_USE_PATH_STYLE_ENDPOINT` per provider |
| `MAIL_MAILER` | required | deployment fallback only; runtime SMTP is Super Admin–managed (`smtp_configurations`, ADR-015) |
| `LOG_CHANNEL` | required | `stderr` |
| `LOG_STDERR_FORMATTER` | required | `Monolog\Formatter\JsonFormatter` |
| `LOG_LEVEL` | required | `info` |
| `INERTIA_SSR_ENABLED` / `INERTIA_SSR_URL` | required | `true` / the SSR renderer URL |

Deployment-only (never in the Laravel runtime env): `PORTAL_KARIR_RUNTIME_DB_PASSWORD`,
the migration-owner credential — see `infra/deployment/README.md`.

---

## 3. Build

Reproducible, from committed lockfiles — never `composer update` / `npm install` at deploy:

```sh
# in the image build (infra/docker/Dockerfile does this in stages)
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --classmap-authoritative
npm ci
npm run build           # client bundle  -> apps/api/public/build
npm run build:ssr       # SSR bundle     -> apps/api/bootstrap/ssr
```

CI is defined in `infra/ci/ci.yml` — move it to `.github/workflows/` to
activate (`infra/ci/README.md`). It runs `composer validate --strict`, the
full PHPUnit Feature suite against PostgreSQL 16 + Redis 7, `npm run
type-check`, `npm run build`, `npm run build:ssr`, `git diff --check`, and
builds the production image.

---

## 4. Database provisioning & migration

PostgreSQL **16+**. Two principals (`infra/deployment/README.md`):

1. **Bootstrap / rotate** the restricted runtime role:
   ```sh
   export PORTAL_KARIR_DATABASE_ADMIN_URL='postgresql://<db-admin>@<host>:5432/portal_karir'
   export PORTAL_KARIR_RUNTIME_DB_PASSWORD='<from-secret-manager>'
   psql "$PORTAL_KARIR_DATABASE_ADMIN_URL" -X -v ON_ERROR_STOP=1 -f infra/deployment/provision-runtime-db-role.sql
   ```
2. **Migrate** — once per release, before new instances take traffic, with the
   **migration-owner** credential injected only into this job:
   ```sh
   DB_USERNAME=<migration-owner> DB_PASSWORD=<owner-secret> php artisan migrate --force
   ```
   Rolling deploys require **backward-compatible** migrations (expand → deploy → contract).
3. **Re-run the provisioning script** — refreshes grants for any new ordinary
   tables/sequences; the six append-only evidence tables keep `SELECT, INSERT`
   only (INV-016). New tables are inaccessible to the runtime role until this
   reviewed refresh runs (fail closed).
4. **Start / replace** web, worker, scheduler, ssr with `DB_USERNAME=portal_karir_app` only.

Migration assumptions: partial unique indexes, CHECK constraints, `timestamptz`
throughout, transaction isolation READ COMMITTED (default). A statement timeout
at the connection/pooler is recommended but not assumed by the app.

---

## 5. Startup order

1. PostgreSQL + Redis reachable.
2. Migrations applied (§4).
3. Web instances start → they pass `GET /health/ready` (PostgreSQL, Redis,
   object storage) before the load balancer adds them.
4. Scheduler (one) and workers start.
5. SSR renderer starts (and restarts on every deploy — it holds the bundle in memory).

---

## 6. Health checks

| Endpoint | Meaning | Consumer |
| --- | --- | --- |
| `GET /health/live` | process responds, no dependency touched | restart supervision |
| `GET /health/ready` | `checks.database` / `checks.redis` / `checks.storage` each `ok`; body `status: ready` (200) or `degraded` (503) | load balancer |
| `GET /up` | Laravel default liveness | optional |

Health responses disclose nothing beyond pass/fail per dependency. A transient
S3 or Redis blip flips `/health/ready` to 503 and removes the instance from
rotation; it rejoins automatically when the dependency recovers. It does **not**
restart the process (that is `/health/live`).

---

## 7. Logging & observability

Structured JSON to stdout/stderr (`LOG_CHANNEL=stderr` +
`LOG_STDERR_FORMATTER=Monolog\Formatter\JsonFormatter`), collected by the
platform. Every line carries the correlation id (`X-Request-Id`, generated if
absent, stored in `audit_logs.correlation_id`).

**Never logged:** passwords, tokens of any kind, SMTP credentials, `APP_KEY`,
DB/Redis/S3 secrets, session ids, file contents.

Alert on (DEPLOYMENT_ARCHITECTURE.md §5): `email_outbox` DEAD_LETTER (any new
one), `email_outbox` PENDING age, `failed_jobs` growth, queue depth/wait,
scheduler last-run heartbeat, SSR fallback rate, `smtp_configurations.last_test_result = FAILURE`,
5xx rate/latency, auth-failure rate, Redis memory/evictions, backup success,
TLS expiry.

`TELESCOPE_ENABLED` and any request-payload recorder must be **off** in
production. Horizon (if used for queue visibility) is Super Admin–restricted in
non-local environments.

---

## 8. Secrets & key rotation

`APP_KEY` encrypts session data and — via Laravel `Crypt` — the SMTP credential
in `smtp_configurations.encrypted_password` (INV-035, ADR-015). It is
deployment-supplied, never committed.

**Rotating `APP_KEY`** invalidates every value it encrypted:
- all active sessions are dropped (users re-authenticate) — acceptable;
- `smtp_configurations.encrypted_password` becomes undecryptable — a Super
  Admin must re-enter the SMTP credential via *Konfigurasi SMTP* after rotation.

Do **not** rotate `APP_KEY` as a routine action. If rotation is required, plan
the SMTP re-entry step and communicate the forced re-login.

---

## 9. Backup & restore

| Asset | Approach |
| --- | --- |
| **PostgreSQL** | continuous WAL archiving for PITR + periodic full base backups; encrypted in transit and at rest; stored offsite / off-account |
| **Object storage** | provider durability + **object versioning** (recover an overwrite/delete); cross-site replication where available. **No lifecycle deletion rule on document buckets** — recruitment evidence has indefinite retention (FR-AUD-002) |
| **Redis** | **not backed up** — cache/locks/in-flight queue state are reconstructible; `email_outbox` `PENDING`/`FAILED_RETRYABLE` rows survive in PostgreSQL and are re-driven |
| **`APP_KEY` / SMTP key** | in the secret manager, **backed up separately from the database**. A DB restore without the matching key leaves the SMTP credential undecryptable — Super Admin re-enters it (§8). Write this into the recovery checklist |
| **Config** | in version control; env values reproducible from `.env.example` + the secret store |

RPO/RTO: **to be set with the business** (BLOCKED BY DEPLOYMENT DECISION). WAL
archiving makes a small RPO achievable — state the target explicitly.

**Restore drill** (mandatory, periodic, into an isolated environment):
1. Restore the latest base backup + WAL to a point in time.
2. Verify row counts, referential integrity, and that the six append-only
   history/audit tables are intact and still `UPDATE`/`DELETE`-revoked for the
   runtime role.
3. Verify `application_documents` rows resolve against object-storage versions
   (a DB rewind without an object rewind is recoverable only because referenced
   documents are never hard-deleted — INV-032).
4. Re-apply any authorized retention/anonymization runs performed since the
   backup (they are forward operations, logged for exactly this).
5. Re-enter the SMTP credential if the key was not restored alongside.

---

## 10. Queue & outbox recovery

- **Failed jobs:** inspect `php artisan queue:failed`; retry with
  `php artisan queue:retry <id|all>` after the cause is fixed; investigate
  growth (retries exhausted).
- **Stuck queue:** workers restart safely (`--max-time` bounded); a lost Redis
  queue DB is not a data-loss event — the outbox is the authority.
- **`email_outbox`:** `PENDING` / `FAILED_RETRYABLE` rows are the source of
  truth for undelivered transactional mail. Once the delivery worker ships
  (B-5), it re-drives them; until then these rows accumulate and **no
  transactional email is sent** — see §11.

---

## 11. SMTP

- Runtime SMTP is managed by Super Admin at *Konfigurasi SMTP*
  (`GET/PUT /admin/smtp-configuration`, `POST /admin/smtp-configuration/test`).
  Credential is write-only, application-encrypted (INV-035), single active row
  (INV-036). Zero active rows → delivery falls back to `MAIL_*` env config.
- **Test send:** *Konfigurasi SMTP → Kirim email uji*. A failure returns a
  sanitized message only; check host/port/encryption/credential. `last_test_result`
  is stored on the config row; alert on `FAILURE`.
- **Do not** put the SMTP password in `MAIL_PASSWORD` in production unless
  runtime config is deliberately unused; the encrypted runtime row is preferred.

---

## 12. Storage verification

- Assert `FILESYSTEM_DISK=s3` and a **private** bucket (no public ACL) before
  candidate-document upload is considered production-ready.
- Candidate documents are only ever served through
  `GET /candidate/documents/{document}/download` — Policy-checked, audited,
  streamed by the app. The proxy denies `/storage/` directly
  (`infra/docker/nginx.conf`). No signed/durable public URL is issued.

---

## 13. Deploy & rollback

**Deploy:** build image → migrate (backward-compatible) → roll web instances
(each must pass `/health/ready`) → restart workers → restart scheduler →
restart SSR renderer.

**Rollback (safe principles only):**
- Roll the **application image** back to the previous tag. Because migrations
  are expand → deploy → contract, the previous image runs against the current
  schema.
- **Never** run a destructive database rollback, `git reset --hard` on a
  server, or a force push. A migration that cannot be rolled forward safely is
  handled with a new corrective migration, not a down-migration in production.
- Queue jobs and `email_outbox` rows written by the newer release remain valid;
  the older code path either handles them or leaves them for the next forward
  deploy.
- The encrypted SMTP configuration is unaffected by a code rollback.

---

## 14. Incident basics

1. Check `/health/ready` on each web instance and the load-balancer target
   health.
2. Correlate by `correlation_id` across app logs, job logs, and `audit_logs`.
3. Dependency down: PostgreSQL → all instances 503 on `/health/ready` (correct,
   fail closed); Redis → cache/rate-limit/lock degraded, sessions unaffected
   (PostgreSQL); S3 → uploads/downloads fail, the rest of the portal works.
4. SSR renderer down: **no user-visible error, no HTTP failure** — public pages
   silently lose server-rendered HTML and search indexing degrades. Monitor
   process liveness and fallback rate; restart the renderer.
5. Scheduler silent: vacancies stop publishing/expiring with **no error** —
   the heartbeat alert is the only signal. Restart the single scheduler.
