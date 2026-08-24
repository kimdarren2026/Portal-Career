# apps/api — Laravel Application

**The single deployable** (SYSTEM_ARCHITECTURE.md §1). Laravel 13 modular monolith serving all five channels through Inertia, with a reserved `/api/v1` surface.

**Status: bootstrapped. No business domain is implemented.**

## What exists

| Area | State |
| --- | --- |
| Laravel 13 skeleton, PostgreSQL, Redis, database sessions | Configured |
| Inertia + Vue 3 + TypeScript + Tailwind + Vite | Integrated and building |
| Inertia SSR entry and bundle | Builds; enable with `INERTIA_SSR_ENABLED` |
| `app/Domains/` — 13 modules | Directories only, per LARAVEL_ARCHITECTURE.md §2 |
| Framework migrations — `sessions`, `cache`, `failed_jobs` | Created |
| **51 business tables** | **Not created.** MIGRATION_PLAN.md Phases 1–5 |
| **Authentication** | **Not implemented.** A later domain phase |
| `/api/v1` routes | Intentionally empty until Sanctum is activated |

## Running processes

Four runtime roles ship in one release (ADR-009, ADR-017):

| Role | Command |
| --- | --- |
| Web / API | `php artisan serve` (local) |
| Queue worker | `php artisan queue:work` |
| Scheduler | `php artisan schedule:work` — **exactly one instance** |
| Inertia SSR renderer | `php artisan inertia:start-ssr` — restart on every deploy |

## Commands

```
composer install
npm install            # run from the repository root (npm workspaces)
npm run build          # client bundle
npm run build:ssr      # client + SSR bundle
npm run type-check     # vue-tsc over apps/web
php artisan test
```

## Rules that must not be broken

- **Never create Laravel's default `users` or `password_reset_tokens` migration.** Both are business tables owned by logical model 1.1-C3; Laravel's `password_reset_tokens` shape would collide and satisfy neither INV-021 nor the model (ADR-011).
- **Never create `jobs` or `job_batches`.** The queue is Redis (ADR-006).
- **Never use SQLite**, including in tests (ADR-003).
- **No repository layer** (ADR-012). Business behaviour lives in Actions, which own the transaction.
- **Both HTTP surfaces call the same Actions and Policies.** No business rule is implemented twice.
