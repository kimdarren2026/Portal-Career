# API Size and Over-Engineering Review — Portal Karir Kampus

**Status:** Review only — advisory
**Date:** 24 August 2026
**Reviewed:** `API_CONTRACT.md` (81 contract sections) and `API_ENDPOINTS.md` (165 listed rows)
**Baselines:** BRD v1.1 · FSD v1.1 · Logical model 1.1-C2 · Architecture ADR-001 to ADR-017

**No API file was modified by this review.** Every recommendation is stated as a proposal for decision. Nothing below removes business behaviour; each merge preserves the same capability behind fewer URIs, and each deferral moves work out of MVP without deleting it from scope.

---

## Current Endpoint Count

| Measure | Value |
| --- | --- |
| Rows listed in `API_ENDPOINTS.md` | 165 |
| **Distinct routable endpoints** | **164** |
| Contract sections in `API_CONTRACT.md` | 81 |
| Named business action endpoints (state transitions) | 41 |
| Read-only endpoints | 63 |

> **Discrepancy found.** `GET /api/v1/application-documents/{applicationDocument}/download` is listed **twice** in `API_ENDPOINTS.md` — once from its own contract heading and once from a cross-reference in Part VI. The true unique count is **164**, not 165. This is a documentation defect in the generated index, not a duplicate endpoint. It is reported here rather than silently corrected, per the instruction not to edit completed files. **Recommended fix:** dedupe the index row; no contract change needed.

### Classification summary

| Classification | Endpoints | Becomes |
| --- | --- | --- |
| **KEEP** | 131 | 131 |
| **MERGE CANDIDATE** | 25 | 12 |
| **DUPLICATIVE** | 2 | 0 |
| **DEFER** | 2 | 0 |
| **QUESTIONABLE** | 4 | 3 |
| **Total** | **164** | **146** |

---

## Keep

**131 endpoints require no change.** The API is not broadly over-engineered: it is dense because the domain is a five-role recruitment workflow with a heavily constrained state machine, and most of that density is FSD-mandated rather than invented.

| Group | Endpoints | Why they stand |
| --- | --- | --- |
| Authentication and session | 10 | FSD §7.1 enumerates almost exactly this set. `PUT /me/password` is the only addition, justified by *Pengaturan Akun* in FSD §4.2/§4.3 |
| Company lifecycle actions | 6 | `submit-verification`, `verify`, `request-revision`, `reject`, `suspend`, `restore` map 1:1 onto FSD §8.2 transitions |
| Vacancy lifecycle actions | 8 | `submit-review`, `approve`, `request-revision`, `reject`, `publish`, `close`, `suspend`, `restore` map 1:1 onto FSD §8.3/§8.4 |
| Application lifecycle actions | 5 | `transition`, `move-stage`, `reopen`, `withdraw`, `bulk-transition` — FSD §8.5, FR-APP-003, FR-APP-006, FR-APP-007 |
| Offering actions | 3 | `send`, `accept`, `reject` — FR-SEL-003/004/005 |
| Schedule actions | 4 | `reschedule`, `cancel`, `complete`, `no-show` — FR-SEL-001 |
| External apply | 2 | `start`, `confirm` — FR-EXT-002/003 |
| Selector assignment | 2 | `assign`, `revoke` — FR-HR-006, resolved by ADR-016 |
| History and version reads | 5 | `verification-history`, `moderation-history`, `versions`, application `history`, schedule `history` — required by INV-016 and FR-VAC-007. **Append-only history is worthless if unreadable** |
| Public reads | 4 | Backs SSR public pages (ADR-017); minimal surface, no private field |
| Scoped list and detail reads | ~20 | `vacancies`, `applications`, `schedules`, `offers`, `partnerships`, `notifications`, `audit-logs` — each query-scoped per role |
| Document upload/download | 6 | Each download is separately Policy-checked and audited (FR-AUD-001) |
| Reporting | 7 | One per FR-REP dashboard tile group |
| Remaining CRUD | ~49 | Company, vacancy, stages, screening questions, evaluations, outcomes, users, roles |

**The 41 named action endpoints are the API's core value, not its bloat.** Collapsing them into generic `PATCH` with a `status` field would let a client drive an invalid transition, scatter state-machine logic across payload shapes, and destroy the 1:1 mapping between an endpoint, an Action, a Policy check, a history row, and an audit row.

---

## Merge Candidates

**25 endpoints → 12. Net −13.** Each merge preserves the capability exactly.

### M-1 — Candidate profile sub-collections: 20 → 10 *(the single largest reduction)*

**Affected:** `GET`/`POST`/`PATCH`/`DELETE` × `educations`, `work-experiences`, `organizations`, `certifications`, `links` — 20 endpoints.

**The finding is an inconsistency inside the contract itself.** `candidate_skills` already uses a two-endpoint **sync** pattern:

```
GET /api/v1/candidate/skills
PUT /api/v1/candidate/skills      ← full replacement
```

while five structurally identical sibling collections use four-method CRUD. Same parent, same ownership rule, same audit event, same UI. There is no principled reason for the split — `skills` got the better pattern and the others did not.

**Recommendation:** apply the sync pattern uniformly.

```
GET /api/v1/candidate/{collection}
PUT /api/v1/candidate/{collection}      ← full replacement
```

**Why sync is the better fit here, not merely fewer URIs:**

- The Stitch *Profil Saya* baseline edits these as **one form with repeatable rows**, then saves. Four-method CRUD forces the client to diff the form against the server and emit N calls — the client must compute which rows were added, changed, and deleted, and a partial failure leaves the profile half-saved.
- A `PUT` sync is **naturally idempotent** and atomic: one transaction, one audit event, no orphaned child rows.
- These are small, bounded, self-owned records with no independent lifecycle, no cross-references, and no per-row authorization. Nothing needs a stable per-row URI.

**Counter-argument, stated honestly:** a candidate with 30 certifications resends all of them to add one. At this data size that is irrelevant, and it is the trade `skills` already makes.

**Result:** 20 → 10 endpoints, and the candidate profile becomes internally consistent.

### M-2 — `GET /api/v1/career-center/company-verifications` → `GET /api/v1/companies`

**Finding:** there is **no generic company list endpoint** anywhere in the contract. There is `POST /companies`, `GET /companies/{company}`, and this Career-Center-specific queue — so the queue is doing double duty as the only company listing, under a name that hides it.

This also breaks the pattern the rest of the API follows: `GET /vacancies` and `GET /applications` are single role-scoped list endpoints, not one endpoint per audience.

**Recommendation:** rename to `GET /api/v1/companies` with role-scoped results and a `status` filter. Career Center's default view is `?status=PENDING_VERIFICATION`; a recruiter sees only their own company; Auditor and Super Admin see their permitted scope.

**Result:** 1 → 1 (a rename, not a reduction) — but it removes a naming inconsistency and fills a genuine gap.

### M-3 — `GET /api/v1/applications/{application}/screening-answers` → `?include=screening_answers`

**Finding:** the contract already defines an allow-listed `include` mechanism on `GET /applications/{application}`, and already lists `screening_answers` among its permitted values. The standalone endpoint duplicates a mechanism that exists.

Screening answers are small, bounded, never paginated, and always read together with the application. They carry no separate authorization rule — the same scope governs both.

**Kept as standalone by contrast:** `history` (paginated, and filtered by `candidate_visibility` for candidates), `documents` (separately authorized per share), and `evaluations` (separately authorized by `ASSIGNED_STAGE`). Those three have their own rules; screening answers do not.

**Result:** 1 → 0.

### M-4 — `GET /reports/companies` + `GET /reports/partnerships` → `GET /reports/company-overview`

**Finding:** FR-REP-002 renders both on **one** Career Center dashboard, both are Career-Center-scoped, both are small aggregate counts, and both are fetched together on every page load. Two endpoints means two round trips and two cache entries for one screen.

**Result:** 2 → 1.

### M-5 — `GET /api/v1/audit-logs/{auditLog}` → merge into the list

**Finding:** `GET /audit-logs` already returns complete rows — actor, action, object type and id, `change_summary`, `correlation_id`, timestamp. The detail endpoint returns the same fields for one row.

The one defensible reason to keep it — a very large `change_summary` payload — is not supported by anything in the model: summaries are redacted and bounded by design (INV-035, FR-AUD-001).

**Result:** 1 → 0. If large-payload audit entries later appear, reintroducing it is trivial.

---

## Duplicates

**2 endpoints → 0.** Both return data another endpoint already returns.

### D-1 — `GET /api/v1/companies/{company}/partnership-status`

`GET /api/v1/companies/{company}` **already returns** `mitra_kampus_active`, derived from an ACTIVE partnership within its period. This endpoint returns the same derived value from the same source with the same authorization.

Worse, a second endpoint computing a derived value invites the two to drift — exactly the failure mode INV-020 exists to prevent (partnership state must never become a second company status).

**Recommendation:** remove. Consumers read `mitra_kampus_active` from the company resource.

### D-2 — `GET /api/v1/notifications/unread-count`

The count is returned in **two** places already: `GET /me` (`unread_notification_count`) and — trivially addable — `meta` on `GET /notifications`.

A dedicated count endpoint exists almost entirely as a **polling** convenience, and polling an endpoint every few seconds to update a badge is implementation thinking, not a requirement. FSD names a notification centre, not a polling contract.

**Recommendation:** remove. Serve the badge from `/me` on load and from `meta.unread_count` after any notification read.

### D-3 — duplicated index row *(documentation defect, not an endpoint)*

`GET /api/v1/application-documents/{applicationDocument}/download` appears twice in `API_ENDPOINTS.md`. Fix the index; no contract change.

---

## Defer Candidates

**2 endpoints → 0 for MVP.** Deferred, not removed from scope.

### DF-1 — `POST /api/v1/admin/master-data/{collection}` and `PATCH /api/v1/admin/master-data/{collection}/{id}`

**Finding:** FSD §4.6 places master-data management in Super Admin scope, so this is legitimately in scope — but it is not on any MVP critical path. The six master entities (`organizational_units`, `study_programs`, `industries`, `organization_types`, `skills`, `geographic_areas`) are reference data that can be **seeded at deployment** and changed by a seeder re-run.

Building runtime CRUD for six heterogeneous collections means six validation shapes, cycle detection for the two hierarchical collections, and a deactivation-versus-delete rule for referenced rows — real work, for data that changes a few times a year.

**Recommendation:** keep `GET /api/v1/admin/master-data/{collection}` (needed to render forms) and defer the two write endpoints to a post-MVP phase. Seed master data initially.

**This is a scope-sequencing recommendation, not a scope reduction.** FSD §4.6 remains unmet until the write endpoints ship, and that must be stated explicitly rather than quietly dropped.

---

## Questionable

**4 endpoints flagged for decision. 3 recommended to keep, 1 to remove.**

### Q-1 — `DELETE /api/v1/vacancies/{vacancy}/screening-questions/{question}` — **recommend removal**

The contract itself says a question with answers **cannot** be deleted (`409 SCREENING_QUESTION_IN_USE`) and must be deactivated via `PATCH active=false`. So `DELETE` is valid only for a question that has never been answered — a narrow window that produces **two mechanisms for one concept** and an easy mistake: a recruiter tries `DELETE`, gets a `409`, and must discover that `PATCH` is the real path.

**Recommendation:** remove `DELETE`; deactivation is the single mechanism. Never-answered questions can also be deactivated, at no cost.

### Q-2 — `DELETE /api/v1/companies/{company}/documents/{document}` — **keep, with a rule to add**

Same shape as Q-1, but the contract does **not** currently state whether a document already submitted for verification can be deleted. If it can, a company could remove the evidence a Career Center decision was based on — which would undermine the append-only verification history that INV-016 protects.

**Recommendation:** keep the endpoint, and add an explicit rule: a document referenced by a submitted or completed verification is **archived, not deleted** — mirroring INV-032's treatment of shared candidate documents. **This is a genuine gap in the contract, not merely an endpoint-count question.**

### Q-3 — `PATCH /api/v1/candidate/documents/{document}` — **keep**

Thin (display name and type only), and a candidate correcting a mislabelled CV is a real need. It is deliberately not a content-replacement route — replacing content would silently alter what a recruiter already reviewed. Keep as is.

### Q-4 — `POST /api/v1/vacancies/{vacancy}/stages/reorder` — **keep**

Superficially mergeable into `PATCH .../stages/{stage}` with `sort_order`, but reordering is inherently a **whole-set** operation: a drag-and-drop UI produces one new ordering, and emitting N individual `PATCH` calls creates transient duplicate `sort_order` values and a partial-failure state. One atomic reorder is correct.

---

## Recommended Endpoint Count After Review

| Step | Count |
| --- | --- |
| Current distinct endpoints | **164** |
| M-1 candidate sub-collections 20 → 10 | −10 |
| M-3 screening-answers → `include` | −1 |
| M-4 two company reports → one | −1 |
| M-5 audit detail → list | −1 |
| D-1 partnership-status | −1 |
| D-2 unread-count | −1 |
| DF-1 master-data writes deferred | −2 |
| Q-1 screening-question `DELETE` | −1 |
| **Recommended** | **146** |

**An 11% reduction.** The number was not targeted — it is what these eight findings happen to produce. No business capability is lost: every merged endpoint's behaviour is preserved behind an existing mechanism, and the one deferral is explicitly recorded as leaving FR-VAC-004's master-data requirement unmet until a later phase.

If only the unambiguous items are accepted (D-1, D-2, Q-1, and the index dedupe), the count is **160** and the API is still coherent. **M-1 is the single change worth arguing for**, because it removes an inconsistency rather than merely a count.

---

## Critical Business Actions That Must Remain Explicit

**These 41 endpoints are protected.** Each represents a real state transition in a frozen baseline. Collapsing any of them into generic CRUD would let a client assert a target state the server had not independently validated.

| Domain | Actions | Frozen source |
| --- | --- | --- |
| Company verification | `submit-verification` · `verify` · `request-revision` · `reject` · `suspend` · `restore` | FSD §8.2, FR-ONB-003/004/005 |
| Vacancy moderation | `submit-review` · `approve` · `request-revision` · `reject` · `publish` · `close` · `suspend` · `restore` | FSD §8.3/§8.4, FR-VAC-004/005/006 |
| Application lifecycle | `transition` · `move-stage` · **`reopen`** · **`withdraw`** · `bulk-transition` | FSD §8.5, FR-APP-003/006/007 |
| Offering | `send` · **`accept`** · **`reject`** | FR-SEL-003/004/005 |
| Schedule | `reschedule` · `cancel` · `complete` · `no-show` | FR-SEL-001, INV-027 |
| External apply | `start` · `confirm` | FR-EXT-002/003, INV-012 |
| Selector assignment | `assign` · `revoke` | FR-HR-006, INV-037 |
| Outcome | `record` · `correct` | FR-EXT-004, INV-022 |
| Account | `suspend` · `restore` · `assign role` · `revoke role` | FSD §8.1, INV-025 |
| Consent | *(embedded in application submit — never a standalone toggle)* | FR-CONSENT-001, INV-011 |

Four deserve individual emphasis:

- **`reopen`** — the only mechanism that reuses an application lifecycle instead of creating a second row (INV-007, INV-008). A generic `PATCH` could not carry the `APPLICATION_REOPENED` event, the `reopen_count` increment, and the authorization check as one atomic, auditable act.
- **`withdraw`** — a candidate-only act that must never be reachable by a proxy, and must never delete anything (INV-009).
- **`accept`** — sets `offer_accepted_at`, the Time-to-Fill anchor (INV-013), under a single-winner concurrency rule (INV-031).
- **`external-apply/start`** — must be structurally incapable of creating an application row (INV-012, INV-024). A generic endpoint could not carry that guarantee.

---

## API Complexity Assessment

**Verdict: appropriately sized, with one structural concern that matters more than the endpoint count.**

### Why 164 is defensible

The API serves **five distinct portals** over one domain, with a state machine of four documented transition tables (FSD §8.1–§8.5), seven authorization scopes, and 37 data invariants. Comparable systems land in this range. Concretely:

- **41 endpoints (25%) are FSD-mandated state transitions.** Not reducible without losing server-side transition validation.
- **~63 endpoints (38%) are reads**, most of them role-scoped variants of a small number of resources.
- **Only 8 findings** produced a recommendation, and 5 of those are one inconsistency (M-1) plus two genuine duplicates.

There is **no evidence of speculative endpoint building**: no versioning-for-its-own-sake, no generic query endpoint, no RPC-shaped catch-all, no premature bulk operations beyond the one FR-APP-007 requires, and no analytics surface beyond FR-REP.

### The structural concern — larger than the count

**`ADR-001` states that `/api/v1` is "reserved for future clients; thin at MVP". The contract documents all 164 endpoints as if the entire surface were a public token-authenticated API at MVP.**

That is a real mismatch. The Inertia web surface calls the same Actions with the same validation and authorization, so the *contract* is correct for both — but shipping 164 token-authenticated public endpoints at MVP would mean building, testing, rate-limiting, versioning, and **committing to backward compatibility** for a surface that currently has no external consumer.

**Recommendation:** designate a **Phase 1 API surface** — a subset actually exposed under `/api/v1` at MVP — while the remainder is served by Inertia endpoints governed by the same contract sections.

| Phase 1 `/api/v1` — suggested | Rationale |
| --- | --- |
| Public reads (4) | Needed by SSR and any external consumer |
| Authentication (10) | Needed by any future client |
| Candidate self-service reads and writes (~20 after M-1) | The most likely first mobile surface |
| Notification reads (3) | Small, stable, useful to a mobile client |
| **~37 endpoints** | The rest remain Inertia-only until a real consumer exists |

This changes **no contract text and no business behaviour**. It changes what is *routed*, *rate-limited*, and *version-committed* at MVP — and it is the difference between an API with 37 endpoints under a compatibility promise and one with 164.

### Complexity distribution

| Domain | Endpoints | Assessment |
| --- | --- | --- |
| Candidate profile | 27 | **Highest concentration and the only genuine over-granularity.** M-1 brings it to 17 |
| Vacancy | 19 | Justified — 8 are FSD transitions |
| Company | 16 | Justified — 6 are FSD transitions |
| Application | 17 | Justified — the densest invariant cluster in the model |
| Reporting | 9 | Slightly thin at the edges (M-4) |
| Administration | 8 | Two deferrable (DF-1) |
| Everything else | 68 | Proportionate |

### Findings that are not about size

Two things surfaced during this review that are worth more than the count reduction:

1. **Q-2 is a contract gap, not an endpoint question.** Whether a company legal document submitted for verification can be deleted is undefined. If it can, verification evidence is destructible — which conflicts with the append-only intent of INV-016. **This should be resolved before implementation regardless of any decision about endpoint count.**
2. **The candidate-profile inconsistency (M-1)** shows `skills` was designed with a better pattern than its five siblings. Whichever way it is resolved, the six collections should behave the same way, because a client author will otherwise assume the pattern they meet first applies to all of them.

**Bottom line:** the API is not over-engineered. It is dense in proportion to a genuinely dense domain, it contains one real inconsistency, two real duplicates, and one real contract gap — and its most important sizing decision is not how many endpoints exist on paper, but how many are exposed under a compatibility promise at MVP.
