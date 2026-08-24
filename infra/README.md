# infra — Infrastructure & Deployment

**Status:** the deployment layer contains the PostgreSQL runtime-role bootstrap
artifact. Provider-specific deployment manifests remain intentionally absent.

| Path | Purpose |
| --- | --- |
| `docker/` | Container definitions and compose files for local and deployed environments. |
| `deployment/` | Deployment manifests, pipeline definitions, environment topology, and runtime database-role provisioning. |
| `env/` | Pointers to environment variable **templates** only. |

## Secrets policy — non-negotiable

**Never commit real secrets to this repository**, in this directory or anywhere else. That includes:

- SMTP passwords
- API keys
- database passwords
- private tokens, signing keys, and certificates

Only committed environment examples containing **placeholder** values are
permitted. The Laravel example is `apps/api/.env.example`; deployment-only
runtime-role bootstrap variables are documented under `deployment/` and are
always sourced from a secret manager or an untracked local environment.

Real values belong in a secret manager or in untracked local files. `.gitignore` at the repository
root already excludes `.env` and `.env.*` while allowing `.env.example`.
