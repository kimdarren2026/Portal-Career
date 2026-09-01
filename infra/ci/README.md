# infra/ci — Continuous Integration

`ci.yml` is the CI pipeline definition for the frozen MVP core.

## Activation

Move it to `.github/workflows/ci.yml`:

```sh
mkdir -p .github/workflows
git mv infra/ci/ci.yml .github/workflows/ci.yml
git commit -m "ci: activate CI workflow"
```

It lives here rather than under `.github/workflows/` only because the token
used to publish this repository lacks the GitHub `workflow` scope. The file is
otherwise ready to run unchanged: `context: .` and `file: infra/docker/Dockerfile`
resolve from the repository root regardless of the workflow file's location.

## What it runs

| Job | Steps |
| --- | --- |
| `test` | PostgreSQL 16 + Redis 7 service containers · provision the restricted `portal_karir_app` role · `composer validate --strict` · `composer install` (locked) · `npm ci` · `npm run type-check` · `npm run build` · `npm run build:ssr` · `php artisan test --testsuite=Feature` · `git diff --check` |
| `image` | build `infra/docker/Dockerfile` · smoke-test that the runtime image boots `php` and resolves `artisan` |

No deployment. No real credentials — every value is a throwaway CI-only value.
