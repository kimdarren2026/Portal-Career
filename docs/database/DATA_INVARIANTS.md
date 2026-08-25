# Data Invariants — Portal Karir Kampus

**Status:** Logical data rules only. Physical constraints, transactions, authorization checks, and retention workflows will be finalized after a database technology and architecture are chosen.
**Revision:** 1.1-C3 — controlled logical alignment applied 24 August 2026. Supersedes 1.1-C2.

INV-001 to INV-021 existed before correction; several were amended. INV-022 to INV-034 were added by the 1.1-C1 correction pass. INV-035 to INV-037 were added by revision 1.1-C2 for runtime SMTP configuration (resolving ADR-010) and selector stage assignment (resolving H-1). **INV-038 was added by revision 1.1-C3** for company verification-evidence retention, aligning the logical model with the already-approved API contract. No invariant here selects a database vendor, physical constraint mechanism, index type, or framework feature.

## Non-Negotiable Rules

### INV-001 — Unique Email

`users.email_normalized` is the **single authoritative uniqueness key** for account identity. Email identity comparison is case-insensitive.

`users.email` is the original/display representation and carries **no** uniqueness rule. There must never be a competing uniqueness constraint on `users.email`; two rows whose display forms differ only by case are prevented by the normalized key alone, and normalization must occur before any uniqueness validation.

### INV-002 — Company Verification Gate

A company vacancy cannot be created unless `companies.verification_status` is VERIFIED. This requires service/application validation in addition to any feasible database constraint.

### INV-003 — Verification Is Not Partnership

A VERIFIED company may have no active partnership and may still create vacancies. Mitra Kampus is derived from an ACTIVE partnerships record; it is not a replacement company status or posting gate.

### INV-004 — No SUBMITTED Vacancy Status

SUBMITTED and DIAJUKAN must never be stored as `vacancies.current_status`. Submit is an action that transitions a company vacancy from DRAFT or REVISION_REQUIRED to PENDING_REVIEW.

### INV-005 — Campus Apply Method

For CAMPUS_EMPLOYMENT, `vacancies.application_method` must be IN_PORTAL. EXTERNAL_ATS is forbidden.

### INV-006 — Target Audience

`vacancies.target_audience` is limited to PUBLIC, ALUMNI_ONLY, FINAL_YEAR_AND_ALUMNI, and INTERNAL.

### INV-007 — One Application Lifecycle

UNIQUE(`candidate_profile_id`, `vacancy_id`) is the logical business key for applications. It covers the **full** lifecycle, not merely active applications, and is an unconditional uniqueness rule rather than a filtered or partial one.

FSD §6.5's phrase "satu lifecycle **aktif**" is read against FR-APP-002 and FR-APP-003.4, which state the rule without the qualifier and forbid a second application outright. Decision D-1 in `ERD.md` records this reading. A change to the looser interpretation would require an FSD change request.

### INV-008 — Reopen Preserves History

An authorized reapply reopens the existing application, updates reopen metadata, and appends an `APPLICATION_REOPENED` history event. It never creates application number two for the same candidate and vacancy.

### INV-009 — Withdrawal Does Not Delete

Withdrawal changes the application state to WITHDRAWN, records `withdrawn_at` and any reason, and appends history. It does not delete the application, its history, consent, shared-document records, or offer history.

`withdrawal_reason` remains optional in all cases: FR-APP-006 states the reason is "alasan opsional". It is deliberately **not** included in the conditional-reason rule of INV-029.

### INV-010 — Candidate Document Privacy

`candidate_documents` are private. A vacancy owner may receive document access only through an active `application_documents` sharing relationship for an application within an object they are authorized to manage. Reaching a `candidate_profiles` record grants no document access of any kind, and no candidate document is ever publicly visible.

### INV-011 — Consent Required

An IN_PORTAL application cannot be finalized without valid explicit consent. The consent record identifies the purpose, version/text reference, receiving company or organizational unit, vacancy, and application where applicable. A Boolean on applications alone is insufficient and must never be introduced as a substitute.

### INV-012 — External Apply Is Not Applied

`EXTERNAL_APPLY_STARTED` is an `external_apply_events` event and must not automatically create an applications record with APPLIED status. Later confirmation is separately recorded from an authorized candidate, company, or legitimate integration source.

See INV-024 for the inverse constraint.

### INV-013 — Time-to-Fill

Time-to-Fill equals `offers.offer_accepted_at` minus `vacancies.published_at`. It does not use onboarding, start-work, contract-signing, first-working-day, or any other date, and no such field exists in the model.

Where a vacancy has more than one accepted offer — permitted when `openings_count` exceeds one — the vacancy-level metric uses the **earliest** `offer_accepted_at` among that vacancy's accepted offers. Time-to-Fill is undefined while `vacancies.published_at` is null and must be reported as unavailable rather than substituted with another date. See INV-031.

### INV-014 — Outcome Reminder Only

Missing recruitment outcome may generate a notification/reminder but must not block creation of a new vacancy. No vacancy-creation gate anywhere in this model may reference `recruitment_outcomes`.

### INV-015 — SMTP Failure Isolation

The business transaction remains committed when email delivery fails. `email_outbox` holds delivery state and retry/dead-letter metadata; it contains no SMTP secrets. The outbox row is written inside the business transaction and delivery is attempted only after that transaction commits.

### INV-016 — Append-Only History

`company_verification_reviews`, `vacancy_moderation_reviews`, `application_status_histories`, `selection_schedule_histories`, `vacancy_versions`, and `audit_logs` are logical append-only records. Correction is represented by a later record, not destructive overwrite.

### INV-017 — Object Ownership

An active company member can access only company-owned recruitment objects for that company and role. Admin Kepegawaian manages campus recruitment only under authorized HR roles. Candidates can access only their own profile, documents, applications, and permitted events.

### INV-018 — Vacancy Ownership Exclusivity

`vacancies.ownership_type` determines exactly one owner, and ambiguous ownership is forbidden.

| `ownership_type` | Required | Forbidden |
| --- | --- | --- |
| COMPANY | `company_id` present; creator authorized as an active member of that company | `organizational_unit_id` absent |
| CAMPUS | `organizational_unit_id` present — FR-HR-002 lists unit/fakultas/bagian among the minimum campus vacancy fields | `company_id` absent |

Exactly one of `company_id` and `organizational_unit_id` is populated on every vacancy row; never both, never neither.

A campus vacancy may never hold a moderation-only status. `PENDING_REVIEW`, `REVISION_REQUIRED`, `APPROVED`, and `REJECTED` apply to company vacancies only, because Career Center moderation applies to company vacancies only. The ownership type and application method must also be mutually consistent per INV-005.

### INV-019 — Application Child Consistency

`applications.current_stage_id`, `application_screening_answers.screening_question_id`, `selection_schedules.recruitment_stage_id`, and `evaluations.recruitment_stage_id` must belong to the same vacancy as the application.

A foreign key can prove the referenced row exists but not that it belongs to the correct vacancy. This rule requires validation at the service/model boundary so that every write path is covered, not validation at a single request entry point.

### INV-020 — Active Partnership Derivation

Mitra Kampus display/eligibility metadata is derived only when a partnerships record is ACTIVE and within its applicable period. It must not be persisted as an alternative company verification state.

### INV-021 — Password Reset Token Safety

`password_reset_tokens` is one-time and expiring. On a successful reset, the consumed token is recorded as used and older valid reset tokens for that user are revoked. Raw reset tokens must not be retained in the logical data model.

---

## Exclusivity and Integrity Rules Added by Correction

### INV-022 — Outcome Source Exclusivity

`recruitment_outcomes.source_type` selects the single valid source reference:

| `source_type` | Required | Forbidden |
| --- | --- | --- |
| INTERNAL_APPLICATION | `application_id` present | `external_apply_event_id` absent |
| EXTERNAL_APPLY | `external_apply_event_id` present | `application_id` absent |

Exactly one valid source reference must exist on every outcome row. Both-present and both-absent are invalid states.

`recruitment_outcomes` stores **no** `vacancy_id` and **no** `candidate_profile_id`. The vacancy and candidate of an outcome are whatever the populated source reference resolves to, and there is no second stored copy that could disagree with it. Reporting joins through the source reference.

An outcome whose `source_type` is EXTERNAL_APPLY may be recorded regardless of the referenced event's confirmation state; recording an outcome does not itself confirm an external application, and neither implies any two-way ATS integration exists.

### INV-023 — Consent Receiving Party Exclusivity

For recruitment data-sharing consent, exactly one receiving party is identifiable, determined by the vacancy's `ownership_type`:

| Vacancy ownership | Required | Forbidden |
| --- | --- | --- |
| COMPANY | `receiving_company_id` present, equal to the vacancy's `company_id` | `receiving_organizational_unit_id` absent |
| CAMPUS | `receiving_organizational_unit_id` present, equal to the vacancy's `organizational_unit_id` | `receiving_company_id` absent |

**Neither-present is invalid** for recruitment data-sharing consent — FR-CONSENT-001 requires the consent to identify the receiving party, so a consent record naming no recipient does not satisfy the requirement. **Both-present is invalid.**

Where a consent type does not concern data sharing with a recruiting party, at most one receiving party may be present and both may be absent.

### INV-024 — In-Portal Application Only

An `applications` row may exist only when its vacancy has `application_method = IN_PORTAL`.

| Vacancy `application_method` | Applications | External apply events |
| --- | --- | --- |
| IN_PORTAL | Permitted, subject to eligibility, screening, and consent | Not the tracking path for this vacancy |
| EXTERNAL_ATS | **Forbidden — must not create an applications row** | The only tracking path: `EXTERNAL_APPLY_STARTED` and its confirmation metadata |

This is the inverse of INV-012 and is equally non-negotiable. INV-012 prevents an external click from becoming an application; INV-024 prevents an application from existing for an external vacancy at all. Together they keep FR-EXT-004's required reporting separation between "external apply started" and "application" reliable.

A vacancy's `application_method` must not be changed from IN_PORTAL to EXTERNAL_ATS while applications exist against it.

### INV-025 — Role Assignment History

`user_roles` is keyed by a surrogate identifier `user_roles.id`. `(user_id, role_id)` is **not** a primary key and must not be used as one, because revoked assignments remain stored and the same role may later be re-assigned to the same user.

For a given `user_id` + `role_id`:

- there may be **many** historical assignments where `revoked_at` is present;
- there may be **at most one** active assignment where `revoked_at` is absent.

Revocation sets `revoked_at` and `revoked_by`; it never deletes the row and never overwrites `assigned_at` or `assigned_by`. The rule is stated logically; no partial index, filtered index, or other vendor-specific mechanism is selected here, and the architecture phase must choose an enforcement approach that survives the database decision.

This rule is what allows a `FINAL_YEAR_STUDENT` to become an `ALUMNI` without losing role history.

### INV-026 — Derived Application Fields

`applications.current_status`, `applications.current_stage_id`, `applications.reopen_count`, and `applications.last_reopened_at` are **derived caches**. `application_status_histories` is authoritative.

- Every change to a cached field must append a corresponding history event in the same transaction.
- `reopen_count` equals the count of `APPLICATION_REOPENED` events for that application.
- `last_reopened_at` equals the `occurred_at` of the most recent `APPLICATION_REOPENED` event.
- `current_status` and `current_stage_id` equal the `to_status` and `to_stage_id` of the most recent event that set them.

A cached field may never be updated without its history event, and a history event may never be appended without updating the cache it affects. Where the two disagree, history is correct and the cache is defective.

### INV-027 — Selection Schedule Reschedule Representation

`selection_schedules.status` is limited to SCHEDULED, COMPLETED, CANCELLED, and NO_SHOW. `RESCHEDULED` must **not** be stored as a current status: a rescheduled appointment is still SCHEDULED at its new time.

A reschedule:

1. updates the schedule's own values (`starts_at`, `ends_at`, `method`, `location`, `meeting_url`, and related fields);
2. increments `selection_schedules.revision_number`;
3. appends a `selection_schedule_histories` record with `event_type = RESCHEDULED`, the prior values in `previous_snapshot`, the actor, and a reason;
4. leaves `status` as SCHEDULED.

History is preserved by the history entity, not by a status value. `RESCHEDULED` remains valid as a `selection_schedule_histories.event_type`.

### INV-028 — Candidate Type, Role, and Eligibility Separation

Three concerns, three authorities, never conflated:

| Concern | Authority | Rule |
| --- | --- | --- |
| **A. Authorization** | `roles` + `user_roles` | Governs which screens and actions a user may reach. Candidate role codes must be kept synchronized with `current_candidate_type` by application logic and are never an independent second opinion. |
| **B. Candidate identity category** | `candidate_profiles.current_candidate_type` | The candidate's self-declared current category. It grants no access and proves nothing. |
| **C. Verified eligibility** | `candidate_verifications` with matching `verification_type` and `status = VERIFIED` | The **only** admissible basis for target-audience gating. |

Eligibility for `ALUMNI_ONLY` and `FINAL_YEAR_AND_ALUMNI` must be evaluated against C. It must never be inferred from A or B. **Holding the role `CANDIDATE_ALUMNI` is not proof of alumni verification.**

A candidate transitioning from final-year student to alumnus retains the same `users` row, the same `candidate_profiles` row, and the complete application history; changes `current_candidate_type`; may gain a corresponding authorization role through application logic per INV-025; and requires a new VERIFIED `candidate_verifications` record of type ALUMNI before verified-alumni eligibility applies.

### INV-029 — Mandatory Review Reason

A review reason is conditionally required, not globally required. The field remains nullable across the entity as a whole and is mandatory only for the actions the FSD names.

| Entity | Field | Required when |
| --- | --- | --- |
| `company_verification_reviews` | `reason_category` and `recruiter_visible_note` | `action` is REQUEST_REVISION, REJECT, or SUSPEND — FR-ONB-004: "Revision/rejection/suspension wajib memiliki reason." |
| `vacancy_moderation_reviews` | `reason_category` and `recruiter_visible_note` | `action` is REQUEST_REVISION, REJECT, or SUSPEND — FR-VAC-006: "Revision/rejection harus memiliki kategori dan recruiter-visible note." |

Only the **vocabulary** of reason categories remains an open policy matter. The **presence** of a reason is required by FSD v1.1 today and is no longer classified as a pending decision. `internal_note` stays optional in all cases and is never recruiter-visible.

### INV-030 — Company Verification Submission Completeness

The FR-ONB-002 fields marked Wajib are conditionally required: nothing is mandatory while the company is DRAFT, and all become mandatory at Submit for Verification.

`companies.organization_type_id`, `companies.industry_id`, `companies.official_email`, `companies.address`, `companies.province_geographic_area_id`, `companies.city_geographic_area_id`, and at least one `company_documents` record must be present before `verification_status` may transition from DRAFT or REVISION_REQUIRED to PENDING_VERIFICATION.

Which legal document types satisfy the last condition remains an open question and is not encoded here.

### INV-031 — Single Accepted Offer

At most one `offers` row per application may hold `status = ACCEPTED`. An application cannot simultaneously have two accepted offers, and `applications.hired_at` corresponds to that single accepted offer.

A vacancy may hold several accepted offers across different applications when `openings_count` exceeds one. Vacancy-level Time-to-Fill then uses the earliest `offer_accepted_at` among them, per INV-013.

### INV-032 — Shared Document Snapshot Integrity

`application_documents` must preserve enough evidence of exactly what was shared at application time that later changes to the candidate's private source document cannot silently alter historical recruitment evidence.

- `snapshot_name` and `snapshot_storage_reference` are **required** at share time. `snapshot_checksum` is optional but recommended.
- A `candidate_documents` row referenced by a non-revoked `application_documents` row must not be hard-deleted. It may only be archived or anonymized by an authorized retention process, which must leave the sharing record and its snapshot intact.
- Revocation via `revoked_at` is an authorized action that withdraws future access. It never deletes the sharing record, never removes the snapshot, and never retroactively invalidates a completed evaluation that relied on the document.
- No snapshot ever makes a document publicly visible. INV-010 continues to govern access.

Candidate-initiated revocation of an already-shared document is not defined by FSD v1.1 and is recorded as human-decision item H-3 in `ERD.md`.

### INV-033 — Stable Polymorphic Type Names

`notifications.related_object_type`, `email_outbox.related_object_type`, and `audit_logs.object_type` carry a stable **logical** entity name, never an implementation class path or file name.

These references carry no referential integrity by design, which is acceptable for notification and audit tails. Because audit history must remain readable indefinitely, refactoring application code must never change a stored type value; a logical name that is retired must remain interpretable.

### INV-035 — SMTP Credential Confidentiality

`smtp_configurations.encrypted_password` is the only credential in the logical model, and it is governed absolutely.

| Rule | Requirement |
| --- | --- |
| **Encrypted before persistence** | The application encrypts the value before it reaches the database. A plaintext credential is never written, not even transiently. |
| **Key held outside the database** | The encryption key is sourced from deployment secret configuration. A key stored alongside the ciphertext it protects provides no protection — an attacker with database access would hold both. |
| **Never returned after save** | No read operation, API response, export, view model, or serializer may return the credential. It is write-only from the moment it is stored. |
| **Masked in the interface** | Administrative screens show only whether a credential is set, never its value or length. |
| **Replaced, never merged** | Changing the credential replaces the stored ciphertext outright. There is no partial update, and no previous value is retained. |
| **Audited as an event, never as a value** | `audit_logs` records that the SMTP credential changed, by whom, and when. The old and new values never appear in `change_summary` or anywhere else in the audit payload. |
| **Never in `email_outbox`** | No outbox row may hold, reference, or embed an SMTP credential. INV-015 already forbids SMTP secrets in the outbox and remains in force. |
| **Never in logs** | The credential must not appear in application logs, exception traces, queue payloads, delivery-failure summaries, or `last_test_result` detail. |

A test-send action may use the stored credential; it must not echo it back in any result.

### INV-036 — Single Active SMTP Configuration

At most one `smtp_configurations` row may have `is_active` set. Zero active rows is a valid state, meaning no runtime configuration has been established and delivery falls back to deployment configuration.

Superseded configurations are retained as history, which lets a Super Admin see what changed and when. The stored credential of a superseded row remains subject to INV-035 in full; a retention process may clear the ciphertext of inactive rows without deleting the row.

This is a **conditional uniqueness** rule of the same shape as INV-017, INV-025, INV-031, and INV-037. No enforcement mechanism is selected here.

### INV-037 — Selector Stage Assignment

`selection_stage_assignments` is keyed by a surrogate identifier. `(recruitment_stage_id, selector_user_id)` is **not** a primary key, because revoked assignments remain stored and the same selector may later be re-assigned to the same stage.

For a given `recruitment_stage_id` + `selector_user_id`:

- there may be **many** historical assignments where `revoked_at` is present;
- there may be **at most one** active assignment where `revoked_at` is absent.

Revocation sets `revoked_at` and `revoked_by_user_id`; it never deletes the row and never overwrites `assigned_at` or `assigned_by_user_id`.

**Scope rules:**

| Rule | Requirement |
| --- | --- |
| Role is a precondition, not a grant | `selector_user_id` must hold an active SELECTOR role assignment (INV-025). Holding the role authorizes *being assigned*; it grants no access by itself. |
| Access requires an active assignment | Selector visibility and evaluation authority derive **only** from a row with `revoked_at` absent. A revoked assignment grants nothing. |
| Scope is inherited from the stage | An assignment reaches only the applications, schedules, and evaluations of the stage it names. It never extends to another stage of the same vacancy, and never to another vacancy. |
| Assignment is not delegation | A selector cannot assign, revoke, or extend assignments. Only an authorized Admin Kepegawaian may, per FR-HR-006. |
| Assignment is not evaluation authorship | `evaluations.evaluator_user_id` records who evaluated; the assignment records who was permitted to. Neither substitutes for the other. |

This mirrors INV-028's separation principle: role, assignment, and authorship are three different facts and must not be conflated.


### INV-038 — Company Verification Evidence Retention

**Submitted company verification evidence is immutable with respect to destructive deletion.**

Once `company_documents.first_submitted_at IS NOT NULL`, that document record has formed part of a submitted company-verification package and **must be preserved**. Replacement occurs only by linking a successor document through the supersede relationship — never by deleting, overwriting, or editing the original row.

**Why this is non-negotiable:** a Career Center verification decision is made against a specific set of documents. If that evidence could be removed afterwards, the append-only `company_verification_reviews` trail would reference a decision whose basis no longer exists, defeating the reconstructability INV-016 guarantees.

#### Document states

| State | Test | Permitted |
| --- | --- | --- |
| **Never submitted** | `first_submitted_at IS NULL` | May be removed or replaced outright under the allowed draft-edit rules, while the company is still assembling its data |
| **Submitted evidence** | `first_submitted_at IS NOT NULL` | **Destructive deletion is forbidden.** Replacement is by supersede only |

`first_submitted_at` is set the first time the document is included in a submitted verification package and is **permanent once set**. It is never cleared — not on revision, not when the company returns to DRAFT, not on rejection. The fact that a reviewer once saw this document does not become untrue.

#### Supersede rules

| # | Rule |
| --- | --- |
| **A** | Replacement **creates or uses another `company_documents` record**. The predecessor is never edited into the successor |
| **B** | On the predecessor: `superseded_at` is set, and `superseded_by_document_id` references the successor |
| **C** | `superseded_by_document_id` is **required when `superseded_at` is present** and absent otherwise — the two move together |
| **D** | **Same company.** `superseded_by_document_id` must reference a document belonging to the **same `company_id`**. Evidence may never be superseded by another company's document |
| **E** | **No self-supersede.** A document must never reference itself |
| **F** | **No cycle.** A supersede chain must never form a loop; it is a linear succession from oldest evidence to current document |
| **G** | The superseded record **remains readable** to authorized verification and audit workflows, so any past decision stays reconstructable |

#### Access boundary

**This invariant preserves evidence; it grants no access.** A retained superseded document remains a **private company document** governed by the existing authorization rules — readable only to active members of that company, Career Center, Auditor, and Super Admin. **It is never public, never indexed, and never reachable without a Policy check and an audited download.** Retention and visibility are different concerns, and this rule concerns only the first.

#### Enforcement boundary

Rules **A**, **C**, and **E** are same-row facts and are enforceable declaratively. Rules **D** and **F** read another row — the successor's `company_id`, and the chain — so they require validation at the service boundary in the transaction that performs the supersede. The precise physical mechanism is finalized in `DATABASE_SCHEMA.md` and `POSTGRESQL_CONSTRAINTS.md`; no mechanism is selected here.


---

## Retention and Authorized Deletion

Default recruitment retention is **indefinite**. The architecture must later support authorized deletion, anonymization, or other retention treatment when required by institutional policy or applicable rules.

- Soft-delete/archive semantics are a logical proposal, not a selected physical implementation.
- Normal candidate or recruiter deletion must not silently destroy historical applications, status histories, consent, offers, outcomes, verification reviews, moderation reviews, vacancy versions, or audits.
- A retention process must be explicitly authorized, logged, and designed to preserve required historical evidence or permitted anonymized substitutes.
- Candidate document storage references may be archived/anonymized by an authorized retention process, but application sharing/history must not silently disappear. See INV-032.

### Logical retention treatments

| Treatment | Logical meaning | Logical marker |
| --- | --- | --- |
| **Archived** | Record retained, excluded from active views. | `archived_at` on `candidate_documents` and `vacancy_documents`. |
| **Anonymized** | Personal identifiers replaced or removed while the recruitment event, its history, and its relationships survive. | `anonymized_at` — a logical concept applicable to `users` and `candidate_profiles`, so an anonymized record is distinguishable from a live one. The physical mechanism is deferred. |
| **Disabled** | Account can no longer authenticate; all history intact. | `users.status = DISABLED` with `users.disabled_at`. |
| **Deleted under authorized policy** | Explicitly authorized, logged, evidence-preserving removal. | No field; an authorized and audited process. |

Applying a soft-delete mechanism to an entity that carries recruitment history is forbidden without an explicit archival design. A soft-deleted parent that neither cascades nor restricts would hide child history from default reads while leaving it stored, which reads as data loss and is indistinguishable from it in reporting.

## Foreign-Key Delete Guidance

No physical foreign-key action is selected in this phase. Do not blindly use CASCADE DELETE.

### Recommend RESTRICT / preserve history

- users with recruitment history;
- companies with vacancy, verification, partnership, or membership history;
- vacancies with applications, versions, reviews, schedules, or external events;
- applications with status history, shared documents, consent, evaluations, offers, or outcomes;
- candidate_documents shared through application_documents (see INV-032);
- company_documents whose `first_submitted_at` is present, and any document referenced as a `superseded_by_document_id` (see INV-038);
- roles referenced by any user_roles assignment, including revoked ones.

### Controlled delete or anonymize

Authorized retention processes should handle personal-data deletion/anonymization, account closure, obsolete private documents, and any legally required treatment. The exact physical FK behavior and storage-object lifecycle are deferred until the selected database and retention policy are known.

## Logical Index and Constraint Recommendations

These are technology-neutral recommendations, not physical index definitions.

### Uniqueness and duplication prevention

| Logical constraint | Purpose |
| --- | --- |
| `users.email_normalized` unique | Prevent case-insensitive duplicate user identities. The **only** email uniqueness rule; see INV-001. |
| `applications(candidate_profile_id, vacancy_id)` unique | Enforce one candidate-vacancy lifecycle. Unconditional; see INV-007. |
| `user_roles(user_id, role_id)` — at most one row with `revoked_at` absent | Permit full assignment history while preventing duplicate active assignments; see INV-025. |
| `company_members(company_id, user_id)` — at most one active membership | Prevent duplicate active membership. |
| `candidate_saved_vacancies(candidate_profile_id, vacancy_id)` unique | Prevent duplicate saved vacancy rows. |
| `vacancy_versions(vacancy_id, version_number)` unique | Preserve one ordered version number per vacancy. |
| `application_screening_answers(application_id, screening_question_id)` unique | Prevent duplicate answers to the same question. |
| `candidate_skills(candidate_profile_id, skill_id)` unique | Prevent duplicate skill association. |
| `candidate_links(candidate_profile_id, url)` unique | Prevent the same link being recorded twice. |
| `offers(application_id)` — at most one row with `status = ACCEPTED` | Enforce INV-031. |
| `recruitment_outcomes(application_id)` unique where present | One outcome per in-portal application. |
| `recruitment_outcomes(external_apply_event_id)` unique where present | One outcome per confirmed external event. |
| `selection_stage_assignments(recruitment_stage_id, selector_user_id)` — at most one row with `revoked_at` absent | Permit full assignment history while preventing duplicate active assignments; see INV-037. |
| `smtp_configurations` — at most one row with `is_active` set | See INV-036. |
| `company_documents(superseded_by_document_id)` — at most one predecessor per successor | A document may replace at most one predecessor, keeping the supersede chain linear (INV-038 rule F). |

**Not unique:** `companies.normalized_name`. See INV-034 below.

Five of these rules — active role assignment, active membership, single accepted offer, active selector assignment, and single active SMTP configuration — are **conditional** uniqueness. They are expressible as partial/filtered indexes on some database engines and require a generated column or an application-level guard on others. The mechanism is deliberately not selected here and must be resolved as part of the database decision; the logical rule stands regardless of which mechanism is chosen.

### INV-034 — Company Name Is Detected, Not Constrained

`companies.normalized_name` carries **no uniqueness constraint**. It is an indexed, searchable value used for duplicate detection and similarity review.

FR-COMP-001 requires the system to *flag potential duplication* across normalized company name, legal identifier, website domain, official email domain, and phone, with merge performed only by an authorized role and recorded in audit. A hard uniqueness constraint would instead hard-reject a legitimate second company whose name normalizes identically, and would pre-empt a matching policy that has not been decided.

Companies with legitimately similar or identical names must remain representable once reviewed and approved.

### Search and workflow candidates

- vacancies: current_status, target_audience, company_id, organizational_unit_id, open_at, close_at, published_at;
- applications: vacancy_id, candidate_profile_id, current_status, current_stage_id;
- companies: verification_status and normalized_name;
- partnerships: company_id, status, start_date, end_date;
- offers: application_id, status, and offer_accepted_at;
- selection_schedules: application_id, recruitment_stage_id, status, starts_at;
- notifications: user_id plus read_at;
- email_outbox: status plus next_attempt_at;
- audit_logs: object_type, object_id, action, created_at;
- review/history tables: parent reference plus occurred/reviewed timestamp;
- vacancy_requirements: vacancy_id, requirement_type, study_program_id, skill_id.

## Validation Boundaries

Some rules need more than a local constraint because they inspect another row's state, authorization, or lifecycle:

| Rule | Logical enforcement boundary |
| --- | --- |
| Company VERIFIED gate (INV-002) | Service/application authorization plus transaction-safe validation. |
| Campus IN_PORTAL method (INV-005) | Row-level constraint where possible; service validation remains required. |
| Vacancy ownership exclusivity (INV-018) | Row-level constraint where possible; service validation for the membership and status-subset parts. |
| Candidate target-audience eligibility (INV-028) | Service/application validation against `candidate_verifications` state. |
| Consent before submit (INV-011, INV-023) | Transactional service validation across applications, vacancies, and consents. |
| Outcome source exclusivity (INV-022) | Row-level constraint where the engine supports it, plus a model-level guard covering every write path. |
| In-portal application only (INV-024) | Service validation reading the parent vacancy's `application_method` inside the submit transaction. |
| Active role assignment uniqueness (INV-025) | Conditional uniqueness; mechanism deferred to the database decision. |
| Derived application caches (INV-026) | Single transactional write path that updates cache and appends history together. |
| Reopen authorization (INV-008) | Workflow/service validation plus append-only history. |
| Document access (INV-010, INV-032) | Object-level authorization plus `application_documents` existence and snapshot integrity. |
| Selector scope (INV-037) | Object-level authorization joining an active assignment to the stage, plus ownership-scoped list queries. A Policy alone is insufficient for list endpoints. |
| Company evidence supersede, same-company and no-cycle (INV-038 rules D and F) | Service validation inside the supersede transaction, with the company row locked. A same-row constraint cannot read the successor's `company_id` or walk the chain. |
| SMTP credential confidentiality (INV-035) | Application-layer encryption, a write-only field contract, and log/audit redaction. No database mechanism can enforce "never returned". |
| Application child consistency (INV-019) | Model/service guard, not request-level validation alone, so background and administrative write paths are also covered. |
| Time-based publish/expiry | Authorized scheduled process with state validation. |

Rules marked as requiring a model-level or service-level guard must be enforced on **every** write path — interactive requests, background jobs, scheduled processes, and administrative tooling alike. Validating only at the request boundary leaves the other paths unprotected.

## Validation Checklist

| Requirement | Logical result |
| --- | --- |
| Company VERIFIED gate represented | Yes — companies, company_members, vacancies, INV-002. |
| Partnership separate | Yes — partnerships and INV-003/020. |
| No temporary-password onboarding | Yes — password_credentials only contains current password metadata. |
| Password reset is one-time and expiring | Yes — password_reset_tokens and INV-021. |
| One authoritative email uniqueness rule | Yes — INV-001; `users.email` carries no competing constraint. |
| Company normalized name is not unique | Yes — INV-034. |
| No SUBMITTED vacancy status | Yes — INV-004 and controlled values. |
| Campus cannot External Apply | Yes — INV-005 and INV-018. |
| Vacancy ownership unambiguous | Yes — INV-018 exclusivity table. |
| Four target audiences only | Yes — INV-006. |
| One candidate-vacancy lifecycle | Yes — INV-007. |
| External ATS vacancy cannot have applications | Yes — INV-024. |
| Reopen and withdrawal preserve history | Yes — INV-008, INV-009, INV-016. |
| Application caches cannot drift from history | Yes — INV-026. |
| Document sharing application-specific | Yes — INV-010. |
| Shared-document evidence preserved | Yes — INV-032. |
| Consent auditable | Yes — INV-011 and consents. |
| Consent receiving party unambiguous | Yes — INV-023. |
| External apply separate | Yes — INV-012 and INV-024. |
| Outcome source unambiguous | Yes — INV-022. |
| Outcome never blocks vacancy creation | Yes — INV-014. |
| Accepted offer supports Time-to-Fill | Yes — INV-013 and INV-031. |
| Role history representable across candidate transition | Yes — INV-025 and INV-028. |
| Identity, authorization, and eligibility separate | Yes — INV-028. |
| Mandatory business reasons represented conditionally | Yes — INV-029 and INV-030. |
| Schedule reschedule preserves history without a RESCHEDULED state | Yes — INV-027. |
| SMTP outbox modeled | Yes — INV-015. |
| Audit trail modeled | Yes — INV-016, INV-033, and audit_logs. |
| Retention preserves recruitment history by default | Yes — retention section and FK guidance. |
| No mandatory workforce approval dependency | Yes — excluded from MVP ERD. |
| Selector stage assignment representable | Yes — `selection_stage_assignments` and INV-037; resolves H-1. |
| Submitted company evidence cannot be destructively deleted | Yes — INV-038 and `first_submitted_at`. |
| Superseded evidence stays traceable and same-company | Yes — INV-038 rules B, D, E, F, G. |
| Retained evidence does not become publicly accessible | Yes — INV-038 access boundary; INV-010 authorization rules are unchanged. |
| Selector cannot reach unassigned stages or vacancies | Yes — INV-037 scope rules. |
| SMTP credential manageable at runtime without plaintext exposure | Yes — INV-035 and INV-036. |
| SMTP credential absent from outbox, logs, and audit payloads | Yes — INV-015 and INV-035. |
| Remaining open questions remain open | Yes — documented in ERD.md; D-1 is closed separately by approved Product Owner decision and no remaining open question is answered by any invariant here. |
