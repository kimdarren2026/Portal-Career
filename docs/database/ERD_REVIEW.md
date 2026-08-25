# Portal Karir Kampus — Logical ERD Final Review

**Review date:** 24 August 2026
**Scope reviewed:** `docs/database/ERD.md`, `docs/database/DATA_DICTIONARY.md`, `docs/database/DATA_INVARIANTS.md`, `docs/database/README.md`
**Cross-checked against:** `docs/requirements/BRD_Portal_Karir_Kampus_v1.1.md`, `docs/requirements/FSD_Portal_Karir_Kampus_v1.1.md`
**Entities reviewed:** 47
**Nature of this document:** review findings only. No BRD/FSD change, no SQL, no migration, no ORM model, no backend/frontend code, no database vendor selection.

---

## Executive Result

**PASS WITH REQUIRED CORRECTIONS**

The logical model is structurally sound and materially faithful to BRD/FSD v1.1. The hardest design decisions — one application lifecycle per candidate+vacancy, External Apply as a separate event stream, verification decoupled from partnership, one central vacancies aggregate, consent as an independent auditable entity, transactional outbox — are all modelled correctly and defensibly. There is no fatal contradiction and no evidence of invented scope.

It is **not yet ready** to be frozen as the database baseline. There are **4 critical** and **9 major** corrections required, concentrated in four areas:

1. two nullable-dual-reference relationships (`recruitment_outcomes`, `consents`) have **no XOR rule** anywhere in the model;
2. nothing prevents an in-portal `applications` row against an `EXTERNAL_ATS` vacancy;
3. `user_roles` uses a composite key that is **broken by its own `revoked_at` field**;
4. FR-CAN-003 mandates candidate profile content (organisasi, sertifikasi, preferensi kerja, link profesional/portfolio) that has **no home in the model**.

None of these require a change to BRD/FSD. All are corrections to the logical model documents.

---

## Critical Findings

### C-1 — `recruitment_outcomes` has no XOR rule and four redundant references

`recruitment_outcomes` carries `application_id` (Conditional), `external_apply_event_id` (Conditional), `vacancy_id` (Yes), and `candidate_profile_id` (Yes). No invariant in `DATA_INVARIANTS.md` constrains this shape.

As written the model permits all of these impossible or ambiguous rows:

- both `application_id` and `external_apply_event_id` null → an outcome attached to nothing;
- both populated → an outcome claiming to be simultaneously in-portal and external;
- `vacancy_id` pointing at vacancy A while `application_id` points at an application on vacancy B;
- `candidate_profile_id` disagreeing with the candidate on the referenced application or external event.

`vacancy_id` and `candidate_profile_id` are fully derivable from either source reference, so every populated row carries two facts that can silently contradict their own parent.

**Required:** add an invariant enforcing exactly-one-of `application_id` / `external_apply_event_id`, and require the denormalized `vacancy_id` / `candidate_profile_id` to equal the values reachable through the chosen source. Recommended wording is in *Required Corrections* (RC-1).

### C-2 — `consents` receiving party is an unconstrained nullable dual reference

`receiving_company_id` and `receiving_organizational_unit_id` are both optional with no rule. FR-CONSENT-001 requires consent to *identify the receiving vacancy/company*, and FR-CONSENT-002 requires the stored record to carry "receiving company/unit". The model currently allows a consent record with **neither** receiving party — which fails the FSD requirement outright — and with **both**, which is meaningless.

This is the difference between a consent record that is legally defensible and one that is not, so it is critical rather than major.

**Required:** at most one receiving party always; exactly one whenever `consent_type` is application/data-sharing consent. See RC-2.

### C-3 — Nothing forbids an in-portal application against an `EXTERNAL_ATS` vacancy

INV-012 correctly states that `EXTERNAL_APPLY_STARTED` must not auto-create an `applications` row with `APPLIED`. But the inverse hole is open: no invariant requires `applications.vacancy_id` to reference a vacancy whose `application_method = IN_PORTAL`.

The consequence is exactly the outcome the FSD is trying to prevent — an `applications` row existing for an external-ATS vacancy, which then flows into funnel reporting and makes FR-EXT-004's required separation of "external apply started" versus "application" unreliable. INV-005 constrains campus vacancies to `IN_PORTAL`, but says nothing about which vacancies may receive applications.

**Required:** add the missing invariant. See RC-3.

### C-4 — `user_roles` composite key is contradicted by `revoked_at`

`ERD.md` and `DATA_DICTIONARY.md` both present `user_roles` as keyed on `(user_id, role_id)` — the dictionary marks both fields "composite key candidate" and the entity carries no `id`. The same entity also carries `assigned_at`, `assigned_by`, and `revoked_at`, and the dictionary describes it as "assignment **history**".

These two facts are mutually exclusive. Once a role is assigned, revoked, and later re-assigned, a second `(user_id, role_id)` row is required — and the composite key forbids it. The model therefore cannot represent the very history it claims to keep, and the only way to re-assign a revoked role is to destructively overwrite the revocation, which violates INV-016's append-only intent.

This directly affects the `FINAL_YEAR_STUDENT → ALUMNI` transition in §15, where candidate role assignments are expected to change over time.

**Required:** give `user_roles` a surrogate identifier and replace the composite key with an active-assignment uniqueness rule. See RC-4.

---

## Major Findings

### M-1 — `ERD.md` and `DATA_DICTIONARY.md` disagree on two uniqueness markers

| Field | `ERD.md` Mermaid | `DATA_DICTIONARY.md` | `DATA_INVARIANTS.md` |
| --- | --- | --- | --- |
| `users.email` | `UK` (unique) | no unique marker | INV-001 makes only `email_normalized` unique |
| `companies.normalized_name` | `UK` (unique) | "Search/deduplication candidate; final matching policy remains flexible" | not listed in the uniqueness table |

Both ERD markings are wrong, and the second is materially harmful. FR-COMP-001 specifies deduplication as a **flagging** process across five signals (normalized name, legal identifier, website domain, official email domain, phone), with merge performed only by an authorized role and audited. A hard unique constraint on `normalized_name` would instead hard-reject a legitimate second company whose name normalizes identically, and would pre-empt an unresolved matching policy.

**Required:** remove both `UK` markers from the ERD; keep `email_normalized` unique per INV-001; record `normalized_name` as an indexed duplicate-detection signal, not a constraint.

### M-2 — FR-CAN-003 profile content has no home in the model

FR-CAN-003 states the candidate profile minimum as: identitas dasar, kontak, domisili, pendidikan, pengalaman, **organisasi**, **sertifikasi**, keterampilan, **preferensi kerja**, **link profesional/portfolio**, CV utama.

Education, experience, and skills are modelled. The four bolded items are not modelled anywhere — `candidate_profiles` holds only `headline`, `phone`, `city`, `province`, `summary`, `current_candidate_type`.

Certificates can arguably be carried as `candidate_documents` of a certificate type, but that stores a file, not the structured certification data (issuer, credential number, validity) the profile implies — and organisational experience, work preferences, and professional/portfolio links have no representation at all.

**Required:** close the gap explicitly, either by adding the two missing child entities and the missing profile fields (recommended, RC-5) or by obtaining a written product decision that these four items are captured as free text. Do not leave the gap undocumented.

### M-3 — `vacancy_requirements.value_reference` as an opaque structured blob defeats its own purpose

Every requirement — EDUCATION, STUDY_PROGRAM, EXPERIENCE, SKILL, CERTIFICATION, OTHER_QUALIFICATION — collapses into one `Structured Data` field. FR-VAC-003 and FR-HR-002 both name "program studi" and "keterampilan" as first-class vacancy fields, and eligibility/matching work needs to answer "which vacancies require study program X".

With an opaque value the model cannot express that relationally, cannot enforce that a STUDY_PROGRAM requirement actually references a real `study_programs` row, and pushes the entire requirement semantics into application code. This is under-normalization disguised as flexibility, and it sits directly beside `geographic_areas` — an entity created *specifically* to normalize a much less important reference.

**Required:** keep the single `vacancy_requirements` table (subtype tables are correctly avoided) but add nullable typed references — `study_program_id`, `skill_id` — used when `requirement_type` is the matching type, with the free value retained for the unstructured types. See RC-6.

### M-4 — Geography is normalized in one place and free text in two others

`geographic_areas` exists and is referenced by `companies.province_geographic_area_id` / `city_geographic_area_id`. But `candidate_profiles.city` / `.province` are plain Strings and `vacancies.location` is a plain String.

FR-ONB-002 marks company Provinsi/kota as **Wajib** with "Master wilayah", so the entity is justified. The inconsistency means location-based search across vacancies and candidates — the most common filter in a career portal — cannot use the master at all, and the master exists to serve one entity.

**Required:** decide one way and apply it consistently. Either reference `geographic_areas` from candidate domicile and vacancy location too, or reduce the master to the company scope FSD actually mandates and stop presenting it as general geography. State the choice in `ERD.md`.

### M-5 — Mandatory moderation/verification reasons are modelled as optional

- FR-ONB-004: "Revision/rejection/suspension **wajib** memiliki reason."
- FR-VAC-006: "Revision/rejection **harus** memiliki kategori dan recruiter-visible note."

In the model, `company_verification_reviews.reason_category` and `vacancy_moderation_reviews.reason_category` are both Required = No and classified "Optional / Pending Decision"; `recruiter_visible_note` is Required = No in both.

Only the *taxonomy* of reason categories is genuinely undecided; the *presence* of a reason is mandated by the FSD today. Marking the field "Pending Decision" reads as if the requirement itself were open.

**Required:** change both fields to **Conditional — required when `action` is REQUEST_REVISION, REJECT, or SUSPEND**, and keep only the category vocabulary as pending.

### M-6 — Company profile fields FSD marks Wajib are modelled as optional with no condition

FR-ONB-002 marks these **Wajib**: organization type (master data), industry (master data), official email, address, province/city, legal documents. In the dictionary, `organization_type_id`, `industry_id`, `official_email`, `address`, and both geography references are all Required = No, with no "Conditional" note.

The dictionary's own convention states that Required means required at creation *unless the description says Conditional*. Because none of these say Conditional, the model currently asserts they are simply optional, which contradicts FR-ONB-002.

The underlying reality is a legitimate two-phase requirement: nothing is mandatory while the company is `DRAFT`; everything in FR-ONB-002 becomes mandatory at **Submit for Verification**.

**Required:** mark these fields **Conditional — required before `verification_status` may leave DRAFT/REVISION_REQUIRED for PENDING_VERIFICATION**, and add a matching invariant.

### M-7 — Multiple offers per application make Time-to-Fill ambiguous

`APPLICATIONS ||--o{ OFFERS` permits many offers per application, and `openings_count` permits many hires per vacancy. INV-013 defines Time-to-Fill as `offers.offer_accepted_at - vacancies.published_at` without saying **which** accepted offer when more than one exists, and `vacancies.published_at` is nullable.

FR-REP-004 gives the same formula and is equally silent, so this is a genuine model-level ambiguity, not a requirement the model failed to copy. Any two implementations will produce different KPI numbers.

**Required:** add an invariant that at most one offer per application may be in ACCEPTED state, and state the vacancy-level rule explicitly — recommended: **Time-to-Fill uses the earliest `offer_accepted_at` among accepted offers for that vacancy**, and is undefined while `published_at` is null. The choice of earliest versus latest is a business decision; it must be written down either way.

### M-8 — Shared-document snapshot metadata is optional, so historical evidence is not protected

`application_documents.snapshot_name`, `snapshot_storage_reference`, and `snapshot_checksum` are all Required = No.

FR-CONSENT-003 requires the application to store the list of documents the candidate chose **at submit time**. With snapshot fields optional, the record can degrade to a bare pointer at `candidate_documents.id`. If the candidate later replaces the file behind that reference, or an authorized retention process archives it, the historical recruitment record silently changes meaning — the recruiter's evaluation would then refer to a document that no longer exists in the form reviewed.

INV-010 protects *access*; nothing protects *content integrity*.

**Required:** make `snapshot_name` and `snapshot_storage_reference` required at share time, keep `snapshot_checksum` optional-but-recommended, and add an invariant that a `candidate_documents` row referenced by a non-revoked `application_documents` row cannot be hard-deleted — only archived/anonymized by the authorized retention process.

### M-9 — `vacancy_types` master and `vacancies.vacancy_type` enum are two sources of the same truth

`vacancies.vacancy_type` is a required Enum (CAMPUS_EMPLOYMENT / COMPANY_EMPLOYMENT / INTERNSHIP) and `vacancy_types` is a separate master catalog, described as "Optional catalog only if implementation chooses configurable vacancy types instead of a fixed controlled value set". The ERD also draws `VACANCY_TYPES ||--o{ VACANCIES : optionally_classifies` — but no `vacancy_type_id` field exists on `vacancies` in the dictionary, so the drawn relationship has no attribute to travel on.

FR-VAC-001 does say "tipe tambahan melalui master data", so a catalog is eventually justified — but shipping both mechanisms simultaneously guarantees drift, and the three MVP types are fixed and behaviour-bearing (INV-005 keys campus behaviour off the type).

**Required:** for MVP keep the enum as the single source of truth and **defer** `vacancy_types` until a configurable-type change request exists. Remove the unattached ERD relationship.

---

## Minor Findings

| # | Finding | Recommended handling |
| --- | --- | --- |
| m-1 | `candidate_verifications.status` uses PENDING/VERIFIED/REJECTED/DATA_MISMATCH; FR-CAN-004 defines NOT_VERIFIED/PENDING/VERIFIED/MISMATCH-MANUAL_REVIEW. `REJECTED` is added and `NOT_VERIFIED` dropped. | Reconcile to the FSD vocabulary, or document that absence-of-record means NOT_VERIFIED and justify REJECTED as distinct from MISMATCH. |
| m-2 | FSD FR-APP-003 names the event `APPLICATION_REOPENED`; the model uses `REOPENED`. | Align to the FSD name for traceability. |
| m-3 | FR-APP-005 requires a **visibility note** on every status change; `application_status_histories` has no such field. | Add `visibility` / `candidate_visible_note`. |
| m-4 | FR-SEL-001 lists **lampiran** (attachments) among schedule fields; `selection_schedules` has none. | Add an attachment reference, or state that attachments are carried as instructions/links. |
| m-5 | `selection_schedules.status` includes `RESCHEDULED`. A rescheduled appointment is still scheduled; RESCHEDULED is an *event*, already carried by `selection_schedule_histories.event_type`. | Drop `RESCHEDULED` from the status set; keep it as a history event only. |
| m-6 | `email_outbox.status` uses `FAILED_RETRYABLE`; FR-NOTIF-001 writes `FAILED → RETRY_SCHEDULED`. | Harmless consolidation — record the mapping so the FSD wording remains traceable. |
| m-7 | `evaluations.stage_id` versus `selection_schedules.recruitment_stage_id` name the same target differently. | Use `recruitment_stage_id` in both. |
| m-8 | `candidate_educations`, `candidate_work_experiences`, `candidate_skills`, `candidate_saved_vacancies` are classified "Derived Technical Requirement". FR-CAN-003 and FSD §4.2 (Lowongan Tersimpan) name all four directly. | Reclassify as **BRD/FSD Required**. The entities are correct; only the provenance label is wrong. |
| m-9 | `roles` catalog omits `PUBLIC`, which FSD §3.1 lists. | Correct as-is (an unauthenticated visitor holds no stored role) — add a one-line note so the omission reads as deliberate. |
| m-10 | `application_documents.revoked_at` introduces candidate revocation of an already-shared document. No FSD requirement defines this. | Flag as scope not present in FSD v1.1 — see *Human Decision* below. |
| m-11 | `vacancies.open_at` / `close_at` are Required = Yes, including for DRAFT. | Make Conditional — required before leaving DRAFT. |
| m-12 | `applications.reopen_count` is Required = Yes; FSD §6.5 calls it "Opsional". | Harmless tightening; document it as a derived cache (see *Application Integrity Review*). |

---

## Entity Classification

| Entity | Classification | Keep/Merge/Defer | Reason |
| --- | --- | --- | --- |
| users | CORE MVP | Keep | Global login identity; FSD §6.1. |
| password_credentials | SUPPORTING MVP | Keep | Separates credential material from profile; FSD §6.1. |
| email_verification_tokens | SUPPORTING MVP | Keep | FR-AUTH-003/004 one-time token metadata. |
| password_reset_tokens | SUPPORTING MVP | Keep | FR-AUTH-007; INV-021 correctly forbids raw tokens. |
| roles | CORE MVP | Keep | FSD §3.1 approved role catalog. |
| user_roles | CORE MVP | Keep — **fix key (C-4)** | Assignment history; composite key currently breaks re-assignment. |
| candidate_profiles | CORE MVP | Keep | FR-CAN-003; one profile per user is correct. |
| candidate_verifications | CORE MVP | Keep | FR-CAN-004; correctly kept flexible for open question 1. |
| candidate_documents | CORE MVP | Keep | FR-CAN-005 private documents. |
| candidate_saved_vacancies | SUPPORTING MVP | Keep | FSD §4.2 Lowongan Tersimpan — reclassify provenance (m-8). |
| candidate_educations | SUPPORTING MVP | Keep | FR-CAN-003 "pendidikan"; multi-value, needs its own rows. |
| candidate_work_experiences | SUPPORTING MVP | Keep | FR-CAN-003 "pengalaman"; multi-value. |
| candidate_skills | SUPPORTING MVP | Keep | FR-CAN-003 "keterampilan"; depends on the `skills` decision below. |
| companies | CORE MVP | Keep | FSD §6.1; verification state correctly on the company. |
| company_members | CORE MVP | Keep | FR-COMP-004; the object-ownership anchor for recruiter policies. |
| company_documents | CORE MVP | Keep | FR-ONB-002 legal documents; typed, no NIB-only assumption. |
| company_verification_reviews | CORE MVP | Keep | FR-ONB-004; append-only decision history. |
| partnerships | CORE MVP | Keep | FR-COMP-003; correctly separate from verification. |
| vacancies | CORE MVP | Keep | Central aggregate; see *Vacancy Review*. |
| vacancy_versions | CORE MVP | Keep | FR-VAC-007 mandates before/after versions. |
| vacancy_requirements | SUPPORTING MVP | Keep — **restructure (M-3)** | Right table, wrong value modelling. |
| vacancy_documents | SUPPORTING MVP | Keep | FSD §6.1. |
| vacancy_screening_questions | SUPPORTING MVP | Keep | FR-VAC-003 / FR-HR-002 screening questions. |
| vacancy_moderation_reviews | CORE MVP | Keep | FR-VAC-006 moderation history. |
| applications | CORE MVP | Keep | The lifecycle spine; correctly one row per candidate+vacancy. |
| application_status_histories | CORE MVP | Keep | FR-APP-005; append-only reconstruction source. |
| application_documents | CORE MVP | Keep — **tighten (M-8)** | FR-CONSENT-003 explicit per-application sharing. |
| application_screening_answers | SUPPORTING MVP | Keep | Answer rows to vacancy questions; correct 1:1 uniqueness. |
| recruitment_stages | CORE MVP | Keep | FSD §6.1; per-vacancy configurable stages. |
| selection_schedules | CORE MVP | Keep | FR-SEL-001; the FSD `interviews` entity, correctly generalized. |
| selection_schedule_histories | TECHNICAL DERIVATION | Keep | FR-SEL-001 requires change/cancel history — justified, see §10. |
| evaluations | CORE MVP | Keep | FR-SEL-002 / FR-HR-007. |
| evaluation_items | SUPPORTING MVP | Keep | FR-HR-007 requires multi-criterion kriteria/bobot/skor — needs child rows. |
| offers | CORE MVP | Keep — **constrain (M-7)** | FR-SEL-003/004/005; authoritative Time-to-Fill endpoint. |
| recruitment_outcomes | SUPPORTING MVP | **Keep + Simplify** | Justified by FSD §4.3 "Outcome Rekrutmen" and FR-EXT-004 — see §13. |
| consents | CORE MVP | Keep — **constrain (C-2)** | FR-CONSENT-002 independently auditable record. |
| external_apply_events | CORE MVP | Keep | FR-EXT-002; correctly separate from applications. |
| notifications | CORE MVP | Keep | FSD §6.1 in-app notifications. |
| email_outbox | CORE MVP | Keep | FR-NOTIF-001 transactional outbox. |
| audit_logs | CORE MVP | Keep | FR-AUD-001. |
| organizational_units | SUPPORTING MVP | Keep | FR-HR-002 requires unit/fakultas/bagian on campus vacancies. |
| study_programs | SUPPORTING MVP | Keep | FR-VAC-003, FR-HR-002, FR-CAN-004 all reference program studi. |
| industries | SUPPORTING MVP | Keep | FR-ONB-002 marks Industri as Wajib master data. |
| organization_types | SUPPORTING MVP | Keep | FR-ONB-002 marks Bentuk badan/jenis organisasi as Wajib master data. |
| vacancy_types | **SHOULD BE DEFERRED** | **Defer** | Duplicates `vacancies.vacancy_type`; FR-VAC-001 extension is post-MVP (M-9). |
| skills | **QUESTIONABLE / HUMAN DECISION** | Decide | Marked "Optional / Pending Decision" yet `candidate_skills.skill_id` is a required reference — see below. |
| geographic_areas | SUPPORTING MVP | Keep — **apply consistently (M-4)** | FR-ONB-002 "Master wilayah" justifies it; current usage is one-sided. |

**`skills` — the decision required.** The dictionary marks the catalog "Optional / Pending Decision" while `candidate_skills.skill_id` is Required = Yes. If the catalog is not adopted, candidate skills become unrecordable, and FR-CAN-003 mandates them. Two coherent options: (a) adopt `skills` as a real master and drop the "Optional" label, or (b) drop `skills` and let `candidate_skills` hold a normalized free-text name. Option (a) is recommended because `vacancy_requirements` of type SKILL benefits from the same reference (M-3). What cannot stand is the current state, where a required field points at an optional entity.

---

## Over-Engineering Review

The model is **not** broadly over-engineered. 47 entities for this scope is proportionate, and the recurring justification pattern — append-only history, multi-value profile data, per-vacancy configuration — is sound. Assessed against the areas flagged for special attention:

| Area | Verdict |
| --- | --- |
| Candidate education | **Justified.** FR-CAN-003 names it; multi-valued with dates and level. Not a premature abstraction. |
| Candidate work experience | **Justified.** Same reasoning; `is_current` plus nullable `end_date` is conventional and correct. |
| Candidate skills | **Justified as a concept**, but blocked on the `skills` catalog decision above. |
| Geographic areas | **Justified but inconsistently applied** (M-4). The entity is not the problem; using it for one entity out of three is. |
| Vacancy requirements | **Correctly avoids subtype tables**, but over-abstracts the value into an opaque blob (M-3). This is the clearest case of abstraction chosen over usable structure. |
| Vacancy versions | **Justified and required.** See §6. |
| Schedule histories | **Justified.** See §10. |
| Evaluation items | **Justified.** FR-HR-007 lists kriteria/bobot/skor as a repeating group; folding them into `evaluations` would force a fixed criteria count. |
| Recruitment outcomes | **Justified but over-referenced** (C-1). Keep the entity, remove the redundancy. |
| Master-data entities | **Mostly justified.** `industries`, `organization_types`, `study_programs`, `organizational_units`, `geographic_areas` are all named as master data by the FSD. `vacancy_types` is the single genuine duplicate concept (M-9). |

**Premature abstractions found:** one (`vacancy_requirements.value_reference`).
**Duplicate concepts found:** one (`vacancy_types` versus `vacancies.vacancy_type`).
**Unnecessary normalization found:** none. No entity should be merged purely to reduce table count, and none is recommended for merging.

---

## Application Integrity Review

**Verdict: the core is correct.** `candidate_profile_id + vacancy_id` does represent one lifecycle, and the model backs this properly.

| Check | Result |
| --- | --- |
| One lifecycle per candidate+vacancy | **Confirmed.** INV-007 declares `UNIQUE(candidate_profile_id, vacancy_id)` explicitly covering the *full* lifecycle, not only active applications. The dictionary marks both fields "unique with" each other. This is a full unique constraint, not a partial/filtered one, so it holds on any database vendor. |
| Reapply reopens the existing row | **Confirmed.** INV-008 states reopen updates metadata and appends a REOPENED event and "never creates application number two". This matches FR-APP-003.4 exactly. |
| No second application row required | **Confirmed.** No entity or relationship provides an alternative path to a second row. |
| `reopen_count` sufficient if retained | **Yes.** It is a derived cache of the count of REOPENED history events. Retaining it is fine; it must be documented as derived so history stays authoritative. |
| `last_reopened_at` logically correct | **Yes.** It equals the `occurred_at` of the most recent REOPENED event. Also a derived cache. |
| History can reconstruct the lifecycle | **Yes.** `application_status_histories` carries from/to status, from/to stage, `event_type`, actor, reason, and `occurred_at`, with an APPLICATION_CREATED anchor event. The full sequence is reconstructable. One field is missing — the FR-APP-005 visibility note (m-3). |
| Withdrawn applications remain stored | **Yes.** INV-009 is explicit and matches FR-APP-006. |
| Rejected applications remain stored | **Yes.** REJECTED is a `current_status` value, not a deletion, and INV-016/retention guidance forbid destructive removal. |
| History is append-only | **Yes.** INV-016 names `application_status_histories` among the append-only records and requires correction-by-later-record. |

**Could the model accidentally allow duplicate applications?**

Not through the uniqueness rule — that part is airtight. But **two adjacent holes exist**, and per the review instruction both are flagged:

1. **C-3 (critical):** an application may be created against an `EXTERNAL_ATS` vacancy. This does not duplicate a row, but it duplicates the *concept* — the same candidate-vacancy pairing would then exist both as an `applications` row and as `external_apply_events`, which is precisely the conflation FR-EXT-002 forbids.
2. **Derived-value drift (major-adjacent):** `reopen_count` and `last_reopened_at` have no stated invariant tying them to the history events. Nothing declares history authoritative, so an implementation could increment the counter without appending the event, or vice versa, and the lifecycle would no longer reconstruct. RC-7 adds the rule.

**One documentation ambiguity, not a model defect.** FSD §6.5 says "satu lifecycle **aktif** per candidate+vacancy", which read alone could permit a second row once the first reaches a terminal state. FR-APP-002 ("satu lifecycle application per candidate_id + vacancy_id") and FR-APP-003.4 ("tidak membuat application kedua") are unambiguous and authoritative, and the model follows them. No FSD change is proposed. The ERD should carry a one-line note recording that §6.5's looser phrasing was read against FR-APP-002/003, so the tighter constraint is a documented reading rather than an undeclared assumption.

---

## External Apply Review

**Verdict: correctly separated.**

- `external_apply_events` is a genuinely independent entity keyed on candidate + vacancy with its own `event_type`, `started_at`, `destination_url_reference`, and a `confirmation_status` that the dictionary explicitly labels "not an application status".
- INV-012 states that `EXTERNAL_APPLY_STARTED` **must not** automatically create an `applications` row with `APPLIED`, matching FR-EXT-002.
- There is no relationship, field, or invariant that would cause a click-through to write into `applications`. The separation is structural, not merely conventional.
- The entity carries no unique constraint on candidate+vacancy, which is correct: it is an event stream and repeat clicks are legitimate events.
- `confirmation_status` / `confirmation_source` / `confirmed_by` / `confirmed_at` match FR-EXT-003's three legitimate confirmation paths (company, candidate, lawful ATS integration) without implying any integration exists. **No two-way ATS integration is invented anywhere in the model.**
- `consent_id` on the event correctly supports FR-EXT-002's "consent tracking bila diwajibkan".
- FR-EXT-004's required reporting split (views / started / confirmed / interview / offering / hired) is derivable, since started and confirmed live on the event and interview/offering/hired live on the application side.

**The one defect is C-1**, and it is exactly the ambiguity the review anticipated. `recruitment_outcomes` currently holds `application_id` nullable **and** `external_apply_event_id` nullable with **no XOR rule stated anywhere**. Both-null and both-populated rows are permitted, and the additional `vacancy_id` / `candidate_profile_id` columns can contradict whichever parent is chosen.

**Recommended logical rule (RC-1):** exactly one of the two source references must be present; the outcome's vacancy and candidate must equal those reachable through that source. This makes the entity able to serve internal applications and external activity without impossible integrity states, and without inventing an integration.

---

## Company & Partnership Review

**Verdict: correct, and one of the strongest parts of the model.**

| Check | Result |
| --- | --- |
| Verification ≠ Partnership | **Confirmed.** INV-003 states a VERIFIED company may have no active partnership and may still create vacancies; INV-020 forbids persisting Mitra Kampus as an alternative verification state. Both match FR-ONB-006 and FR-COMP-003. |
| VERIFIED company may post without partnership | **Confirmed.** The posting gate (INV-002) reads `companies.verification_status` only. `partnerships` is nowhere in the gate. |
| Verification history reconstructable | **Yes.** `company_verification_reviews` is append-only and stores `action`, `from_status`, `to_status`, reviewer, notes, and `reviewed_at` — a complete transition chain. |
| `VERIFIED → SUSPENDED → VERIFIED` representable | **Yes.** The `action` set includes SUSPEND and RESTORE, and `from_status`/`to_status` capture both hops. This matches FR-ONB-004's "RESTORE dari SUSPENDED ke VERIFIED". The company itself keeps `verified_at` and `suspended_at`, and the review trail carries the sequence. |
| REJECTED and SUSPENDED kept distinct | **Yes.** They are separate `verification_status` values with separate actions (REJECT vs SUSPEND) and separate meanings per FR-COMP-002. Nothing in the model conflates them. |
| `company_members` supports object-level ownership | **Yes.** `company_id` + `user_id` + `company_role` + `status` + `revoked_at` gives Laravel policies exactly the active-membership check they need, and FR-COMP-004's "penghapusan membership tidak menghapus audit/history" is honoured by `revoked_at` rather than deletion. |

**Corrections in this area:** M-5 (review reasons must be conditionally required) and M-6 (FR-ONB-002 Wajib fields must be Conditional, not optional). Neither affects the verification/partnership separation itself.

**One implementability note:** `company_members(company_id, user_id)` **active-membership** uniqueness is a *partial* uniqueness rule. It expresses the intent correctly, but partial unique indexes are not portable across all candidate databases. Record it now as a constraint that must survive vendor selection — enforced by a partial index where supported, and otherwise by a generated/derived column or an application-level guard. This is flagged for the architecture phase, not decided here.

---

## Vacancy Review

**Verdict: one central `vacancies` aggregate is still the correct design. Keep it.**

The three types (COMPANY_EMPLOYMENT, CAMPUS_EMPLOYMENT, INTERNSHIP) share the overwhelming majority of their attributes, their entire status machinery, and — critically — the same downstream graph: `applications`, `recruitment_stages`, `selection_schedules`, `evaluations`, `offers`. Splitting them would fork the application pipeline in two, which is exactly what the ERD's own principle ("campus and company recruitment do not use disconnected application systems") rules out and what FR-HR-005 assumes when Admin Kepegawaian manages applicants through the same screens. No alternative is cleaner.

**Ownership invariants:**

| Check | Result |
| --- | --- |
| Company vacancy requires `company_id` | **Confirmed** — dictionary marks it Conditional/"Required for company-owned vacancies"; INV-018 requires it plus active membership authorization. |
| `organizational_unit_id` does not define company ownership | **Confirmed** — the ownership table states it "is not the business owner" for company vacancies. |
| Campus vacancy identifies campus ownership via `organizational_unit_id` | **Weak — correction required.** ERD says it "**may** identify the campus owner/unit" and the dictionary marks it Conditional without a condition. FR-HR-002 lists "unit/fakultas/bagian" among the **minimum** campus vacancy fields, so for `ownership_type = CAMPUS` it is mandatory, not optional. See RC-8. |
| Campus vacancy does not require `company_id` | **Confirmed** — INV-018 and the ownership table both state company is not the campus owner. |
| Polymorphic ambiguity avoided | **Yes.** `ownership_type` is an explicit discriminator with two typed nullable FKs — simpler and more checkable than a `owner_type`/`owner_id` morph pair, and it maps cleanly to real foreign keys. This is the right call; keep it. |
| `CAMPUS_EMPLOYMENT → IN_PORTAL`, external ATS impossible | **Confirmed.** INV-005 forbids EXTERNAL_ATS for campus vacancies, matching FR-HR-003. |
| Only four target audiences | **Confirmed.** INV-006 and the controlled value set list exactly PUBLIC, ALUMNI_ONLY, FINAL_YEAR_AND_ALUMNI, INTERNAL. No "Mahasiswa Aktif" or "Fresh Graduate" appears anywhere. |
| No persistent `SUBMITTED` / `DIAJUKAN` | **Confirmed.** INV-004 forbids it explicitly; the controlled value sets omit it; ERD states "Submit is a transition action to PENDING_REVIEW". Matches FR-VAC-004. |

Two further gaps to close: **C-3** (applications must only attach to IN_PORTAL vacancies) and **M-9** (`vacancy_types` duplication). Also note that `vacancies.current_status` carries two different value sets depending on ownership — correct as a model, but the campus set must be constrained so a campus vacancy can never reach PENDING_REVIEW/APPROVED/REJECTED, which are moderation states that do not apply to it. No invariant currently says so; fold it into RC-8.

---

## Candidate Privacy & Consent Review

**Verdict: privacy separation is correct; consent needs one structural fix.**

**Document privacy — confirmed sound.** `candidate_documents` are owned by the candidate profile and carry no recruiter-facing relationship. The **only** path from a vacancy owner to a candidate file is `application_documents`, which requires an explicit per-application share. INV-010 states this and adds the object-authorization qualifier. Nothing grants blanket document access as a side effect of viewing `candidate_profiles` — a recruiter reaching a profile obtains no document rights at all. This matches FR-CAN-005 and FR-CONSENT-003, and no candidate document is publicly visible anywhere in the model.

**Snapshot metadata — insufficient (M-8).** The snapshot fields exist, which shows the risk was anticipated, but all three are optional. Historical recruitment evidence is therefore only as stable as the candidate's own file management. Making `snapshot_name` and `snapshot_storage_reference` mandatory at share time, plus a no-hard-delete rule for referenced documents, closes it.

**Consent — independently auditable, with one defect.** `consents` is a first-class entity and INV-011 explicitly rejects a Boolean on `applications`. Against FR-CONSENT-002 it records:

| Required by FR-CONSENT-002 | Present |
| --- | --- |
| who consented | `user_id` ✔ |
| application | `application_id` ✔ |
| vacancy | `vacancy_id` ✔ |
| receiving party | `receiving_company_id` / `receiving_organizational_unit_id` ✔ — **but unconstrained (C-2)** |
| purpose | `purpose` ✔ |
| consent version | `consent_version` + `consent_text_hash_reference` ✔ |
| timestamp | `consented_at` ✔ |
| revocation | `revoked_at` ✔ |

Every required element is present. The **nullable-dual-reference problem is real**: with both receiving fields optional and no rule, a consent record can name no recipient at all, which would not satisfy FR-CONSENT-001's requirement to identify the receiving vacancy/company. RC-2 states the constraint.

**One scope observation (m-10):** `application_documents.revoked_at` lets a candidate un-share a document after submission. FSD v1.1 defines consent revocation but says nothing about revoking a shared document, and revocation could remove evidence underpinning a completed evaluation. This is listed under *Human Decision* rather than treated as a defect.

---

## Recruitment Workflow Review

**Verdict: the separation between application status and recruitment stage is correct and should be kept.**

| Concept | Entity | Meaning |
| --- | --- | --- |
| **Application Status** | `applications.current_status` | The **lifecycle / business state** of the candidate's relationship to the vacancy. A fixed, candidate-facing vocabulary of ten values defined by FR-APP-004. It is the same for every vacancy in the system and drives candidate-visible labels, reporting funnels, and terminal-state rules. |
| **Recruitment Stage** | `recruitment_stages` + `applications.current_stage_id` | The **configurable operational selection step** for one specific vacancy. Owner-defined, ordered, and named freely — "Wawancara HR", "Tes Praktik", "Wawancara Dekan". It expresses *where in this vacancy's process* the candidate sits. |

The worked example holds exactly: an application may have `current_status = INTERVIEW` while `current_stage_id` points at a stage named "Wawancara HR", and a second interview round would advance the stage without changing the status. FR-APP-004's closing line ("Internal stage dapat lebih detail tetapi tidak otomatis diekspos sebagai status utama kandidat") and FR-HR-007's ("Hasil internal tidak otomatis menjadi candidate-facing status tanpa action transisi eksplisit") both require precisely this separation, and the model delivers it.

**No duplicate or conflicting truth exists**, because the two carry different information: status is a closed system-wide vocabulary, stage is open per-vacancy configuration. Neither is derivable from the other.

**Does `current_stage_id` belong on `applications`? Yes — keep it.** It is a denormalized pointer to the current position, exactly parallel to `current_status`, and both are reconstructable from `application_status_histories` (which stores `from_stage_id`/`to_stage_id` alongside `from_status`/`to_status`). The history remains authoritative; the two current-value fields are caches that make the applicant list queryable without walking history. INV-019 already requires the stage to belong to the application's vacancy, which is the essential guard. Add the same derived-cache statement recommended in RC-7.

**Candidate-visible labels can remain simpler than internal stages — confirmed.** `recruitment_stages.candidate_visible_label` is optional and distinct from the internal `name`, so an owner may run five internal stages while the candidate sees "Proses Seleksi". Combined with FR-APP-004's fixed candidate status vocabulary, candidates never see internal stage granularity unless the owner deliberately exposes it.

---

## Selection Schedule Model (§10)

**Verdict: `selection_schedules` plus a separate `selection_schedule_histories` is the cleaner option. Keep both.**

Support for the required capabilities:

| Requirement | Support |
| --- | --- |
| online / on-site | `method` enum + `location` + `meeting_url` ✔ |
| timezone | `timezone` (named reference, not an offset — correct for DST safety) ✔ |
| PIC | `pic_user_id` ✔ |
| reschedule | `selection_schedule_histories.event_type = RESCHEDULED` with `previous_snapshot` ✔ |
| cancelled | status CANCELLED + history event ✔ |
| completed | status COMPLETED + history event ✔ |
| no-show | status NO_SHOW + history event ✔ |
| attachments (FR-SEL-001 "lampiran") | **missing** (m-4) |

**Why a separate history entity rather than append-only schedule rows.** Append-only schedule rows would mean every reschedule creates a new `selection_schedules` row, which then requires a "which row is current" discriminator, breaks the natural one-schedule-per-application-per-stage reading, and multiplies the rows that `notifications` and candidate-facing screens must filter. The current shape — one mutable current schedule plus an immutable event log carrying `previous_snapshot`, actor, reason, and `occurred_at` — preserves history equally well while keeping the current appointment trivially queryable. FR-SEL-001 requires only that "Perubahan/pembatalan mempertahankan history", which this satisfies.

**One correction (m-5):** drop `RESCHEDULED` from `selection_schedules.status`. A rescheduled appointment is still SCHEDULED at its new time; RESCHEDULED describes the transition, and it already exists as a history `event_type`. Keeping it in both places creates two ways to express the same fact and makes "is this appointment upcoming?" ambiguous.

---

## Evaluation Model (§11)

**Verdict: `evaluations` + `evaluation_items` is justified. Keep both.**

- **Supports campus recruitment without a fixed scoring algorithm.** `evaluations.total_score` is optional, `evaluation_items.weight` and `.score` are both optional and explicitly labelled "if an approved rubric applies", and `recommendation` is a separate optional judgement. Nothing computes, aggregates, or ranks. An evaluator can submit comments and a recommendation with no numbers at all.
- **`evaluation_items` is justified as a separate entity.** FR-SEL-002 and FR-HR-007 both list kriteria, bobot, skor, komentar as a repeating group — a variable number of criteria per evaluation. Folding them into `evaluations` would require either a fixed criteria count or an opaque blob; a child table is the conventional and correct answer.
- **No psychometric testing or ranking engine is introduced.** There is no test, item-bank, percentile, normalization, or candidate-comparison entity anywhere in the model, and ERD.md explicitly excludes a psychometric-test engine, AI recommendation, and candidate ranking.
- Access restriction ("Akses dibatasi role", FR-SEL-002) is supported through `evaluator_user_id` plus the SELECTOR role and FR-HR-006's stage-scoped assignment. Note that the model does **not** record *which stages a selector is assigned to* — FR-HR-006 says "Admin Kepegawaian menetapkan tahap/data yang dapat diakses". This is listed under *Human Decision*: either a selector-assignment relationship is needed, or assignment is handled outside the data model.

**One correction (m-7):** rename `evaluations.stage_id` to `recruitment_stage_id` to match `selection_schedules`.

---

## Offering / Outcome Review

### Offering (§12)

**Verdict: correct.** `offers.status` covers exactly DRAFT, SENT, PENDING_RESPONSE, ACCEPTED, REJECTED, EXPIRED — matching FR-SEL-003 value for value. Supporting timestamps are coherent: `offered_at`, `sent_at`, `response_deadline`, `responded_at`, `offer_accepted_at`, plus `note` and `document_reference`.

**`offer_accepted_at` is the authoritative Time-to-Fill endpoint — confirmed.** INV-013 defines Time-to-Fill as `offers.offer_accepted_at - vacancies.published_at` and explicitly rejects onboarding, start-work, and contract-signing dates. Reviewed against the whole model: **no onboarding date, contract-signing date, start date, or first-working-day field exists in any of the 47 entities.** The formula cannot be computed any other way, which is the strongest possible guarantee. This matches FR-REP-004 exactly.

**One ambiguity to resolve (M-7):** which accepted offer, when an application has several or a vacancy has `openings_count > 1`, and what the value is while `published_at` is null.

### Outcome (§13)

**Recommendation: KEEP AS ENTITY — with SIMPLIFY applied.**

Reasoning, weighed against merging it away:

- **Final status and offering are sufficient for in-portal recruitment alone.** For an IN_PORTAL vacancy, `applications.current_status = HIRED` plus the accepted offer already carries the truth, and `recruitment_outcomes` adds nothing for that case in isolation.
- **But they are not sufficient for the required reporting surface.** FSD §4.3 lists **Outcome Rekrutmen** as a first-class recruiter portal module, FR-REP-002 requires Career Center to see "incomplete outcome", FR-NOTIF-002 defines an "Outcome belum lengkap" email trigger, and FR-EXT-004 requires reporting on confirmed external applications. Outcome is an explicitly requested artefact, not an inferred one.
- **External activity has no application row to carry status.** For an EXTERNAL_ATS vacancy the portal holds only `external_apply_events`, whose `confirmation_status` records *whether the candidate applied*, not *what the employer decided*. Without `recruitment_outcomes` there is nowhere to record a hire that happened through an external ATS — and that is precisely the "external/company recruitment" reporting the review brief requires.
- **"Incomplete outcome" needs a recordable absence.** A reminder for a missing outcome requires a place where the outcome is expected and currently absent. Deriving that purely from application statuses would conflate "no decision reported" with "candidate rejected".

Merging it into `applications` would therefore break external reporting; deferring it would remove a named FSD module. Keep it.

**Simplify as follows:** apply the C-1 XOR rule, and treat `vacancy_id` / `candidate_profile_id` as derived-and-validated rather than independent facts. That reduces the entity from four independent references to one authoritative source reference plus two guarded denormalizations.

**Outcome incompleteness must never block new vacancy creation — confirmed.** INV-014 states missing outcome "may generate a notification/reminder but must not block creation of a new vacancy", matching FR-NOTIF-004. The vacancy creation gate (INV-002) reads only `companies.verification_status`; `recruitment_outcomes` appears nowhere in it. There is no path by which an incomplete outcome could gate posting.

**One open design question (Human Decision):** `recruitment_outcomes.candidate_profile_id` is required, so every outcome is candidate-level. A vacancy-level outcome — "closed, no suitable candidate", "requirement cancelled" — cannot be expressed. FR-REP-002's "incomplete outcome" monitoring may need that. Flagged, not solved.

---

## Authorization Readiness

**Verdict: the ownership data Laravel Policies need is present.** The model correctly stops short of encoding authorization in relationships, while still supplying every ownership fact required for object-level checks.

| Scope | Ownership path available | Ready |
| --- | --- | --- |
| **Recruiter — own company objects only** | `users` → `company_members` (active, `revoked_at` null) → `company_id` → `vacancies.company_id` → `applications.vacancy_id` → all children. FR-VAC/APP objects all reach a company in one chain. | ✔ |
| **Candidate — own profile/applications only** | `users` → `candidate_profiles` (unique per user) → `applications`, `candidate_documents`, `external_apply_events`, `candidate_saved_vacancies`, `consents.user_id`. | ✔ |
| **Career Center — verification/moderation scope** | Role via `user_roles` (CAREER_CENTER_STAFF/MANAGER) + the reviewable object sets `companies` and company-owned `vacancies`. FSD §3.3's boundary (may monitor, may not decide candidate acceptance on a company's behalf) is expressible because moderation entities are separate from `applications`. | ✔ |
| **Admin Kepegawaian — campus recruitment scope** | Role HR_ADMIN + `vacancies.ownership_type = CAMPUS` (optionally narrowed by `organizational_unit_id`) → campus applications and their children. | ✔ |
| **Selector — assigned stages only** | Role SELECTOR exists; `evaluations.evaluator_user_id` records who evaluated. **No entity records which stages a selector is assigned to**, which FR-HR-006 requires. | ✖ — see *Human Decision* |
| **Auditor — read-only reporting** | Role AUDITOR + `audit_logs`. | ✔ |
| **Super Admin — emergency/audit** | Role SUPER_ADMIN; all actions land in `audit_logs`. | ✔ |

INV-017 states the object-ownership rules in prose and matches FSD §3.3. The relevant correction here is **C-4**: because `user_roles` cannot represent re-assignment after revocation, role-based scoping degrades over time for any user whose roles change — which includes every candidate who transitions from student to alumni.

---

## User Role Transitions (§15)

**The transition works. The sources of truth need one explicit ruling.**

**`FINAL_YEAR_STUDENT → ALUMNI` is representable without loss — confirmed.** `candidate_profiles.current_candidate_type` is a mutable enum on a profile that is unique per user; `applications` reference `candidate_profile_id`, not the type. So the transition requires **no** new user, **no** new candidate profile, and loses **no** application history. This is correct and is one of the model's better decisions.

**But three entities currently describe overlapping facts:**

1. `roles` codes `CANDIDATE_EXTERNAL`, `CANDIDATE_STUDENT_FINAL_YEAR`, `CANDIDATE_ALUMNI` (via `user_roles`);
2. `candidate_profiles.current_candidate_type` (EXTERNAL / FINAL_YEAR_STUDENT / ALUMNI);
3. `candidate_verifications` (type ALUMNI / FINAL_YEAR_STUDENT with status VERIFIED).

Nothing in `DATA_INVARIANTS.md` says which one governs eligibility. That is a live conflict risk: a candidate could hold role `CANDIDATE_ALUMNI`, `current_candidate_type = FINAL_YEAR_STUDENT`, and no VERIFIED alumni verification — three answers to one question. FR-VAC-002 makes eligibility for ALUMNI_ONLY depend on being a **verified** alumnus, so picking the wrong source silently grants access.

**Recommended ruling — these must not be conflated:**

| Concern | Authoritative source | Rule |
| --- | --- | --- |
| **A. Authorization role** | `roles` + `user_roles` | Governs *what screens and actions* a user may reach. The three candidate role codes are retained because FSD §3.1 defines them, but they must be **derived from and kept synchronized with** `current_candidate_type` — never edited independently as a second opinion. |
| **B. Candidate identity / category** | `candidate_profiles.current_candidate_type` | The candidate's *self-declared* current category. Single mutable value; changes on transition. Not proof of anything. |
| **C. Verified eligibility** | `candidate_verifications` (type + `status = VERIFIED`) | The *only* admissible basis for target-audience gating. `ALUMNI_ONLY` and `FINAL_YEAR_AND_ALUMNI` eligibility must read this, never A or B. |

RC-9 states this as an invariant. Note also that `current_candidate_type` has no change history of its own; the transition is auditable only through `audit_logs` and the timestamps on `candidate_verifications`. That is acceptable for MVP provided the candidate-type change is named explicitly in the FR-AUD-001 audit action list.

---

## Email / Outbox (§16)

**Verdict: correct. No corrections required beyond naming (m-6).**

- **SMTP failure cannot roll back business transactions — confirmed.** INV-015 states the business transaction remains committed when delivery fails, matching FR-ONB-003.6 ("kegagalan email tidak membatalkan submit") and FR-NOTIF-001. The outbox row is written inside the business transaction; delivery happens afterwards in a worker. This is the transactional-outbox pattern applied correctly.
- **Retry fields present:** `attempt_count` ✔, `next_attempt_at` ✔ (supports backoff), `status = FAILED_RETRYABLE` ✔, `DEAD_LETTER` terminal state ✔, `last_error_summary` ✔.
- **Related business object:** `related_object_type` + `related_object_id` ✔ — supports the FR-NOTIF-002 trigger table and admin resend.
- **No SMTP credentials in the data model — confirmed.** `email_outbox` holds `recipient`, `template_reference`, and `payload_reference` only. INV-015 states explicitly that it contains no SMTP secrets, and `last_error_summary` is qualified "no credentials". FR-NOTIF-005's SMTP configuration (host, port, encrypted secret) is **not** modelled as an entity anywhere — correctly left to configuration/architecture, with changes captured in `audit_logs`.

`max attempt configurable` (FR-NOTIF-003) is a configuration value rather than a row field, which is right.

---

## Audit Log (§17)

**Verdict: sufficient, with one field to add for completeness.**

`audit_logs` carries `actor_user_id` (nullable for system actions), `action`, `object_type`, `object_id`, `change_summary`, `correlation_id`, `ip_address`, `user_agent_device_metadata`, `created_at`. Checked against every category in the review brief and FR-AUD-001:

| Category | Covered | Notes |
| --- | --- | --- |
| authentication events | ✔ | login success/failure/lock as `action` values; no user row needed for failed logins since `actor_user_id` is nullable. |
| role changes | ✔ | plus `user_roles` history once C-4 is fixed. |
| company verification | ✔ | mirrored by `company_verification_reviews`. |
| vacancy moderation | ✔ | mirrored by `vacancy_moderation_reviews`. |
| application transitions | ✔ | mirrored by `application_status_histories`. |
| reopen | ✔ | |
| withdrawal | ✔ | |
| document access / download | ✔ | the polymorphic object reference targets `candidate_documents` / `application_documents`. |
| schedules | ✔ | mirrored by `selection_schedule_histories`. |
| evaluations | ✔ | |
| offerings | ✔ | |
| reports / exports | ✔ | FR-REP-005 requires export auditing; `action` + `change_summary` covers it. |
| SMTP configuration changes | ✔ | FR-NOTIF-005; no secret is stored, only that a change occurred. |

**No password, token, or secret is required in any audit payload — confirmed.** `change_summary` is explicitly "Redacted change summary; no credential material", and the dictionary header states passwords, verification tokens, and SMTP secrets are excluded. Token entities store `token_hash` only and are never audit subjects.

**Two additions recommended:** (a) name the candidate-type change (§15) explicitly among audited actions; (b) `ip_address` and `user_agent_device_metadata` are correctly marked "only if policy permits collection" — record that this gate is a policy decision so it is not silently resolved during implementation.

---

## Retention & Delete Safety (§18)

**Verdict: correct in intent and appropriately non-vendor-specific.**

`DATA_INVARIANTS.md` states "Do not blindly use CASCADE DELETE" and recommends RESTRICT/preserve for users with recruitment history, companies with verification/partnership/membership history, vacancies with applications or reviews, applications with history/consent/offers/outcomes, and candidate documents shared through applications. Nothing in the model permits ordinary cascade deletion of application history, consent, offers, audits, company verification history, or vacancy moderation history. FR-AUD-002's rule that normal recruiters and candidates have no unrestricted destructive delete is honoured.

**Recommended logical handling — confirmed as adequate for this phase:**

| Treatment | Logical meaning | Status in the documents |
| --- | --- | --- |
| **Archived** | Record retained and excluded from active views. `archived_at` exists on `candidate_documents` and `vacancy_documents`. | Present |
| **Anonymized** | Personal identifiers replaced while the recruitment event and its history survive. | Described in prose; no field designated |
| **Disabled** | Account cannot authenticate; history intact. `users.status = DISABLED` + `disabled_at`. | Present |
| **Deleted under authorized policy** | Explicitly authorized, logged, evidence-preserving removal. | Described; correctly deferred |

Two gaps worth closing without choosing an implementation: **(a)** anonymization has no designated marker, so a reader cannot tell an anonymized row from a real one — name a logical `anonymized_at` concept; **(b)** M-8's rule that a candidate document referenced by a live share cannot be hard-deleted belongs in this section as well as in the FK guidance.

Soft-delete semantics are correctly labelled "a logical proposal, not a selected physical implementation".

---

## Laravel Implementability

The model maps cleanly to Laravel overall. Ordinary Eloquent relationships, migrations, Policies, Form Requests, transactions, and queued jobs all fit without contortion. The patterns below would become awkward or dangerous and should be addressed before the baseline is frozen. **No Laravel code, package, or version is chosen here.**

| # | Pattern | Why it is awkward or dangerous in Laravel | Recommended change |
| --- | --- | --- | --- |
| L-1 | **`user_roles` composite key with `revoked_at`** (C-4) | Eloquent assumes a single-column key. A pivot carrying history that must also be re-assignable is a model, not a plain pivot, and composite keys break `find`, route binding, and relationship save methods. The key also makes re-assignment impossible at all. | Surrogate `id` + active-assignment uniqueness. |
| L-2 | **`candidate_saved_vacancies`, `candidate_skills` have no identifier** | Workable as `belongsToMany` pivots, but any future need for model events, policies, or soft-deletes on them requires a rewrite. | Add surrogate `id` unless they will stay pure pivots forever; keep the composite unique index either way. |
| L-3 | **Two XOR nullable reference pairs** (`recruitment_outcomes`, `consents`) | Laravel has no first-class cross-column check constraint in migrations, and validation lives in Form Requests that are easy to bypass from a queued job or console command. Unenforced, these produce the impossible rows in C-1/C-2. | Enforce in three places: a database check constraint where the chosen engine supports it, a model-level guard that fires regardless of entry point, and Form Request validation. Decide the mechanism at architecture time. |
| L-4 | **`vacancy_requirements.value_reference` as an opaque blob** (M-3) | Becomes a JSON cast. Querying "vacancies requiring study program X" then means engine-specific JSON path queries — precisely the vendor-specific dependency this phase forbids — or full-table scans. | Add typed nullable references beside the free value. |
| L-5 | **`applications.current_stage_id` cross-entity rule** (INV-019) | A foreign key can prove the stage exists but not that it belongs to the application's vacancy. Nothing in Eloquent enforces it, and `updateOrCreate` / mass assignment paths bypass validators easily. | Keep INV-019, and require it be enforced in a service/model guard rather than only in a Form Request. Same for `screening_question_id`, `recruitment_stage_id`, `evaluations.stage_id`. |
| L-6 | **Partial (active-membership) uniqueness on `company_members`** | Not portable: expressible as a partial unique index on some engines, requiring a generated column or application guard on others. Choosing wrongly at migration time is expensive to reverse. | Record it as a portability constraint for the vendor decision; do not assume partial index support. |
| L-7 | **Union status column on `vacancies`** (company set ∪ campus set) | A single Enum cast covering both sets means an invalid combination (campus vacancy in PENDING_REVIEW) is representable and will not be caught by casting. | Constrain by `ownership_type` in a model guard; fold into RC-8. |
| L-8 | **`users.email` and `users.email_normalized`** | Laravel's idiomatic `unique:users,email` validation would target the wrong column, silently permitting case-variant duplicates that INV-001 forbids. | State in the model docs that uniqueness is on `email_normalized` and that normalization happens before validation. Removing the erroneous `UK` on `email` (M-1) is a prerequisite. |
| L-9 | **Polymorphic references** on `notifications`, `email_outbox`, `audit_logs` | Maps naturally to `morphTo`, but carries no referential integrity, and a deleted or renamed target class leaves dangling type strings. This is acceptable and normal for audit/notification tails. | Keep. Record that the type value must be a stable logical name, not a class path, so refactoring cannot corrupt history. |
| L-10 | **Soft deletes versus preserved history** | If soft-deletes are later applied to parents such as `vacancies` or `companies`, Eloquent will neither cascade nor restrict — child history silently disappears from default queries while remaining in the tables, which reads as data loss. | Keep the RESTRICT/archive guidance; state that soft-delete must not be applied to entities carrying recruitment history without an explicit archival design. |
| L-11 | **`email_outbox` worked by queued jobs** | Correct pattern. The only hazard is enqueuing before commit, which would let a worker read a row that does not exist yet. | Note that outbox rows must be dispatched after the business transaction commits. Architecture concern, recorded here so it is not lost. |

**Nothing in the model is structurally hostile to Laravel.** The two genuine hazards are L-1 (a key that cannot represent its own history) and L-3 (integrity rules with no enforcement point), both already captured as C-4, C-1, and C-2.

---

## Open Questions Preserved

The five remaining questions are unresolved and are **not** answered by this review. D-1 was subsequently closed by approved Product Owner decision. Verified against the current FSD open-question section.

| # | Open question | Still open | Does any schema field assume an answer? |
| --- | --- | --- | --- |
| 1 | Final technical source of alumni verification (SSO / master sync / NIM + comparison / combination) | ✔ Open | **No.** `candidate_verifications` keeps `student_number`, `program_study_id`, `graduation_year` all *Conditional* and `source_reference` untyped. No integration entity exists. One caveat: the status set adds `REJECTED` and drops `NOT_VERIFIED` versus FR-CAN-004 (m-1) — a vocabulary divergence, not an assumed source. |
| 2 | Minimum legal documents per organization type | ✔ Open | **No.** `company_documents.document_type` is an open typed enum; no NIB-only rule and no per-organization-type requirement matrix is embedded. `companies.legal_identifier` is optional and explicitly "no NIB-only mandate". |
| 3 | Whether salary range is mandatory, optional, or hidden per vacancy type | ✔ Open | **No.** `salary_min`, `salary_max`, `salary_currency` are all nullable and classified "Optional / Pending Decision". No display-policy field exists — correctly, since that is a policy not a datum. |
| 4 | Same or separate domain/subdomain for the recruiter portal | ✔ Open | **No.** `users` and `password_credentials` are channel-neutral; nothing records an origin domain, tenant, or portal discriminator. |
| 5 | Whether WhatsApp notification enters a later phase | ✔ Open | **No.** Only `notifications` and `email_outbox` exist; no WhatsApp channel, template, or delivery entity. `users.phone` and `candidate_profiles.phone` are ordinary contact fields required independently by FR-CAN-003, not a channel assumption. |
| 6 | Default role of the first recruiter, and minimum-one-active-Company-Admin policy | ✔ **Closed by approved Product Owner decision after this review** | **Yes.** The first creator is active `COMPANY_ADMIN`; runtime last-admin protection enforces at least one active admin; subsequent roles remain explicit with no implicit default. |

**Conclusion: no schema field accidentally assumes an answer to any of the six.** This is a genuine strength of the current model and should be preserved through the corrections — in particular, RC-5 must not introduce a mandatory certification/organisation structure that pre-empts question 1, and RC-6 must not make `study_program_id` mandatory on requirements in a way that pre-empts question 2's document policy.

---

## Required Corrections Before Architecture

Stated as *what* must change. **`ERD.md` has not been edited.** No SQL, migration, or backend code is provided.

### Critical

**RC-1 — `recruitment_outcomes` XOR and derived-reference consistency.** Add to `DATA_INVARIANTS.md`:

> **INV-022 — Outcome Source Exclusivity.** Exactly one of `recruitment_outcomes.application_id` or `recruitment_outcomes.external_apply_event_id` must be present; never both, never neither. `vacancy_id` and `candidate_profile_id` are derived denormalizations and must equal the vacancy and candidate reachable through the populated source reference.

Also update `DATA_DICTIONARY.md` so both source fields state the exclusivity in their descriptions, and mark `vacancy_id` / `candidate_profile_id` as derived-and-validated.

**RC-2 — `consents` receiving-party constraint.** Add:

> **INV-023 — Consent Receiving Party.** At most one of `consents.receiving_company_id` or `consents.receiving_organizational_unit_id` may be present. Exactly one must be present when `consent_type` concerns application/data sharing. The receiving party must be consistent with the referenced vacancy's owner where a vacancy is referenced.

**RC-3 — Applications only against in-portal vacancies.** Add:

> **INV-024 — In-Portal Application Only.** An `applications` row may reference only a vacancy whose `application_method` is `IN_PORTAL`. Vacancies with `EXTERNAL_ATS` are tracked exclusively through `external_apply_events`.

**RC-4 — `user_roles` key.** In `ERD.md` and `DATA_DICTIONARY.md`: add a surrogate `id` primary key to `user_roles`; remove the `(user_id, role_id)` composite-key designation; add to the uniqueness table: *at most one non-revoked assignment per `(user_id, role_id)`*. Historical revoked assignments must be able to coexist with a later active one.

### Major

**RC-5 — Close the FR-CAN-003 profile gap.** Add `candidate_certifications` and `candidate_organizational_experiences` as candidate-profile child entities, and add professional/portfolio link and work-preference fields to `candidate_profiles`. Keep every added field optional so no answer to open question 1 is implied. *Alternative:* obtain a written product decision that these four items are captured as free text in `summary`, and record that decision in `ERD.md`. Do not leave the gap silent.

**RC-6 — Restructure `vacancy_requirements`.** Retain the single table and the `requirement_type` discriminator; add nullable `study_program_id` and `skill_id` references used when the type matches; retain a free value for unstructured types. Remove the requirement that all semantics live inside one opaque structured field.

**RC-7 — Declare derived caches explicitly.** Add:

> **INV-025 — Derived Application Fields.** `applications.current_status`, `current_stage_id`, `reopen_count`, and `last_reopened_at` are derived caches of `application_status_histories`, which is authoritative. Every change to a cached field must append a corresponding history event in the same transaction. `reopen_count` equals the count of `APPLICATION_REOPENED` events and `last_reopened_at` equals the most recent such event's `occurred_at`.

**RC-8 — Campus vacancy ownership and status set.** Change `vacancies.organizational_unit_id` to **Conditional — required when `ownership_type = CAMPUS`** (per FR-HR-002's minimum field list), and extend INV-018 so that a campus vacancy may never hold a moderation-only status (`PENDING_REVIEW`, `REVISION_REQUIRED`, `APPROVED`, `REJECTED`).

**RC-9 — Rule the three candidate-eligibility sources.** Add:

> **INV-026 — Candidate Type, Role, and Eligibility.** `candidate_profiles.current_candidate_type` is the candidate's self-declared category. Candidate role codes in `user_roles` govern authorization only and must be kept synchronized with `current_candidate_type`; they are never an independent source. Target-audience eligibility for `ALUMNI_ONLY` and `FINAL_YEAR_AND_ALUMNI` is determined **only** by a `candidate_verifications` record of the matching type with `status = VERIFIED`.

**RC-10 — Uniqueness markers.** Remove `UK` from `users.email` and from `companies.normalized_name` in the `ERD.md` Mermaid block. Keep `email_normalized` unique. Record `normalized_name` as an indexed duplicate-detection signal supporting FR-COMP-001's flag-and-review process, explicitly **not** a constraint.

**RC-11 — Mandatory review reasons.** Change `reason_category` and `recruiter_visible_note` on `company_verification_reviews` and `vacancy_moderation_reviews` to **Conditional — required when `action` is REQUEST_REVISION, REJECT, or SUSPEND**. Keep only the category vocabulary marked as pending decision.

**RC-12 — Company profile Wajib fields.** Mark `organization_type_id`, `industry_id`, `official_email`, `address`, `province_geographic_area_id`, `city_geographic_area_id` as **Conditional — required before `verification_status` may transition to `PENDING_VERIFICATION`**, and add a matching invariant. This reconciles the model with FR-ONB-002 without making them mandatory at DRAFT.

**RC-13 — Offer uniqueness and Time-to-Fill disambiguation.** Add an invariant that at most one offer per application may be `ACCEPTED`. Extend INV-013 to state which accepted offer defines vacancy-level Time-to-Fill (recommended: the earliest `offer_accepted_at` among that vacancy's accepted offers) and that the metric is undefined while `published_at` is null.

**RC-14 — Shared-document snapshot integrity.** Make `application_documents.snapshot_name` and `snapshot_storage_reference` required at share time. Add an invariant that a `candidate_documents` row referenced by a non-revoked `application_documents` row cannot be hard-deleted, only archived or anonymized by the authorized retention process.

**RC-15 — Defer `vacancy_types`.** Remove `vacancy_types` from the MVP entity set and remove the unattached `VACANCY_TYPES ||--o{ VACANCIES` relationship from the ERD. Record it in the "Future Extension — Not MVP" section as the mechanism for FR-VAC-001's "tipe tambahan melalui master data".

**RC-16 — Resolve the `skills` contradiction.** Either adopt `skills` as a real master (removing the "Optional / Pending Decision" label) or allow `candidate_skills` to carry a normalized free-text name. A required reference to an optional entity cannot stand. Adoption is recommended so `vacancy_requirements` can share it under RC-6.

**RC-17 — Apply geography consistently.** Decide whether `geographic_areas` governs candidate domicile and vacancy location as well as company address, or is scoped to companies only, and apply the decision to `candidate_profiles.city`/`.province` and `vacancies.location`. Record the decision in `ERD.md`.

### Minor

**RC-18** — Apply the twelve minor items m-1 to m-12 as listed in *Minor Findings*, in particular: add the FR-APP-005 visibility note; rename `REOPENED` to `APPLICATION_REOPENED`; drop `RESCHEDULED` from `selection_schedules.status`; rename `evaluations.stage_id` to `recruitment_stage_id`; reclassify the four mislabelled candidate entities as BRD/FSD Required; make `vacancies.open_at`/`close_at` Conditional.

**RC-19** — Add a note to `ERD.md` recording that FSD §6.5's phrase "satu lifecycle **aktif**" was read against FR-APP-002 and FR-APP-003.4, and that full `(candidate_profile_id, vacancy_id)` uniqueness is the resulting documented reading. Per the source-of-truth rule this is a recorded reading, not a requirement change; if the business intends otherwise, it must be raised as a change request against the FSD rather than resolved in the model.

**RC-20** — Add a designated logical `anonymized_at` concept to the retention section so an anonymized record is distinguishable from a live one.

### Human Decision Required — not to be resolved by the model

1. **Selector stage assignment.** FR-HR-006 requires Admin Kepegawaian to set which stages/data a selector may access. No entity records this. Decide whether a selector-assignment relationship is added or assignment is handled outside the data model.
2. **Vacancy-level outcome.** `recruitment_outcomes` is candidate-level only. Decide whether "closed, no suitable candidate" must be recordable.
3. **`application_documents.revoked_at`.** Candidate revocation of an already-shared document is not defined by FSD v1.1 and could remove evidence underpinning a completed evaluation. Decide whether to keep the field, and if so what revocation means for historical evidence.
4. **`candidate_verifications` status vocabulary.** Reconcile the model's PENDING/VERIFIED/REJECTED/DATA_MISMATCH with FR-CAN-004's NOT_VERIFIED/PENDING/VERIFIED/MISMATCH-MANUAL_REVIEW.
5. **Audit metadata collection.** Whether `ip_address` and device metadata may be collected is a policy question the model correctly leaves open.

---

## Final Recommended Entity Count

**48 logical entities**, from the current 47:

| Change | Effect |
| --- | --- |
| Defer `vacancy_types` (RC-15) | −1 |
| Add `candidate_certifications` (RC-5) | +1 |
| Add `candidate_organizational_experiences` (RC-5) | +1 |
| **Net** | **47 → 48** |

No entity is recommended for merging. The count rises slightly because the model is closing a requirements gap, not because it is growing in ambition — and the one entity removed is the only genuine duplicate concept found. If the RC-5 alternative is chosen (free-text capture with a recorded product decision), the final count is **46**.

The remaining corrections change constraints, field optionality, and classifications rather than entity structure — which is the expected shape of a review at this stage, and the reason the executive result is *pass with required corrections* rather than *fail*.

---

**End of review. No BRD/FSD was modified, no SQL or migration was produced, no ORM model or application code was written, and no database vendor was selected.**
