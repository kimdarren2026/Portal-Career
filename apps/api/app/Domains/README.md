# app/Domains — Business Modules

Thirteen modules, exactly as defined in `docs/architecture/LARAVEL_ARCHITECTURE.md` §2. **No module may be added or renamed without an architecture change request.**

| Module | Owns |
| --- | --- |
| `Identity` | users, password_credentials, email_verification_tokens, password_reset_tokens, roles, user_roles |
| `Candidate` | candidate_profiles, verifications, educations, work_experiences, skills, organizations, certifications, links, saved_vacancies |
| `Company` | companies, company_members, company_documents, company_verification_reviews |
| `Partnership` | partnerships — kept separate so INV-003/INV-020 (partnership is never a company status) stay visible in code |
| `Vacancy` | vacancies, versions, requirements, documents, screening_questions, moderation_reviews, recruitment_stages, selection_stage_assignments |
| `Recruitment` | applications, status_histories, application_documents, screening_answers, selection_schedules + histories, evaluations + items, offers, recruitment_outcomes |
| `ExternalApply` | external_apply_events — separate module so INV-012 and INV-024 are structural |
| `Consent` | consents — separate for auditability; INV-011 forbids consent degrading into a flag |
| `Document` | No entities. Cross-cutting storage service: upload, validation, snapshotting, authorized download, audit hooks |
| `Notification` | notifications, email_outbox |
| `Audit` | audit_logs, and the stable logical type-name registry (INV-033) |
| `Reporting` | No entities. Read-only query classes. **Writes nothing, ever** |
| `MasterData` | organizational_units, study_programs, industries, organization_types, skills, geographic_areas |

## Per-module layout

```
<Module>/
├── Actions/      ← business operations; OWN THE TRANSACTION
├── Models/       ← Eloquent models
├── Policies/     ← object-level authorization
├── Events/  Listeners/
├── Jobs/         ← async work
├── Data/         ← DTOs, only where they earn their place
├── Queries/      ← read-side query classes
├── Rules/        ← reusable validation rules
└── Exceptions/   ← domain exceptions carrying FSD §9.2 error codes
```

Sub-directories are created **when a module is implemented**, not in advance. Empty classes are not created for appearance.

## Layer rules

| Layer | Does | Must not |
| --- | --- | --- |
| Controller | Route binding, `authorize()`, hand a Form Request to an Action, return a response | Contain business rules, open transactions, query directly |
| Form Request | Shape validation, allow-listed fields, conditional presence | Authorize beyond a coarse gate, check cross-row state |
| **Action** | **The unit of business behaviour. One public method. Owns the transaction.** Writes business rows, history, audit, outbox | Know about HTTP |
| Policy | Object-level authorization | Contain business eligibility (INV-028 is business logic, not access control) |
| Model | Relationships, casts, scopes, `$fillable` allow-lists | Contain business operations, or model events carrying business logic |
| Query | Read-side composition, ownership-scoped | Write anything |
| Job | Idempotent, retry-safe async work | Assume it runs once |
| **Repository** | **Not used** — ADR-012 | |

Modules are a namespace and ownership boundary, **not** a network boundary. Cross-module calls are direct PHP calls.
