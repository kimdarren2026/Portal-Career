# Migration Plan — Portal Karir Kampus

**Status:** Proposed — awaiting approval
**Date:** 24 August 2026 · **Engine:** PostgreSQL 16+ · **Framework:** Laravel
**Logical source:** revision **1.1-C3** · **Companion to:** `DATABASE_SCHEMA.md` · `POSTGRESQL_CONSTRAINTS.md` · `INDEX_STRATEGY.md`

Dependency-safe ordering and rollback expectations. **No migration file is created by this document.**

---

## 1. Ordering Principle

**No migration may reference a table that does not yet exist.** The 57 tables are grouped into seven phases; within a phase, tables may be created in any order **except** where a self-reference or intra-phase foreign key demands sequence, which is called out.

Two structural facts drive the order:

- **`vacancies` is the graph's hinge.** It depends on `companies`, `organizational_units`, and `geographic_areas`, and almost the entire recruitment domain depends on it. It must land before Phase 4 and after Phase 2.
- **Three self-referencing tables** — `organizational_units.parent_unit_id`, `geographic_areas.parent_geographic_area_id`, `company_documents.superseded_by_document_id` — create the table first, then add the self-FK in the same migration after creation.

---

## 2. Phase Order

### Phase 1 — Master data and identity foundation (10 tables)

No outbound business dependencies. Everything else builds on these.

| Order | Table | Note |
| --- | --- | --- |
| 1 | `geographic_areas` | Self-FK added after creation |
| 2 | `organizational_units` | Self-FK added after creation |
| 3 | `industries` | |
| 4 | `organization_types` | |
| 5 | `skills` | |
| 6 | `study_programs` | → `organizational_units` |
| 7 | `roles` | |
| 8 | `users` | No business FK outbound |
| 9 | `password_credentials` | → `users` (CASCADE) |
| 10 | `email_verification_tokens`, `password_reset_tokens` | → `users` (CASCADE) |

**`user_roles` is deliberately deferred to Phase 7** — it needs `assigned_by`/`revoked_by` → `users`, which exists here, but its partial unique index is grouped with the other conditional-uniqueness indexes so all five are reviewed together.

> **Do not create Laravel's default `password_reset_tokens`.** The business table of that name is created here with `user_id`, `used_at`, and `revoked_at` (ADR-011, INV-021). A framework scaffold would collide by name and satisfy neither.

### Phase 2 — Candidate and company (13 tables)

| Order | Table | Depends on |
| --- | --- | --- |
| 1 | `candidate_profiles` | `users`, `geographic_areas` |
| 2 | `candidate_verifications` | `candidate_profiles`, `study_programs`, `users` |
| 3 | `candidate_documents` | `candidate_profiles` |
| 4 | `candidate_educations` | `candidate_profiles`, `study_programs` |
| 5 | `candidate_work_experiences`, `candidate_organizations` | `candidate_profiles` |
| 6 | `candidate_skills` | `candidate_profiles`, `skills` |
| 7 | `candidate_certifications` | `candidate_profiles`, **`candidate_documents`** — must follow step 3 |
| 8 | `candidate_links` | `candidate_profiles` |
| 9 | `companies` | `organization_types`, `industries`, `geographic_areas`, `users` |
| 10 | `company_members` | `companies`, `users` |
| 11 | `company_documents` | `companies`; self-FK `superseded_by_document_id` added after creation (INV-038) |
| 12 | `company_verification_reviews` | `companies`, `users` |
| 13 | `partnerships` | `companies` |

### Phase 3 — Vacancy (7 tables)

| Order | Table | Depends on |
| --- | --- | --- |
| 1 | **`vacancies`** | `companies`, `organizational_units`, `geographic_areas`, `users` |
| 2 | `vacancy_versions` | `vacancies`, `users` |
| 3 | `vacancy_requirements` | `vacancies`, `study_programs`, `skills` |
| 4 | `vacancy_documents` | `vacancies`, `users` |
| 5 | `vacancy_screening_questions` | `vacancies` |
| 6 | `vacancy_moderation_reviews` | `vacancies`, `users` |
| 7 | `recruitment_stages` | `vacancies` |
| 8 | `candidate_saved_vacancies` | `candidate_profiles`, `vacancies` |

### Phase 4 — Recruitment (11 tables)

**Ordering constraint:** `applications` needs `recruitment_stages` (Phase 3) for `current_stage_id`; `consents` needs `applications`; `recruitment_outcomes` needs both `applications` and `external_apply_events`.

| Order | Table | Depends on |
| --- | --- | --- |
| 1 | `applications` | `candidate_profiles`, `vacancies`, `recruitment_stages` |
| 2 | `application_status_histories` | `applications`, `recruitment_stages`, `users` |
| 3 | `application_documents` | `applications`, `candidate_documents` |
| 4 | `application_screening_answers` | `applications`, `vacancy_screening_questions` |
| 5 | `selection_stage_assignments` | `recruitment_stages`, `users` |
| 6 | `selection_schedules` | `applications`, `recruitment_stages`, `users` |
| 7 | `selection_schedule_histories` | `selection_schedules`, `users` |
| 8 | `evaluations` | `applications`, `recruitment_stages`, `users` |
| 9 | `evaluation_items` | `evaluations` (CASCADE) |
| 10 | `offers` | `applications`, `users` |
| 11 | `external_apply_events` | `candidate_profiles`, `vacancies`, `users` — **`consent_id` FK deferred to Phase 5** |
| 12 | `consents` | `users`, `applications`, `vacancies`, `companies`, `organizational_units` |
| 13 | `recruitment_outcomes` | `applications`, `external_apply_events`, `users` |

> **Circular dependency, resolved:** `external_apply_events.consent_id → consents` and `consents.vacancy_id → vacancies` form no cycle, but `external_apply_events` is created **before** `consents` for outcome ordering. **Create `external_apply_events` without `consent_id`'s foreign key, then add that one constraint after `consents` exists.** This is the only deferred FK in the plan and must not be forgotten.

### Phase 5 — Communication, audit, configuration (4 tables)

| Table | Depends on |
| --- | --- |
| `notifications` | `users` |
| `email_outbox` | none (polymorphic reference is untyped by design) |
| `audit_logs` | `users` (SET NULL) |
| `smtp_configurations` | `users` |

Plus the deferred `external_apply_events.consent_id` FK from Phase 4.

### Phase 6 — Operational and framework infrastructure (6 tables)

**Never mixed with business migrations** — different lifecycle, different review criteria.

| Table | Layer |
| --- | --- |
| `idempotency_keys` | Operational (DB-1) |
| `export_jobs` | Operational (DB-2) — → `users` |
| `sessions` | Framework |
| `cache`, `cache_locks` | Framework (fallback; Redis is primary) |
| `failed_jobs` | Framework |
| `personal_access_tokens` | Framework — **only when the 57 `VERSIONED_API` endpoints are activated** |

**Not created:** `jobs`, `job_batches` — the queue is Redis (ADR-006).

### Phase 7 — Constraints, partial indexes, privileges

Deliberately last, so every referenced table exists and the whole integrity layer is reviewed as one unit.

1. **The five P0 partial unique indexes** — INV-017, INV-025, INV-031, INV-036, INV-037 (`POSTGRESQL_CONSTRAINTS.md` §2), plus `user_roles` itself if grouped here.
2. **The three XOR `CHECK` constraints** — INV-018, INV-022, INV-023.
3. All remaining `CHECK` constraints — value sets and same-row guards.
4. All Class B/C/D/E indexes from `INDEX_STRATEGY.md`.
5. **Privilege revocation** on the six append-only tables (`POSTGRESQL_CONSTRAINTS.md` §5.2).
6. Session `TimeZone = 'UTC'` verification.

---

## 3. Verification Gates Between Phases

| After | Verify |
| --- | --- |
| Phase 1 | All master tables seeded or seedable; `users.email_normalized` unique index present |
| Phase 3 | `vacancies` ownership XOR rejects a row with both owners **and** a row with neither |
| Phase 4 | `uq_applications_candidate_vacancy` rejects a duplicate; a concurrent-insert test produces exactly one row |
| Phase 7 | All five partial uniques and three XOR checks demonstrably reject their violation; append-only privilege revocation verified by attempting an `UPDATE` |

**Phase 7 gates are not optional.** These constraints are the schema's reason for choosing PostgreSQL; a migration that creates them without proving they fire has created a comment, not a guarantee.

---

## 4. Rollback and Production Safety

Guidance for future implementation. Every `up()` has a symmetric, tested `down()`.

### 4.1 Never in one release

| Anti-pattern | Why | Instead |
| --- | --- | --- |
| **Drop a column in the same release that stops using it** | During a rolling deploy, old and new code run against one schema. Old code still selects the column | **Expand → deploy → contract.** Stop using it in release N; drop it in release N+1 |
| **Add `NOT NULL` with a backfill in one operation** | Rewrites the table and holds an `ACCESS EXCLUSIVE` lock for the duration | Add nullable → backfill in bounded batches → add `NOT NULL` in a later release. On PG 12+, `ADD COLUMN … DEFAULT` is safe; the backfill is not |
| **Add a constraint without `NOT VALID`** on a large table | Full validating scan under lock | `ADD CONSTRAINT … NOT VALID`, then `VALIDATE CONSTRAINT` separately — the second takes only a `SHARE UPDATE EXCLUSIVE` lock |
| **Create an index without `CONCURRENTLY`** on a populated table | Blocks writes for the build | `CREATE INDEX CONCURRENTLY`. **Cannot run inside a transaction**, so Laravel's migration must disable its wrapper for that migration |
| **Rename a column or table** | Instantly breaks the currently-running release | Add new → dual-write → migrate reads → drop old, across releases |
| **Alter an enum** | Not applicable — `varchar` + `CHECK` was chosen precisely to avoid this (`DATABASE_SCHEMA.md` §5) |

### 4.2 Enum evolution — the payoff of the `CHECK` decision

Adding a value:

1. `ALTER TABLE … DROP CONSTRAINT chk_<table>_<column>;`
2. `ALTER TABLE … ADD CONSTRAINT chk_<table>_<column> CHECK (<column> IN (…, 'NEW_VALUE'));`

Both statements run **inside one transaction** and roll back cleanly. A native PostgreSQL `ENUM` could not offer this — `ALTER TYPE … ADD VALUE` is not fully transactional, and removing a value requires recreating the type and rewriting every dependent column.

Removing a value additionally requires confirming no row holds it. **Removing a value that rows still hold is a data migration, not a schema migration**, and must be planned as one.

### 4.3 Lock awareness

| Operation | Lock | Safe on a live table? |
| --- | --- | --- |
| `ADD COLUMN` nullable, no default | `ACCESS EXCLUSIVE`, instant | Yes |
| `ADD COLUMN` with a constant default (PG 11+) | `ACCESS EXCLUSIVE`, instant | Yes |
| `ADD COLUMN NOT NULL` without default | Rewrite | **No** |
| `CREATE INDEX` | Blocks writes | **No** — use `CONCURRENTLY` |
| `CREATE INDEX CONCURRENTLY` | `SHARE UPDATE EXCLUSIVE` | Yes; cannot be transactional |
| `ADD CONSTRAINT … NOT VALID` | Brief | Yes |
| `VALIDATE CONSTRAINT` | `SHARE UPDATE EXCLUSIVE` | Yes |
| `DROP CONSTRAINT` | `ACCESS EXCLUSIVE`, instant | Yes |
| `ALTER COLUMN TYPE` | Rewrite | **No** — plan as a data migration |

**At MVP these tables are empty**, so Phase 1–7 can run without concurrency concessions. **These rules govern every migration after the first production deployment**, and that is when they matter.

### 4.4 Migration hygiene

- **One logical change per migration file.** A file that creates a table *and* backfills *and* adds a constraint cannot be partially rolled back.
- **PostgreSQL DDL is transactional** — a failed migration rolls back cleanly (an ADR-003 tiebreaker). The exception is `CREATE INDEX CONCURRENTLY`, which must be isolated in its own migration.
- **Seeders are not migrations.** Master data is seeded separately, so a schema rollback does not destroy reference data.
- **Every `down()` is tested**, not written and assumed.
- **No migration writes business data.** Backfills are explicit, reviewed data migrations.

---

## 5. Seeding

| Data | When | Note |
| --- | --- | --- |
| `roles` | Phase 1, required | The eleven approved codes (FSD §3.1). `PUBLIC` is **not** seeded — a visitor holds no assignment |
| `geographic_areas`, `organizational_units`, `study_programs`, `industries`, `organization_types`, `skills` | Phase 1, required | **Seeded at deployment because master-data write endpoints are deferred beyond MVP** (`API_SIZE_REVIEW.md` DF-1). FSD §4.6's management requirement remains unmet until those ship — this must stay visible, not be counted as delivered |
| Super Admin bootstrap | Phase 1, required | Created without a password and activated through the standard one-time token flow. **No temporary password is ever generated** (ADR-005) |
| `smtp_configurations` | Not seeded | Zero active rows is valid (INV-036); delivery falls back to deployment configuration |
| Synthetic test data | Local and development only | **Never staging or production** |
