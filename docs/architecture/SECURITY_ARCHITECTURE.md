# Security Architecture — Portal Karir Kampus

**Status:** Proposed — awaiting approval
**Date:** 24 August 2026 · **Amended 24 August 2026** (ADR-015, ADR-016) · Logical model revision **1.1-C2**
**Traces to:** FSD §3.3 (object-level authorization), §5.1 (authentication), §9.3 (error principles), §10.1 (security), §10.4 (privacy), FR-AUD-001, and the frozen invariants INV-001, INV-010, INV-017, INV-021, INV-025, INV-028, INV-032, INV-033, and — added in revision 1.1-C2 — INV-035, INV-036, INV-037.

The system holds identity documents, CVs, academic records, and recruitment decisions about real people. The controlling assumption throughout: **a candidate's private documents and a company's applicant data are the assets worth protecting, and the most likely failure is authorization, not cryptography.**

---

## 1. Authentication

### Accounts and registration

| Role | Origin | Email verification |
| --- | --- | --- |
| Candidate (external, final-year, alumni) | Self-registration | Required before applying |
| Recruiter | Self-registration, then company profile → verification (FR-ONB-001) | Required before creating a company profile |
| Career Center staff / manager | Provisioned by Super Admin | Required |
| Admin Kepegawaian | Provisioned by Super Admin | Required |
| Selector | Provisioned, then assigned per stage via `selection_stage_assignments` (FR-HR-006) | Required |
| Auditor | Provisioned | Required |
| Super Admin | Bootstrapped at deployment | Required |

**No temporary-password onboarding, for any role.** Provisioned accounts are created without a password and activated through the same one-time invitation/verification token flow, so no credential is ever transmitted by email. This is absent from the ERD by design and forbidden by FSD.

### Mechanism

| Surface | Guard | Detail |
| --- | --- | --- |
| Web (all portals) | Laravel session guard | `httpOnly`, `Secure`, `SameSite=Lax`, encrypted cookie, session fixation defeated by regenerating the ID on login and privilege change |
| `/api/v1` | Sanctum bearer tokens | Reserved for future clients; abilities scoped per token; revocable |

Session cookies are chosen over browser-stored tokens for the web surface deliberately: an `httpOnly` cookie is not readable by injected script, whereas a token in `localStorage` is exfiltrated by any successful XSS.

### Password handling

- Adaptive hashing (bcrypt or Argon2id) with a work factor reviewed periodically — FSD §10.1.
- Only `password_credentials.password_hash` is stored. Plaintext never touches a log, an exception message, or a queue payload.
- Password policy per FR-AUTH-006. Rejecting known-breached passwords is recommended and can be done offline.
- `password_changed_at` maintained; a password change revokes other sessions and all outstanding reset tokens (INV-021).

### One-time tokens — must not revert to framework defaults

`email_verification_tokens` and `password_reset_tokens` store a **hash of a high-entropy token**, never the raw value. The raw token is delivered once by email and is unrecoverable afterwards.

| Control | Rule |
| --- | --- |
| Entropy | Cryptographically secure random, sufficient length to make guessing infeasible |
| Storage | `token_hash` only |
| Expiry | `expires_at` always set and enforced |
| Single use | `used_at` set on consumption; a consumed token never verifies again |
| Sibling revocation | A successful reset revokes the user's other valid reset tokens (INV-021) |
| Comparison | Constant-time |
| Enumeration | Reset and resend endpoints return an identical response whether or not the address exists (FSD §9.3) |
| Transport | Link is single-use and expiring; verification is a `POST`-confirmed action so link prefetching cannot silently consume it |

**Laravel's stock verification uses a signed URL and stores no token.** The frozen ERD requires stored hashed tokens. The ERD governs. Recorded here because reverting to the framework default would silently break the frozen model.

### Throttling, lockout, and session lifetime

| Control | Applied to |
| --- | --- |
| Per-IP and per-identity rate limits | Login, registration, reset request, resend verification, verify email, reset password, all public write actions. **Exact thresholds and windows: `API_CONTRACT.md` §11.1**, which is authoritative. Both limiters must be satisfied; tripping either returns `429` with `Retry-After` |
| Temporary account lock after repeated failures (FSD §10.1) | **8 credential failures in a rolling 15 minutes → a 15-minute lock** (`API_CONTRACT.md` §11.6). Time-boxed, self-clearing, auditable; never a permanent lock an attacker can trigger against a real user. Held in Redis runtime state — **`users.status` is never written by a lock**, and no table exists for it. Cleared on successful authentication |
| Abuse-control accounting is enumeration-safe | Counters are keyed on a normalized identity-derived value **even when no account exists**, so limiter and lock behaviour cannot reveal whether an address is registered (`API_CONTRACT.md` §11.3) |
| Generic failure messages | Never distinguish "unknown account" from "wrong password" |
| Idle and absolute session lifetime | Shorter for administrative roles than for candidates |
| Suspend / disable | `users.status = SUSPENDED / DISABLED` terminates active sessions and revokes API tokens immediately — status is re-checked on every request, not only at login |

---

## 2. Authorization

**RBAC and object-level ownership, together, always.** FSD §3.3 requires both. The full implementation shape is in `LARAVEL_ARCHITECTURE.md` §5; the security-relevant rules:

| Rule | Why |
| --- | --- |
| **Deny by default.** Every non-public route requires authentication and an explicit ability | An unlisted route must fail closed |
| **Route middleware is never sufficient.** A role check answers "may this kind of user reach this route", not "may this user touch this object" | Role-only checks are the direct cause of IDOR |
| **Every list query is ownership-scoped at the query level** | A Policy protects `show` and does nothing for `index`. Filtering after fetch still loads other companies' data into memory and leaks through counts and pagination totals |
| **Business eligibility is not authorization.** `ALUMNI_ONLY` gating reads `candidate_verifications` (INV-028) | Holding the role `CANDIDATE_ALUMNI` is not proof of verification |
| **Super Admin bypass is audited every time** | A break-glass path that is not recorded is indistinguishable from a compromise |
| **Career Center abilities are granular** | FSD §3.3: may monitor company vacancies, may **not** decide candidate acceptance for a company. One broad "manage company" ability would violate this |

### Selector scope — resolved

FR-HR-006 is now enforceable. `selection_stage_assignments` records the assignment of a SELECTOR to one vacancy-specific `recruitment_stages` row, and Policies resolve access through it.

| Rule | Security consequence |
| --- | --- |
| **Role is a precondition, not a grant** | Holding SELECTOR permits *being assigned*. With no active assignment a selector sees nothing. Role membership is never read as access |
| **Access requires an active assignment** | Only a row with `revoked_at` absent grants anything. Revocation takes effect immediately and is re-checked per request, never cached across requests |
| **Scope is inherited from the stage** | An assignment reaches only the applications, schedules, and evaluations of the stage it names — never another stage of the same vacancy, never another vacancy |
| **List queries must join the assignment** | A Policy protects a single evaluation; it does nothing for an applicant list. Selector list endpoints are scoped through `active assignment → stage → vacancy → applications`, or an unassigned stage's candidates leak into the list |
| **No self-service** | A selector cannot assign, revoke, or extend assignments. Only an authorized Admin Kepegawaian may |
| **Assignment is not authorship** | `evaluations.evaluator_user_id` records who evaluated; the assignment records who was permitted to. Neither substitutes for the other |
| **Audited** | Assignment and revocation are audited under FR-AUD-001 |

Candidate documents remain governed by INV-010 and INV-032: an assignment grants no blanket document access, only what the application-level share allows.

---

## 3. Web Application Controls

| Threat | Control |
| --- | --- |
| **CSRF** | Laravel CSRF middleware on every session-authenticated mutating request (FSD §10.1: "CSRF protection bila menggunakan cookie session" — the recommended architecture does use cookie sessions, so it applies). `SameSite=Lax` as defence in depth. The token-authenticated `/api/v1` surface is stateless and CSRF-exempt |
| **XSS** | Blade/Vue escape by default. No `v-html` or `{!! !!}` on user-supplied content. Rich text, if ever introduced, is sanitized server-side against an allow-list — never client-side only |
| **SQL injection** | Eloquent and the query builder parameterize. No raw SQL with interpolated input. Sorting and filtering accept **allow-listed** column names only — a client-supplied column name in an `orderBy` is an injection vector even through the query builder |
| **Mass assignment** | Explicit `$fillable` allow-lists (never `$guarded = []`). Form Requests return only validated keys. State fields (`current_status`, `verification_status`, `reopen_count`, `offer_accepted_at`) are **never** fillable — they change only through Actions |
| **IDOR / authorization bypass** | Policies per object, query scoping on every list, and non-sequential public identifiers for externally-shared resources. Route model binding never implies permission |
| **Clickjacking** | `X-Frame-Options: DENY` / CSP `frame-ancestors 'none'` on authenticated pages |
| **Open redirect** | Post-login and post-action redirects resolve against an internal allow-list, never a raw `?next=` parameter |
| **Content Security Policy** | Applicable and recommended. The Stitch prototypes use CDN Tailwind and Google Fonts; **production must compile Tailwind locally and self-host fonts** so CSP needs no CDN script allowance. Target: no `unsafe-inline` script, nonce-based where inline is unavoidable |

---

## 4. SSRF — External ATS URLs

`vacancies.external_ats_url` is recruiter-supplied and rendered as an outbound link. This is the system's main SSRF-adjacent surface.

| Control | Rule |
| --- | --- |
| **Protocol allow-list** | `https` only (FSD §9.1 rule 5). Never `http`, `file`, `gopher`, `data`, or `javascript` |
| **No server-side fetch** | The application **must not** request the URL — no link preview, no validation fetch, no favicon retrieval, no availability check. This eliminates classic SSRF rather than filtering it |
| **If a fetch is ever introduced** | It would require DNS re-resolution guarding, private/link-local/loopback range blocking (including IPv6 and redirect chains), and an egress proxy. **Recommended: do not introduce it** |
| **Redirect handling** | The browser navigates. The server never follows redirects on behalf of a user |
| **User warning** | FR-EXT-001 already requires an interstitial before leaving the portal |
| **Link rendering** | `rel="noopener noreferrer"` on outbound links |
| **Stored-XSS via URL** | The URL is validated on write and escaped on render; a `javascript:` scheme is rejected at validation |

---

## 5. File Upload and Download

| Stage | Control |
| --- | --- |
| **Accept** | Size limit per document type at both proxy and application; extension allow-list; **content-inspected MIME**, not the client-declared header |
| **Store** | Generated storage keys — never client filenames, so no path traversal. Client filename kept as display metadata only. Private bucket, no public ACL, no web-accessible path |
| **Scan** | Quarantine prefix → asynchronous scan if a scanner is configured → promote on clean (FSD §7.6, §10.1 "if available"). With no scanner, the gap is an explicitly accepted risk, not an unnoticed one |
| **Serve** | Authorized application route only: Policy check → audit write → stream or issue a seconds-lived signed URL bound to the actor. **Never a durable public or pre-signed link** |
| **Render** | Documents download with `Content-Disposition: attachment` and a correct content type; never rendered inline in the application origin, so a crafted HTML/SVG upload cannot execute in a trusted origin |
| **Index** | `X-Robots-Tag: noindex` on document routes; private buckets are not crawlable (FSD §10.4) |
| **Retain** | No hard delete of a document referenced by a live share (INV-032). Retention is authorized and audited |

---

## 6. Secrets and Configuration

| Rule | Detail |
| --- | --- |
| **No secret in the repository** | Enforced by the root `.gitignore` (`.env`, `.env.*`, `*.pem`, `*.key`, `secrets/`), with `.env.example` carrying variable **names** and placeholders only |
| **No business-domain secret** | No recruitment entity holds a credential. The single exception is `smtp_configurations`, which is **system configuration, not business data**, governed by INV-035 — see below |
| **No secret in logs** | Laravel's log-sanitization allow-list extended to cover password fields, token fields, `Authorization` headers, cookies, and SMTP credentials. Exception reporting must scrub request payloads |
| **Production secrets** | Held in the platform secret manager or environment, injected at runtime, never baked into an image |
| **Rotation** | `APP_KEY`, database credentials, SMTP credentials, and storage keys are rotatable without code change. `APP_KEY` rotation invalidates encrypted values and sessions — a documented, deliberate operation |
| **Least privilege** | The application's database role holds no superuser rights; the storage credential is scoped to its bucket |

### Runtime-managed SMTP credential (ADR-015, INV-035)

FR-NOTIF-005 requires Super Admin to update the SMTP credential at runtime, so it cannot live only in the environment. It is held in `smtp_configurations.encrypted_password` under an absolute contract:

| Control | Requirement |
| --- | --- |
| **Encrypted before persistence** | The application encrypts before the value reaches the database. Plaintext is never written, not even transiently |
| **Key outside the database** | The encryption key comes from deployment secret configuration. A key stored beside its ciphertext protects nothing — database access alone must not yield the credential |
| **Write-only** | No read operation, API response, export, view model, or serializer returns it. It cannot be retrieved after save by any means the application offers |
| **Masked in the UI** | The interface reports only whether a credential is set — never its value, never its length |
| **Replaced, never merged** | A change replaces the ciphertext wholesale. No partial update, no retained previous value |
| **Audited as an event** | `audit_logs` records that the credential changed, by whom, when. **The value never appears in `change_summary` or any audit payload** |
| **Never in the outbox** | INV-015 remains in force: no `email_outbox` row holds, references, or embeds an SMTP credential |
| **Never in logs** | Excluded from application logs, exception traces, queue payloads, delivery-failure summaries, and test-send results |
| **Key rotation** | Rotating the encryption key requires re-encrypting stored ciphertext — a documented, audited operational procedure |

A test-send action may use the stored credential; it must never echo it back in any result. Superseded configuration rows retain ciphertext under the same rules, and a retention process may clear it without deleting the row.

---

## 7. Logging, Audit, and Privacy

`audit_logs` and business history tables are **different things** and must not be conflated:

| | Business history | System audit |
| --- | --- | --- |
| Tables | `application_status_histories`, `company_verification_reviews`, `vacancy_moderation_reviews`, `selection_schedule_histories`, `vacancy_versions` | `audit_logs` |
| Answers | "What happened to this record, and what did it look like before?" | "Who did what, when, from where, in which request?" |
| Audience | Business users and the portal UI | Security, compliance, incident response |
| Written | Inside the business transaction (INV-016, INV-026) | Inside the transaction for successful business actions; **outside** for security events and failed attempts, so a rollback cannot erase the attempt |

Both are append-only. Neither has an update or delete path in the Action layer; revoking `UPDATE`/`DELETE` on those tables at the database role level is available as defence in depth.

### Audit coverage (FR-AUD-001)

Login success / failure / lock · registration · email verification · role change · **candidate-type change** · company submit/revision/verify/reject/suspend/restore · vacancy create/submit/revision/approve/reject/publish/suspend/close · **document access and download** · application create/status/reopen/withdraw · schedule create/update/cancel · evaluation · offering create/send/accept/reject/expire · SMTP configuration change · export · sensitive admin action · **every Super Admin bypass**.

Each entry carries actor, action, object type and id, correlation ID, timestamp, a redacted change summary, and — only where policy permits (H-4) — IP and device metadata.

### Never logged or audited

**Passwords · password reset tokens · email verification tokens · SMTP passwords · API secrets · session identifiers · raw file contents.** Only token *hashes* exist in the database, and even those never appear in a log line or an audit payload.

### Privacy (FSD §10.4)

Candidate data is scoped to the vacancy owner and reached only through an application relationship. Consent is independently auditable (INV-011) and never reduced to a flag. Sensitive data collection is minimized. Default retention is indefinite, with authorized deletion/anonymization available and `anonymized_at` marking treated records. Exports and downloads are audited and contain only what the role requires (FR-REP-005).

---

## 8. Dependency and Platform Security

| Control | Approach |
| --- | --- |
| Dependency updates | Lockfiles committed; automated advisory scanning on PHP and JS dependencies; patch cadence agreed and enforced, with security patches out-of-cycle |
| Supply chain | Dependencies added deliberately, reviewed, and justified — the empty `packages/` directories exist to prevent speculative adoption |
| Transport | TLS everywhere, HSTS, modern cipher suites, HTTP→HTTPS redirect at the edge |
| Framework hardening | Debug mode off in every non-local environment; stack traces never rendered to users (FSD §9.3); default framework routes not exposed in production |
| Error responses | User-safe message + FSD §9.2 code + correlation ID. No internal detail, no account existence disclosure |
| Backups | Encrypted at rest and in transit; restore access is itself privileged and audited |

---

## 9. Residual Risks — Accepted or Blocked

| # | Risk | Status |
| --- | --- | --- |
| ~~R-1~~ | ~~Selector authorization scope has no data model home~~ | **CLOSED.** Resolved by ADR-016 and `selection_stage_assignments` (INV-037). The Selector role can be enabled |
| ~~R-2~~ | ~~SMTP configuration conflict~~ | **CLOSED.** Resolved by ADR-015: runtime-managed configuration with an application-encrypted, write-only credential (INV-035, INV-036) |
| R-7 | A credential now exists in the database, encrypted | **Accepted, mitigated.** Encryption with an externally-held key means database access alone does not yield it. The residual exposure is the key-management process itself, which must be operationally protected and audited |
| R-8 | Inertia SSR adds a Node runtime to production | **Accepted.** It holds no business logic, no database connection, and no API surface. It must be patched, supervised, and restarted on deploy like any other process |
| R-3 | Malware scanning is conditional in FSD ("if available") | Accepted risk if no scanner is provisioned. Quarantine-and-promote design means a scanner can be added later without redesign |
| R-4 | Audit IP/device collection depends on an unmade policy decision (H-4) | Both fields remain optional; collection is configurable |
| R-5 | Super Admin bypass is broad by nature | Mitigated by mandatory auditing of every use; consider requiring a stated reason for break-glass access to candidate documents |
| R-6 | Recruiter domain decision (open question 4) affects cookie and CORS posture | Same-origin default is the secure baseline; a separate domain would require an explicit cross-origin review |
