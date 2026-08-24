# docs/database — Logical Data Design

**Status: logical ERD phase complete and corrected; no physical database design has been selected.**
**Revision: 1.1-C3 — controlled logical alignment applied 24 August 2026. Supersedes 1.1-C2.**

| Document | Purpose |
| --- | --- |
| ERD.md | Technology-agnostic Mermaid ERD, domain model, exclusive-reference rules, value sets, reporting derivation, recorded design decisions, and unresolved physical-schema questions. |
| DATA_DICTIONARY.md | Logical field dictionary for every entity, including source/derivation classification and conditional-requirement rules. |
| DATA_INVARIANTS.md | Cross-entity rules (INV-001 to INV-038), retention guidance, logical constraints, and validation checklist. |
| ERD_REVIEW.md | **Historical review evidence — do not edit.** The final logical review that produced the corrections applied in revision 1.1-C1. |
| ERD_CORRECTION_REPORT.md | **Historical evidence — do not edit.** Record of the 1.1-C1 correction pass: what was applied, added, removed, and deferred. |
| `../architecture/ARCHITECTURE_CORRECTION_REPORT.md` | Record of the 1.1-C2 change request — the two entities added here and the architecture decisions that required them. |
| LOGICAL_MODEL_C3_ALIGNMENT_REPORT.md | Record of the 1.1-C3 alignment — the three `company_documents` fields and INV-038. |
| DATABASE_SCHEMA.md · POSTGRESQL_CONSTRAINTS.md · INDEX_STRATEGY.md · MIGRATION_PLAN.md · SCHEMA_VALIDATION_REPORT.md | Physical PostgreSQL 16 schema specification derived from this logical model. |

**Logical entity count: 51** — 50 business/derivation entities plus 1 system-configuration entity (`smtp_configurations`).

**Revision 1.1-C3** added three lifecycle fields to `company_documents` — `first_submitted_at`, `superseded_at`, `superseded_by_document_id` — plus **INV-038**, aligning the logical model with the already-approved API verification-evidence retention rule. **No entity was added and no business scope was introduced; the count is unchanged at 51.**

Revision 1.1-C2 added `selection_stage_assignments` (FR-HR-006 selector scope, resolving human-decision item H-1) and `smtp_configurations` (FR-NOTIF-005 runtime SMTP management, resolving ADR-010). Both arrived through an approved architecture change request, not through a data-modelling decision taken here.

No database engine, SQL schema, ORM, migration, backend implementation, or frontend implementation is included here. Backend framework has been decided as Laravel, but nothing in this directory selects a database vendor, physical type, index mechanism, or framework feature — the model remains technology-agnostic and Laravel-implementable rather than Laravel-specific.

Logical entities and lifecycles are derived from the approved BRD/FSD v1.1; frozen Stitch screens are context only, not a data-model authority.

## Reading order

1. `ERD.md` — the model, its exclusivity rules, and the decisions taken while reading BRD/FSD.
2. `DATA_INVARIANTS.md` — the rules that make the model correct rather than merely drawable.
3. `DATA_DICTIONARY.md` — field-level detail.
4. `ERD_CORRECTION_REPORT.md` — what changed in revision 1.1-C1 and why.
5. `ERD_REVIEW.md` — the review that identified it.

## Source-of-truth reminder

1. Approved BRD v1.1
2. Approved FSD v1.1
3. Frozen Stitch Functional Baseline
4. This logical data design, once approved
5. Production code

Where this model reads an ambiguous requirement, the reading is recorded as a numbered decision in `ERD.md` rather than applied silently. If the business intends otherwise, that is a change request against the FSD — never a quiet change here.

## Still open

- **Six FSD open questions** remain unresolved and unanswered by this model. See *Open Questions Affecting Physical Schema* in `ERD.md`.
- **H-2, H-3, and H-4** remain unresolved. **H-1 (selector stage assignment) was resolved in revision 1.1-C2.** See *Items Referred for Human Decision* in `ERD.md`.
