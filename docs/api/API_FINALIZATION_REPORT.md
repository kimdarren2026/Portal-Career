# API Finalization Report — Portal Karir Kampus

**Date:** 24 August 2026
**Pass:** Final semantic correction before API freeze
**Baselines:** BRD v1.1 · FSD v1.1 · Stitch canonical baseline · Logical model 1.1-C2 · Architecture ADR-001 to ADR-017

No Laravel code, route, controller, Form Request, model, migration, or SQL was created. BRD, FSD, ERD, architecture documents, and Stitch files were not modified. `API_SIZE_REVIEW.md` is retained unedited as historical evidence.

---

## 1. Corrections Applied

| # | Correction | Outcome |
| --- | --- | --- |
| 1 | Career Center company-vacancy authoring | **Resolved — DENY.** New error code, contract text, matrix ruling |
| 2 | Company legal document delete rule (Q-2) | **Resolved.** Draft-only `DELETE`; `supersede` for verification evidence |
| 3 | Candidate repeatable collections (M-1) | **Resolved.** `GET` + `PUT` sync across all six collections |
| 4 | Versioned API vs Inertia surface split | **Resolved.** Every operation classified; two inventories generated |
| 5 | Duplicate index row | **Removed.** Inventories now generated with enforced deduplication |
| 6 | Idempotency on both surfaces | **Preserved.** Stated explicitly in three documents |
| 7 | DB-1 / DB-2 | **Carried forward** to `DATABASE_SCHEMA.md` |

---

## 2. Career Center Vacancy Authoring — Resolved

**Ruling: Career Center is `DENY` for company-vacancy create and edit.**

FSD §3.2's phrase *"atas nama sesuai kewenangan"* was previously treated as **DENY by default** with the ambiguity flagged. It is now a settled ruling, on a substantive basis rather than a conservative default: **a moderator who could also author would be reviewing their own submission.** The separation of authoring from moderation is the entire point of FR-VAC-006.

Career Center responsibilities remain unchanged and complete: company verification, vacancy moderation, partnership management, permitted monitoring and reporting.

| Applied to | Change |
| --- | --- |
| `API_CONTRACT.md` — `POST /companies/{company}/vacancies` | Explicit DENY clause added ahead of the verification gate |
| `API_CONTRACT.md` — `PATCH /vacancies/{vacancy}` | "Career Center is DENY — it moderates vacancy content, it never authors or edits it" |
| `ERROR_CODES.md` | New `COMPANY_VACANCY_AUTHORING_FORBIDDEN` (403) |
| `AUTHORIZATION_MATRIX.md` §3 | Career Center scope definition gains an explicit non-authoring paragraph |
| `AUTHORIZATION_MATRIX.md` §4.5 | Separate **Edit company vacancy** row added; footnote ⁵ rewritten as RESOLVED |
| `AUTHORIZATION_MATRIX.md` §7 | Item struck through — no longer an open question |

**Future delegated posting** must be an explicit separately authorized capability, scoped to a named company, and separately audited. It is never granted implicitly by holding the Career Center role.

---

## 3. Company Legal Document Rule — Q-2 Resolved

The gap: the contract did not say whether a document already submitted for verification could be deleted. If it could, a Career Center decision would reference evidence that no longer exists — defeating the reconstructability INV-016 guarantees.

| Document state | Permitted action | Route |
| --- | --- | --- |
| **Never part of a submitted verification package** | Remove or replace outright | `DELETE /companies/{company}/documents/{document}` |
| **Included in any submitted package** | **Destructive deletion forbidden.** Superseded by a replacement, previous retained as archived evidence | `POST /companies/{company}/documents/{document}/supersede` |

**Supersede semantics:** permitted only in `DRAFT` or `REVISION_REQUIRED` — a package under active review must not change beneath the reviewer. Atomically stores the replacement as a new row of the same `document_type`, marks the previous row archived and superseded with a pointer to its replacement, and writes audit. **The superseded document is never deleted and remains downloadable** to Career Center, Auditor, and Super Admin, so any past decision stays reconstructable.

**There is no generic destructive `DELETE` for verification evidence.** New code: `COMPANY_DOCUMENT_IS_VERIFICATION_EVIDENCE` (409), whose response names `supersede` as the correct action.

---

## 4. Candidate Repeatable Collections — M-1 Applied

Six collections moved from four-method CRUD to `GET` + `PUT` synchronization: **educations · work-experiences · organizations · certifications · links · skills**.

`skills` already used this pattern; the other five now match it, removing an inconsistency where structurally identical siblings behaved differently for no principled reason.

**Why sync, beyond the count:** the frozen Stitch *Profil Saya* baseline edits each section as one owned form with repeatable rows, then saves. Per-row CRUD forces the client to diff its form against the server and emit N requests, where a partial failure leaves the profile half-saved and the candidate unable to tell which rows persisted.

**Requirements met:**

| Requirement | How |
| --- | --- |
| Candidate owns all rows | Parent taken from the authenticated actor, never the payload |
| All rows validated before commit | Row-level and collection-level validation completes first |
| Atomic per collection | One transaction — creates, updates, deletes together |
| Stable IDs accepted and returned | `items[].id` present → update; absent → create; every resulting row returns its id |
| **Omission/removal semantics documented** | **`PUT` replaces the entire collection. A row absent from `items[]` is deleted. Omission is removal, never "leave unchanged"** — which is why this is `PUT` and never `PATCH`. An explicit `items: []` clears the collection |
| Audit appropriate | One `candidate_profile_section_changed` per sync, with counts created/updated/removed |
| **No partial update on failure** | If any row fails, nothing is written; the response reports every failing row by index so the form highlights all errors at once |

Work preferences remain fields on `candidate_profiles`, unchanged. Deleting a certification does not delete its linked private document — `document_id` is a reference.

**Endpoint effect: 20 → 12.**

---

## 5. Surface Split — VERSIONED_API vs INERTIA_WEB

ADR-001 states `/api/v1` is thin at MVP, but the contract presented all operations as versioned API surface. That mismatch is now closed. **Every operation carries a `Surface` line**; behaviour is unchanged on both.

| | VERSIONED_API | INERTIA_WEB |
| --- | --- | --- |
| Route | `/api/v1/…` | Application route, no version prefix |
| Auth | Sanctum bearer token | Session cookie, **CSRF protected** |
| Compatibility promise | **Yes** | **No** |
| Inventory | `API_ENDPOINTS.md` | `INERTIA_ACTIONS.md` |
| Actions · Policies · validation · invariants · error codes · audit · outbox · idempotency · concurrency | **Identical** | **Identical** |

### Phase-1 versioned surface: 57 operations

**Selection principle:** an operation is versioned when a consumer outside our deploy cycle plausibly depends on it.

| Included | Why |
| --- | --- |
| Public reads (4) | Consumed by SSR and search engines; public by definition |
| Authentication and session (12) | Any future client must authenticate |
| Complete candidate capability set (41) | Profile, documents, verifications, saved vacancies, apply, my applications, withdraw, reopen, external apply, schedules read, offers accept/reject, notifications |

**Why the complete candidate set rather than a smaller slice:** it is a *coherent whole*. The candidate portal is the only channel with a plausible near-term second client, and a candidate API able to log in but not to apply would be worse than either extreme. Back-office operations — recruiter, Career Center, Kepegawaian, Selector, Auditor, Super Admin — have no plausible non-web consumer at MVP and are internal.

**This is not 37.** The size review's 37 was illustrative; 57 is what the coherence principle actually produces, and the brief explicitly said not to force the number.

Promotion from internal to versioned is additive — a route, a token guard, a compatibility commitment — with no behavioural change.

---

## 6. Endpoint Reduction Applied

| Change | Effect |
| --- | --- |
| M-1 candidate collections 20 → 12 | −8 |
| M-3 screening-answers → `?include=screening_answers` | −1 |
| M-4 `reports/companies` + `reports/partnerships` → `reports/company-overview` | −1 |
| M-5 audit-entry detail → list already returns full rows | −1 |
| D-1 `companies/{company}/partnership-status` → `mitra_kampus_active` on the company resource | −1 |
| D-2 `notifications/unread-count` → `/me` and list `meta` | −1 |
| DF-1 master-data write operations deferred beyond MVP | −2 |
| Q-1 screening-question `DELETE` → deactivate only | −1 |
| Q-2 `documents/{document}/supersede` added | **+1** |
| **164 → 148** | **−16** |

M-2 (`career-center/company-verifications` → `GET /companies`) was **not** applied in this pass: it is a rename affecting no behaviour, and renaming a route while resolving four semantic rulings adds churn without value. Recorded for a later editorial pass.

**Nothing was reduced to hit a target.** Every removal folds a capability into an existing mechanism; the one deferral is explicitly recorded as leaving FSD §4.6's master-data requirement unmet until a later phase, rather than quietly dropped.

---

## 7. Duplicate Rows Removed

`GET /api/v1/application-documents/{applicationDocument}/download` appeared twice in the previous index — once from its contract heading, once from a cross-reference.

Both inventories are now **generated from the contract with enforced deduplication**, and validation confirms zero duplicate method+route pairs within either surface. The contract operation itself was not removed.

---

## 8. Idempotency and Concurrency — Preserved on Both Surfaces

**Business safety does not weaken because an action is reached through a session cookie.** Stated explicitly in `API_CONTRACT.md` Part I §2b, in `INERTIA_ACTIONS.md`, and in this report.

Every operation marked **Idempotency: REQUIRED** keeps that requirement regardless of surface — including internal ones: company verification submit and all six verification decisions, vacancy submit and all eight moderation actions, application transition, move-stage, bulk-transition, offer send, schedule actions, selector assignment, outcome recording, and document supersede.

Concurrency rules are likewise unchanged: aggregate row locks on every gate, `If-Match` where concurrent editors are realistic, and the single-winner rule for offer acceptance (INV-031).

---

## 9. DB-1 / DB-2 Carry-Forward

Neither is a business-model change. Both are operational infrastructure like `sessions`, `jobs`, and `failed_jobs`, so **neither requires an ERD change request** and logical model 1.1-C2 is untouched.

| # | Requirement | Detail |
| --- | --- | --- |
| **DB-1** | Idempotency key and replay-response persistence | Key + actor + endpoint + retained response, ≥24 hours, with expiry. **Required on both surfaces** |
| **DB-2** | Asynchronous export job tracking | Whether Laravel's job/batch infrastructure suffices or a dedicated table is needed |

Recorded in `API_CONTRACT.md` Part XI and `docs/api/README.md`.

---

## 10. Final Size

| Measure | Count |
| --- | --- |
| **A. Total logical HTTP/business operations** | **148** |
| **B. VERSIONED_API endpoints** | **57** |
| **C. INERTIA_WEB actions** | **91** |
| **D. Named lifecycle/business actions** | **40** |

**A ≠ B.** Total server capability is 148 operations; the **versioned API surface under a compatibility promise is 57**. Conflating the two was the structural error this pass corrected — the earlier "164 endpoints" figure described total capability while implying a public API commitment 2.6× larger than justified.

The 40 named lifecycle actions are distributed across both surfaces and are unchanged in behaviour.

---

## 11. Cross-Document Validation

All checks executed programmatically against the generated documents.

| # | Check | Result |
| --- | --- | --- |
| 1 | Every VERSIONED_API contract entry appears exactly once in `API_ENDPOINTS.md` | **PASS** — 57/57 |
| 2 | Every `API_ENDPOINTS.md` entry exists in `API_CONTRACT.md` | **PASS** |
| 3 | Every INERTIA_WEB entry appears exactly once in `INERTIA_ACTIONS.md` | **PASS** — 91/91 |
| 4 | Every `INERTIA_ACTIONS.md` entry exists in `API_CONTRACT.md` | **PASS** |
| 5 | No duplicate method+route pairs within a surface | **PASS** |
| 6 | Career Center cannot normally author company vacancies | **PASS** |
| 7 | Submitted company legal evidence cannot be destructively deleted | **PASS** |
| 8 | Repeatable candidate collections use the approved sync approach | **PASS** |
| 9 | External ATS never creates applications (INV-012) | **PASS** |
| 10 | IN_PORTAL-only applications enforced (INV-024) | **PASS** |
| 11 | Consent receiver server-derived (INV-023) | **PASS** |
| 12 | Document sharing application-scoped (INV-010, INV-032) | **PASS** |
| 13 | Selector lists assignment-scoped (INV-037) | **PASS** |
| 14 | Offer concurrency and idempotency defined (INV-031) | **PASS** |
| 15 | Time-to-Fill remains `published_at → offer_accepted_at` (INV-013) | **PASS** |
| 16 | SMTP secret never returned or logged (INV-035) | **PASS** |
| 17 | All five remaining business open questions remain open; D-1 is closed by subsequent approved Product Owner decision | **PASS** |
| 18 | DB-1 and DB-2 carried to `DATABASE_SCHEMA.md` | **PASS** |
| 19 | No Laravel or SQL implementation file created | **PASS** |
| 20 | Every contract section carries a `Surface` classification | **PASS** — 82/82 |
| 21 | Idempotency applies identically on both surfaces | **PASS** |

---

## 12. Open Questions — All Preserved

| # | Question | Status |
| --- | --- | --- |
| 1 | Alumni verification integration source | **Open** — verification request fields remain PENDING BUSINESS DECISION; no integration client specified; candidate-type change endpoint deliberately undefined |
| 2 | Minimum company legal documents per organization type | **Open** — at least one document required before submit (INV-030); no type matrix fixed |
| 3 | Salary mandatory / display policy | **Open** — fields nullable, no rule encoded, `SALARY_REQUIRED` reserved and unused |
| 4 | Recruiter domain / subdomain | **Open** — affects cookie scope, CORS, CSP; no URI or payload in this contract |
| 5 | WhatsApp notification phase | **Open** — no channel, provider, template, or adapter anywhere |
| 6 | First recruiter default role / minimum active Company Admin | **Closed by approved Product Owner decision:** first creator is active `COMPANY_ADMIN`; minimum one active `COMPANY_ADMIN` and last-admin protection are required; subsequent roles are explicit with no implicit default |

**Human-decision items:** H-2 (vacancy-level outcome), H-3 (candidate revocation of a shared document), and H-4 (audit IP/device collection) remain open. H-1 was resolved by ADR-016.

The Career Center delegated-posting question was **not** one of the six; it was an API-level ambiguity, and it is now resolved as DENY.

---

## 13. API Freeze Recommendation

**RECOMMEND FREEZE.**

All four semantic corrections are applied, all 21 validation checks pass, and every invariant the contract depends on is verified present. No ambiguity remains that would force an implementer to invent business behaviour.

**Freeze scope:** `API_CONTRACT.md` (behaviour, both surfaces) · `API_ENDPOINTS.md` (57 versioned endpoints) · `INERTIA_ACTIONS.md` (91 internal actions) · `ERROR_CODES.md` · `AUTHORIZATION_MATRIX.md`.

**Next artefact:** `docs/database/DATABASE_SCHEMA.md`, now unblocked. It should address first the model's least portable guarantees — the **five conditional-uniqueness rules** (INV-017, INV-025, INV-031, INV-036, INV-037) and **three XOR rules** (INV-018, INV-022, INV-023) — plus **DB-1** and **DB-2**.

**Two items carried into implementation planning, neither blocking:**

1. **M-2 rename** — `GET /career-center/company-verifications` → `GET /companies` with role-scoped results. No behavioural change; a later editorial pass.
2. **Master-data write operations (DF-1)** — FSD §4.6 remains unmet until they ship. Master data is seeded at deployment for MVP, and this must stay visible rather than being forgotten as delivered.
