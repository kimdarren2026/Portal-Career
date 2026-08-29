# API Contract — Portal Karir Kampus

**Status:** Proposed — awaiting approval
**Date:** 24 August 2026
**Baselines (frozen):** BRD v1.1 · FSD v1.1 · Stitch canonical baseline · Logical model **1.1-C2** · Architecture **ADR-001 to ADR-017**
**Revision:** Final semantic correction pass, 24 August 2026 — Career Center vacancy-authoring ruling, company legal-document supersede rule, candidate collection sync, and the VERSIONED_API / INERTIA_WEB surface split.
**Amendment:** Auth HTTP surface, 24 August 2026 — SPEC-DOC-05 accepted. The ten authentication and session operations are reclassified to `INERTIA_WEB` for MVP browser authentication (Laravel session guard + CSRF). Their `/api/v1` bearer-token twins remain reserved. SPEC-DOC-06 resolved. **No business rule changed.**
**Amendment:** Candidate Core HTTP surface, 24 August 2026 — SPEC-DOC-07 accepted. The twenty-one Candidate Core operations — profile read and update, the six profile sub-collections, candidate verification request and its paired read, and the five candidate document operations — are reclassified to `INERTIA_WEB` for the MVP browser Candidate portal (Laravel session guard + CSRF, `OWN` authorization, verified-email gate). Their `/api/v1` bearer-token twins remain reserved. `GET /api/v1/candidate/saved-vacancies` is excluded and stays with the vacancy-discovery phase. **No business rule changed, and no implementation block is lifted.**
**Amendment:** Candidate Application MVP transport, 26 August 2026 — SPEC-DOC-08 accepted. The four candidate-facing Candidate Application Foundation v1 operations — submit, candidate's own list, candidate's own detail, and withdraw — are reclassified to `INERTIA_WEB` for the MVP browser Candidate portal (Laravel session guard + CSRF, authenticated candidate, existing account/email gates), the same pattern SPEC-DOC-05 and SPEC-DOC-07 already established. Their `/api/v1` bearer-token twins remain reserved. `POST /api/v1/applications/{application}/reopen` is **not** reclassified — AD-2 (reopen/reapply) remains open and deferred, so it stays `VERSIONED_API`/reserved with no runtime of any kind. The `COMPANY_SCOPE`/`CAMPUS_SCOPE`/`ASSIGNED_STAGE`/Auditor scopes on list/detail belong to the Recruiter Applicant Management phase and are untouched. **No business rule changed** — AD-1, AD-4, consent, documents, screening, history, audit, notifications, idempotency, and concurrency behave identically regardless of surface.
**Companions:** `API_ENDPOINTS.md` (versioned inventory) · `INERTIA_ACTIONS.md` (internal inventory) · `ERROR_CODES.md` · `AUTHORIZATION_MATRIX.md` · `API_SIZE_REVIEW.md` (historical)

**This document is authoritative for behaviour on both surfaces.** Every operation carries a **Surface** classification — `VERSIONED_API` or `INERTIA_WEB` — which governs *where it is routed and whether it carries a compatibility promise*, never *how it behaves*. Both surfaces call the same Actions, Form Requests, Policies, and invariants. **No business rule is ever implemented twice.**

Documentation only. No route, controller, Form Request, Action, Policy, Resource, model, migration, or SQL is created by this document.

This contract is written so that implementation requires **no invention of business behaviour**. Where a rule is not derivable from a frozen baseline, it is marked **PENDING BUSINESS DECISION** rather than guessed.

---

## Part I — Conventions

#### 1. Base and Style

| Item | Value |
| --- | --- |
| Base path | `/api/v1` (ADR-008). All URIs below are relative to it |
| Style | REST resources, plus **named action endpoints** for lifecycle transitions |
| Media type | `application/json` request and response; `multipart/form-data` for uploads |
| Character encoding | UTF-8 |

**Why named actions rather than generic `PATCH`.** A `PATCH` carrying `status` invites a client to drive an invalid transition and scatters state-machine logic across the payload surface. `POST /vacancies/{vacancy}/submit-review` maps to exactly one Action, one Policy check, one validated transition, one history row, and one audit row. Generic `PATCH` is reserved for genuine attribute edits.

#### 2. Two Surfaces, One Core

| Surface | Auth | Notes |
| --- | --- | --- |
| **Web (Inertia)** | Laravel session cookie + CSRF | The five portals, **all MVP browser authentication**, and **the MVP Candidate Core operations**. Every operation is specified in this document; the route inventory is `INERTIA_ACTIONS.md` |
| **`/api/v1`** | Sanctum bearer token | **Reserved for future non-browser clients and inactive at MVP** (ADR-001, ADR-005). Route inventory: `API_ENDPOINTS.md` |

Both surfaces share Actions, Form Requests, Policies, and invariants. **No rule is implemented twice.** Where this contract states a business rule, it holds identically on either surface.

**Both surfaces are fully enumerated.** Every operation appears in exactly one inventory — `API_ENDPOINTS.md` for `VERSIONED_API`, `INERTIA_ACTIONS.md` for `INERTIA_WEB` — and both are generated from this document and verified bidirectionally. *(Resolves SPEC-DOC-06, which recorded a stale claim that the web surface was "not enumerated here".)*

#### 2b. Surface Classification

Every operation below is classified on exactly one surface.

| | **VERSIONED_API** | **INERTIA_WEB** |
| --- | --- | --- |
| Route | `/api/v1/…` | Application route (`/…`), no version prefix |
| Authentication | Sanctum bearer token | Laravel session cookie |
| CSRF | Not applicable (stateless) | **Required** on every mutating request |
| Response shape | The envelope in §3/§4 | Inertia response, or the same envelope for XHR |
| Compatibility promise | **Yes** — versioned, deprecation-managed | **No** — free to change with the application |
| Inventory | `API_ENDPOINTS.md` | `INERTIA_ACTIONS.md` |
| Business behaviour | **Identical** — same Action, Policy, validation, invariants, error codes | |

**The split is about consumers, not capability.** An operation is `VERSIONED_API` when a consumer outside our deploy cycle plausibly depends on it — a search engine, or a future first-party client. Everything else is `INERTIA_WEB`: still fully specified here, still Policy-enforced, still audited, but free to evolve with the application because its only consumer ships with it.

**The reserved versioned surface is candidate-facing: public reads, authentication, and the complete candidate capability set.** That boundary is chosen because it is a *coherent whole* rather than an arbitrary slice: the candidate portal is the only channel with a plausible near-term second client, and a half-versioned candidate API — able to log in but not to apply — would be worse than either extreme. Back-office operations (recruiter, Career Center, Kepegawaian, Selector, Auditor, Super Admin) have no plausible non-web consumer and are `INERTIA_WEB` at MVP.

**Reserved is not active, and what an MVP browser calls is `INERTIA_WEB`.** `/api/v1` is inactive at MVP, so every operation an MVP browser portal actually depends on is routed on the session-guard surface: authentication and session by SPEC-DOC-05, Candidate Core by SPEC-DOC-07, and the four Candidate Application Foundation v1 operations (submit, own list, own detail, withdraw) by SPEC-DOC-08. Neither amendment shrinks the boundary above — each keeps its `/api/v1` twin reserved as a promotion target — but for MVP those twins are inventoried in `INERTIA_ACTIONS.md` against their active browser routes rather than in `API_ENDPOINTS.md`. The versioned inventory holds the remainder: public reads, `reopen` (AD-2 open/deferred), and the candidate-facing operations belonging to later phases.

Promoting an `INERTIA_WEB` operation to `VERSIONED_API` later is additive and requires no behavioural change — only a route, a token guard, and a compatibility commitment.

**Idempotency and concurrency requirements apply identically on both surfaces.** Business safety does not weaken because an action is reached through a session cookie.

##### Browser authentication is `INERTIA_WEB` (SPEC-DOC-05, accepted)

All ten authentication and session operations are `INERTIA_WEB` for MVP. A browser authenticates with the **Laravel session guard** — an `httpOnly`, `Secure`, `SameSite=Lax` encrypted cookie — over **CSRF-protected**, same-origin, non-versioned routes. This follows ADR-005 and `SECURITY_ARCHITECTURE.md` §1 directly.

Routing browser authentication through `/api/v1` was considered and rejected for four reasons, each recorded so the decision is not silently revisited:

| # | Problem with a versioned browser login |
| --- | --- |
| 1 | `/api/v1` is specified as stateless and **CSRF-exempt**. A route that establishes a session cookie while exempt from CSRF is a **login-CSRF vulnerability**, and contradicts ADR-005's consequence that "CSRF protection applies to all session-authenticated mutating requests" |
| 2 | `/api/v1` carries the **Sanctum bearer guard**; browser login needs the session guard |
| 3 | `VERSIONED_API` carries a **backward-compatibility promise**. Binding internal login plumbing to it prevents the UI evolving with the application — the exact cost the split exists to avoid |
| 4 | `device_name` is "required to mint a token on the `/api/v1` surface" and is **meaningless for a browser session** |

##### Page delivery is not a contract operation

Two different things reach the browser, and only one is specified here:

| | **Page delivery** | **State-changing operation** |
| --- | --- | --- |
| Example | `GET /login` renders the login page | `POST /login` authenticates |
| Purpose | Render an Inertia page; read-only, no business effect | Executes an Action |
| Specified in this contract | **No** | **Yes** |
| Inventory | Neither — it is UI plumbing | `INERTIA_ACTIONS.md` |
| CSRF | Not applicable (`GET`) | **Required** |

`GET` page routes such as `/login`, `/register`, `/forgot-password`, `/reset-password/{token}` and `/verify-email/{token}` **render** the corresponding page and change nothing. They are deliberately absent from both inventories and from the operation counts: enumerating them would confuse UI routing with business capability. Only the `POST`/`PUT` operations below carry contract behaviour.

##### The reserved `/api/v1` authentication twin

The architectural concept is preserved, not deleted:

- **`/api/v1` bearer authentication remains reserved** for future non-browser and external clients (ADR-001, ADR-005).
- **Sanctum personal access token support is conditional**, and `personal_access_tokens` is **not required for MVP browser authentication** — the session guard involves no Sanctum component (`DATABASE_SCHEMA.md` §23).
- Each auth operation's heading remains its **canonical `/api/v1` identifier**, so the twin already has a name.
- When API authentication is activated, the API adapter **calls the same domain Actions** with the same validation, error codes, and authorization. Promotion is additive: a route, a token guard, a compatibility commitment. **No behavioural change, and no second implementation of any rule.**

#### 3. Success Envelope

Single resource:

```json
{ "data": { "…": "…" }, "meta": { "correlation_id": "01J9…" } }
```

Collection:

```json
{
  "data": [ { "…": "…" } ],
  "meta": {
    "correlation_id": "01J9…",
    "pagination": { "page": 1, "per_page": 25, "total": 137, "total_pages": 6 }
  }
}
```

`meta.warnings[]` carries non-fatal signals — most importantly `EMAIL_DELIVERY_PENDING` (INV-015). Action endpoints that change state return the affected resource in `data` so the client never needs a follow-up read.

#### 4. Error Envelope

Defined in `ERROR_CODES.md` §1. Clients switch on `error.code`, never on `error.message`.

#### 5. HTTP Status Semantics

| Status | Used for |
| --- | --- |
| `200` | Successful read, or a successful action returning the affected resource |
| `201` | A new resource was created. `Location` header returned |
| `202` | **Genuinely asynchronous only** — the request was accepted and the outcome is not yet determined. Used for registration, forgot-password, resend-verification (deliberately indistinguishable outcomes), and export generation |
| `204` | Success with no body — revoke, mark-read, delete |
| `400` | **Malformed request only** — unparseable body, structurally invalid parameter |
| `401` | No valid session or token |
| `403` | Authenticated but not permitted, **or** a business gate refused (company not verified, email not verified, not eligible) |
| `404` | Not found, **or outside the actor's scope** (`ERROR_CODES.md` §10) |
| `409` | State conflict, concurrency conflict, duplicate lifecycle |
| `413` / `415` | Upload too large / media type not accepted |
| `422` | Validation failed — transport shape **or** business invariant |
| `429` | Rate limited. `Retry-After` returned |
| `500` / `503` | Server failure / dependency unavailable |

**A business validation failure never returns `200`.** `400` is never used for business errors.

#### 6. Pagination, Filtering, Sorting

**Baseline: page-based pagination.** `?page=` and `?per_page=` (default 25, maximum 100), with `total` and `total_pages` in `meta.pagination`. Administrative tables need totals and page jumps, and the data volumes here are modest.

**Exception — cursor pagination**, offered as `?cursor=` on exactly two endpoints where the feed is large and append-heavy and offset paging would skip or repeat rows under concurrent writes:

- `GET /public/vacancies`
- `GET /audit-logs`

Both accept either form; cursor responses return `meta.pagination.next_cursor` and omit `total`.

**Filtering** uses explicit named query parameters, allow-listed per endpoint. No generic `filter[column]=` syntax, no operator expressions, no raw predicates. Multi-value filters accept a comma-separated list. Date ranges use `_from` / `_to` suffixes.

**Sorting** uses `?sort=field` / `?sort=-field` (leading `-` for descending), restricted to an explicit **whitelist per endpoint**. An unlisted field returns `422 VALIDATION_FAILED`. A client-supplied column name reaching an `orderBy` is an injection vector even through a query builder (`SECURITY_ARCHITECTURE.md` §3).

#### 7. Idempotency

`Idempotency-Key` is a client-generated unique value (UUID recommended) sent as a request header.

| Behaviour | Rule |
| --- | --- |
| Scope | Key + authenticated actor + endpoint. A key from one actor never matches another's |
| First request | Executes normally; the response status and body are retained |
| Replay, same payload | Returns the **retained response** without re-executing. Header `Idempotency-Replayed: true` |
| Replay, different payload | `409 IDEMPOTENCY_KEY_REUSED` |
| Replay while in flight | `409 IDEMPOTENT_REPLAY_IN_PROGRESS` |
| Retention | At least 24 hours. **Storage is not specified here** — see Part VIII |
| Missing on a required endpoint | Accepted, but the caller forfeits replay protection and the endpoint's own uniqueness rule becomes the only guard |

Endpoints requiring it are marked **Idempotency: REQUIRED**. Every one is a command whose accidental repetition would create a duplicate business fact or an unintended second transition.

#### 8. Concurrency

Two mechanisms, used deliberately:

| Mechanism | Where |
| --- | --- |
| **Optimistic version check** | Attribute edits on aggregates with concurrent editors. Client sends `If-Match: <version>` or `version` in the body; a mismatch returns `409 STALE_VERSION` |
| **Pessimistic row lock inside the transaction** | Every state transition and every gate that reads another row's state — company verification, vacancy moderation, application transitions, offer acceptance, selector assignment. The aggregate root is locked so two concurrent requests cannot both pass the same gate |

Gates protected by a lock: INV-002 (company VERIFIED), INV-007 (one lifecycle), INV-024 (in-portal only), INV-031 (single accepted offer), INV-036 (single active SMTP configuration), INV-037 (single active assignment). Each is additionally backed by a database constraint, so a lost race surfaces as a `409`, never as corrupt data.

#### 9. Validation Layers

| Layer | Owns | Authoritative |
| --- | --- | --- |
| Client | Field shape, immediate feedback | **No — UX only** |
| Transport (Form Request) | Types, formats, required/conditional presence, allow-listed keys, enum membership | Yes, for shape |
| Business (Action) | Cross-row state, gates, transitions, eligibility, ownership | **Yes** |
| Database | Uniqueness, XOR, conditional uniqueness, referential integrity | Yes — the backstop |

Every rule marked *Business Rules* below is enforced server-side regardless of what the client sent.

#### 10. Common Headers

| Header | Direction | Purpose |
| --- | --- | --- |
| `Authorization: Bearer …` | Request | `/api/v1` authentication |
| `Idempotency-Key` | Request | See §7 |
| `If-Match` | Request | Optimistic concurrency |
| `X-Request-Id` | Both | Correlation ID; generated if absent, echoed always, stored in `audit_logs.correlation_id` |
| `Retry-After` | Response | **Required** on every `429` — both `RATE_LIMITED` and `AUTH_ACCOUNT_LOCKED`. Longest applicable value when several controls block at once (§11.5) |
| `X-Robots-Tag: noindex` | Response | All authenticated and document routes |

#### 11. Abuse Control — Rate Limiting and Temporary Login Lock

Stricter limits apply to unauthenticated and credential-adjacent endpoints (FSD §7.6, §10.1); per-actor limits apply elsewhere. Exceeding a limiter returns `429 RATE_LIMITED` with `Retry-After`.

**This section is the authoritative source of the numbers.** FSD FR-AUTH-004 and FR-AUTH-006 require rate limiting and a temporary lock without quantifying either; the thresholds below resolve `AUTH_RATE_LIMIT_POLICY_REQUIRED` and `AUTH_LOGIN_LOCK_POLICY_REQUIRED` for MVP. Each value is a documented policy decision, not an implementation detail — changing one is a contract change.

##### 11.1 Authentication limiter matrix

| Operation | IP limiter | Identity / token limiter | Limiter key subject |
| --- | --- | --- | --- |
| `POST /auth/register/candidate` | **5 / 30 min** | **3 / 60 min** | `email_normalized` |
| `POST /auth/register/recruiter` | **5 / 30 min** | **3 / 60 min** | `email_normalized` |
| `POST /auth/login` | **20 / 5 min** | **10 / 15 min** | `email_normalized` |
| `POST /auth/resend-verification` | **10 / 15 min** | **3 / 15 min** | `email_normalized` |
| `POST /auth/verify-email` | **20 / 10 min** | **10 / 10 min** | token digest |
| `POST /auth/forgot-password` | **20 / 15 min** | **5 / 15 min** | `email_normalized` |
| `POST /auth/reset-password` | **20 / 15 min** | **5 / 15 min** | token digest |

**The two registration endpoints share one IP limiter namespace.** Candidate and recruiter registration count against the *same* per-IP bucket, so alternating between the two endpoints cannot double the allowance. Their identity buckets are likewise shared, keyed on `email_normalized`.

##### 11.2 Both limiters must be satisfied

Where an IP limiter and an identity or token limiter both apply, a request must satisfy **both**. Tripping **either** is sufficient for `429`. The limits are **conjunctive, never additive** — an IP allowance of 20 does not grant 20 attempts against a single identity that is itself capped at 10.

##### 11.3 Anti-enumeration — mandatory

**Attempt and failure accounting operates on a normalized identity-derived key even when no account exists.** An unknown address is counted exactly as a known one, in the same namespace, against the same thresholds.

A caller must not be able to infer account existence from abuse-control behaviour — not from whether a limiter or lock eventually activates, and not from response shape or timing. Unknown identities participate in equivalent accounting. Password verification continues to follow the safe-comparison behaviour required by `SECURITY_ARCHITECTURE.md` §1, so a request naming a non-existent account performs comparable work to one naming a real account. This complements, and never weakens, the identical-response rule for registration, forgot-password, and resend-verification (`ERROR_CODES.md` §4).

##### 11.4 Limiter key privacy

**A raw email address, verification token, or password reset token must never appear in a rate-limit key name.** Limiter state lives outside PostgreSQL, so a raw key would place a credential-bearing or personally identifying value into a store that the database's access controls do not cover, and into any tooling that can enumerate keys.

Keys are built from a deterministic digest of the normalized identifier or token:

```
auth:<operation>:ip:<ip>
auth:<operation>:identity:<digest of email_normalized>
auth:<operation>:token:<digest of raw token>
```

The digest algorithm and namespace prefix are implementation choices; **the prohibition on raw values is contractual.** This extends `SECURITY_ARCHITECTURE.md` §7 "Never logged or audited" from logs and audit payloads to runtime limiter keys.

##### 11.5 `Retry-After` is mandatory on every abuse-control 429

| Response | `Retry-After` value |
| --- | --- |
| `RATE_LIMITED` | Seconds until the applicable limiter admits another attempt |
| `AUTH_ACCOUNT_LOCKED` | Seconds until the temporary lock expires |

**When more than one limiter or lock blocks the same request, return the longest applicable `Retry-After`.** A client honouring a shorter value would retry early and trip a still-active control.

##### 11.6 Temporary login lock

Credential failures are tracked **by normalized identity**, independently of the `POST /auth/login` limiters in §11.1.

| Property | Policy |
| --- | --- |
| Threshold | **8 failed credential attempts** |
| Observation window | **Rolling 15 minutes** |
| Lock duration | **15 minutes** |
| Response | `AUTH_ACCOUNT_LOCKED`, HTTP `429`, `Retry-After` required |
| Storage | Runtime state only — see §11.7 |
| Expiry | Self-clearing; **never permanent** |
| On success | Failure counter **and** any active lock are cleared |

The lock is time-boxed and self-clearing by design. A permanent or administratively-sticky lock would hand an attacker a denial-of-service primitive against a real user's account (`SECURITY_ARCHITECTURE.md` §1).

**What counts as a credential failure.** Increment the counter **only** for a failed credential authentication — a wrong password, or an unknown normalized identity (§11.3).

**Do not increment** when the credentials were correct and the request was refused on account state: `PENDING_EMAIL_VERIFICATION`, `SUSPENDED`, or `DISABLED`. Those are authorization outcomes, not password failures. Counting them would let repeated requests against a suspended or disabled account extend an existing lock indefinitely, and would blur an account-state signal into a credential signal.

##### 11.7 Storage — runtime only

Rate-limit counters, credential-failure counters, and temporary lock state are held in **Redis runtime state** (`DEPLOYMENT_ARCHITECTURE.md`). They are deliberately **not persisted**:

- no table is created for them, and no PostgreSQL schema change is implied;
- `users.status` is **not** written by a temporary lock — it remains the durable account-state field (`PENDING_EMAIL_VERIFICATION`, `ACTIVE`, `SUSPENDED`, `DISABLED`) and never records a transient throttle;
- state expires on its own; losing it fails **open** for availability, the accepted trade-off for a control that must never become permanent.

Lock activation is auditable as a login event (`SECURITY_ARCHITECTURE.md` §7) — auditing the *event* is not the same as persisting the *state*.

##### 11.8 Candidate document upload limiter

Approved and frozen 25 August 2026 with the candidate document upload policy.

| Property | Policy |
| --- | --- |
| Operation | `POST /candidate/documents` |
| Limit | **20 upload requests per rolling hour, per authenticated candidate identity** |
| Counted | Every upload request admitted to the limiter — successful and failed alike — so a rejected file cannot be retried without cost |
| Limiter key subject | The authenticated **user identifier**, or another deterministic non-sensitive key. **Never a raw email address** (§11.4) |
| Response | `RATE_LIMITED`, HTTP `429`, `Retry-After` **required** (§11.5) |
| Storage | Redis runtime state, per §11.7 — no table, no schema change |

No storage quota applies at MVP; a per-candidate storage quota is **DEFERRED** and may be added later without changing this operation, its route, or its payload.

##### 11.9 Company member invitation limiter

Approved 25 August 2026 with the company member invitation MVP decision.

| Property | Policy |
| --- | --- |
| Operation | `POST /companies/{company}/members` |
| Limit | **20 attempts per rolling hour, per authenticated Company Admin identity** |
| Counted | Every admitted attempt — created, duplicate, and unknown-account alike — so an account-probing loop costs the same as a working invite |
| Limiter key subject | The authenticated **user identifier**. **Never a raw email address** (§11.4) |
| Response | `RATE_LIMITED`, HTTP `429`, `Retry-After` **required** (§11.5) |
| Storage | Redis runtime state, per §11.7 — no table, no schema change |

The subject is the acting admin, not the company: a single admin cannot spread the same probing budget across several companies they administer.

#### 12. Audit and Outbox Notation

Each contract states its **Audit** consequence (a row in `audit_logs`, FR-AUD-001) and its **Notification / Outbox** consequence (`notifications` rows, `email_outbox` rows, FR-NOTIF-002). Both are written **inside** the business transaction; the queue job is dispatched **after commit** (INV-015). "None" means exactly that.

##### The MVP browser Candidate portal is `INERTIA_WEB` (SPEC-DOC-07, accepted)

Candidate Core was originally classified `VERSIONED_API` throughout. That left the MVP browser Candidate portal with **no legal HTTP surface**: `API_ENDPOINTS.md` freezes `/api/v1` as reserved for future non-browser clients and inactive at MVP, and `INERTIA_ACTIONS.md` carried no equivalent Candidate operations. The contradiction is resolved the same way browser authentication was (SPEC-DOC-05) — by classifying the operations onto the surface the approved architecture already designates for browser portals, **not** by activating `/api/v1` and **not** by inventing undocumented routes.

**Twenty-one Candidate Core operations** are therefore `INERTIA_WEB`: profile read and update, the six profile sub-collections (read and atomic synchronize each), candidate verification request **and its paired read**, and the five candidate document operations. The paired read `GET /candidate/verifications` is documented inside the verification contract rather than under its own heading, which is why an operation count taken from `###` headings alone reports twenty; the inventory count is twenty-one. Their active browser paths are the contract URIs with the `/api/v1` prefix removed. Session guard, `OWN` authorization, the verified-email gate, and CSRF on mutations apply; **no bearer token, no Sanctum activation, and no `personal_access_tokens`.**

`GET /candidate/saved-vacancies` is **deliberately excluded** — it belongs to the later vacancy-discovery phase and is reconciled with that phase, not this one.

**Surface classification is not implementation authorization.** `POST /candidate/verifications` carries an explicit implementation block that this amendment does not lift (verification business decision, Part X item 1). `POST /candidate/documents` is **no longer blocked** — its MIME and size policy was approved and frozen on 25 August 2026 (Part X item 9, **CLOSED**; values in this section's contract below) — and it remains **unrouted pending implementation**, which is a delivery state, not a policy block.

---

#### 13. Contract Grouping Convention

Most endpoints have their own `###` section. Five families of structurally identical CRUD are documented as **one grouped contract** that enumerates every concrete URI it covers in a table — candidate profile sub-collections, master data, and similar. Grouped contracts carry the **full section template**; the shared rules genuinely are shared. `API_ENDPOINTS.md` lists every concrete URI and names the contract section that governs it.

---

## Part II — Authentication and Session

### POST /api/v1/auth/register/candidate

**Surface:** `INERTIA_WEB`  ·  **MVP browser route:** `POST /register/candidate`

> **Reclassified for MVP browser authentication (SPEC-DOC-05, accepted).** Browser
> authentication uses the Laravel session guard with CSRF protection, per ADR-005 and
> `SECURITY_ARCHITECTURE.md` §1 — not a Sanctum bearer token. The heading above remains the
> canonical operation identifier and is the reserved `/api/v1` twin for when token
> authentication is activated for a non-browser client; promotion is then additive and
> requires no behavioural change. Business rules, validation, error codes, side effects,
> audit, and authorization below are unchanged and apply identically on either surface.

**Purpose:** Self-registration of a candidate account (FR-AUTH-002).

**Authentication:** None (public).

**Authorization:** Public. Abuse-controlled: **5 / 30 min per IP** and **3 / 60 min per `email_normalized`** (§11.1). The per-IP bucket is **shared with recruiter registration** — alternating between the two endpoints does not raise the allowance.

**Request:**

| Field | Type | Required | Notes |
| --- | --- | --- | --- |
| `name` | string | Yes | 2–150 chars |
| `email` | string | Yes | Valid address; normalized server-side before any uniqueness check |
| `password` | string | Yes | FR-AUTH-006 policy |
| `password_confirmation` | string | Yes | Must match |
| `candidate_type` | enum | Yes | `EXTERNAL` \| `FINAL_YEAR_STUDENT` \| `ALUMNI` — **self-declared identity only** (INV-028) |
| `accepted_terms` | boolean | Yes | Must be `true`; never pre-checked client-side |

**Validation:** Email format; password policy; `candidate_type` within the three values; `name` length. Uniqueness is checked against `users.email_normalized`, **never** against `users.email` (INV-001).

**Business Rules:**
- Creates `users` (`status = PENDING_EMAIL_VERIFICATION`), `password_credentials`, `candidate_profiles`, and the matching candidate role assignment in `user_roles`.
- `candidate_type` is the self-declared category. It grants nothing and proves nothing; verified eligibility requires `candidate_verifications` (INV-028).
- **No temporary password is ever generated or emailed** (ADR-005).
- Issues a hashed one-time verification token per ADR-011 / INV-021 — high-entropy value, only its hash stored, raw value emailed once.
- **If the email is already registered, the response is byte-identical to a successful registration** and an account-exists notice is emailed instead. Account enumeration is not permitted (FSD §9.3).

**Success Response:** `202 Accepted`

```json
{ "data": { "status": "VERIFICATION_EMAIL_QUEUED" },
  "meta": { "correlation_id": "01J9…", "warnings": ["EMAIL_DELIVERY_PENDING"] } }
```

**Error Codes:** `VALIDATION_FAILED` (422) · `AUTH_PASSWORD_POLICY` (422) · `RATE_LIMITED` (429).

**Side Effects:** User, credential, candidate profile, role assignment, verification token, outbox row — all in one transaction.

**Audit:** `registration` with the new user as object. No password or token value in the payload.

**Notification / Outbox:** One `email_outbox` row — verification email. Dispatched after commit. No in-app notification (the user cannot yet sign in).

**Idempotency:** Not required. The `email_normalized` uniqueness rule plus the identical-response behaviour make repeats harmless.

**Concurrency:** Two simultaneous registrations of one address — the unique index resolves it; the loser receives the same `202`.

**Source Requirement:** FR-AUTH-001, FR-AUTH-002, FR-CAN-001 · INV-001, INV-021, INV-028

---

### POST /api/v1/auth/register/recruiter

**Surface:** `INERTIA_WEB`  ·  **MVP browser route:** `POST /register/recruiter`

> **Reclassified for MVP browser authentication (SPEC-DOC-05, accepted).** Browser
> authentication uses the Laravel session guard with CSRF protection, per ADR-005 and
> `SECURITY_ARCHITECTURE.md` §1 — not a Sanctum bearer token. The heading above remains the
> canonical operation identifier and is the reserved `/api/v1` twin for when token
> authentication is activated for a non-browser client; promotion is then additive and
> requires no behavioural change. Business rules, validation, error codes, side effects,
> audit, and authorization below are unchanged and apply identically on either surface.

**Purpose:** Self-registration of a recruiter account, step 1 of FR-ONB-001.

**Authentication:** None (public).

**Authorization:** Public. Abuse-controlled: **5 / 30 min per IP** and **3 / 60 min per `email_normalized`** (§11.1). The per-IP bucket is **shared with candidate registration**.

**Request:** `name`, `email`, `password`, `password_confirmation`, `accepted_terms` — as above, without `candidate_type`.

**Validation:** As above.

**Business Rules:**
- Creates `users` (`PENDING_EMAIL_VERIFICATION`), `password_credentials`, and a recruiter role assignment. **No company is created here.**
- The mandatory flow is **Register → Verify Email → Complete Company Profile → Submit Verification → VERIFIED → vacancy creation available** (FR-ONB-001). No step may be skipped.
- **No temporary password.** Recruiters self-register exactly like candidates.
- The recruiter role assignment created during registration is global identity context only. At company creation, the authenticated creator receives the first active company membership as `COMPANY_ADMIN` under closed D-1; subsequent membership roles are explicit and have no implicit default.
- Identical-response rule for existing addresses, as above.

**Success Response:** `202 Accepted`, same body shape as candidate registration.

**Error Codes:** `VALIDATION_FAILED` · `AUTH_PASSWORD_POLICY` · `RATE_LIMITED`.

**Side Effects:** User, credential, role assignment, verification token, outbox row.

**Audit:** `registration`.

**Notification / Outbox:** Verification email.

**Idempotency:** Not required.

**Concurrency:** As above.

**Source Requirement:** FR-AUTH-002, FR-ONB-001 · INV-001, INV-021 · **D-1 CLOSED by approved Product Owner decision**

---

### POST /api/v1/auth/verify-email

**Surface:** `INERTIA_WEB`  ·  **MVP browser route:** `POST /verify-email`

> **Reclassified for MVP browser authentication (SPEC-DOC-05, accepted).** Browser
> authentication uses the Laravel session guard with CSRF protection, per ADR-005 and
> `SECURITY_ARCHITECTURE.md` §1 — not a Sanctum bearer token. The heading above remains the
> canonical operation identifier and is the reserved `/api/v1` twin for when token
> authentication is activated for a non-browser client; promotion is then additive and
> requires no behavioural change. Business rules, validation, error codes, side effects,
> audit, and authorization below are unchanged and apply identically on either surface.

**Purpose:** Consume a one-time email verification token (FR-AUTH-003).

**Authentication:** None — the token is the credential.

**Authorization:** Public. Abuse-controlled: **20 / 10 min per IP** and **10 / 10 min per token** (§11.1). The limiter is keyed on a **digest** of the token, never the raw token (§11.4).

**Request:** `token` (string, required — the raw value from the emailed link).

**Validation:** Token present and well-formed.

**Business Rules:**
- The token is hashed and matched against `email_verification_tokens.token_hash`. **The raw token is never stored and never returned**; no stored token material appears in any response (ADR-011).
- Rejects when expired (`expires_at`), already consumed (`used_at`), or revoked (`revoked_at`) — INV-021.
- On success: sets `used_at`, sets `users.email_verified_at`, transitions `users.status` PENDING_EMAIL_VERIFICATION → ACTIVE (FSD §8.1), and revokes the user's other outstanding verification tokens.
- Verification is a `POST`-confirmed action so link prefetching cannot silently consume the token.

**Success Response:** `200 OK` — `{ "data": { "email_verified": true, "status": "ACTIVE" } }`

**Error Codes:** `AUTH_TOKEN_INVALID` · `AUTH_TOKEN_EXPIRED` · `AUTH_TOKEN_ALREADY_USED` · `AUTH_TOKEN_REVOKED` (all 422) · `RATE_LIMITED` (429).

**Side Effects:** Token consumed, user activated, sibling tokens revoked.

**Audit:** `email_verification`. Object is the user. **No token value in the payload.**

**Notification / Outbox:** Optional welcome email; no in-app notification required.

**Idempotency:** Not required — single-use consumption is inherently idempotent-safe. A second attempt returns `AUTH_TOKEN_ALREADY_USED`.

**Concurrency:** Two simultaneous submissions of one token — the row is locked; one wins, the other receives `AUTH_TOKEN_ALREADY_USED`.

**Source Requirement:** FR-AUTH-003 · INV-021 · ADR-011

---

### POST /api/v1/auth/resend-verification

**Surface:** `INERTIA_WEB`  ·  **MVP browser route:** `POST /resend-verification`

> **Reclassified for MVP browser authentication (SPEC-DOC-05, accepted).** Browser
> authentication uses the Laravel session guard with CSRF protection, per ADR-005 and
> `SECURITY_ARCHITECTURE.md` §1 — not a Sanctum bearer token. The heading above remains the
> canonical operation identifier and is the reserved `/api/v1` twin for when token
> authentication is activated for a non-browser client; promotion is then additive and
> requires no behavioural change. Business rules, validation, error codes, side effects,
> audit, and authorization below are unchanged and apply identically on either surface.

**Purpose:** Reissue a verification link (FR-AUTH-004).

**Authentication:** None.

**Authorization:** Public. Abuse-controlled: **10 / 15 min per IP** and **3 / 15 min per `email_normalized`** (§11.1). Unknown addresses are counted identically (§11.3).

**Request:** `email` (string, required).

**Validation:** Format only.

**Business Rules:**
- Issues a fresh hashed token and revokes prior unconsumed ones for that user.
- **Response is identical whether or not the address exists or is already verified.** No enumeration.
- Does nothing for an already-verified account beyond the identical response.

**Success Response:** `202 Accepted` — `{ "data": { "status": "VERIFICATION_EMAIL_QUEUED" } }`

**Error Codes:** `VALIDATION_FAILED` · `RATE_LIMITED`.

**Side Effects:** New token row; prior tokens revoked; outbox row.

**Audit:** `email_verification_resend` where a user matched. No audit entry may reveal a non-existent address.

**Notification / Outbox:** Verification email.

**Idempotency:** Not required; the §11.1 limiters are the control.

**Concurrency:** Not significant.

**Source Requirement:** FR-AUTH-004 · INV-021

---

### POST /api/v1/auth/login

**Surface:** `INERTIA_WEB`  ·  **MVP browser route:** `POST /login`

> **Reclassified for MVP browser authentication (SPEC-DOC-05, accepted).** Browser
> authentication uses the Laravel session guard with CSRF protection, per ADR-005 and
> `SECURITY_ARCHITECTURE.md` §1 — not a Sanctum bearer token. The heading above remains the
> canonical operation identifier and is the reserved `/api/v1` twin for when token
> authentication is activated for a non-browser client; promotion is then additive and
> requires no behavioural change. Business rules, validation, error codes, side effects,
> audit, and authorization below are unchanged and apply identically on either surface.

**Purpose:** Authenticate (FR-AUTH-005).

**Authentication:** None.

**Authorization:** Public. Abuse-controlled: **20 / 5 min per IP** and **10 / 15 min per `email_normalized`** (§11.1), plus the temporary login lock in §11.6.

**Request:** `email`, `password`, optional `remember` (boolean), optional `device_name` (string — required to mint a token on the `/api/v1` surface).

**Validation:** Both fields present.

**Business Rules:**
- Compares against `password_credentials.password_hash` using the adaptive hash.
- **Failure is indistinguishable** between unknown account and wrong password — always `AUTH_INVALID_CREDENTIALS`.
- **8 failed credential attempts within a rolling 15 minutes** trigger a **temporary, self-clearing 15-minute** lock (FSD §10.1; §11.6) returning `AUTH_ACCOUNT_LOCKED` with `Retry-After`. The lock is never permanent, or an attacker could deny service to a real user. It is runtime state only — `users.status` is never written by a lock (§11.7).
- The failure counter increments **only** on a credential failure — wrong password, or unknown identity. It does **not** increment when the credentials were correct and the request was refused on account state (`PENDING_EMAIL_VERIFICATION`, `SUSPENDED`, `DISABLED`), so requests against a suspended account cannot extend an existing lock indefinitely (§11.6).
- Failures against an **unknown** address are counted on the same identity-derived key as a known one, so limiter and lock behaviour never reveal whether an account exists (§11.3).
- **Successful authentication clears** the identity credential-failure counter and any active temporary lock.
- Blocked when `users.status` is SUSPENDED or DISABLED — checked at every request, not only at login (`SECURITY_ARCHITECTURE.md` §1).
- Email verification is **not** required to sign in; it is required to act (`AUTH_EMAIL_NOT_VERIFIED` at the point of action).
- Web surface: session regenerated on login to defeat fixation. API surface: a Sanctum token is minted with abilities scoped to the actor's roles.

**Success Response:** `200 OK` — `{ "data": { "user": …, "roles": […], "token": "…" } }`. `token` appears **only** on the `/api/v1` surface and is returned exactly once.

**Error Codes:** `AUTH_INVALID_CREDENTIALS` (422) · `AUTH_ACCOUNT_SUSPENDED` (403) · `AUTH_ACCOUNT_DISABLED` (403) · `AUTH_ACCOUNT_LOCKED` (429) · `RATE_LIMITED` (429).

**Side Effects:** Session or token created. On failure the identity credential-failure counter is incremented; on success that counter and any active temporary lock are cleared (§11.6).

**Audit:** `login_success` or `login_failure`, with IP and device metadata **only where policy permits** (H-4, unresolved). Never the password.

**Notification / Outbox:** None by default.

**Idempotency:** Not applicable.

**Concurrency:** Multiple concurrent sessions are permitted.

**Source Requirement:** FR-AUTH-005, FR-AUTH-006 · FSD §8.1, §10.1 · H-4

---

### POST /api/v1/auth/logout

**Surface:** `INERTIA_WEB`  ·  **MVP browser route:** `POST /logout`

> **Reclassified for MVP browser authentication (SPEC-DOC-05, accepted).** Browser
> authentication uses the Laravel session guard with CSRF protection, per ADR-005 and
> `SECURITY_ARCHITECTURE.md` §1 — not a Sanctum bearer token. The heading above remains the
> canonical operation identifier and is the reserved `/api/v1` twin for when token
> authentication is activated for a non-browser client; promotion is then additive and
> requires no behavioural change. Business rules, validation, error codes, side effects,
> audit, and authorization below are unchanged and apply identically on either surface.

**Purpose:** End the current session or revoke the current token.

**Authentication:** Required.

**Authorization:** Self only.

**Request:** Empty body.

**Validation:** None.

**Business Rules:** Web — invalidate the session and rotate the CSRF token. API — revoke the presented token only, leaving the actor's other devices signed in.

**Success Response:** `204 No Content`.

**Error Codes:** `UNAUTHENTICATED` (401).

**Side Effects:** Session or token invalidated.

**Audit:** `logout`.

**Notification / Outbox:** None.

**Idempotency:** Naturally idempotent.

**Concurrency:** None.

**Source Requirement:** FR-AUTH-005

---

### POST /api/v1/auth/forgot-password

**Surface:** `INERTIA_WEB`  ·  **MVP browser route:** `POST /forgot-password`

> **Reclassified for MVP browser authentication (SPEC-DOC-05, accepted).** Browser
> authentication uses the Laravel session guard with CSRF protection, per ADR-005 and
> `SECURITY_ARCHITECTURE.md` §1 — not a Sanctum bearer token. The heading above remains the
> canonical operation identifier and is the reserved `/api/v1` twin for when token
> authentication is activated for a non-browser client; promotion is then additive and
> requires no behavioural change. Business rules, validation, error codes, side effects,
> audit, and authorization below are unchanged and apply identically on either surface.

**Purpose:** Request a password reset link (FR-AUTH-007).

**Authentication:** None.

**Authorization:** Public. Abuse-controlled: **20 / 15 min per IP** and **5 / 15 min per `email_normalized`** (§11.1). Unknown addresses are counted identically, so limiter behaviour cannot be used to enumerate accounts (§11.3); the outward response stays enumeration-resistant.

**Request:** `email` (string, required).

**Validation:** Format only.

**Business Rules:**
- Issues a high-entropy one-time token; **only its hash is stored** in `password_reset_tokens` with `expires_at` (INV-021, ADR-011).
- **Response is identical whether or not the address exists.** No enumeration.
- Laravel's default reset scaffold does not match the frozen entity shape and is not used verbatim (ADR-011).

**Success Response:** `202 Accepted` — `{ "data": { "status": "RESET_EMAIL_QUEUED" } }`

**Error Codes:** `VALIDATION_FAILED` · `RATE_LIMITED`.

**Side Effects:** Token row; outbox row.

**Audit:** `password_reset_requested` where a user matched. **No token value.**

**Notification / Outbox:** Reset email.

**Idempotency:** Not required.

**Concurrency:** Not significant.

**Source Requirement:** FR-AUTH-007 · INV-021 · ADR-011

---

### POST /api/v1/auth/reset-password

**Surface:** `INERTIA_WEB`  ·  **MVP browser route:** `POST /reset-password`

> **Reclassified for MVP browser authentication (SPEC-DOC-05, accepted).** Browser
> authentication uses the Laravel session guard with CSRF protection, per ADR-005 and
> `SECURITY_ARCHITECTURE.md` §1 — not a Sanctum bearer token. The heading above remains the
> canonical operation identifier and is the reserved `/api/v1` twin for when token
> authentication is activated for a non-browser client; promotion is then additive and
> requires no behavioural change. Business rules, validation, error codes, side effects,
> audit, and authorization below are unchanged and apply identically on either surface.

**Purpose:** Set a new password using a one-time token (FR-AUTH-007).

**Authentication:** None — the token is the credential.

**Authorization:** Public. Abuse-controlled: **20 / 15 min per IP** and **5 / 15 min per token** (§11.1). The limiter is keyed on a **digest** of the token, never the raw token (§11.4).

**Request:** `token`, `password`, `password_confirmation`.

**Validation:** Token present; password meets FR-AUTH-006; confirmation matches.

**Business Rules:**
- Token matched by hash; rejected if expired, used, or revoked.
- On success: `used_at` set on the consumed token, **all other valid reset tokens for that user are revoked** (INV-021), `password_hash` replaced, `password_changed_at` updated.
- **All other sessions are terminated and all API tokens revoked** — a reset is the remedy for a compromised account and must evict the attacker.

**Success Response:** `200 OK` — `{ "data": { "password_reset": true } }`

**Error Codes:** `AUTH_TOKEN_INVALID` · `AUTH_TOKEN_EXPIRED` · `AUTH_TOKEN_ALREADY_USED` · `AUTH_TOKEN_REVOKED` · `AUTH_PASSWORD_POLICY` (all 422) · `RATE_LIMITED`.

**Side Effects:** Credential replaced; sibling tokens revoked; sessions and API tokens invalidated.

**Audit:** `password_reset_completed`. Never the token or the password.

**Notification / Outbox:** Confirmation email that the password changed — a security signal to the real owner.

**Idempotency:** Not required; single-use.

**Concurrency:** Token row locked; second attempt receives `AUTH_TOKEN_ALREADY_USED`.

**Source Requirement:** FR-AUTH-007 · INV-021

---

### PUT /api/v1/me/password

**Surface:** `INERTIA_WEB`  ·  **MVP browser route:** `PUT /me/password`

> **Reclassified for MVP browser authentication (SPEC-DOC-05, accepted).** Browser
> authentication uses the Laravel session guard with CSRF protection, per ADR-005 and
> `SECURITY_ARCHITECTURE.md` §1 — not a Sanctum bearer token. The heading above remains the
> canonical operation identifier and is the reserved `/api/v1` twin for when token
> authentication is activated for a non-browser client; promotion is then additive and
> requires no behavioural change. Business rules, validation, error codes, side effects,
> audit, and authorization below are unchanged and apply identically on either surface.

**Purpose:** Authenticated self-service password change (Pengaturan Akun, FSD §4.2/§4.3).

**Authentication:** Required.

**Authorization:** Self only.

**Request:** `current_password`, `password`, `password_confirmation`.

**Validation:** Current password verified; new password meets FR-AUTH-006; confirmation matches.

**Business Rules:** Replaces `password_hash`, updates `password_changed_at`, revokes outstanding reset tokens, and terminates the actor's **other** sessions and API tokens while keeping the current one.

**Success Response:** `200 OK` — `{ "data": { "password_changed_at": "…" } }`

**Error Codes:** `AUTH_CURRENT_PASSWORD_INVALID` (422) · `AUTH_PASSWORD_POLICY` (422) · `UNAUTHENTICATED` (401).

**Side Effects:** Credential replaced; other sessions evicted.

**Audit:** `password_changed`.

**Notification / Outbox:** Security-notice email.

**Idempotency:** Not required.

**Concurrency:** Row lock on the credential.

**Source Requirement:** FR-AUTH-006

---

### GET /api/v1/me

**Surface:** `INERTIA_WEB`  ·  **MVP browser route:** `GET /me`

> **Reclassified for MVP browser authentication (SPEC-DOC-05, accepted).** Browser
> authentication uses the Laravel session guard with CSRF protection, per ADR-005 and
> `SECURITY_ARCHITECTURE.md` §1 — not a Sanctum bearer token. The heading above remains the
> canonical operation identifier and is the reserved `/api/v1` twin for when token
> authentication is activated for a non-browser client; promotion is then additive and
> requires no behavioural change. Business rules, validation, error codes, side effects,
> audit, and authorization below are unchanged and apply identically on either surface.

**Purpose:** The authoritative session/role context the frontend needs to render a portal (§5 of the brief).

**Authentication:** Required.

**Authorization:** Self only. Returns **only the actor's own** context.

**Request:** None.

**Validation:** None.

**Business Rules:**

Returns exactly:

| Field | Contents |
| --- | --- |
| `user` | id, name, email (display form), `email_verified_at`, `status` |
| `roles[]` | **Active** role codes only — `user_roles` with `revoked_at` null (INV-025) |
| `candidate_profile` | Summary where one exists: id, `current_candidate_type`, `profile_completed_at`, and `verified_eligibility[]` derived from VERIFIED `candidate_verifications` |
| `company_memberships[]` | Active memberships only: company id, name, `company_role`, and the company's `verification_status` |
| `unread_notification_count` | Integer |
| `portal_contexts[]` | Which portals this actor may enter — e.g. `CANDIDATE`, `RECRUITER`, `CAREER_CENTER`, `HR`, `SELECTOR`, `AUDITOR`, `SUPER_ADMIN` |

- **`portal_contexts` is navigation, not authorization.** It tells the UI which shells to offer. Every request is authorized server-side regardless of what the client believes.
- **No permission matrix, ability list, or Policy map is sent to the browser.** The backend remains authoritative (brief §5); shipping a capability list invites the client to treat it as truth and leaks the authorization model.
- `verified_eligibility` is derived from `candidate_verifications`, never from role codes (INV-028).
- Selector stage assignments are **not** enumerated here; they are resolved per request against `selection_stage_assignments` (INV-037).

**Success Response:** `200 OK`.

**Error Codes:** `UNAUTHENTICATED` (401).

**Side Effects:** None.

**Audit:** None — ordinary authenticated read.

**Notification / Outbox:** None.

**Idempotency:** Safe method.

**Concurrency:** Reflects state at request time. **Never cached across requests** — a stale membership or role cache is a privilege-escalation bug (ADR-006).

**Source Requirement:** FSD §2.1, §3.1, §3.3 · INV-025, INV-028, INV-037

---

## Part III — Candidate Profile and Documents

> **Reconciled against BRD v1.1 / FSD v1.1 (`CANDIDATE_CONTRACT_CONFLICT`, resolved).** FR-CAN-003 fixes the *capabilities* of the candidate profile — identity, contact, domicile, education, experience, organizations, certifications, skills, work preferences, professional links, primary CV — and FR-CAN-005 fixes private documents with MIME and size validation. **Every one of those remains MVP and is specified below.**
>
> What the business documents do **not** fix is three controlled vocabularies (work preference values, education level, candidate document type), the exact profile-completion formula, and the exact document MIME allowlist and size ceiling. `DATABASE_SCHEMA.md` §"Complete enum inventory" already records the first three as `varchar` with a `CHECK` **added once the vocabulary is approved**. This contract previously asserted those vocabularies were settled; that over-specification has been removed rather than resolved by invention. See Part X items 7–9.
>
> **No candidate operation, field, or route was removed.** Candidate core — profile, education, experience, organizations, certifications, skills, links, preferences, ownership, and `/me` candidate context — is implementable now. Only document **upload** waits on an approved MIME/size policy, and only **automatic** completion recomputation is deferred.

### GET /api/v1/candidate/profile

**Surface:** `INERTIA_WEB`  ·  **MVP browser route:** `GET /candidate/profile`

> **Reclassified for the MVP browser Candidate portal (SPEC-DOC-07, accepted).** The
> Candidate portal is a browser portal, so this operation is served over the **Laravel
> session guard with CSRF protection on mutations**, per ADR-005 and
> `SECURITY_ARCHITECTURE.md` §1 — not a Sanctum bearer token. The heading above remains the
> canonical operation identifier and is the reserved `/api/v1` twin for when the versioned
> API is explicitly activated for a non-browser client; a future adapter calls the same
> Candidate domain Actions and Queries, so promotion is additive and requires no behavioural
> change. Authorization (`OWN`), the verified-email gate, field semantics, validation,
> collection sync behaviour, pending vocabularies, error codes, side effects, and audit below
> are unchanged and apply identically on either surface.

**Purpose:** Read the authenticated candidate's own profile (FR-CAN-003).

**Authentication:** Required.

**Authorization:** `OWN`. Resolved by `candidate_profiles.user_id = actor`. A candidate can never read another candidate's profile through this route.

**Request:** None.

**Validation:** None.

**Business Rules:** Returns profile attributes, work preferences, normalized and free-text geography, `current_candidate_type`, `profile_completed_at`, and a summary of verification state. **`profile_completed_at` may be `null`** — it is returned as stored and is not computed on read (Part X item 8). Sub-collections are returned as counts plus links, or inline when `?include=educations,work_experiences,organizations,certifications,links,skills` names them (allow-listed values only).

**Success Response:** `200 OK`.

**Error Codes:** `UNAUTHENTICATED` (401) · `CANDIDATE_PROFILE_REQUIRED` (422) when the actor has no candidate profile.

**Side Effects:** None.

**Audit:** None.

**Notification / Outbox:** None.

**Idempotency:** Safe method.

**Concurrency:** None.

**Source Requirement:** FR-CAN-001, FR-CAN-003 · INV-028

---

### PATCH /api/v1/candidate/profile

**Surface:** `INERTIA_WEB`  ·  **MVP browser route:** `PATCH /candidate/profile`

> **Reclassified for the MVP browser Candidate portal (SPEC-DOC-07, accepted).** The
> Candidate portal is a browser portal, so this operation is served over the **Laravel
> session guard with CSRF protection on mutations**, per ADR-005 and
> `SECURITY_ARCHITECTURE.md` §1 — not a Sanctum bearer token. The heading above remains the
> canonical operation identifier and is the reserved `/api/v1` twin for when the versioned
> API is explicitly activated for a non-browser client; a future adapter calls the same
> Candidate domain Actions and Queries, so promotion is additive and requires no behavioural
> change. Authorization (`OWN`), the verified-email gate, field semantics, validation,
> collection sync behaviour, pending vocabularies, error codes, side effects, and audit below
> are unchanged and apply identically on either surface.

**Purpose:** Update own profile attributes and work preferences.

**Authentication:** Required, email verified.

**Authorization:** `OWN`.

**Request (all optional, partial update):** `headline`, `phone`, `summary`, `province_geographic_area_id`, `city_geographic_area_id`, `province`, `city`, `preferred_employment_type`, `preferred_workplace_mode`, `preferred_location_note`, `open_to_opportunities`.

**Validation:** Preference fields — `preferred_employment_type`, `preferred_workplace_mode` — are validated for **type, length, and structural validity only**. **No closed vocabulary is approved for them** (`DATABASE_SCHEMA.md`: `varchar`, `CHECK` pending; Part X item 7), so this contract asserts no enum membership and names no candidate values. Geography references must exist and be `active`; free-text geography accepted only as a fallback where no master area matches.

**Business Rules:**
- **`current_candidate_type` is not editable here.** Changing candidate category is a distinct, audited action because it interacts with eligibility and role synchronization (INV-028). Contract deferred — see the note at the end of Part III.
- Work preferences are availability signals only and are **never** an input to ranking or scoring. No ranking exists anywhere in this API.
- **`profile_completed_at` is not recomputed here — DEFERRED POLICY.** FR-CAN-003 requires candidates to complete a profile but defines **no** global completion formula and no exact required-field set, so none is invented. The column stays in the frozen schema and **may remain `null` indefinitely**; profile and collection CRUD function normally regardless (Part X item 8). Automatic computation is specified only once completion criteria are approved.

**Success Response:** `200 OK`, updated profile.

**Error Codes:** `VALIDATION_FAILED` (422) · `AUTH_EMAIL_NOT_VERIFIED` (403) · `CANDIDATE_PROFILE_REQUIRED` (422).

**Side Effects:** Profile row updated.

**Audit:** `candidate_profile_updated` with a redacted change summary.

**Notification / Outbox:** None.

**Idempotency:** Not required (naturally idempotent).

**Concurrency:** Last-write-wins on attributes; `If-Match` supported.

**Source Requirement:** FR-CAN-003 · INV-028

---

### PUT /api/v1/candidate/{collection}

*(Grouped contract — covers six candidate-owned repeatable profile collections.)*

**Surface:** `INERTIA_WEB`  ·  **MVP browser route:** `PUT /candidate/{collection}`

> **Reclassified for the MVP browser Candidate portal (SPEC-DOC-07, accepted).** The
> Candidate portal is a browser portal, so this operation is served over the **Laravel
> session guard with CSRF protection on mutations**, per ADR-005 and
> `SECURITY_ARCHITECTURE.md` §1 — not a Sanctum bearer token. The heading above remains the
> canonical operation identifier and is the reserved `/api/v1` twin for when the versioned
> API is explicitly activated for a non-browser client; a future adapter calls the same
> Candidate domain Actions and Queries, so promotion is additive and requires no behavioural
> change. Authorization (`OWN`), the verified-email gate, field semantics, validation,
> collection sync behaviour, pending vocabularies, error codes, side effects, and audit below
> are unchanged and apply identically on either surface.

**Covered URIs:**

| Collection | Read | Synchronize |
| --- | --- | --- |
| Education | `GET /api/v1/candidate/educations` | `PUT /api/v1/candidate/educations` |
| Work experience | `GET /api/v1/candidate/work-experiences` | `PUT /api/v1/candidate/work-experiences` |
| Organizations | `GET /api/v1/candidate/organizations` | `PUT /api/v1/candidate/organizations` |
| Certifications | `GET /api/v1/candidate/certifications` | `PUT /api/v1/candidate/certifications` |
| Professional links | `GET /api/v1/candidate/links` | `PUT /api/v1/candidate/links` |
| Skills | `GET /api/v1/candidate/skills` | `PUT /api/v1/candidate/skills` |

**Purpose:** Maintain the repeatable profile sections FR-CAN-003 requires — pendidikan, pengalaman, organisasi, sertifikasi, keterampilan, link profesional/portfolio.

**Why collection synchronization rather than per-row CRUD.** The frozen Stitch *Profil Saya* baseline edits each section as **one owned form with repeatable rows**, then saves. Per-row CRUD would force the client to diff its form against the server and emit N requests, where a partial failure leaves the profile half-saved and the candidate unable to tell which rows persisted. A single `PUT` is atomic, naturally idempotent, produces one audit event, and cannot orphan a child row. These are small, bounded, self-owned records with no independent lifecycle, no cross-references, and no per-row authorization — nothing requires a stable per-row URI. `skills` already used this pattern; the other five now match it.

**Authentication:** Required, email verified for writes.

**Authorization:** `OWN` for both methods. The parent `candidate_profile_id` is taken from the **authenticated actor** and never from the payload; a client cannot write into another candidate's profile by supplying an identifier.

**Request (`PUT`):** `items[]` — the **complete desired contents** of that collection.

| Field | Rule |
| --- | --- |
| `items[].id` | **Optional.** Present → update that existing row. Absent → create a new row. An `id` that is not an existing row of **this candidate's** collection is rejected |
| `items[]` other fields | Exactly the fields defined in `DATA_DICTIONARY.md` for the corresponding entity. No field outside the dictionary is accepted |
| `items: []` | An explicit empty array clears the collection. This is a valid, deliberate operation |

**Omission and removal semantics — stated explicitly:**

> **`PUT` replaces the entire collection.** A row whose `id` is **absent from `items[]` is deleted.** Omission is removal — never "leave unchanged". A client that sends a partial list will delete the rest, which is why this is `PUT` (full replacement) and never `PATCH`.

Clients must therefore read the collection, edit the whole set, and send it back. That is exactly what the profile form already does.

**Validation:**
- **Every row is validated before anything is written.** Row-level rules per collection: dates ordered where both present; `is_current = true` requires a null `end_date`; `education_level` **type/length and structural validity only — no closed level vocabulary is approved** (`DATABASE_SCHEMA.md`: `varchar`, `CHECK` pending; Part X item 7), so no level codes are fixed by this contract; `study_program_id` active or `study_program_name` free-text fallback; certification `expires_at` after `issued_at`; certification `document_id` owned by this candidate; `link_type` enum, allowed URL scheme, unique `url` within the candidate; `skill_id` active, no duplicate `skill_id`.
- Collection-level: no duplicate `id`; every supplied `id` belongs to this candidate and this collection; bounded array length.

**Business Rules:**
- **Atomic per collection. If any row fails validation, nothing is written** — no partial collection update, ever. The response reports every failing row by index so the form can highlight all errors at once rather than one per round trip.
- Rows are created, updated, and deleted in **one transaction**.
- Stable identifiers are returned for every resulting row, so the client can send them back on the next synchronization.
- **A deleted row is genuinely removed** — these are the candidate's own editable profile entries and carry no recruitment evidence. This is unlike `application_documents` snapshots (INV-032) and unlike append-only history (INV-016), neither of which is reachable from here.
- Deleting a certification does **not** delete the linked private document; `document_id` is a reference, and the document remains in `candidate_documents`.
- No entry influences eligibility, matching, ranking, or scoring. `score_summary` and `proficiency_level` are declarative and are **never** ranking inputs. **No ranking exists anywhere in this API.**

**Success Response:** `200 OK` with the complete resulting collection, each row carrying its identifier.

**Error Codes:** `VALIDATION_FAILED` (422 — `details.items[]` indexed by position) · `AUTH_EMAIL_NOT_VERIFIED` (403) · `NOT_FOUND` (404, an `id` outside this candidate's collection) · `DOCUMENT_NOT_OWNED` (403, certification linking).

**Side Effects:** Rows created, updated, and deleted within the one collection, in a single transaction.

**Audit:** One `candidate_profile_section_changed` entry per synchronization, naming the collection and summarizing counts created, updated, and removed. Row content is not audited in detail — it is the candidate's own data, and FR-AUD-001 does not require it.

**Notification / Outbox:** None.

**Idempotency:** **Naturally idempotent.** Re-sending an identical payload produces an identical result and no additional change. An `Idempotency-Key` is therefore not required.

**Concurrency:** Last-write-wins per collection. `If-Match` on the candidate profile version is supported for clients that need to detect a concurrent edit from another device.

**Source Requirement:** FR-CAN-003 · INV-028 · `API_SIZE_REVIEW.md` M-1

---

### POST /api/v1/candidate/verifications

**Surface:** `INERTIA_WEB`  ·  **MVP browser route:** `POST /candidate/verifications`

> **Reclassified for the MVP browser Candidate portal (SPEC-DOC-07, accepted).** The
> Candidate portal is a browser portal, so this operation is served over the **Laravel
> session guard with CSRF protection on mutations**, per ADR-005 and
> `SECURITY_ARCHITECTURE.md` §1 — not a Sanctum bearer token. The heading above remains the
> canonical operation identifier and is the reserved `/api/v1` twin for when the versioned
> API is explicitly activated for a non-browser client; a future adapter calls the same
> Candidate domain Actions and Queries, so promotion is additive and requires no behavioural
> change. Authorization (`OWN`), the verified-email gate, field semantics, validation,
> collection sync behaviour, pending vocabularies, error codes, side effects, and audit below
> are unchanged and apply identically on either surface.

> **IMPLEMENTATION BLOCKED — CANDIDATE VERIFICATION BUSINESS DECISION REQUIRED.**
> Reclassifying the HTTP surface does **not** authorize implementation. Part X item 1 leaves
> the alumni verification integration source unresolved, and with it which of
> `student_number`, `program_study_id`, `graduation_year` are mandatory. The verification
> source, required payload fields, matching rules, and any automatic transition to `VERIFIED`
> are **not invented here** and remain blocked until explicitly approved. The route and its
> surface are settled; the business rules are not.

**Purpose:** Request alumni or final-year-student verification (FR-CAN-004).

**Authentication:** Required, email verified.

**Authorization:** `OWN`.

**Request:** `verification_type` (`ALUMNI` \| `FINAL_YEAR_STUDENT`), plus `student_number`, `program_study_id`, `graduation_year` — **conditionally required depending on the verification source that has not yet been chosen**.

**Validation:** `verification_type` enum. Field-level requirements are **PENDING BUSINESS DECISION** (open question 1 — final alumni verification integration source: SSO, master-data sync, NIM plus comparison data, or a combination). The contract deliberately does not fix which fields are mandatory, and no integration client is specified.

**Business Rules:**
- Creates a `candidate_verifications` row with `status = PENDING`.
- Rejects when a PENDING verification of the same type already exists (`CANDIDATE_VERIFICATION_PENDING`).
- **A VERIFIED record of the matching type is the only basis for target-audience eligibility** (INV-028). Neither the role code nor `current_candidate_type` grants eligibility.
- Alumni verification is for identity and eligibility only — never premium status, ranking, or recommendation (FR-CAN-004).

**Success Response:** `201 Created` — the verification record with `status = PENDING`.

**Error Codes:** `VALIDATION_FAILED` (422) · `CANDIDATE_VERIFICATION_PENDING` (409) · `AUTH_EMAIL_NOT_VERIFIED` (403).

**Side Effects:** Verification row created. Paired read: `GET /api/v1/candidate/verifications`.

**Audit:** `candidate_verification_requested`.

**Notification / Outbox:** Notification to the reviewing unit where a manual path applies; candidate notified on outcome. Exact routing depends on open question 1.

**Idempotency:** Not required — the PENDING-uniqueness rule prevents duplicates.

**Concurrency:** Row lock on the candidate profile prevents two simultaneous PENDING rows of one type.

**Source Requirement:** FR-CAN-002, FR-CAN-004 · INV-028 · **Open question 1**

---

### GET /api/v1/candidate/documents

**Surface:** `INERTIA_WEB`  ·  **MVP browser route:** `GET /candidate/documents`

> **Reclassified for the MVP browser Candidate portal (SPEC-DOC-07, accepted).** The
> Candidate portal is a browser portal, so this operation is served over the **Laravel
> session guard with CSRF protection on mutations**, per ADR-005 and
> `SECURITY_ARCHITECTURE.md` §1 — not a Sanctum bearer token. The heading above remains the
> canonical operation identifier and is the reserved `/api/v1` twin for when the versioned
> API is explicitly activated for a non-browser client; a future adapter calls the same
> Candidate domain Actions and Queries, so promotion is additive and requires no behavioural
> change. Authorization (`OWN`), the verified-email gate, field semantics, validation,
> collection sync behaviour, pending vocabularies, error codes, side effects, and audit below
> are unchanged and apply identically on either surface.

**Purpose:** List the candidate's own private documents (FR-CAN-005).

**Authentication:** Required.

**Authorization:** `OWN` — scoped by `candidate_profile_id`. **Query-scoped, not merely Policy-checked**, so no other candidate's document can appear.

**Request:** Optional `document_type`, `include_archived` (boolean, default false). Sortable: `uploaded_at`, `display_name`.

**Validation:** Allow-listed filter and sort fields only.

**Business Rules:** Returns metadata only — id, type, display name, MIME, size, `uploaded_at`, `archived_at`, and whether the document is currently shared with any application. **Never returns `storage_reference` or any object-storage URL.**

**Success Response:** `200 OK`, paginated.

**Error Codes:** `UNAUTHENTICATED` (401).

**Side Effects:** None.

**Audit:** None for listing metadata. Audit applies to **download** (FR-AUD-001).

**Notification / Outbox:** None.

**Idempotency:** Safe method.

**Concurrency:** None.

**Source Requirement:** FR-CAN-005 · INV-010

---

### POST /api/v1/candidate/documents

**Surface:** `INERTIA_WEB`  ·  **MVP browser route:** `POST /candidate/documents`

> **Reclassified for the MVP browser Candidate portal (SPEC-DOC-07, accepted).** The
> Candidate portal is a browser portal, so this operation is served over the **Laravel
> session guard with CSRF protection on mutations**, per ADR-005 and
> `SECURITY_ARCHITECTURE.md` §1 — not a Sanctum bearer token. The heading above remains the
> canonical operation identifier and is the reserved `/api/v1` twin for when the versioned
> API is explicitly activated for a non-browser client; a future adapter calls the same
> Candidate domain Actions and Queries, so promotion is additive and requires no behavioural
> change. Authorization (`OWN`), the verified-email gate, field semantics, validation,
> collection sync behaviour, pending vocabularies, error codes, side effects, and audit below
> are unchanged and apply identically on either surface.

> **UPLOAD POLICY APPROVED AND FROZEN — 25 August 2026 (Part X item 9, CLOSED).**
> The MIME allowlist, the maximum file size, and the question of per-`document_type` variation
> were approved by the product owner and are frozen in the *Validation* and *Business Rules*
> sections below. FR-CAN-005 still requires MIME and size validation and neither control may
> ever be dropped; the approved values now fill what BRD/FSD deliberately left to this layer.
> **BRD and FSD are unchanged** — they require the controls, this contract states the values.
>
> **The operation is not yet routed.** `POST /candidate/documents` remains absent from
> `routes/web.php` until it is implemented. That is a delivery state, not a policy block, and
> it no longer gates any other candidate contract.

**Purpose:** Upload a private candidate document.

**Authentication:** Required, email verified.

**Authorization:** `OWN`.

**Request:** `multipart/form-data` — `file` (required), `document_type` (**required**; free-form `varchar` — see Validation), `display_name` (optional; defaults to a sanitized client filename).

**Validation:**
- **MIME validation and size validation are both MANDATORY — FR-CAN-005 requires them explicitly and neither may be dropped.** The client-declared `Content-Type` and the filename extension are **advisory only and are never trusted**; the **inspected** content type governs (`SECURITY_ARCHITECTURE.md` §5).
- **MIME allowlist — APPROVED AND FROZEN: `application/pdf` only.** Admission requires **both** signals to agree: (1) the **server-side inspected** MIME is `application/pdf`, **and** (2) the file content begins with the PDF signature `%PDF-`. If either check fails, or the inspected type is outside the allowlist, the upload is rejected — `UNSUPPORTED_MEDIA_TYPE` (415) for a type outside the allowlist, `DOCUMENT_TYPE_NOT_ALLOWED` (422) where the declared type, extension, and inspected content disagree. **No other media type is approved**: not DOC, DOCX, XLS, XLSX, ZIP, RAR, 7Z, SVG, images, audio, or video. Widening the allowlist requires a new security and business review.
- **`mime_type` is persisted as the server-inspected value.** The client-declared media type is never persisted as authoritative and is never echoed back on download.
- **Maximum file size — APPROVED AND FROZEN: 10 MiB, exactly `10,485,760` bytes.** This is a **single global limit applied to every candidate document**; it does **not** vary by `document_type`. **Application validation is the authoritative enforcement point.** Reverse-proxy and PHP runtime ceilings must be configured **above** this figure so an oversized request reaches the application and receives the frozen `PAYLOAD_TOO_LARGE` (413) envelope rather than a bare transport error (`DEPLOYMENT_ARCHITECTURE.md` §6). A transport ceiling is never the business limit.
- `document_type` present, and valid for **type and length only** — `varchar(64)`. **The document-type vocabulary remains OPEN and is NOT closed by this policy** (`DATABASE_SCHEMA.md`: `varchar`, `CHECK` pending; Part X item 7 — still open). No closed vocabulary, no `CHECK` constraint, and **no migration** are introduced. A controlled vocabulary can still be added later without changing this operation, its route, or the candidate-document feature.
- `display_name` optional, sanitized, at most 255 characters — see *Business Rules* for the sanitization requirements.

**Business Rules:**
- The object is stored under a **generated, opaque storage key**; the client filename is retained as display metadata only, so there is no path-traversal surface. **No segment of the client filename may influence the object-storage path**, and `storage_reference` is **never returned to the client** in any response.
- **Filename sanitization** produces `display_name`: strip path separators and traversal sequences to a bare basename; remove control, `NUL`, and newline characters; normalize Unicode and strip bidirectional override characters used to disguise an extension; truncate within `varchar(255)`; and fall back to a safe server-generated name when sanitization yields empty text. **Duplicate `display_name` values are permitted** — no uniqueness constraint exists and a repeated upload is legitimate.
- Uploads land in a quarantine prefix and are promoted after an asynchronous scan **where a scanner is configured**. FSD §7.6 and §10.1 make scanning conditional ("bila tersedia"); where none is configured the file is accepted and the gap is a recorded accepted risk, not a silent one. While quarantined the document cannot be shared (`DOCUMENT_SCAN_PENDING`).
- Files are **private by default**. No public URL is ever produced.
- **Frozen sequencing:** validate → authorize → inspect content → generate storage key → write object → database transaction creating the `candidate_documents` row and its audit entry → response. The upload happens **before** the metadata transaction, so a rolled-back transaction can only orphan an object (recoverable by cleanup), never leave a row pointing at a missing file. **A row is never created pointing at an object that was not successfully stored.** On persistence failure after a successful object write, perform **best-effort object cleanup** and leave the remainder to later orphan reconciliation. **No distributed-transaction abstraction is required or implied.**
- **Abuse control:** the candidate upload limiter in Part I §11.8 applies — 20 upload requests per rolling hour per authenticated candidate identity, `RATE_LIMITED` (429) with `Retry-After`. No storage quota applies at MVP; a quota is **DEFERRED**.
- **Malware scanning is not required for the PDF-only allowlist at MVP**, consistent with FSD §7.6 and §10.1 ("bila tersedia") and the accepted risk recorded in `SECURITY_ARCHITECTURE.md`. Where a scanner **is** configured, the quarantine-and-promote contract above applies unchanged. A scanner is a prerequisite for any widening of the allowlist beyond `application/pdf`.

**Success Response:** `201 Created` with document metadata and `Location`.

**Error Codes:** `VALIDATION_FAILED` (422) · `DOCUMENT_TYPE_NOT_ALLOWED` (422) · `PAYLOAD_TOO_LARGE` (413) · `UNSUPPORTED_MEDIA_TYPE` (415) · `DOCUMENT_SCAN_PENDING` (409, where a scanner workflow exists) · `DOCUMENT_SCAN_FAILED` (422, where a scanner rejects the file) · `RATE_LIMITED` (429, with `Retry-After`) · `AUTH_EMAIL_NOT_VERIFIED` (403) · `DOCUMENT_NOT_OWNED` (403, where ownership applies).

**No storage-failure error code exists and none is introduced.** A storage-layer failure returns the ordinary sanitized server-error envelope with its correlation id, and **never** leaks the bucket, the storage key, credentials, or backend exception detail.

**Side Effects:** Object stored; `candidate_documents` row created.

**Audit:** `document_uploaded`, carrying at most `document_type`, `size`, and the **inspected** `mime_type`, alongside the ordinary actor, object, and correlation fields. **Never** the file content or bytes, `storage_reference`, a signed URL, a storage credential, or any client secret material. `document_metadata_updated`, `document_archived`, and `document_access` are unchanged.

**Notification / Outbox:** None.

**Idempotency:** Not required. A duplicate upload creates a distinct document, which is legitimate.

**Concurrency:** None.

**Source Requirement:** FR-CAN-005 · FSD §7.6, §10.1 · INV-010

---

### PATCH /api/v1/candidate/documents/{document}

**Surface:** `INERTIA_WEB`  ·  **MVP browser route:** `PATCH /candidate/documents/{document}`

> **Reclassified for the MVP browser Candidate portal (SPEC-DOC-07, accepted).** The
> Candidate portal is a browser portal, so this operation is served over the **Laravel
> session guard with CSRF protection on mutations**, per ADR-005 and
> `SECURITY_ARCHITECTURE.md` §1 — not a Sanctum bearer token. The heading above remains the
> canonical operation identifier and is the reserved `/api/v1` twin for when the versioned
> API is explicitly activated for a non-browser client; a future adapter calls the same
> Candidate domain Actions and Queries, so promotion is additive and requires no behavioural
> change. Authorization (`OWN`), the verified-email gate, field semantics, validation,
> collection sync behaviour, pending vocabularies, error codes, side effects, and audit below
> are unchanged and apply identically on either surface.

**Purpose:** Update document metadata (display name, type).

**Authentication:** Required, email verified.

**Authorization:** `OWN`.

**Request:** `display_name`, `document_type` — both optional.

**Validation:** `document_type` type/length and structural validity — **no approved vocabulary to check membership against** (Part X item 7); `display_name` length.

**Business Rules:** **The stored file is never replaced through this route.** Replacing content would silently alter what a recruiter already reviewed; a new version is a new upload. Existing `application_documents` snapshots are unaffected by a metadata change (INV-032).

**Success Response:** `200 OK`.

**Error Codes:** `VALIDATION_FAILED` (422) · `NOT_FOUND` (404) · `DOCUMENT_NOT_OWNED` (403).

**Side Effects:** Metadata updated.

**Audit:** `document_metadata_updated`.

**Notification / Outbox:** None.

**Idempotency:** Naturally idempotent.

**Concurrency:** Last-write-wins.

**Source Requirement:** FR-CAN-005 · INV-032

---

### DELETE /api/v1/candidate/documents/{document}

**Surface:** `INERTIA_WEB`  ·  **MVP browser route:** `DELETE /candidate/documents/{document}`

> **Reclassified for the MVP browser Candidate portal (SPEC-DOC-07, accepted).** The
> Candidate portal is a browser portal, so this operation is served over the **Laravel
> session guard with CSRF protection on mutations**, per ADR-005 and
> `SECURITY_ARCHITECTURE.md` §1 — not a Sanctum bearer token. The heading above remains the
> canonical operation identifier and is the reserved `/api/v1` twin for when the versioned
> API is explicitly activated for a non-browser client; a future adapter calls the same
> Candidate domain Actions and Queries, so promotion is additive and requires no behavioural
> change. Authorization (`OWN`), the verified-email gate, field semantics, validation,
> collection sync behaviour, pending vocabularies, error codes, side effects, and audit below
> are unchanged and apply identically on either surface.

**Purpose:** Archive a private document.

**Authentication:** Required, email verified.

**Authorization:** `OWN`.

**Request:** None.

**Validation:** None.

**Business Rules:**
- **This is an archive, not a hard delete.** Sets `archived_at`. An archived document is excluded from selection for new applications.
- A document referenced by a **non-revoked** `application_documents` row can never be hard-deleted (INV-032). Its snapshot survives, so historical recruitment evidence is unaffected.
- Hard deletion exists only as an authorized, audited retention process, never as a user action.

**Success Response:** `204 No Content`.

**Error Codes:** `NOT_FOUND` (404) · `DOCUMENT_NOT_OWNED` (403) · `DOCUMENT_IN_USE` (409) if a hard delete is attempted by a privileged path.

**Side Effects:** `archived_at` set. Object retained.

**Audit:** `document_archived`.

**Notification / Outbox:** None.

**Idempotency:** Naturally idempotent.

**Concurrency:** None.

**Source Requirement:** FR-CAN-005 · INV-032 · FR-AUD-002

---

### GET /api/v1/candidate/documents/{document}/download

**Surface:** `INERTIA_WEB`  ·  **MVP browser route:** `GET /candidate/documents/{document}/download`

> **Reclassified for the MVP browser Candidate portal (SPEC-DOC-07, accepted).** The
> Candidate portal is a browser portal, so this operation is served over the **Laravel
> session guard with CSRF protection on mutations**, per ADR-005 and
> `SECURITY_ARCHITECTURE.md` §1 — not a Sanctum bearer token. The heading above remains the
> canonical operation identifier and is the reserved `/api/v1` twin for when the versioned
> API is explicitly activated for a non-browser client; a future adapter calls the same
> Candidate domain Actions and Queries, so promotion is additive and requires no behavioural
> change. Authorization (`OWN`), the verified-email gate, field semantics, validation,
> collection sync behaviour, pending vocabularies, error codes, side effects, and audit below
> are unchanged and apply identically on either surface.

**Purpose:** Authorized download of the candidate's own document.

**Authentication:** Required.

**Authorization:** `OWN` only. This route serves the candidate's own file; a recruiter reaches a shared document through `GET /api/v1/application-documents/{applicationDocument}/download` instead.

**Request:** None.

**Validation:** None.

**Business Rules:**
- Policy check → **audit write** → stream, or issue a signed URL with a lifetime measured in seconds, bound to this actor.
- **A durable public or pre-signed link is never returned.** A raw pre-signed URL bypasses the application on the actual fetch, which would make FR-AUD-001's download auditing impossible (ADR-007).
- Response carries `Content-Disposition: attachment` and `X-Robots-Tag: noindex`. Documents are never rendered inline in the application origin, so a crafted HTML or SVG upload cannot execute in a trusted origin.

**Success Response:** `200 OK` with the file stream, or `302` to a short-lived signed URL.

**Error Codes:** `NOT_FOUND` (404) · `DOCUMENT_NOT_OWNED` (403) · `DOCUMENT_SCAN_PENDING` (409).

**Side Effects:** None to business state.

**Audit:** **`document_access` — required.** Actor, document id, correlation ID. Denied attempts are audited too.

**Notification / Outbox:** None.

**Idempotency:** Safe method.

**Concurrency:** None.

**Source Requirement:** FR-CAN-005, FR-AUD-001 · INV-010 · ADR-007

---

> **Candidate type change — contract deliberately deferred.** Transitioning `FINAL_YEAR_STUDENT → ALUMNI` keeps the same user, the same candidate profile, and the entire application history (INV-028), and the audited action exists in the model. The *trigger* — self-declared, verification-driven, or administrative — depends on **open question 1**, so no endpoint is specified here rather than inventing one. `PATCH /api/v1/candidate/profile` deliberately excludes `current_candidate_type` so the decision is not pre-empted.

---

## Part IV — Company, Verification, and Partnership

### POST /api/v1/companies

**Surface:** `INERTIA_WEB`

**Purpose:** Create the company profile — step 3 of FR-ONB-001 (FR-ONB-002).

**Authentication:** Required, **email verified** — FR-ONB-001 forbids skipping verification.

**Authorization:** Recruiter role. The authenticated creator becomes the first active `COMPANY_ADMIN` `company_members` row; the company and initial membership are one atomic transaction.

**Request:** `name` (required), `organization_type_id`, `industry_id`, `website`, `official_email`, `official_phone`, `address`, `province_geographic_area_id`, `city_geographic_area_id`, `legal_identifier`, `logo` (upload reference).

**Validation:** `name` 2–200 chars. Everything else is **optional at DRAFT** and becomes mandatory at submit-verification (INV-030) — the two-phase requirement FR-ONB-002 implies. Geography references must exist and be active.

**Business Rules:**
- Creates `companies` with `verification_status = DRAFT` and one active `company_members` row for the creator.
- The first member's `company_role` is `COMPANY_ADMIN`, with the existing active membership representation. This is the closed D-1 decision. Subsequent member roles require explicit selection and have no implicit default; at least one active `COMPANY_ADMIN` must remain.
- `normalized_name` is computed server-side as a **duplicate-detection signal only**. FR-COMP-001 requires flag-and-review across five signals (normalized name, legal identifier, website domain, official email domain, phone) with merge by an authorized role. It is **not unique** and a similar name is never hard-rejected (INV-034).
- Where a potential duplicate is detected the response carries `meta.warnings` with **`COMPANY_DUPLICATE_REVIEW_SUGGESTED`** (approved as a non-error signal, 25 August 2026; `ERROR_CODES.md` §9); creation still succeeds and the matched company is never named.

**Success Response:** `201 Created` with the company and `Location`.

**Error Codes:** `VALIDATION_FAILED` (422) · `AUTH_EMAIL_NOT_VERIFIED` (403) · `COMPANY_ALREADY_EXISTS_FOR_USER` (409).

**Side Effects:** Company + membership rows.

**Audit:** `company_created`.

**Notification / Outbox:** None at DRAFT.

**Idempotency:** Not required.

**Concurrency:** Uniqueness of active membership per (company, user) is enforced.

**Source Requirement:** FR-ONB-001, FR-ONB-002, FR-COMP-001, FR-COMP-004 · INV-017, INV-030, INV-034 · **D-1 CLOSED by approved Product Owner decision**

---

### GET /api/v1/companies/{company}

**Surface:** `INERTIA_WEB`

**Purpose:** Read a company the actor is entitled to see.

**Authentication:** Required.

**Authorization:** `COMPANY_SCOPE` for recruiters (active membership, INV-017) · `ALLOW` read for Career Center · `READ_ONLY` for Auditor · `ALLOW` for Super Admin. Anyone else receives `404`, not `403` (`ERROR_CODES.md` §10).

**Request:** Optional `?include=documents,members,verification_history` (allow-listed).

**Validation:** Allow-listed include values.

**Business Rules:**
- Recruiters see their own company fully, including recruiter-visible review notes.
- **`internal_note` on any review is never returned to a recruiter** (FR-VAC-006, FR-ONB-004). It is visible only to Career Center, Auditor, and Super Admin.
- Partnership state is exposed as derived `mitra_kampus_active`, computed from an ACTIVE `partnerships` row within its period — **never** as a company status (INV-003, INV-020).

**Success Response:** `200 OK`.

**Error Codes:** `NOT_FOUND` (404) · `COMPANY_ASSOCIATION_FORBIDDEN` (403).

**Side Effects:** None.

**Audit:** None for ordinary reads.

**Notification / Outbox:** None.

**Idempotency:** Safe method.

**Concurrency:** None.

**Source Requirement:** FR-ONB-002, FR-COMP-003 · INV-003, INV-017, INV-020

---

### PATCH /api/v1/companies/{company}

**Surface:** `INERTIA_WEB`

**Purpose:** Edit the company profile while its status permits (FR-ONB-005).

**Authentication:** Required, email verified.

**Authorization:** `COMPANY_SCOPE` — active membership required.

**Request:** Any FR-ONB-002 field.

**Validation:** As for create.

**Business Rules:**
- Editable in `DRAFT` and `REVISION_REQUIRED`. In `PENDING_VERIFICATION`, `VERIFIED`, `REJECTED`, or `SUSPENDED` the response is `409 VACANCY_NOT_EDITABLE`-equivalent → `COMPANY_INVALID_TRANSITION`.
- **Editing never creates a second company record** (FR-ONB-005). The same row is corrected and resubmitted.
- `verification_status` is **not** writable here. Status changes only through named actions.

**Success Response:** `200 OK`.

**Error Codes:** `VALIDATION_FAILED` (422) · `COMPANY_INVALID_TRANSITION` (409) · `COMPANY_ASSOCIATION_FORBIDDEN` (403) · `STALE_VERSION` (409).

**Side Effects:** Company row updated.

**Audit:** `company_updated` with a redacted change summary.

**Notification / Outbox:** None.

**Idempotency:** Not required.

**Concurrency:** `If-Match` supported; two recruiters editing one company is realistic, so a stale write returns `409 STALE_VERSION`.

**Source Requirement:** FR-ONB-002, FR-ONB-005 · INV-017

---

### POST /api/v1/companies/{company}/documents

**Surface:** `INERTIA_WEB`

**Purpose:** Upload a company legal or supporting document (FR-ONB-002).

**Authentication:** Required, email verified.

**Authorization:** `COMPANY_SCOPE`.

**Request:** `multipart/form-data` — `file`, `document_type`, `document_number`, `issued_at`, `expires_at`.

**Validation:** Content-inspected MIME; size limit; `expires_at` after `issued_at`. **Which document types are mandatory per organization type is PENDING BUSINESS DECISION** (open question 2) — the contract requires *at least one* document before submit (INV-030) but fixes no type matrix.

**Business Rules:** Private storage, generated key, quarantine-and-promote as for candidate documents. Company documents are **never** publicly readable and are visible only to company members, Career Center reviewers, Auditor, and Super Admin.

**Success Response:** `201 Created`.

**Error Codes:** `VALIDATION_FAILED` · `DOCUMENT_TYPE_NOT_ALLOWED` · `PAYLOAD_TOO_LARGE` · `UNSUPPORTED_MEDIA_TYPE` · `COMPANY_ASSOCIATION_FORBIDDEN`.

**Verification evidence rule (resolves review finding Q-2).** A company legal document's removability depends entirely on whether it has ever formed part of a **submitted verification package**:

| Document state | Permitted action | Mechanism |
| --- | --- | --- |
| **Never included in any submitted verification package** — the company is still assembling draft data | May be removed or replaced outright | `DELETE /api/v1/companies/{company}/documents/{document}` |
| **Included in any submitted verification package** (the company has reached `PENDING_VERIFICATION` at least once with this document attached) | **Destructive deletion is forbidden.** The document is superseded by a replacement and retained as archived evidence | `POST /api/v1/companies/{company}/documents/{document}/supersede` |

**Why:** a Career Center verification decision is made against a specific set of documents. If that evidence could be deleted afterwards, the append-only `company_verification_reviews` trail would reference a decision whose basis no longer exists — defeating the reconstructability INV-016 exists to guarantee. **There is deliberately no generic destructive `DELETE` for submitted verification evidence.**

**Side Effects:** Object stored; `company_documents` row. Paired routes: `GET /api/v1/companies/{company}/documents`, `GET /api/v1/companies/{company}/documents/{document}/download` (Policy-checked and audited exactly like candidate downloads), `DELETE /api/v1/companies/{company}/documents/{document}` (**draft-only** — see the rule above), and `POST /api/v1/companies/{company}/documents/{document}/supersede` (see below).

**Audit:** `company_document_uploaded`; downloads audited as `document_access`.

**Notification / Outbox:** None.

**Idempotency:** Not required.

**Concurrency:** None.

**Source Requirement:** FR-ONB-002 · INV-030 · **Open question 2**

---

### DELETE /api/v1/companies/{company}/documents/{document}

**Surface:** `INERTIA_WEB`

**Purpose:** Remove a company legal document that has never formed part of a submitted verification package.

**Authentication:** Required, email verified. **Authorization:** `COMPANY_SCOPE` — active membership.

**Request:** None. **Validation:** None at transport level.

**Business Rules:**
- Permitted **only** when the document has never been included in a submitted verification package — that is, the company has never reached `PENDING_VERIFICATION` with this document attached.
- Otherwise → **`409 COMPANY_DOCUMENT_IS_VERIFICATION_EVIDENCE`**, and the response names `POST …/supersede` as the correct action.
- The stored object is removed together with its metadata row. This is a genuine deletion, valid precisely because no decision has ever been based on the document.

**Success Response:** `204 No Content`.

**Error Codes:** `COMPANY_DOCUMENT_IS_VERIFICATION_EVIDENCE` (409) · `NOT_FOUND` (404) · `COMPANY_ASSOCIATION_FORBIDDEN` (403).

**Side Effects:** Metadata row and stored object removed.

**Audit:** `company_document_deleted`.

**Notification / Outbox:** None.

**Idempotency:** Naturally idempotent.

**Concurrency:** Company row locked so a concurrent `submit-verification` cannot turn the document into evidence mid-delete.

**Source Requirement:** FR-ONB-002, FR-ONB-005 · INV-016 · `API_SIZE_REVIEW.md` Q-2

---

### POST /api/v1/companies/{company}/documents/{document}/supersede

**Surface:** `INERTIA_WEB`

**Purpose:** Replace a company legal document that is already verification evidence, without destroying it (FR-ONB-005 revision flow).

**Authentication:** Required, email verified. **Authorization:** `COMPANY_SCOPE` — active membership.

**Request:** `multipart/form-data` — `file` (required), `document_number`, `issued_at`, `expires_at`, `reason` (optional).

**Validation:** Content-inspected MIME; size limit; `expires_at` after `issued_at`. Same upload controls as the original document.

**Business Rules:**
- Permitted while the company is in a state where revision is allowed — `DRAFT` or `REVISION_REQUIRED`. In `PENDING_VERIFICATION` → `409 COMPANY_INVALID_TRANSITION`; a package under active review must not change beneath the reviewer.
- Atomically: stores the replacement as a **new** `company_documents` row of the same `document_type`; marks the previous row **archived and superseded**, recording which row replaced it; writes audit.
- **The superseded document is never deleted and remains downloadable** to Career Center, Auditor, and Super Admin, so any past verification decision stays reconstructable.
- The replacement enters the next submitted package; the superseded row does not.

**Success Response:** `200 OK` with both the new document and the superseded reference.

**Error Codes:** `COMPANY_INVALID_TRANSITION` (409) · `VALIDATION_FAILED` (422) · `DOCUMENT_TYPE_NOT_ALLOWED` (422) · `PAYLOAD_TOO_LARGE` (413) · `UNSUPPORTED_MEDIA_TYPE` (415) · `COMPANY_ASSOCIATION_FORBIDDEN` (403).

**Side Effects:** New document row; previous row archived and marked superseded; audit.

**Audit:** `company_document_superseded`, naming both rows.

**Notification / Outbox:** None.

**Idempotency:** **REQUIRED** — a retried upload must not create two replacements.

**Concurrency:** Company and document rows locked.

**Source Requirement:** FR-ONB-002, FR-ONB-005 · INV-016 · `API_SIZE_REVIEW.md` Q-2

---

### POST /api/v1/companies/{company}/submit-verification

**Surface:** `INERTIA_WEB`

**Purpose:** Submit the company for Career Center review (FR-ONB-003). **Named action — submit is an action, never a stored status.**

**Authentication:** Required, email verified.

**Authorization:** `COMPANY_SCOPE`.

**Request:** Empty body. Optional `note` for the reviewer.

**Validation:** None at transport level; everything is a business gate.

**Business Rules:**
- Legal transitions: `DRAFT → PENDING_VERIFICATION`, `REVISION_REQUIRED → PENDING_VERIFICATION` (FSD §8.2). Any other source status → `409 COMPANY_INVALID_TRANSITION`.
- **Completeness gate (INV-030):** `organization_type_id`, `industry_id`, `official_email`, `address`, province and city geography references, and **at least one** `company_documents` row must all be present. Missing items → `422 COMPANY_PROFILE_INCOMPLETE` with `details.missing`.
- Creates an append-only `company_verification_reviews` row with `action = SUBMIT`, `from_status`, `to_status` (INV-016).
- **Email failure never rolls back the submit** (FR-ONB-003.6, INV-015).

**Success Response:** `200 OK` with the company at `PENDING_VERIFICATION`, plus `meta.warnings` possibly carrying `EMAIL_DELIVERY_PENDING`.

**Error Codes:** `COMPANY_INVALID_TRANSITION` (409) · `COMPANY_PROFILE_INCOMPLETE` (422) · `COMPANY_DOCUMENT_REQUIRED` (422) · `COMPANY_ASSOCIATION_FORBIDDEN` (403) · `AUTH_EMAIL_NOT_VERIFIED` (403).

**Side Effects:** Status change + review row + audit + outbox, one transaction.

**Audit:** `company_submitted`.

**Notification / Outbox:** In-app notification to the Career Center queue; `email_outbox` rows to the recruiter and to Career Center per preference (FR-NOTIF-002).

**Idempotency:** **REQUIRED.** A double submit would append a duplicate review row and a duplicate notification.

**Concurrency:** Company row locked; the second concurrent submit sees `PENDING_VERIFICATION` and returns `409`.

**Source Requirement:** FR-ONB-001, FR-ONB-003 · FSD §8.2 · INV-015, INV-016, INV-029, INV-030

---

### POST /api/v1/companies/{company}/verify

**Surface:** `INERTIA_WEB`

**Purpose:** Career Center approves a company (FR-ONB-004).

**Authentication:** Required.

**Authorization:** Career Center staff or manager, **or Super Admin** (approved reconciliation, 25 August 2026 — `AUTHORIZATION_MATRIX.md` §4.4 already granted Super Admin `ALLOW`; this section previously named Career Center alone). **Not** available to recruiters, HR admins, or Auditor.

> **An active member of the company under review may never review it** (approved decision, 25 August 2026). The prohibition follows the reviewer, not the role code: it applies to Career Center staff, to Career Center managers, and to Super Admin alike. A reviewer who holds an active `company_members` row for that company receives `403 AUTH_FORBIDDEN`.

**Request:** Optional `recruiter_visible_note`, optional `internal_note`.

**Validation:** Note lengths.

**Business Rules:**
- Legal transition `PENDING_VERIFICATION → VERIFIED` only (FSD §8.2).
- Sets `verified_at`; appends `company_verification_reviews` with `action = VERIFY` (append-only — **history is never overwritten**, INV-016).
- **Verification is not partnership.** Becoming VERIFIED creates no partnership and requires none; a VERIFIED non-partner company may create vacancies immediately (INV-003, FR-COMP-003, FR-ONB-006).
- Reason fields optional for VERIFY (INV-029 requires them only for REQUEST_REVISION / REJECT / SUSPEND).

**Success Response:** `200 OK`.

**Error Codes:** `COMPANY_INVALID_TRANSITION` (409) · `AUTH_FORBIDDEN` (403).

**Side Effects:** Status + review row + audit + outbox.

**Audit:** `company_verified` with actor and reviewer note reference.

**Notification / Outbox:** Notification and email to the company's active members.

**Idempotency:** **REQUIRED.**

**Concurrency:** Row lock; the second reviewer receives `409`.

**Source Requirement:** FR-ONB-004, FR-ONB-006, FR-COMP-003 · FSD §8.2 · INV-003, INV-016, INV-029

---

### POST /api/v1/companies/{company}/request-revision

**Surface:** `INERTIA_WEB`

**Purpose:** Career Center returns a company for correction (FR-ONB-005).

**Authentication:** Required.

**Authorization:** Career Center staff or manager, **or Super Admin** (approved reconciliation, 25 August 2026). An **active member of the company under review may never review it**, whatever their role — `403 AUTH_FORBIDDEN`.

**Request:** `reason_category` (**required**), `recruiter_visible_note` (**required**), `internal_note` (optional).

**Validation:** Both required fields present and non-empty.

**Business Rules:**
- Legal transition `PENDING_VERIFICATION → REVISION_REQUIRED`.
- **Reason is mandatory (INV-029, FR-ONB-004: "Revision/rejection/suspension wajib memiliki reason").** Missing → `422 REVIEW_REASON_REQUIRED`.
- `internal_note` is **never** returned to the recruiter.
- The recruiter then edits the **same** company and resubmits — no new record (FR-ONB-005).

**Success Response:** `200 OK`.

**Error Codes:** `REVIEW_REASON_REQUIRED` (422) · `COMPANY_INVALID_TRANSITION` (409) · `AUTH_FORBIDDEN` (403).

**Side Effects:** Status + review row + audit + outbox.

**Audit:** `company_revision_requested`.

**Notification / Outbox:** Notification and email to company members with the recruiter-visible note only.

**Idempotency:** **REQUIRED.**

**Concurrency:** Row lock.

**Source Requirement:** FR-ONB-004, FR-ONB-005 · FSD §8.2 · INV-016, INV-029

---

### POST /api/v1/companies/{company}/reject

**Surface:** `INERTIA_WEB`

**Purpose:** Career Center rejects a company verification (FR-ONB-004).

**Authentication:** Required. **Authorization:** Career Center staff or manager, **or Super Admin** (approved reconciliation, 25 August 2026). An **active member of the company under review may never review it**, whatever their role — `403 AUTH_FORBIDDEN`.

**Request:** `reason_category` (required), `recruiter_visible_note` (required), `internal_note` (optional).

**Validation:** Required fields present.

**Business Rules:** `PENDING_VERIFICATION → REJECTED` only. **REJECTED and SUSPENDED are distinct outcomes and never equivalent** — rejection concerns a verification that did not pass; suspension concerns an already-verified company whose access is withdrawn. Reason mandatory (INV-029).

**Success Response:** `200 OK`. **Error Codes:** `REVIEW_REASON_REQUIRED` (422) · `COMPANY_INVALID_TRANSITION` (409) · `AUTH_FORBIDDEN` (403).

**Side Effects:** Status + review row + audit + outbox. **Audit:** `company_rejected`. **Notification / Outbox:** To company members.

**Idempotency:** **REQUIRED.** **Concurrency:** Row lock.

**Source Requirement:** FR-ONB-004, FR-COMP-002 · FSD §8.2 · INV-016, INV-029

---

### POST /api/v1/companies/{company}/suspend

**Surface:** `INERTIA_WEB`

**Purpose:** Career Center suspends an active company (FR-ONB-004).

**Authentication:** Required. **Authorization:** Career Center staff or manager, **or Super Admin** (approved reconciliation, 25 August 2026). An **active member of the company under review may never review it**, whatever their role — `403 AUTH_FORBIDDEN`.

**Request:** `reason_category` (required), `recruiter_visible_note` (required), `internal_note` (optional).

**Validation:** Required fields present.

**Business Rules:** `VERIFIED → SUSPENDED` only — suspension applies to a company that is already active/verified. Sets `suspended_at`. A suspended company cannot create vacancies (`COMPANY_NOT_VERIFIED` on that path). **Existing applications, history, consent, and offers are untouched** — suspension withdraws access, it does not delete recruitment history.

**Success Response:** `200 OK`. **Error Codes:** `REVIEW_REASON_REQUIRED` (422) · `COMPANY_INVALID_TRANSITION` (409) · `AUTH_FORBIDDEN` (403).

**Side Effects:** Status + review row + audit + outbox. **Audit:** `company_suspended`. **Notification / Outbox:** To company members.

**Idempotency:** **REQUIRED.** **Concurrency:** Row lock.

**Source Requirement:** FR-ONB-004, FR-COMP-002 · FSD §8.2 · INV-016, INV-029

---

### POST /api/v1/companies/{company}/restore

**Surface:** `INERTIA_WEB`

**Purpose:** Career Center restores a suspended company (FR-ONB-004).

**Authentication:** Required. **Authorization:** Career Center staff or manager, **or Super Admin** (approved reconciliation, 25 August 2026). An **active member of the company under review may never review it**, whatever their role — `403 AUTH_FORBIDDEN`.

**Request:** Optional `recruiter_visible_note`, optional `internal_note`.

**Validation:** None mandatory — INV-029 requires a reason for REQUEST_REVISION, REJECT, and SUSPEND, not RESTORE.

**Business Rules:** `SUSPENDED → VERIFIED` only. The full path `VERIFIED → SUSPENDED → VERIFIED` is representable and fully reconstructable from the append-only review trail (INV-016).

**Success Response:** `200 OK`. **Error Codes:** `COMPANY_INVALID_TRANSITION` (409) · `AUTH_FORBIDDEN` (403).

**Side Effects:** Status + review row + audit + outbox. **Audit:** `company_restored`. **Notification / Outbox:** To company members.

**Idempotency:** **REQUIRED.** **Concurrency:** Row lock.

**Source Requirement:** FR-ONB-004 · FSD §8.2 · INV-016

---

### GET /api/v1/career-center/company-verifications

**Surface:** `INERTIA_WEB`

**Purpose:** The Career Center verification queue (FR-REP-002).

**Authentication:** Required. **Authorization:** Career Center · Auditor `READ_ONLY` · Super Admin.

**Request:** Filters `status` (default `PENDING_VERIFICATION`), `submitted_from`, `submitted_to`, `q` (company name). Sortable: `submitted_at`, `name`.

**Validation:** Allow-listed filters and sort fields only.

**Business Rules:** Query-scoped to companies in reviewable states. Returns queue metadata and the latest review summary — **not** applicant data and not company documents inline.

**Success Response:** `200 OK`, paginated. **Error Codes:** `AUTH_FORBIDDEN` (403).

**Side Effects:** None. **Audit:** None. **Notification / Outbox:** None.

**Idempotency:** Safe method. **Concurrency:** None.

**Source Requirement:** FR-ONB-004, FR-REP-002

---

### GET /api/v1/companies/{company}/verification-history

**Surface:** `INERTIA_WEB`

**Purpose:** Full append-only verification trail.

**Authentication:** Required. **Authorization:** `COMPANY_SCOPE` (recruiter, **without** `internal_note`) · Career Center, Auditor, Super Admin (full).

**Request:** None. **Validation:** None.

**Business Rules:** Returns every `company_verification_reviews` row in order — action, from/to status, reviewer, timestamp, recruiter-visible note. **`internal_note` is filtered out for recruiters.** History is append-only and is never edited or removed (INV-016).

**Success Response:** `200 OK`. **Error Codes:** `NOT_FOUND` (404) · `COMPANY_ASSOCIATION_FORBIDDEN` (403).

**Side Effects:** None. **Audit:** None. **Notification / Outbox:** None.

**Idempotency:** Safe method. **Concurrency:** None.

**Source Requirement:** FR-ONB-004, FR-ONB-005 · INV-016

---

### POST /api/v1/companies/{company}/members

**Surface:** `INERTIA_WEB`

**Purpose:** Invite or add a company member (FR-COMP-004).

**Authentication:** Required, email verified. **Authorization:** `COMPANY_SCOPE`, Company Admin capability.

**Rate limit:** **20 attempts per hour per Company Admin identity** (approved MVP decision, 25 August 2026). The window counts attempts, not successes, so a probing loop is throttled the same as a working one. Exceeding it returns `RATE_LIMITED` (429) with `Retry-After`.

**Request:** `email` (required), `company_role` (required — `COMPANY_ADMIN` \| `COMPANY_RECRUITER`).

**Validation:** Email format; enum membership.

**Business Rules:**
- **The invitee must already hold an account.** An address with no `users` row is **NOT SUPPORTED at MVP** (approved decision, 25 August 2026): no membership row is created, **no email is sent**, and no pending-invitation record exists anywhere — there is no invitation entity in the schema, and none is introduced.
- **An unknown address returns the generic `NOT_FOUND` envelope**, identical to the response for a member id outside this company. The response must not distinguish "no such account" from "not visible to you".
- **Accepted residual (R-9).** An authorized Company Admin can still infer account existence from `201`/`409` versus `404`; that inference is inherent to admitting a known address and refusing an unknown one. It is **accepted for MVP** by approved decision of 25 August 2026 and is bounded by the eight mitigations in `SECURITY_ARCHITECTURE.md` §9 R-9 — the acceptance is void if any of them is removed. It must never widen to a public or authentication surface: registration, login, and forgot-password answer identically for known and unknown identities.
- The **unknown-user invitation lifecycle is DEFERRED** — see Part X item 10. Nothing about a future pre-registration invite is implied by this contract.
- An invited member must verify their email/account through the standard security mechanism before gaining access (FR-COMP-004). **No temporary password is issued.**
- `company_role` is explicitly selected for every member added after the creator; there is no implicit default role.
- Rejects a duplicate **active** membership (`MEMBER_ALREADY_ACTIVE`, INV-017).
- Access requires an **active** membership; a revoked one grants nothing.
- Revoke, demote, deactivate, or leave operations must not leave zero active `COMPANY_ADMIN` memberships; otherwise they return `MEMBER_LAST_ADMIN` (closed D-1).

**Success Response:** `201 Created` with the membership row. **There is no `202` outcome at MVP**, because an invitation email is never the only immediate effect: either a membership is created, or the request is `NOT_FOUND`.

**Error Codes:** `VALIDATION_FAILED` (422) · `MEMBER_ALREADY_ACTIVE` (409) · `MEMBER_LAST_ADMIN` (409) · `NOT_FOUND` (404, unknown account) · `RATE_LIMITED` (429) · `COMPANY_ASSOCIATION_FORBIDDEN` (403).

**Side Effects:** One active membership row; outbox row. Paired routes: `GET /api/v1/companies/{company}/members`, `PATCH /api/v1/companies/{company}/members/{member}` (change role), `DELETE /api/v1/companies/{company}/members/{member}` (revoke — sets `revoked_at`, never deletes; FR-COMP-004 "penghapusan membership tidak menghapus audit/history").

**Audit:** `company_member_added` / `_role_changed` / `_revoked`.

**Notification / Outbox:** Invitation email.

**Idempotency:** Recommended on create.

**Concurrency:** Active-membership uniqueness enforced by a conditional constraint.

**Source Requirement:** FR-COMP-004 · INV-017 · **D-1 CLOSED by approved Product Owner decision**

---

### POST /api/v1/partnerships

**Surface:** `INERTIA_WEB`

**Purpose:** Record a campus–company partnership (FR-COMP-003).

**Authentication:** Required. **Authorization:** Career Center only. Recruiters cannot create or edit partnerships.

**Request:** `company_id` (required), `partnership_type` (required), `agreement_number`, `start_date` (required), `end_date`, `campus_pic`, `company_pic`, `document_reference`, `notes`.

**Validation:** Company exists; `end_date` after `start_date`; enum membership.

**Business Rules:**
- **Partnership is entirely separate from verification.** Creating one changes no company status; a company need not be VERIFIED to have a partnership recorded, and a VERIFIED company needs no partnership to post vacancies (INV-003, INV-020, FR-ONB-006).
- `Mitra Kampus` is **derived** from an ACTIVE partnership within its period. It is never persisted as a company status and never appears in the vacancy-creation gate.
- Rejects an overlapping active period for the same company (`PARTNERSHIP_OVERLAPPING_PERIOD`).

**Success Response:** `201 Created`.

**Error Codes:** `VALIDATION_FAILED` (422) · `PARTNERSHIP_OVERLAPPING_PERIOD` (409) · `AUTH_FORBIDDEN` (403).

**Side Effects:** Partnership row. Paired routes: `GET /api/v1/partnerships`, `GET /api/v1/partnerships/{partnership}`, `PATCH /api/v1/partnerships/{partnership}`, `POST /api/v1/partnerships/{partnership}/activate` and `POST /api/v1/partnerships/{partnership}/end`.

**Partnership status is read from the company resource, not a dedicated endpoint.** `GET /api/v1/companies/{company}` already returns `mitra_kampus_active`, derived from an ACTIVE partnership within its period. A second endpoint computing the same derived value would invite the two to drift — the failure mode INV-020 exists to prevent (`API_SIZE_REVIEW.md` D-1).

**Audit:** `partnership_created` / `_updated` / `_activated` / `_ended`.

**Notification / Outbox:** Expiry-warning email to Career Center and PIC (FR-NOTIF-002).

**Idempotency:** Recommended on activate and end.

**Concurrency:** Row lock on activate/end.

**Source Requirement:** FR-COMP-003, FR-REP-002 · INV-003, INV-020

---

## Part V — Vacancy

### POST /api/v1/companies/{company}/vacancies

**Surface:** `INERTIA_WEB`

**Purpose:** Create a company vacancy — `COMPANY_EMPLOYMENT` or `INTERNSHIP` (FR-VAC-001, FR-VAC-003).

**Authentication:** Required, email verified.

**Authorization:** `COMPANY_SCOPE` — active `company_members` row required (INV-017). **The global `SUPER_ADMIN` role alone confers no company-authoring capability** (PO decision VA-4, approved — CLOSED); see `AUTHORIZATION_MATRIX.md` §4.5 footnote 36.

**Request:** `vacancy_type` (`COMPANY_EMPLOYMENT` \| `INTERNSHIP`), `title`, `description`, `responsibilities`, `employment_type`, `workplace_mode`, geography references and `location` fallback, `openings_count`, `minimum_education`, `experience_requirement`, `salary_min`/`salary_max`/`salary_currency`, `target_audience`, `application_method`, `external_ats_url`, `open_at`, `close_at`, `requirements[]`, `screening_questions[]`.

**Validation:**
- `target_audience` ∈ `PUBLIC` \| `ALUMNI_ONLY` \| `FINAL_YEAR_AND_ALUMNI` \| `INTERNAL` — **exactly four values** (INV-006). `Mahasiswa Aktif` and `Fresh Graduate` are not values.
- `application_method` ∈ `IN_PORTAL` \| `EXTERNAL_ATS`.
- `external_ats_url` required when `EXTERNAL_ATS`; **`https` scheme only** (FSD §9.1.5).
- `close_at` after `open_at` (FSD §9.1.4); both required before leaving DRAFT.
- `requirements[]` entries carry exactly one typed value per `requirement_type` — `education_level`, `study_program_id`, `skill_id`, `minimum_years_experience`, or `value_text`.
- **Each `requirements[]` entry must state `required` explicitly as a boolean** (PO decision VA-5, approved — CLOSED). `true` means the candidate must satisfy or provide that requirement under its existing downstream semantics; `false` means it is optional under those same semantics. **Omitted → `422 VALIDATION_FAILED`.** There is no server default, and neither `true` nor `false` is ever supplied silently.
- **Each inline `screening_questions[]` entry must state `required` and `active` explicitly as booleans** (PO decision VA-3). There is no server default for either flag on a new question.
- **Salary requirement is PENDING BUSINESS DECISION** (open question 3). Fields are nullable and no mandatory rule is enforced. `SALARY_REQUIRED` is reserved but unused.

**Business Rules:**
- **Authoring is a company capability. Career Center is `DENY` on this operation** (final ruling, 24 August 2026). Company vacancy ownership and authoring remain with active, verified company members. FSD §3.2's phrase "atas nama sesuai kewenangan" is **not** read as an implicit grant: delegated posting, if ever introduced, must be an explicit separately authorized capability, scoped to a named company, and separately audited — never an implicit consequence of holding the Career Center role. Career Center's responsibilities remain company verification, vacancy moderation, partnership management, and permitted monitoring and reporting.
- **Company verification gate (INV-002, FR-ONB-006): `companies.verification_status` must be `VERIFIED`.** Otherwise `403 VACANCY_COMPANY_NOT_VERIFIED`. The company row is **locked** for this check so two concurrent creations cannot both pass a gate that is changing.
- **Partnership is never part of this gate** (INV-003). A VERIFIED non-partner company creates vacancies normally.
- Ownership XOR (INV-018): `ownership_type = COMPANY`, `company_id` set from the path, **`organizational_unit_id` must be absent**. A client-supplied `organizational_unit_id` is rejected.
- Created at `current_status = DRAFT`. **`SUBMITTED`/`DIAJUKAN` are never stored** (INV-004, FR-VAC-004).
- Creates `vacancy_versions` version 1 as a full logical snapshot (FR-VAC-007).

**Success Response:** `201 Created` with the vacancy at DRAFT and `Location`.

**Error Codes:** `VACANCY_COMPANY_NOT_VERIFIED` (403) · `COMPANY_ASSOCIATION_FORBIDDEN` (403) · `VALIDATION_FAILED` (422) · `VACANCY_TARGET_AUDIENCE_INVALID` (422) · `VACANCY_EXTERNAL_ATS_URL_REQUIRED` / `_INVALID` (422) · `VACANCY_CLOSE_BEFORE_OPEN` (422) · `VACANCY_OWNERSHIP_INVALID` (422).

**Side Effects:** Vacancy + requirements + screening questions + version 1 snapshot, one transaction.

**Audit:** `vacancy_created`.

**Notification / Outbox:** None at DRAFT.

**Idempotency:** Recommended.

**Concurrency:** Company row locked for the verification gate.

**Source Requirement:** FR-VAC-001, FR-VAC-002, FR-VAC-003, FR-ONB-006 · INV-002, INV-003, INV-004, INV-006, INV-017, INV-018 · **Open question 3**

---

### POST /api/v1/hr/vacancies

**Surface:** `INERTIA_WEB`

**Purpose:** Create a campus vacancy — `CAMPUS_EMPLOYMENT` (FR-HR-001, FR-HR-002).

**Authentication:** Required, email verified.

**Authorization:** `HR_ADMIN` (Admin Kepegawaian) only. Recruiters and Career Center cannot create campus vacancies.

**Request:** As above, plus `organizational_unit_id` (**required**).

**Validation:**
- `organizational_unit_id` required and active — FR-HR-002 lists unit/fakultas/bagian among the minimum campus fields.
- **`application_method` must be `IN_PORTAL`.** `EXTERNAL_ATS` → `422 VACANCY_EXTERNAL_ATS_NOT_ALLOWED_FOR_CAMPUS` (INV-005, FR-HR-003). The API must not even offer External ATS for campus vacancies.
- Four target audiences only.

**Business Rules:**
- Ownership XOR (INV-018): `ownership_type = CAMPUS`, `organizational_unit_id` required, **`company_id` must be absent**.
- **No workforce request or approval chain is required** (FR-HR-001). Admin Kepegawaian may create, save as draft, schedule, or publish directly.
- **No company verification gate applies** — that gate is for company-owned vacancies.
- Campus vacancies are **not moderated**: `PENDING_REVIEW`, `REVISION_REQUIRED`, `APPROVED`, and `REJECTED` are unreachable (INV-018). Legal statuses are `DRAFT`, `SCHEDULED`, `PUBLISHED`, `CLOSED`, `EXPIRED`, `SUSPENDED` (FSD §8.4).
- Version 1 snapshot created.

**Success Response:** `201 Created` at DRAFT.

**Error Codes:** `VACANCY_EXTERNAL_ATS_NOT_ALLOWED_FOR_CAMPUS` (422) · `VACANCY_OWNERSHIP_INVALID` (422) · `VALIDATION_FAILED` (422) · `AUTH_FORBIDDEN` (403).

**Side Effects:** Vacancy + children + version 1.

**Audit:** `vacancy_created`.

**Notification / Outbox:** None at DRAFT.

**Idempotency:** Recommended.

**Concurrency:** None significant.

**Source Requirement:** FR-HR-001, FR-HR-002, FR-HR-003, FR-VAC-002 · INV-004, INV-005, INV-006, INV-018

---

### PATCH /api/v1/vacancies/{vacancy}

**Surface:** `INERTIA_WEB`

**Purpose:** Edit a vacancy in an editable status (FR-VAC-007).

**Authentication:** Required, email verified.

**Authorization:** `COMPANY_SCOPE` for company vacancies · `CAMPUS_SCOPE` for campus vacancies. **The global `SUPER_ADMIN` role alone confers no company-authoring capability** (PO decision VA-4); a Super Admin who also holds an ACTIVE membership of that company edits **as that member**. **Career Center is `DENY`** — it moderates vacancy content, it never authors or edits it (final ruling, 24 August 2026). A moderator who could also edit the submission would be reviewing their own work.

**Request:** Any editable attribute, plus **optional** `requirements[]`.

> **`screening_questions[]` is NOT part of this request (PO decision VA-2, approved — CLOSED).** Screening questions are mutated **only** through their own frozen routes: `GET`, `POST /vacancies/{vacancy}/screening-questions` and `PATCH /vacancies/{vacancy}/screening-questions/{question}`. There is no `DELETE`. This operation never performs a competing screening-question synchronization; a `screening_questions` key in the payload is an unsupported field and is rejected by the ordinary unsupported-field convention (`VALIDATION_FAILED`), with **no new error code**. Inline `screening_questions[]` on **create** is unaffected and remains supported.

**Validation:** As for create, for every attribute present.

**`requirements[]` semantics (PO decision VA-1, approved — CLOSED):**

| Payload | Meaning |
| --- | --- |
| `requirements` **omitted** | The existing requirement collection is **preserved unchanged**. Nothing is read, written, or reordered |
| `requirements` **present** | The array is the **complete desired collection**. The stored collection is **fully replaced** by it — this is replacement, not an incremental merge, and no per-row identity is matched |
| `requirements: []` | The collection is **cleared** — every existing requirement row is removed |

Entries are validated exactly as on create: frozen `requirement_type` membership, **exactly one typed value per type** (`chk_vacancy_requirements_typed_value`), and **an explicit boolean `required` on every entry** (PO decision VA-5 — omitted is `422 VALIDATION_FAILED`, with no server default in either direction). **No individual vacancy-requirement CRUD endpoint exists or is introduced** — a requirement is only ever written through its parent vacancy.

**VA-5 changes nothing else.** The requirement-type vocabulary, the typed-value mapping, the VA-1 atomic replacement semantics, the B-5 submit-completeness question, and candidate eligibility and application logic are all untouched by it.

**Business Rules:**
- Editable in `DRAFT` and `REVISION_REQUIRED` (company), `DRAFT` and `SCHEDULED` (campus). Otherwise `409 VACANCY_NOT_EDITABLE`.
- **Editing never creates a new vacancy** (FR-VAC-007). The same row is revised.
- **Every accepted edit appends a `vacancy_versions` snapshot** so the before/after state FR-VAC-007 requires is reconstructable. Versions are append-only (INV-016).
- **`application_method` may not change from `IN_PORTAL` to `EXTERNAL_ATS` while applications exist** → `409 VACANCY_HAS_APPLICATIONS` (INV-024). Allowing it would strand application rows on an external vacancy. The guard runs **inside** the update transaction, before any mutation: a blocked transition leaves `application_method`, `external_ats_url`, every other attribute and the requirement collection unchanged, appends **no** version, and writes **no** `vacancy_updated` audit entry. The reverse transition `EXTERNAL_ATS` → `IN_PORTAL` is **not** blocked by INV-024.
- `current_status` is not writable here.
- **The whole edit is one atomic transaction** in this order: scoped lookup with the vacancy row locked → editable-status check → `If-Match` / stale-version check → INV-024 application-method guard → parent attribute mutation → requirement replacement **if `requirements[]` was supplied** → version allocation → full post-update snapshot → audit. A failure at any step rolls back **everything**: the parent stays as it was, the requirement collection stays as it was, no version is appended and no audit row is written. In particular a **stale `If-Match` is rejected before any destructive requirement synchronization**.
- **One accepted PATCH appends exactly one `vacancy_versions` row**, whatever combination of attributes and `requirements[]` it carried. A **requirements-only** PATCH is a fully accepted edit and appends exactly one version like any other.
- **The version snapshot represents the final post-synchronization state**: the replacement collection when `requirements[]` was supplied, and the preserved existing collection when it was omitted.

**Success Response:** `200 OK` with the new version number.

**Error Codes:** `VACANCY_NOT_EDITABLE` (409) · `VACANCY_HAS_APPLICATIONS` (409) · `VALIDATION_FAILED` (422) · `STALE_VERSION` (409) · `AUTH_FORBIDDEN` (403).

**Side Effects:** Vacancy updated + new version snapshot.

**Audit:** `vacancy_updated` with the version number.

**Notification / Outbox:** None.

**Idempotency:** Not required.

**Concurrency:** `If-Match` on version; stale write → `409 STALE_VERSION`.

**Source Requirement:** FR-VAC-007 · INV-016, INV-024 · PO decisions **VA-1** (requirement synchronization), **VA-2** (screening questions excluded) and **VA-5** (explicit `required` per requirement), approved 25 August 2026

---

### POST /api/v1/vacancies/{vacancy}/submit-review

**Surface:** `INERTIA_WEB`

**Purpose:** Submit a company vacancy for Career Center moderation (FR-VAC-004). **Submit is an action.**

**Authentication:** Required, email verified. **Authorization:** `COMPANY_SCOPE`.

**Request:** Empty body; optional `note`.

**Validation:** None at transport level.

**Business Rules:**
- `DRAFT → PENDING_REVIEW` or `REVISION_REQUIRED → PENDING_REVIEW` (FSD §8.3). Otherwise `409 VACANCY_INVALID_TRANSITION`.
- **Applies to company vacancies only.** A campus vacancy → `409 VACANCY_MODERATION_NOT_APPLICABLE` (INV-018).
- Re-checks the company gate: still `VERIFIED` (INV-002). The company row is re-read at submit; a company that lost verification after the vacancy was drafted cannot submit.
- **Completeness is enforced here and only here (PO decision B-5, approved — CLOSED).** `DRAFT` and `REVISION_REQUIRED` may be incomplete while editing; the gate runs against the **currently persisted** vacancy, never against the request body, and mutates nothing while validating.

**B-5 — submit completeness (CLOSED):**

| Required category | Stable identifier in `details.missing` |
| --- | --- |
| Position title | `title` |
| Description | `description` |
| Qualifications | `qualifications` |
| Employment / job type | `employment_type` |
| Location | `location` |
| Work arrangement | `workplace_mode` |
| Headcount / openings | `openings_count` |
| Minimum education | `minimum_education` |
| Experience | `experience_requirement` |
| Opening date | `open_at` |
| Closing date | `close_at` |
| Target audience | `target_audience` |
| Application method | `application_method` |

- **Dates:** `open_at` and `close_at` are both required, and `close_at` must be strictly after `open_at`. A missing date answers `422 VACANCY_DATES_REQUIRED`; an ordering failure answers `422 VACANCY_CLOSE_BEFORE_OPEN`. These specific codes remain authoritative over the generic completeness code.
- **Conditional:** `application_method = EXTERNAL_ATS` requires a valid **https** `external_ats_url` → `422 VACANCY_EXTERNAL_ATS_URL_REQUIRED` / `422 VACANCY_EXTERNAL_ATS_URL_INVALID`. `IN_PORTAL` requires no URL.
- **Salary is OPTIONAL** and is never part of this gate. B-5 closes nothing about salary presentation policy, which stays open (Part X item 3).
- **Zero is a legal cardinality** for every optional collection: study-program requirements, skill requirements, additional qualification or document requirements, and screening questions. **No minimum-one rule exists for any of them and none may be introduced.** Requirement rows that *do* exist still obey their frozen typed value and their explicit `required` boolean (VA-5).
- Any other missing category answers `422 VACANCY_PROFILE_INCOMPLETE` with `details.missing` carrying the **stable semantic identifiers** above — never internal column names beyond those identifiers, and never a partial or ordering-dependent list.
- **A failed submit changes nothing:** no status transition, no `SUBMIT` moderation-review row, no `vacancy_submitted` audit entry, and no notification or outbox row.
- Appends `vacancy_moderation_reviews` with `action = SUBMIT` (INV-016).
- **`PENDING_REVIEW` is the stored status. `SUBMITTED`/`DIAJUKAN` never exist** (INV-004).

**Success Response:** `200 OK` at `PENDING_REVIEW`.

**Error Codes:** `VACANCY_INVALID_TRANSITION` (409) · `VACANCY_MODERATION_NOT_APPLICABLE` (409) · `VACANCY_COMPANY_NOT_VERIFIED` (403) · `VACANCY_PROFILE_INCOMPLETE` (422) · `VACANCY_DATES_REQUIRED` (422) · `VACANCY_CLOSE_BEFORE_OPEN` (422) · `VACANCY_EXTERNAL_ATS_URL_REQUIRED` (422) · `VACANCY_EXTERNAL_ATS_URL_INVALID` (422) · `COMPANY_ASSOCIATION_FORBIDDEN` (403) · `AUTH_FORBIDDEN` (403).

**Side Effects:** Status + moderation review row + audit + outbox.

**Audit:** `vacancy_submitted`.

**Notification / Outbox:** Career Center moderation queue notification; recruiter confirmation email.

**Idempotency:** **REQUIRED.**

**Concurrency:** Vacancy row locked.

**Source Requirement:** FR-VAC-004, FR-VAC-005, FR-VAC-006 · FSD §8.3 · INV-002, INV-004, INV-016, INV-018 · PO decision **B-5** (submit completeness), approved 25 August 2026

---

### POST /api/v1/vacancies/{vacancy}/approve

**Surface:** `INERTIA_WEB`

**Purpose:** Career Center approves a company vacancy (FR-VAC-006).

**Authentication:** Required. **Authorization:** Company-vacancy moderators — `CAREER_CENTER_STAFF`, `CAREER_CENTER_MANAGER`, `SUPER_ADMIN` (PO decision B-3, approved — CLOSED). **Conflict of interest: a moderator holding an ACTIVE `company_members` row for the owning company may not moderate that company's vacancy** → `403 AUTH_FORBIDDEN`. Super Admin uses the identical lifecycle checks, reason rules, history rules, audit attribution and notification behaviour — the global role is never a bypass of a source-status rule.

**Request:** Optional `recruiter_visible_note`, `internal_note`.

**Validation:** Note lengths.

**Business Rules:**
- **Approve target is deterministic (PO decision B-1, approved — CLOSED).** From `PENDING_REVIEW`, evaluated against `open_at` / `close_at` at execution time:

| Condition | Result |
| --- | --- |
| `now < open_at` | **`SCHEDULED`** — `published_at` stays `null`; the scheduler publishes when `open_at` is reached |
| `open_at <= now < close_at` | **`PUBLISHED`** — publication happens in this same business transaction; `published_at` set exactly once |
| `now >= close_at` | **Approval must not succeed.** The vacancy stays `PENDING_REVIEW`; answer `409 VACANCY_INVALID_TRANSITION`. No review row, no audit, no notification |

- **`APPROVED` remains part of the frozen status vocabulary and stays in the enum and the schema `CHECK`, but the MVP company moderation flow never emits it.** Approval resolves directly to `SCHEDULED` or `PUBLISHED`.
- **The recruiter never separately publishes** (PO decision B-4). Where approval yields `PUBLISHED`, publication is part of this operation; where it yields `SCHEDULED`, only the scheduler publishes.
- Sets `published_at` **only** when the vacancy actually becomes PUBLISHED. `published_at` is the Time-to-Fill start point (INV-013) and must never be set speculatively.
- Re-checks the company gate: still `VERIFIED` (INV-002).
- Appends a moderation review with `action = APPROVE`.
- Campus vacancy → `409 VACANCY_MODERATION_NOT_APPLICABLE`.

**Success Response:** `200 OK` with the resulting status.

**Error Codes:** `VACANCY_INVALID_TRANSITION` (409) · `VACANCY_MODERATION_NOT_APPLICABLE` (409) · `AUTH_FORBIDDEN` (403).

**Side Effects:** Status (+ `published_at` if published) + review row + audit + outbox.

**Audit:** `vacancy_approved`, plus `vacancy_published` when it publishes immediately.

**Notification / Outbox:** Recruiter notification and email (FR-NOTIF-002).

**Idempotency:** **REQUIRED.**

**Concurrency:** Row lock; second approver → `409`.

**Source Requirement:** FR-VAC-005, FR-VAC-006 · FSD §8.3 · INV-013, INV-016, INV-018 · PO decisions **B-1** (approve target), **B-3** (moderation authority), **B-4** (publish actor), approved 25 August 2026

---

### POST /api/v1/vacancies/{vacancy}/request-revision

**Surface:** `INERTIA_WEB`

**Purpose:** Career Center returns a company vacancy for revision (FR-VAC-006).

**Authentication:** Required. **Authorization:** Company-vacancy moderators — `CAREER_CENTER_STAFF`, `CAREER_CENTER_MANAGER`, `SUPER_ADMIN` (PO decision B-3). **A moderator with an ACTIVE membership of the owning company may not moderate that company's vacancy** → `403 AUTH_FORBIDDEN`. Recruiters (`COMPANY_ADMIN`, `COMPANY_RECRUITER`) never moderate; `AUDITOR` is read-only.

**Request:** `reason_category` (**required**), `recruiter_visible_note` (**required**), `internal_note` (optional).

**Validation:** Both required fields present.

**Business Rules:** `PENDING_REVIEW → REVISION_REQUIRED`. **Reason category and recruiter-visible note are mandatory** (FR-VAC-006, INV-029) → `422 REVIEW_REASON_REQUIRED` if missing. `internal_note` is never visible to the recruiter. The recruiter edits the **same** vacancy and resubmits (FR-VAC-007).

**Success Response:** `200 OK`. **Error Codes:** `REVIEW_REASON_REQUIRED` (422) · `VACANCY_INVALID_TRANSITION` (409) · `VACANCY_MODERATION_NOT_APPLICABLE` (409) · `AUTH_FORBIDDEN` (403).

**Side Effects:** Status + review row + audit + outbox. **Audit:** `vacancy_revision_requested`. **Notification / Outbox:** Recruiter notification and email carrying the recruiter-visible note only.

**Idempotency:** **REQUIRED.** **Concurrency:** Row lock.

**Source Requirement:** FR-VAC-006, FR-VAC-007 · INV-016, INV-029

---

### POST /api/v1/vacancies/{vacancy}/reject

**Surface:** `INERTIA_WEB`

**Purpose:** Career Center rejects a company vacancy (FR-VAC-006).

**Authentication:** Required. **Authorization:** Company-vacancy moderators — `CAREER_CENTER_STAFF`, `CAREER_CENTER_MANAGER`, `SUPER_ADMIN` (PO decision B-3). **A moderator with an ACTIVE membership of the owning company may not moderate that company's vacancy** → `403 AUTH_FORBIDDEN`. Recruiters (`COMPANY_ADMIN`, `COMPANY_RECRUITER`) never moderate; `AUDITOR` is read-only.

**Request:** `reason_category` (required), `recruiter_visible_note` (required), `internal_note` (optional).

**Validation:** Required fields present.

**Business Rules:** `PENDING_REVIEW → REJECTED`. Reason mandatory (INV-029). Campus vacancies are not moderated.

**Success Response:** `200 OK`. **Error Codes:** `REVIEW_REASON_REQUIRED` (422) · `VACANCY_INVALID_TRANSITION` (409) · `VACANCY_MODERATION_NOT_APPLICABLE` (409) · `AUTH_FORBIDDEN` (403).

**Side Effects:** Status + review row + audit + outbox. **Audit:** `vacancy_rejected`. **Notification / Outbox:** Recruiter notification and email.

**Idempotency:** **REQUIRED.** **Concurrency:** Row lock.

**Source Requirement:** FR-VAC-005, FR-VAC-006 · INV-016, INV-029

---

### POST /api/v1/vacancies/{vacancy}/publish

**Surface:** `INERTIA_WEB`

**Purpose:** Publish an approved or scheduled vacancy, or publish a campus vacancy directly (FR-VAC-005, FR-HR-004).

> **COMPANY VACANCIES — THIS OPERATION IS NOT USER-INVOCABLE AT MVP (PO decision B-4, approved — CLOSED).**
> A company vacancy publishes through exactly two paths and no other: **APPROVE inside its active window** (publication happens in the approval transaction), or the **system scheduler** when a `SCHEDULED` vacancy reaches `open_at`. There is **no recruiter publish, no Career Center manual publish, and no Super Admin manual publish** — the browser surface exposes no company publish route, so a company vacancy can never bypass moderation.
> The operation is **retained in this inventory** because the campus flow (`DRAFT → PUBLISHED`, `SCHEDULED → PUBLISHED`, FR-HR-004, FSD §8.4) still requires it; that path is not implemented in this phase. An internal reusable `PublishVacancy` Action may back both approval-time and scheduled publication, but it grants no independent authorization path.

**Request:** Empty body.

**Validation:** None at transport level.

**Business Rules:**
- Company: `SCHEDULED → PUBLISHED`, **driven by the scheduler only** (B-4). `APPROVED → PUBLISHED` remains in the frozen vocabulary but is unreachable in the MVP company flow, which never emits `APPROVED` (B-1). Campus: `DRAFT → PUBLISHED` or `SCHEDULED → PUBLISHED` (FSD §8.3, §8.4).
- **Sets `published_at` if not already set.** This is the Time-to-Fill anchor (INV-013) and is set exactly once.
- Scheduled publication also occurs automatically when `open_at` is reached, driven by the scheduler dispatching a queued job — not by this endpoint. Both paths converge on the same Action.

**Success Response:** `200 OK` at PUBLISHED.

**Error Codes:** `VACANCY_INVALID_TRANSITION` (409) · `VACANCY_DATES_REQUIRED` (422) · `AUTH_FORBIDDEN` (403).

**Side Effects:** Status + `published_at` + audit + outbox.

**Audit:** `vacancy_published`.

**Notification / Outbox:** Recruiter/owner notification and email.

**Idempotency:** **REQUIRED** — a double publish must not move `published_at` and corrupt Time-to-Fill.

**Concurrency:** Row lock; `published_at` written once.

**Source Requirement:** FR-VAC-005, FR-VAC-008, FR-HR-004 · FSD §8.3, §8.4 · INV-013 · PO decision **B-4** (publish actor), approved 25 August 2026

---

### Automatic vacancy expiry (O-7) — system operation, no endpoint

**Surface:** none. **This is not an HTTP operation and has no route**, so no actor of any role invokes it. It is a scheduler-driven system transition (FR-VAC-008, "Scheduler menandai `EXPIRED` setelah `close_at`"; FSD §8.3).

**Rule (PO decision O-7, approved — CLOSED):**

| Source status | Condition | Result |
| --- | --- | --- |
| `PUBLISHED` | `now >= close_at` | **`EXPIRED`** |
| `PUBLISHED` | `now < close_at` | unchanged — still active |
| `SCHEDULED` · `SUSPENDED` · `CLOSED` · `EXPIRED` · any other | any | **unchanged — never auto-expired by this operation** |

**`close_at` is an exclusive end boundary.** `now < close_at` is still active; `now == close_at` and `now > close_at` both reach expiry eligibility. This is the same boundary B-1 uses to refuse approval at or after `close_at`, and the same one B-2 uses to resolve a restore to `CLOSED`.

**Scope and interaction with other decisions:**
- **Only `PUBLISHED` company vacancies expire here.** A `SUSPENDED` vacancy past `close_at` is **not** expired by this operation — **B-2 remains authoritative**: an explicit restore at or after `close_at` resolves to `CLOSED`.
- **`EXPIRED` is terminal for the publication lifecycle.** No restore, reopen or un-expire transition exists or is introduced.
- **Campus vacancies are out of scope for this phase.** FSD requires campus expiry ultimately; it is wired with the Campus Vacancy lifecycle, not here.

**Side Effects:** `current_status → EXPIRED` and nothing else. Specifically: `published_at` is **preserved** (INV-013), `closed_at` is **not written** — expiry is not a close — `suspended_at` is left as it stands, applications and their history are **untouched**, and **no `vacancy_moderation_reviews` row and no `vacancy_versions` snapshot are appended**, because expiry is neither a moderation decision nor an authored revision.

**Applications:** existing applications and their history are retained in full and continue their own lifecycle. An expired vacancy is past its active period, so no new application may be accepted against it.

**Audit:** `vacancy_expired`, written by the **system**: the actor is recorded through the existing no-actor audit representation (`actor_user_id` null), exactly as scheduled publication does. **No synthetic or impersonated user identity is invented.**

**Notification / Outbox:** **NONE.** FSD v1.1 does not define vacancy expiry as a notification trigger, so no `email_outbox` row and no in-app notification is created solely because a vacancy expired.

**Concurrency:** the scheduler locks each vacancy row and **re-checks status and `close_at` after acquiring the lock**, so two concurrent runs cannot both transition the same vacancy. The operation is **idempotent**: a repeated execution finds nothing eligible and produces no duplicate transition and no duplicate audit entry.

**Operational cadence is an implementation detail, not a business SLA.** Eligibility is defined by `now >= close_at`; how often the job runs affects only latency.

**Source Requirement:** FR-VAC-008 · FSD §8.3 · INV-013 · PO decision **O-7**, approved 25 August 2026

---

### POST /api/v1/vacancies/{vacancy}/close

**Surface:** `INERTIA_WEB`

**Purpose:** Close a published vacancy manually.

**Authentication:** Required. **Authorization:** two distinct paths, and the distinction matters. **(a) The owner** — an ACTIVE member of the owning company under `COMPANY_SCOPE`, or the `CAMPUS_SCOPE` owner — closing their own published vacancy. **This is an ownership capability, not moderation**, so the conflict-of-interest rule does not apply to it. **(b) A moderator** — `CAREER_CENTER_STAFF`, `CAREER_CENTER_MANAGER` or `SUPER_ADMIN` (PO decision B-3), subject to the conflict-of-interest rule: an ACTIVE member of the owning company may not close it *as a moderator*, though they may still close it as the owner under (a).

**Request:** Optional `note`. **Validation:** None.

**Business Rules:** `PUBLISHED → CLOSED`, setting `closed_at`. Automatic expiry (`PUBLISHED → EXPIRED` after `close_at`) is a scheduler-driven job, not this endpoint (FR-VAC-008). **Closing does not delete or alter existing applications**; in-flight applications continue their lifecycle.

**Success Response:** `200 OK`. **Error Codes:** `VACANCY_INVALID_TRANSITION` (409) · `AUTH_FORBIDDEN` (403).

**Side Effects:** Status transition `PUBLISHED → CLOSED` · `closed_at` set to the execution time · **exactly one `vacancy_moderation_reviews` row** with `action = CLOSE`, `from_status = PUBLISHED`, `to_status = CLOSED` and `reviewer_user_id` = the authenticated actor who executed the close · audit · outbox.

> **CLOSE lifecycle-history semantics (PO decision M-1, approved — CLOSED).** Both authorization paths append the **same single** history row, and the row records **whoever executed the action**: the company member on an owner close, the Career Center or Super Admin actor on a moderator close. The physical column name `reviewer_user_id` does **not** require the actor to be Career Center personnel — the same is already true of the `SUBMIT` row, which only a company owner ever writes.
>
> This row is append-only lifecycle evidence and nothing more. It does **not** convert an owner close into moderation, grant any moderation authority, or alter the conflict-of-interest rule, B-3, or the owner-close authorization above. Exactly one CLOSE row exists per accepted close; a refused close appends none.

**Audit:** `vacancy_closed` on both paths. **Notification / Outbox:** Owner notification.

**Idempotency:** **REQUIRED.** **Concurrency:** Row lock.

**Source Requirement:** FR-VAC-005, FR-VAC-008 · FSD §8.3, §8.4 · PO decisions **B-3** (moderation authority) and **M-1** (close history semantics), approved 25 August 2026

---

### POST /api/v1/vacancies/{vacancy}/suspend

**Surface:** `INERTIA_WEB`

**Purpose:** Suspend a published vacancy (FR-VAC-006).

**Authentication:** Required. **Authorization:** Company vacancies — `CAREER_CENTER_STAFF`, `CAREER_CENTER_MANAGER`, `SUPER_ADMIN` (PO decision B-3), with the same conflict-of-interest rule: an ACTIVE member of the owning company may not moderate it → `403 AUTH_FORBIDDEN`. Campus vacancies — HR_ADMIN.

**Request:** `reason_category` (required), `recruiter_visible_note` (required), `internal_note` (optional).

**Validation:** Required fields present.

**Business Rules:** `PUBLISHED → SUSPENDED`. Reason mandatory for company-vacancy suspension (INV-029). A suspended vacancy accepts no new applications; existing applications and history are untouched. `published_at` is never rewritten by a suspension.

**Paired route `POST /api/v1/vacancies/{vacancy}/restore` — restore target is deterministic (PO decision B-2, approved — CLOSED):**

| Condition at restore time | Result |
| --- | --- |
| `now < close_at` | **`PUBLISHED`** |
| `now >= close_at` | **`CLOSED`**, with `closed_at` set to the restore execution time |

- Restore **never** returns `APPROVED` and **never** returns `SCHEDULED`.
- The **original `published_at` is preserved** — restore never rewrites it (INV-013).
- Restore revises the same vacancy row; it never creates a replacement, and existing applications and history are untouched.
- Restore appends a `RESTORE` moderation review and audits `vacancy_restored`. **A restore that resolves to `CLOSED` emits `vacancy_restored` only** — no second `vacancy_closed` event, because no frozen contract requires one for this path.
- This governs explicit RESTORE only. Automatic expiry is a separate system operation (O-7, CLOSED) that never touches a `SUSPENDED` vacancy — see *Automatic vacancy expiry* above.

**Success Response:** `200 OK`. **Error Codes:** `REVIEW_REASON_REQUIRED` (422) · `VACANCY_INVALID_TRANSITION` (409) · `AUTH_FORBIDDEN` (403).

**Side Effects:** Status + review row + audit + outbox. **Audit:** `vacancy_suspended` / `vacancy_restored`. **Notification / Outbox:** Owner notification and email.

**Idempotency:** **REQUIRED.** **Concurrency:** Row lock.

**Source Requirement:** FR-VAC-005, FR-VAC-006 · FSD §8.3 · INV-016, INV-029 · PO decisions **B-2** (restore target) and **B-3** (moderation authority), approved 25 August 2026

---

### GET /api/v1/vacancies

**Surface:** `INERTIA_WEB`

**Purpose:** Scoped vacancy list for authenticated owners and moderators.

**Authentication:** Required.

**Authorization:** **Query-scoped, never merely Policy-checked.** Recruiter → own company's vacancies (active membership). HR_ADMIN → campus vacancies. Career Center → company vacancies for moderation. Auditor → read-only within permitted scope. Super Admin → all. A vacancy outside scope is absent from results and returns `404` on direct read.

**Request:** Filters `status`, `vacancy_type`, `target_audience`, `application_method`, `company_id`, `organizational_unit_id`, `open_from`, `close_to`, `q`. Sortable: `created_at`, `published_at`, `close_at`, `title`.

**Validation:** Allow-listed filters and sort fields only. An unlisted sort field → `422`.

**Business Rules:** Returns owner-visible fields including moderation state and, for Career Center and above, `internal_note` references. Recruiters never see `internal_note`.

**Success Response:** `200 OK`, paginated. **Error Codes:** `UNAUTHENTICATED` (401) · `VALIDATION_FAILED` (422).

**Side Effects:** None. **Audit:** None. **Notification / Outbox:** None.

**Idempotency:** Safe method. **Concurrency:** None.

**Source Requirement:** FR-VAC-005, FR-REP-001, FR-REP-003 · FSD §3.3 · INV-017

---

### GET /api/v1/vacancies/{vacancy}

**Surface:** `INERTIA_WEB`

**Purpose:** Owner/moderator vacancy detail, including moderation and version history references.

**Authentication:** Required. **Authorization:** As for the list. Out-of-scope → `404`.

**Request:** Optional `?include=requirements,screening_questions,stages,moderation_history,versions`.

**Validation:** Allow-listed include values.

**Business Rules:** Full owner view. `internal_note` filtered for recruiters. Paired routes: `GET /api/v1/vacancies/{vacancy}/versions` (append-only snapshots, FR-VAC-007) and `GET /api/v1/vacancies/{vacancy}/moderation-history` (append-only review trail, INV-016).

**Success Response:** `200 OK`. **Error Codes:** `NOT_FOUND` (404) · `AUTH_FORBIDDEN` (403).

**Side Effects:** None. **Audit:** None. **Notification / Outbox:** None.

**Idempotency:** Safe method. **Concurrency:** None.

**Source Requirement:** FR-VAC-005, FR-VAC-006, FR-VAC-007 · INV-016

---

### GET /api/v1/public/vacancies

**Surface:** `VERSIONED_API`

**Purpose:** Public vacancy discovery (FSD §4.1). Backs the SSR-rendered public listing at browser route `GET /lowongan` (`Web\PublicVacancyController@index`, Inertia component `public/VacancyList`) — a technical routing choice (no URL shape is frozen by FSD/Stitch, only the terminology and screens are), mirroring the canonical Stitch screen names (`design/stitch/public/daftar-lowongan`). The web controller consumes `ListPublicVacancies`/`PublicVacancyScope` directly — never a loopback call to this API route.

**Authentication:** **None.** Optional — an authenticated candidate receives eligibility hints, never additional records.

**Authorization:** `PUBLIC`. Rate-limited per IP.

**Request:** Filters `q`, `vacancy_type`, `employment_type`, `workplace_mode`, `province_geographic_area_id`, `city_geographic_area_id`, `study_program_id`, `industry_id`, `company_id`, `target_audience`. Pagination: page-based, or `?cursor=` for deep traversal. Sortable: `published_at` (default, descending), `close_at`, `title`.

**Validation:** Allow-listed filters and sort fields only.

**Business Rules:**
- **Returns only vacancies that are `PUBLISHED` and within `open_at`…`close_at`, AND whose owning company is `VERIFIED` (PD-1, approved — CLOSED).** DRAFT, PENDING_REVIEW, REVISION_REQUIRED, APPROVED, SCHEDULED, REJECTED, CLOSED, EXPIRED, and SUSPENDED are never exposed, and neither is a `PUBLISHED`, in-window vacancy whose company is not currently `VERIFIED`. See *Public visibility and company verification (PD-1)* below.
- **`INTERNAL` audience vacancies are excluded from public results entirely.** `ALUMNI_ONLY` and `FINAL_YEAR_AND_ALUMNI` may be listed as discoverable, but applying is gated by verified eligibility at submit time (INV-028) — visibility is not permission.
- **Never returned:** moderation notes of any kind, `internal_note`, company documents, applicant data or counts, recruiter identities or contact details, `created_by`, or any private field. A public payload leaking applicant volume would disclose a company's hiring position.
- Company data limited to a public summary — name, logo, industry, city, and derived `mitra_kampus_active`. **Partnership (`mitra_kampus_active`) is never part of the visibility predicate** — only `verification_status` gates visibility (PD-1); a VERIFIED non-partner company's vacancies remain fully public.

**Success Response:** `200 OK`, paginated. **Error Codes:** `VALIDATION_FAILED` (422) · `RATE_LIMITED` (429).

**Side Effects:** None. **Audit:** None. **Notification / Outbox:** None.

**Idempotency:** Safe method. **Concurrency:** Cacheable with a short TTL, invalidated on publish, close, suspend, and expire. **No authorization-sensitive record is cached** (ADR-006).

**Source Requirement:** FR-VAC-002, FR-VAC-005 · FSD §4.1, §10.2, §10.4 · INV-006, INV-028 · PO decision **PD-1**, approved 26 August 2026

---

### Public visibility and company verification (PD-1)

**This is not an HTTP operation.** It documents the shared predicate both `GET /api/v1/public/vacancies` and `GET /api/v1/public/vacancies/{slug}` apply, identically and exclusively — neither route implements or weakens it independently.

**Rule (PO decision PD-1, approved 26 August 2026 — CLOSED):** a `COMPANY`-owned vacancy is publicly discoverable only when **all** of the following hold simultaneously:

| Condition | Source |
| --- | --- |
| `ownership_type = COMPANY` | This phase is company-vacancy discovery only; campus is a separate future phase |
| `current_status = PUBLISHED` | FR-VAC-005 |
| `open_at <= now` | FSD §4.1, §10.2 — independently enforced here, never assumed from the B-4 scheduled-publication job |
| `now < close_at` | Same exclusive boundary O-7 uses for expiry eligibility |
| `target_audience <> INTERNAL` | INV-006 |
| the owning company's `verification_status = VERIFIED` | **PD-1** |

**Why this decision was needed:** the frozen company-suspend operation (`POST /api/v1/companies/{company}/suspend`) states only that a suspended company cannot *create* new vacancies — it says nothing about the public visibility of vacancies that company already published before suspension. Left unresolved, a company suspended for cause (e.g. a fraud complaint) could keep an already-`PUBLISHED` vacancy fully public and discoverable. PD-1 closes that gap by making public visibility itself conditional on the owning company's *current* verification state, re-evaluated on every read — not a one-time check at publish time.

**Partnership is explicitly not part of this predicate.** `mitra_kampus_active` (derived from an ACTIVE `partnerships` row) has no bearing on visibility in either direction — a VERIFIED non-partner company's vacancies are exactly as publicly visible as a VERIFIED partner company's (RULE-004, PD-1). Activating or ending a partnership never changes what is publicly visible.

**No cascading side effect.** Evaluating PD-1 is a pure read filter. A company transitioning to a non-`VERIFIED` state (`SUSPENDED`, or any state other than `VERIFIED`) never mutates, on its own:
- the vacancy's `current_status` — it remains exactly what it was (typically `PUBLISHED`);
- `vacancy_moderation_reviews` — no row is appended;
- any vacancy lifecycle audit event — none is written;
- `applications`, their history, offers, or consents — all untouched.

**Restoration is symmetric and automatic, without being a vacancy transition.** If the company later returns to `VERIFIED`, an existing vacancy becomes publicly visible again automatically — the moment the read predicate next evaluates true — **provided it still independently satisfies every other condition** (`PUBLISHED`, within the date window, non-`INTERNAL`). This is **not** a republish, a restore, a reapprove, or any other vacancy-state action; no vacancy row is touched and no moderation or lifecycle event is written. If the vacancy reached `EXPIRED`, `CLOSED`, `SUSPENDED`, or any other non-public state while the company was non-`VERIFIED`, restoring the company to `VERIFIED` does **not** revive it — that vacancy remains hidden exactly as any other vacancy in that state would.

**Failure disclosure.** On direct detail lookup, a `PUBLISHED`, in-window, non-`INTERNAL` vacancy whose company fails the `VERIFIED` condition returns the identical **`404 VACANCY_NOT_PUBLIC`** as every other failed visibility condition (§10) — never a distinguishable error, and never `403`, which would confirm the row's existence to an anonymous caller.

**Source Requirement:** FSD §4.1, §10.2 · PO decision **PD-1**, approved 26 August 2026 (recorded as Part X item 13 below)

---

### GET /api/v1/public/vacancies/{slug}

**Surface:** `VERSIONED_API`

**Purpose:** Public vacancy detail, SEO-indexable through Inertia SSR (ADR-017). Browser route `GET /lowongan/{slug}` (`Web\PublicVacancyController@show`, Inertia component `public/VacancyDetail`) renders the same predicate through `GetPublicVacancy`/`PublicVacancyScope` directly, with the identical public-not-found behaviour described below.

**Authentication:** None. **Authorization:** `PUBLIC`.

**Request:** None. **Validation:** Slug format.

**Business Rules:**
- Same visibility rule as the listing, including the company-verification condition (PD-1) — see *Public visibility and company verification (PD-1)* above. A non-public vacancy returns **`404 VACANCY_NOT_PUBLIC`**, never `403` — a `403` would confirm the vacancy exists.
- Returns public content plus public company summary, requirements, and — for `EXTERNAL_ATS` vacancies — the fact that applying happens externally. **The external URL is returned only through the external-apply start endpoint**, which records the event and applies the FR-EXT-001 warning; the raw URL is not published in the detail payload as a bare link for crawlers to follow.
- Screening questions are **not** exposed publicly; they are returned to an authenticated, eligible candidate during the apply flow.

**Success Response:** `200 OK`. **Error Codes:** `NOT_FOUND` / `VACANCY_NOT_PUBLIC` (404) · `RATE_LIMITED` (429).

**Side Effects:** None. **Audit:** None. **Notification / Outbox:** None.

**Idempotency:** Safe method. **Concurrency:** Short-TTL cache.

`GET /api/v1/public/reference-data` (master data for filters: study programs, industries, organization types, geographic areas, skills — active entries only) is implemented alongside this route.

**Source Requirement:** FR-VAC-005, FR-EXT-001 · FSD §4.1, §10.4 · ADR-017

---

### GET /api/v1/public/companies/{slug}

**Surface:** `VERSIONED_API`

**Purpose:** Public company summary only (PD-2, approved 26 August 2026) — never documents, never members, never applicants. Backs the company summary shown alongside a company's public vacancies; this is a single-company lookup, never a directory.

**Authentication:** None. **Authorization:** `PUBLIC`. Rate-limited per IP.

**Request:** None. **Validation:** Slug format.

**Business Rules:**
- Visible only when the company's `verification_status = VERIFIED` — the same trust gate PD-1 already applies to that company's vacancies (PR-COMP-01: a company is never publicly presented before Career Center verification). A `DRAFT`, `PENDING_VERIFICATION`, `REVISION_REQUIRED`, `REJECTED`, or `SUSPENDED` company and a nonexistent slug are indistinguishable to the caller.
- **Payload is exactly:** `name`, `logo_url` (always `null` — the public logo-serving mechanism is undecided; the private `logo_storage_reference` key is never exposed and textual discovery does not wait on this decision), `industry_id`, `city_geographic_area_id`, `mitra_kampus_active`. No `verification_status` field is exposed — VERIFIED is required for visibility, never itself displayed. No `id`, no legal identifier, no documents, no members, no contact details.
- **Slug resolution (PD-2):** `companies.slug` is generated exactly once, at company creation, from the company's name at that moment, plus a random discriminator, and is guaranteed unique. It is never regenerated by a later name edit — no update path touches it. It carries no authorization meaning; visibility is governed entirely by the business rule above, never by the slug's format or presence. There is no redirect/history table and no public numeric-ID fallback.

**Success Response:** `200 OK`. **Error Codes:** `NOT_FOUND` (404) · `RATE_LIMITED` (429).

**Side Effects:** None. **Audit:** None. **Notification / Outbox:** None.

**Idempotency:** Safe method. **Concurrency:** Short-TTL cache permitted; no authorization-sensitive record is cached (ADR-006).

**Source Requirement:** FSD §4.1 · PO decision **PD-2**, approved 26 August 2026

---

### POST /api/v1/vacancies/{vacancy}/screening-questions

**Surface:** `INERTIA_WEB`

**Purpose:** Define vacancy screening questions (FR-VAC-003, FR-HR-002).

**Authentication:** Required, email verified. **Authorization:** `COMPANY_SCOPE` or `CAMPUS_SCOPE` owner. For a company vacancy, the **global `SUPER_ADMIN` role alone is not sufficient** (PO decision VA-4) — an ACTIVE company membership is required, and a Super Admin who holds one acts through it.

**Request:** `question_text`, `question_type` (`SHORT_TEXT` \| `LONG_TEXT` \| `YES_NO` \| `SINGLE_CHOICE` \| `NUMBER`), **`required` (boolean, MANDATORY)**, `options_definition` (required for `SINGLE_CHOICE`), `sort_order`, **`active` (boolean, MANDATORY)**.

**Validation:** Enum membership; `options_definition` present and non-empty **only** for `SINGLE_CHOICE`; rejected for other types.

**`required` and `active` on a NEW question (PO decision VA-3, approved — CLOSED):**
- Every **new** screening question must state **both** `required` and `active` explicitly, as booleans. A missing value is `422 VALIDATION_FAILED`.
- **There is no server default.** The former implicit `required = false` / `active = true` fallback was unsourced and is removed. `false` and `true` are both accepted — but only when the client sends them.
- This applies identically to **inline `screening_questions[]` on `POST /companies/{company}/vacancies`**, which the create contract supports: each inline question must carry both flags.
- **`PATCH /vacancies/{vacancy}/screening-questions/{question}` stays a partial update.** An omitted `required` leaves the stored value unchanged; an omitted `active` leaves the stored value unchanged. Deactivation is still `PATCH active=false`.

**Business Rules:**
- Questions belong to one vacancy. A question from another vacancy can never be referenced by an answer (INV-019).
- **A question that already has answers is never hard-deleted** → `409 SCREENING_QUESTION_IN_USE`. Deactivate it instead (`active = false`), which preserves historical answers while removing it from new applications.
- Candidates receive **only active questions** for the vacancy.

**Success Response:** `201 Created`. Paired routes: `GET /api/v1/vacancies/{vacancy}/screening-questions` and `PATCH /api/v1/vacancies/{vacancy}/screening-questions/{question}`.

**There is no `DELETE`.** A question with answers can never be removed, and deactivation via `PATCH active=false` works for every case including a never-answered question. Exposing both would give one concept two mechanisms and lead a recruiter to try `DELETE`, receive a `409`, and have to discover the real path (`API_SIZE_REVIEW.md` Q-1).

**Error Codes:** `VALIDATION_FAILED` (422) · `SCREENING_QUESTION_IN_USE` (409) · `VACANCY_NOT_EDITABLE` (409) · `AUTH_FORBIDDEN` (403).

**Side Effects:** Question rows. **Audit:** `vacancy_screening_question_changed`. **Notification / Outbox:** None.

**Idempotency:** Not required. **Concurrency:** Last-write-wins per question.

**Source Requirement:** FR-VAC-003, FR-HR-002 · INV-019 · PO decision **VA-3** (explicit `required` / `active` on new questions), approved 25 August 2026

---

### POST /api/v1/vacancies/{vacancy}/stages

**Surface:** `INERTIA_WEB`  ·  **Active MVP route:** `POST /vacancies/{vacancy}/stages`

> **Recruitment Stage Authoring Foundation v1 (RS-2, RS-6 — approved and CLOSED, 26 August 2026) scopes this operation for `COMPANY` vacancies.**
>
> **RS-2 — stage administration state policy.** Stage read/write authorization for Foundation v1 is determined **only** by actor capability (`COMPANY_SCOPE`/`SUPER_ADMIN`), vacancy ownership, and the stage belonging to that vacancy. **No additional gate exists on `vacancy.current_status` or `company.verification_status`** — a stage may be listed, created, updated, reordered, or deactivated regardless of whether the vacancy is `DRAFT`, `PENDING_REVIEW`, `REVISION_REQUIRED`, `APPROVED`, `SCHEDULED`, `PUBLISHED`, `CLOSED`, `EXPIRED`, `SUSPENDED`, or `REJECTED`, and regardless of the owning company's `verification_status`, as long as the actor is authorized against that vacancy. Neither `VACANCY_NOT_EDITABLE` nor a new company-verification error is used for Stage Authoring v1 — none is invented. **Stage mutation is configuration only**: it never changes vacancy status, never writes `applications.current_stage_id`, never moves a candidate, never reopens candidate intake, and never bypasses RA-2 — a `SUSPENDED` company or vacancy still cannot process applicants where RA-2 forbids it; RS-2 governs stage *configuration* exclusively and leaves RA-2 completely untouched.
>
> **RS-6 — Super Admin.** `SUPER_ADMIN` has unconditional `ALLOW` for stage management, distinct from VA-4 (which requires an active `company_members` row for vacancy editing and screening questions) — the frozen matrix's "Manage recruitment stages · reorder" row carries no VA-4 footnote, and RS-6 confirms this is a deliberate, separate grant: Super Admin needs no company membership to author stages for any `COMPANY` vacancy.
>
> **Actor set active in Foundation v1:** `COMPANY_RECRUITER`, `COMPANY_ADMIN` (`COMPANY_SCOPE`), `SUPER_ADMIN` (`ALLOW`, RS-6). Career Center is `DENY` (the matrix's single combined "Manage recruitment stages" row denies it for both read and write — `INERTIA_ACTIONS.md`'s route-inventory table listing Career Center among stage-list roles is a doc-generation artifact inherited from the generic Vacancy-domain grouping, not an authorization grant, and does not govern). `CAMPUS_SCOPE` (`HR_ADMIN`) is **not** activated — no Campus vacancy runtime exists. Selector assignment and move-stage remain **entirely out of scope**: selector assignment for `COMPANY` vacancies is not defined by the frozen matrix (footnote 23: "not defined for company vacancies... a change request") and stays deferred pending that explicit future decision; move-stage stays deferred per AD-2-adjacent scope discipline, unrelated to this milestone.

**Purpose:** Configure vacancy-specific recruitment stages (FSD §6.1, FR-HR-005).

**Authentication:** Required, email verified. **Authorization:** `COMPANY_SCOPE` or `CAMPUS_SCOPE` owner.

**Request:** `name`, `stage_type`, `sort_order` (create only — see the ordering rule below), `active`, `candidate_visible_label` (optional). `PATCH` accepts `name`, `stage_type`, `active`, `candidate_visible_label` only.

**Validation:** Enum membership; `sort_order` integer.

**Business Rules:**
- **Recruitment stage is not application status.** Status is the fixed, system-wide lifecycle vocabulary of FR-APP-004; a stage is this vacancy's configurable operational step. Neither is derived from the other.
- `candidate_visible_label` lets the candidate-facing label stay simpler than the internal stage name (FR-APP-004: internal stages are not automatically exposed).
- **A stage referenced by live applications, schedules, evaluations, or selector assignments cannot be deleted** → `409 STAGE_IN_USE`. Deactivate instead. This protects `applications.current_stage_id` and existing history from being orphaned.
- **`sort_order` is create-only.** `POST .../stages/reorder` is the **sole** post-creation operation permitted to change `sort_order` — a whole-set, atomic, lock-serialized write (see below). `PATCH /vacancies/{vacancy}/stages/{stage}` **rejects** a supplied `sort_order` outright (`422 VALIDATION_FAILED`), never silently discarding it. `recruitment_stages` carries no unique constraint on `(vacancy_id, sort_order)`, so allowing `PATCH` to also carry `sort_order` would reopen exactly the risk the dedicated reorder endpoint exists to close.
- Reordering (`POST /api/v1/vacancies/{vacancy}/stages/reorder`) changes `sort_order` only; it never rewrites which stage an application currently sits in.
- Reordering is a **whole-set atomic operation** (`API_SIZE_REVIEW.md` Q-4): the client submits the complete new ordering in one request, never N individual `sort_order` edits, so no transient duplicate position or partial-failure state is ever observable. This is why `PATCH` never carries `sort_order` — an individual `PATCH` is exactly the pattern Q-4 reasons against.

**Success Response:** `201 Created`. Paired routes: `GET /vacancies/{vacancy}/stages`, `PATCH /vacancies/{vacancy}/stages/{stage}`, `POST /vacancies/{vacancy}/stages/reorder` — all `INERTIA_WEB`, same authorization and RS-2/RS-6 scope as above.

**Error Codes:** `VALIDATION_FAILED` (422) · `STAGE_IN_USE` (409) · `STAGE_NOT_IN_VACANCY` (422) · `AUTH_FORBIDDEN` (403) · `NOT_FOUND` (404).

**Side Effects:** Stage rows. **Audit:** `vacancy_stage_changed`. **Notification / Outbox:** None.

**Idempotency:** Not required. **Concurrency:** Reorder locks the vacancy's stage set.

**Source Requirement:** FR-HR-005, FR-APP-004, FR-HR-007 · INV-019, INV-026

---

## Part VI — Application Lifecycle

> This part carries the model's densest invariant cluster. Every rule below is enforced server-side inside one transaction, backed by a database constraint wherever one can express it.

> **The four candidate-facing operations below are `INERTIA_WEB` for MVP (SPEC-DOC-08, accepted).** Submit, the candidate's own list, the candidate's own detail read, and withdraw are served over the **Laravel session guard with CSRF protection on mutations**, the same reasoning already applied to browser authentication (SPEC-DOC-05) and Candidate Core (SPEC-DOC-07): the MVP Candidate portal is a browser, and `/api/v1` is inactive at MVP. Each heading below remains the canonical operation identifier and the reserved `/api/v1` twin for when the versioned API is explicitly activated for a non-browser client; a future adapter calls the same `SubmitApplication`/`WithdrawApplication`/`ApplicationScope` Actions and Queries, so promotion is additive and requires no behavioural change. **No business rule changed by this amendment** — AD-1, AD-4, eligibility, consent, documents, screening, history, audit, notifications, idempotency, and concurrency are identical on either surface. `POST /applications/{application}/reopen` is **not** part of this amendment and stays `VERSIONED_API`/reserved, with no runtime — AD-2 remains open and deferred. The `COMPANY_SCOPE`/`CAMPUS_SCOPE`/`ASSIGNED_STAGE`/Auditor scopes on list and detail belong to a later Recruiter Applicant Management phase and are untouched.

### POST /vacancies/{vacancy}/applications

**Surface:** `INERTIA_WEB`  ·  **Reserved `/api/v1` twin:** `POST /api/v1/vacancies/{vacancy}/applications`

> **Reclassified for the MVP browser Candidate portal (SPEC-DOC-08, accepted).** See the Part VI note above.

**Purpose:** Submit an in-portal application (FR-APP-001). **The single most invariant-dense endpoint in the system.**

**Authentication:** Required, **email verified**.

**Authorization:** Candidate role with an existing `candidate_profiles` row. The candidate is taken from the **authenticated actor**, never from the payload — a client cannot apply on another candidate's behalf.

**Request:**

| Field | Type | Required | Notes |
| --- | --- | --- | --- |
| `document_ids[]` | array | Conditional | `candidate_documents` ids the candidate chooses to share |
| `screening_answers[]` | array | Conditional | `{ screening_question_id, answer_value }` |
| `consent` | object | **Yes** | `{ consent_version, consent_text_hash_reference, accepted: true }` |

**The `consent` object carries no receiving party.** See Business Rules.

> **FE-1 — application document-sharing consent copy (approved and CLOSED, 27 August 2026).** The exact Indonesian text the candidate-facing checkbox displays is: *"Saya menyetujui dokumen yang saya pilih pada lamaran ini dibagikan kepada perusahaan pemilik lowongan untuk keperluan proses rekrutmen."* Scope: only the documents explicitly selected for **this** application, shared with the vacancy's owning company, for **this** recruitment process only — it must never be read as marketing consent, consent to share with another company, public document access, blanket future-application consent, blanket profile/document access, or consent to unrelated processing. This is frontend copy only: `consent_version`, `consent_text_hash_reference`, and the consent entity/hash architecture are unchanged — the frontend hashes this exact string with the same SHA-256 mechanism already established.

**Validation:** `consent.accepted` must be literally `true` — never defaulted, never pre-checked (FR-CONSENT-001). `document_ids[]` must be unique. `screening_answers[]` must not contain duplicate question ids.

**Business Rules — validated atomically, in one transaction, with the vacancy row locked:**

1. **Vacancy exists and is applicable.** `current_status = PUBLISHED` and now within `open_at`…`close_at` → else `409 VACANCY_NOT_OPEN`.
2. **`application_method` must be `IN_PORTAL`** → else `422 APPLICATION_NOT_IN_PORTAL_VACANCY`. **This is INV-024, the inverse of INV-012: an application row may never exist for an `EXTERNAL_ATS` vacancy.** The vacancy row is locked so the method cannot change mid-transaction.
3. **Candidate eligibility against `target_audience`** — evaluated **only** against `candidate_verifications` with `status = VERIFIED` of the matching type (INV-028). `ALUMNI_ONLY` requires a VERIFIED `ALUMNI` record; `FINAL_YEAR_AND_ALUMNI` requires a VERIFIED record of either type. **Neither the role code nor `current_candidate_type` is accepted as proof.** Failure → `403 CANDIDATE_NOT_ELIGIBLE`.
4. **Profile completeness** per FR-APP-001 → else `422 CANDIDATE_PROFILE_INCOMPLETE`.
5. **No existing lifecycle for this candidate and vacancy.** `UNIQUE(candidate_profile_id, vacancy_id)` is **unconditional and covers the full lifecycle, not only active applications** (INV-007). A terminal WITHDRAWN or REJECTED application still occupies the pair. Violation → `409 APPLICATION_ALREADY_EXISTS`, with `details.application_id` so the UI can route to *Lihat Status Lamaran* (FR-APP-002). **Reapplying is `reopen`, never a second row.**
6. **Consent is valid and explicit** → else `422 APPLICATION_CONSENT_REQUIRED` (INV-011). `consent_version` must be a known published version.
7. **Consent receiving party is derived by the server from the vacancy's ownership, never accepted from the client** (INV-023): `ownership_type = COMPANY` → `receiving_company_id` = the vacancy's company, `receiving_organizational_unit_id` absent. `ownership_type = CAMPUS` → `receiving_organizational_unit_id` = the vacancy's unit, `receiving_company_id` absent. Exactly one, never both, never neither. A client that supplies a receiver is rejected with `422 CONSENT_RECEIVER_MISMATCH`.
8. **Every `document_ids[]` entry belongs to this candidate**, is not archived, and is not quarantined → else `403 DOCUMENT_NOT_OWNED`, `422 DOCUMENT_ARCHIVED`, `409 DOCUMENT_SCAN_PENDING`. Required document types missing → `422 APPLICATION_DOCUMENT_REQUIRED`.
9. **Screening answers match the vacancy's own active questions.** Every required active question answered → else `422 APPLICATION_SCREENING_INCOMPLETE`. Every answer's type and choice validated against its question definition, and every `screening_question_id` must belong to **this** vacancy → else `422 APPLICATION_SCREENING_INVALID` (INV-019).

**Written atomically on success:**

- `consents` row (receiver derived per rule 7);
- `applications` row — `current_status = APPLIED`, `first_applied_at`, `reopen_count = 0`, `application_code`;
- `application_documents` rows, **each capturing `snapshot_name` and `snapshot_storage_reference` at share time** (required, INV-032) so later edits or archival of the source document cannot alter historical recruitment evidence;
- `application_screening_answers` rows;
- `application_status_histories` row with `event_type = APPLICATION_CREATED` — history is authoritative, the application's current-value fields are its cache (INV-026);
- `audit_logs` row;
- `email_outbox` rows.

**Everything above commits together or not at all.** Consent and application in particular are inseparable (INV-011).

**Success Response:** `201 Created`

```json
{ "data": { "id": "…", "application_code": "APP-2026-000123", "current_status": "APPLIED",
             "first_applied_at": "…", "shared_document_count": 3 },
  "meta": { "correlation_id": "…", "warnings": ["EMAIL_DELIVERY_PENDING"] } }
```

**Error Codes:** `VACANCY_NOT_OPEN` (409) · `APPLICATION_NOT_IN_PORTAL_VACANCY` (422) · `CANDIDATE_NOT_ELIGIBLE` (403) · `CANDIDATE_PROFILE_INCOMPLETE` (422) · `APPLICATION_ALREADY_EXISTS` (409) · `APPLICATION_CONSENT_REQUIRED` (422) · `CONSENT_VERSION_UNKNOWN` (422) · `CONSENT_RECEIVER_MISMATCH` (422) · `APPLICATION_DOCUMENT_REQUIRED` (422) · `DOCUMENT_NOT_OWNED` (403) · `DOCUMENT_ARCHIVED` (422) · `DOCUMENT_SCAN_PENDING` (409) · `APPLICATION_SCREENING_INCOMPLETE` (422) · `APPLICATION_SCREENING_INVALID` (422) · `AUTH_EMAIL_NOT_VERIFIED` (403).

**Side Effects:** As listed above. No external call occurs inside the transaction.

**Audit:** `application_created` — actor, application, vacancy, correlation ID. Document **ids** only, never content.

**Notification / Outbox:** In-app notification to the candidate and to the vacancy owner; `email_outbox` rows for both per FR-NOTIF-002. Dispatched **after commit** (INV-015). **SMTP failure never rolls back the application.**

**Idempotency:** **REQUIRED.** A retried submit must not attempt a second lifecycle. Even without the header, INV-007's unique constraint makes a duplicate impossible — the client simply receives `409` instead of a replayed `201`.

**Concurrency:** Vacancy row locked for the status, method, and window checks. Two simultaneous submits for one candidate+vacancy: the unique index guarantees exactly one succeeds; the loser receives `409 APPLICATION_ALREADY_EXISTS`.

**Source Requirement:** FR-APP-001, FR-APP-002, FR-CONSENT-001, FR-CONSENT-002, FR-CONSENT-003, FR-CAN-005, FR-VAC-002 · **INV-007, INV-010, INV-011, INV-015, INV-016, INV-019, INV-023, INV-024, INV-026, INV-028, INV-032**

---

### POST /api/v1/applications/{application}/reopen

**Surface:** `VERSIONED_API`

**Purpose:** Reopen an existing application lifecycle on authorized reapply (FR-APP-003).

**Authentication:** Required, email verified.

**Authorization:** Authorized actor per FR-APP-003 — the vacancy owner (`COMPANY_SCOPE` / `CAMPUS_SCOPE`), or a candidate where the business permits self-reapply. **Candidate self-reopen eligibility is bounded by vacancy state and business rules, not by client assertion.**

**Request:** `reason` (optional), `target_stage_id` (optional — must belong to the same vacancy).

**Validation:** `target_stage_id`, where given, belongs to this application's vacancy (INV-019).

**Business Rules:**
- **Reuses the same `applications` row. It never creates application number two** (FR-APP-003.4, INV-008). There is no code path in this API that produces a second row for one candidate+vacancy.
- Requires the vacancy to still permit applications — PUBLISHED and within its window → else `409 VACANCY_NOT_OPEN`.
- Requires `application_method = IN_PORTAL` (INV-024).
- Reopen conditions unmet → `409 APPLICATION_NOT_REOPENABLE`.
- On success, atomically: appends `application_status_histories` with **`event_type = APPLICATION_REOPENED`** (the FR-APP-003 name), records actor, timestamp, and reason; sets `current_status` and `current_stage_id` to the permitted resumption point; increments `reopen_count`; sets `last_reopened_at`.
- **`reopen_count` and `last_reopened_at` are derived caches and must be written in the same transaction as the event** (INV-026). They can never drift from the history.
- **All prior history is preserved** — terminal events remain (FSD §8.5). Reopen adds; it never rewrites or removes.

**Success Response:** `200 OK` with the application, its new status, `reopen_count`, and `last_reopened_at`.

**Error Codes:** `APPLICATION_NOT_REOPENABLE` (409) · `VACANCY_NOT_OPEN` (409) · `APPLICATION_NOT_IN_PORTAL_VACANCY` (422) · `AUTH_FORBIDDEN` (403) · `NOT_FOUND` (404).

**Side Effects:** Application cache fields + history event + audit + outbox.

**Audit:** `application_reopened` with actor and reason.

**Notification / Outbox:** Candidate and vacancy owner notified.

**Idempotency:** **REQUIRED.** Without it a retry would increment `reopen_count` twice and append two events.

**Concurrency:** Application row locked. Two concurrent reopens → one succeeds, the other `409`.

**Source Requirement:** FR-APP-003 · FSD §8.5 · **INV-007, INV-008, INV-016, INV-024, INV-026**

---

### POST /applications/{application}/withdraw

**Surface:** `INERTIA_WEB`  ·  **Reserved `/api/v1` twin:** `POST /api/v1/applications/{application}/withdraw`

> **Reclassified for the MVP browser Candidate portal (SPEC-DOC-08, accepted).** See the Part VI note above.

**Purpose:** Candidate withdraws an application (FR-APP-006).

**Authentication:** Required. **Authorization:** `OWN` — the applying candidate only. A recruiter cannot withdraw on a candidate's behalf.

**Request:** `reason` (**optional** — FR-APP-006 states "alasan opsional", and INV-009 deliberately excludes it from the mandatory-reason rule INV-029).

**Validation:** Reason length where supplied.

**Business Rules:**
- Sets `current_status = WITHDRAWN`, `withdrawn_at`, and `withdrawal_reason` where given.
- **Nothing is deleted** (INV-009). The application, its status history, consent, `application_documents` shares and snapshots, schedules, evaluations, and offers all remain stored.
- The candidate sees **Mengundurkan Diri** (FR-APP-004), never a deletion or a disappearance from *Lamaran Saya*.
- Appends a `WITHDRAWN` history event.
- Already withdrawn → `409 APPLICATION_ALREADY_WITHDRAWN`.

**Success Response:** `200 OK` with the application at WITHDRAWN.

**Error Codes:** `APPLICATION_ALREADY_WITHDRAWN` (409) · `APPLICATION_INVALID_TRANSITION` (409) · `AUTH_FORBIDDEN` (403) · `NOT_FOUND` (404).

**Side Effects:** Status + history + audit + outbox.

**Audit:** `application_withdrawn`.

**Notification / Outbox:** **Vacancy owner notified** (FR-APP-006 requires it); candidate confirmation.

**Idempotency:** **REQUIRED.**

**Concurrency:** Application row locked.

**Source Requirement:** FR-APP-006, FR-APP-004 · **INV-009, INV-016, INV-026**

---

### POST /api/v1/applications/{application}/transition

**Surface:** `INERTIA_WEB`  ·  **Active MVP route:** `POST /applications/{application}/transition`

> **Recruiter Applicant Management Foundation v1 (RA-1, RA-2 — approved and CLOSED, 26 August 2026) narrows this operation for its first milestone.** The full transition graph and full actor set below remain the long-run contract; Foundation v1 activates only the subset RA-1/RA-2 define. Nothing below is redefined — only narrowed for what is actually routed today.
>
> **RA-1 — Foundation v1 transition graph.** Exactly these edges are legal in this milestone: `APPLIED → UNDER_REVIEW`, `APPLIED → REJECTED`, `UNDER_REVIEW → SHORTLISTED`, `UNDER_REVIEW → REJECTED`, `SHORTLISTED → REJECTED`. Every other edge — including any target of `ASSESSMENT`, `INTERVIEW`, `OFFERED`, `HIRED`, `NO_SHOW`, any backward edge, and same-status no-ops — is `409 APPLICATION_INVALID_TRANSITION` in this milestone, reserved for later Selection/Interview/Offering phases. `WITHDRAWN` remains unreachable here per the existing rule. `REJECTED` and `WITHDRAWN` are terminal for Foundation v1 and cannot be left through this endpoint (`409 APPLICATION_TERMINAL`) — reactivation is `reopen`, which AD-2 leaves open and unimplemented.
>
> **RA-2 — existing-applicant processing gate.** Beyond the `COMPANY_SCOPE` object-authorization check, a successful transition additionally requires, inside the same transaction: the owning company's `verification_status = VERIFIED` (else `403 VACANCY_COMPANY_NOT_VERIFIED` — the same code AD-1 already established for the submit gate, reused rather than duplicated), **and** the vacancy's `current_status` ∈ `{PUBLISHED, CLOSED, EXPIRED}` (else `409 APPLICATION_VACANCY_NOT_PROCESSABLE` — new, see `ERROR_CODES.md` §8; no existing code expresses "vacancy in a non-processing state" without contradicting `VACANCY_NOT_OPEN`'s meaning, which governs *new-submission* eligibility and would incorrectly reject the allowed `CLOSED`/`EXPIRED` processing states). `CLOSED`/`EXPIRED` stop new intake but do not block processing candidates who applied while intake was valid; `SUSPENDED` (and any vacancy state outside the allow-list) blocks processing entirely. Reads (list/detail) are unaffected by either gate. Neither gate mutates the vacancy, the company, or any application on denial. `SUPER_ADMIN`'s broad object authorization is a separate layer from this business-legality gate — Foundation v1 applies RA-2 identically to `SUPER_ADMIN`, since no source exempts it.
>
> **Actor set active in Foundation v1:** `COMPANY_RECRUITER`, `COMPANY_ADMIN` (`COMPANY_SCOPE`), `SUPER_ADMIN` (`ALLOW`). `CAMPUS_SCOPE` (`HR_ADMIN`) and `ASSIGNED_STAGE` (`SELECTOR`) are **not** activated — `SELECTOR` requires `recruitment_stages` runtime that does not exist yet; no fallback to role-alone or company-wide selector access is implemented. Career Center remains `DENY`, unchanged.

**Purpose:** Move an application's **lifecycle status** (FR-APP-004, FSD §8.5).

**Authentication:** Required. **Authorization:** Vacancy owner — `COMPANY_SCOPE` for company vacancies, `CAMPUS_SCOPE` for campus vacancies. **Career Center cannot transition applications**: FSD §3.3 permits it to monitor company vacancies but never to decide candidate acceptance on a company's behalf. Selectors cannot transition either — they evaluate.

**Request:** `to_status` (required, enum), `reason` (optional), `candidate_visibility` (required — whether the candidate sees this event), `candidate_visible_note` (optional).

**Validation:** `to_status` ∈ `APPLIED`, `UNDER_REVIEW`, `SHORTLISTED`, `ASSESSMENT`, `INTERVIEW`, `OFFERED`, `HIRED`, `REJECTED`, `WITHDRAWN`, `NO_SHOW`. **`candidate_visibility` is required because FR-APP-005 mandates a visibility note on every status change.** Foundation v1 additionally rejects, at the RA-1 graph check, any syntactically valid `to_status` that is not one of this milestone's five edges.

**Business Rules:**
- The **server** validates that the transition is legal from the current status. An illegal move → `409 APPLICATION_INVALID_TRANSITION` with `details.from` and `details.attempted`. **A client can never assert an arbitrary status** — this endpoint accepts a requested target and independently verifies it.
- Terminal states are `HIRED`, `REJECTED`, `WITHDRAWN`, `NO_SHOW` (FSD §8.5). Leaving a terminal state happens only through `reopen`. Foundation v1 can only ever reach `REJECTED` as a terminal state through its own writes; `WITHDRAWN` is reachable only through the candidate's own withdraw action.
- `WITHDRAWN` is **not** reachable here — withdrawal is the candidate's own action.
- `HIRED` is normally reached through offer acceptance, which sets it atomically (INV-031). Setting it directly is permitted only where the workflow allows and is audited identically. **Not reachable in Foundation v1** (RA-1).
- Atomically: updates `current_status`, appends `application_status_histories` with from/to status, actor, reason, visibility, and `occurred_at`, writes audit, writes outbox. **Cache and history are written together** (INV-026). `event_type` is `REJECTED` when `to_status = REJECTED` (the vocabulary's own dedicated case, the same "dedicated status → dedicated event_type" pattern `WITHDRAWN` already uses) and `STATUS_CHANGED` otherwise.

**Success Response:** `200 OK` with the application and its new status.

**Error Codes:** `APPLICATION_INVALID_TRANSITION` (409) · `APPLICATION_TERMINAL` (409) · `VALIDATION_FAILED` (422) · `AUTH_FORBIDDEN` (403) · `NOT_FOUND` (404) · `STALE_VERSION` (409) · `VACANCY_COMPANY_NOT_VERIFIED` (403, RA-2) · `APPLICATION_VACANCY_NOT_PROCESSABLE` (409, RA-2).

**Side Effects:** Status + history + audit + outbox.

**Audit:** `application_status_changed` with from/to.

**Notification / Outbox:** Candidate notified **according to `candidate_visibility`**; email per FR-NOTIF-002. An internal-only transition produces no candidate-facing message.

**Idempotency:** **REQUIRED.**

**Concurrency:** Application row locked. `If-Match` supported so a stale UI cannot transition from a status the reviewer no longer sees.

**Source Requirement:** FR-APP-004, FR-APP-005, FR-HR-005 · FSD §3.3, §8.5 · **INV-016, INV-019, INV-026**

---

### POST /api/v1/applications/{application}/move-stage

**Surface:** `INERTIA_WEB`  ·  **Active MVP route:** `POST /applications/{application}/move-stage`

> **Application Stage Movement Foundation v1 (MS-3, MS-4 — approved and CLOSED, 27 August 2026) activates this operation.** Application Stage Movement authorization is a separate capability from Recruitment Stage Authoring's RS-6 grant — it is not inherited from it, but independently sourced from `AUTHORIZATION_MATRIX.md`'s own "Move recruitment stage" row.
>
> **MS-3 — same-stage movement.** A request where `to_stage_id == applications.current_stage_id` is rejected with `422 VALIDATION_FAILED`. The application is not mutated; no history event is appended; no audit, notification, or outbox row is written. This is not treated as a successful no-op, and no `STAGE_CHANGED` history row with `from_stage_id == to_stage_id` is ever created.
>
> **MS-4 — stage movement adjacency.** A recruiter may move an eligible application to **any active recruitment stage belonging to the same vacancy** — forward, backward, skipping stages, or lateral movement are all permitted. `sort_order` remains an authoring/display concern only (unchanged from RS-1/RS-2) and is never compared for movement legality. The only target-stage constraints are: the target exists, belongs to the application's vacancy, is `active`, and differs from `current_stage_id`.
>
> **RA-2 — existing-applicant processing gate, reused.** Beyond the `COMPANY_SCOPE` object-authorization check, a successful move additionally requires, inside the same transaction, the same gate `/transition` already enforces: the owning company's `verification_status = VERIFIED` (else `403 VACANCY_COMPANY_NOT_VERIFIED`) and the vacancy's `current_status` ∈ `{PUBLISHED, CLOSED, EXPIRED}` (else `409 APPLICATION_VACANCY_NOT_PROCESSABLE`). This is the same `ApplicationProcessingGate` used by `/transition`, not a second processing-state policy. Applies identically to `SUPER_ADMIN` — no source exempts it.
>
> **Actor set active in Foundation v1:** `COMPANY_RECRUITER`, `COMPANY_ADMIN` (`COMPANY_SCOPE`), `SUPER_ADMIN` (`ALLOW`). `CAMPUS_SCOPE` (`HR_ADMIN`) is **not** activated — no Campus vacancy runtime exists. Career Center remains `DENY` (footnote 13/18 both name move-stage explicitly: "It cannot list, read, transition, or move applications for company vacancies"). Selectors remain `DENY` (footnote 18: "No transition or stage-move capability, at any scope").

**Purpose:** Move an application to a different **recruitment stage** — an operational step, distinct from lifecycle status.

**Authentication:** Required. **Authorization:** Vacancy owner — `COMPANY_SCOPE` for company vacancies, `CAMPUS_SCOPE` for campus vacancies (inert, no Campus runtime). **Career Center cannot move applications between stages**, same rationale as `/transition` (FSD §3.3). Selectors cannot move stages either — they evaluate.

**Request:** `to_stage_id` (required), `reason` (optional), `candidate_visibility` (required).

**Validation:** `to_stage_id` exists, is `active`, belongs to the same vacancy as the application, and differs from the application's current `current_stage_id`.

**Business Rules:**
- **`to_stage_id` must belong to the same vacancy as the application** → else `422 STAGE_NOT_IN_VACANCY` (INV-019). A foreign key alone cannot prove this, so it is checked in the Action on every write path.
- **`to_stage_id` equal to the application's current stage is rejected** → `422 VALIDATION_FAILED` (MS-3). No mutation, no history, no audit, no notification.
- **Stage movement does not imply a status change and never triggers one implicitly.** A candidate may move "Wawancara HR" → "Wawancara Dekan" while `current_status` stays `INTERVIEW`. FR-APP-004 and FR-HR-007 both require an explicit transition action to change candidate-facing status.
- **No adjacency or ordering restriction** (MS-4): forward, backward, skipped, and lateral movement between active same-vacancy stages are all permitted. `sort_order` never gates movement legality.
- `applications.current_stage_id` is nullable; `NULL → active target` is permitted and is the natural first movement, recorded with `from_stage_id = NULL`.
- A disabled (`active = false`) stage may remain an application's current stage (RS-1/RS-2); moving **away** from it is permitted, moving **to** an inactive stage is denied.
- **Terminal applications cannot move stage** → `409 APPLICATION_TERMINAL`. Terminal statuses are `HIRED`, `REJECTED`, `WITHDRAWN`, `NO_SHOW` (FSD §8.5), the same set `/transition` and `/reopen` already use.
- Appends a `STAGE_CHANGED` history event carrying `from_stage_id` and `to_stage_id` (`from_status`/`to_status`/`candidate_visible_note` all `NULL` — this is a pure stage-only event); updates `current_stage_id` in the same transaction (INV-026).

**Success Response:** `200 OK`.

**Error Codes:** `STAGE_NOT_IN_VACANCY` (422) · `VALIDATION_FAILED` (422, MS-3 same-stage and request validation) · `APPLICATION_TERMINAL` (409) · `AUTH_FORBIDDEN` (403) · `NOT_FOUND` (404) · `VACANCY_COMPANY_NOT_VERIFIED` (403, RA-2) · `APPLICATION_VACANCY_NOT_PROCESSABLE` (409, RA-2).

**Side Effects:** `current_stage_id` + history + audit + outbox.

**Audit:** `application_stage_changed`, emitted only after a genuine successful movement — a rejected same-stage request produces no audit entry.

**Notification / Outbox:** Per `candidate_visibility`; the candidate sees `candidate_visible_label` where set, not the internal stage name. `candidate_visibility = INTERNAL` writes no candidate notification and no outbox row. No recruiter self-notification.

**Idempotency:** **REQUIRED.**

**Concurrency:** Application row locked, then vacancy, then target stage, then company (deterministic order avoiding deadlock with `/transition`, `SaveRecruitmentStage`, and `ReorderRecruitmentStages`). After locks, eligibility, RA-2, and target-stage `active`/vacancy-membership are all rechecked against the freshly locked rows, so a concurrent stage-disable cannot admit movement into a now-inactive stage.

**Source Requirement:** FR-APP-004, FR-HR-005, FR-HR-007 · **INV-019, INV-026**

---

### POST /api/v1/applications/bulk-transition

**Surface:** `INERTIA_WEB`

**Purpose:** Apply one transition to several applications at once (FR-APP-007).

**Authentication:** Required. **Authorization:** Vacancy owner. **Every application is authorized individually** — a bulk request is not a bulk permission.

**Request:** `application_ids[]` (required, bounded — maximum enforced), `to_status` **or** `to_stage_id`, `reason`, `candidate_visibility`.

**Validation:** Array size limit; all ids distinct.

**Business Rules:**
- Each application is validated and transitioned under the same rules as the single-item endpoints. **Per-item failures do not silently vanish**: the response reports each id's outcome.
- Partial success is reported explicitly rather than rolled back wholesale, so one ineligible application does not block a legitimate batch. Each successful item is individually atomic — its status, history, audit, and outbox rows commit together.
- **Every item produces its own audit entry** (FR-APP-007: "Semua bulk action diaudit").

**Success Response:** `200 OK` with `data.results[]` — `{ application_id, outcome: "APPLIED" | "SKIPPED", error_code? }`.

**Error Codes:** `VALIDATION_FAILED` (422) · `AUTH_FORBIDDEN` (403). Per-item codes appear inside `results[]`.

**Side Effects:** Per successful item as above.

**Audit:** One entry per item, plus a batch correlation ID linking them.

**Notification / Outbox:** Per item, per visibility.

**Idempotency:** **REQUIRED** — a retried batch must not double-transition.

**Concurrency:** Per-application row locks, acquired in a deterministic order to avoid deadlock.

**Source Requirement:** FR-APP-007 · INV-016, INV-026

---

### GET /applications

**Surface:** `INERTIA_WEB` for the candidate `OWN` scope  ·  **Reserved `/api/v1` twin:** `GET /api/v1/applications`

> **Candidate `OWN` scope reclassified for the MVP browser Candidate portal (SPEC-DOC-08, accepted).** See the Part VI note above.
>
> **`COMPANY_SCOPE` (`COMPANY_RECRUITER`, `COMPANY_ADMIN`) and `SUPER_ADMIN`'s `ALLOW` are active as of Recruiter Applicant Management Foundation v1.** Filters/sort below are unchanged; `vacancy_id` narrowing is available to recruiter scope the same as any other filter. `CAMPUS_SCOPE`, `ASSIGNED_STAGE`, and Auditor remain **not** implemented — `CAMPUS_SCOPE` awaits Campus recruitment, `ASSIGNED_STAGE` awaits `recruitment_stages` runtime (see the transition section's RA-1/RA-2 note above), Auditor awaits its reporting phase.

**Purpose:** Scoped application list — *Lamaran Saya* for candidates, applicant lists for owners (FSD §4.2, FR-HR-005).

**Authentication:** Required.

**Authorization:** **Query-scoped at the query level, never filtered after fetch.** This is the endpoint where a Policy alone is insufficient — a Policy protects `show`, and does nothing for a list.

| Actor | Scope |
| --- | --- |
| Candidate | `OWN` — own `candidate_profile_id` only |
| Company Recruiter / Admin | `COMPANY_SCOPE` — applications on vacancies of a company where the actor has an **active** membership |
| HR_ADMIN | `CAMPUS_SCOPE` — applications on `ownership_type = CAMPUS` vacancies |
| **Selector** | **`ASSIGNED_STAGE`** — only applications whose **current stage** has an **active** `selection_stage_assignments` row for this selector (INV-037). The query joins the assignment; it is not a post-filter |
| Career Center | **DENY** for candidate-selection data. It monitors companies and vacancies, not applicant pipelines (FSD §3.3) |
| Auditor | `READ_ONLY` within permitted reporting scope |

**Request:** Filters `vacancy_id`, `current_status`, `current_stage_id`, `applied_from`, `applied_to`, `q`. Sortable: `first_applied_at`, `updated_at`, `current_status`.

**Validation:** Allow-listed filters and sort fields only.

**Business Rules:** A candidate sees candidate-facing labels (FR-APP-004). An owner sees internal stage names. **A selector sees only the candidate data needed for the assigned stage** (FR-HR-006) — no unrelated vacancy, no unassigned stage, no document unless shared with that application.

**Success Response:** `200 OK`, paginated. **Error Codes:** `VALIDATION_FAILED` (422) · `UNAUTHENTICATED` (401).

**Side Effects:** None. **Audit:** None for listing. **Notification / Outbox:** None.

**Idempotency:** Safe method. **Concurrency:** None. **Never cached across requests** — scope depends on live membership and assignment state.

**Source Requirement:** FR-APP-004, FR-HR-005, FR-HR-006 · FSD §3.3, §4.2 · **INV-017, INV-037**

---

### GET /applications/{application}

**Surface:** `INERTIA_WEB` for the candidate `OWN` scope  ·  **Reserved `/api/v1` twin:** `GET /api/v1/applications/{application}`

> **Candidate `OWN` scope reclassified for the MVP browser Candidate portal (SPEC-DOC-08, accepted).** See the Part VI note above.
>
> **`COMPANY_SCOPE` and `SUPER_ADMIN` are active as of Recruiter Applicant Management Foundation v1.** Recruiter/company/Super Admin detail may include the full authorized subset: application summary, a source-backed candidate profile summary, screening answers, shared application-document **metadata** (RA-3 — download is deferred; no `snapshot_storage_reference`/`snapshot_checksum`/direct URL is ever returned), and the **full** application history (`reason` included, not filtered to `candidate_visibility = VISIBLE`) — the candidate-side visibility filter belongs only to the candidate's own detail read and is unchanged. `evaluations`, `schedules`, and `offers` remain absent from the recruiter response the same way they are absent from the candidate's — no placeholder or empty-array key is invented for domains that do not exist yet. `CAMPUS_SCOPE` and `ASSIGNED_STAGE` remain **not** implemented, for the same reasons as the list above.

**Purpose:** Application detail (FSD §4.2 Detail Lamaran).

**Authentication:** Required. **Authorization:** As for the list. Out of scope → `404`, never `403`.

**Request:** Optional `?include=history,documents,schedules,offers,evaluations,screening_answers`.

**Validation:** Allow-listed include values.

**Business Rules:**
- Field visibility differs by actor. A candidate sees candidate-facing status labels, their own shared documents, schedules, and offers. An owner additionally sees internal stage, evaluations, and internal notes. A **selector sees only what the assigned stage requires** and no evaluation authored by another evaluator beyond what the workflow permits.
- **Evaluations are never candidate-visible** (FR-HR-007: internal results do not become candidate-facing status without an explicit transition).
- Paired routes: `GET /api/v1/applications/{application}/history` (append-only, respecting `candidate_visibility` for candidates) and `GET /api/v1/applications/{application}/documents` (metadata of documents shared **with this application only**). Both are standalone because each has its own pagination or authorization rule.
- **Screening answers have no standalone route.** They are small, bounded, never paginated, always read with the application, and governed by the same scope — so they are returned through `?include=screening_answers` on this endpoint (`API_SIZE_REVIEW.md` M-3).

**Success Response:** `200 OK`. **Error Codes:** `NOT_FOUND` (404) · `AUTH_FORBIDDEN` (403).

**Side Effects:** None. **Audit:** None for reading the record itself; **document download is audited separately**. **Notification / Outbox:** None.

**Idempotency:** Safe method. **Concurrency:** None.

**Source Requirement:** FR-APP-004, FR-APP-005, FR-HR-005 · INV-010, INV-037

---

### GET /api/v1/application-documents/{applicationDocument}/download

**Surface:** `VERSIONED_API`

**Purpose:** Authorized download of a document **shared with one application** (FR-CONSENT-003, FR-AUD-001).

**Authentication:** Required.

**Authorization:** The owning candidate, or an actor authorized over **that application's** vacancy — `COMPANY_SCOPE`, `CAMPUS_SCOPE`, or `ASSIGNED_STAGE` selector.

**Request:** None. **Validation:** None.

**Business Rules:**
- **This is the only path by which a recruiter, HR admin, or selector reaches a candidate file.** Reaching a candidate profile grants no document access whatsoever (INV-010).
- The record must exist and not be revoked → else `403 DOCUMENT_NOT_SHARED`. A document the candidate owns but did not share with **this** application is unreachable here, even if shared with a different application for the same company.
- Serves the **snapshot** (`snapshot_storage_reference`), not the candidate's current file, so later edits or archival cannot alter what was reviewed (INV-032).
- Policy check → **audit write** → stream or seconds-lived actor-bound signed URL. No durable public or pre-signed link is ever returned.
- `Content-Disposition: attachment`, `X-Robots-Tag: noindex`, never rendered inline in the application origin.

**Success Response:** `200 OK` file stream, or `302` to a short-lived signed URL.

**Error Codes:** `DOCUMENT_NOT_SHARED` (403) · `NOT_FOUND` (404) · `AUTH_FORBIDDEN` (403).

**Side Effects:** None to business state.

**Audit:** **`document_access` — required, including denied attempts.**

**Notification / Outbox:** None.

**Idempotency:** Safe method. **Concurrency:** None.

**Source Requirement:** FR-CAN-005, FR-CONSENT-003, FR-AUD-001 · **INV-010, INV-032, INV-037** · ADR-007

---

## Part VII — External Apply

### POST /api/v1/vacancies/{vacancy}/external-apply/start

**Surface:** `VERSIONED_API`

**Purpose:** Record that a candidate is leaving for an external ATS (FR-EXT-001, FR-EXT-002).

**Authentication:** Required — tracking applies to a signed-in candidate (FR-EXT-002: "Jika kandidat login dan tracking diizinkan").

**Authorization:** Candidate role with a candidate profile.

**Request:** `consent` object where tracking consent is required — `{ consent_version, consent_text_hash_reference, accepted: true }`.

**Validation:** Consent shape where required.

**Business Rules:**
- **`vacancy.application_method` must be `EXTERNAL_ATS`** → else `422 EXTERNAL_APPLY_INVALID_METHOD`. An in-portal vacancy is applied to through Part VI.
- Vacancy must be PUBLISHED and within its window.
- Creates **one `external_apply_events` row with `event_type = EXTERNAL_APPLY_STARTED`**, `started_at`, `destination_url_reference`, `confirmation_status` pending, and `consent_id` where tracking consent applies.
- **It must NOT create an `applications` row, and must NOT set any status to `APPLIED`** (INV-012, FR-EXT-002). This is enforced structurally: the Action has no path to `applications`, and INV-024 independently forbids an application row on an `EXTERNAL_ATS` vacancy.
- **The candidate is never shown "Lamaran Diterima" because a link was opened.** External activity appears in *Aktivitas Lamaran Eksternal*, a separate surface from *Lamaran Saya*.
- Returns controlled destination metadata after the FR-EXT-001 warning has been shown. The URL is `https`-only and **the server never fetches, previews, or validates it by requesting it** (SSRF — `SECURITY_ARCHITECTURE.md` §4).
- Repeat events for the same candidate and vacancy are **legitimate** — this is an event stream with no uniqueness rule.

**Success Response:** `201 Created`

```json
{ "data": { "external_apply_event_id": "…", "event_type": "EXTERNAL_APPLY_STARTED",
             "destination_url": "https://…", "confirmation_status": "PENDING" } }
```

**Error Codes:** `EXTERNAL_APPLY_INVALID_METHOD` (422) · `VACANCY_NOT_OPEN` (409) · `APPLICATION_CONSENT_REQUIRED` (422) · `AUTH_EMAIL_NOT_VERIFIED` (403).

**Side Effects:** One event row, optional consent row, audit, no outbox by default.

**Audit:** `external_apply_started`.

**Notification / Outbox:** None required.

**Idempotency:** **Optional but recommended.** Repeat starts are valid events; the header lets a client avoid recording a duplicate caused by a retry rather than a genuine second attempt.

**Concurrency:** None significant.

**Source Requirement:** FR-EXT-001, FR-EXT-002, FR-EXT-004 · **INV-012, INV-024**

---

### POST /api/v1/external-apply-events/{event}/confirm

**Surface:** `VERSIONED_API`

**Purpose:** Record confirmation that an external application actually happened or concluded (FR-EXT-003).

**Authentication:** Required.

**Authorization:** A **legitimate confirmation source only** (FR-EXT-003): the candidate who started the event, an authorized member of the owning company, or an authorized integration principal. Anyone else → `403 EXTERNAL_APPLY_CONFIRMATION_FORBIDDEN`.

**Request:** `confirmation_status` (required), `confirmation_source` (required), `notes` (optional).

**Validation:** Enum membership.

**Business Rules:**
- Sets `confirmation_status`, `confirmation_source`, `confirmed_at`, `confirmed_by`.
- **`confirmation_status` is not an application status and is never mapped onto one** (INV-012). Confirmation still creates no `applications` row.
- Already confirmed → `409 EXTERNAL_APPLY_EVENT_ALREADY_CONFIRMED`.
- **No two-way ATS integration is specified or implied.** FR-EXT-003 permits an integration source "pada fase yang mendukung"; this contract defines only the inbound confirmation shape and invents no synchronization, polling, callback, or webhook protocol.

**Success Response:** `200 OK`.

**Error Codes:** `EXTERNAL_APPLY_EVENT_ALREADY_CONFIRMED` (409) · `EXTERNAL_APPLY_CONFIRMATION_FORBIDDEN` (403) · `VALIDATION_FAILED` (422).

**Side Effects:** Event row updated; audit. Paired read: `GET /api/v1/candidate/external-apply-events` (own events only, query-scoped).

**Audit:** `external_apply_confirmed`.

**Notification / Outbox:** Optional notification to the vacancy owner.

**Idempotency:** **REQUIRED.**

**Concurrency:** Event row locked; second confirm → `409`.

**Source Requirement:** FR-EXT-003, FR-EXT-004 · INV-012, INV-022

---

## Part VIII — Selection, Evaluation, Offering, Outcome

### POST /api/v1/stages/{stage}/selector-assignments

**Surface:** `INERTIA_WEB`

**Purpose:** Assign a selector to one vacancy-specific recruitment stage (FR-HR-006, ADR-016).

**Authentication:** Required. **Authorization:** `HR_ADMIN` (Admin Kepegawaian) only. **A selector can never assign, extend, or revoke assignments** — including their own.

**Request:** `selector_user_id` (required).

**Validation:** User exists.

**Business Rules:**
- The target user must hold an **active** SELECTOR role assignment → else `422 SELECTOR_ROLE_REQUIRED`. **Holding the role is a precondition for being assigned; it grants no access on its own** (INV-037).
- Rejects a duplicate **active** assignment for the same stage and selector → `409 SELECTOR_ASSIGNMENT_ALREADY_ACTIVE`. Historical revoked assignments may exist in any number.
- Creates a `selection_stage_assignments` row with `assigned_by_user_id` and `assigned_at`.
- **Scope is inherited from the stage.** The assignment reaches only that stage's applications, schedules, and evaluations — never another stage of the same vacancy, and never another vacancy.

**Success Response:** `201 Created`.

**Error Codes:** `SELECTOR_ROLE_REQUIRED` (422) · `SELECTOR_ASSIGNMENT_ALREADY_ACTIVE` (409) · `AUTH_FORBIDDEN` (403) · `NOT_FOUND` (404).

**Side Effects:** Assignment row + audit. Paired routes: `GET /api/v1/stages/{stage}/selector-assignments` (list, including revoked history) and `POST /api/v1/selector-assignments/{assignment}/revoke` (sets `revoked_at` and `revoked_by_user_id`; **never deletes** — history is preserved, INV-037).

**Audit:** `selector_assigned` / `selector_assignment_revoked` (FR-AUD-001).

**Notification / Outbox:** Selector notified of assignment and revocation.

**Idempotency:** **REQUIRED.**

**Concurrency:** Conditional uniqueness on active assignment enforced by constraint plus row lock.

**Source Requirement:** FR-HR-006 · **INV-025, INV-037** · ADR-016

---

### POST /api/v1/applications/{application}/schedules

**Surface:** `INERTIA_WEB`  ·  **Active MVP route:** `POST /applications/{application}/schedules`

> **Selection Schedule Foundation v1 (SS-1, SS-2, SS-3, SS-5, SS-8 — approved and CLOSED, 27 August 2026) activates this operation.** Schedule authorization is a separate capability from Application Stage Movement and Recruitment Stage Authoring — it is not inherited from either, but independently sourced from `AUTHORIZATION_MATRIX.md` §4.8's own "Create schedule" row.
>
> **SS-1 — terminal application scheduling.** An application in `HIRED`, `REJECTED`, `WITHDRAWN`, or `NO_SHOW` cannot receive a new schedule → `409 APPLICATION_TERMINAL`, reusing the same code and terminal-status set `/move-stage` already uses.
>
> **SS-2 — schedule stage alignment.** `recruitment_stage_id` does **not** need to equal `applications.current_stage_id`. A recruiter may schedule any active stage of the same vacancy in advance, independent of the application's current position — the same "stage identity, not position" principle MS-4 already established for `/move-stage`. Creating a schedule never moves the application; `applications.current_stage_id` is untouched.
>
> **SS-3 — inactive target stage eligibility.** The target `recruitment_stage_id` **must be `active`** at creation → else `422 VALIDATION_FAILED`. Rechecked from a freshly locked row inside the write transaction, mirroring `/move-stage`'s post-lock stage-active recheck.
>
> **SS-5 — past schedule policy.** `starts_at` must be a **future** instant relative to authoritative server time at the moment of commit → else `422 SCHEDULE_TIME_INVALID`. No minimum lead time beyond "strictly future" — a request at `now + 1 second` is valid.
>
> **SS-8 — RA-2 applies to create.** Beyond the `COMPANY_SCOPE` object-authorization check, a successful create additionally requires, inside the same transaction, the same gate `/transition` and `/move-stage` already enforce: the owning company's `verification_status = VERIFIED` (else `403 VACANCY_COMPANY_NOT_VERIFIED`) and the vacancy's `current_status` ∈ `{PUBLISHED, CLOSED, EXPIRED}` (else `409 APPLICATION_VACANCY_NOT_PROCESSABLE`). This is the same `ApplicationProcessingGate` already reused twice, not a second processing-state policy. Applies identically to `SUPER_ADMIN` — no source exempts it.
>
> **Actor set active in Foundation v1:** `COMPANY_RECRUITER`, `COMPANY_ADMIN` (`COMPANY_SCOPE`), `SUPER_ADMIN` (`ALLOW`). `CAMPUS_SCOPE` (`HR_ADMIN`) is **not** activated — no Campus vacancy runtime exists. Career Center remains `DENY` (`AUTHORIZATION_MATRIX.md:85`: "cannot... create schedules... for any company"). Selectors remain `DENY` for schedule writes (§4.8 "Create schedule" row) — `pic_user_id` is a plain optional user reference, never a selector-role or `selection_stage_assignments` requirement. Auditor remains `DENY` for writes.

**Purpose:** Create a selection appointment (FR-SEL-001).

**Authentication:** Required. **Authorization:** Vacancy owner — `COMPANY_SCOPE` for company vacancies, `CAMPUS_SCOPE` for campus vacancies (inert, no Campus runtime). Career Center and Selectors cannot create schedules.

**Request:** `recruitment_stage_id` (required), `selection_type` (required), `starts_at` (required), `ends_at`, `timezone` (required), `method` (required — online or on-site), `location`, `meeting_url`, `pic_user_id`, `instructions`, `attachment` (optional upload reference — **not activated in Foundation v1**; see the Selection Schedule Foundation v1 amendment note below).

**Validation:**
- `ends_at` after `starts_at` where supplied → else `422 SCHEDULE_TIME_INVALID`.
- `starts_at` strictly future → else `422 SCHEDULE_TIME_INVALID` (SS-5).
- **On-site requires `location`; online requires `meeting_url`** → else `422 SCHEDULE_METHOD_DETAIL_REQUIRED`.
- `timezone` is a **named zone**, never a fixed offset, so DST is handled correctly.
- `recruitment_stage_id` **must belong to the application's vacancy** and be `active` → else `422 STAGE_NOT_IN_VACANCY` (INV-019) or `422 VALIDATION_FAILED` (SS-3).

**Business Rules:**
- Terminal applications are rejected before any other check (SS-1) → `409 APPLICATION_TERMINAL`.
- Created at `status = SCHEDULED` with `revision_number = 0`. Appends a `selection_schedule_histories` row with `event_type = CREATED`.
- **Never mutates `applications.current_stage_id`, `applications.current_status`, or any application history** — scheduling is status-independent and stage-position-independent (SS-2).
- `attachment_storage_reference` implements the FR-SEL-001 "lampiran" field structurally, but no upload mechanism is wired to it in Foundation v1 — the field stays `null` on every row created by this milestone (see amendment note).

**Success Response:** `201 Created`.

**Error Codes:** `SCHEDULE_TIME_INVALID` (422) · `SCHEDULE_METHOD_DETAIL_REQUIRED` (422) · `STAGE_NOT_IN_VACANCY` (422) · `VALIDATION_FAILED` (422, SS-3 inactive target and request validation) · `APPLICATION_TERMINAL` (409, SS-1) · `AUTH_FORBIDDEN` (403) · `NOT_FOUND` (404) · `VACANCY_COMPANY_NOT_VERIFIED` (403, SS-8/RA-2) · `APPLICATION_VACANCY_NOT_PROCESSABLE` (409, SS-8/RA-2).

**Side Effects:** Schedule + history + audit + outbox.

**Audit:** `schedule_created`.

**Notification / Outbox:** Candidate and PIC (if `pic_user_id` set) notified; email per FR-NOTIF-002.

**Idempotency:** **REQUIRED.**

**Concurrency:** Application row locked, then vacancy, then target stage, then company — the same deterministic order `/move-stage` established, extended by inserting the new schedule row last. After locks, terminal eligibility, RA-2, and target-stage `active`/vacancy-membership are all rechecked against freshly locked rows.

**Source Requirement:** FR-SEL-001, FR-HR-005 · INV-019, INV-027

> **Attachment amendment (Selection Schedule Foundation v1).** FR-SEL-001's "lampiran" field has no frozen upload mechanism anywhere in this project — no MIME allowlist, size limit, or storage/download convention comparable to `CANDIDATE_DOCUMENT_UPLOAD_POLICY` (item 9) has ever been approved for schedule attachments. Inventing one here would be a new, unsourced infrastructure decision. `attachment_storage_reference` remains in the frozen schema, is accepted as `null` by every write path in this milestone, and **is not populated by any route**. Activating a real upload path is future scope, gated on that same kind of explicit policy decision.

---

### PATCH /api/v1/schedules/{schedule}

**Surface:** `INERTIA_WEB`  ·  **Active MVP route:** `PATCH /schedules/{schedule}`

> **Selection Schedule Foundation v1 amendment.** SS-1, SS-3, SS-5, and SS-8 apply to reschedule identically to create (terminal denial, inactive-stage denial, future-time requirement, RA-2 gate) — see the create section's blockquote for the full text of each. SS-2 is create-only (this operation never changes `recruitment_stage_id` — see below).

**Purpose:** Reschedule an appointment (FR-SEL-001).

**Authentication:** Required. **Authorization:** Vacancy owner — `COMPANY_SCOPE` / `CAMPUS_SCOPE` (inert), `SUPER_ADMIN` `ALLOW`. Career Center and Selectors cannot reschedule.

**Request:** Any of `starts_at`, `ends_at`, `timezone`, `method`, `location`, `meeting_url`, `pic_user_id`, `instructions`, plus `reason` (recommended). **`recruitment_stage_id` is never accepted here** — reschedule changes time/logistics, never the schedule's stage. A stage reassignment would be a new schedule, not a PATCH.

**Validation:** As for create (time, method-detail, future-`starts_at`), plus: the schedule's **existing** `recruitment_stage_id` must still be `active` (SS-3) → else `422 VALIDATION_FAILED`.

**Business Rules:**
- Only a schedule currently `SCHEDULED` may be rescheduled → `COMPLETED`, `CANCELLED`, or `NO_SHOW` → `409 SCHEDULE_INVALID_TRANSITION`.
- The parent application must be non-terminal (SS-1) → else `409 APPLICATION_TERMINAL`, no mutation.
- RA-2 applies identically to create (SS-8) → `403 VACANCY_COMPANY_NOT_VERIFIED` / `409 APPLICATION_VACANCY_NOT_PROCESSABLE`, no `SUPER_ADMIN` bypass.
- **`RESCHEDULED` is an event, not a status** (INV-027). A rescheduled appointment remains `SCHEDULED` at its new time.
- Atomically: updates the schedule values, **increments `revision_number`**, and appends a `selection_schedule_histories` row with `event_type = RESCHEDULED`, `previous_snapshot`, `resulting_revision_number`, actor, and reason.
- **History is preserved by the history entity, never by a status value.** No prior schedule value is lost.

**Success Response:** `200 OK` with the new `revision_number`.

**Error Codes:** `SCHEDULE_INVALID_TRANSITION` (409) · `SCHEDULE_TIME_INVALID` (422) · `SCHEDULE_METHOD_DETAIL_REQUIRED` (422) · `VALIDATION_FAILED` (422, SS-3) · `APPLICATION_TERMINAL` (409, SS-1) · `STALE_VERSION` (409) · `AUTH_FORBIDDEN` (403) · `NOT_FOUND` (404) · `VACANCY_COMPANY_NOT_VERIFIED` (403, SS-8/RA-2) · `APPLICATION_VACANCY_NOT_PROCESSABLE` (409, SS-8/RA-2).

**Side Effects:** Schedule + history + audit + outbox.

**Audit:** `schedule_updated`.

**Notification / Outbox:** Candidate and PIC (if set) notified of the change (FR-SEL-001 requires notification on change/cancel).

**Idempotency:** **REQUIRED.**

**Concurrency:** Schedule row locked first (the primary contended resource), then application, vacancy, target stage, and company for RA-2/eligibility/stage-active rechecks. `If-Match` on `revision_number`; a stale reschedule → `409 STALE_VERSION` before any lock is taken on the write path — no mutation, no history, no audit, no notification.

**Source Requirement:** FR-SEL-001 · **INV-027**

---

### POST /api/v1/schedules/{schedule}/cancel

**Surface:** `INERTIA_WEB`  ·  **Active MVP route:** `POST /schedules/{schedule}/cancel`

> **Selection Schedule Foundation v1 amendment.** SS-1 and SS-8 both narrow cancel in the **opposite** direction from create/reschedule: cancel is deliberately exempt from both the terminal-application block and the RA-2 processing gate, so a stale `SCHEDULED` record can always be administratively closed out. SS-3 similarly does not block cancel on an inactive stage.

**Purpose:** Cancel an appointment (FR-SEL-001).

**Authentication:** Required. **Authorization:** Vacancy owner — `COMPANY_SCOPE` / `CAMPUS_SCOPE` (inert), `SUPER_ADMIN` `ALLOW`. Career Center and Selectors cannot cancel.

**Request:** `reason` (recommended). **Validation:** None mandatory.

**Business Rules:**
- **SS-1 — cancel is permitted even when the parent application is terminal** (`HIRED`, `REJECTED`, `WITHDRAWN`, `NO_SHOW`) — `APPLICATION_TERMINAL` is never raised by this operation. This is deliberate administrative cleanup: a schedule created before the application became terminal must remain closeable.
- **SS-8 — RA-2 does not apply to cancel.** Company verification and vacancy processability are never checked; `ApplicationProcessingGate` is never called by this operation. Cancel remains available even while the company is `SUSPENDED`/non-verified or the vacancy is `SUSPENDED`.
- **SS-3 — cancel is permitted even when the referenced stage is `active = false`.** A schedule is never stranded by a later stage deactivation.
- Only a schedule currently `SCHEDULED` may be cancelled → `COMPLETED` or `NO_SHOW` → `409 SCHEDULE_INVALID_TRANSITION`; already `CANCELLED` → `409 SCHEDULE_INVALID_TRANSITION` (not a no-op).
- `SCHEDULED → CANCELLED`. Appends a `CANCELLED` history event. Cancellation preserves the entire schedule record and its history. Paired actions `POST /api/v1/schedules/{schedule}/complete` and `POST /api/v1/schedules/{schedule}/no-show` remain in the frozen long-run contract but carry **no runtime in Foundation v1** — see Part X.

**Success Response:** `200 OK`. **Error Codes:** `SCHEDULE_INVALID_TRANSITION` (409) · `AUTH_FORBIDDEN` (403) · `NOT_FOUND` (404).

**Side Effects:** Status + history + audit + outbox. **Audit:** `schedule_cancelled`. **Notification / Outbox:** Candidate and PIC (if set) notified.

**Idempotency:** **REQUIRED.** **Concurrency:** Schedule row locked; status rechecked from the locked row before mutation. No `If-Match`/`STALE_VERSION` — cancel uses row-lock-and-status-recheck, not optimistic versioning (that mechanism is reschedule-only, per its own frozen contract).

**Source Requirement:** FR-SEL-001 · INV-027

---

### GET /api/v1/schedules

**Surface:** `INERTIA_WEB`  ·  **Active MVP route:** `GET /schedules`

> **Selection Schedule Foundation v1 (SS-9 — approved and CLOSED, 27 August 2026) reclassifies this operation and its two paired routes** (`GET /api/v1/schedules/{schedule}`, `GET /api/v1/schedules/{schedule}/history`) **from the reserved/inactive `VERSIONED_API` surface to `INERTIA_WEB`**, the same MVP browser transport pattern SPEC-DOC-05, -07, and -08 already established (Laravel session guard + CSRF, no Sanctum, no `personal_access_tokens`). The `/api/v1` identifiers remain reserved/conceptual twins, exactly as every other reclassified operation's twin does. **No business rule changed** — authorization, scoping, and payload shape are identical regardless of surface. `HR_ADMIN`/`CAMPUS_SCOPE` remains **not** activated (no Campus runtime). `SELECTOR`'s `ASSIGNED_STAGE` grant remains inert for `COMPANY` vacancies — company-side selector assignment stays deferred, and no `selection_stage_assignments` runtime is fabricated to make that row usable.

**Purpose:** Scoped schedule list — *Jadwal Seleksi* for candidates, operational calendar for owners.

**Authentication:** Required.

**Authorization:** Query-scoped. Candidate → `OWN`, own applications' schedules only. Owner → `COMPANY_SCOPE` / `CAMPUS_SCOPE` (inert). `SUPER_ADMIN` → `ALLOW`. `AUDITOR` → `READ_ONLY` (read succeeds; write remains denied). **Selector → `ASSIGNED_STAGE` only** (INV-037), inert for `COMPANY` vacancies pending selector assignment. Career Center → `DENY`.

**Request:** Filters `application_id`, `recruitment_stage_id`, `status`, `starts_from`, `starts_to`. Sortable: `starts_at` (default).

**Validation:** Allow-listed filters and sort fields.

**Business Rules:** Candidates see their own appointment detail and instructions only — no internal `reason`, `actor_user_id`, raw `previous_snapshot`, or `attachment_storage_reference` is ever returned to a candidate. Paired routes: `GET /api/v1/schedules/{schedule}` and `GET /api/v1/schedules/{schedule}/history` (append-only event trail, same candidate-safe projection rule).

**Success Response:** `200 OK`, paginated. **Error Codes:** `VALIDATION_FAILED` (422) · `AUTH_FORBIDDEN` (403) · `NOT_FOUND` (404).

**Side Effects:** None. **Audit:** None. **Notification / Outbox:** None.

**Idempotency:** Safe method. **Concurrency:** None.

**Source Requirement:** FR-SEL-001, FSD §4.2 · INV-037

---

### POST /api/v1/applications/{application}/evaluations

**Surface:** `INERTIA_WEB`  ·  **Active MVP route:** `POST /applications/{application}/evaluations`

> **Evaluation / Scoring Foundation v1 (EV-1, EV-2, RC-1 — approved and CLOSED, 27 August 2026) activates this operation.** Evaluation authorization is a separate capability from Selection Schedule and Application Stage Movement — it is not inherited from either, but independently sourced from `AUTHORIZATION_MATRIX.md` §4.8's own "Create / update evaluation" row, which grants `COMPANY_RECRUITER`/`COMPANY_ADMIN` their own `COMPANY_SCOPE`, parallel to (not gated behind) Selector's `ASSIGNED_STAGE` grant.
>
> **EV-1 — terminal application eligibility.** An application in `HIRED`, `REJECTED`, `WITHDRAWN`, or `NO_SHOW` cannot receive a new evaluation, an update to an existing draft, or a submit → `409 APPLICATION_TERMINAL` in all three cases, reusing the same code and terminal-status set `/move-stage` and Selection Schedule already use.
>
> **EV-2 — evaluation stage alignment.** `recruitment_stage_id` does **not** need to equal `applications.current_stage_id`. An evaluator may record against any `active` stage of the same vacancy — the same "stage identity, not position" principle MS-4/SS-2 already established. Create and update both require the referenced stage to be `active`; **submit is deliberately exempt** — an existing draft remains submittable even if its stage is later disabled, so a valid draft is never stranded by an unrelated Stage Authoring action. Evaluation never mutates `applications.current_stage_id`.
>
> **RC-1 — RA-2 applies to create, update, and submit.** Beyond the `COMPANY_SCOPE` object-authorization check, each of these three operations additionally requires, inside the same transaction, the same gate `/transition`, `/move-stage`, and Selection Schedule create/reschedule already enforce: the owning company's `verification_status = VERIFIED` (else `403 VACANCY_COMPANY_NOT_VERIFIED`) and the vacancy's `current_status` ∈ `{PUBLISHED, CLOSED, EXPIRED}` (else `409 APPLICATION_VACANCY_NOT_PROCESSABLE`). This is the same `ApplicationProcessingGate` already reused three times, not a second processing-state policy. Applies identically to `SUPER_ADMIN` — no source exempts it.
>
> **Actor set active in Foundation v1:** `COMPANY_RECRUITER`, `COMPANY_ADMIN` (`COMPANY_SCOPE`), `SUPER_ADMIN` (`ALLOW`). `CAMPUS_SCOPE` (`HR_ADMIN`) is **not** activated — no Campus vacancy runtime exists. Career Center remains `DENY` (§4.8's own row). `AUDITOR` remains `DENY` — the Evaluation matrix rows carry no `READ_ONLY` carve-out, unlike Selection Schedule's read rows. Selector's `ASSIGNED_STAGE` grant remains **inert** for `COMPANY` vacancies — company-side selector assignment stays deferred (matrix footnote 23), and no `selection_stage_assignments` runtime is fabricated to activate it; `SELECTOR_NOT_ASSIGNED_TO_STAGE` is therefore unreachable in this milestone. Recruiter/Admin evaluation is fully independent of Selector Assignment.
>
> **Author-only PATCH and submit.** "Editable by its author until submitted" and "author only" are business rules distinct from object authorization — `SUPER_ADMIN`'s unconditional `ALLOW` reaches (may read) any evaluation, but does **not** exempt it from the author-only restriction on `PATCH`/`submit`, since no source grants that exemption. Violation → `403 EVALUATION_NOT_OWNED` (already-registered code).
>
> **PATCH scope (Foundation v1).** The frozen contract names no exact PATCH field set beyond "author only, before submission." Foundation v1 accepts scalar-field updates — `recruitment_stage_id` (per EV-2), `recommendation`, `comments`, `total_score` — matching the same fields create accepts, using the established partial-update (`sometimes`) convention. **Item-set mutation via PATCH is out of scope for Foundation v1** — no source defines whether a PATCH's `items[]` replaces the whole set, merges, or is rejected outright, and inventing collection semantics here would be an unsourced business rule. `items[]` remains create-only in this milestone.

**Purpose:** Record a candidate evaluation (FR-SEL-002, FR-HR-007).

**Authentication:** Required. **Authorization:** Vacancy owner — `COMPANY_SCOPE` for company vacancies, `CAMPUS_SCOPE` for campus vacancies (inert, no Campus runtime), **or** a selector with an **active** `selection_stage_assignments` row for the target stage (inert for `COMPANY` vacancies in Foundation v1) → else `403 SELECTOR_NOT_ASSIGNED_TO_STAGE` (INV-037). Role SELECTOR alone is never sufficient. Career Center and Auditor cannot create, read, update, or submit evaluations.

**Request:** `recruitment_stage_id` (required), `recommendation`, `comments`, `total_score`, `items[]` — each `{ criterion, weight?, score?, comment?, sort_order }`. `evaluator_user_id` is **not** a request field — it is always the authenticated actor; no delegation exists.

**Validation:** `recruitment_stage_id` belongs to the application's vacancy (INV-019) and is `active` (EV-2) → else `422 STAGE_NOT_IN_VACANCY` or `422 VALIDATION_FAILED`. Numeric fields numeric where supplied.

**Business Rules:**
- Terminal applications are rejected before any other check (EV-1) → `409 APPLICATION_TERMINAL`.
- **No scoring algorithm is assumed and none is computed.** `weight`, `score`, and `total_score` are all optional; an evaluator may submit comments and a recommendation with no numbers at all (FR-SEL-002 lists criteria, weight, score, comments, recommendation as available fields, not as a required rubric). No weight-sum rule, no automatic aggregation, no pass/fail threshold.
- **No ranking, normalization, percentile, candidate comparison, psychometric engine, or AI recommendation exists anywhere in this API.** No endpoint returns a ranked candidate list, and no field feeds one.
- Created as a draft; editable by its author until submitted.
- **Never mutates `applications.current_stage_id` or `applications.current_status`, creates no offer, and creates no recruitment outcome.**
- **An evaluation result never becomes a candidate-facing status by itself** (FR-HR-007). Changing the candidate's status is a separate, explicit transition.
- Evaluations are **never candidate-visible** — no Evaluation surface of any kind is exposed to Candidate actors.

**Success Response:** `201 Created`.

**Error Codes:** `SELECTOR_NOT_ASSIGNED_TO_STAGE` (403) · `STAGE_NOT_IN_VACANCY` (422) · `VALIDATION_FAILED` (422) · `APPLICATION_TERMINAL` (409, EV-1) · `AUTH_FORBIDDEN` (403) · `NOT_FOUND` (404) · `VACANCY_COMPANY_NOT_VERIFIED` (403, RC-1/RA-2) · `APPLICATION_VACANCY_NOT_PROCESSABLE` (409, RC-1/RA-2).

**Side Effects:** Evaluation + items. Paired routes: `GET /api/v1/applications/{application}/evaluations` (scoped — a selector sees the assigned stage's evaluations only), `GET /api/v1/evaluations/{evaluation}`, `PATCH /api/v1/evaluations/{evaluation}` (author only, before submission — scalar fields only in Foundation v1, see amendment note above), `POST /api/v1/evaluations/{evaluation}/submit` (finalizes; sets `submitted_at`; afterwards `409 EVALUATION_ALREADY_SUBMITTED`).

**Audit:** `evaluation_created` / `_updated` / `_submitted` (FR-AUD-001).

**Notification / Outbox:** Owner notified on submission. **Never the candidate.** "Owner" resolves to every active member of the owning company, the same recipient-resolution rule already established for Application submit/withdraw notifications.

**Idempotency:** **REQUIRED** on submit. **Not required on create or update** — the frozen contract scopes this requirement to submit only.

**Concurrency:** Application row locked, then vacancy, then target stage, then company — the same deterministic order Selection Schedule create established, extended for the new evaluation row inserted last. Evaluation row locked on submit; a second submit → `409 EVALUATION_ALREADY_SUBMITTED`.

**Source Requirement:** FR-SEL-002, FR-HR-006, FR-HR-007 · **INV-019, INV-037**

---

### POST /api/v1/applications/{application}/offers

**Surface:** `INERTIA_WEB`  ·  **Active MVP route:** `POST /applications/{application}/offers`

> **Offering Foundation v1 (OF-1, OF-2, RC-1 — approved and CLOSED, 27 August 2026) activates create, update, send, accept, and reject.**
>
> **OF-1 — Offering / application status independence.** Create, update, and send never mutate `applications.current_status` or `applications.current_stage_id` — no new RA-1 edge is added merely to make `OFFERED` reachable; the offer lifecycle is represented entirely by `offers.status`. The already-frozen candidate **accept** side effect (`applications.current_status = HIRED`, `hired_at` set, an `application_status_histories` row with `event_type = OFFER_ACCEPTED`) is preserved exactly as this section already specified — OF-1 does not remove it. **Reject** does not mutate application status either, exactly as already specified below.
>
> **RC-1 — RA-2 applies to the recruiter-side operations only.** Create, update (`PATCH`), and send all additionally require, inside the same transaction, the same gate `/transition`, `/move-stage`, Selection Schedule, and Evaluation already enforce: the owning company's `verification_status = VERIFIED` (else `403 VACANCY_COMPANY_NOT_VERIFIED`) and the vacancy's `current_status` ∈ `{PUBLISHED, CLOSED, EXPIRED}` (else `409 APPLICATION_VACANCY_NOT_PROCESSABLE`). Applies identically to `SUPER_ADMIN` — no source exempts it. **Candidate accept/reject are explicitly RA-2-exempt** — once a valid offer has been sent, the candidate's right to respond is governed by offer ownership, offer lifecycle, and the response deadline, never by later recruiter/company processing eligibility.
>
> **Actor set active in Foundation v1 (recruiter-side):** `COMPANY_RECRUITER`, `COMPANY_ADMIN` (`COMPANY_SCOPE`), `SUPER_ADMIN` (`ALLOW`). `CAMPUS_SCOPE` (`HR_ADMIN`) is **not** activated. Career Center, Selector, and Auditor are `DENY`.
>
> **Document reference amendment (Offering Foundation v1).** Same gap and same resolution already established for Selection Schedule's `attachment` field: no MIME allowlist, size limit, or storage/download convention has ever been approved for offer documents, and no generic private-upload infrastructure exists in this codebase to reuse safely (`UploadCandidateDocument` is narrowly bound to its own explicitly-approved PDF/10 MiB policy, not transferable by analogy). `document_reference` remains in the frozen schema, is accepted as `null` by every write path in this milestone, and is not populated by any route. Activating a real upload path is future scope, gated on the same kind of explicit policy decision.
>
> **`note` is candidate-facing offer content**, not an internal recruiter-only field — it is part of what `send` delivers to the candidate, distinct from evaluation `comments`, which are internal-only.

**Purpose:** Create an offering (FR-SEL-003).

**Authentication:** Required. **Authorization:** Vacancy owner — `COMPANY_SCOPE` for company vacancies, `CAMPUS_SCOPE` for campus vacancies (inert, no Campus runtime). Selectors, Career Center, and Auditor cannot create, update, or send offers.

**Request:** `response_deadline`, `note`, `document_reference` (optional upload — **not activated in Foundation v1**; see the amendment note above), `send_now` (boolean, default false).

**Validation:** `response_deadline` in the future where supplied.

**Business Rules:**
- Terminal applications may not receive a new offer → `409 APPLICATION_TERMINAL`.
- Created at `status = DRAFT`, or `SENT` when `send_now` is true. FR-SEL-003 stores application, offering date, response deadline, note, and document.
- **Contains no onboarding date, contract-signing date, start date, or first-working-day field.** No such field exists anywhere in the model, which is what makes the Time-to-Fill definition unfalsifiable.
- Rejects creation when another offer on this application is already `ACCEPTED` → `409 OFFER_ALREADY_ACCEPTED_FOR_APPLICATION` (INV-031).
- Never mutates `applications.current_status` or `applications.current_stage_id` (OF-1).

**Success Response:** `201 Created`.

**Error Codes:** `OFFER_ALREADY_ACCEPTED_FOR_APPLICATION` (409) · `APPLICATION_TERMINAL` (409) · `VALIDATION_FAILED` (422) · `AUTH_FORBIDDEN` (403) · `NOT_FOUND` (404) · `VACANCY_COMPANY_NOT_VERIFIED` (403, RC-1/RA-2) · `APPLICATION_VACANCY_NOT_PROCESSABLE` (409, RC-1/RA-2). `PATCH`/`send` additionally use `OFFER_INVALID_TRANSITION` (409) when the offer is not `DRAFT`.

**Side Effects:** Offer row + audit; outbox only when sent. Paired routes: `PATCH /api/v1/offers/{offer}` (DRAFT only; mutable fields in Foundation v1: `response_deadline`, `note`, `document_reference` — `application_id` and `offered_by_user_id` are never mutable), `POST /api/v1/offers/{offer}/send` (`DRAFT → SENT`, sets `sent_at`, notifies candidate; **REQUIRED** `Idempotency-Key`).

**Audit:** `offer_created` / `_sent` / `_updated`.

**Notification / Outbox:** On send — candidate notification and email (FR-NOTIF-002 "Offering diterbitkan"). No candidate notification on plain `DRAFT` create or on `PATCH`.

**Idempotency:** **REQUIRED** on send. Not required on create (including `send_now = true`) or update.

**Concurrency:** Application row locked (for the accepted-offer check and RA-2), then vacancy, then company, for create. `PATCH`/send lock application, then the target offer, then vacancy, then company — the deterministic order this whole domain uses, chosen specifically so accept's own application-first lock (needed for INV-031 correctness across different offer rows) can never invert against send/update on the same or a different offer.

**Source Requirement:** FR-SEL-003 · INV-031

---

### POST /api/v1/offers/{offer}/accept

**Surface:** `INERTIA_WEB`  ·  **Active MVP route:** `POST /offers/{offer}/accept`

> **OF-2 — candidate offer response transport (approved and CLOSED, 27 August 2026).** This operation and `POST /offers/{offer}/reject` are reclassified from the reserved `VERSIONED_API` surface to `INERTIA_WEB`, the same SPEC-DOC-05/-07/-08/SS-9 pattern — session guard, CSRF, no Sanctum, no `personal_access_tokens`. Their `/api/v1` twins stay reserved. **No proxy response exists for either operation, for any actor** — `SUPER_ADMIN`'s unconditional recruiter-side `ALLOW` does **not** extend here; this is an explicit Offering-specific exception, the same shape already established for `/transition`'s and `/move-stage`'s "candidate's own act" rule. `COMPANY_RECRUITER`, `COMPANY_ADMIN`, `SUPER_ADMIN`, `CAREER_CENTER`, `SELECTOR`, and `AUDITOR` are all `DENY`.

**Purpose:** Candidate accepts an offering (FR-SEL-004). **The Time-to-Fill anchor.**

**Authentication:** Required. **Authorization:** `OWN` — the candidate of the offer's application, and no one else. A recruiter can never accept on a candidate's behalf.

**Request:** Empty body. Optional `note`.

**Validation:** None at transport level.

**Business Rules — all inside one transaction with the application and offer rows locked:**

1. Offer must be `SENT` or `PENDING_RESPONSE` → a DRAFT offer gives `409 OFFER_NOT_SENT`; an already-answered offer gives `409 OFFER_ALREADY_RESPONDED`.
2. Not past `response_deadline` and not `EXPIRED` → else `409 OFFER_EXPIRED`.
3. **No other offer on this application may already be `ACCEPTED`** → else `409 OFFER_ALREADY_ACCEPTED_FOR_APPLICATION` (INV-031).
4. On success, atomically (FR-SEL-004):
   - offer `status = ACCEPTED`, `responded_at`, and **`offer_accepted_at` set — this is the authoritative Time-to-Fill endpoint**;
   - application `current_status = HIRED` and `hired_at` set;
   - `application_status_histories` row with `event_type = OFFER_ACCEPTED`;
   - `audit_logs` row;
   - `email_outbox` rows.

**Time-to-Fill** = `offers.offer_accepted_at − vacancies.published_at` (INV-013, FR-REP-004). Where a vacancy has several accepted offers because `openings_count > 1`, the vacancy-level metric uses the **earliest** `offer_accepted_at`. It is **undefined** while `published_at` is null and is reported as unavailable, never substituted. **It never uses an onboarding, contract-signing, start, or first-working-day date.**

**Success Response:** `200 OK` with the offer at ACCEPTED and the application at HIRED.

**Error Codes:** `OFFER_NOT_SENT` (409) · `OFFER_ALREADY_RESPONDED` (409) · `OFFER_EXPIRED` (409) · `OFFER_ALREADY_ACCEPTED_FOR_APPLICATION` (409) · `AUTH_FORBIDDEN` (403) · `NOT_FOUND` (404).

**Side Effects:** As above, one transaction.

**Audit:** `offer_accepted` — actor, offer, application, `offer_accepted_at`.

**Notification / Outbox:** **Vacancy owner notified** (FR-NOTIF-002 "Offering accepted/rejected"); candidate confirmation.

**Idempotency:** **REQUIRED.** A replayed accept with the same key returns the retained `200`, not a second acceptance.

**Concurrency — explicitly defined:**
- **Only one acceptance can win.** The offer and application rows are locked for the duration; the conditional uniqueness rule "at most one `ACCEPTED` offer per application" (INV-031) is enforced by a database constraint as the backstop.
- Two concurrent accepts of the **same** offer: one commits; the other observes the changed status and returns `409 OFFER_ALREADY_RESPONDED` — **or**, when an `Idempotency-Key` matches, the retained response, so an honest client retry is never punished.
- Two concurrent accepts of **different** offers on one application: exactly one succeeds; the other receives `409 OFFER_ALREADY_ACCEPTED_FOR_APPLICATION`.
- A lost race therefore always surfaces as a `409`, never as two accepted offers or a corrupted `hired_at`.

**Source Requirement:** FR-SEL-003, FR-SEL-004, FR-REP-004 · **INV-013, INV-016, INV-026, INV-031**

---

### POST /api/v1/offers/{offer}/reject

**Surface:** `INERTIA_WEB`  ·  **Active MVP route:** `POST /offers/{offer}/reject`

> **OF-2 — see the accept section's amendment note above for the full transport and authorization rule; identical here.** Reject never mutates `applications.current_status` (OF-1) — the application does not automatically become `REJECTED`, confirmed by this section's own pre-existing text below.

**Purpose:** Candidate declines an offering (FR-SEL-005).

**Authentication:** Required. **Authorization:** `OWN` — the offer's candidate.

**Request:** `rejection_reason` (**optional** — FR-SEL-005 states the reason is optional).

**Validation:** Reason length where supplied.

**Business Rules:** Offer must be `SENT` or `PENDING_RESPONSE` and not past deadline. Sets `status = REJECTED`, `responded_at`, and the reason where given. **The application and its history remain fully stored** (FR-SEL-005) — the owner may continue with other candidates. The application does **not** automatically become `REJECTED`; that is a separate explicit transition.

**Success Response:** `200 OK`. **Error Codes:** `OFFER_ALREADY_RESPONDED` (409) · `OFFER_EXPIRED` (409) · `OFFER_NOT_SENT` (409) · `AUTH_FORBIDDEN` (403).

**Side Effects:** Offer + history + audit + outbox. **Audit:** `offer_rejected`. **Notification / Outbox:** Vacancy owner notified.

**Idempotency:** **REQUIRED.** **Concurrency:** Offer row locked; second response → `409`.

**Source Requirement:** FR-SEL-005 · INV-016

> **Offer expiry** is not an endpoint. A scheduled job transitions `SENT`/`PENDING_RESPONSE` offers past `response_deadline` to `EXPIRED`, appending audit and notifying both parties. Expiry is time-driven, never client-driven.

---

### POST /api/v1/recruitment-outcomes

**Surface:** `INERTIA_WEB`  ·  **Active MVP route:** `POST /recruitment-outcomes`

> **Recruitment Outcome Foundation v1 (OC-1, RC-2 — approved and CLOSED, 27 August 2026) activates create, list, and correction (`PATCH`) for `source_type = INTERNAL_APPLICATION` only.**
>
> **OC-1 — Internal Application outcome vocabulary.** For `source_type = INTERNAL_APPLICATION`, the `outcome` field accepts exactly `HIRED`, `REJECTED`, `WITHDRAWN`, `NO_SHOW` in Foundation v1 — enforced by request/runtime validation, **not** a database CHECK constraint (the `outcome` column itself remains an unconstrained `string(64)`, unchanged). No other string is accepted for `INTERNAL_APPLICATION` (`ACCEPTED`, `DECLINED`, `OTHER`, and every other value → `422 VALIDATION_FAILED`). This vocabulary is scoped to `INTERNAL_APPLICATION` only and must not be assumed to apply to a future `EXTERNAL_APPLY` runtime.
>
> **RC-2 — Career Center outcome scope for this milestone.** The matrix's existing Career Center `ALLOW` for "Record / correct recruitment outcome" and "List outcomes" (footnote 29) is **alumni/reporting scope** — i.e. `EXTERNAL_APPLY`. It is **not activated** for this `INTERNAL_APPLICATION`/`COMPANY`-only milestone: Career Center is `DENY` on every route below. The matrix grant itself is untouched and remains available for the future Alumni/External Apply Outcome milestone that activates it.
>
> **Actor set active in Foundation v1:** `COMPANY_RECRUITER`, `COMPANY_ADMIN` (`COMPANY_SCOPE`, create/list/correct), `SUPER_ADMIN` (`ALLOW`), `AUDITOR` (`READ_ONLY` — list only, no write). `CAREER_CENTER` (RC-2, above), `SELECTOR`, and `CANDIDATE` are `DENY`. `HR_ADMIN`/`CAMPUS_SCOPE` remains **deferred** — no Campus vacancy runtime exists.
>
> **H-5 — `GET /recruitment-outcomes/incomplete` selection semantics (approved and CLOSED, 27 August 2026).** An `INTERNAL_APPLICATION` is **incomplete** iff (1) `applications.current_status` ∈ `{HIRED, REJECTED, WITHDRAWN, NO_SHOW}` **and** (2) no `recruitment_outcomes` row exists for it (`source_type = INTERNAL_APPLICATION`, matching `application_id`). No other qualifier — explicitly **no** minimum age/waiting period, **no** accepted-offer or offer-existence requirement, **no** vacancy-lifecycle or company-`VERIFIED` gate, **no** RA-2, **no** selection-stage or schedule requirement. The route is **read/reporting only**: it never creates an outcome, mutates `applications.current_status`/`current_stage_id`, sends a notification, or queues an `email_outbox` row. Authorization mirrors create/list: `COMPANY_SCOPE` (recruiter/admin), `ALLOW` (Super Admin), `READ_ONLY` (Auditor); `CAREER_CENTER` (RC-2), `SELECTOR`, and `CANDIDATE` are `DENY`. `HR_ADMIN`/`CAMPUS_SCOPE` and `EXTERNAL_APPLY` remain deferred.
>
> **`reported_by_source` is client-supplied**, not server-derived — the request table below and the schema `CHECK` constraint already fix its exact vocabulary (`CANDIDATE` \| `COMPANY` \| `CAMPUS_STAFF` \| `INTEGRATION`); no additional actor-to-source mapping is invented or enforced. `confirmed_by`/`confirmed_at` are **not** request fields — they are server-set to the authenticated actor and the record time, exactly the same "who/when performed this write" pattern already used for `offered_by_user_id`/`offered_at`.

**Purpose:** Record the final recruitment outcome (FSD §4.3 *Outcome Rekrutmen*, FR-EXT-004, FR-REP-002).

**Authentication:** Required. **Authorization:** Vacancy owner — `COMPANY_SCOPE` for `INTERNAL_APPLICATION`/`COMPANY` vacancies in Foundation v1; `CAMPUS_SCOPE` remains deferred; Career Center's alumni-outcome grant remains deferred/inactive for this milestone (RC-2).

**Request:**

| Field | Required | Notes |
| --- | --- | --- |
| `source_type` | Yes | `INTERNAL_APPLICATION` \| `EXTERNAL_APPLY` |
| `application_id` | Conditional | **Required** for `INTERNAL_APPLICATION`; **must be absent** for `EXTERNAL_APPLY` |
| `external_apply_event_id` | Conditional | **Required** for `EXTERNAL_APPLY`; **must be absent** for `INTERNAL_APPLICATION` |
| `outcome` | Yes | Normalized outcome value |
| `reported_by_source` | Yes | `CANDIDATE` \| `COMPANY` \| `CAMPUS_STAFF` \| `INTEGRATION` |
| `notes` | No | |

**Validation:** **Exactly one source reference must be present** (INV-022). Both present or neither present → `422 OUTCOME_SOURCE_INVALID`.

**Business Rules:**
- The XOR is enforced in the Action **and** by a database CHECK constraint. Both-null and both-populated rows are unrepresentable. Foundation v1 accepts `source_type = INTERNAL_APPLICATION` only — a request with `source_type = EXTERNAL_APPLY` is rejected (no `EXTERNAL_APPLY` runtime exists yet), never silently reinterpreted.
- **No `vacancy_id` or `candidate_profile_id` is accepted or stored.** Both are reachable through the single populated source reference; storing a second copy would create an authority able to contradict its own parent (decision D-4). Reporting joins through the source.
- `reported_by_source` records *who reported*, distinct from `source_type` which records *which recruitment path*. Recording an outcome implies **no** two-way ATS integration.
- `outcome` for `INTERNAL_APPLICATION` is validated against the OC-1 vocabulary (`HIRED`, `REJECTED`, `WITHDRAWN`, `NO_SHOW`) → `422 VALIDATION_FAILED` otherwise.
- One outcome per source → `409 OUTCOME_ALREADY_RECORDED`.
- **No terminal-application gate.** Unlike Evaluation (EV-1) and Selection Schedule (SS-1), outcome recording is never denied because the application is terminal — a terminal lifecycle state is the expected, common case for reporting an outcome.
- **No offer dependency.** Recording `HIRED` never requires an `ACCEPTED` offer to exist, never creates or mutates an offer, and never sets `applications.current_status`. Outcome recording is explicit reporting, not an offer or application side effect.
- **Application status/stage independence.** Recording any outcome value never mutates `applications.current_status` or `current_stage_id`, and no source requires synchronizing them — application lifecycle and outcome reporting are independently maintained.
- **A missing outcome never blocks anything** (INV-014, FR-NOTIF-004). **`GET /recruitment-outcomes/incomplete` is active** (H-5, above) and strictly read-only — no vacancy-creation endpoint in this contract reads `recruitment_outcomes`, and the gate does not exist and must never be added.

**Success Response:** `201 Created`.

**Error Codes:** `OUTCOME_SOURCE_INVALID` (422) · `OUTCOME_ALREADY_RECORDED` (409) · `VALIDATION_FAILED` (422, OC-1 vocabulary) · `AUTH_FORBIDDEN` (403) · `NOT_FOUND` (404).

**Side Effects:** Outcome row + audit. Paired routes: `GET /recruitment-outcomes` (scoped list), `PATCH /recruitment-outcomes/{outcome}` (authorized correction, audited — the source reference itself is **not** editable; mutable fields are `outcome` (OC-1-validated), `reported_by_source`, `notes`), `GET /recruitment-outcomes/incomplete` (H-5 — active, read-only terminal-without-outcome report).

**Audit:** `recruitment_outcome_recorded` / `_updated`.

**Notification / Outbox:** **NONE.** No candidate, company, or Career Center notification and no `email_outbox` row is queued by create or correction. Reminder emails for incomplete outcomes remain scheduler-driven future scope (FR-NOTIF-004), not triggered by this milestone.

**Idempotency:** **REQUIRED** on create, the same `IdempotencyGuard` pattern as every other `REQUIRED` operation in this contract — a missing key is accepted and simply forfeits replay protection, never a hard `4xx`. Not required on `PATCH` (no source names a correction idempotency requirement).

**Concurrency:** Conditional uniqueness per source enforced by constraint; the application row is additionally locked for `INTERNAL_APPLICATION` create so two concurrent creates for the same application serialize deterministically, with the partial unique index (`uq_recruitment_outcomes_application`) as the backstop — never a raw `SQLSTATE`.

**Source Requirement:** FR-EXT-004, FR-NOTIF-004, FR-REP-002, FR-HR-005 · **INV-014, INV-022**

> **Vacancy-level outcome (H-2) remains unresolved.** `recruitment_outcomes` is candidate-level: `source_type` always resolves to one candidate. An outcome such as "closed, no suitable candidate" is **not recordable**, and no endpoint invents one. This limits FR-REP-002's incomplete-outcome monitoring to candidate-level outcomes and is carried forward as an open item.

---

## Part IX — Notifications, Configuration, Reporting, Audit

### GET /api/v1/notifications

**Surface:** `VERSIONED_API`

**Purpose:** The actor's in-app notification centre (FSD §4.2–§4.5 Notifikasi).

**Authentication:** Required. **Authorization:** `OWN` — scoped by `user_id`. Never another user's notifications.

**Request:** Filters `read` (boolean), `type`, `created_from`, `created_to`. Sortable: `created_at` (default, descending).

**Validation:** Allow-listed filters and sort fields.

**Business Rules:**
- **`notifications` is in-app truth; `email_outbox` is delivery infrastructure.** They are two records of one business event, neither derived from the other. One event may produce both, either, or neither.
- **No outbox state, retry count, attempt schedule, dead-letter status, or delivery error is ever exposed to a normal user.** A candidate never learns that their notification email is on attempt 3 — that is operational data, surfaced only through monitoring and the Super Admin surface.
- Returns title, type, body reference, related object type and id, and `read_at`. The related object type is a **stable logical name**, never an implementation class path (INV-033).

**Success Response:** `200 OK`, paginated.

**Error Codes:** `UNAUTHENTICATED` (401) · `VALIDATION_FAILED` (422).

**Side Effects:** None. Paired routes: `POST /api/v1/notifications/{notification}/read` and `POST /api/v1/notifications/read-all` — both `OWN`-scoped, returning `204`.

**The unread count has no dedicated endpoint.** It is returned by `GET /api/v1/me` (`unread_notification_count`) and in this endpoint's `meta.unread_count`. A standalone count endpoint exists mainly to be polled, and polling for a badge is implementation convenience rather than a requirement — FSD names a notification centre, not a polling contract (`API_SIZE_REVIEW.md` D-2).

**Audit:** None — reading one's own notifications is not an audited event under FR-AUD-001.

**Notification / Outbox:** None.

**Idempotency:** Safe method; mark-read is naturally idempotent.

**Concurrency:** None.

**Source Requirement:** FR-NOTIF-001, FR-NOTIF-002 · FSD §4.2 · INV-033

---

### GET /api/v1/candidate/saved-vacancies

**Surface:** `VERSIONED_API`

**Purpose:** *Lowongan Tersimpan* (FSD §4.2).

**Authentication:** Required. **Authorization:** `OWN`.

**Request:** Sortable: `saved_at` (default). **Validation:** Allow-listed sort fields.

**Business Rules:** Returns saved vacancies with their **current** public status, so a candidate can see that a saved vacancy has closed or expired. Saving is a convenience action with no effect on eligibility, ranking, or application state. Paired routes: `POST /api/v1/candidate/saved-vacancies` (body `vacancy_id`; duplicate save is idempotent by the `(candidate_profile_id, vacancy_id)` uniqueness rule) and `DELETE /api/v1/candidate/saved-vacancies/{vacancy}` → `204`.

**Success Response:** `200 OK`, paginated. **Error Codes:** `NOT_FOUND` (404) · `VALIDATION_FAILED` (422).

**Side Effects:** Saved-vacancy row on write. **Audit:** None. **Notification / Outbox:** None.

**Idempotency:** Naturally idempotent. **Concurrency:** Uniqueness constraint.

**Source Requirement:** FSD §4.2

---

### GET /api/v1/admin/smtp-configuration

**Surface:** `INERTIA_WEB`

**Purpose:** Read SMTP configuration metadata (FR-NOTIF-005, ADR-015).

**Authentication:** Required. **Authorization:** **`SUPER_ADMIN` only.** No other role, including Auditor.

**Request:** None. **Validation:** None.

**Business Rules:**

**The credential is never returned. Not masked, not truncated, not length-hinted — absent.**

Returned fields: `host`, `port`, `encryption_mode`, `username`, `from_address`, `from_name`, `reply_to_address`, `timeout_seconds`, `max_attempts`, `retry_backoff_seconds`, `is_active`, `last_tested_at`, `last_test_result`, `updated_by_user_id`, `updated_at`, and:

```json
"secret_configured": true
```

`secret_configured` is a **boolean only**. It states whether a credential exists. It never conveys the value, its length, or any derivative (INV-035).

**Success Response:** `200 OK`.

**Error Codes:** `AUTH_FORBIDDEN` (403) · `UNAUTHENTICATED` (401).

**Side Effects:** None.

**Audit:** `smtp_configuration_viewed` — configuration access is a sensitive admin action under FR-AUD-001.

**Notification / Outbox:** None.

**Idempotency:** Safe method.

**Concurrency:** None.

**Source Requirement:** FR-NOTIF-005 · **INV-035, INV-036** · ADR-015

---

### PUT /api/v1/admin/smtp-configuration

**Surface:** `INERTIA_WEB`

**Purpose:** Update SMTP configuration, including credential rotation (FR-NOTIF-005, ADR-015).

**Authentication:** Required. **Authorization:** `SUPER_ADMIN` only.

**Request:** `host`, `port`, `encryption_mode`, `username`, `from_address`, `from_name`, `reply_to_address`, `timeout_seconds`, `max_attempts`, `retry_backoff_seconds`, `is_active`, and:

| Field | Behaviour |
| --- | --- |
| `password` **omitted** | **The existing encrypted value is preserved unchanged.** Omission is never interpreted as "clear the credential" |
| `password` **provided** | The stored ciphertext is **replaced wholesale**. No partial update, no merge, no retained previous value |
| `password: null` explicitly | Treated as an explicit clear, and audited as a credential change |

**Validation:** `port` in range; `encryption_mode` ∈ `NONE` \| `STARTTLS` \| `TLS`; `from_address` a valid address; `max_attempts` and `retry_backoff_seconds` positive.

**Business Rules:**
- **The credential is encrypted by the application before it reaches the database**, using a key sourced from deployment secret configuration and held outside the database (INV-035). Plaintext is never written, not even transiently.
- **The response never returns the credential**, only `secret_configured`.
- Activating a configuration deactivates any other active one **in the same transaction** — at most one may be active (INV-036).
- `max_attempts` and `retry_backoff_seconds` supply the FR-NOTIF-003 configurable retry ceiling used by the outbox worker. **No SMTP credential is ever written into `email_outbox`** (INV-015, INV-035).

**Success Response:** `200 OK` with the same shape as the read, including `secret_configured`.

**Error Codes:** `VALIDATION_FAILED` (422) · `AUTH_FORBIDDEN` (403).

**Side Effects:** Configuration row updated; previous active row deactivated and retained as history.

**Audit:** `smtp_configuration_updated`, recording **`credential_changed: true | false`** and the non-secret fields that changed. **The credential value — old or new — never appears in `change_summary` or anywhere else in the audit payload** (INV-035).

**Notification / Outbox:** None. Changing mail configuration must not itself depend on mail.

**Idempotency:** **REQUIRED.**

**Concurrency:** Row lock; single-active enforced by a conditional constraint.

**Source Requirement:** FR-NOTIF-003, FR-NOTIF-005 · **INV-015, INV-035, INV-036** · ADR-015

---

### POST /api/v1/admin/smtp-configuration/test

**Surface:** `INERTIA_WEB`

**Purpose:** Send a test email using the stored configuration (FR-NOTIF-005).

**Authentication:** Required. **Authorization:** `SUPER_ADMIN` only.

**Request:** `recipient` (required — a valid address).

**Validation:** Address format.

**Business Rules:**
- Uses the stored credential to attempt delivery. **It never echoes, returns, or logs the credential** (INV-035).
- Records `last_tested_at` and `last_test_result` ∈ `SUCCESS` \| `FAILURE`.
- **A failure summary must contain no credential material.** Provider error text is sanitized before storage or display — SMTP servers can echo the username, and some misconfigurations echo more.
- The test bypasses `email_outbox` (it is a diagnostic, not a business message) and never creates a notification.

**Success Response:** `200 OK` — `{ "data": { "result": "SUCCESS", "tested_at": "…" } }`. A delivery failure still returns `200` with `result: "FAILURE"` and a sanitized summary: the **test executed successfully** even though delivery failed.

**Error Codes:** `VALIDATION_FAILED` (422) · `AUTH_FORBIDDEN` (403) · `SERVICE_UNAVAILABLE` (503) if no configuration exists.

**Side Effects:** `last_tested_at` and `last_test_result` updated.

**Audit:** `smtp_configuration_tested` with the result and recipient. **Never the credential.**

**Notification / Outbox:** None — deliberately outside the outbox.

**Idempotency:** Not required.

**Concurrency:** None.

**Source Requirement:** FR-NOTIF-005 · **INV-035**

---

### GET /api/v1/reports/{report}

*(Grouped contract — covers all read-only report endpoints.)*

**Surface:** `INERTIA_WEB`

**Covered URIs:** `GET /api/v1/reports/company-overview` · `GET /api/v1/reports/vacancies` · `GET /api/v1/reports/application-funnel` · `GET /api/v1/reports/external-apply` · `GET /api/v1/reports/offers` · `GET /api/v1/reports/outcomes` · `GET /api/v1/reports/time-to-fill`

**Purpose:** Dashboard and report data for FR-REP-001 (recruiter), FR-REP-002 (Career Center), FR-REP-003 (Admin Kepegawaian).

**Authentication:** Required.

**Authorization:** **Every report is scoped to the actor.** Recruiter → own company only. HR_ADMIN → campus only. Career Center → verification, moderation, partnership, and alumni-outcome scope. Auditor → `READ_ONLY` within permitted scope. Super Admin → all. **A recruiter can never see another company's funnel**, and scoping happens in the query, not in a post-filter.

**Request:** Filters `date_from`, `date_to`, `company_id`, `organizational_unit_id`, `vacancy_id`, `vacancy_type` — each honoured only within the actor's scope. A `company_id` outside scope yields an empty result, never another company's data.

**Validation:** Allow-listed filters; date range bounded.

**Business Rules:**

| Report | Derivation |
| --- | --- |
| `company-overview` | Verified-company counts (`companies.verification_status = VERIFIED`) **and** active `Mitra Kampus` counts (ACTIVE `partnerships` within period — derived, **never a company status**, INV-020). Merged because FR-REP-002 renders both on one Career Center dashboard, both are Career-Center-scoped, and both are fetched together on every load (`API_SIZE_REVIEW.md` M-4) |
| `vacancies` | Counts by `current_status`, scoped by ownership |
| `application-funnel` | `applications.current_status` counts, with `application_status_histories` for stage movement |
| `external-apply` | `external_apply_events` started versus confirmed — **never mixed into application counts** (INV-012, INV-024). FR-EXT-004 requires these to remain distinguishable |
| `offers` | `offers.status = ACCEPTED` and response rates |
| `outcomes` | `recruitment_outcomes` joined through its single populated source reference (INV-022), plus incomplete-outcome counts |
| `time-to-fill` | **`offers.offer_accepted_at − vacancies.published_at`**, using the **earliest** accepted offer per vacancy; **undefined and reported as unavailable while `published_at` is null** (INV-013, INV-031, FR-REP-004) |

- **All figures derive from transactional tables. No separate analytics store, no stored KPI counter, no denormalized metric exists** (ADR-013) — a stored counter would drift, which is the defect INV-026 exists to prevent.
- Cached tiles carry an explicit `meta.computed_at`, so a reader always knows the figure's age.

**Success Response:** `200 OK` with aggregate `data` and `meta.computed_at`.

**Error Codes:** `AUTH_FORBIDDEN` (403) · `VALIDATION_FAILED` (422).

**Side Effects:** None — reports are strictly read-only and write nothing, ever.

**Audit:** None for viewing. **Export is audited** — see below.

**Notification / Outbox:** None.

**Idempotency:** Safe method.

**Concurrency:** Short-TTL cache permitted; **no authorization-sensitive record is cached** (ADR-006).

**Source Requirement:** FR-REP-001, FR-REP-002, FR-REP-003, FR-REP-004, FR-EXT-004 · **INV-012, INV-013, INV-020, INV-022, INV-024, INV-031** · ADR-013

---

### POST /api/v1/reports/exports

**Surface:** `INERTIA_WEB`

**Purpose:** Request a CSV/XLSX export (FR-REP-005).

**Authentication:** Required. **Authorization:** As for the corresponding report. **Candidate-data export is restricted to roles authorized over that data** and is always scoped.

**Request:** `report` (required — one of the report keys), `format` (`CSV` \| `XLSX`), plus the same filters as the report.

**Validation:** Allow-listed report keys, formats, and filters.

**Business Rules:**
- Runs as a **queued job**; the response is `202 Accepted` with a job reference. Exports can be large and must not hold an HTTP worker.
- **Contains only the fields the role requires** (FR-REP-005: "hanya memuat data yang diperlukan"). No export includes `internal_note`, credentials, storage keys, or out-of-scope records.
- The finished file is delivered through the **same Policy-checked, audited download path** as any other private file. It is never placed at a public URL.

**Success Response:** `202 Accepted` — `{ "data": { "export_id": "…", "status": "QUEUED" } }`.

**Error Codes:** `AUTH_FORBIDDEN` (403) · `VALIDATION_FAILED` (422) · `RATE_LIMITED` (429).

**Side Effects:** Export job enqueued.

**Audit:** **`export_requested` — required by FR-REP-005 and FR-AUD-001** — with report key, filters, and scope. **`document_access` is audited again when the file is downloaded.**

**Notification / Outbox:** Notification when the export is ready.

**Idempotency:** Recommended.

**Concurrency:** Per-actor concurrent-export limit.

**Source Requirement:** FR-REP-005, FR-AUD-001 · FSD §10.4

---

### GET /api/v1/audit-logs

**Surface:** `INERTIA_WEB`

**Purpose:** Read the audit trail (FR-AUD-001).

**Authentication:** Required. **Authorization:** **`SUPER_ADMIN` and `AUDITOR` only**, both `READ_ONLY`. No other role. Auditor scope may be further narrowed by policy.

**Request:** Filters `actor_user_id`, `action`, `object_type`, `object_id`, `correlation_id`, `created_from`, `created_to`. Sortable: `created_at` (default, descending). Pagination: page-based, or `?cursor=` for deep traversal of a large, append-heavy table.

**Validation:** Allow-listed filters and sort fields; date range bounded.

**Business Rules:**
- Returns actor, action, object type and id, redacted change summary, correlation ID, timestamp, and — **only where policy permits collection (H-4, unresolved)** — IP and device metadata.
- **`change_summary` is redacted at write time.** It never contains passwords, password hashes, verification or reset tokens, SMTP credentials, API secrets, session identifiers, storage keys, or file contents. An SMTP change records `credential_changed: true`, never a value (INV-035).
- `object_type` is a **stable logical name**, never a class path, so history stays interpretable across refactoring (INV-033).
- **`audit_logs` is append-only. There is deliberately no create, update, or delete endpoint** — not for Super Admin, not for anyone. An audit trail that can be edited is not an audit trail (INV-016).
- **There is no separate audit-entry detail route.** This list already returns complete rows — actor, action, object type and id, redacted `change_summary`, correlation ID, timestamp — and summaries are bounded by design, so a detail endpoint would duplicate the list for one row (`API_SIZE_REVIEW.md` M-5).

**Success Response:** `200 OK`, paginated.

**Error Codes:** `AUTH_FORBIDDEN` (403) · `VALIDATION_FAILED` (422).

**Side Effects:** None.

**Audit:** Access to the audit log is **itself audited** as a sensitive admin action.

**Notification / Outbox:** None.

**Idempotency:** Safe method.

**Concurrency:** None.

**Source Requirement:** FR-AUD-001 · **INV-016, INV-033, INV-035** · **H-4 unresolved**

---

### POST /api/v1/admin/users/{user}/roles

**Surface:** `INERTIA_WEB`

**Purpose:** Assign a role (FSD §4.6, FR-AUD-001 role change).

**Authentication:** Required. **Authorization:** `SUPER_ADMIN` only.

**Request:** `role_code` (required).

**Validation:** Role exists in the approved catalogue.

**Business Rules:**
- Creates a `user_roles` row with `assigned_by` and `assigned_at`. **At most one active assignment per (user, role)** — a revoked assignment may be reissued, and history is preserved (INV-025).
- **Role assignment is authorization only.** It never proves candidate eligibility: `CANDIDATE_ALUMNI` is not evidence of alumni verification, which requires a VERIFIED `candidate_verifications` record (INV-028).
- Assigning `SELECTOR` grants **no** candidate access on its own — access additionally requires an active `selection_stage_assignments` row (INV-037).
- Paired routes: `POST /api/v1/admin/users/{user}/roles/{role}/revoke` (sets `revoked_at` and `revoked_by`, **never deletes**), `GET /api/v1/admin/users` (scoped list), `POST /api/v1/admin/users/{user}/suspend`, `POST /api/v1/admin/users/{user}/restore` (FSD §8.1 — suspension terminates sessions and revokes API tokens immediately).

**Success Response:** `201 Created`.

**Error Codes:** `VALIDATION_FAILED` (422) · `CONFLICT` (409) if an active assignment already exists · `AUTH_FORBIDDEN` (403).

**Side Effects:** Role assignment row + audit.

**Audit:** **`role_changed` — explicitly required by FR-AUD-001.**

**Notification / Outbox:** Affected user notified.

**Idempotency:** **REQUIRED.**

**Concurrency:** Conditional uniqueness on active assignment enforced by constraint.

**Source Requirement:** FSD §3.1, §4.6, §8.1, FR-AUD-001 · **INV-025, INV-028, INV-037**

---

### GET /api/v1/admin/master-data/{collection}

*(Grouped contract — covers master-data administration.)*

**Surface:** `INERTIA_WEB`

**Covered URIs:** `GET /api/v1/admin/master-data/{collection}`, where `{collection}` ∈ `organizational-units` \| `study-programs` \| `industries` \| `organization-types` \| `skills` \| `geographic-areas`.

**Write operations are deferred beyond MVP** (`API_SIZE_REVIEW.md` DF-1). The six master entities are reference data that changes a few times a year and is **seeded at deployment**; building runtime CRUD for six heterogeneous collections — six validation shapes, cycle detection for the two hierarchical collections, and a deactivation-versus-delete rule for referenced rows — is real work with no MVP dependency. **This is scope sequencing, not scope reduction: FSD §4.6's master-data management requirement remains unmet until the write operations ship, and that is stated rather than quietly dropped.**

**Purpose:** Super Admin maintenance of the six master-data entities (FSD §4.6).

**Authentication:** Required. **Authorization:** `SUPER_ADMIN` only for writes; authenticated read for the collections needed to render forms. Public read is served by `GET /api/v1/public/reference-data`.

**Request:** Per collection, exactly the fields in `DATA_DICTIONARY.md`.

**Validation:** `code` uniqueness where the dictionary defines it; parent references must exist and not create a cycle for the two hierarchical collections (`organizational-units`, `geographic-areas`).

**Business Rules:**
- **Master rows are deactivated (`active = false`), never deleted**, when referenced by any business record. Deleting a study programme referenced by a candidate's education or a vacancy requirement would orphan history.
- Deactivation removes a value from **new** selections only; existing references remain valid and readable.
- `skills` is a real master (decision D-3) referenced as a required value by `candidate_skills` and as a typed optional reference by `vacancy_requirements`.

**Success Response:** `200` (list, update) · `201` (create).

**Error Codes:** `VALIDATION_FAILED` (422) · `CONFLICT` (409) on duplicate code or attempted deletion of a referenced row · `AUTH_FORBIDDEN` (403).

**Side Effects:** Master row created or updated.

**Audit:** `master_data_changed` — a sensitive admin action under FR-AUD-001.

**Notification / Outbox:** None.

**Idempotency:** Not required.

**Concurrency:** Code uniqueness enforced by constraint.

**Source Requirement:** FSD §4.6, FR-ONB-002, FR-VAC-003, FR-CAN-003 · ERD decisions D-2, D-3

---

## Part X — Open Questions Carried by This Contract

Nothing below is resolved by this document **except where a row is explicitly marked CLOSED with its approval date**; a closed row is retained with its resolution so the decision history stays readable. Each open item affects a specific field or rule, and no unrelated contract is blocked.

| # | Open question | Effect on this contract |
| --- | --- | --- |
| 1 | **Alumni verification integration source** | `POST /candidate/verifications` — which of `student_number`, `program_study_id`, `graduation_year` are mandatory is **PENDING BUSINESS DECISION**. No integration client, sync job, callback, or SSO flow is specified. The candidate-type change endpoint is deliberately **not defined**, because its trigger depends on this answer |
| 2 | **Minimum company legal documents per organization type** | `POST /companies/{company}/documents` — at least one document is required before submit (INV-030), but **no per-organization-type matrix is fixed**. `COMPANY_DOCUMENT_TYPE_REQUIRED` is reserved and unused |
| 3 | **Salary mandatory / display policy** | Vacancy create and update — `salary_min`, `salary_max`, `salary_currency` are nullable with **no mandatory rule and no display policy encoded**. `SALARY_REQUIRED` is reserved and unused |
| 4 | **Recruiter domain / subdomain** | Affects cookie scope, CORS posture, and CSP, **not any URI or payload in this contract**. Same-origin is the default; a separate domain would require a cross-origin review before production DNS/TLS |
| 5 | **WhatsApp notification phase** | **No WhatsApp channel, provider, template, adapter, or delivery field appears anywhere.** Notification endpoints cover in-app and email only |
| 6 | ~~**First recruiter default role / minimum active Company Admin**~~ | **CLOSED by approved Product Owner decision:** `POST /companies` creates the creator's active `COMPANY_ADMIN` membership atomically; at least one active `COMPANY_ADMIN` must remain; last-admin protection is required; subsequent roles are explicit with no implicit default |
| 7 | **Candidate controlled vocabularies** — work preference values, education level, candidate document type | `PATCH /candidate/profile`, `PUT /candidate/{collection}`, `POST` and `PATCH /candidate/documents` validate these fields for **type, length, and structural validity only**. `DATABASE_SCHEMA.md` holds all three as `varchar` with the `CHECK` **pending approval**; no values are invented here. **The fields and their features remain MVP** — approving a vocabulary later adds a `CHECK` and a membership rule, and changes no route, payload shape, or capability |
| 8 | **Profile completion criteria** — `DEFERRED POLICY` | FR-CAN-003 requires a completed profile but fixes **no** completion formula or required-field set. `candidate_profiles.profile_completed_at` **stays in the frozen schema**, is returned as stored, and **may remain `null`**. It is **not** automatically recomputed by any operation in this contract. Candidate profile and collection CRUD are **not blocked** by this |
| 9 | ~~**Candidate document upload policy**~~ | **CLOSED — approved and frozen 25 August 2026.** MIME allowlist: **`application/pdf` only**, admitted on the **server-inspected** type **and** the `%PDF-` signature. Maximum size: **10 MiB / `10,485,760` bytes**, a **single global limit** with **no per-`document_type` variation**; application validation is authoritative and transport ceilings sit above it. Upload rate limit: **20 per hour per candidate** (Part I §11.8). Storage quota: **DEFERRED**. Malware scanner: **not required** for the PDF-only allowlist. `document_type` stays **open-text `varchar(64)`** — see item 7, still open. `CANDIDATE_DOCUMENT_UPLOAD_POLICY_REQUIRED` is retired; **no migration and no API-shape change** resulted. `POST /candidate/documents` remains **unrouted pending implementation** |
| 10 | **Unknown-user company member invitation lifecycle** — `DEFERRED` | Approved 25 August 2026: at MVP `POST /companies/{company}/members` accepts **only an address that already has an account**. An unknown address creates no membership, sends **no email**, records no pending invitation, and returns the generic `NOT_FOUND` envelope. Whether a pre-registration invite is ever offered — and if so whether it is an invitation entity, a nullable-user membership, or a tokenized link — is **not decided**. No schema, route, or response shape anticipates it |
| 11 | ~~**Vacancy moderation authority**~~ | **CLOSED for company vacancy moderation — approved 25 August 2026.** PO decisions **B-1** (approve target: `SCHEDULED` before `open_at`, `PUBLISHED` inside the window, refusal at or after `close_at`), **B-2** (restore target: `PUBLISHED` before `close_at`, else `CLOSED` with `closed_at`), **B-3** (moderators are `CAREER_CENTER_STAFF`, `CAREER_CENTER_MANAGER`, `SUPER_ADMIN`, with a conflict-of-interest bar on any ACTIVE member of the owning company; recruiters never moderate; Auditor read-only), **B-4** (no user-facing company publish; publication only through approval-in-window or the scheduler) and **B-5** (submit completeness). **VA-4 is unchanged**: company *authoring* still never derives from the global `SUPER_ADMIN` role. Campus-vacancy authority is untouched by these decisions |
| 12 | ~~**O-7 — automatic vacancy expiry**~~ | **CLOSED — approved 25 August 2026.** `PUBLISHED` + `now >= close_at` → `EXPIRED`, with `close_at` an **exclusive end boundary**: `now < close_at` is still active, `now == close_at` and `now > close_at` both reach expiry eligibility. System-driven only — see *Automatic vacancy expiry (O-7)* below for the full rule. Company vacancies only in this phase; campus expiry is wired with the Campus Vacancy lifecycle |
| 13 | ~~**PD-1 — public visibility after company suspension**~~ | **CLOSED — approved 26 August 2026.** A `PUBLISHED`, in-window, non-`INTERNAL` company vacancy is publicly discoverable only while its owning company is currently `VERIFIED`; this is re-evaluated on every public read, not fixed at publish time. Partnership is not part of the predicate. No vacancy state, moderation history, audit event, or application is mutated by a company's verification-status change. See *Public visibility and company verification (PD-1)* above for the full rule |
| 14 | ~~**PD-2 — public company identifier**~~ | **CLOSED — approved 26 August 2026.** `companies.slug`: generated once at creation from the company's name plus a random discriminator, unique, non-null once assigned, and never regenerated by a later name edit. No redirect/history table, no public numeric-ID fallback, no legal identifier exposed in the URL. Carries no authorization meaning — visibility of `GET /api/v1/public/companies/{slug}` is governed by `verification_status = VERIFIED` alone (mirroring PD-1's principle), never by the slug. See *GET /api/v1/public/companies/{slug}* above |
| 15 | ~~**AD-1 — company VERIFIED gate at application submit**~~ | **CLOSED — approved 26 August 2026.** `POST /vacancies/{vacancy}/applications` on a `COMPANY`-owned vacancy independently rechecks `companies.verification_status = VERIFIED` inside the submit transaction — never inferred from public visibility, cached state, or a stale page. Reuses the existing `VACANCY_COMPANY_NOT_VERIFIED` (403) contract already established for `ModerateVacancy`'s APPROVE recheck; no new error code was introduced. On failure, no application, history, consent, document share, screening answer, notification, outbox row, or audit event is written, and neither the vacancy nor the company row is mutated. `VERIFIED` does not depend on any Mitra Kampus / partnership state, and this gate causes no vacancy-state cascade |
| 16 | ~~**AD-4 — application profile-completeness gate for Foundation v1**~~ | **CLOSED — approved 26 August 2026.** `POST /vacancies/{vacancy}/applications` does **not** enforce a completeness formula on `candidate_profiles` in this milestone. `CANDIDATE_PROFILE_INCOMPLETE` (row 8 above) **stays RESERVED and is not emitted** by this endpoint; it is not deleted from the vocabulary and the broader profile-completion policy (row 8) is **not** closed by this decision. The candidate must still have a `candidate_profiles` row at all — that pre-existing `CANDIDATE_PROFILE_REQUIRED` check is unaffected and unrelated to this decision |
| 17 | **AD-2 — reopen / reapply** — `OPEN, DEFERRED` | `POST /applications/{application}/reopen` (documented above) has **no runtime implementation, route, or side effect** in Candidate Application Foundation v1. Its business conditions (who may trigger it, and under what vacancy/application state) remain undecided; nothing in Foundation v1 anticipates or forecloses an answer |
| 18 | **AD-3 — `INTERNAL` audience eligibility** — `OPEN, DEFERRED` | `target_audience = INTERNAL` is not a supported audience for `POST /vacancies/{vacancy}/applications` in Foundation v1. A submit against an `INTERNAL`-audience vacancy is rejected via the existing `CANDIDATE_NOT_ELIGIBLE` (403) contract (rule 3 above) — no "internal candidate" eligibility concept is invented. What would make a candidate eligible for an `INTERNAL` vacancy is undecided |
| 19 | ~~**SPEC-DOC-08 — Candidate Application MVP transport**~~ | **CLOSED — approved 26 August 2026.** The four candidate-facing Candidate Application Foundation v1 operations (submit, candidate `OWN` list, candidate `OWN` detail, withdraw) are reclassified `INERTIA_WEB` for MVP, the same pattern already accepted for browser authentication (SPEC-DOC-05) and Candidate Core (SPEC-DOC-07): Laravel session guard + CSRF, no Sanctum, no `personal_access_tokens`. Their `/api/v1` twins remain reserved and inactive. `POST /applications/{application}/reopen` is **not** reclassified (AD-2 open/deferred, no runtime); `COMPANY_SCOPE`/`CAMPUS_SCOPE`/`ASSIGNED_STAGE`/Auditor scopes on list/detail are **not** reclassified (unimplemented, later phase). Transport classification only — no business rule for AD-1, AD-4, consent, documents, screening, history, audit, notifications, idempotency, or concurrency changed |
| 20 | ~~**RA-1 — Recruiter Applicant Management transition graph v1**~~ | **CLOSED — approved 26 August 2026.** `POST /applications/{application}/transition` supports exactly five edges in Foundation v1: `APPLIED → UNDER_REVIEW`, `APPLIED → REJECTED`, `UNDER_REVIEW → SHORTLISTED`, `UNDER_REVIEW → REJECTED`, `SHORTLISTED → REJECTED`. Every other target (`ASSESSMENT`, `INTERVIEW`, `OFFERED`, `HIRED`, `NO_SHOW`), every backward edge, and same-status no-ops are `409 APPLICATION_INVALID_TRANSITION`. `REJECTED` and `WITHDRAWN` are terminal for this milestone and cannot be left through `/transition` (`409 APPLICATION_TERMINAL`) — reactivation remains `reopen`, which AD-2 leaves open. `WITHDRAWN` stays unreachable via `/transition` (candidate-only action, unchanged). See the transition section above for the full rule |
| 21 | ~~**RA-2 — existing-applicant processing gate**~~ | **CLOSED — approved 26 August 2026.** A successful `/transition` additionally requires, inside the transaction: the owning company `verification_status = VERIFIED` (else `403 VACANCY_COMPANY_NOT_VERIFIED`, reused from AD-1) and the vacancy `current_status` ∈ `{PUBLISHED, CLOSED, EXPIRED}` (else `409 APPLICATION_VACANCY_NOT_PROCESSABLE`, new — see `ERROR_CODES.md` §8). `CLOSED`/`EXPIRED` stop new intake but permit continued processing of applicants who applied while intake was valid; `SUSPENDED` and any other vacancy state block processing entirely. Reads are unaffected. No mutation of vacancy/company/application on denial. Applies identically to `SUPER_ADMIN` — object authorization and transition business-legality are separate checks, and no source exempts `SUPER_ADMIN` from the latter. See the transition section above for the full rule |
| 22 | ~~**RA-3 — recruiter application-document download**~~ | **CLOSED / DEFERRED — approved 26 August 2026.** Recruiter/company/Super Admin application detail may expose shared `application_documents` **metadata only** (`id`, `snapshot_name`, `shared_at`) for documents actually shared with that application. No download route, private object streaming, signed URL, or direct storage reference is implemented in this milestone; `snapshot_storage_reference` and `snapshot_checksum` are never returned. `GET /api/v1/application-documents/{applicationDocument}/download` stays listed in `API_ENDPOINTS.md`, reserved and inactive |
| 23 | ~~**RS-2 — recruitment stage administration state policy**~~ | **CLOSED — approved 26 August 2026.** Stage list/create/update/reorder/deactivate authorization is determined only by actor capability (`COMPANY_SCOPE`/`SUPER_ADMIN`), vacancy ownership, and the stage belonging to that vacancy — **no additional gate on `vacancy.current_status` or `company.verification_status`**. A stage may be administered regardless of vacancy lifecycle state or company verification status, as long as the actor is authorized against the vacancy. Neither `VACANCY_NOT_EDITABLE` nor a new company-verification error is used for Stage Authoring v1. Stage mutation never changes vacancy status, never writes `applications.current_stage_id`, never moves a candidate, never reopens intake, and never bypasses RA-2 — a `SUSPENDED` company or vacancy still cannot process applicants where RA-2 forbids it; RS-2 governs stage configuration only |
| 24 | ~~**RS-6 — Super Admin stage authorization**~~ | **CLOSED — approved 26 August 2026.** `SUPER_ADMIN` has unconditional `ALLOW` for recruitment stage management, distinct from VA-4 (which requires an active `company_members` row for company vacancy editing and screening questions). The "Manage recruitment stages · reorder" row in `AUTHORIZATION_MATRIX.md` carries no VA-4 footnote; RS-6 confirms this is a deliberate, separate grant, not an omission — Super Admin needs no company membership to author stages for any `COMPANY` vacancy |
| 25 | **Selector assignment for `COMPANY` vacancies** — `OUT OF SCOPE, DEFERRED` | Unchanged from the frozen matrix (footnote 23): "not defined for company vacancies... a change request." No selector-assignment runtime of any kind is implemented for `COMPANY` vacancies by Recruitment Stage Authoring Foundation v1. `HR_ADMIN`'s Campus-only selector-assignment authority is untouched and remains unimplemented, since no Campus vacancy runtime exists |
| 26 | ~~**move-stage**~~ | **CLOSED — approved 27 August 2026.** `POST /applications/{application}/move-stage` is routed by Application Stage Movement Foundation v1. See the move-stage section above and rows 27–28 below for the full rule |
| 27 | ~~**MS-3 — same-stage movement**~~ | **CLOSED — approved 27 August 2026 (Decision A — REJECT).** `to_stage_id == applications.current_stage_id` → `422 VALIDATION_FAILED`, no mutation, no history, no audit, no notification. Not treated as a successful no-op; no `STAGE_CHANGED` row with `from_stage_id == to_stage_id` is ever written |
| 28 | ~~**MS-4 — stage movement adjacency**~~ | **CLOSED — approved 27 August 2026 (Decision A — arbitrary active same-vacancy target).** Forward, backward, skipped, and lateral movement between active same-vacancy stages are all permitted. `sort_order` is authoring/display only and never gates movement legality. No forward-only rule, adjacency rule, or backward-movement permission/reason requirement exists |
| 29 | ~~**SS-1 — terminal application scheduling**~~ | **CLOSED — approved 27 August 2026.** Create and reschedule are both denied for a terminal application (`HIRED`, `REJECTED`, `WITHDRAWN`, `NO_SHOW`) via `409 APPLICATION_TERMINAL`. Cancel of an existing `SCHEDULED` schedule remains permitted regardless of the parent application's terminal status — deliberate administrative cleanup so a stale record is never stranded |
| 30 | ~~**SS-2 — schedule stage alignment**~~ | **CLOSED — approved 27 August 2026.** `recruitment_stage_id` need not equal `applications.current_stage_id`; create may target any `active` same-vacancy stage in advance, with no adjacency or `sort_order` rule. Creating a schedule never mutates `applications.current_stage_id` |
| 31 | ~~**SS-3 — inactive target stage eligibility**~~ | **CLOSED — approved 27 August 2026.** Create requires the target stage `active = true`; reschedule requires the schedule's existing referenced stage to still be `active = true`; cancel is permitted regardless of the referenced stage's active state. No cascading cleanup occurs when a stage referenced by an existing schedule is later disabled |
| 32 | ~~**SS-5 — past schedule policy**~~ | **CLOSED — approved 27 August 2026.** Create and reschedule both require `starts_at` to be strictly future relative to authoritative server time, checked inside the write transaction, not merely at request-shape validation time. No minimum lead time beyond "strictly future". `ends_at`, where supplied, must be strictly later than `starts_at` — both `422 SCHEDULE_TIME_INVALID` on violation |
| 33 | ~~**SS-8 — RA-2 applicability**~~ | **CLOSED — approved 27 August 2026.** Create and reschedule both require the existing RA-2 processing gate (`ApplicationProcessingGate`, reused unmodified): owning company `VERIFIED` and vacancy ∈ `{PUBLISHED, CLOSED, EXPIRED}`, with no `SUPER_ADMIN` bypass. Cancel is explicitly exempt from RA-2 — company verification and vacancy processability are never checked for cancel |
| 34 | ~~**SS-9 — schedule read transport**~~ | **CLOSED — approved 27 August 2026.** `GET /schedules`, `GET /schedules/{schedule}`, and `GET /schedules/{schedule}/history` are reclassified `INERTIA_WEB` for the MVP browser portal, the same SPEC-DOC-05/-07/-08 pattern. Their `/api/v1` twins remain reserved and inactive. No Sanctum/PAT activation. Authorization is unchanged from the frozen matrix: `COMPANY_SCOPE` (recruiter/admin), `ALLOW` (Super Admin), `READ_ONLY` (Auditor), `OWN` (candidate), `DENY` (Career Center), inert `ASSIGNED_STAGE` (Selector, pending deferred company selector assignment), deferred `CAMPUS_SCOPE` (HR_ADMIN) |
| 35 | **Selection Schedule Foundation v1 deferred scope** — `DEFERRED` | `complete`/`no-show` paired actions, selector assignment/revocation, company-side selector runtime, evaluation, scoring, offer, outcome, reopen, Campus recruitment runtime, External Apply, candidate self-reschedule, bulk scheduling, calendar-provider integration, and the schedule attachment upload mechanism (see the create section's amendment note) are all explicitly out of Selection Schedule Foundation v1's scope. None are implemented merely because this document already describes them |
| 36 | ~~**EV-1 — evaluation terminal application eligibility**~~ | **CLOSED — approved 27 August 2026.** Create, update, and submit are all denied for a terminal application (`HIRED`, `REJECTED`, `WITHDRAWN`, `NO_SHOW`) via `409 APPLICATION_TERMINAL`, checked against an authoritative locked application row in all three operations. No late evaluation mutation after the application becomes terminal |
| 37 | ~~**EV-2 — evaluation stage alignment**~~ | **CLOSED — approved 27 August 2026.** `recruitment_stage_id` need not equal `applications.current_stage_id`; create/update may target any `active` same-vacancy stage, with no adjacency or `sort_order` rule. **Submit is exempt from the active-stage requirement** — a draft created against a then-active stage remains submittable even if that stage is later disabled, so a valid draft is never stranded by a later Stage Authoring action. Evaluation never mutates `applications.current_stage_id` |
| 38 | ~~**RC-1 — RA-2 applicability to Evaluation**~~ | **CLOSED — approved 27 August 2026.** Create, update, and submit all require the existing RA-2 processing gate (`ApplicationProcessingGate`, reused unmodified): owning company `VERIFIED` and vacancy ∈ `{PUBLISHED, CLOSED, EXPIRED}`, with no `SUPER_ADMIN` bypass. This closes the same shared cross-cutting question RC-1 already identified for Offering — this decision resolves it for Evaluation only; Offering's own RA-2 applicability remains a separate, not-yet-closed decision |
| 39 | **Evaluation / Scoring Foundation v1 deferred scope** — `DEFERRED` | Item-set mutation via `PATCH` (see the create section's PATCH-scope amendment note), company-side selector assignment/revocation, selector applicant evaluation runtime, schedule `complete`/`no-show`, offering, offer accept/reject, recruitment outcome, reopen, Campus recruitment runtime, External Apply, bulk evaluation, AI/automatic scoring, and any weighted-average or pass/fail formula are all explicitly out of this milestone's scope. None are implemented merely because this document already describes them |
| 40 | ~~**OF-1 — Offering / application status independence**~~ | **CLOSED — approved 27 August 2026.** Create, update, and send never mutate `applications.current_status`/`current_stage_id`; no new RA-1 edge is added to make `OFFERED` reachable. The already-frozen accept side effect (`current_status = HIRED`, `hired_at`, `OFFER_ACCEPTED` history event) is preserved exactly as originally specified — this decision narrows nothing already active. Reject does not mutate application status either |
| 41 | ~~**OF-2 — candidate offer response transport**~~ | **CLOSED — approved 27 August 2026.** `POST /offers/{offer}/accept` and `POST /offers/{offer}/reject` are reclassified `INERTIA_WEB` for the MVP browser portal, the same SPEC-DOC-05/-07/-08/SS-9 pattern. Their `/api/v1` twins remain reserved and inactive. No Sanctum/PAT activation. `OWN` only — no proxy response exists for any other actor, including `SUPER_ADMIN`, which is `DENY` here despite its unconditional recruiter-side `ALLOW` |
| 42 | ~~**RC-1 — RA-2 applicability to Offering**~~ | **CLOSED — approved 27 August 2026.** Create, update, and send all require the existing RA-2 processing gate (`ApplicationProcessingGate`, reused unmodified): owning company `VERIFIED` and vacancy ∈ `{PUBLISHED, CLOSED, EXPIRED}`, with no `SUPER_ADMIN` bypass. Candidate accept/reject are explicitly RA-2-exempt — this closes the RC-1 question Evaluation's own closure (row 38) explicitly left open for Offering |
| 43 | **Offering Foundation v1 deferred scope** — `DEFERRED` | Offer revoke/withdraw/resend, the offer-expiry scheduler job, bulk offer, salary/currency/benefits/start-date fields (none exist in the schema), counter-offer, digital signature, document generation, payroll/onboarding/employment-contract workflow, Evaluation coupling, automatic Outcome creation, Campus recruitment runtime, External Apply, Selector offering capability, and the offer document upload mechanism (see the create section's amendment note) are all explicitly out of this milestone's scope. `PENDING_RESPONSE` remains a dormant, unproduced frozen status value — no trigger for it is invented. None of the deferred items are implemented merely because this document already describes them |
| 44 | ~~**OC-1 — Internal Application outcome vocabulary**~~ | **CLOSED — approved 27 August 2026.** For `source_type = INTERNAL_APPLICATION`, `outcome` accepts exactly `HIRED`, `REJECTED`, `WITHDRAWN`, `NO_SHOW` in Foundation v1, enforced by request/runtime validation only — no database CHECK constraint or migration is added, and the `outcome` column remains an unconstrained `string(64)`. No other value is accepted (`422 VALIDATION_FAILED`). This vocabulary applies to `INTERNAL_APPLICATION` only and is never assumed for a future `EXTERNAL_APPLY` runtime |
| 45 | ~~**RC-2 — Career Center outcome scope**~~ | **CLOSED — approved 27 August 2026.** The matrix's Career Center `ALLOW` for outcome record/correct/list (footnote 29) is alumni/reporting scope (`EXTERNAL_APPLY`). It is **not activated** for this `INTERNAL_APPLICATION`/`COMPANY`-only milestone — Career Center is `DENY` on every Recruitment Outcome Foundation v1 route. The matrix grant itself is untouched, reserved for a future Alumni/External Apply Outcome milestone |
| 46 | **Recruitment Outcome Foundation v1 deferred scope** — `DEFERRED` | `EXTERNAL_APPLY` outcome runtime and vocabulary, Career Center alumni outcome recording, Campus (`HR_ADMIN`) outcome runtime, candidate outcome write, Selector outcome access, automatic outcome creation/correction, outcome delete, outcome history table, outcome approval workflow, bulk outcome mutation, a reminder scheduler, and Time-to-Fill persistence are all explicitly out of this milestone's scope. None are implemented merely because this document already describes them |
| 47 | ~~**H-5 — `INTERNAL_APPLICATION` incomplete outcome**~~ | **CLOSED — approved 27 August 2026.** `GET /recruitment-outcomes/incomplete` is active. Incomplete iff `applications.current_status` ∈ `{HIRED, REJECTED, WITHDRAWN, NO_SHOW}` **and** no matching `recruitment_outcomes` row exists — no minimum age, offer requirement, vacancy/company lifecycle gate, or RA-2. Strictly read-only: never creates an outcome, mutates application status/stage, or sends a notification. See the create contract's amendment note above for the full rule and authorization |
| 48 | ~~**FE-1 — application document-sharing consent copy**~~ | **CLOSED — approved 27 August 2026.** Exact candidate-facing text: *"Saya menyetujui dokumen yang saya pilih pada lamaran ini dibagikan kepada perusahaan pemilik lowongan untuk keperluan proses rekrutmen."* Scoped to documents selected for this application only, shared with the vacancy's owning company, for this recruitment process only — never marketing consent, cross-company sharing, public access, blanket future-application consent, or blanket profile/document access. Frontend copy only: `consent_version`, the hash reference, and the consent entity/architecture are unchanged. See the create contract's amendment note above |
| 49 | ~~**FE-3 — vacancy type display labels**~~ | **CLOSED — approved 29 August 2026.** Frontend display vocabulary for `vacancies.vacancy_type` (already a fixed enumeration): `CAMPUS_EMPLOYMENT` → "Karier di Kampus", `COMPANY_EMPLOYMENT` → "Pekerjaan di Perusahaan", `INTERNSHIP` → "Magang". Display only — stored enum values, `chk_vacancies_vacancy_type`, and `App\Domains\Vacancy\Enums\VacancyType` are unchanged. BRD/FSD did not define these labels; this is a Product Owner decision recorded here, not attributed to BRD/FSD |
| 50 | ~~**FE-4 — employment type v1 display (approved value only)**~~ | **CLOSED FOR THE APPROVED VALUE ONLY — approved 29 August 2026.** `vacancies.employment_type = FULL_TIME` → "Penuh Waktu" (frontend display only). **This does NOT close the `employment_type` vocabulary.** The value set stays *vocabulary pending* per `DATABASE_SCHEMA.md` Part X item 7 / `DATA_DICTIONARY.md`; no value or label is approved for `PART_TIME`, `CONTRACT`, `FREELANCE`, `TEMPORARY`, or any other — they remain OPEN. No CHECK constraint, migration, or backend enum is added. Unknown values fall through to the existing frontend unknown-value fallback (raw string), never a manufactured label |
| 51 | ~~**FE-5 — workplace mode v1 display (approved value only)**~~ | **CLOSED FOR THE APPROVED VALUE ONLY — approved 29 August 2026.** `vacancies.workplace_mode = HYBRID` → "Hybrid" (frontend display only). **This does NOT close the `workplace_mode` vocabulary.** It stays *vocabulary pending* per `DATABASE_SCHEMA.md` Part X item 7 and `DATA_DICTIONARY.md` ("Onsite, hybrid, remote, or later approved logical values"); no value or label is approved for `ONSITE`, `REMOTE`, or any other — they remain OPEN. No CHECK constraint, migration, or backend enum is added. Unknown values fall through to the existing frontend unknown-value fallback |
| 52 | ~~**FE-6 — recruiter application-history display**~~ | **CLOSED — approved 29 August 2026.** `application_status_histories.event_type = STAGE_CHANGED` renders as "Tahap Seleksi Diubah" on the **recruiter** timeline presentation. `STAGE_CHANGED` remains a stage-only history event and is **never** an application status: `from_status`/`to_status` may stay `NULL`, the recruiter timeline must not pass a stage-only event through the application-status label map, and `candidate_visibility` semantics are unchanged. An `INTERNAL` `STAGE_CHANGED` stays hidden from the candidate — `ApplicationPresenter`'s `candidate_visibility = VISIBLE` backend filter remains sole authority; a display label does not make the event candidate-visible. No change to `App\Domains\Application\Enums\ApplicationEventType` or any history write path. Frontend presentation only |

**Human-decision items carried from the ERD:**

| # | Item | Effect |
| --- | --- | --- |
| **H-2** | Vacancy-level outcome not recordable | `recruitment_outcomes` is candidate-level only. "Closed, no suitable candidate" has no endpoint, and none was invented |
| **H-3** | Candidate revocation of a shared document | `application_documents.revoked_at` exists and is honoured on read, but **no candidate-facing revoke endpoint is specified** — FSD v1.1 does not define the action |
| **H-4** | Audit IP / device metadata collection | `audit_logs` may include them **only where policy permits**; both remain optional throughout |

---

## Part XI — Requirements Deferred to the Database Schema Phase

Two capabilities this contract depends on have **no representation in logical model 1.1-C2** and must be resolved in `DATABASE_SCHEMA.md`. Neither is invented here.

| # | Requirement | Detail |
| --- | --- | --- |
| **DB-1** | **Idempotency key storage** | Part I §7 requires retaining key + actor + endpoint + response for at least 24 hours. The frozen model has no such entity. It is **operational infrastructure, not business data** — like `sessions`, `jobs`, and `failed_jobs` — so it does not change the 51-entity business model and needs no ERD change request. The schema phase must define its storage, uniqueness, and expiry |
| **DB-2** | **Export job tracking** | `POST /reports/exports` returns an export reference for an asynchronous job. Whether this is Laravel's job/batch infrastructure or a dedicated table is a schema-phase decision. Also operational, not business data |

Both were surfaced by writing this contract and are recorded rather than resolved.
