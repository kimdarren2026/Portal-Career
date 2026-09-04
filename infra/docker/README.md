# infra/docker — Container Definitions

## Files

| File | Purpose |
| --- | --- |
| `Dockerfile` | The one Portal Career release build. Two targets from one commit. |
| `compose.production.yaml` | **Production Dokploy Compose stack** (controlled production / soft launch). |
| `compose.yaml` | **Local development only** — never used in production. |
| `nginx.conf` | Frozen app-tier vhost. Used verbatim by the `app-runtime` web role. |
| `nginx.main.conf` | Top-level `http{}` wrapper that includes `nginx.conf` and runs nginx non-root. |
| `supervisord.conf` | Supervises `php-fpm` + `nginx` for the co-located web role only. |
| `initdb/` | Local-compose test-database bootstrap. |

## Runtime image profiles (`Dockerfile`)

One release, one commit, built in lockstep by `compose.production.yaml`:

| Target | Base | Runs | Roles |
| --- | --- | --- | --- |
| `app-runtime` | `php:8.3-fpm-alpine` | PHP-FPM **and** nginx co-located (`supervisord`); `fastcgi_pass 127.0.0.1:9000`; nginx on `:8080` | `web`, `worker`, `scheduler` |
| `ssr-runtime` | `node:22-alpine` | `node bootstrap/ssr/ssr.js` (Inertia SSR renderer, ADR-017) — no PHP, no nginx, no DB | `ssr` |

`web`, `worker`, `scheduler` share one image digest (`portal-career-app`). `ssr`
is a dedicated small Node image (`portal-career-ssr`) built from the same
`Dockerfile` and commit. `org.opencontainers.image.revision` is stamped on both
from the `GIT_REVISION` build arg.

The co-located web model is the frozen `DEPLOYMENT_ARCHITECTURE.md §2` topology
("PHP-FPM + Nginx" as one instance). `nginx.conf` is unchanged; `nginx.main.conf`
only supplies the `http{}` context and relocates writable paths so nginx runs as
the non-root `www-data` user.

## Production deploy notes

- Secrets: `${VAR}` only, from the Dokploy secret facility. Nothing sensitive is committed.
- `postgres` / `redis`: attached only to the compose-scoped `portal-career` network; **no host ports**.
- Database: two-principal model (`infra/deployment/README.md`). `POSTGRES_*` = cluster/migration owner; `DB_*` = restricted runtime role.
- Migrations: **not** run by any long-running service. One-shot `php artisan migrate --force` with the migration-owner credential, out of band.
- Object storage: external private S3-compatible only. No MinIO. No local candidate-document store.
- Transactional email delivery worker: not implemented (B-5 unresolved). In-app notifications, SMTP config, SMTP test-send, and `email_outbox` persistence work.
- Public routing (hostname + TLS) is configured later via the Dokploy Domains UI; no hostname or Traefik rule is baked in.
