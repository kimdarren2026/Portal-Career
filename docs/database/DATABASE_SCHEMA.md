# Physical Database Schema — Portal Karir Kampus

**Status:** Proposed — awaiting approval
**Date:** 24 August 2026
**Engine:** PostgreSQL 16+ (ADR-003)
**Framework:** Laravel (ADR-002)
**Baselines (frozen):** BRD v1.1 · FSD v1.1 · Logical model **1.1-C3** (51 entities) · Architecture ADR-001 to ADR-017 · API Contract (148 operations)
**Companions:** `POSTGRESQL_CONSTRAINTS.md` · `INDEX_STRATEGY.md` · `MIGRATION_PLAN.md` · `SCHEMA_VALIDATION_REPORT.md`

Specification only. **No migration, SQL file, Eloquent model, or PHP code is created by this document.** It is written so that implementation requires no invention of columns, constraints, indexes, uniqueness semantics, delete behaviour, enum values, timestamps, or concurrency protection.

The physical schema **preserves the frozen logical model exactly**. No logical entity was dropped, merged, or renamed, and no business entity was added.

---

## 1. Schema Layers

Three layers, deliberately distinguished. Only the first is business data.

| Layer | Tables | Governed by | ERD change needed to alter? |
| --- | --- | --- | --- |
| **Business schema** | **51** | Logical model 1.1-C3 | **Yes** |
| **Operational application schema** | **2** — `idempotency_keys` (DB-1), `export_jobs` (DB-2) | This document | **No** — technical infrastructure |
| **Laravel framework infrastructure** | **4** — `sessions`, `cache`, `cache_locks`, `failed_jobs` | Framework | **No** |
| **Total physical tables** | **57** | | |

The operational and framework layers hold no recruitment meaning, appear in no reporting derivation, and **must never be referenced by a business invariant**. This mirrors how `smtp_configurations` is classified inside the business schema itself — present, governed, but not business-domain data.

---

## 2. Identifier Strategy

### Recommendation: `bigint` generated always as identity, project-wide

Every table uses a surrogate primary key `id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY`.

| Option | Assessment |
| --- | --- |
| **`bigint` identity — RECOMMENDED** | 8 bytes, monotonic, B-tree friendly. Every foreign key, index, and join is half the width of a UUID and stays physically ordered, so inserts append to the index rather than fragmenting it. Laravel's `bigIncrements`/`id()` and `foreignId()` map to it directly, and route-model binding, `findOrFail`, and Eloquent relations work with zero configuration |
| `uuid` v4 | Rejected. 16 bytes, random distribution — index fragmentation and page splits on every insert, on **every** foreign key in a 51-table graph. Buys non-enumerability, which this system does not need because **every read is query-scoped and out-of-scope objects return `404`, not `403`** (`ERROR_CODES.md` §10). Enumeration is already closed by authorization, not by identifier opacity |
| `uuid` v7 / ULID | Rejected for MVP. Fixes ordering but keeps the 16-byte cost. Justified when identifiers must be generated client-side or merged across databases — neither applies to a single-database monolith |
| Composite natural keys | Rejected. `user_roles`, `company_members`, and `selection_stage_assignments` all require **history plus re-issuance**, which a natural composite key structurally forbids (INV-025, INV-037) |

**External exposure is handled by opaque business codes, not by the primary key.** The logical model already provides them: `applications.application_code`, `vacancies.vacancy_code`, and `vacancies.slug`. Public vacancy URLs use `slug`; nothing forces an internal `id` into a public URL.

**Consequence recorded honestly:** if a future phase needs client-generated identifiers or multi-database merge, migrating to UUID v7 is expensive. That trade is accepted because it is speculative, and the operational cost of UUID is paid on every row, every day, from day one.

### Foreign keys

Every FK column is `bigint` and named `<referenced_table_singular>_id`, except where the logical model already fixes a role-bearing name — `assigned_by`, `revoked_by`, `reviewer_user_id`, `evaluator_user_id`, `pic_user_id`, `actor_user_id`, `confirmed_by`, `created_by`, `uploaded_by`, `invited_by`, `updated_by_user_id`, `selector_user_id`, `assigned_by_user_id`, `revoked_by_user_id`, `verified_by`, `offered_by_user_id`. **Those names are taken verbatim from `DATA_DICTIONARY.md` and are not renamed to fit a convention.**

---

## 3. Type Strategy

| Logical type | PostgreSQL type | Notes |
| --- | --- | --- |
| Identifier | `bigint` identity | §2 |
| Reference | `bigint` | FK, always typed to match its target |
| String (bounded) | `varchar(n)` | §10 for lengths |
| Text (unbounded) | `text` | Descriptions, notes, reasons. PostgreSQL stores `varchar` and `text` identically; `varchar(n)` is used only where a limit is a genuine guard |
| Boolean | `boolean NOT NULL DEFAULT false` | Never nullable — a three-valued boolean is a modelling error |
| Integer | `integer` | Counts, ports, ordering, years |
| Decimal (money) | `numeric(14,2)` | §11. **Never `float`/`double`** |
| Decimal (score) | `numeric(6,2)` | `evaluations.total_score`, `evaluation_items.score`, `evaluation_items.weight` |
| Date | `date` | Calendar dates with no time component |
| Timestamp | **`timestamptz`** | §4 — every business timestamp, without exception |
| Enum | `varchar(n)` + `CHECK` | §5 |
| Structured Data | `jsonb` | §9 — six columns, each individually justified |
| IP address | `inet` | `audit_logs.ip_address`. Native validation, correct comparison and containment semantics, compact storage. Collection is conditional on policy (H-4) |
| Storage reference | `varchar(512)` | Object-storage key, never a URL, never binary |
| Hash | `varchar(255)` | `token_hash`, `password_hash`, `checksum`, `consent_text_hash_reference` |

---

## 4. Timestamp Standard

**Every business timestamp is `timestamptz`, persisted in UTC.** No exceptions, and no naked `timestamp` column anywhere in the business schema.

| Rule | Detail |
| --- | --- |
| **Persistence** | PostgreSQL stores `timestamptz` as an absolute instant in UTC. The session `TimeZone` is set to `UTC` on every connection so no implicit local conversion can occur |
| **Application boundary** | Laravel's `$casts` convert to `CarbonImmutable` in UTC. Conversion to a user-facing zone happens **at render time only** — never in a query predicate, never in storage |
| **User-facing display** | The viewer's zone is a presentation concern. `Asia/Jakarta` is the expected default for this institution, but it is applied at the boundary, not baked into the schema |
| **Comparisons** | `open_at`, `close_at`, `published_at`, `expires_at`, `next_attempt_at` are all compared as absolute instants, so scheduler and expiry logic is DST-safe by construction |

### Selection schedule timezone semantics — the one nuance that matters

`selection_schedules` carries **both** `starts_at timestamptz` **and** `timezone varchar(64)`, and they answer different questions:

- `starts_at` is **when the interview happens** — one absolute instant, identical for every participant worldwide.
- `timezone` is **the named IANA zone the appointment was scheduled in** — for example `Asia/Jakarta`, never a fixed offset like `+07:00`.

The named zone is retained because the *intent* ("09:00 Jakarta time") survives a DST or offset rule change, whereas a stored offset does not. It also lets the interface show the candidate the zone the interviewer meant, alongside their own local rendering. `DATA_DICTIONARY.md` already specifies "a named zone rather than a fixed offset"; this is its physical realization.

**No recruitment event is ever stored without timezone context.**

---

## 5. Enum Strategy

### Recommendation: `varchar` + named `CHECK` constraint

| Option | Assessment |
| --- | --- |
| **`varchar(n)` + `CHECK` — RECOMMENDED** | Adding a value is `ALTER TABLE … DROP CONSTRAINT` + `ADD CONSTRAINT` — **transactional, reversible, and reviewable in a diff**. Laravel expresses it with `->check()` or raw `DB::statement` in `up()` and `down()`, and rollback is symmetric. Values read as plain strings in every client and export |
| PostgreSQL native `ENUM` | Rejected. `ALTER TYPE … ADD VALUE` **could not run inside a transaction block before PG12** and still cannot be rolled back cleanly; **removing** a value requires recreating the type and rewriting every dependent column. For a system whose states are still evolving under remaining open business questions, that is a rollback trap. Ordering semantics are also a trap — enum comparison follows declaration order, which silently makes `status > 'DRAFT'` meaningful and wrong |
| Lookup/master table | Rejected **for these enums**. The logical model deliberately distinguishes enums from master data: `industries`, `organization_types`, `study_programs`, `skills`, `geographic_areas`, and `organizational_units` **are** lookup tables because they are institution-managed reference data. `applications.current_status` is not — it is a behaviour-bearing state whose value set is fixed by FSD §8.5, and turning it into rows would let an administrator invent a status no state machine handles |

**Constraint naming:** `chk_<table>_<column>`. Every constraint is named explicitly so migrations can drop it deterministically.

### Complete enum inventory

All values are taken verbatim from `ERD.md` *Controlled Value Sets* and `DATA_DICTIONARY.md`. No value is added or renamed.

| Table.column | Values |
| --- | --- |
| `users.status` | `PENDING_EMAIL_VERIFICATION`, `ACTIVE`, `SUSPENDED`, `DISABLED` |
| `candidate_profiles.current_candidate_type` | `EXTERNAL`, `FINAL_YEAR_STUDENT`, `ALUMNI` |
| `candidate_verifications.verification_type` | `ALUMNI`, `FINAL_YEAR_STUDENT` |
| `candidate_verifications.status` | `NOT_VERIFIED`, `PENDING`, `VERIFIED`, `MISMATCH_MANUAL_REVIEW` |
| `candidate_links.link_type` | `LINKEDIN`, `PORTFOLIO`, `PERSONAL_WEBSITE`, `PUBLICATION`, `OTHER` |
| `companies.verification_status` | `DRAFT`, `PENDING_VERIFICATION`, `REVISION_REQUIRED`, `VERIFIED`, `REJECTED`, `SUSPENDED` |
| `company_members.company_role` | `COMPANY_ADMIN`, `COMPANY_RECRUITER` |
| `company_verification_reviews.action` | `SUBMIT`, `REQUEST_REVISION`, `VERIFY`, `REJECT`, `SUSPEND`, `RESTORE` |
| `vacancies.vacancy_type` | `CAMPUS_EMPLOYMENT`, `COMPANY_EMPLOYMENT`, `INTERNSHIP` |
| `vacancies.ownership_type` | `COMPANY`, `CAMPUS` |
| `vacancies.target_audience` | `PUBLIC`, `ALUMNI_ONLY`, `FINAL_YEAR_AND_ALUMNI`, `INTERNAL` — **exactly four** (INV-006) |
| `vacancies.application_method` | `IN_PORTAL`, `EXTERNAL_ATS` |
| `vacancies.current_status` | `DRAFT`, `PENDING_REVIEW`, `REVISION_REQUIRED`, `APPROVED`, `SCHEDULED`, `PUBLISHED`, `REJECTED`, `CLOSED`, `EXPIRED`, `SUSPENDED` — **`SUBMITTED`/`DIAJUKAN` are absent** (INV-004) |
| `vacancy_requirements.requirement_type` | `EDUCATION`, `STUDY_PROGRAM`, `EXPERIENCE`, `SKILL`, `CERTIFICATION`, `OTHER_QUALIFICATION` |
| `vacancy_screening_questions.question_type` | `SHORT_TEXT`, `LONG_TEXT`, `YES_NO`, `SINGLE_CHOICE`, `NUMBER` |
| `vacancy_moderation_reviews.action` | `SUBMIT`, `REQUEST_REVISION`, `APPROVE`, `REJECT`, `SUSPEND`, `RESTORE`, `CLOSE` |
| `applications.current_status` | `APPLIED`, `UNDER_REVIEW`, `SHORTLISTED`, `ASSESSMENT`, `INTERVIEW`, `OFFERED`, `HIRED`, `REJECTED`, `WITHDRAWN`, `NO_SHOW` |
| `application_status_histories.event_type` | `APPLICATION_CREATED`, `STATUS_CHANGED`, `STAGE_CHANGED`, `APPLICATION_REOPENED`, `WITHDRAWN`, `REJECTED`, `OFFER_ACCEPTED`, `NO_SHOW` |
| `selection_schedules.status` | `SCHEDULED`, `COMPLETED`, `CANCELLED`, `NO_SHOW` — **`RESCHEDULED` is absent** (INV-027) |
| `selection_schedule_histories.event_type` | `CREATED`, `RESCHEDULED`, `COMPLETED`, `CANCELLED`, `NO_SHOW` |
| `offers.status` | `DRAFT`, `SENT`, `PENDING_RESPONSE`, `ACCEPTED`, `REJECTED`, `EXPIRED` |
| `recruitment_outcomes.source_type` | `INTERNAL_APPLICATION`, `EXTERNAL_APPLY` |
| `recruitment_outcomes.reported_by_source` | `CANDIDATE`, `COMPANY`, `CAMPUS_STAFF`, `INTEGRATION` |
| `email_outbox.status` | `PENDING`, `PROCESSING`, `SENT`, `FAILED_RETRYABLE`, `DEAD_LETTER` |
| `smtp_configurations.encryption_mode` | `NONE`, `STARTTLS`, `TLS` |
| `smtp_configurations.last_test_result` | `NOT_TESTED`, `SUCCESS`, `FAILURE` |
| `geographic_areas.area_type` | `PROVINCE`, `CITY` (extensible per the dictionary's "approved future geography level") |
| `external_apply_events.event_type` | `EXTERNAL_APPLY_STARTED` |
| `application_status_histories.candidate_visibility` | `VISIBLE`, `INTERNAL` |
| `idempotency_keys.state` *(operational)* | `PROCESSING`, `COMPLETED`, `FAILED` |
| `export_jobs.status` *(operational)* | `PENDING`, `PROCESSING`, `COMPLETED`, `FAILED`, `EXPIRED` |

Enums whose value set the frozen documents leave open — `candidate_profiles.preferred_employment_type`, `vacancies.employment_type`, `workplace_mode`, `selection_schedules.selection_type`, `method`, `company_documents.document_type`, `candidate_documents.document_type`, `vacancy_documents.document_type`, `consents.consent_type`, `notifications.type`, `recruitment_outcomes.outcome`, `evaluations.recommendation`, `candidate_organizations.organization_type`, `candidate_skills.proficiency_level`, `candidate_educations.education_level`, `vacancy_requirements.education_level`, `partnerships.partnership_type`, `partnerships.status`, `company_documents.status`, `company_members.status`, `recruitment_stages.stage_type`, `reason_category` on both review tables, `external_apply_events.confirmation_status`, `confirmation_source` — are `varchar` with a `CHECK` added **once the vocabulary is approved**. They are documented as *vocabulary pending* rather than invented here.

---

## 6. Business Table Traceability

All 51 logical entities, each with domain, invariants, and material requirement IDs.

| # | Physical table | Logical entity | Domain | Invariants | Requirements |
| --- | --- | --- | --- | --- | --- |
| 1 | `users` | users | Identity | INV-001, INV-016 | FR-AUTH-001/002/005, FSD §8.1 |
| 2 | `password_credentials` | password_credentials | Identity | INV-021 | FR-AUTH-006 |
| 3 | `email_verification_tokens` | email_verification_tokens | Identity | INV-021 | FR-AUTH-003/004 |
| 4 | `password_reset_tokens` | password_reset_tokens | Identity | INV-021 | FR-AUTH-007 |
| 5 | `roles` | roles | Identity | INV-028 | FSD §3.1 |
| 6 | `user_roles` | user_roles | Identity | **INV-025**, INV-028 | FSD §3.1, FR-AUD-001 |
| 7 | `candidate_profiles` | candidate_profiles | Candidate | INV-028 | FR-CAN-001/003 |
| 8 | `candidate_verifications` | candidate_verifications | Candidate | **INV-028** | FR-CAN-002/004 |
| 9 | `candidate_documents` | candidate_documents | Candidate | **INV-010**, INV-032 | FR-CAN-005 |
| 10 | `candidate_saved_vacancies` | candidate_saved_vacancies | Candidate | — | FSD §4.2 |
| 11 | `candidate_educations` | candidate_educations | Candidate | — | FR-CAN-003 |
| 12 | `candidate_work_experiences` | candidate_work_experiences | Candidate | — | FR-CAN-003 |
| 13 | `candidate_skills` | candidate_skills | Candidate | — | FR-CAN-003 |
| 14 | `candidate_organizations` | candidate_organizations | Candidate | — | FR-CAN-003 |
| 15 | `candidate_certifications` | candidate_certifications | Candidate | — | FR-CAN-003 |
| 16 | `candidate_links` | candidate_links | Candidate | — | FR-CAN-003 |
| 17 | `companies` | companies | Company | **INV-002**, INV-030, **INV-034** | FR-ONB-002, FR-COMP-001/002 |
| 18 | `company_members` | company_members | Company | **INV-017** | FR-COMP-004 |
| 19 | `company_documents` | company_documents | Company | INV-016, INV-030 | FR-ONB-002/005 |
| 20 | `company_verification_reviews` | company_verification_reviews | Company | INV-016, INV-029 | FR-ONB-003/004/005 |
| 21 | `partnerships` | partnerships | Partnership | **INV-003**, **INV-020** | FR-COMP-003 |
| 22 | `vacancies` | vacancies | Vacancy | **INV-004/005/006/018**, INV-013, INV-024 | FR-VAC-001…008, FR-HR-002/003/004 |
| 23 | `vacancy_versions` | vacancy_versions | Vacancy | INV-016 | **FR-VAC-007** |
| 24 | `vacancy_requirements` | vacancy_requirements | Vacancy | — | FR-VAC-003, FR-HR-002 |
| 25 | `vacancy_documents` | vacancy_documents | Vacancy | — | FSD §6.1 |
| 26 | `vacancy_screening_questions` | vacancy_screening_questions | Vacancy | INV-019 | FR-VAC-003, FR-HR-002 |
| 27 | `vacancy_moderation_reviews` | vacancy_moderation_reviews | Vacancy | INV-016, INV-029 | FR-VAC-006 |
| 28 | `recruitment_stages` | recruitment_stages | Vacancy | INV-019 | FR-HR-005, FR-APP-004 |
| 29 | `applications` | applications | Recruitment | **INV-007/008/024/026** | FR-APP-001/002/003 |
| 30 | `application_status_histories` | application_status_histories | Recruitment | **INV-016**, INV-026 | FR-APP-005 |
| 31 | `application_documents` | application_documents | Recruitment | **INV-010**, **INV-032** | FR-CONSENT-003 |
| 32 | `application_screening_answers` | application_screening_answers | Recruitment | INV-019 | FR-APP-001 |
| 33 | `selection_stage_assignments` | selection_stage_assignments | Vacancy | **INV-037**, INV-025 | **FR-HR-006** |
| 34 | `selection_schedules` | selection_schedules | Recruitment | **INV-027**, INV-019 | FR-SEL-001 |
| 35 | `selection_schedule_histories` | selection_schedule_histories | Recruitment | INV-016, INV-027 | FR-SEL-001 |
| 36 | `evaluations` | evaluations | Recruitment | INV-019, INV-037 | FR-SEL-002, FR-HR-007 |
| 37 | `evaluation_items` | evaluation_items | Recruitment | — | FR-SEL-002, FR-HR-007 |
| 38 | `offers` | offers | Recruitment | **INV-013**, **INV-031** | FR-SEL-003/004/005 |
| 39 | `recruitment_outcomes` | recruitment_outcomes | Recruitment | **INV-022**, INV-014 | FR-EXT-004, FR-NOTIF-004 |
| 40 | `consents` | consents | Consent | **INV-011**, **INV-023** | FR-CONSENT-001/002 |
| 41 | `external_apply_events` | external_apply_events | External Apply | **INV-012**, INV-024 | FR-EXT-001/002/003 |
| 42 | `notifications` | notifications | Notification | INV-033 | FR-NOTIF-002 |
| 43 | `email_outbox` | email_outbox | Notification | **INV-015**, INV-035 | FR-NOTIF-001/003 |
| 44 | `audit_logs` | audit_logs | Audit | **INV-016**, INV-033, INV-035 | **FR-AUD-001** |
| 45 | `smtp_configurations` | smtp_configurations | System Configuration | **INV-035**, **INV-036** | FR-NOTIF-005 |
| 46 | `organizational_units` | organizational_units | Master Data | INV-018 | FR-HR-002 |
| 47 | `study_programs` | study_programs | Master Data | — | FR-VAC-003, FR-CAN-004 |
| 48 | `industries` | industries | Master Data | — | FR-ONB-002 |
| 49 | `organization_types` | organization_types | Master Data | INV-030 | FR-ONB-002 |
| 50 | `skills` | skills | Master Data | — | FR-CAN-003, FR-VAC-003 |
| 51 | `geographic_areas` | geographic_areas | Master Data | — | FR-ONB-002 |

### Operational tables — **Operational Technical Requirement, not BRD business entities**

| # | Table | Source | Note |
| --- | --- | --- | --- |
| O-1 | `idempotency_keys` | **DB-1** | §12. Not a business entity; no ERD change request required |
| O-2 | `export_jobs` | **DB-2** | §13. Not a business entity; no ERD change request required |

---

## 7. Foreign Keys and Delete Behaviour

**No blanket `CASCADE`.** Default is `ON DELETE RESTRICT`, `ON UPDATE NO ACTION` (identity keys never change). `CASCADE` appears only where a child row is a **technical dependent with no independent meaning**, and every instance is listed below.

Full column-level detail is in `POSTGRESQL_CONSTRAINTS.md` §3. Policy summary:

| Policy | Applies to | Rationale |
| --- | --- | --- |
| **RESTRICT** (default) | `users` with recruitment history · `companies` with vacancies, members, reviews, partnerships · `vacancies` with applications, versions, reviews, stages, external events · `applications` with history, documents, consents, evaluations, offers, outcomes · `candidate_documents` referenced by a live `application_documents` share (**INV-032**) · every master-data reference · `roles` referenced by any assignment including revoked ones | Recruitment history, consent, offers, verification decisions, and moderation decisions are **evidence**. Default retention is indefinite (FR-AUD-002) |
| **SET NULL** | Nullable *actor* references only: `actor_user_id`, `assigned_by`, `revoked_by`, `invited_by`, `confirmed_by`, `pic_user_id`, `verified_by`, `revoked_by_user_id` | The event survives even if the acting account is later removed by an authorized retention process. **The history row must never disappear because an actor did** |
| **CASCADE** — the complete list | `password_credentials.user_id` · `email_verification_tokens.user_id` · `password_reset_tokens.user_id` · `evaluation_items.evaluation_id` · `candidate_saved_vacancies.candidate_profile_id` | Credentials and tokens are meaningless without their user and hold no recruitment evidence. `evaluation_items` are physically part of their parent evaluation. A saved vacancy is a bookmark. **Nothing else cascades** |
| **Controlled archival** | `candidate_documents.archived_at` · `vacancy_documents.archived_at` · `company_documents` supersede chain · `users.anonymized_at` · `candidate_profiles.anonymized_at` | Retention is an authorized, audited **process**, never a foreign-key side effect |

**Deliberately not RESTRICT-blocked but not deletable either:** `applications` has no delete path in the Action layer at all. Withdrawal sets `WITHDRAWN` (INV-009); nothing removes the row.

---

## 8. Soft Delete and Archival — Entity by Entity

**Laravel `SoftDeletes` is NOT applied globally.** Adding `deleted_at` to a history table would let a developer soft-delete an audit record with a single Eloquent call — silently defeating INV-016. The frozen model already provides purpose-specific lifecycle columns; those are used instead.

| Column | Tables | Meaning |
| --- | --- | --- |
| `revoked_at` | `user_roles`, `company_members`, `selection_stage_assignments`, `application_documents`, `email_verification_tokens`, `password_reset_tokens`, `consents` | Assignment/grant ended. Row retained; drives the partial unique indexes in §9 |
| `archived_at` | `candidate_documents`, `vacancy_documents` | Excluded from active views; object retained |
| `disabled_at` | `users` | Account can no longer authenticate; all history intact |
| `anonymized_at` | `users`, `candidate_profiles` | Authorized retention treatment applied; distinguishes an anonymized record from a live one |
| `superseded_at` + `superseded_by_document_id` | `company_documents` | §14 — logical model 1.1-C3, INV-038 |
| `suspended_at` | `companies`, `vacancies` | State timestamp accompanying the status enum |
| `closed_at` | `vacancies` | Manual close time |
| **`deleted_at` — none** | — | **No table in the business schema receives a Laravel `deleted_at` column.** No requirement calls for generic soft delete, and every real case above is served by a specific, meaningful column |

**Immutable / history-oriented tables — no deletion field of any kind:** `application_status_histories`, `company_verification_reviews`, `vacancy_moderation_reviews`, `selection_schedule_histories`, `vacancy_versions`, `audit_logs`. These are append-only (INV-016). The Action layer exposes no update or delete path, and **revoking `UPDATE`/`DELETE` on these six tables at the database role level is recommended as defence in depth**.

> **Divergence resolved in logical revision 1.1-C3.** `company_documents.first_submitted_at`, `superseded_at`, and `superseded_by_document_id` were disclosed by the schema review as absent from logical model 1.1-C2. They are now part of the logical model and governed by **INV-038**. **The physical schema and the logical model agree; no divergence remains.**

---

## 9. Conditional Uniqueness — the Five P0 Rules

These are the rules that made PostgreSQL the engine choice (ADR-003). Each is a **partial unique index** — declarative, one line, and readable next to the invariant it implements. Full specifications in `POSTGRESQL_CONSTRAINTS.md` §2.

| Invariant | Table | Columns | `WHERE` predicate | Business meaning |
| --- | --- | --- | --- | --- |
| **INV-025** | `user_roles` | `(user_id, role_id)` | `revoked_at IS NULL` | At most one **active** role assignment; unlimited revoked history; re-issuance after revocation permitted |
| **INV-017** | `company_members` | `(company_id, user_id)` | `revoked_at IS NULL` | At most one **active** membership; revoked membership retained (FR-COMP-004) |
| **INV-031** | `offers` | `(application_id)` | `status = 'ACCEPTED'` | At most one **accepted** offer per application |
| **INV-036** | `smtp_configurations` | *(no columns — expression index)* | `is_active` | At most one **active** SMTP configuration; zero is valid |
| **INV-037** | `selection_stage_assignments` | `(recruitment_stage_id, selector_user_id)` | `revoked_at IS NULL` | At most one **active** selector assignment per stage |

**Why this could not be done in MySQL, restated as physical fact:** MySQL 8 has no partial indexes. Each of these five would require a generated column that is `NULL` when inactive, plus a unique index over it — encoding the business rule inside a column definition where no reader of `DATA_INVARIANTS.md` would find it.

Two further partial uniques of the same shape, from INV-022:

| Invariant | Table | Columns | Predicate |
| --- | --- | --- | --- |
| INV-022 | `recruitment_outcomes` | `(application_id)` | `application_id IS NOT NULL` |
| INV-022 | `recruitment_outcomes` | `(external_apply_event_id)` | `external_apply_event_id IS NOT NULL` |

---

## 10. Column Lengths

Business-neutral limits, generous enough not to encode a policy nobody approved.

| Kind | Type | Rationale |
| --- | --- | --- |
| Email | `varchar(255)` | Practical maximum for deliverable addresses |
| Person / company name | `varchar(255)` | `companies.name` is `varchar(200)` — FR-ONB-002 states 2–200 characters explicitly |
| URL | `varchar(2048)` | Browser-practical limit; covers `external_ats_url`, `credential_url`, `meeting_url`, `website`, `candidate_links.url` |
| Phone | `varchar(32)` | International format with separators |
| Code / slug | `varchar(64)` | `roles.code`, `vacancy_code`, `application_code`, master-data codes |
| `vacancies.slug` | `varchar(255)` | Public URL segment derived from title |
| Title / headline | `varchar(255)` | |
| Short note / label / reason category | `varchar(255)` | |
| Storage reference | `varchar(512)` | Object-storage key |
| Hash | `varchar(255)` | |
| Timezone | `varchar(64)` | IANA zone names |
| MIME type | `varchar(128)` | |
| Long text — `description`, `responsibilities`, `summary`, `comments`, `instructions`, `notes`, `reason`, `purpose`, `internal_note`, `recruiter_visible_note`, `rejection_reason`, `withdrawal_reason`, `last_error_summary`, `question_text`, `body_reference`, `address` | **`text`** | Arbitrary length is legitimately expected. **No invented character limit** |

`vacancies.minimum_education` and `experience_requirement` are `varchar(255)` display summaries — the structured requirements live in `vacancy_requirements`.

---

## 11. Money

`vacancies.salary_min` and `salary_max` are **`numeric(14,2)`**, nullable. `salary_currency` is `varchar(3)` (ISO 4217), nullable.

**Never `float` or `double`.** Binary floating point cannot represent decimal currency exactly, and a salary range that renders as `7999999.99` instead of `8000000.00` is a defect users will notice immediately.

**Salary policy remains OPEN (business question 3).** The schema supports mandatory, optional, or hidden without deciding: all three columns are nullable, no `CHECK` requires them, and no `NOT NULL` is added. A `CHECK (salary_max >= salary_min)` guard is applied only when both are present — that is arithmetic sanity, not a policy decision. `SALARY_REQUIRED` remains a reserved, unenforced error code.

---

## 12. DB-1 — `idempotency_keys` *(Operational Technical Requirement)*

Serves **both** surfaces. Business safety does not weaken because an action is reached through a session cookie.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | `bigint` identity | |
| `idempotency_key` | `varchar(255) NOT NULL` | Client-supplied |
| `actor_user_id` | `bigint NULL` FK → `users` `ON DELETE CASCADE` | Null only for unauthenticated idempotent operations |
| `actor_context` | `varchar(64) NOT NULL` | `VERSIONED_API` or `INERTIA_WEB`, plus session/token discriminator |
| `operation` | `varchar(191) NOT NULL` | Stable logical operation name — **never a controller class path** (INV-033 principle) |
| `request_fingerprint` | `varchar(64) NOT NULL` | Hash of the canonicalized request payload |
| `state` | `varchar(32) NOT NULL` | `PROCESSING`, `COMPLETED`, `FAILED` |
| `response_status` | `smallint NULL` | HTTP status of the retained response |
| `response_body` | `jsonb NULL` | Retained response — see §15 |
| `response_reference` | `varchar(512) NULL` | Pointer instead of inline body where the response is large |
| `created_at` | `timestamptz NOT NULL` | |
| `completed_at` | `timestamptz NULL` | |
| `expires_at` | `timestamptz NOT NULL` | |

**Uniqueness:** `UNIQUE (idempotency_key, operation, COALESCE(actor_user_id, 0))`.

**Concurrency — how double execution is prevented:** the row is inserted with `state = 'PROCESSING'` **before** the business transaction begins. The unique index makes a second concurrent request fail its insert; that request then reads the existing row and responds `409 IDEMPOTENT_REPLAY_IN_PROGRESS`. On completion the row moves to `COMPLETED` with the retained response.

| Situation | Result |
| --- | --- |
| Same key + operation + actor + **same** fingerprint, `COMPLETED` | Replay the retained response; header `Idempotency-Replayed: true` |
| Same key + operation + actor + **different** fingerprint | `409 IDEMPOTENCY_KEY_REUSED` |
| Same key, still `PROCESSING` | `409 IDEMPOTENT_REPLAY_IN_PROGRESS` |

**TTL:** `expires_at` defaults to 24 hours (contract minimum). A scheduled job deletes expired rows in bounded batches. **Idempotency rows are never kept forever** — this table is high-churn and must not grow unbounded.

---

## 13. DB-2 — `export_jobs` *(Operational Technical Requirement)*

| Column | Type | Notes |
| --- | --- | --- |
| `id` | `bigint` identity | |
| `requested_by_user_id` | `bigint NOT NULL` FK → `users` `ON DELETE RESTRICT` | The export was authorized as **this** actor; the reference must survive |
| `export_type` | `varchar(64) NOT NULL` | Report key |
| `parameters` | `jsonb NOT NULL` | Filter snapshot — see §15 |
| `authorized_scope` | `jsonb NOT NULL` | **The scope resolved at request time**, frozen with the job |
| `status` | `varchar(32) NOT NULL` | `PENDING`, `PROCESSING`, `COMPLETED`, `FAILED`, `EXPIRED` |
| `storage_reference` | `varchar(512) NULL` | Private object-storage key, never a URL |
| `row_count` | `integer NULL` | |
| `failure_summary` | `text NULL` | Sanitized — **never credentials, never SQL** |
| `requested_at` | `timestamptz NOT NULL` | |
| `started_at` | `timestamptz NULL` | |
| `completed_at` | `timestamptz NULL` | |
| `expires_at` | `timestamptz NULL` | |
| `downloaded_at` | `timestamptz NULL` | Last download |
| `download_count` | `integer NOT NULL DEFAULT 0` | Justified: FR-REP-005 and FR-AUD-001 require export access to be auditable, and a counter makes repeated retrieval of a candidate-data export visible without scanning the audit log |

**`authorized_scope` is the important column.** FR-REP-005 requires an export to contain only what the requester's role permits. Because the job runs asynchronously — possibly after the requester's membership or role has been revoked — **the scope is resolved and frozen at request time**, and the worker filters against the frozen scope rather than re-resolving live permissions. Delivery still goes through the Policy-checked, audited download path; expired jobs serve nothing.

---

## 14. Company Document Evidence

Physically supports the three-state rule from the frozen API contract.

| Additional column | Type | Purpose |
| --- | --- | --- |
| `first_submitted_at` | `timestamptz NULL` | Set the first time this document is included in a submitted verification package. **`NULL` means never submitted** |
| `superseded_at` | `timestamptz NULL` | Set when replaced |
| `superseded_by_document_id` | `bigint NULL` FK → `company_documents` `ON DELETE RESTRICT` | Points at the replacement |

| Document state | Physical test | Permitted |
| --- | --- | --- |
| Never submitted | `first_submitted_at IS NULL` | Destructive `DELETE` allowed |
| Ever submitted | `first_submitted_at IS NOT NULL` | **`DELETE` refused** → `409 COMPANY_DOCUMENT_IS_VERIFICATION_EVIDENCE`. Replacement via supersede |

The `superseded_by_document_id` self-reference with `RESTRICT` means a replacement can never be deleted while it is another document's successor, so the evidence chain cannot be broken from either end. **Any past verification decision stays reconstructable** (INV-016).

**Enforcement note:** `first_submitted_at IS NULL` is a same-row test, so a `DELETE` guard is enforceable in the Action with a row lock. A database `RULE`/trigger to block the delete is *possible* but rejected — see §16.

---

## 15. JSONB Usage — Individually Reviewed

**Eight columns total. Each justified separately; none is an escape hatch for unresolved modelling.**

| # | Column | Verdict | Why relational columns are not superior |
| --- | --- | --- | --- |
| 1 | `vacancy_versions.snapshot` | **JUSTIFIED** | §17 |
| 2 | `email_outbox.payload_reference` | **JUSTIFIED** | Template variables differ per template and are never queried — only rendered by the mail worker. Relational columns would require a table per template shape |
| 3 | `audit_logs.change_summary` | **JUSTIFIED** | Shape varies by audited object across 51 tables. Already redacted at write time (INV-035). Never filtered on — audit queries filter actor, action, object, time, correlation ID (§28) |
| 4 | `audit_logs.user_agent_device_metadata` | **JUSTIFIED** | Optional, policy-gated (H-4), never queried |
| 5 | `selection_schedule_histories.previous_snapshot` | **JUSTIFIED** | Prior values of a changed schedule, read only when displaying that history entry |
| 6 | `vacancy_screening_questions.options_definition` | **JUSTIFIED** | An ordered option list belonging to exactly one `SINGLE_CHOICE` question, never queried across questions. A child table would add a join for data always read with its parent |
| 7 | `idempotency_keys.response_body` | **JUSTIFIED** *(operational)* | An opaque retained HTTP response, replayed verbatim |
| 8 | `export_jobs.parameters` + `authorized_scope` | **JUSTIFIED** *(operational)* | A frozen filter and scope snapshot, replayed by the worker |

### Explicitly rejected as JSONB

| Candidate | Decision |
| --- | --- |
| `application_screening_answers.answer_value_reference` | **Relational, not JSONB.** Typed columns — `answer_text text`, `answer_boolean boolean`, `answer_number numeric(14,2)`, `answer_option varchar(255)` — selected by the question's `question_type`, with a `CHECK` that exactly one is populated. Answers **are** queried and exported (FR-REP-005), and validated against the question definition (INV-019). This is the same reasoning that removed the opaque `value_reference` from `vacancy_requirements` in revision 1.1-C1; applying JSONB here would reintroduce the defect that correction eliminated |
| `vacancy_requirements` values | Already relational in 1.1-C1 — typed `education_level`, `study_program_id`, `skill_id`, `minimum_years_experience`, `value_text`. **Not reverted** |
| Candidate profile collections | Real tables, per FR-CAN-003 |

**No JSONB column is GIN-indexed at MVP.** None is filtered on. `INDEX_STRATEGY.md` §7 explains what would justify one later.

---

## 16. Cross-Row Invariants — Enforcement Classification

Rules a same-row `CHECK` cannot express because they read another table.

| Invariant | Rule | Classification | Mechanism |
| --- | --- | --- | --- |
| **INV-002** | Company must be `VERIFIED` before company vacancy creation | **Transaction/service** | `SELECT … FOR UPDATE` on `companies` inside the creating transaction. A trigger was considered and rejected — it would hide the project's most important business gate inside the schema, where no reader of the Action would find it |
| **INV-024** | Application only for `application_method = 'IN_PORTAL'` | **Transaction/service + structural option** | Service check with `FOR UPDATE` on `vacancies`. A **composite-FK alternative** exists and is documented in `POSTGRESQL_CONSTRAINTS.md` §4: add a redundant `application_method` to `applications` with a composite FK to `vacancies (id, application_method)` and a `CHECK`. **Rejected** — it stores a derivable value, which 1.1-C1 deliberately removed elsewhere (decision D-4). Recorded so the option is a decision, not an oversight |
| **INV-005** | Campus vacancy must use `IN_PORTAL` | **Database CHECK** | Same-row: `ownership_type` and `application_method` are both on `vacancies` |
| **INV-023** | Consent receiver must match the vacancy's actual owner | **Both** | Presence/absence XOR is a same-row `CHECK`. The *match* half reads `vacancies` → service validation |
| **INV-037** | Selector must hold an active SELECTOR role | **Transaction/service** | Reads `user_roles`. Partial unique index handles the one-active-assignment half |
| **INV-019** | Application children belong to the same vacancy | **Transaction/service** | Enforced in the Action on **every** write path — background jobs and console commands included, not the request boundary alone |
| **INV-026** | Derived caches consistent with history | **Transaction/service** | Single transactional write path. A trigger would hide business logic in the schema and is rejected |
| **INV-028** | Eligibility from `candidate_verifications` only | **Transaction/service** | Business eligibility, deliberately not a Policy and not a constraint |
| **INV-030** | Company completeness before `PENDING_VERIFICATION` | **Transaction/service** | Requires counting `company_documents` |
| **INV-011** | Consent required before application submit | **Transaction/service** | Same-transaction validation across three tables |
| **INV-014** | Outcome never gates vacancy creation | **Enforced by absence** | No vacancy-creation path reads `recruitment_outcomes`. Covered by regression test, not a constraint |

**Trigger policy: no triggers in the MVP schema.** Every candidate above is either same-row (a `CHECK` is better) or needs authorization context a trigger does not have. The one place a trigger would materially help — blocking `UPDATE`/`DELETE` on the six append-only tables — is better served by **revoking those privileges from the application's database role**, which is simpler, visible in role configuration, and cannot be bypassed by a bug in trigger logic.

---

## 17. Vacancy Version Snapshot

`vacancy_versions.snapshot jsonb NOT NULL`.

**Why a snapshot rather than a parallel versioned table set:** `vacancies` has 33 columns plus four child collections (`vacancy_requirements`, `vacancy_screening_questions`, `vacancy_documents`, `recruitment_stages`). Duplicating that into `vacancy_versions_*` tables would mean **five extra tables that must be migrated in lockstep with the live schema forever** — and the moment they drift, historical versions become unreadable. FR-VAC-007 requires the before/after state to be *retrievable*, not *queryable*: a version is read when a reviewer opens one revision.

| Property | Specification |
| --- | --- |
| Uniqueness | `UNIQUE (vacancy_id, version_number)` |
| Immutability | Append-only (INV-016). No `UPDATE`/`DELETE` path; `UPDATE`/`DELETE` privilege recommended revoked |
| Content | Full logical snapshot: the vacancy row plus its requirements, screening questions, documents, and stages at that revision |
| Indexes | `(vacancy_id, version_number DESC)` for retrieval. **No GIN index on `snapshot`** — snapshot contents are never searched |
| Ordering | `version_number` is assigned inside the writing transaction with the vacancy row locked, so two concurrent edits cannot produce the same number |

---

## 18. Candidate Document Snapshot

`application_documents` preserves historical shared-document identity independently of the source document (INV-032).

| Column | Type | Null | Purpose |
| --- | --- | --- | --- |
| `application_id` | `bigint` | No | FK → `applications`, `RESTRICT` |
| `candidate_document_id` | `bigint` | No | FK → `candidate_documents`, **`RESTRICT`** — the source can never be hard-deleted while shared |
| `shared_at` | `timestamptz` | No | |
| `snapshot_name` | `varchar(255)` | **No** | Display name **as it stood at share time** |
| `snapshot_storage_reference` | `varchar(512)` | **No** | **Immutable/versioned object reference** — the file exactly as shared |
| `snapshot_checksum` | `varchar(255)` | Yes | Integrity value where the storage layer supplies one |
| `revoked_at` | `timestamptz` | Yes | Withdraws future access; **never deletes the record or the snapshot** |

**The two `NOT NULL` snapshot columns are the whole point.** With them nullable, the record could degrade to a bare pointer, and a candidate replacing their CV would silently change what a recruiter reviewed. `snapshot_storage_reference` must resolve to an immutable object (a versioned key or a copy) — pointing it at a mutable "latest" key would defeat the constraint while appearing to satisfy it.

Reads serve the **snapshot**, never the candidate's current file.

---

## 19. Consent

| Aspect | Physical |
| --- | --- |
| Actor | `user_id bigint NOT NULL` FK → `users`, `RESTRICT` |
| Application | `application_id bigint NULL` FK → `applications`, `RESTRICT` |
| Vacancy | `vacancy_id bigint NULL` FK → `vacancies`, `RESTRICT` |
| **Receiver XOR** | `receiving_company_id` / `receiving_organizational_unit_id`, both `bigint NULL` — `CHECK` allows at most one; the service requires exactly one for data-sharing consent and validates it against the vacancy's owner (INV-023) |
| Type / version | `consent_type varchar(64) NOT NULL`, `consent_version varchar(64) NOT NULL`, `consent_text_hash_reference varchar(255) NOT NULL` |
| Purpose | `purpose text NOT NULL` |
| Timestamps | `consented_at timestamptz NOT NULL`, `revoked_at timestamptz NULL`, `created_at timestamptz NOT NULL` |

**Consent is never reduced to a boolean on `applications`** (INV-011). No such column exists, and none may be added.

---

## 20. Offer Concurrency

| Layer | Mechanism |
| --- | --- |
| **Database (final protection)** | Partial unique index on `offers (application_id) WHERE status = 'ACCEPTED'` (INV-031) |
| **Row locking** | The accept Action locks the `offers` row **and** its `applications` row with `FOR UPDATE`, in that order consistently, so concurrent accepts on one application serialize without deadlock |
| **Authority** | `offer_accepted_at timestamptz` is set **once**, inside the transaction that sets `status = 'ACCEPTED'`. It is the sole Time-to-Fill endpoint (INV-013) |
| **Losing safely** | The second transaction blocks, then sees the changed status and returns `409 OFFER_ALREADY_RESPONDED` — or, with a matching `Idempotency-Key`, the retained response. Two accepts of *different* offers on one application: the loser hits the partial unique index and returns `409 OFFER_ALREADY_ACCEPTED_FOR_APPLICATION` |

**The database constraint is the final protection, not the application check.** If the service guard were ever bypassed — a background job, a console command, a future code path — the index still refuses the second accepted offer.

---

## 21. SMTP Configuration

| Column | Type | Notes |
| --- | --- | --- |
| `host` | `varchar(255) NOT NULL` | |
| `port` | `integer NOT NULL` | `CHECK (port BETWEEN 1 AND 65535)` |
| `encryption_mode` | `varchar(16) NOT NULL` | `NONE`, `STARTTLS`, `TLS` |
| `username` | `varchar(255) NULL` | Not a secret |
| **`encrypted_password`** | **`text NULL`** | **Ciphertext only.** Encrypted by the application before it reaches the database, with a key held **outside** the database (INV-035) |
| `from_address` | `varchar(255) NOT NULL` | |
| `timeout_seconds` | `integer NULL` | `CHECK (timeout_seconds BETWEEN 1 AND 600)` |
| `max_attempts` | `integer NOT NULL` | `CHECK (max_attempts BETWEEN 1 AND 20)` |
| `retry_backoff_seconds` | `integer NOT NULL` | `CHECK (retry_backoff_seconds BETWEEN 1 AND 86400)` |
| `is_active` | `boolean NOT NULL DEFAULT false` | Partial unique index (INV-036) |
| `last_test_result` | `varchar(16) NULL` | `NOT_TESTED`, `SUCCESS`, `FAILURE` |
| `last_tested_at` | `timestamptz NULL` | |

**Non-negotiable physical rules:**

- **`encrypted_password` is never indexed.** Not a B-tree, not a hash, not a functional index, not part of any composite. An index leaks ciphertext into a second structure and enables equality probing.
- **Never in `audit_logs`.** Audit records `credential_changed: true|false` only (INV-035).
- **Never in `email_outbox`** (INV-015).
- **The encryption algorithm is not defined in the schema.** The column is `text` holding ciphertext; the cipher, key derivation, and rotation are application concerns (ADR-015). Choosing `pgcrypto` would put the key in reach of the database and is explicitly rejected.
- **`text`, not `varchar(n)`** — ciphertext length varies with algorithm and must not be capped by a guess.

---

## 22. Selector Assignment

| Requirement | Physical |
| --- | --- |
| Historical revoked assignments | `revoked_at timestamptz NULL`, `revoked_by_user_id bigint NULL`. Rows retained; surrogate `id` permits re-issuance |
| One active per selector + stage | Partial unique index on `(recruitment_stage_id, selector_user_id) WHERE revoked_at IS NULL` (INV-037) |
| **Performant assigned-stage scoping** | Index on `(selector_user_id, recruitment_stage_id) WHERE revoked_at IS NULL` — **column order deliberately reversed** from the unique index. The uniqueness check probes by stage; the authorization query starts from *the logged-in selector* and finds their stages, so `selector_user_id` must lead. Both indexes exist because they serve different access paths |

The selector applicant-list query joins `selection_stage_assignments → recruitment_stages → vacancies → applications`, filtered on the **active** assignment. `INDEX_STRATEGY.md` §5 covers the join path.

---

## 23. Framework and Operational Tables

| Table | Layer | Included? | Rationale |
| --- | --- | --- | --- |
| `sessions` | Framework | **Yes** | Sessions in PostgreSQL (ADR-006) — a Redis eviction would log out every user simultaneously |
| `cache` | Framework | **Yes, unused at MVP** | Cache is Redis (ADR-006). Created as a fallback so a Redis outage has a documented degradation path |
| `cache_locks` | Framework | **Yes, unused at MVP** | Same reasoning; Redis is the lock store |
| `failed_jobs` | Framework | **Yes** | Laravel writes exhausted jobs here regardless of queue driver. Monitored (`DEPLOYMENT_ARCHITECTURE.md` §5) |
| `jobs` | Framework | **No** | Queue is Redis. A database queue table would add polling load to the primary |
| `job_batches` | Framework | **No** | No batch dispatch at MVP |
| `password_reset_tokens` (Laravel's) | Framework | **No — deliberately excluded** | The **business** table of the same name already exists (ADR-011) with `user_id`, `used_at`, and `revoked_at`, which Laravel's scaffold lacks. **The Laravel default must not be created; it would collide by name and silently satisfy neither INV-021 nor the frozen model** |
| `personal_access_tokens` | Framework | **Conditional** | Required only when Sanctum tokens are issued for the 57 `VERSIONED_API` endpoints. Included in the phase that activates that surface |
| `idempotency_keys` | Operational | **Yes** | DB-1, §12 |
| `export_jobs` | Operational | **Yes** | DB-2, §13 |

---

## 24. Transaction Scope and Locking

Every Action owns exactly one transaction. External calls — SMTP, object storage, HTTP — never occur inside one.

| Action | Transaction contents | Lock |
| --- | --- | --- |
| **Application submit** | `consents` + `applications` + `application_documents` + `application_screening_answers` + initial history + audit + outbox | `FOR UPDATE` on `vacancies` (status, method, window) |
| **Application reopen** | Application cache fields + `APPLICATION_REOPENED` history + audit + outbox | `FOR UPDATE` on `applications` |
| **Withdraw** | Status + `withdrawn_at` + history + audit + outbox | `FOR UPDATE` on `applications` |
| **Status / stage transition** | Cache fields + history + audit + outbox | `FOR UPDATE` on `applications` |
| **Company verification submit** | Status + review row + audit + outbox | `FOR UPDATE` on `companies` |
| **Company verification decision** | Status + review row + audit + outbox | `FOR UPDATE` on `companies` |
| **Vacancy submit / moderation** | Status + moderation review + version snapshot + audit + outbox | `FOR UPDATE` on `vacancies` |
| **Vacancy publish** | Status + `published_at` (written once) + audit + outbox | `FOR UPDATE` on `vacancies` |
| **Schedule reschedule** | Schedule values + `revision_number` + history + audit + outbox | `FOR UPDATE` on `selection_schedules` |
| **Offer send** | Status + `sent_at` + audit + outbox | `FOR UPDATE` on `offers` |
| **Offer accept / reject** | Offer status + `offer_accepted_at` + application `HIRED` + `hired_at` + history + audit + outbox | `FOR UPDATE` on `offers`, then `applications` — **consistent order** |
| **Recruitment outcome** | Outcome row + audit | `FOR UPDATE` on the source row |
| **SMTP update** | Configuration row + deactivate previous + audit | `FOR UPDATE` on `smtp_configurations` |
| **Role / membership / selector assignment** | Revoke existing active + insert new + audit | `FOR UPDATE` on the parent |
| **Candidate collection sync** | Creates + updates + deletes for one collection | `FOR UPDATE` on `candidate_profiles` |

**No global or table-level locking.** All locks are row-level, acquired in a consistent order, and held only for the transaction.

**Advisory locks:** justified in exactly one place — the **scheduler**, where `pg_try_advisory_lock` is an acceptable alternative to the Redis lock store for guaranteeing a single publish/expiry sweep. Not used anywhere in request handling.

**Isolation:** PostgreSQL default `READ COMMITTED` throughout. Every gate is protected by an explicit row lock plus a database constraint, so `SERIALIZABLE` — with its retry burden on every transaction — is not required.

---

## 25. Naming Conventions

| Object | Convention | Example |
| --- | --- | --- |
| Table | `snake_case`, plural | `application_status_histories` |
| Column | `snake_case`, singular | `offer_accepted_at` |
| Primary key | `id` | |
| Foreign key | `<target_singular>_id`, or the role-bearing name fixed by the dictionary | `vacancy_id`, `reviewer_user_id` |
| Primary key constraint | `pk_<table>` | `pk_applications` |
| Foreign key constraint | `fk_<table>_<column>` | `fk_applications_vacancy_id` |
| Unique constraint/index | `uq_<table>_<columns>` | `uq_applications_candidate_vacancy` |
| **Partial unique index** | `uq_<table>_<columns>_active` | `uq_user_roles_user_role_active` |
| Check constraint | `chk_<table>_<subject>` | `chk_vacancies_ownership_xor` |
| Plain index | `idx_<table>_<columns>` | `idx_vacancies_status_published_at` |
| Boolean | affirmative, no `is_` prefix except where the dictionary fixes it | `active`, `required`, `is_current`, `is_active` |
| Timestamp | `<verb>_at` | `published_at`, `revoked_at` |
| Status column | `status`, or `<subject>_status` | `verification_status`, `current_status` |

**Every constraint and index is explicitly named.** Relying on PostgreSQL's auto-generated names makes a migration's `down()` guesswork.

**Where Laravel convention and the frozen model conflict, the frozen model wins.** Laravel would infer `user_id`; the dictionary says `reviewer_user_id`, `assigned_by`, `confirmed_by`. Those stand, with the relationship's foreign key declared explicitly in the eventual model rather than renamed to suit inference.

---

## 26. Open Questions — Preserved

The schema supports every possible answer and decides none.

| # | Question | Physical posture |
| --- | --- | --- |
| 1 | Alumni verification source | `candidate_verifications.student_number`, `program_study_id`, `graduation_year` all **nullable**; `source_reference` untyped. No integration table |
| 2 | Minimum legal documents per organization type | `company_documents.document_type` is `varchar` with **no** approved `CHECK` vocabulary and no per-type requirement matrix |
| 3 | Salary policy | All three salary columns nullable; no `NOT NULL`, no required-`CHECK` (§11) |
| 4 | Recruiter domain / subdomain | No schema impact — cookie scope and CORS only |
| 5 | WhatsApp phase | No channel, provider, template, or delivery table. Phone columns are contact data (FR-CAN-003) |
| 6 | First recruiter default role / minimum Company Admin | **D-1 CLOSED:** first creator is active `COMPANY_ADMIN`; runtime last-admin protection enforces at least one active admin. The schema intentionally has no implicit default for subsequent members and no migration is required |
| **H-2** | Vacancy-level outcome | `recruitment_outcomes` remains candidate-level via its source XOR. **No vacancy-level column added** |
| **H-3** | Candidate revocation of a shared document | `application_documents.revoked_at` exists and is honoured on read; no candidate-facing path |
| **H-4** | Audit IP / device collection | `ip_address inet NULL` and `user_agent_device_metadata jsonb NULL` — both optional, collection configurable |

---

## 27. Related Documents

| Document | Covers |
| --- | --- |
| `POSTGRESQL_CONSTRAINTS.md` | Every partial unique index, `CHECK`, and foreign key with its delete policy, stated precisely enough to implement directly |
| `INDEX_STRATEGY.md` | Index classification, the twelve named workloads, composite column ordering, and the public search recommendation |
| `MIGRATION_PLAN.md` | Dependency-safe phase order and rollback safety expectations |
| `SCHEMA_VALIDATION_REPORT.md` | The 23-point validation and the single disclosed divergence |
