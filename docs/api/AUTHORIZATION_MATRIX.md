# Authorization Matrix — Portal Karir Kampus

**Status:** Proposed — awaiting approval
**Date:** 24 August 2026
**Baselines:** BRD v1.1 · FSD v1.1 (§3.1 roles, §3.2 access matrix, §3.3 object-level authorization) · Logical model 1.1-C2 · Architecture ADR-001 to ADR-017
**Revision:** Final semantic correction pass, 24 August 2026 — Career Center vacancy-authoring ruling and candidate collection sync applied.
**Companions:** `API_CONTRACT.md` · `API_ENDPOINTS.md` · `INERTIA_ACTIONS.md` · `ERROR_CODES.md`

**This matrix applies identically to both surfaces.** A `VERSIONED_API` request and an `INERTIA_WEB` request are authorized by the same Policies and the same query scopes. The surface split governs routing, authentication transport, and compatibility — **never** who may do what.

This matrix is the **binding authorization reference**. Where it and an endpoint's *Authorization* section in `API_CONTRACT.md` appear to differ, they must be reconciled before implementation — neither silently overrides the other.

---

## 1. The Governing Rule

> **A role is never sufficient on its own where object ownership applies.**

FSD §3.3 requires RBAC to be combined with object ownership. Every capability in this document is therefore evaluated in **three layers**, all of which must pass:

| Layer | Mechanism | Answers | Catches |
| --- | --- | --- | --- |
| **1. Coarse gate** | Route middleware — authenticated, email verified, holds a qualifying role | "May this *kind* of user reach this route?" | Wrong audience at the door |
| **2. Object authorization** | Laravel Policy, invoked per object | "May *this actor* act on *that object*?" | IDOR |
| **3. Query scoping** | Ownership-scoped query at the query layer | "Which objects may this actor *see at all*?" | **Enumeration** |

**Layer 3 is the one most often omitted and the one that leaks candidate data across companies.** A Policy protects `show`; it does nothing for `index`. Every list endpoint in this API is scoped in its query — never filtered after fetch, because a post-filter has already loaded another company's rows into memory and still leaks through counts and pagination totals.

### Not-found versus forbidden

| Situation | Response |
| --- | --- |
| The actor may legitimately know the object exists but may not perform this action | `403 AUTH_FORBIDDEN` |
| The actor may **not** know the object exists — another company's vacancy, another candidate's application, an unpublished vacancy on a public route | `404 NOT_FOUND` |

Returning `403` for an out-of-scope object confirms its existence and leaks a company's applicant volume or a candidate's activity by probing identifiers.

---

## 2. Value Legend

| Value | Meaning |
| --- | --- |
| **ALLOW** | Permitted without an ownership constraint on this capability |
| **OWN** | Permitted **only** for objects belonging to the acting user — own candidate profile, own documents, own applications, own notifications |
| **COMPANY_SCOPE** | Permitted **only** for objects belonging to a company where the actor holds an **active** `company_members` row (`revoked_at` null). Reaches that company's vacancies and, through them, their applications and children |
| **CAMPUS_SCOPE** | Permitted **only** for vacancies where `ownership_type = CAMPUS`, and their applications and children. Optionally narrowed further by `organizational_unit_id` |
| **ASSIGNED_STAGE** | Permitted **only** where an **active** `selection_stage_assignments` row (`revoked_at` null) links the actor to the specific `recruitment_stages` row. Never extends to another stage of the same vacancy, and never to another vacancy |
| **READ_ONLY** | Read permitted within the actor's permitted scope. **No write ability exists on any Policy** for this role and capability |
| **DENY** | Not permitted. The capability is absent, and out-of-scope objects return `404` |

---

## 3. Role Scope Definitions

The nine columns of every matrix below. Each definition is binding.

### PUBLIC
Unauthenticated visitor. Holds **no stored role** — `PUBLIC` appears in FSD §3.1 but is deliberately not a `user_roles` assignment, because an unauthenticated visitor has no assignment to hold. Reaches only `GET /api/v1/public/*`, which exposes exclusively `PUBLISHED` vacancies inside their open window, with `INTERNAL`-audience vacancies excluded entirely.

### CANDIDATE
**Own candidate, profile, application, and document scope only.**

Resolved through `candidate_profiles.user_id = actor`. Reaches: own profile and its sub-collections, own documents, own applications and their history, own screening answers, own schedules, own offers, own consents, own external-apply events, own saved vacancies, own notifications.

A candidate can **never** read another candidate's anything. Candidate identity (`current_candidate_type`) and candidate role codes are **not** eligibility — target-audience eligibility is evaluated **only** against a VERIFIED `candidate_verifications` record (INV-028).

### COMPANY_RECRUITER
**Own company and own-company vacancy/application scope only.**

Resolved through an **active** `company_members` row. Reaches: that company's profile and documents, its vacancies, those vacancies' applications and their children — schedules, evaluations, offers, outcomes.

Cannot reach another company's objects under any circumstance (INV-017). Cannot moderate any vacancy, including its own. Cannot verify any company, including its own. A revoked membership grants nothing; membership is re-checked per request and **never cached across requests** (ADR-006).

### COMPANY_ADMIN
Everything `COMPANY_RECRUITER` has, **within the same company scope**, plus member management for that company. It is a company-level role, **not** a platform-level one. A Company Admin has no visibility outside their own company.

> **D-1 CLOSED by approved Product Owner decision:** the first company creator is the active `COMPANY_ADMIN`; the company must retain at least one active `COMPANY_ADMIN`; last-admin protection is enforced for membership mutations. Subsequent member roles are explicitly selected and have no implicit default.

### CAREER_CENTER
**Verification and moderation scope — explicitly not recruiter candidate-selection scope.**

Reaches: company verification queue and decisions, company vacancy moderation decisions, partnership records, alumni-outcome reporting, and monitoring views of companies and company vacancies.

**Does not reach candidate selection.** FSD §3.3 is explicit: Career Center may monitor a company's vacancies but may **not** decide candidate acceptance on that company's behalf. It therefore cannot transition applications, move stages, create schedules, write evaluations, or issue offers — for any company. This is the single most important boundary in this matrix, because "monitors companies" reads deceptively close to "manages their candidates".

**Does not author company vacancies** (final ruling, 24 August 2026). Career Center is **DENY** for company-vacancy create and edit. Company vacancy ownership and authoring remain with active, verified company members. FSD §3.2's phrase "atas nama sesuai kewenangan" is **not** read as an implicit grant — a moderator who could also author would be reviewing their own submission. If delegated posting is introduced in a future phase it must be **an explicit separately authorized capability, scoped to a named company, and separately audited**, never an implicit consequence of holding this role.

### HR_ADMIN
**Campus recruitment scope** (Admin Kepegawaian / HR-SDM).

Reaches: campus vacancies (`ownership_type = CAMPUS`) and everything beneath them — applicants, stages, selector assignments, schedules, evaluations, offers, outcomes, campus reports.

Cannot create or manage company vacancies, cannot verify companies, cannot moderate company vacancies. Campus vacancies are never moderated (INV-018), so no moderation capability exists on this path at all.

### SELECTOR
**Active `ASSIGNED_STAGE` scope only.**

Holding the SELECTOR role authorizes **being assigned**; it grants no access by itself (INV-037). Access requires an active `selection_stage_assignments` row for the specific stage, and reaches only that stage's applications, schedules, and evaluations — plus documents shared with those applications.

A selector cannot assign, extend, or revoke assignments, including their own. Revocation takes effect immediately. **Selector list endpoints are query-scoped through the assignment join**, so an unassigned stage's candidates never appear.

### AUDITOR
**Permitted read-only audit and report scope.**

Reaches: `audit_logs` and reporting within its permitted scope. **No Policy grants this role any write ability anywhere in the API** — this is structural, not a convention. Auditor cannot transition, moderate, verify, assign, evaluate, or offer.

### SUPER_ADMIN
**Administrative scope — but still bound by the secret-handling rules.**

Reaches: roles and user administration, master data, SMTP configuration, audit, and break-glass access to business objects.

Two constraints hold absolutely and are **not** waived by this role:

1. **The SMTP credential is never returned to anyone, including Super Admin** (INV-035). `GET /admin/smtp-configuration` returns `secret_configured: true` — a boolean, never the value, never a mask conveying length. Update accepts a new secret; nothing ever reads one back.
2. **Every Super Admin bypass is audited**, and audit entries never carry credential material. A break-glass path that is not recorded is indistinguishable from a compromise.

3. **Break-glass is a read capability, not an authoring one.** Company vacancy authoring — create, edit, screening-question management, and submitting as the company owner — is **never** granted by this role (PO decision **VA-4**, 25 August 2026; §4.5 footnote ³⁶). Where a Super Admin also holds an ACTIVE `company_members` row, they author **as that member** and only within that company.

Super Admin also cannot mutate `audit_logs` — no create, update, or delete endpoint exists for anyone (INV-016).

---

## 4. Capability Matrices

Legend: **A**=ALLOW · **O**=OWN · **C**=COMPANY_SCOPE · **K**=CAMPUS_SCOPE · **S**=ASSIGNED_STAGE · **R**=READ_ONLY · **D**=DENY

### 4.1 Authentication and Session

| Capability | PUBLIC | CANDIDATE | COMPANY_RECRUITER | COMPANY_ADMIN | CAREER_CENTER | HR_ADMIN | SELECTOR | AUDITOR | SUPER_ADMIN |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Register candidate | **A** | D | D | D | D | D | D | D | D |
| Register recruiter | **A** | D | D | D | D | D | D | D | D |
| Verify email · resend · forgot · reset password | **A** | **A** | **A** | **A** | **A** | **A** | **A** | **A** | **A** |
| Login · logout | **A** | **A** | **A** | **A** | **A** | **A** | **A** | **A** | **A** |
| Read own session context (`/me`) | D | **O** | **O** | **O** | **O** | **O** | **O** | **O** | **O** |
| Change own password | D | **O** | **O** | **O** | **O** | **O** | **O** | **O** | **O** |

`/me` returns **only** the actor's own context. It never returns a permission matrix, ability list, or Policy map — the backend stays authoritative, and shipping a capability list would invite the client to treat it as truth.

### 4.2 Candidate Profile and Documents

| Capability | PUBLIC | CANDIDATE | COMPANY_RECRUITER | COMPANY_ADMIN | CAREER_CENTER | HR_ADMIN | SELECTOR | AUDITOR | SUPER_ADMIN |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Read own candidate profile | D | **O** | D | D | D | D | D | D | **A** |
| Update own candidate profile | D | **O** | D | D | D | D | D | D | D |
| Read / synchronize education · experience · organizations · certifications · links · skills | D | **O** | D | D | D | D | D | D | D |
| Request candidate verification | D | **O** | D | D | D | D | D | D | D |
| Read own verification status | D | **O** | D | D | D | D | D | D | **A** |
| **List own private documents** | D | **O** | **D** | **D** | **D** | **D** | **D** | D | **A** |
| Upload / update / archive own document | D | **O** | D | D | D | D | D | D | D |
| **Download own private document** | D | **O** | **D** | **D** | **D** | **D** | **D** | D | **A** |
| Manage own saved vacancies | D | **O** | D | D | D | D | D | D | D |

**No recruiter, HR admin, selector, or Career Center staff member can list or download a candidate's private documents through any candidate route.** Reaching a candidate profile grants **no** document access whatsoever (INV-010). The only path to a candidate file is `GET /api/v1/application-documents/{applicationDocument}/download` — see §4.6.

Super Admin's `ALLOW` on candidate reads is break-glass, audited on every use, and should require a stated reason for document content.

### 4.3 Company Profile, Documents, Members

| Capability | PUBLIC | CANDIDATE | COMPANY_RECRUITER | COMPANY_ADMIN | CAREER_CENTER | HR_ADMIN | SELECTOR | AUDITOR | SUPER_ADMIN |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Create company profile | D | D | **A** | **A** | D | D | D | D | **A** |
| Read company profile | D | D | **C** | **C** | **A** | D | D | **R** | **A** |
| Update company profile | D | D | **C** | **C** | **D** | D | D | D | **A** |
| Upload / delete company legal document | D | D | **C** | **C** | D | D | D | D | **A** |
| Download company legal document | D | D | **C** | **C** | **A** | D | D | **R** | **A** |
| Read verification history | D | D | **C** ¹ | **C** ¹ | **A** | D | D | **R** | **A** |
| List company members | D | D | **C** | **C** | **A** | D | D | **R** | **A** |
| Invite / change role / revoke member | D | D | **D** ² | **C** | D | D | D | D | **A** |
| Public company summary | **A** | **A** | **A** | **A** | **A** | **A** | **A** | **A** | **A** |

¹ Recruiters see verification history **without `internal_note`**. Internal notes are visible only to Career Center, Auditor, and Super Admin (FR-ONB-004).
² Member management is a Company Admin capability. Subsequent member roles must be explicitly selected; no implicit role default exists.

**Career Center cannot edit a company's profile.** It reviews and decides; the recruiter corrects (FR-ONB-005).

### 4.4 Company Verification and Partnership

| Capability | PUBLIC | CANDIDATE | COMPANY_RECRUITER | COMPANY_ADMIN | CAREER_CENTER | HR_ADMIN | SELECTOR | AUDITOR | SUPER_ADMIN |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| **Submit company for verification** | D | D | **C** | **C** | D | D | D | D | **A** |
| View verification queue | D | D | D | D | **A** | D | D | **R** | **A** |
| **Verify company** | D | D | **D** | **D** | **A** | **D** | D | D | **A** |
| **Request revision** | D | D | D | D | **A** | D | D | D | **A** |
| **Reject company** | D | D | D | D | **A** | D | D | D | **A** |
| **Suspend company** | D | D | D | D | **A** | D | D | D | **A** |
| **Restore company** | D | D | D | D | **A** | D | D | D | **A** |
| List / read partnerships | D | D | **C** ³ | **C** ³ | **A** | D | D | **R** | **A** |
| Create / update / activate / end partnership | D | D | **D** | **D** | **A** | D | D | D | **A** |
| Read own company partnership status | D | D | **C** | **C** | **A** | D | D | **R** | **A** |

³ A recruiter sees only their own company's partnership record.

**A recruiter can never verify their own company** — that would defeat the entire gate. **Generalized by approved decision, 25 August 2026: an active member of a company may never review that company, whatever their role.** The prohibition follows the reviewer, not the role code, and applies to Career Center staff, Career Center managers, and Super Admin alike; such a reviewer receives `403 AUTH_FORBIDDEN`. Super Admin's `ALLOW` on the five review actions below is confirmed and is now stated identically in each `API_CONTRACT.md` review section. **Partnership is never a verification substitute**: a VERIFIED company with no partnership creates vacancies normally, and no partnership capability appears in any vacancy-creation path (INV-003, INV-020, FR-ONB-006).

### 4.5 Vacancy, Moderation, Stages, Screening

| Capability | PUBLIC | CANDIDATE | COMPANY_RECRUITER | COMPANY_ADMIN | CAREER_CENTER | HR_ADMIN | SELECTOR | AUDITOR | SUPER_ADMIN |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Browse public vacancies · public detail · reference data | **A** | **A** | **A** | **A** | **A** | **A** | **A** | **A** | **A** |
| **Create company vacancy** | D | D | **C** ⁴ | **C** ⁴ | **D** ⁵ | D | D | D | **D** ³⁶ |
| **Edit company vacancy** | D | D | **C** | **C** | **D** ⁵ | D | D | D | **D** ³⁶ |
| **Create campus vacancy** | D | D | **D** | **D** | **D** | **K** | D | D | **A** |
| List / read vacancy (owner view) | D | D | **C** | **C** | **A** ⁶ | **K** | **S** ⁷ | **R** | **A** |
| Update vacancy (draft / revision) | D | D | **C** | **C** | **D** | **K** | D | D | **A** ³⁶ |
| **Submit vacancy for review** | D | D | **C** | **C** | D | **D** ⁸ | D | D | **D** ³⁶ |
| **Approve · reject · request revision** | D | D | **D** | **D** | **A** ³⁷ | **D** ⁸ | D | D | **A** ³⁷ |
| **Publish vacancy** | D | D | **D** ⁹ | **D** ⁹ | **D** ³⁸ | **K** | D | D | **D** ³⁸ |
| **Close vacancy** | D | D | **C** | **C** | **A** ³⁷ | **K** | D | D | **A** ³⁷ |
| **Suspend · restore vacancy** | D | D | D | D | **A** ³⁷ | **K** ¹⁰ | D | D | **A** ³⁷ |
| Read moderation history · versions | D | D | **C** ¹ | **C** ¹ | **A** | **K** | D | **R** | **A** |
| Manage screening questions | D | D | **C** | **C** | **D** | **K** | D | D | **A** ³⁶ |
| Manage recruitment stages · reorder | D | D | **C** | **C** | **D** | **K** | D | D | **A** |

⁴ Requires `companies.verification_status = VERIFIED` (INV-002). Not verified → `403 VACANCY_COMPANY_NOT_VERIFIED`.
⁵ **RESOLVED — DENY** (final ruling, 24 August 2026). FSD §3.2's "atas nama sesuai kewenangan" does **not** implicitly grant authoring. Career Center creates and edits **no** company vacancy. Attempting it returns `403 COMPANY_VACANCY_AUTHORING_FORBIDDEN`. Delegated posting, if ever introduced, becomes an explicit separately authorized capability, scoped to a particular company, and separately audited. This is no longer an open question.
⁶ Career Center reads company vacancies **for moderation**. This grants no access to their applicants — see §4.6.
⁷ A selector reads only the vacancy reachable through an active stage assignment, and only as context for that stage.
⁸ Campus vacancies are never moderated (INV-018). The moderation capabilities do not exist on the campus path.
⁹ A company vacancy publishes through moderation approval or the scheduler, not by recruiter action.
¹⁰ HR_ADMIN suspends and restores campus vacancies only.

³⁶ **Company authoring never derives from the global role** — PO decision **VA-4**, approved 25 August 2026. Holding `SUPER_ADMIN` **alone** grants **no** capability to create a company vacancy, edit a company vacancy, manage that vacancy's screening questions, or submit it as the company owner. Where the same user separately holds a **valid ACTIVE `company_members` row** for that company, their authoring capability is evaluated **from that membership role** (`COMPANY_ADMIN` or `COMPANY_RECRUITER`) exactly as for any other member — a membership in Company A grants nothing over Company B, and a revoked or inactive membership grants nothing at all regardless of the global role. On the two rows that also cover the campus path (*Update vacancy (draft / revision)*, *Manage screening questions*) VA-4 settles the **company** portion only, which the dedicated company rows above state as `DENY`; the campus portion of those cells is untouched by this decision. **Super Admin READ capability is unchanged** — the read rows in this section and §4.9 stand as frozen, break-glass and audited on every use (OL-9). **VA-4 settles no moderation authority**: approve, request revision, reject, publish, close, suspend and restore remain **OPEN** (`API_CONTRACT.md` Part X item 11).

³⁷ **Company vacancy moderation — PO decision B-3, approved 25 August 2026 (CLOSED).** The moderator set is `CAREER_CENTER_STAFF`, `CAREER_CENTER_MANAGER` and `SUPER_ADMIN`, for `REQUEST_REVISION`, `REJECT`, `APPROVE`, `SUSPEND`, `RESTORE` and `CLOSE`, each only where the source status permits. Super Admin runs the **same** lifecycle checks, reason requirements, moderation-history rules, audit attribution and notification behaviour — the global role never bypasses a source-status rule. **Conflict of interest: a moderator holding an ACTIVE `company_members` row for the owning company may not moderate that company's vacancy**, and this bars Career Center and Super Admin alike → `403 AUTH_FORBIDDEN`. Recruiters (`COMPANY_ADMIN`, `COMPANY_RECRUITER`) never moderate; `AUDITOR` stays read-only. **Close is the one row with two paths**: the owner closing their own published vacancy is an ownership capability (`C`) and is not subject to the conflict rule; closing as a moderator is. **VA-4 is untouched** — moderation authority confers no company authoring, and footnote ³⁶ still governs create, edit and screening questions.

³⁸ **No user-facing company publish — PO decision B-4, approved 25 August 2026 (CLOSED).** A company vacancy publishes only through **APPROVE inside its active window** (publication is part of the approval transaction) or through the **system scheduler** when a `SCHEDULED` vacancy reaches `open_at`. No recruiter, Career Center or Super Admin manual publish operation exists on the company path, and no company publish route is registered. `HR_ADMIN` keeps its campus publish capability (`K`), which these decisions do not touch.

### 4.6 Applications, Documents, Lifecycle

**The most consequential table in this document.**

| Capability | PUBLIC | CANDIDATE | COMPANY_RECRUITER | COMPANY_ADMIN | CAREER_CENTER | HR_ADMIN | SELECTOR | AUDITOR | SUPER_ADMIN |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| **Submit in-portal application** | D | **O** ¹¹ | **D** | **D** | **D** | **D** | **D** | D | **D** ¹² |
| List applications | D | **O** | **C** | **C** | **D** ¹³ | **K** | **S** | **R** ¹⁴ | **A** |
| Read application detail | D | **O** | **C** | **C** | **D** ¹³ | **K** | **S** | **R** ¹⁴ | **A** |
| Read status history | D | **O** ¹⁵ | **C** | **C** | **D** | **K** | **S** | **R** | **A** |
| Read screening answers | D | **O** | **C** | **C** | **D** | **K** | **S** | **R** | **A** |
| List documents shared with an application | D | **O** | **C** | **C** | **D** | **K** | **S** | D | **A** |
| **Download an application-shared document** | D | **O** | **C** ¹⁶ | **C** ¹⁶ | **D** | **K** ¹⁶ | **S** ¹⁶ | D | **A** |
| **Transition application status** | D | **D** ¹⁷ | **C** | **C** | **D** ¹³ | **K** | **D** ¹⁸ | D | **A** |
| **Move recruitment stage** | D | D | **C** | **C** | **D** | **K** | **D** ¹⁸ | D | **A** |
| **Bulk transition** | D | D | **C** | **C** | **D** | **K** | D | D | **A** |
| **Reopen application** | D | **O** ¹⁹ | **C** | **C** | **D** | **K** | D | D | **A** |
| **Withdraw application** | D | **O** | **D** ²⁰ | **D** ²⁰ | **D** | **D** ²⁰ | D | D | **D** ²⁰ |

¹¹ Requires email verified, a candidate profile, target-audience eligibility from a **VERIFIED** `candidate_verifications` record (INV-028), explicit consent (INV-011), and a vacancy with `application_method = IN_PORTAL` (**INV-024**).
¹² Super Admin does not apply on a candidate's behalf. Applying is an act of consent and cannot be performed by a proxy.
¹³ **Career Center has no candidate-selection scope.** It cannot list, read, transition, or move applications for company vacancies (FSD §3.3). Its alumni-outcome visibility is served by aggregate reporting in §4.9, not by applicant-level access.
¹⁴ Auditor's application visibility is read-only and limited to what its permitted audit/report scope allows.
¹⁵ A candidate sees only history events marked candidate-visible (FR-APP-005).
¹⁶ **This is the only path to a candidate's file.** It requires a non-revoked `application_documents` row for *that* application. A document the candidate owns but did not share with this application is unreachable, even if shared with a different application at the same company (INV-010, INV-032). Every download — and every denied attempt — is audited.
¹⁷ Candidates do not transition their own application status. Their only lifecycle action is withdrawal.
¹⁸ **Selectors evaluate; they do not decide.** No transition or stage-move capability, at any scope.
¹⁹ Candidate self-reopen is bounded by vacancy state and business rules, never by client assertion.
²⁰ **Withdrawal is the candidate's own act.** No one withdraws on a candidate's behalf — not a recruiter, not HR, not Super Admin.

### 4.7 External Apply

| Capability | PUBLIC | CANDIDATE | COMPANY_RECRUITER | COMPANY_ADMIN | CAREER_CENTER | HR_ADMIN | SELECTOR | AUDITOR | SUPER_ADMIN |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| **Start external apply** | **D** ²¹ | **O** | D | D | D | D | D | D | D |
| List own external-apply events | D | **O** | D | D | D | D | D | D | **A** |
| **Confirm external apply outcome** | D | **O** ²² | **C** ²² | **C** ²² | **D** | **D** | D | D | **A** |

²¹ Tracking requires a signed-in candidate (FR-EXT-002). An anonymous visitor may follow the external link after the FR-EXT-001 warning, but no event is recorded.
²² FR-EXT-003 permits three legitimate confirmation sources: the candidate who started the event, the owning company, or a lawful integration principal. **No two-way ATS integration is specified.**

Starting an external apply **creates no application row and sets no status to `APPLIED`** (INV-012), and no application row may exist for an `EXTERNAL_ATS` vacancy at all (INV-024).

### 4.8 Selector Assignment, Schedules, Evaluation, Offering, Outcome

| Capability | PUBLIC | CANDIDATE | COMPANY_RECRUITER | COMPANY_ADMIN | CAREER_CENTER | HR_ADMIN | SELECTOR | AUDITOR | SUPER_ADMIN |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| **Assign selector to stage** | D | D | **D** ²³ | **D** ²³ | **D** | **K** | **D** ²⁴ | D | **A** |
| **Revoke selector assignment** | D | D | **D** | **D** | **D** | **K** | **D** ²⁴ | D | **A** |
| List stage assignments | D | D | **D** | **D** | **D** | **K** | **S** ²⁵ | **R** | **A** |
| Create schedule | D | D | **C** | **C** | **D** | **K** | **D** | D | **A** |
| Reschedule · cancel · complete · no-show | D | D | **C** | **C** | **D** | **K** | **D** | D | **A** |
| List / read schedules | D | **O** | **C** | **C** | **D** | **K** | **S** | **R** | **A** |
| Read schedule history | D | **O** | **C** | **C** | **D** | **K** | **S** | **R** | **A** |
| **Create / update evaluation** | D | **D** ²⁶ | **C** | **C** | **D** | **K** | **S** | D | **A** |
| **Submit (finalize) evaluation** | D | D | **C** | **C** | **D** | **K** | **S** ²⁷ | D | **A** |
| Read evaluations | D | **D** ²⁶ | **C** | **C** | **D** | **K** | **S** | **R** | **A** |
| Create / send offering | D | D | **C** | **C** | **D** | **K** | **D** | D | **A** |
| **Accept offering** | D | **O** ²⁸ | **D** | **D** | **D** | **D** | D | D | **D** ²⁸ |
| **Reject offering** | D | **O** ²⁸ | **D** | **D** | **D** | **D** | D | D | **D** ²⁸ |
| Read offerings | D | **O** | **C** | **C** | **D** | **K** | D | **R** | **A** |
| Record / correct recruitment outcome | D | D | **C** | **C** | **A** ²⁹ | **K** | D | D | **A** |
| List outcomes · incomplete outcomes | D | D | **C** | **C** | **A** ²⁹ | **K** | D | **R** | **A** |

²³ Selector assignment is a campus-recruitment capability under FR-HR-006. It is not defined for company vacancies; if company-side selectors are later required, that is a change request.
²⁴ **A selector can never assign, extend, or revoke assignments — including their own.**
²⁵ A selector sees their own assignments, not the full roster for a stage.
²⁶ **Evaluations are never candidate-visible.** Internal results do not become candidate-facing status without an explicit transition (FR-HR-007).
²⁷ Only the evaluation's author may submit it, and only with an active assignment for that stage.
²⁸ **Responding to an offer is the candidate's own act.** No proxy acceptance exists — acceptance sets `offer_accepted_at`, the Time-to-Fill anchor, and must be the candidate's decision.
²⁹ Career Center records alumni outcomes within its reporting scope. This is outcome reporting, **not** candidate selection.

**Outcome incompleteness never blocks vacancy creation** (INV-014). No vacancy-creation capability anywhere in this matrix reads `recruitment_outcomes`.

### 4.9 Notifications, Reporting, Audit, Administration

| Capability | PUBLIC | CANDIDATE | COMPANY_RECRUITER | COMPANY_ADMIN | CAREER_CENTER | HR_ADMIN | SELECTOR | AUDITOR | SUPER_ADMIN |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| List own notifications · unread count · mark read ⁺ | D | **O** | **O** | **O** | **O** | **O** | **O** | **O** | **O** |
| Recruiter dashboard reports | D | D | **C** | **C** | **D** | D | D | **R** | **A** |
| Career Center reports | D | D | D | D | **A** | D | D | **R** | **A** |
| Campus reports | D | D | D | D | **D** | **K** | D | **R** | **A** |
| **Time-to-Fill report** | D | D | **C** | **C** | **A** ³⁰ | **K** | D | **R** | **A** |
| Request export | D | D | **C** | **C** | **A** ³⁰ | **K** | D | **R** ³¹ | **A** |
| **Read audit logs** | D | **D** | **D** | **D** | **D** | **D** | **D** | **R** | **A** |
| **Mutate audit logs** | **D** | **D** | **D** | **D** | **D** | **D** | **D** | **D** | **D** ³² |
| Read SMTP configuration metadata | D | D | D | D | D | D | D | **D** ³³ | **A** ³⁴ |
| Update SMTP configuration | D | D | D | D | D | D | D | D | **A** ³⁴ |
| Test SMTP configuration | D | D | D | D | D | D | D | D | **A** |
| List users · assign / revoke roles | D | D | D | D | D | D | D | **R** | **A** |
| Suspend / restore user account | D | D | D | D | D | D | D | D | **A** |
| Manage master data | D | D | D | D | **D** ³⁵ | **D** ³⁵ | D | **R** | **A** |

³⁰ Career Center's Time-to-Fill and export scope covers what FR-REP-002 defines; it is aggregate reporting, not applicant-level access.
³¹ Auditor may export within its permitted read scope. Every export is audited (FR-REP-005).
³² **No role can mutate the audit log — including Super Admin.** No create, update, or delete endpoint exists (INV-016). An audit trail that can be edited is not an audit trail.
⁺ **Surface: `INERTIA_WEB`** as of 1 September 2026 (`API_CONTRACT.md` Part X item 59 / SPEC-DOC-09). The three operations — `GET /notifications`, `POST /notifications/{notification}/read`, `POST /notifications/read-all` — moved from the reserved `VERSIONED_API` surface to the browser session-guard surface. **`O` (`OWN` by `notifications.user_id`) is unchanged on every row above**; company membership never widens it, and there is no global-reader, Super Admin, or Auditor override — every persona reads only its own rows.

³³ Auditor does **not** reach SMTP configuration. It is system configuration, not audit data.
³⁴ **The SMTP credential is never returned, to anyone, ever** (INV-035). Reads return `secret_configured: true` — a boolean conveying neither the value nor its length. Updates accept a new secret and replace the ciphertext wholesale; omitting it preserves the existing value. Audit records `credential_changed: true|false`, never a value.
³⁵ Master data is read by many roles to render forms; **writes are Super Admin only** (FSD §4.6).

---

## 5. Object-Level Restrictions in Detail

These constraints are not expressible in a matrix cell and must be enforced in code.

| # | Restriction | Enforcement |
| --- | --- | --- |
| **OL-1** | An **active** membership is required for every `COMPANY_SCOPE` capability. A revoked membership grants nothing, is re-checked per request, and is **never cached across requests** — a stale membership cache is a privilege-escalation bug | INV-017, INV-025 · ADR-006 |
| **OL-2** | An **active** stage assignment is required for every `ASSIGNED_STAGE` capability. Selector list endpoints join the assignment in the query; a post-filter is insufficient | INV-037 |
| **OL-3** | Application child objects — schedules, evaluations, screening answers, current stage — must belong to the **same vacancy** as the application. A foreign key cannot prove this; it is checked in the Action on **every** write path, including background jobs and administrative tooling | INV-019 |
| **OL-4** | Candidate documents are reachable **only** through a non-revoked `application_documents` row for that specific application, and the **snapshot** is served, not the candidate's current file | INV-010, INV-032 |
| **OL-5** | Target-audience eligibility reads **only** `candidate_verifications` with `status = VERIFIED`. Never the role code, never `current_candidate_type` | INV-028 |
| **OL-6** | The consent receiving party is **derived server-side** from the vacancy's ownership. A client-supplied receiver is rejected, never trusted | INV-023 |
| **OL-7** | Every list endpoint is **query-scoped**. Out-of-scope objects are absent from results and return `404` on direct read | FSD §3.3 |
| **OL-8** | Career Center's moderation reach over a company vacancy grants **no** access to that vacancy's applicants | FSD §3.3 |
| **OL-9** | Super Admin bypass is audited on **every** use, with a stated reason recommended for candidate document content | FR-AUD-001 |
| **OL-10** | Account status is re-checked **per request**. Suspension or disabling terminates sessions and revokes API tokens immediately, not at next login | FSD §8.1, §10.1 |
| **OL-11** | **Authorization is identical on both surfaces.** An `INERTIA_WEB` route enforces the same Policy and the same query scope as its `VERSIONED_API` equivalent. A capability denied here is denied on every surface — there is no "internal route" shortcut | ADR-001 |

---

## 6. Traceability

| Source | Covered by |
| --- | --- |
| FSD §3.1 — role catalogue | The nine columns. `PUBLIC` is a visitor state, not a stored assignment |
| FSD §3.2 — access matrix | §4.1 to §4.9 |
| FSD §3.3 — object-level authorization | §1 three-layer model, §5 OL-1 to OL-10 |
| FR-ONB-006 — vacancy creation gate | §4.5, footnote 4 |
| FR-COMP-003 — partnership independence | §4.4 |
| FR-HR-006 — selector assignment scope | §4.8, OL-2 |
| FR-CAN-005, FR-CONSENT-003 — document privacy | §4.2, §4.6, OL-4 |
| FR-AUD-001 — audit coverage | §4.9, OL-9 |
| FR-NOTIF-005 — SMTP administration | §4.9, footnote 34 |
| INV-002, 010, 011, 014, 016, 017, 019, 020, 022, 023, 024, 025, 028, 032, 035, 037 | Cited inline throughout |

---

## 7. Open Questions Affecting This Matrix

| # | Question | Effect |
| --- | --- | --- |
| **6** | ~~First recruiter default role, and whether at least one active Company Admin must exist~~ | **CLOSED by approved Product Owner decision:** first creator is active `COMPANY_ADMIN`; minimum one active `COMPANY_ADMIN` and last-admin protection are required; subsequent roles are explicit with no implicit default |
| ~~Career Center delegated posting~~ | ~~Whether Career Center may post vacancies "atas nama" a company~~ | **RESOLVED 24 August 2026 — DENY.** See §3 and footnote ⁵. Not one of the remaining business open questions |
| ~~Super Admin **company authoring**~~ | ~~Whether the global `SUPER_ADMIN` role by itself authorizes creating, editing, or screening-question management on a company vacancy~~ | **CLOSED 25 August 2026 — PO decision VA-4: NO.** Company authoring derives only from an ACTIVE `company_members` row; see §4.5 footnote ³⁶. Super Admin **read** capability is unchanged |
| ~~**Vacancy moderation authority**~~ | ~~Which actors may approve, request revision, reject, publish, close, suspend, and restore a company vacancy~~ | **CLOSED 25 August 2026 — PO decisions B-1 to B-5.** Moderators, conflict of interest, approve and restore targets, and the absence of a user-facing publish are settled in footnotes ¹² and ¹³ and in `API_CONTRACT.md` Part X item 11. Campus-vacancy authority is untouched |
| ~~**O-7 — automatic vacancy expiry**~~ | ~~Whether and how `PUBLISHED → EXPIRED` runs once `close_at` passes~~ | **CLOSED 25 August 2026.** `PUBLISHED` + `now >= close_at` → `EXPIRED`, `close_at` exclusive; audit `vacancy_expired` with no actor; no notification. **System-only — no role holds an expiry capability and no endpoint exists**, so this table gains no row (`API_CONTRACT.md` *Automatic vacancy expiry*) |
| ~~**PD-1 — public visibility after company suspension**~~ | ~~Whether a `PUBLISHED` company vacancy stays publicly visible once its owning company is no longer `VERIFIED`~~ | **CLOSED 26 August 2026.** Public visibility requires `company.verification_status = VERIFIED`, re-evaluated on every anonymous read, in addition to the existing `PUBLISHED` + date-window + non-`INTERNAL` conditions. **`PUBLIC` surface only — no authenticated role or capability is affected**, so this table gains no row; the predicate is enforced entirely in the public query layer, never by RBAC or object-ownership policy (`API_CONTRACT.md` *Public visibility and company verification (PD-1)*, Part X item 13) |
