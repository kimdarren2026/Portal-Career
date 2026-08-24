# apps/worker — Background Process Documentation

**There is no separate worker codebase** (ADR-009). Queue workers, the scheduler, and the Inertia SSR renderer all run **the same application and release** as `apps/api`, with different entrypoints.

A separate worker codebase would need its own copy of the models, invariants, and validation — two implementations of INV-026 and INV-032 that would inevitably diverge.

## Process roles

| # | Role | Entrypoint | Scaling |
| --- | --- | --- | --- |
| 1 | Web / API | PHP-FPM behind the reverse proxy | Horizontal, stateless |
| 2 | Queue worker | `php artisan queue:work --queue=mail,notifications,scheduled,maintenance` | Horizontal, per queue |
| 3 | Scheduler | `php artisan schedule:work` | **Exactly one instance** — two would double-publish vacancies |
| 4 | Inertia SSR renderer | `php artisan inertia:start-ssr` | Horizontal, small |

## Operational requirements

- **The scheduler dispatches jobs; it does not do work.** A scheduler performing heavy work directly cannot be scaled and loses everything if the process dies mid-run.
- **The SSR renderer must restart on every deploy** — it holds the compiled SSR bundle in memory, so a stale renderer serves the previous release's markup.
- **SSR failure is degradation, not outage.** Inertia falls back to client rendering; public pages lose indexable HTML with no user-visible error, so process liveness *and* fallback rate must both be monitored (DEPLOYMENT_ARCHITECTURE.md §5).
- **Queue loss is survivable; outbox loss is not.** `email_outbox` in PostgreSQL is authoritative and the scheduler re-drives it, which is why Redis is not backed up.

**No business job is implemented yet.**
