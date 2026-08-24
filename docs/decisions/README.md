# docs/decisions — Architecture Decision Records

**Status: reserved and empty. No technology decisions have been made.**

Planned ADRs:

| ADR | Subject | Status |
| --- | --- | --- |
| `ADR-001-frontend-stack.md` | Frontend framework and build tooling | Not started |
| `ADR-002-backend-stack.md` | Backend language, framework, runtime | Not started |
| `ADR-003-database.md` | Database engine and hosting | Not started |
| `ADR-004-authentication.md` | Authentication and session strategy | Not started |
| `ADR-005-file-storage.md` | Document/CV object storage | Not started |

## Why these are blocking

`apps/web`, `apps/api`, and every package under `packages/` stay empty until the relevant ADR is
approved. FSD v1.1 is explicitly technology-agnostic — it defers framework, database, object
storage, message queue, and SMTP provider selection to a separate decision. That decision is
recorded here.

## Suggested ADR format

Context → Options considered → Decision → Consequences → Status (Proposed / Accepted / Superseded).
