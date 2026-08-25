# docs/architecture — Technical Architecture

**Status: proposed, awaiting approval. No implementation has started.**
**Date: 24 August 2026 · Amended 24 August 2026 (ADR-015, ADR-016, ADR-017) · Backend framework: Laravel (decided)**
**Aligned to logical model revision 1.1-C2.**

Architecture and decision documentation only. No Laravel project, migration, model, controller, or frontend code exists or is created by these documents.

| Document | Covers |
| --- | --- |
| `SYSTEM_ARCHITECTURE.md` | Application topology, database engine, constraint strategy, queue, cache, email, notifications, object storage, scheduler, transaction boundaries, reporting, open questions |
| `LARAVEL_ARCHITECTURE.md` | Module boundaries, layer responsibilities, transaction placement, authentication and authorization shape, frontend integration, API boundary, testing architecture |
| `SECURITY_ARCHITECTURE.md` | Authentication, authorization, web application controls, SSRF, file handling, secrets, audit and privacy, residual risks |
| `DEPLOYMENT_ARCHITECTURE.md` | Environments, production topology, backup and recovery, observability, reverse proxy and TLS, local development |
| `TECHNICAL_DECISIONS.md` | ADR-001 to ADR-017 — decision, alternatives, reason, tradeoffs, consequences |
| `ARCHITECTURE_CORRECTION_REPORT.md` | Record of the approved change request applied on 24 August 2026: runtime SMTP configuration, selector stage assignment, Inertia SSR topology, and the Stitch traceability correction |

## Recommended stack at a glance

| Area | Recommendation |
| --- | --- |
| Application architecture | Laravel modular monolith, domain modules, Actions as the unit of business behaviour |
| Frontend | Inertia.js + Vue 3 + Tailwind; public pages server-rendered via **Inertia SSR** |
| Database | **PostgreSQL 16+** |
| Queue / cache / locks / rate limit | Redis — sessions in PostgreSQL |
| Object storage | S3-compatible, private by default, downloads mediated and audited by the application |
| SMTP configuration | Runtime-managed in `smtp_configurations` with an application-encrypted, write-only credential |
| Authentication | Laravel session guard for web; Sanctum reserved for `/api/v1` |
| Authorization | RBAC + object-level Policies + ownership-scoped queries; Selector scoped by `selection_stage_assignments` |
| API style | REST with action sub-resources, `/api/v1` |
| Deployment | Containerized monolith, one release, **four process roles**: web · queue workers · exactly one scheduler · Inertia SSR renderer |

## Reading order

1. `SYSTEM_ARCHITECTURE.md` — what the system is and why
2. `TECHNICAL_DECISIONS.md` — what was chosen, what was rejected, and at what cost
3. `LARAVEL_ARCHITECTURE.md` — how the application is organized internally
4. `SECURITY_ARCHITECTURE.md` — what protects the data
5. `DEPLOYMENT_ARCHITECTURE.md` — how it runs

## Source-of-truth position

1. Approved BRD v1.1
2. Approved FSD v1.1
3. Frozen Stitch functional baseline
4. **Logical ERD revision 1.1-C1, then this architecture — once approved**
5. Production code

This architecture sits below the requirements and the data model. Where a framework default conflicts with the frozen ERD, **the ERD wins** — see ADR-011. Where a requirement cannot be met without changing a frozen baseline, the conflict is raised rather than resolved silently — ADR-010 was raised that way and resolved by an approved change request in ADR-015.

## Previously blocking — now resolved

| Item | Resolution |
| --- | --- |
| ~~ADR-010 — SMTP configuration storage~~ | **Resolved by ADR-015.** Runtime-managed configuration with an application-encrypted, write-only credential in `smtp_configurations` (INV-035, INV-036) |
| ~~H-1 — Selector stage assignment~~ | **Resolved by ADR-016.** `selection_stage_assignments` gives Laravel Policies a concrete assignment relation to enforce (INV-037) |

**No architectural item now blocks implementation.**

## Still open

The **five remaining BRD/FSD business open questions** are: alumni verification source · minimum company legal documents (D-6) · salary policy · recruiter domain/subdomain · WhatsApp phase. **D-1 is CLOSED** by approved Product Owner decision: the first company creator is active `COMPANY_ADMIN`, at least one active admin must remain, last-admin protection is required, and subsequent roles are explicit with no implicit default. **H-2, H-3, and H-4** also remain open. All are listed in `TECHNICAL_DECISIONS.md`.

## Not yet written

`docs/database/DATABASE_SCHEMA.md` and `docs/api/API_CONTRACT.md` are the next artefacts, and both depend on this architecture being approved first.
