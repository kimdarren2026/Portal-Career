# Logical ERD — Portal Karir Kampus

**Status:** Logical design only
**Revision:** 1.1-C3 — controlled logical alignment applied 24 August 2026: three fields added to `company_documents` to align the logical model with the already-approved API verification-evidence retention rule. **No entity added; no business scope introduced.** Supersedes 1.1-C2.
**Authority:** BRD v1.1 and FSD v1.1
**Scope:** Technology-agnostic entity relationships and logical fields. This is not SQL, an ORM model, or a physical database design.
**Logical entity count:** 51 — 50 business/derivation entities plus 1 system-configuration entity (`smtp_configurations`)

## Modelling Principles

- One global user identity can hold more than one role and can own at most one candidate profile.
- Company verification and an active campus partnership are independent facts.
- One vacancies aggregate supports campus employment, company employment, and internships; campus and company recruitment do not use disconnected application systems.
- One candidate and one vacancy have one application lifecycle. Reapply reopens that lifecycle and appends history.
- Candidate documents remain private until intentionally shared through an application.
- External Apply is an event stream separate from an in-portal application.
- Historical recruitment, consent, review, and audit records are preserved by default.
- **A record that can be derived from another record is not stored a second time as an independent authority.** Where a current-value field is kept for queryability, it is declared a derived cache and an invariant binds it to its authoritative source.
- **Every optional pair of alternative references carries an explicit exclusivity rule.** No entity in this model may reach a state where two alternative parents are both present or both absent.

## Domains and Entities

| Domain | Logical entities |
| --- | --- |
| Identity & Authorization | users, password_credentials, email_verification_tokens, password_reset_tokens, roles, user_roles |
| Candidate | candidate_profiles, candidate_verifications, candidate_documents, candidate_saved_vacancies, candidate_educations, candidate_work_experiences, candidate_skills, candidate_organizations, candidate_certifications, candidate_links |
| Company & Partnership | companies, company_members, company_documents, company_verification_reviews, partnerships |
| Vacancy | vacancies, vacancy_versions, vacancy_requirements, vacancy_documents, vacancy_screening_questions, vacancy_moderation_reviews |
| Application & Recruitment | applications, application_status_histories, application_documents, application_screening_answers, recruitment_stages, selection_stage_assignments, selection_schedules, selection_schedule_histories, evaluations, evaluation_items, offers, recruitment_outcomes |
| Consent & Document Sharing | consents, application_documents |
| External Apply | external_apply_events |
| Notification & Email | notifications, email_outbox |
| Audit | audit_logs |
| System Configuration | smtp_configurations |
| Supporting Master Data | organizational_units, study_programs, industries, organization_types, skills, geographic_areas |

Candidate education, work experience, skills, organizations, certifications, professional links, saved vacancies, reusable geographic areas, screening answers, evaluation items, schedule history, selection stage assignments, and recruitment outcomes are **Technical Derivations implementing approved FSD functions — they require no new business scope**. They normalize approved candidate-profile (FR-CAN-003), location, screening, evaluation, scheduling, saved-vacancy, and outcome functions without adding a business feature.

`vacancy_types` is **deferred** and is no longer a logical entity in this model. See *Future Extension — Not MVP*.

`company_documents` gained three lifecycle fields in revision 1.1-C3. They implement the already-approved API verification-evidence retention rule and add **no** entity and **no** business capability the frozen API contract does not already define. The entity count is unchanged at 51.

`smtp_configurations` is **system configuration, not business-domain data**. It is listed and governed here because it holds a credential and therefore needs explicit invariants, but it carries no recruitment meaning, appears in no reporting derivation, and must never be referenced by a business rule.

## Logical Relationship ERD

~~~mermaid
erDiagram
    USERS {
        Identifier id PK
        String email
        String email_normalized UK
        Enum status
        Timestamp email_verified_at
    }
    PASSWORD_CREDENTIALS {
        Identifier id PK
        Reference user_id FK
        String password_hash
    }
    EMAIL_VERIFICATION_TOKENS {
        Identifier id PK
        Reference user_id FK
        String token_hash
        Timestamp expires_at
    }
    PASSWORD_RESET_TOKENS {
        Identifier id PK
        Reference user_id FK
        String token_hash
        Timestamp expires_at
        Timestamp used_at
        Timestamp revoked_at
    }
    ROLES {
        Identifier id PK
        String code UK
        String name
    }
    USER_ROLES {
        Identifier id PK
        Reference user_id FK
        Reference role_id FK
        Timestamp assigned_at
        Reference assigned_by FK
        Timestamp revoked_at
        Reference revoked_by FK
    }
    CANDIDATE_PROFILES {
        Identifier id PK
        Reference user_id FK
        Enum current_candidate_type
        Reference province_geographic_area_id FK
        Reference city_geographic_area_id FK
        Enum preferred_employment_type
        Enum preferred_workplace_mode
    }
    CANDIDATE_VERIFICATIONS {
        Identifier id PK
        Reference candidate_profile_id FK
        Enum verification_type
        Enum status
    }
    CANDIDATE_DOCUMENTS {
        Identifier id PK
        Reference candidate_profile_id FK
        Enum document_type
        String storage_reference
    }
    CANDIDATE_SAVED_VACANCIES {
        Identifier id PK
        Reference candidate_profile_id FK
        Reference vacancy_id FK
        Timestamp saved_at
    }
    CANDIDATE_EDUCATIONS {
        Identifier id PK
        Reference candidate_profile_id FK
        Reference study_program_id FK
    }
    CANDIDATE_WORK_EXPERIENCES {
        Identifier id PK
        Reference candidate_profile_id FK
        String employer_name
    }
    CANDIDATE_SKILLS {
        Identifier id PK
        Reference candidate_profile_id FK
        Reference skill_id FK
        Enum proficiency_level
    }
    CANDIDATE_ORGANIZATIONS {
        Identifier id PK
        Reference candidate_profile_id FK
        String organization_name
        String role_title
    }
    CANDIDATE_CERTIFICATIONS {
        Identifier id PK
        Reference candidate_profile_id FK
        String certification_name
        String issuer_name
    }
    CANDIDATE_LINKS {
        Identifier id PK
        Reference candidate_profile_id FK
        Enum link_type
        String url
    }
    COMPANIES {
        Identifier id PK
        String normalized_name
        Enum verification_status
        Reference organization_type_id FK
        Reference province_geographic_area_id FK
        Reference city_geographic_area_id FK
    }
    COMPANY_MEMBERS {
        Identifier id PK
        Reference company_id FK
        Reference user_id FK
        Enum company_role
        Enum status
    }
    COMPANY_DOCUMENTS {
        Identifier id PK
        Reference company_id FK
        Enum document_type
        Timestamp first_submitted_at
        Timestamp superseded_at
        Reference superseded_by_document_id FK
    }
    COMPANY_VERIFICATION_REVIEWS {
        Identifier id PK
        Reference company_id FK
        Reference reviewer_user_id FK
        Enum action
        Enum to_status
        Enum reason_category
    }
    PARTNERSHIPS {
        Identifier id PK
        Reference company_id FK
        Enum status
        Date end_date
    }
    VACANCIES {
        Identifier id PK
        String vacancy_code UK
        String slug UK
        Enum vacancy_type
        Enum ownership_type
        Reference company_id FK
        Reference organizational_unit_id FK
        Reference province_geographic_area_id FK
        Reference city_geographic_area_id FK
        Enum target_audience
        Enum application_method
        Enum current_status
        Timestamp published_at
    }
    VACANCY_VERSIONS {
        Identifier id PK
        Reference vacancy_id FK
        Integer version_number
        StructuredData snapshot
        Text change_reason
    }
    VACANCY_REQUIREMENTS {
        Identifier id PK
        Reference vacancy_id FK
        Enum requirement_type
        Reference study_program_id FK
        Reference skill_id FK
        Enum education_level
        String value_text
    }
    VACANCY_DOCUMENTS {
        Identifier id PK
        Reference vacancy_id FK
        Enum document_type
    }
    VACANCY_SCREENING_QUESTIONS {
        Identifier id PK
        Reference vacancy_id FK
        Enum question_type
        Boolean required
    }
    VACANCY_MODERATION_REVIEWS {
        Identifier id PK
        Reference vacancy_id FK
        Reference reviewer_user_id FK
        Enum action
        Enum to_status
        Enum reason_category
    }
    APPLICATIONS {
        Identifier id PK
        String application_code UK
        Reference candidate_profile_id FK
        Reference vacancy_id FK
        Reference current_stage_id FK
        Enum current_status
        Integer reopen_count
    }
    APPLICATION_STATUS_HISTORIES {
        Identifier id PK
        Reference application_id FK
        Enum event_type
        Enum candidate_visibility
        Timestamp occurred_at
    }
    APPLICATION_DOCUMENTS {
        Identifier id PK
        Reference application_id FK
        Reference candidate_document_id FK
        String snapshot_name
        String snapshot_storage_reference
        Timestamp shared_at
    }
    APPLICATION_SCREENING_ANSWERS {
        Identifier id PK
        Reference application_id FK
        Reference screening_question_id FK
    }
    RECRUITMENT_STAGES {
        Identifier id PK
        Reference vacancy_id FK
        Enum stage_type
        String candidate_visible_label
    }
    SELECTION_STAGE_ASSIGNMENTS {
        Identifier id PK
        Reference recruitment_stage_id FK
        Reference selector_user_id FK
        Reference assigned_by_user_id FK
        Timestamp assigned_at
        Timestamp revoked_at
        Reference revoked_by_user_id FK
    }
    SELECTION_SCHEDULES {
        Identifier id PK
        Reference application_id FK
        Reference recruitment_stage_id FK
        Enum status
        Integer revision_number
    }
    SELECTION_SCHEDULE_HISTORIES {
        Identifier id PK
        Reference selection_schedule_id FK
        Enum event_type
    }
    EVALUATIONS {
        Identifier id PK
        Reference application_id FK
        Reference recruitment_stage_id FK
        Reference evaluator_user_id FK
    }
    EVALUATION_ITEMS {
        Identifier id PK
        Reference evaluation_id FK
        Decimal score
    }
    OFFERS {
        Identifier id PK
        Reference application_id FK
        Enum status
        Timestamp offer_accepted_at
    }
    RECRUITMENT_OUTCOMES {
        Identifier id PK
        Enum source_type
        Reference application_id FK
        Reference external_apply_event_id FK
        Enum outcome
        Enum reported_by_source
    }
    CONSENTS {
        Identifier id PK
        Reference user_id FK
        Reference application_id FK
        Reference vacancy_id FK
        Reference receiving_company_id FK
        Reference receiving_organizational_unit_id FK
        Enum consent_type
    }
    EXTERNAL_APPLY_EVENTS {
        Identifier id PK
        Reference candidate_profile_id FK
        Reference vacancy_id FK
        Reference consent_id FK
        Enum event_type
    }
    NOTIFICATIONS {
        Identifier id PK
        Reference user_id FK
        Enum type
    }
    EMAIL_OUTBOX {
        Identifier id PK
        String recipient
        Enum status
    }
    AUDIT_LOGS {
        Identifier id PK
        Reference actor_user_id FK
        String action
        String object_type
    }
    SMTP_CONFIGURATIONS {
        Identifier id PK
        String host
        Integer port
        Enum encryption_mode
        String username
        String encrypted_password
        String from_address
        Boolean is_active
        Reference updated_by_user_id FK
    }
    ORGANIZATIONAL_UNITS {
        Identifier id PK
        Reference parent_unit_id FK
        String code UK
    }
    STUDY_PROGRAMS {
        Identifier id PK
        String code UK
    }
    INDUSTRIES {
        Identifier id PK
        String code UK
    }
    ORGANIZATION_TYPES {
        Identifier id PK
        String code UK
    }
    SKILLS {
        Identifier id PK
        String normalized_name UK
    }
    GEOGRAPHIC_AREAS {
        Identifier id PK
        Reference parent_geographic_area_id FK
        Enum area_type
    }

    USERS ||--o| PASSWORD_CREDENTIALS : authenticates_with
    USERS ||--o{ EMAIL_VERIFICATION_TOKENS : receives
    USERS ||--o{ PASSWORD_RESET_TOKENS : resets_password_with
    USERS ||--o{ USER_ROLES : has
    ROLES ||--o{ USER_ROLES : assigned_in
    USERS ||--o| CANDIDATE_PROFILES : owns
    USERS ||--o{ COMPANY_MEMBERS : joins
    USERS ||--o{ COMPANY_VERIFICATION_REVIEWS : reviews
    USERS ||--o{ VACANCY_MODERATION_REVIEWS : moderates
    USERS ||--o{ EVALUATIONS : submits
    USERS ||--o{ OFFERS : issues
    USERS ||--o{ CONSENTS : gives
    USERS ||--o{ NOTIFICATIONS : receives
    USERS ||--o{ AUDIT_LOGS : performs
    USERS ||--o{ SMTP_CONFIGURATIONS : maintains

    CANDIDATE_PROFILES ||--o{ CANDIDATE_VERIFICATIONS : has
    CANDIDATE_PROFILES ||--o{ CANDIDATE_DOCUMENTS : owns
    CANDIDATE_PROFILES ||--o{ CANDIDATE_SAVED_VACANCIES : saves
    CANDIDATE_PROFILES ||--o{ CANDIDATE_EDUCATIONS : records
    CANDIDATE_PROFILES ||--o{ CANDIDATE_WORK_EXPERIENCES : records
    CANDIDATE_PROFILES ||--o{ CANDIDATE_SKILLS : records
    CANDIDATE_PROFILES ||--o{ CANDIDATE_ORGANIZATIONS : records
    CANDIDATE_PROFILES ||--o{ CANDIDATE_CERTIFICATIONS : records
    CANDIDATE_PROFILES ||--o{ CANDIDATE_LINKS : publishes
    CANDIDATE_PROFILES ||--o{ APPLICATIONS : submits
    CANDIDATE_PROFILES ||--o{ EXTERNAL_APPLY_EVENTS : starts
    STUDY_PROGRAMS ||--o{ CANDIDATE_VERIFICATIONS : supports
    STUDY_PROGRAMS ||--o{ CANDIDATE_EDUCATIONS : identifies
    SKILLS ||--o{ CANDIDATE_SKILLS : catalogs
    GEOGRAPHIC_AREAS ||--o{ CANDIDATE_PROFILES : locates

    COMPANIES ||--o{ COMPANY_MEMBERS : has
    COMPANIES ||--o{ COMPANY_DOCUMENTS : supplies
    COMPANY_DOCUMENTS ||--o| COMPANY_DOCUMENTS : superseded_by
    COMPANIES ||--o{ COMPANY_VERIFICATION_REVIEWS : reviewed_by
    COMPANIES ||--o{ PARTNERSHIPS : may_have
    COMPANIES ||--o{ VACANCIES : owns
    ORGANIZATION_TYPES ||--o{ COMPANIES : classifies
    INDUSTRIES ||--o{ COMPANIES : classifies
    GEOGRAPHIC_AREAS ||--o{ COMPANIES : locates
    GEOGRAPHIC_AREAS ||--o{ GEOGRAPHIC_AREAS : contains

    ORGANIZATIONAL_UNITS ||--o{ ORGANIZATIONAL_UNITS : contains
    ORGANIZATIONAL_UNITS ||--o{ VACANCIES : owns
    GEOGRAPHIC_AREAS ||--o{ VACANCIES : locates
    VACANCIES ||--o{ VACANCY_VERSIONS : versions
    VACANCIES ||--o{ VACANCY_REQUIREMENTS : requires
    VACANCIES ||--o{ VACANCY_DOCUMENTS : attaches
    VACANCIES ||--o{ VACANCY_SCREENING_QUESTIONS : asks
    VACANCIES ||--o{ VACANCY_MODERATION_REVIEWS : moderated_by
    VACANCIES ||--o{ RECRUITMENT_STAGES : defines
    VACANCIES ||--o{ APPLICATIONS : receives
    VACANCIES ||--o{ EXTERNAL_APPLY_EVENTS : tracks
    VACANCIES ||--o{ CANDIDATE_SAVED_VACANCIES : saved_as
    VACANCIES ||--o{ CONSENTS : scopes
    STUDY_PROGRAMS ||--o{ VACANCY_REQUIREMENTS : qualifies
    SKILLS ||--o{ VACANCY_REQUIREMENTS : qualifies

    APPLICATIONS ||--o{ APPLICATION_STATUS_HISTORIES : records
    APPLICATIONS ||--o{ APPLICATION_DOCUMENTS : shares
    APPLICATIONS ||--o{ APPLICATION_SCREENING_ANSWERS : answers
    APPLICATIONS ||--o{ SELECTION_SCHEDULES : schedules
    APPLICATIONS ||--o{ EVALUATIONS : evaluates
    APPLICATIONS ||--o{ OFFERS : receives
    APPLICATIONS ||--o{ CONSENTS : authorizes
    APPLICATIONS ||--o| RECRUITMENT_OUTCOMES : concludes
    CANDIDATE_DOCUMENTS ||--o{ APPLICATION_DOCUMENTS : shared_as
    VACANCY_SCREENING_QUESTIONS ||--o{ APPLICATION_SCREENING_ANSWERS : answered_by
    RECRUITMENT_STAGES ||--o{ SELECTION_SCHEDULES : schedules_in
    RECRUITMENT_STAGES ||--o{ EVALUATIONS : evaluated_in
    RECRUITMENT_STAGES ||--o{ SELECTION_STAGE_ASSIGNMENTS : assigns_selectors_to
    USERS ||--o{ SELECTION_STAGE_ASSIGNMENTS : assigned_as_selector
    SELECTION_SCHEDULES ||--o{ SELECTION_SCHEDULE_HISTORIES : changes
    EVALUATIONS ||--o{ EVALUATION_ITEMS : contains

    COMPANIES ||--o{ CONSENTS : receives
    ORGANIZATIONAL_UNITS ||--o{ CONSENTS : receives
    CONSENTS ||--o{ EXTERNAL_APPLY_EVENTS : may_authorize
    EXTERNAL_APPLY_EVENTS ||--o| RECRUITMENT_OUTCOMES : may_confirm
~~~

## Exclusive Reference Rules

Three relationships in this model offer two alternative parents. Each carries a mandatory exclusivity rule; none may be left ambiguous.

| Entity | Alternative references | Rule | Invariant |
| --- | --- | --- | --- |
| `recruitment_outcomes` | `application_id` / `external_apply_event_id` | `source_type = INTERNAL_APPLICATION` requires `application_id` and forbids `external_apply_event_id`. `source_type = EXTERNAL_APPLY` requires `external_apply_event_id` and forbids `application_id`. Exactly one is always present. | INV-022 |
| `consents` | `receiving_company_id` / `receiving_organizational_unit_id` | For recruitment data-sharing consent exactly one is present, determined by the vacancy's `ownership_type`: COMPANY → company; CAMPUS → organizational unit. Neither-present and both-present are invalid. | INV-023 |
| `vacancies` | `company_id` / `organizational_unit_id` | `ownership_type = COMPANY` requires `company_id` and forbids `organizational_unit_id`. `ownership_type = CAMPUS` requires `organizational_unit_id` and forbids `company_id`. | INV-018 |

Three **conditional uniqueness** rules also govern this model — at most one active role assignment (INV-025), at most one active company membership (INV-017), and at most one ACCEPTED offer per application (INV-031). Revision 1.1-C2 adds two more of the same shape: at most one active selector assignment per stage (INV-037) and at most one active SMTP configuration (INV-036).

`recruitment_outcomes` deliberately stores **no** `vacancy_id` and **no** `candidate_profile_id`. Both are reachable through whichever source reference is populated, and duplicating them would create a second authority that could contradict its own parent. Reporting joins through the source reference.

## Core Logical Ownership Rules

| Vacancy ownership_type | Required owner | Forbidden owner | Additional constraints |
| --- | --- | --- | --- |
| COMPANY (`COMPANY_EMPLOYMENT` or `INTERNSHIP`) | `company_id` required; creator must be an active company member of that company | `organizational_unit_id` must be absent | Company must be `VERIFIED` to create; may use `IN_PORTAL` or `EXTERNAL_ATS` |
| CAMPUS (`CAMPUS_EMPLOYMENT`) | `organizational_unit_id` required — FR-HR-002 lists unit/fakultas/bagian among the minimum campus vacancy fields | `company_id` must be absent | `application_method` must be `IN_PORTAL`; moderation-only statuses are forbidden |

The exact physical enforcement mechanism is deferred. It will combine relational constraints where possible with authorized service validation for cross-record state checks.

## Application Status Versus Recruitment Stage

These are two different facts and must never become competing lifecycle truth.

| | Application Status | Recruitment Stage |
| --- | --- | --- |
| **Field** | `applications.current_status` | `recruitment_stages` + `applications.current_stage_id` |
| **Meaning** | Lifecycle / business state of the candidate's relationship to the vacancy | Configurable operational selection step defined by one vacancy |
| **Vocabulary** | Fixed and system-wide — the ten values of FR-APP-004 | Open, owner-defined, ordered per vacancy |
| **Example** | `INTERVIEW` | "Wawancara HR", then "Wawancara Dekan" |
| **Audience** | Candidate-facing labels per FR-APP-004 | Internal; exposed only through the optional `candidate_visible_label` |
| **Authority** | Derived cache of `application_status_histories` | Derived cache of `application_status_histories` |

A candidate may move from "Wawancara HR" to "Wawancara Dekan" while `current_status` stays `INTERVIEW`. Internal stage detail is never automatically promoted to a candidate-facing status; FR-APP-004 and FR-HR-007 both require an explicit transition action. `applications.current_stage_id` is retained because it points at the active operational stage and makes applicant lists queryable without walking history; INV-019 keeps it inside the application's own vacancy and INV-026 keeps it consistent with history.

## Candidate Identity, Authorization, and Eligibility

Three separate concerns that must not be conflated. INV-028 states the rule.

| Concern | Authoritative source | What it does **not** mean |
| --- | --- | --- |
| **A. Authorization** | `roles` + `user_roles` | Holding `CANDIDATE_ALUMNI` is **not** proof of alumni verification and must never be read as eligibility. |
| **B. Candidate identity category** | `candidate_profiles.current_candidate_type` | A self-declared category. It is not proof of anything and does not grant access. |
| **C. Verified eligibility** | `candidate_verifications` with `status = VERIFIED` and the matching `verification_type` | The **only** admissible basis for `ALUMNI_ONLY` and `FINAL_YEAR_AND_ALUMNI` target-audience gating. |

A final-year student who becomes an alumnus keeps the **same** user, the **same** candidate profile, and the **entire** application history. `current_candidate_type` changes from `FINAL_YEAR_STUDENT` to `ALUMNI`; the candidate authorization role may change accordingly through application logic; and verified alumni eligibility requires a new `candidate_verifications` record of type `ALUMNI` reaching `VERIFIED`. Because `user_roles` keeps a surrogate identifier and a `revoked_at`/`revoked_by` pair rather than a composite key, the previous student role assignment remains in history alongside the new alumni assignment.

## Controlled Value Sets

| Field | Allowed logical values |
| --- | --- |
| users.status | PENDING_EMAIL_VERIFICATION, ACTIVE, SUSPENDED, DISABLED |
| candidate_profiles.current_candidate_type | EXTERNAL, FINAL_YEAR_STUDENT, ALUMNI |
| candidate_verifications.verification_type | ALUMNI, FINAL_YEAR_STUDENT |
| candidate_verifications.status | NOT_VERIFIED, PENDING, VERIFIED, MISMATCH_MANUAL_REVIEW |
| candidate_links.link_type | LINKEDIN, PORTFOLIO, PERSONAL_WEBSITE, PUBLICATION, OTHER |
| companies.verification_status | DRAFT, PENDING_VERIFICATION, REVISION_REQUIRED, VERIFIED, REJECTED, SUSPENDED |
| vacancies.vacancy_type | CAMPUS_EMPLOYMENT, COMPANY_EMPLOYMENT, INTERNSHIP |
| vacancies.ownership_type | COMPANY, CAMPUS |
| vacancies.target_audience | PUBLIC, ALUMNI_ONLY, FINAL_YEAR_AND_ALUMNI, INTERNAL |
| vacancies.application_method | IN_PORTAL, EXTERNAL_ATS |
| company vacancy status | DRAFT, PENDING_REVIEW, REVISION_REQUIRED, APPROVED, SCHEDULED, PUBLISHED, REJECTED, CLOSED, EXPIRED, SUSPENDED |
| campus vacancy status | DRAFT, SCHEDULED, PUBLISHED, CLOSED, EXPIRED, SUSPENDED |
| vacancy_requirements.requirement_type | EDUCATION, STUDY_PROGRAM, EXPERIENCE, SKILL, CERTIFICATION, OTHER_QUALIFICATION |
| applications.current_status | APPLIED, UNDER_REVIEW, SHORTLISTED, ASSESSMENT, INTERVIEW, OFFERED, HIRED, REJECTED, WITHDRAWN, NO_SHOW |
| application_status_histories.event_type | APPLICATION_CREATED, STATUS_CHANGED, STAGE_CHANGED, APPLICATION_REOPENED, WITHDRAWN, REJECTED, OFFER_ACCEPTED, NO_SHOW |
| offers.status | DRAFT, SENT, PENDING_RESPONSE, ACCEPTED, REJECTED, EXPIRED |
| selection_schedules.status | SCHEDULED, COMPLETED, CANCELLED, NO_SHOW |
| selection_schedule_histories.event_type | CREATED, RESCHEDULED, COMPLETED, CANCELLED, NO_SHOW |
| recruitment_outcomes.source_type | INTERNAL_APPLICATION, EXTERNAL_APPLY |
| recruitment_outcomes.reported_by_source | CANDIDATE, COMPANY, CAMPUS_STAFF, INTEGRATION |
| email_outbox.status | PENDING, PROCESSING, SENT, FAILED_RETRYABLE, DEAD_LETTER |
| smtp_configurations.encryption_mode | NONE, STARTTLS, TLS |
| smtp_configurations.last_test_result | NOT_TESTED, SUCCESS, FAILURE |

SUBMITTED and DIAJUKAN are deliberately absent from stored vacancy statuses. Submit is a transition action to PENDING_REVIEW.

`RESCHEDULED` is deliberately absent from `selection_schedules.status`. A rescheduled appointment is still `SCHEDULED` at its new time; the reschedule is an event recorded in `selection_schedule_histories` together with an incremented `selection_schedules.revision_number`. Keeping it in both places would create two ways to express one fact and make "is this appointment upcoming?" ambiguous.

`candidate_verifications.status` follows the FR-CAN-004 vocabulary. `REJECTED` was removed during correction because FR-CAN-004 does not define it; a rejected verification is recorded as `MISMATCH_MANUAL_REVIEW` with a reason. Introducing a distinct rejected outcome would require an FSD change request.

`email_outbox.status` consolidates FR-NOTIF-001's `FAILED → RETRY_SCHEDULED` into the single retryable state `FAILED_RETRYABLE`, whose scheduled retry time is `next_attempt_at`. The mapping is recorded so the FSD wording stays traceable.

## Reporting Is Derived From Transactional Truth

Verified companies, active partnerships, vacancy/application funnels, external starts versus confirmations, accepted offers, and Time-to-Fill are derived from the entities above.

**Time-to-Fill is `offers.offer_accepted_at` minus `vacancies.published_at`.** It never uses an onboarding date, contract-signing date, start date, or first working day — no such field exists anywhere in this model. Where a vacancy has more than one accepted offer, the vacancy-level metric uses the **earliest** `offer_accepted_at` among that vacancy's accepted offers. The metric is undefined while `published_at` is null. INV-013 and INV-031 state the rules.

Stored KPI totals, database views, materialized reporting, and refresh strategy are later architecture decisions.

## Open Questions Affecting Physical Schema

All six remain open and none is answered by this model.

| Open question | Flexible logical design |
| --- | --- |
| Final alumni verification integration source | candidate_verifications retains verification_type, source_reference, reviewer, and status without assuming SSO, sync, or NIM-only. |
| Minimum legal documents by organization type | company_documents supports typed records; no mandatory NIB-only rule and no per-organization-type requirement matrix is embedded. |
| Salary mandatory/optional/display policy | Vacancy salary fields are nullable and policy is not encoded as a universal requirement. |
| Recruiter domain/subdomain | users and password_credentials remain channel-neutral; no portal, tenant, or origin-domain field exists. |
| WhatsApp notification in a later phase | notifications and email_outbox do not imply a WhatsApp delivery entity. Contact phone fields exist only because FR-CAN-003 requires contact data. |
| First recruiter role and minimum active Company Admin rule | company_members records a company_role without assigning a default or enforcing a minimum rule. |

## Recorded Design Decisions

Decisions taken while applying the corrections in `ERD_REVIEW.md`. They are readings of BRD/FSD v1.1, not changes to it.

| # | Decision | Basis |
| --- | --- | --- |
| D-1 | `applications` uniqueness on `(candidate_profile_id, vacancy_id)` is **full**, not limited to active applications. | FSD §6.5's phrase "satu lifecycle **aktif**" was read against FR-APP-002 ("satu lifecycle application per candidate_id + vacancy_id") and FR-APP-003.4 ("tidak membuat application kedua"), which are unambiguous and authoritative. If the business intends otherwise this must be raised as a change request against the FSD; it is not resolved in the model. |
| D-2 | `geographic_areas` governs company address, candidate domicile, and vacancy location consistently, each with a free-text fallback for unmatched values. | FR-ONB-002 marks company Provinsi/kota as Wajib using "Master wilayah". Normalizing one entity and leaving the other two as free text would make location search unusable. |
| D-3 | `skills` is adopted as a real master rather than an optional catalog. | `candidate_skills.skill_id` is a required reference; a required reference to an optional entity cannot stand. Adoption also lets `vacancy_requirements` share the same catalog. |
| D-4 | `recruitment_outcomes` stores no `vacancy_id` or `candidate_profile_id`. | Both are derivable from the single populated source reference. Storing them would create a second authority able to contradict its parent. |
| D-5 | Professional/portfolio links are a repeatable `candidate_links` entity rather than fixed profile columns. | FR-CAN-003 names "link profesional/portfolio" without limiting the count. A small typed entity is flexible without building portfolio management. |
| D-6 | Work preferences are logical fields on `candidate_profiles`, not a separate entity. | FR-CAN-003 names "preferensi kerja" as profile content; a single-valued preference set does not justify a child entity. |
| D-7 | Selector scope is modelled as `selection_stage_assignments` — an assignment per stage, not a broader permission model. | FR-HR-006 requires Admin Kepegawaian to set which stages a selector may access. Assignment to a specific `recruitment_stages` row is the narrowest structure that expresses exactly that, and it inherits the vacancy scope from the stage. A general permission/ACL table would grant far more than the requirement asks for. Resolves human-decision item H-1. |
| D-8 | SMTP runtime configuration is a single `smtp_configurations` entity holding an application-encrypted secret, **not** a separate `system_secrets` abstraction. | FR-NOTIF-005 requires Super Admin to manage SMTP settings including the credential, so environment-only configuration cannot satisfy it. Exactly one secret is runtime-managed in this system; a general secret-store abstraction would add an indirection layer with one row in it. Resolves ADR-010. |
| D-9 | Company verification evidence is retained through a **supersede chain on `company_documents`** — `first_submitted_at`, `superseded_at`, `superseded_by_document_id` — rather than a separate evidence or version entity. | A Career Center verification decision is made against a specific set of documents; if that evidence could be deleted, the append-only `company_verification_reviews` trail would reference a decision whose basis no longer exists (INV-016). A separate entity was rejected because a superseded document **is** a company document — it differs only in lifecycle state, which three fields express without duplicating ten columns and a relationship. Applied in revision 1.1-C3 to align with the already-approved API contract; **no new business scope**. |

## Future Extension — Not MVP

`vacancy_types` was removed from the MVP entity set during correction. FR-VAC-001's "tipe tambahan melalui master data" is a post-MVP extension; for MVP the three types are a fixed logical enumeration on `vacancies.vacancy_type`, and INV-005 keys campus behaviour off that value. Introducing a configurable-type master alongside the enumeration would create two sources of one truth. It may be reintroduced through an approved change request, at which point `vacancies.vacancy_type` becomes a reference rather than an enumeration.

`workforce_requests` and `approval_flows` are intentionally absent from the required ERD. If introduced after a separately approved change, they may be related to campus planning; they must not become a prerequisite for publishing a valid campus vacancy in MVP v1.1.

Payroll, attendance, performance management, full onboarding, digital contracts, a psychometric-test engine, AI recommendation, candidate ranking, a full tracer study, and two-way ATS integration are also excluded from this MVP logical model. No entity in this ERD represents those capabilities.

## Items Referred for Human Decision

Recorded by `ERD_REVIEW.md`. H-1 was resolved by the approved change request applied in revision 1.1-C2; H-2, H-3, and H-4 remain unresolved and are **not** decided here. They are distinct from the six open questions above, all of which remain open.

| # | Item | Current logical treatment |
| --- | --- | --- |
| ~~H-1~~ | ~~Selector stage assignment~~ | **RESOLVED in revision 1.1-C2** by `selection_stage_assignments` and INV-037 (decision D-7). Retained here for history. |
| H-2 | Vacancy-level outcome — `recruitment_outcomes` is candidate-level only, so "closed, no suitable candidate" is not recordable. | Not modelled. Awaiting a decision on whether vacancy-level outcome reporting is required. |
| H-3 | `application_documents.revoked_at` — candidate revocation of an already-shared document is not defined by FSD v1.1. | Field retained and constrained by INV-032: revocation is authorized, never deletes the record, and never retroactively invalidates a completed evaluation. |
| H-4 | Audit metadata collection — whether `ip_address` and device metadata may be collected. | Both remain optional and explicitly conditioned on policy permission. |
