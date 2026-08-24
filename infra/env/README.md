# infra/env — Environment Configuration Templates

Application environment variable names and safe placeholders live in
`apps/api/.env.example`. Deployment-only database role bootstrap variables are
documented in `../deployment/README.md`; they must come from the platform
secret manager and must not be copied into the Laravel runtime environment.

## Rules

- `.env.example` is committed. It contains placeholders only, never real values.
- Real `.env` files are never committed; they are excluded by the root `.gitignore`.
- Never store a real SMTP password, API key, database password, or private token here.
