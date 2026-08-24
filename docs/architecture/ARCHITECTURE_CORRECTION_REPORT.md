# Architecture Correction Report

**Applied:** 24 August 2026
**Change request:** Final architecture corrections — approved
**Logical model:** revision 1.1-C1 → **1.1-C2**
**Architecture decisions:** ADR-001 to ADR-014 → **ADR-001 to ADR-017**
**Entity count:** 49 → **51**

Four corrections applied. No application code, migration, Laravel scaffold, or dependency was created. BRD v1.1, FSD v1.1, and all Stitch screen files are byte-unchanged. `ERD_REVIEW.md` and `ERD_CORRECTION_REPORT.md` are retained unchanged as historical evidence.

---

## Corrections Applied

| # | Correction | Outcome |
| --- | --- | --- |
| 1 | **SMTP runtime configuration** (resolves ADR-010, closes A-1) | `smtp_configurations` entity + INV-035, INV-036 + ADR-015 |
| 2 | **Selector stage assignment** (resolves H-1) | `selection_stage_assignments` entity + INV-037 + ADR-016 |
| 3 | **Inertia SSR topology** | Fourth process role declared + ADR-017 + updated diagrams |
| 4 | **Stitch traceability statement** | One inaccurate sentence corrected; **no file restored, no mapping changed** |

---

## SMTP Configuration Resolution

**Decision: a single `smtp_configurations` entity holding an application-encrypted, write-only credential. A separate `system_secrets` abstraction was considered and not adopted.**

The brief permitted either shape and asked for the simpler secure solution. Exactly **one** secret in this system is runtime-managed, so a general secret store would be an indirection layer containing one row — with its own lifecycle, access rules, and tests — bought against a second use case that does not exist. The security properties come from INV-035, not from the shape of the table, so the single entity is no less secure and materially simpler. If a second runtime-managed secret ever appears, `system_secrets` should be reconsidered rather than adding a second bespoke encrypted column.

### Fields

`id` · `host` · `port` · `encryption_mode` · `username` · `encrypted_password` · `from_address` · `from_name` · `reply_to_address` · `timeout_seconds` · `max_attempts` · `retry_backoff_seconds` · `is_active` · `last_tested_at` · `last_test_result` · `updated_by_user_id` · `created_at` · `updated_at`

Beyond the recommended set, `reply_to_address`, `timeout_seconds`, `max_attempts`, `retry_backoff_seconds`, `last_tested_at`, and `last_test_result` were added because FR-NOTIF-005 explicitly names reply-to, timeout, retry policy, and a test-email action. **`max_attempts` also closes a real gap:** FR-NOTIF-003 requires a configurable maximum attempt count, which revision 1.1-C1 noted was "configuration, not a stored field" with no home anywhere. It now has one.

### Required properties — all captured as INV-035

| Requirement from the brief | Where enforced |
| --- | --- |
| Application-level encryption before persistence | INV-035 — plaintext never written, not even transiently |
| Encryption key sourced outside the database | INV-035 — deployment secret configuration; database access alone does not yield the credential |
| Secret never returned after save | INV-035 — write-only field contract; no read path, API response, export, or serializer returns it |
| UI shows masked state only | INV-035 — the interface reports whether a credential is set, never its value or length |
| Changing a secret replaces the encrypted value | INV-035 — wholesale ciphertext replacement, no partial update, no retained previous value |
| Audit records that the credential changed, never its value | INV-035 — `audit_logs` records the event; values never enter `change_summary` |
| **Not stored in `email_outbox`** | INV-015 remains in force unchanged, and INV-035 restates it explicitly |
| Never in logs | INV-035 — excluded from logs, exception traces, queue payloads, failure summaries, and test results |

**INV-036** adds: at most one configuration may be active. Zero active rows is valid and means delivery falls back to deployment configuration. Superseded rows are retained as history; a retention process may clear their ciphertext without deleting the row.

`smtp_configurations` is classified as **system configuration, not business-domain data**. It carries no recruitment meaning, appears in no reporting derivation, and is never referenced by a business rule — recorded explicitly in `ERD.md` and in the dictionary's new *System Configuration* section.

**One operational consequence worth stating plainly:** a database restore without the corresponding encryption key leaves the stored credential undecryptable, and a Super Admin must re-enter it. That is an acceptable recovery path, but it only works if someone knows about it — so it is now written into `DEPLOYMENT_ARCHITECTURE.md` §4, along with the requirement to back the key up separately from the database.

---

## Selector Assignment Resolution

**`selection_stage_assignments` added, resolving H-1 and closing residual risk R-1.**

Fields exactly as specified: `id` · `recruitment_stage_id` · `selector_user_id` · `assigned_by_user_id` · `assigned_at` · `revoked_at` · `revoked_by_user_id`, plus `created_at` / `updated_at`.

No vacancy reference is stored: the stage determines the vacancy, so storing it again would create a second authority able to contradict its parent — the same reasoning that removed `vacancy_id` from `recruitment_outcomes` in revision 1.1-C1.

### INV-037 — the rules

| Rule | Effect |
| --- | --- |
| Surrogate key, not `(stage, selector)` | A revoked assignment can be re-issued; history survives. Same pattern as INV-025 |
| Many historical, **at most one active** | Conditional uniqueness on `revoked_at IS NULL` |
| Revocation preserves history | Never deletes the row; never overwrites `assigned_at` or `assigned_by_user_id` |
| **Role is a precondition, not a grant** | The selector must hold an active SELECTOR role assignment. Holding the role permits *being assigned*; it grants no access by itself |
| **Access requires an active assignment** | A revoked assignment grants nothing |
| **Scope inherited from the stage** | Reaches only that stage's applications, schedules, and evaluations — never another stage of the same vacancy, never another vacancy |
| Not delegation | A selector cannot assign, revoke, or extend assignments |
| Not authorship | `evaluations.evaluator_user_id` records who evaluated; the assignment records who was permitted to |

**No broader permission model was introduced.** A general ACL table was considered and rejected: it would grant a far wider capability surface than FR-HR-006 asks for, and every unused branch of a permission model is untested attack surface.

### Laravel Policy consequence

Policies now have a concrete ownership relation to evaluate:

```
active selection_stage_assignments (revoked_at IS NULL)
  → recruitment_stages
    → vacancies
      → applications / selection_schedules / evaluations
```

**Object-level Policy alone is not sufficient**, and this is stated in both `LARAVEL_ARCHITECTURE.md` §5 and `SECURITY_ARCHITECTURE.md` §2: a Policy protects `show` for one evaluation but does nothing for an applicant *list*. Selector list endpoints must be **query-scoped through the same join**, or candidates from unassigned stages appear in the list. Revocation is re-checked per request and never cached across requests.

---

## Inertia SSR Resolution

**Adopted explicitly as a fourth runtime process (ADR-017), amending ADR-004 and ADR-014.**

The previous documents committed to server-rendered public pages while describing a three-role topology — the renderer was implied but never declared. That inconsistency is now closed.

| # | Process role | Notes |
| --- | --- | --- |
| 1 | Laravel web/application | Horizontal, stateless |
| 2 | Laravel queue workers | Horizontal, per queue |
| 3 | Laravel scheduler | **Exactly one instance** |
| 4 | **Inertia SSR renderer (Node)** | Public/SEO routes only. **No database, no API, no business logic, no independent release** |

### Why this does not contradict rejecting the separated SPA + API architecture

The distinction is not whether Node runs in production — it is where the contract, the routing, and the authentication live.

| | Rejected separated SPA + API | Inertia SSR |
| --- | --- | --- |
| Routing and validation | Duplicated in the frontend app | **Laravel only** |
| API contract | A second, independently versioned contract | **None — Inertia passes props** |
| Authentication | Token in browser storage, cross-origin | **Cookie session, same-origin, unchanged** |
| Deployment | Independently deployed and versioned | **Same release, deployed together** |
| Node's role | Runs the application | **Renders the first HTML payload** |
| Business logic in Node | Yes | **None** |
| Independent database or API | Yes | **Neither** |

The renderer executes the same compiled Vue components the browser would, one request earlier. It is not a microservice.

### Health and restart requirements

- **Supervised** with automatic restart, and a liveness health check that removes a failed instance from rotation for public routes.
- **Restart on every deploy is mandatory** — the renderer holds the compiled SSR bundle in memory, so a stale renderer would serve the previous release's markup alongside the new application.
- **Version lockstep** — the SSR bundle is built and versioned with the application; a mismatch is a deployment defect.
- **Failure is degradation, not outage.** If the renderer is down, Inertia falls back to client-side rendering: the application keeps working and authenticated portals are unaffected, but public pages lose their indexable HTML. There is **no user-visible error and no HTTP failure**, so both process liveness *and* SSR fallback rate are now named as required monitoring signals — otherwise the first symptom is lost search ranking.
- **SSR runs locally too**, or SSR-unsafe component code is not discovered until staging.

Diagrams updated in `SYSTEM_ARCHITECTURE.md` §1 and `DEPLOYMENT_ARCHITECTURE.md` §2.

---

## Database Revision

**Revision 1.1-C1 → 1.1-C2.** Applied to `ERD.md`, `DATA_DICTIONARY.md`, `DATA_INVARIANTS.md`, and `docs/database/README.md`. `ERD_REVIEW.md` and `ERD_CORRECTION_REPORT.md` were **not** modified.

### Entities added

| Entity | Domain | Classification |
| --- | --- | --- |
| `selection_stage_assignments` | Application & Recruitment | Technical derivation implementing FR-HR-006 |
| `smtp_configurations` | **System Configuration** (new domain) | System configuration — not business-domain data |

### Entity count

| Step | Count |
| --- | --- |
| Revision 1.1-C1 | 49 |
| + `selection_stage_assignments` | 50 |
| + `smtp_configurations` | **51** |

**51 logical entities = 50 business/derivation + 1 system configuration.** The count was measured, not targeted: it is exactly 49 plus the two entities this change request required, with nothing else added or removed.

### Invariants added

| ID | Rule |
| --- | --- |
| **INV-035** | SMTP Credential Confidentiality — encryption before persistence, key outside the database, write-only, masked, replace-on-change, audited as an event never a value, never in the outbox, never in logs |
| **INV-036** | Single Active SMTP Configuration — at most one active row; zero is valid |
| **INV-037** | Selector Stage Assignment — surrogate key, at most one active per stage and selector, role is a precondition not a grant, scope inherited from the stage |

Invariant count: 34 → **37**.

### Side effects recorded

- **Conditional uniqueness rules: 3 → 5.** This *strengthens* ADR-003: five of the model's own invariants are now declarative one-liners in PostgreSQL and generated-column workarounds in MySQL. `SYSTEM_ARCHITECTURE.md` §2 was updated accordingly.
- `email_outbox.attempt_count` now points at `smtp_configurations.max_attempts` for its ceiling, replacing the previous "configuration, not a stored field" note, and restates that the outbox never holds a credential.
- Decisions **D-7** (selector assignment shape) and **D-8** (single SMTP entity over `system_secrets`) recorded in `ERD.md`.
- **H-1 struck through and marked resolved** in `ERD.md`, with the original text retained for history.

---

## Architecture Revision

All six architecture documents updated. ADR numbering and history preserved cleanly — nothing was renumbered or deleted.

| ADR | Change |
| --- | --- |
| **ADR-015** *(new)* | Runtime SMTP configuration with application-encrypted secret. **Supersedes ADR-010** |
| **ADR-016** *(new)* | Selector authorization by stage assignment. **Resolves H-1** |
| **ADR-017** *(new)* | Inertia SSR as a fourth runtime process. **Amends ADR-004 and ADR-014** |
| ADR-010 | Marked **SUPERSEDED BY ADR-015**; original text retained unchanged as decision history |
| ADR-004 | Amendment note added pointing to ADR-017; the frontend framework choice is unchanged |
| ADR-014 | Amendment note added: three process roles → four |

| Document | Updates |
| --- | --- |
| `SYSTEM_ARCHITECTURE.md` | Topology diagram gains the SSR renderer; SSR rationale added to the topology comparison; the SMTP section rewritten from "conflict requiring a decision" to "RESOLVED"; constraint-strategy table gains INV-035/036/037; PostgreSQL rationale updated from three to five conditional-uniqueness rules; H-1 and A-1 struck through as resolved |
| `LARAVEL_ARCHITECTURE.md` | `selection_stage_assignments` assigned to the Vacancy module; new **SystemConfiguration** module for `smtp_configurations`; entity total 49 → 51; Selector row in the authorization table rewritten from BLOCKED to resolved; SSR component-authorship constraints added; three new invariant tests added to the release matrix |
| `SECURITY_ARCHITECTURE.md` | Selector scope section rewritten as resolved with seven explicit rules; new *Runtime-managed SMTP credential* section with the full control table; R-1 and R-2 closed; R-7 (encrypted credential in the database) and R-8 (Node runtime) added as accepted risks |
| `DEPLOYMENT_ARCHITECTURE.md` | Four process roles; SSR in the topology diagram; SSR health, restart, and failure-mode section; SSR and SMTP monitoring signals added; encryption-key backup requirement added; local Compose stack gains SSR |
| `TECHNICAL_DECISIONS.md` | ADR-015/016/017 added; ADR-010 superseded; ADR-004/014 amendment notes; decision summary and H-item table updated |
| `README.md` | Amendment banner, 1.1-C2 alignment, SSR and SMTP in the stack table, blocked-items section replaced with resolved/still-open |

---

## Stitch Traceability Correction

**One sentence corrected. No file restored, no mapping changed, no screen file touched.**

`design/stitch/CANONICAL_SELECTION_REPORT.md` claimed:

> "The original iteration remains unchanged in archive/stitch-iterations."

That is accurate for most groups but not for the seven redesigned ones. The claim was replaced with a dated archive-accuracy note stating that for those seven groups a subsequent cleanup removed **21 iteration directories and 42 files**, introducing **7 redesigned screens comprising 14 canonical files**; that the removed legacy iterations are therefore no longer individually present in the working-tree iteration archive for those groups; and that **all original export files remain recoverable from the verified raw Stitch ZIP** at `archive/packages/stitch_campus_career_portal_system_original.zip`.

These figures match the independent measurement taken during the previous architecture review — 42 original file hashes absent from the working tree, 14 files under `design/stitch/` not matching any original, and `archive/stitch-iterations/` reduced from 86 to 65 directories.

ZIP integrity re-verified: `unzip -t` clean, 229/229 files present.

---

## Remaining Open Questions

### The six business open questions — all preserved, none decided

| # | Question | Status |
| --- | --- | --- |
| 1 | Alumni verification integration source | **Open** — no integration component exists |
| 2 | Minimum company legal documents by organization type | **Open** — validation configuration |
| 3 | Salary mandatory / display policy | **Open** — validation and view configuration |
| 4 | Recruiter domain / subdomain | **Open** — topology supports all three shapes; settle before production DNS/TLS |
| 5 | WhatsApp notification phase | **Open** — no provider abstraction built |
| 6 | First recruiter default role / minimum Company Admin | **Open** — affects onboarding Action and a Policy guard |

Nothing in this change request touches any of the six. `smtp_configurations` concerns email transport, not the WhatsApp phase decision (5): no channel abstraction, provider adapter, or delivery entity for WhatsApp was introduced.

### Human-decision items

| # | Item | Status |
| --- | --- | --- |
| ~~H-1~~ | ~~Selector stage assignment~~ | **RESOLVED** by ADR-016 and INV-037 |
| **H-2** | Vacancy-level outcome not recordable | **Still open** — untouched |
| **H-3** | Candidate revocation of a shared document | **Still open** — untouched, constrained by INV-032 |
| **H-4** | Audit IP / device metadata collection | **Still open** — untouched, both fields remain optional |

---

## Cross-Document Validation

| # | Check | Result |
| --- | --- | --- |
| 1 | BRD v1.1 and FSD v1.1 byte-unchanged | **PASS** — MD5 match |
| 2 | Stitch screen files (`code.html`, `screen.png`) unchanged | **PASS** — zero modified |
| 3 | No deleted Stitch iteration restored | **PASS** — 65 iteration directories before and after |
| 4 | Original Stitch ZIP intact | **PASS** — `unzip -t` clean, 229/229 |
| 5 | Every Mermaid entity exists in the dictionary | **PASS** — 51/51 |
| 6 | Every dictionary entity exists in the ERD | **PASS** — 51/51 |
| 7 | ERD domain table matches the dictionary | **PASS** — 51/51 |
| 8 | Every `entity.field` cited in an invariant exists | **PASS** |
| 9 | Invariant IDs unique; every referenced `INV-nnn` defined | **PASS** — 37 defined |
| 10 | SMTP credential updatable at runtime, securely | **PASS** — ADR-015, INV-035, INV-036 |
| 11 | Secret never plaintext in logs or audits | **PASS** — INV-035; INV-015 unchanged |
| 12 | Selector stage assignment representable | **PASS** — entity + INV-037 |
| 13 | Policies have an ownership/assignment relation to enforce | **PASS** — join path documented in two documents, with list-scoping requirement |
| 14 | Inertia SSR present in deployment topology | **PASS** — process role 4, diagram, health and restart requirements |
| 15 | No SQL, DDL, or migration created | **PASS** |
| 16 | No Laravel source code or scaffold created | **PASS** |
| 17 | No dependency installed | **PASS** |
| 18 | Logical ERD and architecture documents agree | **PASS** — entity count, invariant references, and revision markers consistent |
| 19 | Six open questions still open | **PASS** |
| 20 | ERD_REVIEW.md and ERD_CORRECTION_REPORT.md unchanged | **PASS** — byte-identical |

---

## Architecture Freeze Recommendation

**RECOMMEND FREEZE.**

Both items that previously blocked implementation are resolved, and no new blocker was introduced:

- **ADR-010 → ADR-015.** SMTP configuration is manageable at runtime with a credential that is encrypted before persistence, keyed outside the database, and unreadable after save.
- **H-1 → ADR-016.** Selector authorization has a concrete assignment relation that Laravel Policies can enforce, and the list-scoping requirement is documented so the usual failure mode is pre-empted.
- **The SSR inconsistency is closed.** The Node renderer is declared, justified against the rejected SPA architecture, and given health, restart, and monitoring requirements.

The six business open questions do not block architecture freeze. Each is a validation rule, a configuration value, a future phase, or a DNS decision — none changes the topology, the data model, or the module boundaries. H-2, H-3, and H-4 are similarly non-blocking.

**Recommended freeze scope:** BRD v1.1 · FSD v1.1 · Stitch functional baseline · **logical model revision 1.1-C2** · **architecture ADR-001 to ADR-017**.

**Next artefacts, in order:** `docs/api/API_CONTRACT.md`, then `docs/database/DATABASE_SCHEMA.md`. Both were previously waiting on architecture approval and are now unblocked. The five conditional-uniqueness rules and three XOR rules should be the first things the physical schema addresses, since they are the model's least portable and most easily lost guarantees.

**Two items to carry into implementation planning**, neither blocking:

1. **Open question 4** (recruiter domain/subdomain) must be settled before production DNS and TLS are provisioned — not before coding starts.
2. **The SMTP encryption-key backup and restore procedure** needs a runbook entry before the first production deployment, because a database restore without the key requires manual credential re-entry.

---

**End of report. No BRD/FSD change. No Stitch HTML or screenshot modified. No file restored. No SQL, migration, Laravel scaffold, or application code created.**
