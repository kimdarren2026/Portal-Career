# Product Owner Decisions

Approved Product Owner decisions and contract amendments that are **not**
attributed to BRD/FSD (which are unedited). Each decision is also cross-referenced
from `docs/api/API_CONTRACT.md` Part X.

---

## PGC-V1 — Production Gap Closure V1

**Status:** APPROVED — Product Owner, recorded with batch `p1-production-blockers-batch-1b`.
**Supersedes (for the stated scope only):** API_CONTRACT.md Part X item 22 (RA-3 —
application-document download), item 2 (minimum company legal documents), and the
`B-5` product-blocker note in `docs/operations/RUNBOOK.md` §1/§9 (email delivery
worker). All other deferrals/open items are unchanged.

BRD/FSD are not modified. These are Product Owner decisions recorded here.

---

### PD-A — Application document (snapshot) download

RA-3 is **un-deferred** for the download operation. The previously reserved
endpoint `GET /api/v1/application-documents/{applicationDocument}/download`
(browser route `GET /application-documents/{applicationDocument}/download`) is
activated.

- The downloadable object is the **immutable `application_documents` snapshot**
  (`snapshot_storage_reference` / `snapshot_name`), never the candidate's
  current/live `candidate_documents` library entry. Snapshot semantics
  (INV-032) are preserved: a revoked share (`revoked_at` not null) is not
  downloadable; the snapshot is never mutated by this operation.
- **Authorization** (`AUTHORIZATION_MATRIX.md` §4.6 already specifies this):
  - `CANDIDATE` — OWN application only.
  - `COMPANY_RECRUITER` / `COMPANY_ADMIN` — `COMPANY_SCOPE` application only.
  - `HR_ADMIN` — `CAMPUS_SCOPE` application only.
  - `SELECTOR` — active `ASSIGNED_STAGE` only (the share must belong to an
    application currently on an actively-assigned stage).
  - `SUPER_ADMIN` — ALLOW (existing broad admin authorization).
  - `CAREER_CENTER` — DENIED.
  - `AUDITOR` — DENIED (no frozen contract grants Auditor application-document
    access — §4.6 shows `D` for Auditor on both "list documents shared with an
    application" and "download an application-shared document").
- **Security:** application-mediated streaming only; private disk only; the
  storage reference is never returned or exposed; no public/pre-signed URL; no
  client-supplied path; `Content-Disposition: attachment` with a sanitized
  filename and `X-Content-Type-Options: nosniff`; authorization is checked
  before the object is retrieved; out-of-scope objects are enumeration-safe
  `404` (indistinguishable from not-found); a missing stored object is a safe
  `404`, never a 500. Every authorized download **and every denied attempt** is
  audited as `document_access`.

### PD-B — Transactional email delivery

Minimal factual, version-controlled transactional email templates are
**approved for production**. Branded/visual templates are not required for
launch — these are operational messages, not marketing.

- Every `template_reference` emitted by production runtime has a deterministic
  renderer. Subject and body describe only the actual business event; no
  fabricated information, no promotional copy, no password/credential/secret;
  an action link is included only where the destination is already
  deterministic. A coverage check proves every runtime `template_reference` has
  a renderer.
- Standard layout:
  ```
  STIKES Advaita Medika Tabanan
  Portal Karir

  [Event title]

  [Short factual event description]

  Reference / status where applicable.

  [Action link where applicable]

  Email ini dikirim otomatis oleh Portal Karir STIKES Advaita Medika Tabanan.
  ```
- **Delivery runtime** on the frozen `email_outbox` table and states
  (`chk_email_outbox_status`): `PENDING → PROCESSING → SENT`; temporary failure
  `→ FAILED_RETRYABLE` with controlled backoff and retry; on `attempt_count`
  reaching the configured `max_attempts` `→ DEAD_LETTER`.
  `smtp_configurations.max_attempts` / `retry_backoff_seconds` drive the policy
  when an active configuration exists, else configuration defaults.
  `last_error_summary` is sanitized (never a credential, host, DSN, password,
  or stack trace — INV-015 / INV-035). Concurrency- and duplicate-delivery
  safe (row lock + status guard). SMTP failure **never** rolls back the
  business transaction — the outbox row is written inside the business
  transaction, delivery is attempted only after commit. Runtime SMTP config
  (active `smtp_configurations` row) is used when present; otherwise deployment
  `MAIL_*`. A Super Admin requeue capability re-drives a `FAILED_RETRYABLE` /
  `DEAD_LETTER` row.

### PD-C — Laporkan Lowongan (public vacancy reporting)

An anti-fraud vacancy-reporting capability, targeting an existing published
vacancy.

- **Who may report:** BOTH anonymous/public visitors and authenticated users.
  Public users may submit anonymously and are never required to create an
  account; authenticated users have their user id attached automatically.
- **Routes:** `GET /lowongan/{vacancy}/laporkan` (form), `POST
  /lowongan/{vacancy}/laporkan` (submit). `{vacancy}` is the public vacancy
  slug; a non-public/nonexistent slug is an enumeration-safe `404`.
- **Reason vocabulary (closed, MVP):** `FRAUD_OR_SCAM` ("Dugaan penipuan"),
  `MISLEADING_INFORMATION` ("Informasi menyesatkan"), `INAPPROPRIATE_CONTENT`
  ("Konten tidak pantas"), `INVALID_OR_EXPIRED_VACANCY` ("Lowongan tidak
  valid/kedaluwarsa"), `SUSPICIOUS_EXTERNAL_LINK` ("Tautan eksternal
  mencurigakan"), `OTHER` ("Lainnya").
- **Fields:** `vacancy` and `reason` required. `details` optional in general,
  **required when `reason = OTHER`**, max 2000 characters. For an anonymous
  reporter `reporter_name` and `reporter_email` are optional (email format
  validated if supplied); for an authenticated reporter identity derives from
  the account and any supplied name/email is ignored. Reporter identity is
  never exposed publicly.
- **Lifecycle:** `NEW → UNDER_REVIEW → ACTIONED` or `NEW → UNDER_REVIEW →
  DISMISSED`. Career Center (`CAREER_CENTER_STAFF`, `CAREER_CENTER_MANAGER`)
  owns review and status transitions; no recruiter may review or dismiss.
  A Career Center reviewer who filed the report may not review it
  (conflict-of-interest). `SUPER_ADMIN` may read for audit/support but does not
  perform business transitions.
- **Anti-spam:** anonymous/public `5` submissions per IP per hour;
  authenticated `10` submissions per account per day. Normal CSRF applies. A
  malformed or non-existent vacancy is rejected safely.
- **Audit:** `vacancy_report_created`, `vacancy_report_review_started`,
  `vacancy_report_actioned`, `vacancy_report_dismissed` — the reviewer's reason
  is recorded, never a credential/session secret. Report data is not exposed
  publicly. Retention follows the existing indefinite recruitment/audit
  default.

### PD-D — Company legal document policy & runtime

The frozen company legal-document runtime is **activated**; item 2's
per-organization-type mandatory matrix is **closed as "no matrix for MVP"**.

- **Allowed document types (closed, MVP):** `NIB` ("NIB"), `AKTA_PENDIRIAN`
  ("Akta Pendirian"), `SK_KEMENKUMHAM` ("SK Kemenkumham"), `IZIN_OPERASIONAL`
  ("Izin Operasional"), `DOKUMEN_LEGALITAS_LAINNYA` ("Dokumen Legalitas
  Lainnya"). Enforced by request/runtime validation only — no DB `CHECK`, no
  migration on `company_documents.document_type`.
- **Verification submission requirement:** at least **one active** legal
  document of an allowed type (a row not superseded). No individual type is
  mandatory for every organization type in MVP.
- **File policy:** allowed MIME `application/pdf`, `image/jpeg`, `image/png`;
  maximum `10 MiB` (`10,485,760` bytes) per file; the **actual file
  content/MIME is validated** (magic bytes / server inspection), not the
  filename extension. Private storage is mandatory; the storage key is
  randomized; the storage path/reference is never exposed.
- **Replacement:** the already-frozen Q-2 rule is unchanged — a `DRAFT`
  document (never part of a submitted verification package,
  `first_submitted_at` null) may be deleted/replaced outright; a
  submitted/previously-reviewed document must not be destructively overwritten
  and is replaced through `supersede`, retaining history (INV-038).
- **Expiry:** **no expiry-blocking rule for MVP.** Verification is not blocked
  because a document lacks an expiry date; there is no expiry scheduler. A
  document-validity policy may be added later.
- **Runtime:** upload, list, private download, draft delete, supersede,
  review visibility, audit. Recruiter/Company Admin — own-company scope only.
  Career Center — verification/review read scope. Auditor — read-only within
  its permitted scope. No cross-company access.

### PD-E — Application consent canonical text

`APPLICATION_CONSENT_2026_08` is **ratified**. Authoritative Indonesian text:

> "Saya menyetujui data profil, CV, dokumen, dan informasi lamaran yang saya
> pilih untuk lamaran ini diproses dan dibagikan kepada perusahaan atau unit
> kampus pemilik lowongan yang saya lamar, hanya untuk keperluan proses
> rekrutmen dan seleksi. Sistem akan mencatat versi persetujuan, tujuan
> penggunaan, penerima data, dan waktu persetujuan sebagai bagian dari riwayat
> lamaran."

The server is authoritative. `consent_text_hash_reference` is derived
server-side from the exact canonical text + version identifier; a client hash
is never authoritative. Any punctuation/wording change requires a **new**
consent version — a historical version is never silently altered. The consent
receiver, purpose and timestamp invariants (INV-011, INV-023) are unchanged.

### PD-F — Super Admin user directory & account suspension

- **`GET /admin/users`** — `SUPER_ADMIN` only. Returned fields: `id`, `name`,
  `email`, `status`, `active_roles`, `created_at`. Never a password/hash,
  token, secret, private candidate document, or unnecessary profile data.
  Filters: text search over name OR normalized email; `status` filter; `role`
  filter. Pagination default `25`, maximum `100`. Sort `created_at DESC`.
- **Account lifecycle (MVP): `ACTIVE ↔ SUSPENDED` only.** `DISABLED` remains
  deferred. `POST /admin/users/{user}/suspend` — `reason` **required**, max
  1000 chars. `POST /admin/users/{user}/restore` — `reason` optional.
  `SUPER_ADMIN` only.
- **Suspension effect:** immediately terminates the user's active sessions
  (OL-10 semantics — re-checked per request; server-side session rows are also
  cleared on suspend so it does not rely on "next login"). Any applicable
  API/auth tokens are invalidated where such a surface exists (none in the
  MVP session-only surface). A suspended user cannot continue authenticated
  application operations.
- **Notification:** MVP does **not** send the user an email/in-app notification
  on suspension — abuse response may require silent containment. The
  administrative action itself is audited: `user_suspended`, `user_restored`,
  recording the reason but no credential/session secret.

### PD-G — Public homepage

The bootstrap Health page at `/` is **replaced** with a real minimal
production homepage. No fabricated statistics, testimonials, companies, or
vacancies.

- **Hero:** title "Temukan Peluang Karier Terbaikmu"; supporting copy "Portal
  Karir STIKES Advaita Medika Tabanan menghubungkan mahasiswa, alumni, dan
  pencari kerja dengan peluang karier di kampus maupun perusahaan
  terverifikasi."; primary CTA "Cari Lowongan" → `/lowongan`; secondary CTA
  "Daftarkan Perusahaan" → `/register`.
- **Quick pathways:** "Karier di Kampus" →
  `/lowongan?vacancy_type=CAMPUS_EMPLOYMENT`; "Karier untuk Alumni" →
  `/lowongan?target_audience=FINAL_YEAR_AND_ALUMNI`.
- **Latest vacancies:** the existing real public vacancy query; only actually
  eligible `PUBLISHED` vacancies; maximum 6; empty state "Belum ada lowongan
  yang tersedia saat ini." — no fabricated fallback cards.
- **Authentication CTA:** "Masuk" → `/login`; "Daftar" → `/register`.
- **Laporkan Lowongan:** the PD-C reporting capability is reachable from the
  relevant public/vacancy surfaces.
- **No public metrics** — no company count, vacancy count, hire count, success
  percentage, "24/7", or placement rate unless later backed by an explicitly
  approved real aggregation contract.
- **No public environment diagnostics** — the public `/` must not display the
  Laravel version, PHP version, or `APP_ENV`. Health diagnostics remain only
  at `/health/live` and `/health/ready` (and Laravel's `/up`).
