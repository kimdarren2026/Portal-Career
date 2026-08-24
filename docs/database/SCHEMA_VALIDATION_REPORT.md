# Schema Validation Report — Portal Karir Kampus

**Status:** Validation complete
**Date:** 24 August 2026 · **Engine:** PostgreSQL 16+ · **Framework:** Laravel
**Validates:** `DATABASE_SCHEMA.md` · `POSTGRESQL_CONSTRAINTS.md` · `INDEX_STRATEGY.md` · `MIGRATION_PLAN.md`
**Against:** Logical model **1.1-C3** (51 entities, INV-001…038) · Architecture ADR-001…017 · Frozen API Contract (148 operations)
**Updated:** 24 August 2026 — the single divergence recorded below was **resolved** by logical revision 1.1-C3.

---

## 1. Result

| | |
| --- | --- |
| **Verdict** | **PASS** — the previously disclosed divergence is resolved by logical revision 1.1-C3 |
| Logical entities represented | **51 / 51** — verified programmatically |
| Business tables | 51 |
| Operational tables | 2 |
| Framework tables | 4 |
| **Total physical tables** | **57** |
| Speculative business entities added | **0** |
| Logical entities silently dropped | **0** |
| Divergences from the logical model | **0** — see §3 |

---

## 2. The 23-Point Validation

| # | Check | Result | Evidence |
| --- | --- | --- | --- |
| 1 | All 51 logical entities receive a physical representation | **PASS** | Verified by script: entity set from `DATA_DICTIONARY.md` §### headings equals the traceability table in `DATABASE_SCHEMA.md` §6 — zero missing, zero extra |
| 2 | DB-1 and DB-2 represented separately | **PASS** | `idempotency_keys` and `export_jobs`, both labelled **Operational Technical Requirement**, in a separate layer table (§6) — not business entities, no ERD change request |
| 3 | No logical entity disappears silently | **PASS** | Set comparison above; every entity carries domain, invariants, and requirement IDs |
| 4 | No speculative business entity added | **PASS** | The business layer is exactly the 51. The only new tables are two operational and four framework, all explicitly classified |
| 5 | Five conditional uniqueness rules have a physical strategy | **PASS** | INV-025, INV-017, INV-031, INV-036, INV-037 → five partial unique indexes, `POSTGRESQL_CONSTRAINTS.md` §2.1–2.5, each with predicate, race prevented, and Laravel handling |
| 6 | Three core XOR rules have a physical strategy | **PASS** | INV-018, INV-022, INV-023 → three `CHECK` constraints with full expressions, §4.1 |
| 7 | `applications` unique candidate+vacancy is DB-enforced | **PASS** | `uq_applications_candidate_vacancy` — **unconditional**, not filtered, covering the full lifecycle (INV-007, D-1). Concurrency walkthrough in §8 |
| 8 | External ATS application hole remains closed | **PASS** | INV-024 service-enforced with `FOR UPDATE` on `vacancies`; the structural composite-FK alternative is documented and explicitly rejected with reasons (§5.1) |
| 9 | Company verification gate has an enforcement strategy | **PASS** | INV-002 → `SELECT … FOR UPDATE` on `companies` inside the creating transaction; trigger alternative considered and rejected (§5.2) |
| 10 | Consent receiver remains constrained | **PASS** | `chk_consents_receiver_xor` forbids both-present; the exactly-one and owner-match halves are service-enforced (INV-023) |
| 11 | Selector assignment remains constrained | **PASS** | Partial unique index (INV-037) plus the reverse-ordered scoping index; role precondition service-enforced |
| 12 | Submitted company evidence cannot be destructively lost | **PASS** | `first_submitted_at` gate, `superseded_at` + `superseded_by_document_id` with `RESTRICT` self-FK (§14), now governed by **INV-038** |
| 13 | Document sharing snapshots remain immutable | **PASS** | `snapshot_name` and `snapshot_storage_reference` **`NOT NULL`**; `candidate_document_id` FK is `RESTRICT` (INV-032, §18) |
| 14 | Offer acceptance concurrency has DB protection | **PASS** | Partial unique index `WHERE status = 'ACCEPTED'` as final protection, plus ordered row locks (INV-031, §20) |
| 15 | SMTP secret remains encrypted and application-managed | **PASS** | `encrypted_password text` ciphertext only, **never indexed**, key outside the database, `pgcrypto` rejected (INV-035, §21) |
| 16 | Time-to-Fill unchanged | **PASS** | `offers.offer_accepted_at − vacancies.published_at`, earliest accepted offer, undefined while `published_at` is null. **No stored metric column exists anywhere** |
| 17 | All FKs have a delete policy | **PASS** | `POSTGRESQL_CONSTRAINTS.md` §6 — `RESTRICT` default, `SET NULL` for nullable actors, five enumerated `CASCADE` |
| 18 | No blanket cascade deletion | **PASS** | Exactly five `CASCADE` constraints, each individually justified; nothing else cascades |
| 19 | JSONB usage justified individually | **PASS** | Eight columns, each reviewed separately (§15). Two candidates **rejected** — `application_screening_answers` made relational, `vacancy_requirements` left relational |
| 20 | PostgreSQL-specific choices explicitly documented | **PASS** | Partial indexes, `timestamptz`, `jsonb`, `inet`, expression index for INV-036, transactional DDL, `NOT VALID`/`VALIDATE`, `CREATE INDEX CONCURRENTLY` |
| 21 | Laravel migration implementability clear | **PASS** | §5 |
| 22 | No PHP, migration, or SQL executable file created | **PASS** | Five Markdown documents only; verified by filesystem scan |
| 23 | All open business questions remain open | **PASS** | Six questions plus H-2/H-3/H-4, each with its physical posture (`DATABASE_SCHEMA.md` §26) |

**23 / 23 pass.**

---

## 3. The Disclosed Divergence — RESOLVED in 1.1-C3

> **Status: closed 24 August 2026.** Logical revision 1.1-C3 added all three fields to `company_documents` in `ERD.md` and `DATA_DICTIONARY.md`, and added **INV-038** to govern them. **The physical schema and the logical model now agree, and no divergence remains.** The original disclosure is retained below as review evidence.

**`company_documents` gained three columns not present in logical model 1.1-C2:**

| Column | Purpose |
| --- | --- |
| `first_submitted_at timestamptz NULL` | Distinguishes never-submitted from ever-submitted |
| `superseded_at timestamptz NULL` | Marks a replaced document |
| `superseded_by_document_id bigint NULL` | Points at the replacement |

**Why this is not an unauthorized change:**

The **frozen API contract** defines `POST /companies/{company}/documents/{document}/supersede` and the rule that a document included in any submitted verification package cannot be destructively deleted. That contract was approved *after* logical revision 1.1-C2, so 1.1-C2 has no columns for it.

These three columns are the **physical realization of an already-approved behaviour**. They add no entity, no relationship to a new concept, and no capability the API contract does not already specify. Without them, the frozen contract is unimplementable.

**Disposition applied:** recorded in logical revision **1.1-C3** through a controlled alignment, with **INV-038** stating the retention rule, the same-company requirement, the no-self-supersede and no-cycle rules, and an explicit access boundary confirming that retained evidence remains private. **The schema phase did not amend the frozen logical model unilaterally** — it disclosed, and the alignment was applied as a separate approved step.

**No other divergence exists.** No other column, table, or relationship departs from the logical model.

---

## 4. Invariant Enforcement Map

All 38 invariants, with the layer that enforces each.

| Enforcement | Invariants | Count |
| --- | --- | --- |
| **Database — unique or partial unique index** | INV-001, INV-007, INV-017, INV-022, INV-025, INV-031, INV-036, INV-037 | 8 |
| **Database — `CHECK` constraint** | INV-004, INV-005, INV-006, INV-018, INV-023 *(presence half)*, INV-027 | 6 |
| **Database — FK `RESTRICT` / `NOT NULL`** | INV-010, INV-016 *(with privilege revocation)*, INV-032, INV-034 *(by absence of a constraint)*, INV-038 *(RESTRICT on the supersede self-FK, plus two same-row CHECKs; rules D and F service-enforced)* | 5 |
| **Service / transaction** | INV-002, INV-008, INV-009, INV-011, INV-012, INV-013, INV-015, INV-019, INV-021, INV-024, INV-026, INV-028, INV-029, INV-030, INV-033, INV-035 | 16 |
| **Enforced by absence** | INV-014 *(no creation path reads outcomes)*, INV-003 / INV-020 *(no partnership column in any gate)* | 3 |
| **Total** | | **38** |

**19 of 38 invariants have a database-level backstop.** The remainder require cross-table state or actor context that no constraint can express, and each is assigned an explicit service-layer mechanism in `POSTGRESQL_CONSTRAINTS.md` §5 — none is left to convention.

---

## 5. Laravel Migration Readiness

| Concern | Status |
| --- | --- |
| Identifier strategy | `bigint` identity — `$table->id()` and `foreignId()` map directly (§2) |
| Timestamps | `timestamptz` throughout — `timestampTz()`; session `TimeZone = UTC` |
| Enums | `varchar` + named `CHECK` — expressible in `up()`/`down()` with symmetric rollback |
| Partial unique indexes | Not expressible in Laravel's fluent builder → `DB::statement` with the exact expressions in `POSTGRESQL_CONSTRAINTS.md` §2 |
| `CHECK` constraints | Same — full expressions provided, ready to paste |
| Delete policies | Every FK has an explicit policy; `->restrictOnDelete()`, `->nullOnDelete()`, `->cascadeOnDelete()` |
| Constraint naming | Every constraint explicitly named, so `down()` drops deterministically |
| Violation handling | SQLSTATE → error-code mapping, **caught by constraint name, never message text** (§9) |
| Soft deletes | **`SoftDeletes` not applied to any table.** Purpose-specific lifecycle columns instead (§8) |
| Model naming conflicts | Role-bearing FK names (`reviewer_user_id`, `assigned_by`) declared explicitly rather than renamed to suit Eloquent inference |
| Framework tables | `jobs`/`job_batches` **not** created; Laravel's default `password_reset_tokens` **not** created — the business table of that name already exists (ADR-011) |
| Migration order | Seven dependency-safe phases; the single deferred FK (`external_apply_events.consent_id`) is called out explicitly |
| Testing | PostgreSQL in tests, **never SQLite** — SQLite shares neither partial indexes nor `CHECK` semantics and would silently skip the guarantees that matter most |

**Nothing in this schema is hostile to Laravel.** The two things the fluent builder cannot express — partial unique indexes and `CHECK` constraints — are specified precisely enough to implement with `DB::statement` without interpretation.

---

## 6. Open Questions — Preserved

None is decided by the physical schema.

| # | Question | Physical posture |
| --- | --- | --- |
| 1 | Alumni verification source | `student_number`, `program_study_id`, `graduation_year` all nullable; `source_reference` untyped; no integration table |
| 2 | Minimum legal documents per organization type | `company_documents.document_type` has **no approved `CHECK` vocabulary** and no per-type requirement matrix |
| 3 | Salary policy | All three salary columns nullable; the only guard is `salary_max >= salary_min` when both are present — arithmetic, not policy |
| 4 | Recruiter domain / subdomain | No schema impact |
| 5 | WhatsApp phase | No channel, provider, template, or delivery table |
| 6 | First recruiter default role / minimum Company Admin | `company_members.company_role` has **no default value** and no minimum-count constraint |
| **H-2** | Vacancy-level outcome | `recruitment_outcomes` stays candidate-level via its source XOR; **no vacancy-level column added** |
| **H-3** | Candidate revocation of a shared document | `application_documents.revoked_at` exists and is honoured on read; no candidate-facing path |
| **H-4** | Audit IP / device collection | `ip_address inet NULL`, `user_agent_device_metadata jsonb NULL` — both optional |

**Additionally carried forward:** FSD §4.6 master-data management remains **unmet** while the write endpoints are deferred (`API_SIZE_REVIEW.md` DF-1). Master data is seeded at deployment. This is scope sequencing, and it must stay visible rather than be counted as delivered.

---

## 7. Freeze Recommendation

**RECOMMEND FREEZE — unconditional.**

All 51 logical entities are represented, all 23 validation points pass, the five conditional-uniqueness rules and three XOR rules that made PostgreSQL the engine choice have precise physical specifications, no open business question is decided, and **the previously disclosed divergence is closed** by logical revision 1.1-C3.

The ERD, the data dictionary, the invariants, and the physical schema are now in agreement — which is the property that makes all four trustworthy.

**Next artefacts, in order:** Laravel migrations generated from these specifications, then seeders, then the first Action implementations. **Phase 7's verification gates are not optional** — the five partial uniques and three XOR checks must be proven to reject their violations, because a constraint that has never been tested is a comment, not a guarantee.
