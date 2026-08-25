# ERD Required Corrections Applied

**Applied:** 24 August 2026
**Source of corrections:** `docs/database/ERD_REVIEW.md` (unchanged — retained as historical review evidence)
**Documents updated:** `ERD.md`, `DATA_DICTIONARY.md`, `DATA_INVARIANTS.md`, `README.md`
**Resulting revision:** 1.1-C1
**Entity count:** 47 → **49**

All 20 required corrections (RC-1 to RC-20) from the review were applied, together with every Critical, Major, and Minor finding. Nothing was silently omitted. No SQL, migration, ORM model, or application code was produced, and no database vendor was chosen.

---

## Critical Corrections Applied

### C-1 / RC-1 — `recruitment_outcomes` source exclusivity

**Applied. Option A was chosen: redundant authoritative fields were removed rather than marked as caches** — the review and the correction brief both asked for the simpler and safer design, and removal makes contradiction structurally impossible rather than merely forbidden.

| Change | Where |
| --- | --- |
| Added `source_type` discriminator with values `INTERNAL_APPLICATION` / `EXTERNAL_APPLY` | ERD Mermaid, value sets, dictionary |
| `application_id` → **Conditional**: required for INTERNAL_APPLICATION, must be absent for EXTERNAL_APPLY | dictionary |
| `external_apply_event_id` → **Conditional**: required for EXTERNAL_APPLY, must be absent for INTERNAL_APPLICATION | dictionary |
| **Removed `vacancy_id`** — derivable from the populated source reference | ERD Mermaid, dictionary, ERD relationships |
| **Removed `candidate_profile_id`** — derivable from the populated source reference | ERD Mermaid, dictionary, ERD relationships |
| Renamed the overloaded `source` field to `reported_by_source` (CANDIDATE / COMPANY / CAMPUS_STAFF / INTEGRATION) so it no longer collides with the new `source_type` | dictionary, value sets |
| Removed relationships `CANDIDATE_PROFILES ||--o{ RECRUITMENT_OUTCOMES` and `VACANCIES ||--o{ RECRUITMENT_OUTCOMES` | ERD Mermaid |
| **INV-022 — Outcome Source Exclusivity** added, stating exactly-one-of and the absence of any stored vacancy/candidate copy | DATA_INVARIANTS |
| Uniqueness added: one outcome per application, one per external event | DATA_INVARIANTS |
| Decision D-4 recorded | ERD |

Both-present, both-absent, and contradictory-parent rows are now unrepresentable rather than merely discouraged.

### C-2 / RC-2 — `consents` receiving-party exclusivity

**Applied.** The receiving party is now determined by the vacancy's `ownership_type`:

- COMPANY vacancy → `receiving_company_id` **required** and equal to the vacancy's company; `receiving_organizational_unit_id` **must be absent**.
- CAMPUS vacancy → `receiving_organizational_unit_id` **required** and equal to the vacancy's unit; `receiving_company_id` **must be absent**.
- **Neither-present is invalid** for recruitment data-sharing consent; **both-present is invalid**.

Both fields changed from Required = No to **Conditional** in the dictionary, both were added to the ERD Mermaid block, the rule was added to the *Exclusive Reference Rules* table in `ERD.md`, and **INV-023 — Consent Receiving Party Exclusivity** was added. INV-011 was amended to state that a Boolean on `applications` must never be introduced as a substitute.

### C-3 / RC-3 — External ATS inverse rule

**Applied. INV-024 — In-Portal Application Only** was added as a non-negotiable rule:

> An `applications` row may exist only when its vacancy has `application_method = IN_PORTAL`.

INV-012 (`EXTERNAL_APPLY_STARTED` ≠ `APPLIED`) is retained unchanged and now cross-references INV-024 as its inverse. A supplementary clause forbids changing a vacancy from IN_PORTAL to EXTERNAL_ATS while applications exist against it. The constraint is also recorded on `applications.vacancy_id` and `vacancies.application_method` in the dictionary.

### C-4 / RC-4 — `user_roles` history

**Applied.** The composite key was removed and replaced with a surrogate identifier:

| Field | State |
| --- | --- |
| `id` | **Added** — primary key |
| `user_id`, `role_id` | Retained; **no longer a composite key** |
| `assigned_at`, `assigned_by` | Retained; never overwritten by a later revocation |
| `revoked_at` | Retained; absence means the assignment is active |
| `revoked_by` | **Added** — justified because FR-AUD-001 requires role changes to be attributable |

**INV-025 — Role Assignment History** states the rule logically: many historical revoked assignments per `(user_id, role_id)`, at most one active assignment where `revoked_at` is absent. No partial index, filtered index, or other vendor-specific mechanism was selected; the invariant explicitly defers the enforcement mechanism to the database decision. `FINAL_YEAR_STUDENT → ALUMNI` is now representable without losing role history.

---

## Major Corrections Applied

| # | Correction | Applied |
| --- | --- | --- |
| RC-5 | **Candidate profile gap (FR-CAN-003)** — added `candidate_organizations`, `candidate_certifications`, `candidate_links`; added work-preference fields (`preferred_employment_type`, `preferred_workplace_mode`, `preferred_location_note`, `open_to_opportunities`) to `candidate_profiles`. Every added field is optional so open question 1 stays unanswered. Classified as technical derivations implementing FR-CAN-003. No ranking, scoring, or AI concept was introduced. | ✔ |
| RC-6 | **`vacancy_requirements` restructured** — the opaque `value_reference` was replaced by typed conditional fields: `education_level`, `study_program_id`, `skill_id`, `minimum_years_experience`, `value_text`, plus `note`. One table and one discriminator retained; CERTIFICATION and OTHER_QUALIFICATION deliberately stay free text rather than being over-normalized. | ✔ |
| RC-7 | **Derived caches declared** — `applications.current_status`, `current_stage_id`, `reopen_count`, `last_reopened_at` marked as derived caches in the dictionary; **INV-026** binds each to `application_status_histories` and requires cache and history to be written in the same transaction. | ✔ |
| RC-8 | **Campus vacancy ownership** — `organizational_unit_id` is now **Conditional: required when `ownership_type = CAMPUS`** (FR-HR-002), `company_id` **must be absent** for campus vacancies and is required for company vacancies with `organizational_unit_id` absent. INV-018 rewritten as a full exclusivity table and extended so a campus vacancy can never hold a moderation-only status (PENDING_REVIEW, REVISION_REQUIRED, APPROVED, REJECTED). | ✔ |
| RC-9 | **Candidate type / role / eligibility ruling** — **INV-028** added, plus a dedicated section in `ERD.md`. Authorization = `roles`/`user_roles`; identity category = `current_candidate_type`; verified eligibility = `candidate_verifications`. Target-audience gating must read verification only. "Holding the role `CANDIDATE_ALUMNI` is not proof of alumni verification" is stated explicitly. | ✔ |
| RC-10 | **Uniqueness markers** — `UK` removed from `users.email` and from `companies.normalized_name` in the ERD Mermaid. INV-001 rewritten to state `email_normalized` is the sole authoritative uniqueness key and that no competing constraint may exist on `email`. `skills.normalized_name` legitimately keeps its `UK` and matches its dictionary row. | ✔ |
| RC-11 | **Mandatory review reasons** — `reason_category` and `recruiter_visible_note` on `company_verification_reviews` and `vacancy_moderation_reviews` changed from optional to **Conditional: required when `action` is REQUEST_REVISION, REJECT, or SUSPEND**. Reclassified from "Optional / Pending Decision" to "BRD/FSD Required"; only the category vocabulary stays pending. **INV-029** added. Two malformed 5-column table rows (`internal_note` in both entities) were repaired to 6 columns while applying this. | ✔ |
| RC-12 | **Company profile Wajib fields** — `organization_type_id`, `industry_id`, `official_email`, `address`, `province_geographic_area_id`, `city_geographic_area_id` changed to **Conditional: required before `verification_status` may reach PENDING_VERIFICATION**. **INV-030** added, including at least one `company_documents` record. Which document types satisfy it stays open question 2. | ✔ |
| RC-13 | **Offer uniqueness and Time-to-Fill** — **INV-031** added (at most one ACCEPTED offer per application). INV-013 extended: vacancy-level Time-to-Fill uses the **earliest** `offer_accepted_at` among that vacancy's accepted offers, and is **undefined** while `published_at` is null. `offers.offer_accepted_at` became Conditional (required when status is ACCEPTED). | ✔ |
| RC-14 | **Shared-document snapshot integrity** — `snapshot_name` and `snapshot_storage_reference` changed from optional to **required at share time**; `snapshot_checksum` stays optional-but-recommended. **INV-032** added: a `candidate_documents` row referenced by a non-revoked share cannot be hard-deleted, only archived or anonymized; revocation withdraws future access without deleting the record, removing the snapshot, or invalidating a completed evaluation. No document is made public. | ✔ |
| RC-15 | **`vacancy_types` deferred** — entity removed from the dictionary, from the ERD Mermaid, from the domain table, and its unattached `VACANCY_TYPES ||--o{ VACANCIES` relationship deleted. `vacancies.vacancy_type` remains a fixed logical enumeration. Recorded in *Future Extension — Not MVP* with the condition for reintroduction. | ✔ |
| RC-16 | **`skills` contradiction resolved** — adopted as a real master (decision D-3). "Optional / Pending Decision" labels removed from its identity, name, and active fields; it is now referenced as a required value by `candidate_skills` and as a typed optional reference by `vacancy_requirements`. | ✔ |
| RC-17 | **Geography applied consistently** (decision D-2) — `geographic_areas` now serves company address, candidate domicile (`candidate_profiles.province_geographic_area_id` / `city_geographic_area_id`), and vacancy location (`vacancies.province_geographic_area_id` / `city_geographic_area_id`). Each keeps a free-text fallback for unmatched values. Two ERD relationships added. | ✔ |

---

## Minor Corrections Applied

All twelve minor findings (m-1 to m-12) from the review were applied.

| # | Correction | Applied |
| --- | --- | --- |
| m-1 | `candidate_verifications.status` reconciled to the FR-CAN-004 vocabulary: **NOT_VERIFIED, PENDING, VERIFIED, MISMATCH_MANUAL_REVIEW**. `REJECTED` removed — FSD does not define it, and FSD is the source of truth. `rejection_reason` became Conditional (required for MISMATCH_MANUAL_REVIEW). A distinct rejected outcome would require an FSD change request. | ✔ |
| m-2 | History event renamed `REOPENED` → **`APPLICATION_REOPENED`**, matching FR-APP-003. Updated in the value sets, the dictionary, INV-008, and INV-026. | ✔ |
| m-3 | FR-APP-005 visibility note added: `application_status_histories.candidate_visibility` (required) and `candidate_visible_note` (optional). | ✔ |
| m-4 | FR-SEL-001 "lampiran" added: `selection_schedules.attachment_storage_reference`. | ✔ |
| m-5 | **`RESCHEDULED` removed from `selection_schedules.status`.** Status is now SCHEDULED, COMPLETED, CANCELLED, NO_SHOW. `revision_number` added to the schedule and `resulting_revision_number` to its history. **INV-027** defines the reschedule as: update values → increment revision → append history event → leave status SCHEDULED. `RESCHEDULED` remains a valid history `event_type`. | ✔ |
| m-6 | `email_outbox.status` mapping documented — `FAILED_RETRYABLE` consolidates FR-NOTIF-001's `FAILED → RETRY_SCHEDULED`, with `next_attempt_at` carrying the scheduled-retry meaning. Recorded in the ERD value sets and the dictionary so the FSD wording stays traceable. | ✔ |
| m-7 | `evaluations.stage_id` renamed **`recruitment_stage_id`**, matching `selection_schedules`. Updated in the ERD, dictionary, and INV-019. | ✔ |
| m-8 | `candidate_educations`, `candidate_work_experiences`, `candidate_skills`, `candidate_saved_vacancies` reclassified from "Derived Technical Requirement" to **BRD/FSD Required** at field level (FR-CAN-003 and FSD §4.2 name all four). Entity structure unchanged. | ✔ |
| m-9 | `roles` omission of `PUBLIC` documented as deliberate — an unauthenticated visitor holds no assignment; public visibility is governed by `vacancies.target_audience`. | ✔ |
| m-10 | `application_documents.revoked_at` retained and constrained by INV-032; recorded as human-decision item **H-3** because FSD v1.1 does not define candidate-initiated revocation. | ✔ |
| m-11 | `vacancies.open_at` and `close_at` changed to **Conditional: required before leaving DRAFT**. | ✔ |
| m-12 | `applications.reopen_count` documented as a derived cache under INV-026, with FSD §6.5's "opsional" wording noted. | ✔ |

### Additional corrections applied beyond the numbered findings

| Correction | Reason |
| --- | --- |
| **RC-18/L-2** — surrogate `id` added to `candidate_saved_vacancies` and `candidate_skills` | Both carry their own attributes; composite-key-only pivots would need a rewrite the moment they need model behaviour or policies. Composite uniqueness retained. |
| **RC-19** — decision **D-1** recorded in `ERD.md` and INV-007 | FSD §6.5's "satu lifecycle **aktif**" read against FR-APP-002/003.4. Recorded as a documented reading, with a note that the alternative would require an FSD change request. No FSD text was touched. |
| **RC-20** — logical `anonymized_at` concept added to `users` and `candidate_profiles`, and to the retention treatment table | An anonymized record was previously indistinguishable from a live one. |
| **L-9/INV-033** — polymorphic `object_type` / `related_object_type` must carry a stable **logical** entity name, never an implementation class path | Audit history must stay readable indefinitely; code refactoring must not corrupt it. |
| **L-11** — outbox rows are dispatched only after the business transaction commits | Recorded in INV-015 so a worker can never read a row that does not yet exist. |
| **L-10** — soft-delete forbidden on entities carrying recruitment history without an explicit archival design | A soft-deleted parent that neither cascades nor restricts hides child history while leaving it stored — indistinguishable from data loss in reporting. |
| **L-5/INV-019** — cross-entity consistency must be enforced at the model/service boundary, not at the request boundary alone | Background jobs, scheduled processes, and administrative tooling bypass request-level validation. |
| **Table repair** — two malformed 5-column rows (`internal_note`) corrected to 6 columns | Pre-existing defect in both review entities, found while applying RC-11. |
| **`offers.rejection_reason`** added as explicitly optional | FR-SEL-005 stores an optional decline reason; it had no field. Marked optional, not conditional. |
| **`evaluation_items.sort_order`** added | Form presentation order for a repeating criteria group. |
| **`candidate_certifications.document_id`** added as an optional link to the private certificate file | Keeps structured certification data separate from the file, without granting any implicit sharing. |

---

## Entities Added

| Entity | Purpose | Classification |
| --- | --- | --- |
| `candidate_organizations` | FR-CAN-003 "organisasi" — student organizations, committees, professional bodies, volunteer roles. Kept separate from work experience because FR-CAN-003 lists them as distinct sections. | Technical derivation implementing FR-CAN-003 |
| `candidate_certifications` | FR-CAN-003 "sertifikasi" — structured credential data (issuer, identifier, validity), optionally linked to a private certificate file. | Technical derivation implementing FR-CAN-003 |
| `candidate_links` | FR-CAN-003 "link profesional/portfolio" — a typed URL list. Stores no portfolio content, renders no external profile, performs no fetching (decision D-5). | Technical derivation implementing FR-CAN-003 |

## Entities Removed / Deferred

| Entity | Action | Reason |
| --- | --- | --- |
| `vacancy_types` | **Deferred** to *Future Extension — Not MVP* | Duplicated `vacancies.vacancy_type`, which is behaviour-bearing (INV-005 keys campus rules off it). The ERD also drew a relationship with no attribute to travel on. FR-VAC-001's "tipe tambahan melalui master data" is a post-MVP extension; reintroduction requires an approved change request, at which point `vacancy_type` becomes a reference rather than an enumeration. |

No entity was merged. No entity was removed to reduce table count.

## Final Entity Count

**49 logical entities.**

| Step | Count |
| --- | --- |
| Before correction | 47 |
| − `vacancy_types` deferred | 46 |
| + `candidate_organizations` | 47 |
| + `candidate_certifications` | 48 |
| + `candidate_links` | 49 |

The review's RC-5 estimate of 48 assumed professional/portfolio links would become fixed profile columns. The correction brief asked for them "in a repeatable and flexible way", which requires a row per link, so the result is one above that estimate and one above the stated 46–48 range. The number was not targeted: 49 is what the FR-CAN-003 gap costs once links are repeatable. Choosing fixed link columns instead would yield 48 and is the only lever; it was not taken because it would cap the number of links a candidate may record.

Verified counts, all in agreement: **49** Mermaid entity blocks, **49** dictionary `###` sections, **49** entities across the ERD domain table.

## Invariants Added / Changed

**34 invariants** are now defined (INV-001 to INV-034), up from 21. No duplicate identifiers; every `INV-nnn` referenced anywhere in the three documents is defined.

### Added

| ID | Rule |
| --- | --- |
| INV-022 | Outcome Source Exclusivity — XOR plus the absence of any stored vacancy/candidate copy |
| INV-023 | Consent Receiving Party Exclusivity — ownership-driven XOR; neither-present invalid |
| INV-024 | In-Portal Application Only — the inverse of INV-012 |
| INV-025 | Role Assignment History — surrogate key, at most one active assignment |
| INV-026 | Derived Application Fields — caches bound to append-only history |
| INV-027 | Selection Schedule Reschedule Representation — no RESCHEDULED status |
| INV-028 | Candidate Type, Role, and Eligibility Separation |
| INV-029 | Mandatory Review Reason — conditional, not global |
| INV-030 | Company Verification Submission Completeness — conditional Wajib set |
| INV-031 | Single Accepted Offer |
| INV-032 | Shared Document Snapshot Integrity |
| INV-033 | Stable Polymorphic Type Names |
| INV-034 | Company Name Is Detected, Not Constrained |

### Changed

| ID | Change |
| --- | --- |
| INV-001 | Rewritten — `email_normalized` is the sole authoritative uniqueness key; no competing constraint may exist on `email`; normalization precedes validation |
| INV-007 | Uniqueness stated as unconditional and full-lifecycle; decision D-1 referenced |
| INV-008 | Event renamed to `APPLICATION_REOPENED` |
| INV-009 | Clarified that `withdrawal_reason` stays optional per FR-APP-006 and is excluded from INV-029 |
| INV-010 | Added that reaching a candidate profile grants no document access and nothing is ever public |
| INV-011 | Added that a Boolean on `applications` must never be introduced as a substitute |
| INV-012 | Cross-references INV-024 as its inverse |
| INV-013 | Time-to-Fill unchanged in definition; disambiguated for multiple accepted offers and null `published_at` |
| INV-014 | Added that no vacancy-creation gate may reference `recruitment_outcomes` |
| INV-015 | Added that outbox dispatch happens only after the business transaction commits |
| INV-016 | `vacancy_versions` added to the append-only set |
| INV-018 | Rewritten as a full ownership exclusivity table plus the campus status-subset rule |
| INV-019 | Renamed `evaluations.stage_id` → `recruitment_stage_id`; enforcement moved to the model/service boundary |

---

## Open Questions Preserved

The five remaining questions remain open and unanswered. D-1 was subsequently closed by approved Product Owner decision. Verified field by field against the corrected model.

| # | Open question | Status | Verification |
| --- | --- | --- | --- |
| 1 | Final alumni verification integration source | **Open** | `candidate_verifications` keeps `student_number`, `program_study_id`, `graduation_year` Conditional and `source_reference` untyped. No integration entity exists. The new candidate entities added by RC-5 are all optional and introduce no verification dependency. |
| 2 | Minimum company legal documents by organization type | **Open** | INV-030 requires *at least one* `company_documents` record before verification submission but deliberately does not say which types, and no per-organization-type requirement matrix exists. |
| 3 | Salary mandatory/optional/display policy | **Open** | `salary_min`, `salary_max`, `salary_currency` remain nullable and classified "Optional / Pending Decision". No display-policy field was added. |
| 4 | Recruiter domain/subdomain | **Open** | `users` and `password_credentials` remain channel-neutral; no portal, tenant, or origin-domain field exists anywhere. |
| 5 | WhatsApp notification phase | **Open** | Only `notifications` and `email_outbox` exist. `users.phone` and `candidate_profiles.phone` are annotated as contact data implying no delivery channel. |
| 6 | Default first recruiter role / minimum active Company Admin | **Closed by approved Product Owner decision after this report** | First creator is active `COMPANY_ADMIN`; runtime last-admin protection enforces at least one active admin; subsequent roles remain explicit with no implicit default. |

**No correction introduced a field that assumes an answer to any of the six.** Four separate human-decision items (H-1 selector stage assignment, H-2 vacancy-level outcome, H-3 shared-document revocation, H-4 audit metadata collection) were likewise recorded in `ERD.md` and left unresolved.

---

## Cross-Document Consistency Result

**PASS.** Verified programmatically across `ERD.md`, `DATA_DICTIONARY.md`, and `DATA_INVARIANTS.md`.

| # | Check | Result |
| --- | --- | --- |
| 1 | Every Mermaid entity exists in the data dictionary | **PASS** — 49/49, zero orphans |
| 2 | Every dictionary entity exists in the ERD | **PASS** — 49/49, zero orphans |
| 3 | ERD domain table matches the dictionary exactly | **PASS** — 49/49, no difference in either direction |
| 4 | Every Mermaid relationship endpoint is a defined entity | **PASS** — zero undefined endpoints |
| 5 | Every `entity.field` referenced in invariants exists in the dictionary | **PASS** — zero dangling references |
| 6 | Invariant identifiers unique and every referenced `INV-nnn` defined | **PASS** — 34 defined, no duplicates, no undefined references |
| 7 | `recruitment_outcomes` XOR explicit in all three documents | **PASS** |
| 8 | Consent receiver XOR explicit in all three documents | **PASS** |
| 9 | External ATS cannot have applications | **PASS** — INV-024 |
| 10 | User role history representable | **PASS** — surrogate `id`, `revoked_by`, INV-025 |
| 11 | `companies.normalized_name` is **not** unique | **PASS** — no `UK` in the ERD; dictionary states "not unique"; INV-034 |
| 12 | Exactly one authoritative email uniqueness rule | **PASS** — `email_normalized` only; `email` carries none |
| 13 | FR-CAN-003 profile sections all have a home | **PASS** — education, experience, skills, organizations, certifications, links, work preferences |
| 14 | Campus/company vacancy ownership unambiguous | **PASS** — INV-018 exclusivity table |
| 15 | No SUBMITTED/DIAJUKAN stored state | **PASS** — INV-004 and value sets |
| 16 | `RESCHEDULED` not used as a current schedule state | **PASS** — INV-027 |
| 17 | Candidate identity, authorization, and verification separate | **PASS** — INV-028 |
| 18 | Time-to-Fill remains `published_at → offer_accepted_at` | **PASS** — no onboarding, contract, start-date, or first-working-day field exists in any of the 49 entities |
| 19 | No SQL, DDL, or vendor type introduced | **PASS** — no `VARCHAR`, `BIGINT`, `CREATE TABLE`, `SERIAL`, `jsonb`, or equivalent anywhere |
| 20 | No Laravel implementation code introduced | **PASS** — no `Schema::`, `$table->`, `Illuminate\`, model class, or artisan reference anywhere |
| 21 | All five remaining open questions still open; D-1 closed subsequently by approved Product Owner decision | **PASS** |

No field or entity carries contradictory rules across the three documents.

---

## Laravel Logical Implementability Result

**READY — logical model only.** No Laravel code, package, version, or database vendor was chosen. Every hazard the review raised (L-1 to L-11) is now either fixed in the model or recorded as an explicit invariant the architecture phase must honour.

| # | Review hazard | Status after correction |
| --- | --- | --- |
| L-1 | `user_roles` composite key broken by `revoked_at` | **Fixed.** Surrogate `id`; INV-025 states the at-most-one-active rule logically. Maps to an ordinary Eloquent model with a normal primary key, route binding, and policies. |
| L-2 | Attribute-carrying pivots without identifiers | **Fixed.** `candidate_saved_vacancies` and `candidate_skills` have surrogate identifiers; composite uniqueness retained. |
| L-3 | XOR pairs with no enforcement point | **Addressed.** INV-022 and INV-023 state the rules; the *Validation Boundaries* table requires enforcement on every write path — background jobs and administrative tooling included — not at the request boundary alone. Mechanism deferred. |
| L-4 | Opaque `value_reference` forcing engine-specific JSON queries | **Fixed.** RC-6 replaced it with typed conditional fields, so requirement queries are ordinary relational queries with no vendor-specific JSON path dependency. |
| L-5 | Cross-entity stage consistency unenforceable by FK | **Addressed.** INV-019 rewritten to require a model/service guard covering every write path. |
| L-6 | Partial (active-membership) uniqueness not portable | **Addressed.** Three conditional uniqueness rules — active role assignment, active membership, single accepted offer — are collected and flagged as requiring either a partial index or a generated column / application guard depending on the engine. Explicitly deferred to the database decision. |
| L-7 | Union status column allowing invalid campus states | **Fixed.** INV-018 forbids moderation-only statuses on campus vacancies; the dictionary states both sets on `current_status`. |
| L-8 | `unique:users,email` would target the wrong column | **Fixed.** INV-001 makes `email_normalized` the sole key and requires normalization before validation; the erroneous `UK` on `email` was removed. |
| L-9 | Polymorphic references carry no integrity | **Accepted and constrained.** INV-033 requires stable logical type names, never class paths, so audit history stays interpretable across refactoring. |
| L-10 | Soft deletes silently hiding recruitment history | **Addressed.** Forbidden on history-carrying entities without an explicit archival design; `anonymized_at` added so anonymized rows are distinguishable. |
| L-11 | Outbox rows dispatched before commit | **Addressed.** INV-015 requires dispatch only after the business transaction commits. |

**Remaining architecture-phase decisions**, all deliberately left open: the enforcement mechanism for the three conditional uniqueness rules and the two XOR rules; the physical representation of `Structured Data` fields (`vacancy_versions.snapshot`, `previous_snapshot`, `change_summary`, `payload_reference`, `options_definition`, `answer_value_reference`); foreign-key delete actions; and the retention/anonymization implementation. None of these is decided in this phase, and none is blocked by the logical model.

---

**End of correction report. `ERD_REVIEW.md` was not modified. BRD v1.1 and FSD v1.1 were not modified. No Stitch or design file was touched. No SQL, migration, ORM model, or application code was created, and no database vendor was selected.**
