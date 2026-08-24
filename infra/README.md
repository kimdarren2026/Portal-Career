# infra — Infrastructure & Deployment

**Status: reserved and empty. No infrastructure has been defined.**

| Path | Purpose |
| --- | --- |
| `docker/` | Container definitions and compose files for local and deployed environments. |
| `deployment/` | Deployment manifests, pipeline definitions, environment topology. |
| `env/` | Environment variable **templates** only. |

## Secrets policy — non-negotiable

**Never commit real secrets to this repository**, in this directory or anywhere else. That includes:

- SMTP passwords
- API keys
- database passwords
- private tokens, signing keys, and certificates

Only a committed `env/.env.example` containing **placeholder** values is permitted. It will be
created once the required environment variables are actually defined — it does not exist yet
because no application code exists to require any variable.

Real values belong in a secret manager or in untracked local files. `.gitignore` at the repository
root already excludes `.env` and `.env.*` while allowing `.env.example`.
