# PostgreSQL Constraint Specification — Portal Karir Kampus

**Status:** Proposed — awaiting approval
**Date:** 24 August 2026 · **Engine:** PostgreSQL 16+ · **Logical source:** revision **1.1-C3** · **Companion to:** `DATABASE_SCHEMA.md`

Constraint-level detail, stated precisely enough to implement directly. **No executable SQL or migration file is created here** — the expressions below are specifications, written in SQL-shaped notation because ambiguity at this level is what produces defects.

---

## 1. Constraint Philosophy

| Principle | Applied as |
| --- | --- |
| **The database is the backstop, not the only guard** | Every rule expressible in a same-row `CHECK` or a unique index gets one, *in addition to* the Action-layer guard. If service logic is ever bypassed — a job, a console command, a future code path — the constraint still refuses |
| **Same-row rules → database. Cross-row rules → service** | A `CHECK` cannot read another table. Rules that must are listed in §4 with their enforcement class |
| **No triggers in the MVP schema** | §5 |
| **Every constraint is explicitly named** | An unnamed constraint cannot be dropped deterministically in a migration `down()` |
| **`RESTRICT` by default** | Recruitment history is evidence. Five `CASCADE` exceptions total, all listed in §3 |

---

## 2. Partial Unique Indexes — The Five P0 Conditional Uniqueness Rules

These are why PostgreSQL was chosen (ADR-003). Each is one declarative line beside the invariant it implements.

### 2.1 INV-025 — one active role assignment

```
uq_user_roles_user_role_active
  UNIQUE (user_id, role_id)
  WHERE revoked_at IS NULL
```

**Business meaning:** a user holds a role once, or not at all. Unlimited revoked assignments may coexist, and a revoked role may be re-issued.
**Race prevented:** two administrators granting the same role simultaneously → one row, not two, so revoking "the" assignment cannot leave a hidden duplicate active.
**Why the surrogate key exists:** `(user_id, role_id)` cannot be the primary key — history plus re-issuance requires repeated pairs.
**Laravel on violation:** `QueryException`, SQLSTATE `23505`. The Action catches it and returns `409 CONFLICT`. It must **not** be surfaced as a 500.
**Supports:** `FINAL_YEAR_STUDENT → ALUMNI` transition without losing role history.

### 2.2 INV-017 — one active company membership

```
uq_company_members_company_user_active
  UNIQUE (company_id, user_id)
  WHERE revoked_at IS NULL
```

**Business meaning:** one active membership per user per company. Revoked memberships are retained — FR-COMP-004 requires that removal not destroy audit/history.
**Race prevented:** two Company Admins inviting the same user concurrently.
**Laravel on violation:** `23505` → `409 MEMBER_ALREADY_ACTIVE`.
**Authorization dependency:** `COMPANY_SCOPE` resolves through *active* membership. A duplicate active row would make revocation unreliable, which is a privilege-escalation path.

### 2.3 INV-031 — one accepted offer per application

```
uq_offers_application_accepted
  UNIQUE (application_id)
  WHERE status = 'ACCEPTED'
```

**Business meaning:** an application cannot have two accepted offers. `offers.offer_accepted_at` on that single row is the authoritative Time-to-Fill endpoint (INV-013).
**Race prevented:** a candidate accepting two offers on one application in the same instant — the **final** protection behind the row locks in §6.
**Laravel on violation:** `23505` → `409 OFFER_ALREADY_ACCEPTED_FOR_APPLICATION`.
**Note:** a *vacancy* may legitimately have several accepted offers when `openings_count > 1`; this index is scoped to the application, which is correct.

### 2.4 INV-036 — one active SMTP configuration

```
uq_smtp_configurations_active
  UNIQUE ((true))
  WHERE is_active
```

**Business meaning:** at most one active configuration. **Zero is valid** — delivery then falls back to deployment configuration.
**Expression form:** a constant-expression unique index over the filtered set permits exactly one qualifying row. Equivalent formulation: `UNIQUE (is_active) WHERE is_active`.
**Race prevented:** two Super Admins activating different configurations concurrently.
**Laravel on violation:** `23505` → `409 CONFLICT`. The activation Action deactivates the previous row **in the same transaction**, so the index fires only on a genuine race.

### 2.5 INV-037 — one active selector assignment per stage

```
uq_selection_stage_assignments_stage_selector_active
  UNIQUE (recruitment_stage_id, selector_user_id)
  WHERE revoked_at IS NULL
```

**Business meaning:** a selector is assigned to a stage once. Revoked assignments are retained and may be re-issued.
**Race prevented:** duplicate active assignments, which would make revocation unreliable — and revocation is what removes a selector's access to candidate data.
**Laravel on violation:** `23505` → `409 SELECTOR_ASSIGNMENT_ALREADY_ACTIVE`.

### 2.6 INV-022 — one outcome per source

```
uq_recruitment_outcomes_application
  UNIQUE (application_id)          WHERE application_id IS NOT NULL

uq_recruitment_outcomes_external_event
  UNIQUE (external_apply_event_id) WHERE external_apply_event_id IS NOT NULL
```

**Laravel on violation:** `23505` → `409 OUTCOME_ALREADY_RECORDED`.

---

## 3. Unconditional Unique Constraints

| Name | Table | Columns | Invariant / note |
| --- | --- | --- | --- |
| `uq_users_email_normalized` | `users` | `(email_normalized)` | **INV-001 — the sole email uniqueness rule.** §7 |
| **none on `users.email`** | | | **Deliberate.** A second constraint would create competing uniqueness (INV-001) |
| `uq_applications_candidate_vacancy` | `applications` | `(candidate_profile_id, vacancy_id)` | **INV-007.** §8 |
| `uq_applications_application_code` | `applications` | `(application_code)` | |
| `uq_vacancies_vacancy_code` | `vacancies` | `(vacancy_code)` | |
| `uq_vacancies_slug` | `vacancies` | `(slug)` | Public URL identifier |
| `uq_vacancy_versions_vacancy_version` | `vacancy_versions` | `(vacancy_id, version_number)` | FR-VAC-007 |
| `uq_application_screening_answers_app_question` | `application_screening_answers` | `(application_id, screening_question_id)` | One answer per question |
| `uq_candidate_skills_profile_skill` | `candidate_skills` | `(candidate_profile_id, skill_id)` | |
| `uq_candidate_saved_vacancies_profile_vacancy` | `candidate_saved_vacancies` | `(candidate_profile_id, vacancy_id)` | |
| `uq_candidate_links_profile_url` | `candidate_links` | `(candidate_profile_id, url)` | |
| `uq_candidate_profiles_user` | `candidate_profiles` | `(user_id)` | At most one profile per user |
| `uq_password_credentials_user` | `password_credentials` | `(user_id)` | One current credential |
| `uq_roles_code` | `roles` | `(code)` | |
| `uq_organizational_units_code` | `organizational_units` | `(code)` | |
| `uq_study_programs_code` | `study_programs` | `(code)` | |
| `uq_industries_code` | `industries` | `(code)` | |
| `uq_organization_types_code` | `organization_types` | `(code)` | |
| `uq_skills_normalized_name` | `skills` | `(normalized_name)` | |
| `uq_geographic_areas_code` | `geographic_areas` | `(code)` `WHERE code IS NOT NULL` | Dictionary: "unique when source provides it" |
| `uq_email_verification_tokens_hash` | `email_verification_tokens` | `(token_hash)` | |
| `uq_password_reset_tokens_hash` | `password_reset_tokens` | `(token_hash)` | |
| `uq_idempotency_keys_scope` | `idempotency_keys` | `(idempotency_key, operation, COALESCE(actor_user_id, 0))` | Operational — DB-1 |

> **`companies.normalized_name` carries NO unique constraint** (INV-034). FR-COMP-001 requires duplicate *detection and review*, not hard rejection. See `INDEX_STRATEGY.md` §6.

---

## 4. CHECK Constraints

### 4.1 The three core XOR rules

#### INV-018 — vacancy ownership exclusivity

```
chk_vacancies_ownership_xor CHECK (
  (ownership_type = 'COMPANY'
     AND company_id IS NOT NULL
     AND organizational_unit_id IS NULL)
  OR
  (ownership_type = 'CAMPUS'
     AND organizational_unit_id IS NOT NULL
     AND company_id IS NULL)
)
```

Exactly one owner on every row: never both, never neither.

```
chk_vacancies_campus_in_portal CHECK (
  ownership_type <> 'CAMPUS' OR application_method = 'IN_PORTAL'
)
```

**INV-005** — campus recruitment can never use External ATS (FR-HR-003).

```
chk_vacancies_campus_status CHECK (
  ownership_type <> 'CAMPUS'
  OR current_status NOT IN ('PENDING_REVIEW','REVISION_REQUIRED','APPROVED','REJECTED')
)
```

**INV-018** — campus vacancies are never moderated, so moderation-only statuses are unreachable.

```
chk_vacancies_external_url CHECK (
  application_method <> 'EXTERNAL_ATS' OR external_ats_url IS NOT NULL
)

chk_vacancies_close_after_open CHECK (
  open_at IS NULL OR close_at IS NULL OR close_at > open_at
)

chk_vacancies_salary_range CHECK (
  salary_min IS NULL OR salary_max IS NULL OR salary_max >= salary_min
)

chk_vacancies_openings_positive CHECK (openings_count >= 1)
```

The salary guard is **arithmetic sanity only** — it fires just when both values are present and decides nothing about business question 3.

#### INV-022 — recruitment outcome source exclusivity

```
chk_recruitment_outcomes_source_xor CHECK (
  (source_type = 'INTERNAL_APPLICATION'
     AND application_id IS NOT NULL
     AND external_apply_event_id IS NULL)
  OR
  (source_type = 'EXTERNAL_APPLY'
     AND external_apply_event_id IS NOT NULL
     AND application_id IS NULL)
)
```

Both-present and both-absent are unrepresentable. **No `vacancy_id` or `candidate_profile_id` column exists on this table** — both were deliberately removed in revision 1.1-C1 (decision D-4) because they are reachable through the single populated source reference, and a stored copy could contradict its own parent. **They must not be re-added.**

#### INV-023 — consent receiver exclusivity

```
chk_consents_receiver_xor CHECK (
  NOT (receiving_company_id IS NOT NULL
       AND receiving_organizational_unit_id IS NOT NULL)
)
```

**Both-present is structurally impossible.** The *exactly one* half for data-sharing consent, and the requirement that the receiver equal the vacancy's actual owner, both read `vacancies` — service-enforced (§5).

### 4.2 Value-set constraints

One `chk_<table>_<column>` per enum listed in `DATABASE_SCHEMA.md` §5. Two carry non-negotiable business meaning:

```
chk_vacancies_current_status CHECK (current_status IN (
  'DRAFT','PENDING_REVIEW','REVISION_REQUIRED','APPROVED','SCHEDULED',
  'PUBLISHED','REJECTED','CLOSED','EXPIRED','SUSPENDED'))
```
**INV-004** — `SUBMITTED` and `DIAJUKAN` are absent, so they cannot be stored even by a direct write.

```
chk_vacancies_target_audience CHECK (target_audience IN (
  'PUBLIC','ALUMNI_ONLY','FINAL_YEAR_AND_ALUMNI','INTERNAL'))
```
**INV-006** — exactly four.

```
chk_selection_schedules_status CHECK (status IN (
  'SCHEDULED','COMPLETED','CANCELLED','NO_SHOW'))
```
**INV-027** — `RESCHEDULED` is a history event, never a current status.

### 4.3 Other same-row guards

```
chk_offers_accepted_at CHECK (status <> 'ACCEPTED' OR offer_accepted_at IS NOT NULL)
chk_applications_reopen_count CHECK (reopen_count >= 0)
chk_applications_withdrawn CHECK (current_status <> 'WITHDRAWN' OR withdrawn_at IS NOT NULL)
chk_company_documents_expiry CHECK (issued_at IS NULL OR expires_at IS NULL OR expires_at >= issued_at)
chk_candidate_certifications_expiry CHECK (issued_at IS NULL OR expires_at IS NULL OR expires_at >= issued_at)
chk_candidate_work_experiences_current CHECK (NOT is_current OR end_date IS NULL)
chk_candidate_organizations_current CHECK (NOT is_current OR end_date IS NULL)
chk_selection_schedules_time CHECK (ends_at IS NULL OR ends_at > starts_at)
chk_selection_schedules_revision CHECK (revision_number >= 0)
chk_smtp_configurations_port CHECK (port BETWEEN 1 AND 65535)
chk_smtp_configurations_attempts CHECK (max_attempts BETWEEN 1 AND 20)
chk_smtp_configurations_backoff CHECK (retry_backoff_seconds BETWEEN 1 AND 86400)
chk_smtp_configurations_timeout CHECK (timeout_seconds IS NULL OR timeout_seconds BETWEEN 1 AND 600)
chk_email_outbox_attempts CHECK (attempt_count >= 0)
chk_company_documents_supersede CHECK (
  (superseded_at IS NULL     AND superseded_by_document_id IS NULL)
  OR
  (superseded_at IS NOT NULL AND superseded_by_document_id IS NOT NULL)
)                                    -- INV-038 rule C: the two move together

chk_company_documents_no_self_supersede CHECK (
  superseded_by_document_id IS NULL OR superseded_by_document_id <> id
)                                    -- INV-038 rule E
```

**`vacancy_requirements` typed-value guard** — exactly one qualifying value per `requirement_type`:

```
chk_vacancy_requirements_typed_value CHECK (
  (requirement_type = 'EDUCATION'            AND education_level IS NOT NULL)
  OR (requirement_type = 'STUDY_PROGRAM'     AND study_program_id IS NOT NULL)
  OR (requirement_type = 'SKILL'             AND skill_id IS NOT NULL)
  OR (requirement_type = 'EXPERIENCE'        AND minimum_years_experience IS NOT NULL)
  OR (requirement_type IN ('CERTIFICATION','OTHER_QUALIFICATION') AND value_text IS NOT NULL)
)
```

**`application_screening_answers` typed-answer guard** — exactly one populated answer column:

```
chk_application_screening_answers_one_value CHECK (
  (answer_text IS NOT NULL)::int
+ (answer_boolean IS NOT NULL)::int
+ (answer_number IS NOT NULL)::int
+ (answer_option IS NOT NULL)::int = 1
)
```

Matching the answer's type to its question's `question_type` reads `vacancy_screening_questions` → service-enforced (§5).

---

## 5. Cross-Row Invariants — Not Enforceable by CHECK

| Invariant | Rule | Class | Mechanism |
| --- | --- | --- | --- |
| **INV-002** | Company `VERIFIED` before company vacancy creation | Service | `SELECT … FOR UPDATE` on `companies` in the creating transaction |
| **INV-024** | Application only where vacancy is `IN_PORTAL` | Service (+ structural option, §5.1) | `FOR UPDATE` on `vacancies`, checked in the submit transaction |
| **INV-023** (match half) | Consent receiver equals the vacancy's owner | Service | Reads `vacancies` in the submit transaction |
| **INV-019** | Application children belong to the same vacancy | Service | Checked in the Action on **every** write path, not the request boundary alone |
| **INV-026** | Derived caches consistent with history | Service | Single transactional write path |
| **INV-028** | Eligibility from `candidate_verifications` only | Service | Business eligibility, deliberately not a constraint |
| **INV-030** | Company completeness before `PENDING_VERIFICATION` | Service | Counts `company_documents` |
| **INV-037** (role half) | Selector must hold an active SELECTOR role | Service | Reads `user_roles` |
| **INV-011** | Consent required before submit | Service | Same-transaction validation |
| **INV-014** | Outcome never gates vacancy creation | **Absence** | No creation path reads `recruitment_outcomes`; regression test, not constraint |
| **Screening answer type match** | Answer type matches question type | Service | Reads `vacancy_screening_questions` |
| **INV-038** — company document delete guard | Submitted evidence not destructively deletable | Service (+ same-row CHECK) | `first_submitted_at IS NULL` checked with `FOR UPDATE` on `companies`. Rules **D** (same company) and **F** (no cycle) read the successor row → service-enforced in the supersede transaction |

### 5.1 The INV-024 structural alternative — considered and rejected

A composite foreign key would make INV-024 database-enforceable:

```
-- add: applications.application_method varchar(16) NOT NULL
-- add: UNIQUE (id, application_method) on vacancies
-- add: FK applications (vacancy_id, application_method)
--        REFERENCES vacancies (id, application_method)
-- add: CHECK (application_method = 'IN_PORTAL')
```

**Rejected.** It stores a derived value on `applications`, which revision 1.1-C1 explicitly removed elsewhere (decision D-4, `recruitment_outcomes`), and it would make a vacancy's `application_method` immutable once any application exists — the frozen contract already forbids that change via `409 VACANCY_HAS_APPLICATIONS`, at the Action layer where the error is explainable.

**Recorded so this is a decision, not an oversight.** If a future review prefers structural enforcement, this is the exact shape.

### 5.2 Trigger policy

**No triggers in the MVP schema.** Every cross-row rule above needs authorization or actor context a trigger does not have, and a trigger enforcing INV-002 would hide the project's most important business gate inside the schema where no reader of the Action would find it.

The one case with real merit — blocking `UPDATE`/`DELETE` on append-only tables — is better served by **privilege revocation**:

```
REVOKE UPDATE, DELETE ON
  application_status_histories,
  company_verification_reviews,
  vacancy_moderation_reviews,
  selection_schedule_histories,
  vacancy_versions,
  audit_logs
FROM <application_role>;
```

Simpler than a trigger, visible in role configuration, and impossible to bypass with a logic bug (INV-016).

---

## 6. Foreign Keys and Delete Policy

`ON UPDATE NO ACTION` throughout — identity primary keys never change.

### 6.1 `CASCADE` — the complete list (five)

| Constraint | Justification |
| --- | --- |
| `fk_password_credentials_user_id` | Credential is meaningless without its user; holds no recruitment evidence |
| `fk_email_verification_tokens_user_id` | Same |
| `fk_password_reset_tokens_user_id` | Same |
| `fk_evaluation_items_evaluation_id` | Physically part of its parent evaluation |
| `fk_candidate_saved_vacancies_candidate_profile_id` | A bookmark |

**Nothing else cascades anywhere in the schema.**

### 6.2 `SET NULL` — nullable actor references only

`application_status_histories.actor_user_id` · `selection_schedule_histories.actor_user_id` · `audit_logs.actor_user_id` · `user_roles.assigned_by` · `user_roles.revoked_by` · `company_members.invited_by` · `selection_stage_assignments.revoked_by_user_id` · `selection_schedules.pic_user_id` · `candidate_verifications.verified_by` · `external_apply_events.confirmed_by` · `recruitment_outcomes.confirmed_by`

**The event survives the actor.** A history row must never disappear because an account was later anonymized by an authorized retention process.

**Deliberately `RESTRICT`, not `SET NULL`:** `company_verification_reviews.reviewer_user_id`, `vacancy_moderation_reviews.reviewer_user_id`, `evaluations.evaluator_user_id`, `offers.offered_by_user_id`, `selection_stage_assignments.assigned_by_user_id`, `smtp_configurations.updated_by_user_id`, `export_jobs.requested_by_user_id`. **A decision must remain attributable.** "Who verified this company" and "who issued this offer" are not optional facts.

### 6.3 `RESTRICT` — everything else

All remaining foreign keys: `users` with recruitment history · `companies` with any dependent · `vacancies` with any dependent · `applications` with any child · `candidate_profiles` with applications · `candidate_documents` referenced by a live share (**INV-032**) · every master-data reference · `roles` referenced by any assignment including revoked · `company_documents.superseded_by_document_id` · `recruitment_stages` referenced by applications, schedules, evaluations, or assignments.

**Every foreign key in the schema has an explicit delete policy. None is left to default.**

---

## 7. Email Uniqueness — `citext` versus Application Normalization

**Recommendation: application-layer normalization into `email_normalized varchar(255)`, with a plain unique index. `citext` is NOT adopted.**

| Option | Assessment |
| --- | --- |
| **Application normalization — RECOMMENDED** | The frozen model **already has** `email_normalized` as a distinct column (INV-001), separate from the display `email`. Normalization is lowercasing plus any agreed trimming, applied before validation and before insert. Requires no extension, works identically in every environment, and the normalized value is inspectable — which matters when diagnosing a duplicate-account report |
| `citext` extension | Rejected. It would make `users.email` itself case-insensitively unique, creating **exactly the competing uniqueness INV-001 forbids** — two rules, on two columns, for one business fact. It also requires an extension in every environment, and `citext` comparison semantics are collation-dependent in ways that surprise people |

**One authoritative rule:** `uq_users_email_normalized`. **No unique constraint on `users.email` exists or may be added.**

Laravel implication: `unique:users,email` validation would target the **wrong column**. Validation must run against `email_normalized` after normalization (`SECURITY_ARCHITECTURE.md` L-8).

---

## 8. Application Uniqueness and Concurrent Submission

```
uq_applications_candidate_vacancy
  UNIQUE (candidate_profile_id, vacancy_id)
```

**Unconditional. Not partial, not filtered.** It covers the **full lifecycle** (INV-007, decision D-1): a terminal `WITHDRAWN` or `REJECTED` application still occupies the pair, so reapplying is `reopen`, never a second row.

### Concurrent duplicate submission — exactly what happens

1. Both requests pass the pre-check (neither sees the other's uncommitted row — `READ COMMITTED`).
2. Both proceed into their transactions; both lock `vacancies` for the status/method/window checks.
3. The first commits its `applications` insert.
4. The second's insert **blocks** on the unique index, then fails with `23505` on the first's commit.
5. The Action catches `23505` on `uq_applications_candidate_vacancy` and returns **`409 APPLICATION_ALREADY_EXISTS`** with `details.application_id`, so the UI routes to *Lihat Status Lamaran* (FR-APP-002).
6. With a matching `Idempotency-Key`, the retained response is replayed instead.

**One request wins; the other resolves deterministically. The pre-check is a courtesy for a better message — the index is the guarantee.** Relying on the pre-check alone would produce duplicate lifecycles under concurrency, which no later cleanup could safely merge.

### Reopen

Reopen **updates the existing row** — sets status and stage, increments `reopen_count`, sets `last_reopened_at`, appends an `APPLICATION_REOPENED` history event (INV-008, INV-026). **No schema path permits a second lifecycle row**: the unique index has no predicate to escape through, and no Action inserts into `applications` outside submit. History tables remain append-only.

---

## 9. Laravel Handling of Constraint Violations

| SQLSTATE | Meaning | Handling |
| --- | --- | --- |
| `23505` unique violation | A race lost | Catch **by constraint name** and map to the specific error code (§2, §8). **Never** a generic 500 |
| `23514` check violation | A same-row invariant was violated | **A defect, not a user error.** The Action should have caught it. Log with correlation ID, return `422 VALIDATION_FAILED`, and treat as a bug |
| `23503` FK violation | `RESTRICT` refused a delete, or a reference is missing | Map to `409 CONFLICT` or `422`, per the operation |
| `40001` serialization failure | Not expected under `READ COMMITTED` with explicit locks | Retry once, then surface `409` |

**Catch by constraint name, never by message text.** Message text varies with PostgreSQL version and locale; names are stable because §25 of `DATABASE_SCHEMA.md` makes every one explicit.

---

## 10. Extensions

| Extension | Status | Rationale |
| --- | --- | --- |
| `pg_trgm` | **OPTIONAL** | Only if company duplicate-detection similarity search (FR-COMP-001) proves inadequate with the indexes in `INDEX_STRATEGY.md` §6. Not required at MVP |
| `citext` | **NOT ADOPTED** | §7 |
| `pgcrypto` | **NOT ADOPTED** | Encryption is application-side with a key held outside the database (INV-035, ADR-015). Database-side encryption would put the key within reach of the data it protects |
| `uuid-ossp` | **NOT ADOPTED** | Identifiers are `bigint` (`DATABASE_SCHEMA.md` §2) |
| `unaccent` | **OPTIONAL** | Only alongside full-text search if Indonesian diacritic folding proves necessary |

**No extension is required for MVP.** Every optional one is marked as such so an environment without superuser rights is not blocked.
