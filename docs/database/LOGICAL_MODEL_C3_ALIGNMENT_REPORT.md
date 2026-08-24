# Logical Model 1.1-C3 Alignment Report

**Date:** 24 August 2026
**Change type:** Controlled logical alignment — **not a new business feature**
**Revision:** 1.1-C2 → **1.1-C3**
**Trigger:** The single divergence disclosed by `SCHEMA_VALIDATION_REPORT.md` §3

BRD v1.1, FSD v1.1, the API contract, the architecture documents, and all Stitch files are unmodified. `ERD_REVIEW.md` and `ERD_CORRECTION_REPORT.md` are retained unchanged as historical evidence. No SQL, migration, or PHP file was created.

---

## 1. Why This Alignment Was Needed

The frozen API contract defines `POST /companies/{company}/documents/{document}/supersede` and the rule that a document included in any submitted verification package cannot be destructively deleted. That contract was approved **after** logical revision 1.1-C2, so 1.1-C2 had no fields to express it.

The physical schema review disclosed this rather than applying it silently, because **the schema phase does not amend a frozen logical model unilaterally**. This alignment closes the gap in the correct direction: the logical model now states the rule, and the physical schema derives from it.

**The behaviour was already approved.** Nothing here adds business scope, capability, or an entity.

---

## 2. Revision Applied

| Document | Change |
| --- | --- |
| `ERD.md` | Revision marker → 1.1-C3 · three fields added to the `COMPANY_DOCUMENTS` Mermaid entity · self-referencing relationship `COMPANY_DOCUMENTS ||--o| COMPANY_DOCUMENTS : superseded_by` added · decision **D-9** recorded · note confirming the entity count is unchanged |
| `DATA_DICTIONARY.md` | Revision marker → 1.1-C3 · three field rows added to `company_documents` · entity description extended with the verification-evidence retention rule |
| `DATA_INVARIANTS.md` | Revision marker → 1.1-C3 · **INV-038** added · uniqueness table, validation-boundary table, FK delete guidance, and validation checklist all extended |
| `README.md` | Revision marker → 1.1-C3 · invariant range → INV-001…038 · document index updated |
| **Unchanged** | `ERD_REVIEW.md`, `ERD_CORRECTION_REPORT.md` — historical evidence |

---

## 3. Fields Added

All three on `company_documents`. Source classification: **Derived Technical Requirement implementing already-approved company-verification evidence retention/supersede behaviour** — deliberately *not* new BRD scope.

| Field | Type | Required | Meaning |
| --- | --- | --- | --- |
| `first_submitted_at` | Timestamp | No | Time this exact document record first became part of a **submitted** company-verification package. **Absent** means never submitted. **Once set it is permanent** — never cleared, not on revision, not on return to DRAFT, not on rejection |
| `superseded_at` | Timestamp | No | Time this document was replaced. **Absent while the document is current.** Setting it never deletes or edits the original row |
| `superseded_by_document_id` | Reference → `company_documents.id` | Conditional | The newer record that replaced this one. **Required when `superseded_at` is present**, absent otherwise. Same company, never itself, never a cycle |

**Why `first_submitted_at` is permanent once set.** The alternative — clearing it when a company returns to DRAFT — would let a recruiter submit, withdraw, and then delete the very document a reviewer had already seen. The fact that a reviewer once saw this document does not become untrue, so the flag does not reset.

---

## 4. Invariant Added

**INV-038 — Company Verification Evidence Retention.** Next available ID; INV-001 to INV-037 are unchanged.

Core statement: once `company_documents.first_submitted_at IS NOT NULL`, the record **must be preserved**. Replacement occurs only by linking a successor through the supersede relationship — never by deleting, overwriting, or editing the original.

| Rule | Content |
| --- | --- |
| States | Never submitted (`first_submitted_at IS NULL`) → removable under draft-edit rules. Submitted evidence → **destructive deletion forbidden** |
| **A** | Replacement creates or uses **another** `company_documents` record; the predecessor is never edited into the successor |
| **B** | On the predecessor: `superseded_at` set, `superseded_by_document_id` references the successor |
| **C** | The two supersede fields **move together** — both present or both absent |
| **D** | **Same company.** The successor must belong to the same `company_id` |
| **E** | **No self-supersede.** A document must never reference itself |
| **F** | **No cycle.** The chain is a linear succession, never a loop |
| **G** | The superseded record **remains readable** to authorized verification and audit workflows |

### The access boundary — stated explicitly

**This invariant preserves evidence; it grants no access.** A retained superseded document remains a **private company document** under the existing authorization rules — readable only to active members of that company, Career Center, Auditor, and Super Admin, and only through a Policy check and an audited download. **It is never public and never indexed.**

This was written in deliberately, because "historical evidence must remain available" is exactly the phrasing that later gets misread as "make it broadly readable". Retention and visibility are different concerns, and INV-038 governs only the first. INV-010's access rules are untouched.

### Enforcement boundary

Rules **A**, **C**, and **E** are same-row facts, enforceable declaratively. Rules **D** and **F** read another row — the successor's `company_id`, and the chain — so they require service validation inside the supersede transaction. **No mechanism is selected in the invariant document**; the physical realization lives in `POSTGRESQL_CONSTRAINTS.md`.

---

## 5. Entity Count

**51 — unchanged.**

| Measure | 1.1-C2 | 1.1-C3 |
| --- | --- | --- |
| Logical entities | 51 | **51** |
| Business/derivation entities | 50 | 50 |
| System-configuration entities | 1 | 1 |
| Invariants | 37 | **38** |

**No entity was added, merged, renamed, or removed.** Verified programmatically: the entity set in `DATA_DICTIONARY.md` still equals the Mermaid entity set and the domain table in `ERD.md`.

A separate evidence or version entity was considered and rejected (decision D-9): a superseded document **is** a company document, differing only in lifecycle state. Three fields express that; a second entity would duplicate ten columns and a relationship to say the same thing.

---

## 6. API Alignment

**No contradiction with the frozen API contract. Full agreement.**

| API contract rule | Logical model 1.1-C3 |
| --- | --- |
| Never submitted → `DELETE` permitted | `first_submitted_at IS NULL` (INV-038 states) |
| Ever submitted → destructive deletion forbidden | `first_submitted_at IS NOT NULL` → INV-038 core statement |
| `POST …/supersede` creates a replacement | INV-038 rule A |
| Previous document archived and superseded | INV-038 rules B and C |
| Superseded document still downloadable to authorized roles | INV-038 rule G and the access boundary |
| `409 COMPANY_DOCUMENT_IS_VERIFICATION_EVIDENCE` | The error code for the refused delete |
| Supersede blocked in `PENDING_VERIFICATION` | Unchanged — a workflow-state rule in the contract, deliberately not duplicated as an invariant |

**The API contract was not modified.** This alignment moved the logical model to match it, which is the correct direction: the contract sits above the schema in the source-of-truth hierarchy.

---

## 7. Physical Schema Alignment

**The physical schema already contained these three fields correctly. Nothing was redesigned and nothing duplicated** — only revision markers and two constraint refinements.

| Document | Change |
| --- | --- |
| `DATABASE_SCHEMA.md` | Logical source → 1.1-C3. The "physical addition disclosed" note replaced with a resolution note. §8 lifecycle-column row now cites INV-038 |
| `POSTGRESQL_CONSTRAINTS.md` | Logical source → 1.1-C3. `chk_company_documents_supersede` **strengthened** to require both fields together (rule C) rather than one-way implication. **`chk_company_documents_no_self_supersede` added** (rule E). Cross-row table now cites INV-038 and identifies rules D and F as service-enforced |
| `INDEX_STRATEGY.md` | Logical source → 1.1-C3. Two indexes added: `idx_company_documents_superseded_by` (partial — serves the `RESTRICT` check on the self-FK, without which every document delete scans the table) and `idx_company_documents_company_current` (partial — the current evidence set, excluding superseded history that grows monotonically) |
| `MIGRATION_PLAN.md` | Logical source → 1.1-C3. Phase 2 self-FK note cites INV-038 |
| `SCHEMA_VALIDATION_REPORT.md` | Validated against 1.1-C3 (INV-001…038). §3 divergence marked **RESOLVED**, original disclosure retained as evidence. Verdict → **PASS**, divergence count → **0**. Enforcement map → 38 invariants, 19 with a database backstop. Freeze recommendation → **unconditional** |

**Two genuine improvements surfaced while aligning**, both from writing rules C and E precisely:

1. The original `CHECK` allowed `superseded_by_document_id` to be set while `superseded_at` was null — a half-superseded row. Now both must move together.
2. Self-supersede was expressible. It is now structurally forbidden.

---

## 8. Cross-Document Validation

| # | Check | Result |
| --- | --- | --- |
| 1 | Entity count remains 51 | **PASS** |
| 2 | No new logical entity added | **PASS** |
| 3 | ERD Mermaid entities match dictionary entities | **PASS** — 51/51, verified programmatically |
| 4 | ERD domain table matches dictionary entities | **PASS** — 51/51 |
| 5 | Three new fields present in ERD, dictionary, and physical schema | **PASS** |
| 6 | INV-038 defined once; no duplicate invariant IDs | **PASS** — 38 invariants, INV-001…038 |
| 7 | Every `INV-nnn` referenced anywhere is defined | **PASS** |
| 8 | Submitted evidence cannot be destructively deleted | **PASS** — INV-038 core statement |
| 9 | Supersede is same-company | **PASS** — rule D, service-enforced |
| 10 | Self-supersede forbidden | **PASS** — rule E, now a `CHECK` |
| 11 | No cycle permitted | **PASS** — rule F, service-enforced |
| 12 | Retained evidence does not become publicly accessible | **PASS** — explicit access boundary |
| 13 | No contradiction with the API contract | **PASS** — §6 mapping |
| 14 | All revision markers read 1.1-C3 | **PASS** — nine documents |
| 15 | BRD and FSD unchanged | **PASS** — MD5 identical |
| 16 | Architecture documents unchanged | **PASS** |
| 17 | API documents unchanged | **PASS** |
| 18 | Stitch and archive unchanged | **PASS** |
| 19 | `ERD_REVIEW.md` and `ERD_CORRECTION_REPORT.md` unchanged | **PASS** — byte-identical |
| 20 | No SQL, migration, or PHP file created | **PASS** |

---

## 9. Remaining Open Questions

**None is affected by this alignment. All remain open.**

| # | Question | Status |
| --- | --- | --- |
| 1 | Alumni verification integration source | **Open** |
| 2 | Minimum company legal documents per organization type | **Open** — INV-038 governs *retention* of a document once submitted; it says nothing about **which types** are required. `company_documents.document_type` still has no approved vocabulary and no per-type matrix |
| 3 | Salary mandatory / display policy | **Open** |
| 4 | Recruiter domain / subdomain | **Open** |
| 5 | WhatsApp notification phase | **Open** |
| 6 | First recruiter default role / minimum Company Admin | **Open** |
| **H-2** | Vacancy-level outcome | **Open** |
| **H-3** | Candidate revocation of a shared document | **Open** — unaffected; INV-038 concerns *company* documents, INV-032 concerns *candidate* documents |
| **H-4** | Audit IP / device metadata collection | **Open** |

Open question 2 deserves the explicit note above, because "company legal documents" appears in both — but retention and requirement are different questions, and this alignment answers only the first.

**Also still carried:** FSD §4.6 master-data management remains unmet while the write endpoints are deferred (`API_SIZE_REVIEW.md` DF-1).

---

## 10. Logical Model Freeze Recommendation

**RECOMMEND FREEZE — logical model revision 1.1-C3.**

51 entities, 38 invariants, no new business scope, no open question decided. The model now states every rule the frozen API contract depends on, and the last known gap between the two is closed.

**Freeze scope:** `ERD.md` · `DATA_DICTIONARY.md` · `DATA_INVARIANTS.md` at revision 1.1-C3.

## 11. Physical Schema Freeze Recommendation

**RECOMMEND FREEZE — unconditional.**

The condition attached to the previous recommendation was confirmation of this one divergence. It is resolved, and `SCHEMA_VALIDATION_REPORT.md` now records **PASS** with **zero** divergences. The physical schema requires no redesign — it was correct; the logical model has caught up to it.

**Freeze scope:** `DATABASE_SCHEMA.md` · `POSTGRESQL_CONSTRAINTS.md` · `INDEX_STRATEGY.md` · `MIGRATION_PLAN.md`, all deriving from logical revision 1.1-C3.

**One caution carried into implementation, unchanged:** Phase 7's verification gates are not optional. The five partial unique indexes and the XOR checks — now joined by the two `company_documents` supersede checks — must be proven to reject their violations. **A constraint that has never been tested is a comment, not a guarantee.**
