# Technical Decisions — Portal Karir Kampus

**Status:** Proposed — awaiting approval
**Date:** 24 August 2026 · **Amended 24 August 2026** by the approved architecture change request (ADR-015, ADR-016, ADR-017)
**Baselines:** BRD v1.1 · FSD v1.1 · Stitch functional baseline · Logical ERD revision **1.1-C2**
**Given:** Laravel is the decided backend framework (ADR-002 below records it for completeness).

Each decision states **Decision · Alternatives · Reason · Tradeoffs · Consequences**. Decisions marked **OPEN** are deliberately unresolved.

`docs/decisions/` currently plans ADR-001 to ADR-005 as separate files. This document is the authoritative record for all of them; splitting it into individual ADR files is an optional later editorial step.

---

## ADR-001 — Application Architecture: Modular Monolith with Inertia

**Decision.** One Laravel modular monolith serving all five channels via Inertia.js, plus a separately versioned `/api/v1` REST surface reserved for future clients. Business modules organized by domain under `app/Domains/`, with Actions as the unit of business behaviour.

**Alternatives.** (a) Separated SPA + Laravel API-only. (b) Blade + Livewire server-rendered monolith. (c) Microservices. (d) Layered monolith organized by technical file type.

**Reason.** The five channels are role-scoped views of one recruitment domain sharing one set of cross-entity invariants (INV-019, INV-022, INV-023, INV-026, INV-031). Distributing that consistency boundary across deployables or services would require enforcing transactional invariants over a network. Inertia keeps routing, validation, and authorization in Laravel — one contract, not two — while giving the authenticated portals a real component model. Organizing by domain rather than file type keeps each invariant's enforcement in one readable place.

**Tradeoffs.** Inertia couples frontend and backend deployment: a frontend change requires a backend deploy. The team must know both Laravel and the chosen JS framework. Extracting a service later is real work — accepted, because that work is not free in any architecture and is not warranted now.

**Consequences.** `apps/api` holds the single deployable; `apps/web` holds frontend source compiled by Vite; `apps/worker` holds process documentation, not a codebase. `/api/v1` is designed now and thin at MVP. Both HTTP surfaces call the same Actions, so no rule is implemented twice.

---

## ADR-002 — Backend Framework: Laravel

**Decision.** Laravel. *(Recorded for completeness — decided before this architecture.)*

**Alternatives.** Symfony, Node/NestJS, Django, Spring.

**Reason.** Given as a project decision. It is a good fit: first-class queues, scheduler, filesystem abstraction, Policy-based authorization, and transactional primitives map directly onto what the frozen model needs.

**Tradeoffs.** Some Laravel defaults conflict with the frozen ERD and must be deliberately overridden — most importantly the stock email-verification flow (ADR-011).

**Consequences.** Framework conventions are followed except where the frozen ERD or FSD requires otherwise; every such divergence is recorded.

---

## ADR-003 — Database: PostgreSQL 16+

**Decision.** PostgreSQL 16 or later.

**Alternatives.** MySQL 8, MariaDB, SQL Server.

**Reason.** The frozen model contains three **conditional uniqueness** rules — at most one active role assignment (INV-025), at most one active company membership (INV-017), at most one ACCEPTED offer per application (INV-031) — plus three XOR rules (INV-018, INV-022, INV-023) and six `Structured Data` fields. PostgreSQL expresses the conditional rules as partial unique indexes: declarative, one line each, readable next to the invariant they implement. MySQL has no partial indexes and would need three generated-column workarounds that hide business rules inside column definitions. PostgreSQL additionally offers materialized views (MySQL has none) for FR-REP reporting, richer `jsonb` indexing, and transactional DDL so a failed migration cannot leave a half-applied schema.

**Tradeoffs.** MySQL is more familiar to many PHP teams and marginally simpler on shared hosting. If campus infrastructure has PostgreSQL-free operational experience, that is a genuine cost.

**Consequences.** Local, test, staging, and production all run PostgreSQL — **SQLite must not be used for tests**, since it shares neither partial indexes nor CHECK constraints and would silently skip the guarantees that matter. If MySQL is later mandated by infrastructure, that is an accepted risk requiring the three workarounds to be specified explicitly, not a silent substitution.

---

## ADR-004 — Frontend: Inertia.js + Vue 3 + Tailwind CSS

**Decision.** Vue 3 single-file components over Inertia.js, styled with Tailwind CSS compiled from the Stitch design tokens, built by Vite. Public pages server-rendered; authenticated portals client-rendered.

**Alternatives.** (a) Inertia + React. (b) Blade + Livewire. (c) Standalone React/Next or Vue/Nuxt SPA. (d) Blade + Alpine.

**Reason.** The Stitch baseline is plain HTML with Tailwind utility classes and carries no framework bias, so its tokens and markup port to any of these. Vue has the strongest Laravel-ecosystem alignment for Inertia, and single-file components suit a team whose primary strength is Laravel. Server rendering for public vacancy pages preserves search indexing without adding a Node SSR runtime for the whole application.

**Tradeoffs.** React has a larger hiring pool. **This rejection is weak and the decision is cheap to reverse** — if the team's React experience is stronger, choose React and nothing else in this architecture changes.

**Consequences.** `apps/web` holds page components and the Tailwind theme derived from `design/stitch/design-system/DESIGN.md`. **Production compiles Tailwind locally and self-hosts fonts** rather than using the prototypes' CDN, so CSP needs no CDN script allowance. `packages/ui` stays empty until two portals demonstrably share a component.

> **Amended by ADR-017.** The server-rendering half of this decision is now realized explicitly as **Inertia SSR**, which introduces a fourth runtime process. The frontend framework choice is unchanged.

---

## ADR-005 — Authentication: Laravel Session Guard, with Sanctum Reserved for the API

**Decision.** Session-cookie authentication for the web surface; Sanctum bearer tokens for `/api/v1`, reserved for future clients. One-time hashed tokens for email verification and password reset, implemented against the frozen ERD entities.

**Alternatives.** (a) Token-only authentication for all surfaces. (b) Laravel Passport / full OAuth2. (c) JWT in browser storage. (d) Framework scaffolds (Fortify/Breeze/Jetstream) adopted verbatim.

**Reason.** An `httpOnly` session cookie is not readable by injected script; a token in `localStorage` is exfiltrated by any successful XSS. The web application has no cross-origin requirement under the recommended same-origin topology, so tokens buy nothing there. Sanctum covers future first-party clients without the operational weight of a full OAuth2 server, which no requirement calls for. Scaffolds are useful references but generate flows that do not match the frozen token entities.

**Tradeoffs.** Session state must be stored somewhere (PostgreSQL — see ADR-006). Two auth mechanisms mean two sets of tests. A cross-domain recruiter portal (open question 4) could force a rethink for that surface only.

**Consequences.** CSRF protection applies to all session-authenticated mutating requests. Account suspension terminates sessions and revokes tokens immediately, re-checked per request. **No temporary-password onboarding exists for any role.**

---

## ADR-006 — Queue, Cache, Locks, and Sessions: Redis, with Sessions in PostgreSQL

**Decision.** One Redis instance with separate logical databases for cache, queue, locks, and rate limiting. **Sessions in PostgreSQL, not Redis.**

**Alternatives.** (a) Database queue, no Redis at all. (b) Amazon SQS. (c) Beanstalkd. (d) Sessions in Redis. (e) Memcached for cache.

**Reason.** FSD independently requires rate limiting (§10.1) and scheduler locking (§10.3), so a shared store is needed regardless; adding queue and cache to it costs nothing extra. A database queue would add polling and lock contention to the same PostgreSQL instance serving interactive traffic. SQS caps delayed delivery at 15 minutes, insufficient for scheduled vacancy publication. Sessions live in PostgreSQL because a Redis eviction or flush would log every user out simultaneously; session volume is low and PostgreSQL handles it comfortably.

**Tradeoffs.** Redis is one more component to operate, monitor, and secure. Sessions in PostgreSQL add write volume to the primary — small, and revisitable if measured.

**Consequences.** Redis memory and evictions must be monitored; **eviction on the queue database would drop jobs**, which is why queue and cache are separated. Queue loss is survivable because `email_outbox` in PostgreSQL is the authority and the scheduler re-drives it — Redis is therefore not backed up. **No authorization-sensitive record is cached across requests**: a stale `company_members` or `user_roles` cache is a privilege-escalation bug, and no invalidation strategy justifies that risk.

---

## ADR-007 — Object Storage: S3-Compatible API, Private by Default

**Decision.** Laravel's filesystem abstraction over an S3-compatible API. Local disk for development; S3-compatible storage for staging and production. **The API is specified; the vendor is not.** All buckets private; downloads served through an authorized application route.

**Alternatives.** (a) Local/NFS filesystem in production. (b) Database BLOB storage. (c) A specific cloud vendor named now. (d) Direct pre-signed URLs handed to the browser.

**Reason.** S3-compatibility keeps AWS S3 and self-hosted MinIO both viable, which matters for a campus that may require on-premise data residency. Local filesystem storage breaks horizontal scaling and complicates backup. Database BLOBs bloat backups and cripple restore times. Direct pre-signed URLs are rejected as the primary mechanism because they bypass the application on the actual fetch, making FR-AUD-001's document-access auditing impossible and preventing re-authorization at fetch time.

**Tradeoffs.** Streaming through the application uses application bandwidth and worker time that a direct URL would not. Accepted: auditability of document access is a stated requirement, and document volume is modest.

**Consequences.** Every download is Policy-checked, audited, then streamed or granted a seconds-lived actor-bound signed URL. Uploads are content-inspected for MIME, size-limited, stored under generated keys, and quarantined for asynchronous scanning where a scanner exists. `application_documents` snapshots must reference an immutable or versioned object (INV-032). **Object-storage lifecycle deletion rules must never be applied to document buckets.**

---

## ADR-008 — API Style: REST with Action Sub-Resources, URI-Versioned

**Decision.** Resource-oriented REST with named action sub-resources for state transitions, versioned as `/api/v1`, JSON, with a single error envelope carrying FSD §9.2 codes and a correlation ID.

**Alternatives.** (a) GraphQL. (b) RPC-style endpoints. (c) Pure REST with `PATCH` for state changes. (d) Header or media-type versioning.

**Reason.** This is the shape FSD §7 already uses. For a workflow domain, a named action endpoint (`POST /applications/{id}/withdraw`) maps to one Action, one Policy check, and one validated transition; a `PATCH` carrying a status field invites clients to drive invalid transitions and scatters state-machine logic. GraphQL's flexible querying is a poor fit where every read must be ownership-scoped — it would multiply the authorization surface. URI versioning is explicit, cacheable, and trivially routable.

**Tradeoffs.** Action sub-resources are less "pure" REST. URI versioning duplicates route definitions across versions when v2 arrives.

**Consequences.** `Idempotency-Key` required on submit-class endpoints (FSD §7.6). Pagination, filtering, and sorting use allow-listed parameters only. Endpoint enumeration is deferred to `docs/api/API_CONTRACT.md`; the error-code vocabulary starts from FSD §9.2.

---

## ADR-009 — `apps/worker` Runs the Same Application, Not a Separate Codebase

**Decision.** Queue workers and the scheduler run the same Laravel application under different entrypoints. `apps/worker` holds worker operational documentation and process definitions only.

**Alternatives.** (a) A separate worker application. (b) A separate service consuming a message bus.

**Reason.** A separate worker codebase would need its own copy of the models, invariants, and validation — two implementations of INV-026 and INV-032 that would inevitably diverge. Laravel's queue design assumes workers share the application's code.

**Tradeoffs.** The reserved directory name now implies something it does not contain, which is why this is recorded explicitly. Workers and web instances scale on the same image.

**Consequences.** `apps/worker/README.md` should be updated after approval to describe process roles rather than a future codebase. Deployment ships one image with three process roles: web, worker, scheduler.

---

## ADR-010 — SMTP Configuration Storage — **SUPERSEDED BY ADR-015**

> **Status: superseded 24 August 2026.** The business confirmed that Super Admin must be able to manage SMTP configuration including the credential, which rules out option 1. The approved direction is runtime-managed configuration with an application-encrypted secret — **option 2 as originally recommended, narrowed to a single entity.** See ADR-015. The original text is retained below unchanged as decision history.

**Decision.** **None. Deliberately unresolved and raised for review.**

**The conflict.** FR-NOTIF-005 requires Super Admin to manage SMTP host, port, encryption, username, **encrypted secret**, from, reply-to, timeout, retry policy, and test email. The frozen ERD contains **no configuration or settings entity**. The architecture brief requires SMTP secrets to live in environment/secret configuration and never in business tables. All three cannot hold at once.

**Options.**

| # | Option | Satisfies FR-NOTIF-005 | Satisfies the secret rule | Requires |
| --- | --- | --- | --- | --- |
| 1 | Environment-only configuration | No — needs a deploy to change | Yes | FSD change request |
| 2 | Split: non-secret parameters in an admin-managed settings store; password only in the secret manager, referenced by name | Mostly — password rotation stays operational | Yes | ERD change request (new settings entity) |
| 3 | Full settings store with the secret encrypted at rest | Yes | No | Accepting a credential in the database |

**Recommendation for evaluation:** option 2. It preserves admin control over everything operationally useful while keeping the credential out of business tables.

**Consequences of not deciding.** Email is a hard dependency of nearly every flow (FR-NOTIF-002), so a default must be chosen before the notification module is implemented. **Option 1 is the safe interim default** — it is secure and requires no model change — but shipping it silently would leave FR-NOTIF-005 unmet without acknowledgement.

*(End of superseded text. Resolution in ADR-015.)*

---

## ADR-011 — Email Verification and Reset Tokens Follow the ERD, Not Laravel Defaults

**Decision.** Implement one-time token issuance against the frozen `email_verification_tokens` and `password_reset_tokens` entities: high-entropy token, store only the hash, deliver the raw value once by email, verify by hash, set `used_at`, revoke siblings on success (INV-021).

**Alternatives.** (a) Laravel's stock signed-URL email verification, which stores no token. (b) Laravel's default `password_reset_tokens` scaffold.

**Reason.** The frozen ERD requires both token tables with `token_hash`, `expires_at`, `used_at`, and `revoked_at`. Laravel's signed-URL verification stores nothing, and its default reset scaffold lacks `user_id`, `used_at`, and `revoked_at`. The ERD is a higher source of truth than a framework convention.

**Tradeoffs.** Custom code where a framework default exists — more to write and test, and it must be maintained across Laravel upgrades.

**Consequences.** Recorded prominently because this is exactly the divergence a later developer "corrects" back to the framework default, silently breaking the frozen model and losing single-use and revocation semantics. Covered by explicit tests.

---

## ADR-012 — No Repository Layer

**Decision.** Eloquent models are used directly by Actions and Query classes. No repository abstraction.

**Alternatives.** Repository interfaces per aggregate; a generic repository base.

**Reason.** Eloquent is already a data-access abstraction. A repository over it forwards calls, doubles the maintained surface, forfeits eager loading and scopes at the boundary, and pays for a database-swap capability nobody plans to exercise. The genuine benefit — keeping queries out of controllers — is delivered by Actions and Query classes.

**Tradeoffs.** Unit-testing an Action requires a database rather than a mocked repository. Accepted: feature tests against real PostgreSQL are more valuable here precisely because several invariants are enforced by database constraints a mock would not reproduce.

**Consequences.** Revisit only if a genuine second data source appears — an alumni verification integration (open question 1) would be an integration client, not a repository.

---

## ADR-013 — Reporting Derives from Transactional Truth; No Analytics Platform

**Decision.** Dashboards and reports are computed from the transactional tables through dedicated read-side Query classes. No warehouse, ETL pipeline, or analytics platform for MVP. Materialized views only if a measured query proves too slow.

**Alternatives.** (a) A separate analytics database with ETL. (b) Stored/denormalized KPI counters. (c) Materialized views from the start.

**Reason.** FR-REP-001 to FR-REP-004 describe dashboards over data this model already holds, at a data volume where indexed queries are sufficient. A warehouse adds a synchronization problem and a second version of the truth. Stored counters drift — the same class of defect INV-026 exists to prevent.

**Tradeoffs.** Complex funnel queries may need tuning. Dashboards read the primary database; a read replica can be added later.

**Consequences.** **Time-to-Fill stays `offer_accepted_at − published_at`**, using the earliest accepted offer per vacancy and undefined while `published_at` is null (INV-013, INV-031). No metric is persisted. Exports run as queued, role-scoped, audited jobs.

---

## ADR-014 — Deployment: Containerized Monolith, Three Process Roles

**Decision.** One application image deployed as three process roles — web/API (horizontally scaled), queue workers (horizontally scaled per queue), and scheduler (**exactly one instance**) — behind a TLS-terminating reverse proxy, with PostgreSQL, Redis, and S3-compatible storage as managed or self-hosted dependencies.

**Alternatives.** (a) Single VM running everything. (b) Serverless/FaaS. (c) Kubernetes microservices. (d) Shared hosting.

**Reason.** Matches the modular monolith without over-engineering. Separating process roles lets queue throughput scale independently of web traffic — the realistic first bottleneck, since email and notifications are on the critical path of most flows. Serverless fits Laravel queue workers and the scheduler poorly. Kubernetes microservices are unjustified at this scale.

**Tradeoffs.** More processes to supervise than a single VM. Requires container tooling in the deployment environment.

**Consequences.** **Exactly one scheduler instance** — two would double-publish vacancies; the Redis lock is defence in depth, not the primary control. Rolling deploys require backward-compatible migrations (expand → deploy → contract). Workers restart after every deploy. Sessions in PostgreSQL keep web instances stateless.

> **Amended by ADR-017.** The topology now has **four** process roles, not three: the Inertia SSR renderer is added. It ships in the same release and holds no independent database or API.

---

---

## ADR-015 — Runtime SMTP Configuration with Application-Encrypted Secret

**Supersedes ADR-010.** Resolves the conflict raised there.

**Decision.** A single logical entity, `smtp_configurations`, holds runtime-managed SMTP settings including an **application-encrypted** credential. Super Admin manages it through the administrative interface. The encryption key is sourced from deployment secret configuration and never stored in the database. The credential is write-only: never returned after save, masked in the interface, replaced wholesale on change, and absent from logs, exports, queue payloads, and audit payloads. A separate `system_secrets` abstraction was considered and **not** adopted.

**Alternatives.**
(a) Environment-only configuration — rejected: the business confirmed Super Admin must update the credential at runtime, and env-only would require a deployment for every change.
(b) `smtp_configurations` **+** `system_secrets` — a general secret-store abstraction with the SMTP credential as its first entry.
(c) Plaintext or database-encrypted-at-rest-only storage — rejected outright.
(d) Delegating to an external secret manager with a runtime write API — rejected as premature.

**Reason.** FR-NOTIF-005 is explicit, and the business has confirmed it. Between (a) and (b): exactly **one** secret in this entire system is runtime-managed. A general secret store would be an indirection layer containing one row, with its own lifecycle, its own access rules, and its own tests — complexity bought against a second use case that does not exist. The brief asked for the simpler secure solution, and a single entity with a write-only encrypted field is both simpler and no less secure: the security properties come from INV-035, not from the shape of the table. Application-level encryption with an external key means database access alone does not yield the credential.

**Tradeoffs.** A credential now lives in the database, which the original architecture brief preferred to avoid — mitigated, not eliminated, by encryption with an externally-held key. If a second runtime-managed secret ever appears, `system_secrets` should be reconsidered rather than adding a second bespoke encrypted column. Encryption-key rotation requires re-encrypting stored ciphertext, a documented operational procedure.

**Consequences.** `smtp_configurations` is added to the logical model in revision 1.1-C2, governed by INV-035 (confidentiality) and INV-036 (at most one active configuration). It is **system configuration, not business-domain data**: it carries no recruitment meaning, appears in no reporting derivation, and must never be referenced by a business rule. `smtp_configurations.max_attempts` now supplies FR-NOTIF-003's configurable retry ceiling, which previously had no home. **`email_outbox` still never holds an SMTP credential** — INV-015 remains in force unchanged. Superseded configuration rows are retained as history; their ciphertext may be cleared by a retention process without deleting the row.

---

## ADR-016 — Selector Authorization by Stage Assignment

**Resolves human-decision item H-1.**

**Decision.** A logical entity, `selection_stage_assignments`, records the assignment of a SELECTOR user to one vacancy-specific `recruitment_stages` row, with assignment and revocation history. Selector visibility and evaluation authority derive **only** from an active assignment. Holding the SELECTOR role is a precondition for being assigned and grants no access on its own.

**Alternatives.**
(a) No model change — Selector ships with a coarser scope (all stages of any campus vacancy), accepted as a risk. Rejected: it contradicts FR-HR-006 directly.
(b) A general permission/ACL table (subject, action, resource). Rejected: it would grant a far broader capability surface than the one requirement asks for, and every unused branch of a permission model is untested attack surface.
(c) Assignment at the **vacancy** level rather than the stage level. Rejected: FR-HR-006 says "menetapkan **tahap**/data yang dapat diakses" — stage, not vacancy. Vacancy-level assignment would over-grant.
(d) Assignment inferred from `evaluations.evaluator_user_id`. Rejected: that records who *did* evaluate, which cannot authorize who *may*.

**Reason.** FR-HR-006 requires per-stage scoping, and without a stored assignment no Laravel Policy has anything to evaluate — the requirement is unenforceable in code. Assignment to a specific stage is the narrowest structure that expresses exactly the requirement, and scope is inherited from the stage's vacancy, so an assignment can never leak to an unrelated vacancy. The surrogate-key-plus-revocation shape mirrors INV-025, so assignment history survives re-assignment.

**Tradeoffs.** Adds an entity and an administrative screen to assign and revoke. Assignment must be maintained per vacancy, which is operational work for Admin Kepegawaian — but that work *is* the requirement.

**Consequences.** `selection_stage_assignments` is added in revision 1.1-C2 under INV-037. Laravel Policies gain a concrete ownership relation for the Selector role: an evaluation or applicant-view ability resolves through `active assignment → stage → vacancy → applications`. **List endpoints must be scoped by the same join**, not merely Policy-checked per object. The residual risk R-1 in `SECURITY_ARCHITECTURE.md` is closed, and the Selector role can now be enabled. Assignment changes are audited under FR-AUD-001.

---

## ADR-017 — Inertia SSR as a Fourth Runtime Process

**Amends ADR-004 (frontend) and ADR-014 (deployment).**

**Decision.** Public, SEO-sensitive pages are rendered through **Inertia SSR**, which runs as a dedicated Node renderer process belonging to the same application and release. Authenticated portals continue to use normal client-side Inertia hydration and navigation. The production topology therefore has **four** process roles: Laravel web/application, Laravel queue workers, exactly one Laravel scheduler, and the Inertia SSR renderer.

**Alternatives.**
(a) Client-rendered public pages — rejected: a job portal whose vacancy pages are not indexable fails a core discovery expectation.
(b) Blade-rendered public pages beside an Inertia application — viable, but it means two view layers, two sets of components, and design drift between the public and authenticated experience of the same vacancy.
(c) A full SSR framework (Next/Nuxt) for the whole frontend — this *is* the separated SPA architecture rejected in ADR-001, with all of its costs.
(d) Prerendering public pages to static HTML at build time — rejected: vacancy content is dynamic and changes on publish, close, and expiry.

**Reason.** ADR-004 already committed to server-rendered public pages; this decision names the mechanism and admits its cost honestly rather than leaving a Node process undeclared in the deployment topology. Inertia SSR reuses the *same* Vue components as the client, so there is no second view layer and no design drift.

### Why this does not contradict rejecting the separated SPA + API architecture

The distinction is not "is there Node in production" — it is where the contract, the routing, and the authentication live.

| | Rejected separated SPA + API | Inertia SSR (this decision) |
| --- | --- | --- |
| Routing and validation | Duplicated in the frontend application | **Laravel only** |
| API contract | A second, independently versioned contract to keep in sync | **None — Inertia passes props** |
| Authentication | Token in browser storage, cross-origin | **Cookie session, same-origin, unchanged** |
| Deployment | Frontend independently deployed and versioned | **Same release, same version, deployed together** |
| Node's role | Runs the application | **Renders the first HTML payload, nothing more** |
| Business logic in Node | Yes | **None** |
| Independent database or API | Yes | **Neither** |

The SSR renderer is **not a microservice**. It holds no business logic, no database connection, no API surface, and no independent lifecycle. It executes the same compiled components the browser would, one HTTP request earlier.

**Tradeoffs.** A Node runtime must be present, supervised, and patched in production. Components must be SSR-safe — no direct `window`/`document` access during render. Debugging spans two runtimes. The SSR bundle is a second build artefact that must be versioned with the application.

**Consequences.**

- **Failure is degradation, not outage.** If the renderer is unavailable, Inertia falls back to client-side rendering: the application keeps working and authenticated portals are unaffected, but public pages lose their server-rendered HTML and **search indexing silently degrades**. This is a failure with no user-visible error, so it must be monitored explicitly rather than discovered through ranking loss.
- **Health and restart.** The renderer is supervised with automatic restart, exposes a health check, and is included in readiness gating for public routes. It **must be restarted on every deploy**, because it holds the compiled SSR bundle in memory — a stale renderer would serve the previous release's markup alongside the new application.
- **Same release, always.** The SSR bundle is built and versioned with the application. It is never deployed independently.
- Documented in `DEPLOYMENT_ARCHITECTURE.md` §2 and §5, and in `SYSTEM_ARCHITECTURE.md` §1.

---

## Decision Summary

| ADR | Area | Decision | Status |
| --- | --- | --- | --- |
| 001 | Application architecture | Modular monolith + Inertia; `/api/v1` reserved | Proposed |
| 002 | Backend framework | Laravel | Given |
| 003 | Database | PostgreSQL 16+ | Proposed |
| 004 | Frontend | Inertia + Vue 3 + Tailwind | Proposed — amended by ADR-017 |
| 005 | Authentication | Session guard; Sanctum reserved | Proposed |
| 006 | Queue / cache / locks | Redis; sessions in PostgreSQL | Proposed |
| 007 | Object storage | S3-compatible, private, app-mediated download | Proposed |
| 008 | API style | REST + action sub-resources, `/api/v1` | Proposed |
| 009 | `apps/worker` | Same application, different entrypoint | Proposed |
| 010 | SMTP configuration | Raised as an open conflict | **Superseded by ADR-015** |
| 011 | Verification / reset tokens | Follow the ERD, not framework defaults | Proposed |
| 012 | Repository layer | None | Proposed |
| 013 | Reporting | Transactional truth, no analytics platform | Proposed |
| 014 | Deployment | Containerized monolith, process roles | Proposed — amended by ADR-017 |
| 015 | **Runtime SMTP configuration** | `smtp_configurations` with application-encrypted write-only secret | Proposed — resolves ADR-010 |
| 016 | **Selector authorization** | `selection_stage_assignments` per stage | Proposed — resolves H-1 |
| 017 | **Inertia SSR runtime** | Fourth process role, same release | Proposed — amends ADR-004, ADR-014 |

## Business Open Questions — Not Decided Here

| # | Question | Status |
| --- | --- | --- |
| 1 | Alumni verification integration source | **Open** — no integration component introduced |
| 2 | Minimum company legal documents by organization type | **Open** — validation configuration |
| 3 | Salary mandatory / display policy | **Open** — validation and view configuration |
| 4 | Recruiter domain / subdomain | **Open** — architecture supports all three shapes; must be settled before production DNS/TLS |
| 5 | WhatsApp notification phase | **Open** — no provider abstraction built |
| 6 | First recruiter default role / minimum Company Admin | **Open** — affects onboarding Action and a Policy guard |

## Human-Decision Items Carried from the ERD

| # | Item | Architectural status |
| --- | --- | --- |
| ~~H-1~~ | ~~Selector stage assignment~~ | **RESOLVED by ADR-016** and logical model revision 1.1-C2 (`selection_stage_assignments`, INV-037). Retained for history |
| H-2 | Vacancy-level outcome not recordable | Limits FR-REP-002 incomplete-outcome monitoring |
| H-3 | Candidate revocation of a shared document | Constrained by INV-032; no architectural blocker |
| H-4 | Audit IP / device metadata collection | Policy switch; both fields stay optional |
