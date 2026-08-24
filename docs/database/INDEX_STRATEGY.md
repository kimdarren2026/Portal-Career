# Index Strategy — Portal Karir Kampus

**Status:** Proposed — awaiting approval
**Date:** 24 August 2026 · **Engine:** PostgreSQL 16+ · **Logical source:** revision **1.1-C3** · **Companion to:** `DATABASE_SCHEMA.md`

Indexes are specified **per named workload**, not per column. Every index below exists because a query in the frozen API contract needs it.

**Speculative indexes are excluded on purpose.** An unused index costs write throughput on every insert and update, consumes cache the working set needs, and misleads the next reader into thinking a query pattern exists. Where a workload might need an index later, it is listed in §8 as deferred with the signal that would justify it.

---

## 1. Classification

| Class | Purpose | Created |
| --- | --- | --- |
| **A** | Primary and unique keys | Automatically by the constraint |
| **B** | Foreign-key lookup | Only where the reverse traversal is a real query path — §2 |
| **C** | Queue and operational scanning | Outbox, scheduler, idempotency, exports |
| **D** | Common filters | The twelve named workloads |
| **E** | Reporting support | Aggregate and funnel queries |
| **F** | Partial uniqueness | `POSTGRESQL_CONSTRAINTS.md` §2 — also serve as lookup indexes |
| **G** | Search | §6 |

> **PostgreSQL does not auto-index foreign keys.** This is the most common source of slow deletes and slow reverse joins in a Postgres schema, and it is why §2 is explicit rather than assumed.

---

## 2. Foreign-Key Indexes — Class B

Created **only** where the child is looked up by parent in a real query, or where a `RESTRICT` delete check would otherwise scan the child table.

| Table | Index | Serves |
| --- | --- | --- |
| `applications` | `idx_applications_vacancy_id` | Applicant lists |
| `applications` | `idx_applications_candidate_profile_id` | *Lamaran Saya* |
| `application_status_histories` | `idx_ash_application_occurred` on `(application_id, occurred_at DESC)` | History timeline, newest first |
| `application_documents` | `idx_application_documents_application_id` | Documents shared with an application |
| `application_documents` | `idx_application_documents_candidate_document_id` | **`RESTRICT` check on candidate document delete (INV-032)** — without it, every delete attempt scans |
| `application_screening_answers` | covered by `uq_application_screening_answers_app_question` | |
| `selection_schedules` | `idx_selection_schedules_application_id` | Schedules for an application |
| `selection_schedule_histories` | `idx_ssh_schedule_occurred` on `(selection_schedule_id, occurred_at DESC)` | Schedule history |
| `evaluations` | `idx_evaluations_application_id` | Evaluations for an application |
| `evaluation_items` | `idx_evaluation_items_evaluation_id` | Items of an evaluation |
| `offers` | `idx_offers_application_id` | Offers for an application |
| `consents` | `idx_consents_application_id` | Consent for an application |
| `consents` | `idx_consents_user_id` | A user's consent record |
| `external_apply_events` | `idx_eae_candidate_profile_id` | *Aktivitas Lamaran Eksternal* |
| `external_apply_events` | `idx_eae_vacancy_id` | External reporting |
| `vacancies` | `idx_vacancies_company_id` | Recruiter vacancy list |
| `vacancies` | `idx_vacancies_organizational_unit_id` | Campus vacancy list |
| `vacancy_requirements` | `idx_vacancy_requirements_vacancy_sort` on `(vacancy_id, sort_order)` | Ordered display |
| `vacancy_screening_questions` | `idx_vsq_vacancy_sort_active` on `(vacancy_id, sort_order) WHERE active` | Active questions in order |
| `vacancy_documents` | `idx_vacancy_documents_vacancy_id` | |
| `vacancy_versions` | `uq_vacancy_versions_vacancy_version` | Also serves retrieval |
| `vacancy_moderation_reviews` | `idx_vmr_vacancy_reviewed` on `(vacancy_id, reviewed_at DESC)` | Moderation history |
| `recruitment_stages` | `idx_recruitment_stages_vacancy_sort` on `(vacancy_id, sort_order)` | Stage list in order |
| `company_members` | `idx_company_members_user_id` | **`COMPANY_SCOPE` resolution — one of the hottest lookups in the system**, run on nearly every recruiter request |
| `company_documents` | `idx_company_documents_company_id` | |
| `company_documents` | `idx_company_documents_superseded_by` on `(superseded_by_document_id) WHERE superseded_by_document_id IS NOT NULL` | **`RESTRICT` check on the supersede self-FK (INV-038)** — without it, deleting any document scans the table. Also serves chain traversal |
| `company_documents` | `idx_company_documents_company_current` on `(company_id) WHERE superseded_at IS NULL` | The **current** evidence set for a verification package — excludes superseded history, which grows monotonically |
| `company_verification_reviews` | `idx_cvr_company_reviewed` on `(company_id, reviewed_at DESC)` | Verification history |
| `partnerships` | `idx_partnerships_company_status` on `(company_id, status)` | Derived `mitra_kampus_active` |
| `candidate_*` child tables | `idx_<table>_candidate_profile_id` on each of the six collections | `PUT` sync reads the whole collection by parent |
| `candidate_documents` | `idx_candidate_documents_profile_type` on `(candidate_profile_id, document_type)` | Document picker during apply |
| `candidate_verifications` | `idx_candidate_verifications_profile_type_status` on `(candidate_profile_id, verification_type, status)` | **Eligibility check on every apply (INV-028)** |
| `user_roles` | `uq_user_roles_user_role_active` | Also serves role resolution |
| `notifications` | §3 | |
| `audit_logs` | §7 | |

**Deliberately not indexed:** actor columns that are written but never used as a query entry point — `assigned_by`, `revoked_by`, `invited_by`, `uploaded_by`, `created_by`, `confirmed_by`. They are `SET NULL` or `RESTRICT` on delete, and account deletion is a rare authorized retention operation where a sequential scan is acceptable.

---

## 3. The Twelve Named Workloads — Class D

### 3.1 Public vacancy search — the highest-volume read

```
idx_vacancies_public_listing
  ON vacancies (published_at DESC)
  WHERE current_status = 'PUBLISHED' AND target_audience <> 'INTERNAL'
```

**Column ordering rationale:** the default listing is "most recently published first", and the predicate is *always* the same — `PUBLISHED`, non-`INTERNAL`. Putting those in the index predicate rather than the key keeps the index to only the rows the public can ever see, which is a fraction of the table once expired and closed vacancies accumulate. Ordering by `published_at DESC` inside the partial index means the default page is an index scan with **no sort**.

```
idx_vacancies_public_filters
  ON vacancies (vacancy_type, employment_type, workplace_mode, city_geographic_area_id)
  WHERE current_status = 'PUBLISHED'
```

Supports the FSD §4.1 filter combinations. **Ordering follows expected selectivity**: `vacancy_type` narrows most (three values across the whole corpus), then employment type, then workplace mode, then city. A leading `city_geographic_area_id` would be wrong — most searches do not filter by city, and a leading column that is usually absent makes the index unusable for those queries.

`idx_vacancies_close_at ON vacancies (close_at) WHERE current_status = 'PUBLISHED'` — scheduler expiry sweep (FR-VAC-008).
`idx_vacancies_open_at ON vacancies (open_at) WHERE current_status = 'SCHEDULED'` — scheduled publication sweep.

### 3.2 Company verification queue

```
idx_companies_verification_queue
  ON companies (verification_status, created_at DESC)
```
Status leads because the queue is *always* filtered by it (default `PENDING_VERIFICATION`), then ordered by age.

### 3.3 Vacancy moderation queue

```
idx_vacancies_moderation_queue
  ON vacancies (current_status, created_at DESC)
  WHERE ownership_type = 'COMPANY'
```
Partial on `COMPANY` because **campus vacancies are never moderated** (INV-018) — excluding them keeps the queue index to only rows that can ever appear in it.

### 3.4 Recruiter applicant list

```
idx_applications_vacancy_status_applied
  ON applications (vacancy_id, current_status, first_applied_at DESC)
```
`vacancy_id` leads — the list is always scoped to one vacancy; status is the common secondary filter; the tail supports the default ordering without a sort.

### 3.5 Candidate *Lamaran Saya*

```
idx_applications_candidate_applied
  ON applications (candidate_profile_id, first_applied_at DESC)
```

### 3.6 Schedule lists

```
idx_selection_schedules_app_starts ON selection_schedules (application_id, starts_at)
idx_selection_schedules_upcoming
  ON selection_schedules (starts_at)
  WHERE status = 'SCHEDULED'
```
The second serves "upcoming schedules" on the recruiter and Kepegawaian dashboards (FR-REP-001, FR-REP-003), partial because completed and cancelled appointments never appear there.

### 3.7 Notifications unread

```
idx_notifications_user_unread
  ON notifications (user_id, created_at DESC)
  WHERE read_at IS NULL
```
**Partial on unread** because the badge count and unread list are the hot path, and read notifications become the overwhelming majority over time. A full `(user_id, read_at)` index would grow without bound for a query that only ever wants the unread slice.

### 3.8 Email outbox worker — Class C

```
idx_email_outbox_due
  ON email_outbox (next_attempt_at)
  WHERE status IN ('PENDING','FAILED_RETRYABLE')
```
The sweep runs every minute; the partial predicate keeps the index to only rows that can be due. `SENT` rows — eventually most of the table — are excluded entirely.

```
idx_email_outbox_dead_letter ON email_outbox (created_at DESC) WHERE status = 'DEAD_LETTER'
```
Small, and directly serves the **required** dead-letter monitoring alert (`DEPLOYMENT_ARCHITECTURE.md` §5).

### 3.9 Selector assigned-candidate scoping

```
idx_ssa_selector_active
  ON selection_stage_assignments (selector_user_id, recruitment_stage_id)
  WHERE revoked_at IS NULL
```
**Column order is deliberately the reverse of `uq_selection_stage_assignments_stage_selector_active`.** The unique index probes *by stage* to enforce INV-037; this one starts *from the logged-in selector* to find their stages, which is the direction every authorization-scoped list query travels. Both are needed — same columns, different access paths.

### 3.10 Audit filters — §7

### 3.11 Time-to-Fill and reporting — §5

### 3.12 Company duplicate detection — §6

---

## 4. Operational Indexes — Class C

| Index | Purpose |
| --- | --- |
| `uq_idempotency_keys_scope` | Replay lookup and concurrent-execution guard (DB-1) |
| `idx_idempotency_keys_expires_at ON idempotency_keys (expires_at)` | TTL cleanup sweep in bounded batches |
| `idx_export_jobs_status_requested ON export_jobs (status, requested_at)` | Worker pickup and admin list (DB-2) |
| `idx_export_jobs_requested_by ON export_jobs (requested_by_user_id, requested_at DESC)` | A user's own exports |
| `idx_export_jobs_expires_at ON export_jobs (expires_at) WHERE status = 'COMPLETED'` | Expiry sweep |
| `idx_sessions_last_activity ON sessions (last_activity)` | Laravel session GC |

---

## 5. Reporting — Class E

FR-REP-001 to FR-REP-004 run over the transactional tables (ADR-013). No separate analytics store, no stored KPI counter.

| Report | Index |
| --- | --- |
| Application funnel | `idx_applications_vacancy_status_applied` (§3.4) |
| Offering acceptance | `idx_offers_accepted ON offers (offer_accepted_at) WHERE status = 'ACCEPTED'` |
| **Time-to-Fill** | `idx_offers_accepted` joined to `vacancies.published_at` via `idx_vacancies_public_listing` |
| External started vs confirmed | `idx_eae_vacancy_confirmation ON external_apply_events (vacancy_id, confirmation_status)` |
| Incomplete outcomes | `idx_recruitment_outcomes_created ON recruitment_outcomes (created_at DESC)` |
| Verified companies | `idx_companies_verification_queue` (§3.2) |
| Active partnerships | `idx_partnerships_company_status` (§2) |

**Time-to-Fill remains derived, never stored:** `offers.offer_accepted_at − vacancies.published_at`, earliest accepted offer per vacancy, undefined while `published_at` is null (INV-013, INV-031). **No materialized metric column exists** — a stored counter would drift, which is the defect INV-026 exists to prevent.

**Views and materialized views: deferred.** A plain view is acceptable to name a funnel query. A **materialized** view is justified only when a measured dashboard query is too slow, and it must then carry an explicit refresh cadence and a `computed_at` label in the UI. Introducing one now would add a staleness problem to solve a performance problem nobody has measured.

---

## 6. Public Search and Company Deduplication — Class G

### Public vacancy search: **`ILIKE` with a trigram index, deferred**

| Option | Assessment |
| --- | --- |
| **`ILIKE '%term%'` — RECOMMENDED for MVP** | A campus career portal holds hundreds to low thousands of published vacancies. At that size a sequential scan behind the partial `PUBLISHED` index is measured in milliseconds. Laravel expresses it directly, there is no ranking configuration to tune, and behaviour is obvious to whoever debugs it next |
| PostgreSQL full-text (`tsvector` + GIN) | **Deferred, not rejected.** Better once the corpus grows or relevance ranking is wanted. It needs a language configuration decision — **PostgreSQL ships no Indonesian stemmer**, so `simple` or a custom dictionary would be required, plus a generated column and GIN index. That is a real decision, and making it before there is a measured need would be guessing |
| `pg_trgm` GIN | **Optional.** Makes `ILIKE '%term%'` index-assisted. The natural first upgrade if search slows before ranking is needed |
| Elasticsearch / OpenSearch | **Rejected.** An entire additional service, with its own sync problem and second source of truth, for a dataset this size |

**Upgrade signal:** move to trigram when public search exceeds ~200 ms at p95, and to full-text when relevance ranking becomes a requirement rather than a preference.

### Company duplicate detection (FR-COMP-001)

`companies.normalized_name` is **not unique** (INV-034) — detection flags candidates for authorized review; it never hard-rejects.

```
idx_companies_normalized_name ON companies (normalized_name)
```
Exact and prefix matching — the common case, since normalization already folds case and spacing.

```
idx_companies_legal_identifier ON companies (legal_identifier) WHERE legal_identifier IS NOT NULL
idx_companies_official_email   ON companies (official_email)   WHERE official_email IS NOT NULL
idx_companies_official_phone   ON companies (official_phone)   WHERE official_phone IS NOT NULL
```
FR-COMP-001 names five detection signals; these index four of them exactly. Website-domain matching is derived at query time from `website`.

**`pg_trgm` is OPTIONAL here** — add `gin_trgm_ops` on `normalized_name` only if fuzzy similarity proves necessary. Exact-and-prefix detection plus the other four signals is expected to suffice.

---

## 7. Audit Log at Scale — Class D/E

`audit_logs` is the most append-heavy table in the system and is written inside business transactions, so **every index on it is a tax on every business write.**

| Index | Serves |
| --- | --- |
| `idx_audit_logs_created_at ON audit_logs (created_at DESC)` | Default listing and cursor pagination |
| `idx_audit_logs_actor ON audit_logs (actor_user_id, created_at DESC)` | "What did this user do?" |
| `idx_audit_logs_object ON audit_logs (object_type, object_id, created_at DESC)` | "What happened to this record?" — the most common investigative query |
| `idx_audit_logs_action ON audit_logs (action, created_at DESC)` | "All document downloads last month" |
| `idx_audit_logs_correlation ON audit_logs (correlation_id) WHERE correlation_id IS NOT NULL` | Trace one request across logs, jobs, and audit |

**Not indexed:** `change_summary` and `user_agent_device_metadata`. Both are `jsonb`, neither is ever filtered on, and a GIN index on either would add substantial write cost inside every business transaction (`DATABASE_SCHEMA.md` §15).

**Partitioning: deferred.** Monthly range partitioning on `created_at` is the natural future optimization, but it adds routing complexity and a partition-maintenance job. **Not warranted at MVP** — an institutional career portal generates modest audit volume, and retention is indefinite rather than high-churn. Revisit when the table passes roughly 50 million rows or when retention policy introduces bulk expiry, where partition drop is dramatically cheaper than bulk delete.

---

## 8. Deferred Indexes — Signals That Would Justify Them

Listed so a future decision is informed rather than reactive.

| Deferred index | Justifying signal |
| --- | --- |
| GIN trigram on `companies.normalized_name` | Duplicate review misses similar names, or exact/prefix matching proves too narrow |
| `tsvector` + GIN on vacancy title/description | Public search p95 exceeds ~200 ms, or relevance ranking becomes a requirement |
| GIN on any `jsonb` column | A query genuinely filters inside JSON — none does today |
| Partitioning on `audit_logs` | ~50M rows, or bulk retention expiry |
| Covering (`INCLUDE`) indexes on hot lists | A measured index-scan-plus-heap-fetch bottleneck |
| Index on `applications.current_stage_id` | Stage-filtered applicant lists become common; today the vacancy+status index serves the observed pattern |

**None of these is created at MVP.** Each would cost write throughput now to serve a query pattern that has not been measured.
