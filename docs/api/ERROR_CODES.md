# API Error Codes — Portal Karir Kampus

**Status:** Proposed — awaiting approval
**Date:** 24 August 2026
**Baselines:** BRD v1.1 · FSD v1.1 · Logical model 1.1-C2 · Architecture ADR-001 to ADR-017
**Companion documents:** `API_CONTRACT.md` · `API_ENDPOINTS.md` · `INERTIA_ACTIONS.md` · `AUTHORIZATION_MATRIX.md`

**These codes apply identically on both surfaces.** A `VERSIONED_API` request and an `INERTIA_WEB` request that violate the same rule return the same `code`. The envelope differs only in transport; the vocabulary does not.

Error codes are the **machine-readable contract**. They are stable across releases and across translations; the human message is not. Clients switch on `error.code` and never on `error.message`.

---

## 1. Error Envelope

Every non-2xx response uses one shape:

```json
{
  "error": {
    "code": "APPLICATION_ALREADY_EXISTS",
    "message": "Anda sudah melamar lowongan ini.",
    "details": {
      "application_id": "…",
      "application_code": "APP-2026-000123"
    },
    "correlation_id": "01J9…"
  }
}
```

| Field | Rule |
| --- | --- |
| `code` | Required. From this catalogue only. Stable, SCREAMING_SNAKE_CASE, never localized |
| `message` | Required. User-safe, actionable, localized. **Never** the switching key |
| `details` | Optional, code-specific. For `VALIDATION_FAILED` it carries the field-error map. Never contains secrets, SQL, stack traces, or another user's data |
| `correlation_id` | Required. Also returned as the `X-Request-Id` header and stored in `audit_logs.correlation_id` (FSD §9.3) |

**Never returned in any error payload:** stack traces · SQL or query fragments · framework/class names · file paths · password hashes · raw or hashed tokens · SMTP credentials · API secrets · storage keys · another account's existence or data.

### Message principles (FSD §9.3)

Messages must not disclose whether another account exists, must be understandable to the role receiving them, must state a next action where one exists, and must carry the correlation ID for support. Technical detail stays in internal logs.

---

## 2. Relationship to FSD §9.2

FSD §9.2 provides a table headed **"Kode Kesalahan Fungsional Contoh"** — *example* functional error codes, not a frozen vocabulary. This catalogue adopts a consistent namespaced scheme and **maps every FSD example onto it**, so nothing in the FSD is lost or contradicted.

| FSD §9.2 code | Catalogue code | Note |
| --- | --- | --- |
| `EMAIL_ALREADY_REGISTERED` | `AUTH_EMAIL_ALREADY_REGISTERED` | Never returned on registration — see §4 note |
| `EMAIL_NOT_VERIFIED` | `AUTH_EMAIL_NOT_VERIFIED` | |
| `COMPANY_NOT_VERIFIED` | `COMPANY_NOT_VERIFIED` | Unchanged |
| `COMPANY_ASSOCIATION_FORBIDDEN` | `COMPANY_ASSOCIATION_FORBIDDEN` | Unchanged |
| `VACANCY_NOT_OPEN` | `VACANCY_NOT_OPEN` | Unchanged |
| `APPLICATION_ALREADY_EXISTS` | `APPLICATION_ALREADY_EXISTS` | Unchanged |
| `APPLICATION_REOPEN_NOT_ALLOWED` | `APPLICATION_NOT_REOPENABLE` | Renamed for scheme consistency; same meaning |
| `DOCUMENT_REQUIRED` | `APPLICATION_DOCUMENT_REQUIRED` | |
| `CONSENT_REQUIRED` | `APPLICATION_CONSENT_REQUIRED` | |
| `INVALID_STATUS_TRANSITION` | `APPLICATION_INVALID_TRANSITION` / `VACANCY_INVALID_TRANSITION` / `COMPANY_INVALID_TRANSITION` / `OFFER_INVALID_TRANSITION` | Split by aggregate so a client can respond specifically |
| `CAMPUS_EXTERNAL_APPLY_FORBIDDEN` | `VACANCY_EXTERNAL_ATS_NOT_ALLOWED_FOR_CAMPUS` | |
| `EMAIL_DELIVERY_PENDING` | `EMAIL_DELIVERY_PENDING` | Unchanged. **Not an error** — see §9 |

This mapping is a documented reading of an FSD table explicitly labelled as examples. It is **not** a change to FSD v1.1. If the business wants the FSD strings verbatim as the wire vocabulary, that is a change request against this document, not against the FSD.

---

## 3. Generic Codes

| Code | HTTP | Meaning |
| --- | --- | --- |
| `VALIDATION_FAILED` | 422 | Transport/shape validation failed. `details.fields` carries a field → messages map |
| `UNAUTHENTICATED` | 401 | No valid session or token |
| `AUTH_FORBIDDEN` | 403 | Authenticated, but the Policy denied this actor on this object |
| `NOT_FOUND` | 404 | Resource does not exist, **or exists but is outside the actor's scope** (see §10) |
| `CONFLICT` | 409 | Generic state or concurrency conflict where no specific code applies |
| `STALE_VERSION` | 409 | Optimistic concurrency: the client's `version`/`If-Match` no longer matches |
| `IDEMPOTENCY_KEY_REUSED` | 409 | Same `Idempotency-Key` replayed with a **different** payload |
| `IDEMPOTENT_REPLAY_IN_PROGRESS` | 409 | The original request for this key is still executing |
| `RATE_LIMITED` | 429 | Throttle exceeded. `Retry-After` header returned |
| `PAYLOAD_TOO_LARGE` | 413 | Upload exceeds the configured limit |
| `UNSUPPORTED_MEDIA_TYPE` | 415 | Content type not accepted for this endpoint |
| `MALFORMED_REQUEST` | 400 | Body is unparseable, or a path/query parameter is structurally invalid |
| `SERVER_ERROR` | 500 | Unhandled failure. `message` is generic; detail exists only in internal logs |
| `SERVICE_UNAVAILABLE` | 503 | A required dependency is unavailable |

`400` is used **only** for malformed requests. Business validation failures are `422`, and state conflicts are `409` — never `200`.

---

## 4. Authentication and Account

| Code | HTTP | Meaning |
| --- | --- | --- |
| `AUTH_INVALID_CREDENTIALS` | 422 | Login failed. Deliberately identical whether the account exists or the password is wrong |
| `AUTH_EMAIL_NOT_VERIFIED` | 403 | Action requires a verified email (FR-AUTH-003) |
| `AUTH_EMAIL_ALREADY_REGISTERED` | 422 | Reserved. **Not returned by registration** — see the note below |
| `AUTH_ACCOUNT_SUSPENDED` | 403 | `users.status = SUSPENDED` |
| `AUTH_ACCOUNT_DISABLED` | 403 | `users.status = DISABLED` |
| `AUTH_ACCOUNT_LOCKED` | 429 | Temporary lock after repeated failures (FSD §10.1). `Retry-After` returned |
| `AUTH_TOKEN_INVALID` | 422 | Verification or reset token does not match a stored hash |
| `AUTH_TOKEN_EXPIRED` | 422 | Token past `expires_at` |
| `AUTH_TOKEN_ALREADY_USED` | 422 | Token has `used_at` set (INV-021) |
| `AUTH_TOKEN_REVOKED` | 422 | Token has `revoked_at` set |
| `AUTH_PASSWORD_POLICY` | 422 | Password fails FR-AUTH-006 policy |
| `AUTH_CURRENT_PASSWORD_INVALID` | 422 | Password change supplied the wrong current password |

> **Account-enumeration note.** `AUTH_EMAIL_ALREADY_REGISTERED` is defined because FSD §9.2 names it, but **registration, forgot-password, and resend-verification never return it**. All three return `202` with an identical body whether or not the address exists, and the differentiated outcome is delivered by email. Returning it would let an attacker enumerate registered candidates and recruiters, which FSD §9.3 forbids. It remains available for authenticated self-service flows where the actor already knows the address is theirs.

---

## 5. Candidate, Documents, and Consent

| Code | HTTP | Meaning |
| --- | --- | --- |
| `CANDIDATE_PROFILE_REQUIRED` | 422 | Action needs a candidate profile that does not exist |
| `CANDIDATE_PROFILE_INCOMPLETE` | 422 | Required profile fields missing before apply (FR-APP-001) |
| `CANDIDATE_NOT_ELIGIBLE` | 403 | Target-audience eligibility failed. Evaluated **only** against `candidate_verifications` (INV-028) |
| `CANDIDATE_VERIFICATION_PENDING` | 409 | A verification of this type is already PENDING |
| `DOCUMENT_NOT_FOUND` | 404 | Document does not exist or is not the actor's |
| `DOCUMENT_NOT_OWNED` | 403 | Document belongs to another candidate |
| `DOCUMENT_NOT_SHARED` | 403 | Document exists but was not shared with this application (INV-010, INV-032) |
| `DOCUMENT_ARCHIVED` | 422 | Document is archived and cannot be shared with a new application |
| `DOCUMENT_TYPE_NOT_ALLOWED` | 422 | Extension or inspected MIME type not permitted |
| `DOCUMENT_SCAN_PENDING` | 409 | Upload is quarantined awaiting scan and cannot yet be used |
| `DOCUMENT_SCAN_FAILED` | 422 | Scanner rejected the file |
| `DOCUMENT_IN_USE` | 409 | Cannot hard-delete a document referenced by a live share (INV-032) |
| `APPLICATION_CONSENT_REQUIRED` | 422 | No valid explicit consent supplied (INV-011, FR-CONSENT-001) |
| `CONSENT_VERSION_UNKNOWN` | 422 | Supplied consent version is not a known published version |
| `CONSENT_RECEIVER_MISMATCH` | 422 | Client-supplied receiver disagrees with the vacancy's owner (INV-023). The server derives the receiver; this code exists to reject a client that tries to assert one |

---

## 6. Company, Verification, and Partnership

| Code | HTTP | Meaning |
| --- | --- | --- |
| `COMPANY_NOT_VERIFIED` | 403 | Company is not VERIFIED and the action requires it (INV-002, FR-ONB-006) |
| `COMPANY_ASSOCIATION_FORBIDDEN` | 403 | Actor holds no active membership of this company (INV-017) |
| `COMPANY_INVALID_TRANSITION` | 409 | Requested verification action is not legal from the current status (FSD §8.2) |
| `COMPANY_ALREADY_EXISTS_FOR_USER` | 409 | Recruiter already owns a company profile |
| `COMPANY_PROFILE_INCOMPLETE` | 422 | FR-ONB-002 required fields missing at submit (INV-030). `details.missing` lists them |
| `COMPANY_DOCUMENT_REQUIRED` | 422 | At least one legal document is required before submitting (INV-030) |
| `COMPANY_REVISION_REQUIRED` | 409 | Company is in REVISION_REQUIRED and must be corrected and resubmitted |
| `COMPANY_SUSPENDED` | 403 | Company access is suspended |
| `REVIEW_REASON_REQUIRED` | 422 | REQUEST_REVISION / REJECT / SUSPEND requires reason category and recruiter-visible note (INV-029) |
| `MEMBER_ALREADY_ACTIVE` | 409 | An active membership already exists for this user and company |
| `COMPANY_DOCUMENT_IS_VERIFICATION_EVIDENCE` | 409 | Destructive deletion refused: the document has formed part of a submitted verification package and is now evidence. The response names `POST …/supersede` as the correct action |
| `COMPANY_VACANCY_AUTHORING_FORBIDDEN` | 403 | Career Center attempted to create or edit a company vacancy. Authoring belongs to active verified company members; Career Center moderates. Delegated posting, if introduced, must be a separate explicitly authorized capability |
| `MEMBER_LAST_ADMIN` | 409 | **PENDING BUSINESS DECISION** (open question 6). Reserved for a minimum-active-Company-Admin rule. Not enforced until decided |
| `PARTNERSHIP_INVALID_TRANSITION` | 409 | Partnership activate/end not legal from the current status |
| `PARTNERSHIP_OVERLAPPING_PERIOD` | 409 | An active partnership already covers this period for this company |

> Partnership codes never gate vacancy creation. A VERIFIED company with no partnership creates vacancies normally (INV-003, INV-020, FR-COMP-003).

---

## 7. Vacancy

| Code | HTTP | Meaning |
| --- | --- | --- |
| `VACANCY_INVALID_TRANSITION` | 409 | Action not legal from the current status (FSD §8.3, §8.4). `details.from` / `details.attempted` |
| `VACANCY_COMPANY_NOT_VERIFIED` | 403 | Company vacancy creation blocked by the verification gate (INV-002) |
| `VACANCY_NOT_OPEN` | 409 | Not PUBLISHED, or outside `open_at`…`close_at` |
| `VACANCY_NOT_PUBLIC` | 404 | Requested through a public endpoint but not publicly visible (see §10) |
| `VACANCY_EXTERNAL_ATS_NOT_ALLOWED_FOR_CAMPUS` | 422 | `CAMPUS_EMPLOYMENT` must be `IN_PORTAL` (INV-005, FR-HR-003) |
| `VACANCY_EXTERNAL_ATS_URL_REQUIRED` | 422 | `application_method = EXTERNAL_ATS` requires a valid URL |
| `VACANCY_EXTERNAL_ATS_URL_INVALID` | 422 | URL scheme not permitted. **`https` only** (FSD §9.1.5) |
| `VACANCY_OWNERSHIP_INVALID` | 422 | Ownership XOR violated: company vacancy without company, campus vacancy without unit, or both present (INV-018) |
| `VACANCY_TARGET_AUDIENCE_INVALID` | 422 | Value outside the four permitted audiences (INV-006) |
| `VACANCY_CLOSE_BEFORE_OPEN` | 422 | `close_at` must follow `open_at` (FSD §9.1.4) |
| `VACANCY_DATES_REQUIRED` | 422 | `open_at` / `close_at` required before leaving DRAFT |
| `VACANCY_NOT_EDITABLE` | 409 | Current status forbids editing |
| `VACANCY_HAS_APPLICATIONS` | 409 | Change would invalidate existing applications — e.g. switching `IN_PORTAL` → `EXTERNAL_ATS` while applications exist (INV-024) |
| `VACANCY_MODERATION_NOT_APPLICABLE` | 409 | Moderation attempted on a campus vacancy. Campus vacancies are not moderated (INV-018) |
| `SCREENING_QUESTION_IN_USE` | 409 | Question already has answers; deactivate instead of deleting. **There is no `DELETE` route** — deactivation via `PATCH active=false` is the single mechanism for every case |
| `STAGE_IN_USE` | 409 | Stage is referenced by live applications, schedules, or evaluations |
| `STAGE_NOT_IN_VACANCY` | 422 | Referenced stage belongs to a different vacancy (INV-019) |

---

## 8. Application, External Apply, Selection, and Offering

| Code | HTTP | Meaning |
| --- | --- | --- |
| `APPLICATION_ALREADY_EXISTS` | 409 | A lifecycle already exists for this candidate and vacancy (INV-007). `details.application_id` is returned so the client can route to *Lihat Status Lamaran* (FR-APP-002) |
| `APPLICATION_NOT_IN_PORTAL_VACANCY` | 422 | Vacancy uses `EXTERNAL_ATS`; an application row may not exist for it (**INV-024**) |
| `APPLICATION_INVALID_TRANSITION` | 409 | Status change not legal from the current state (FSD §8.5) |
| `APPLICATION_TERMINAL` | 409 | Application is in a terminal state and the action is not permitted |
| `APPLICATION_NOT_REOPENABLE` | 409 | Reopen conditions not met (FR-APP-003) |
| `APPLICATION_DOCUMENT_REQUIRED` | 422 | A required document type was not selected |
| `APPLICATION_SCREENING_INCOMPLETE` | 422 | A required screening question is unanswered |
| `APPLICATION_SCREENING_INVALID` | 422 | Answer does not match its question definition — wrong type, or a choice outside the defined options |
| `APPLICATION_ALREADY_WITHDRAWN` | 409 | Already WITHDRAWN |
| `EXTERNAL_APPLY_INVALID_METHOD` | 422 | Vacancy is `IN_PORTAL`; external-apply start does not apply |
| `EXTERNAL_APPLY_EVENT_ALREADY_CONFIRMED` | 409 | Event already confirmed |
| `EXTERNAL_APPLY_CONFIRMATION_FORBIDDEN` | 403 | Actor is not a legitimate confirmation source (FR-EXT-003) |
| `SELECTOR_NOT_ASSIGNED_TO_STAGE` | 403 | No active `selection_stage_assignments` row for this selector and stage (INV-037) |
| `SELECTOR_ROLE_REQUIRED` | 422 | Target user does not hold an active SELECTOR role and cannot be assigned |
| `SELECTOR_ASSIGNMENT_ALREADY_ACTIVE` | 409 | An active assignment already exists for this selector and stage (INV-037) |
| `SCHEDULE_INVALID_TRANSITION` | 409 | Schedule action not legal from the current status (INV-027) |
| `SCHEDULE_TIME_INVALID` | 422 | `ends_at` before `starts_at`, or a start time in the past where forbidden |
| `SCHEDULE_METHOD_DETAIL_REQUIRED` | 422 | On-site requires a location; online requires a meeting URL |
| `EVALUATION_ALREADY_SUBMITTED` | 409 | Evaluation is finalized and no longer editable |
| `EVALUATION_NOT_OWNED` | 403 | Evaluation belongs to a different evaluator |
| `OFFER_INVALID_TRANSITION` | 409 | Offer action not legal from the current status |
| `OFFER_ALREADY_RESPONDED` | 409 | Offer is already ACCEPTED or REJECTED |
| `OFFER_EXPIRED` | 409 | Past `response_deadline`, or status EXPIRED |
| `OFFER_ALREADY_ACCEPTED_FOR_APPLICATION` | 409 | Another offer on this application is already ACCEPTED (INV-031) |
| `OFFER_NOT_SENT` | 409 | A DRAFT offer cannot be accepted or rejected |
| `OUTCOME_SOURCE_INVALID` | 422 | Neither or both of `application_id` / `external_apply_event_id` supplied (**INV-022**) |
| `OUTCOME_ALREADY_RECORDED` | 409 | An outcome already exists for this source |

---

## 9. Non-Error Signals

| Code | HTTP | Meaning |
| --- | --- | --- |
| `EMAIL_DELIVERY_PENDING` | **200 / 201** | **Not an error.** The business transaction committed; email delivery is queued (INV-015, FR-NOTIF-001). Returned in `meta.warnings[]` on a success response, never in the `error` envelope |

An email failure never turns a committed business action into an HTTP error. If it did, the client would retry an operation that already succeeded.

---

## 10. Not-Found versus Forbidden

The rule, applied consistently:

| Situation | Response |
| --- | --- |
| Actor may know the object exists (they can see it in some listing) but may not perform this action | `403 AUTH_FORBIDDEN` |
| Actor may **not** know the object exists — another company's vacancy, another candidate's application, an unpublished vacancy on a public route | `404 NOT_FOUND` |

Returning `403` for an object outside the actor's scope confirms its existence and leaks a company's applicant volume or a candidate's activity by probing IDs. Scoped queries return `404` for anything outside scope.

---

## 11. Reserved — Pending Business Decisions

These codes exist so contracts can reference them, but **no endpoint enforces them** until the corresponding question is decided. Nothing here is silently resolved.

| Code | Blocked on |
| --- | --- |
| `MEMBER_LAST_ADMIN` | Open question 6 — first recruiter default role and minimum active Company Admin |
| `SALARY_REQUIRED` | Open question 3 — whether salary is mandatory, optional, or hidden per vacancy type |
| `COMPANY_DOCUMENT_TYPE_REQUIRED` | Open question 2 — minimum legal documents per organization type |
| `VERIFICATION_SOURCE_UNAVAILABLE` | Open question 1 — alumni verification integration source |

---

## 12. Stability Rules

1. A code's **meaning never changes**. Narrowing or widening it requires a new code.
2. Codes are **never removed** within a major API version. Retired codes are marked deprecated and remain documented.
3. New codes may be added in a minor release; clients must treat an unknown code as a generic failure of its HTTP class.
4. `message` text may change freely — translation, tone, clarity. It is not part of the contract.
5. A new code requires a matching entry in this catalogue and in the affected endpoint's **Error Codes** section in `API_CONTRACT.md`.
