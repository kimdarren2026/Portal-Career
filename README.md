# Portal Karir Kampus

Campus career portal connecting the university's Career Center and Admin Kepegawaian/HR-SDM with
candidates (external applicants, final-year students, and alumni) and partner companies.

---

## Current state

**Frontend Functional Baseline frozen from Google Stitch prototype.**

Software development has **not** started. This repository currently contains approved requirements,
the frozen design baseline, and a prepared directory structure — nothing more.

| Area | State |
| --- | --- |
| Requirements (BRD/FSD v1.1) | ✅ Approved and in place |
| Stitch design baseline | ✅ Frozen, organized by role |
| Frontend application | ⛔ Not started — stack not chosen |
| Backend API | ⛔ **Does not exist** — no code, no stubs, no mock endpoints |
| Background worker | ⛔ Not started |
| Database | ⛔ No schema, no migrations, no engine chosen |
| Infrastructure | ⛔ Not defined |
| Tests | ⛔ None written |

Every application and package directory contains a README stating what it is reserved for and what
has deliberately not been created. No speculative or placeholder implementation code exists
anywhere in this repository.

---

## Authoritative requirements

- **BRD v1.1** — `docs/requirements/BRD_Portal_Karir_Kampus_v1.1.md`
- **FSD v1.1** — `docs/requirements/FSD_Portal_Karir_Kampus_v1.1.md`

Both are dated 23 August 2026, status *Revised Baseline / Frontend Freeze Alignment*.
Versions 1.0 are superseded and preserved in `archive/superseded-docs/`.

---

## Repository areas

### `apps/web`

Production frontend. Reserved; no framework chosen yet.

### `apps/api`

Backend API. Reserved; **no backend implementation exists**.

### `apps/worker`

Background processing — email delivery, SMTP retry, notification outbox, scheduled publication and
expiration, reminder jobs. Reserved; not implemented.

### `packages`

Shared application packages: `ui`, `types`, `shared`, `config`. Reserved; intentionally empty.

### `docs`

Business, functional, architecture, database, API, and testing documentation.
Only `docs/requirements/` currently holds approved content; the rest are reserved and empty.

### `design/stitch`

Frozen Stitch design references, organized by role: `public`, `candidate`, `recruiter`,
`career-center`, `kepegawaian`. See `design/stitch/SCREEN_MAPPING.md` for full traceability back to
the original export folder names.

### `infra`

Infrastructure/deployment configuration: `docker`, `deployment`, `env`. Reserved.
**No real secret may ever be committed to this repository.**

### `archive`

Historical exports and previous iterations — the original export ZIP, unselected screen iterations,
byte-identical duplicates, and superseded v1.0 documents. Nothing here may be deleted.

---

## Important rule

**Stitch HTML exports are design references and must not automatically be treated as production
frontend implementation.**

`design/stitch/**/code.html` defines *intended screens, states, and flows*. It is prototype output
to be re-implemented deliberately once a frontend stack is approved — not source to be lifted into
`apps/web/`.

---

## Source-of-truth priority

1. Approved **BRD v1.1**
2. Approved **FSD v1.1**
3. **Frozen Stitch Functional Baseline** (`design/stitch/`)
4. Technical Architecture / API / ERD **after approval** (`docs/architecture`, `docs/api`, `docs/database`)
5. Production code

If implementation conflicts with BRD/FSD or an approved later decision, **do not silently change
behaviour — raise the conflict for review.**

---

## Open items before development starts

- **29 of 56 Stitch screens have multiple exported iterations and no canonical version selected.**
  All iterations are preserved in `archive/stitch-iterations/`; each affected screen folder in
  `design/stitch/` carries a `PENDING_SELECTION.md`. See `design/stitch/SCREEN_MAPPING.md`.
- **No stack decisions exist.** ADR-001 through ADR-005 (`docs/decisions/`) are unwritten and block
  work in `apps/` and `packages/`.
- **Screen gaps.** Login, recruiter vacancy creation, recruiter applicant list, Kemitraan, Alumni &
  Outcome, and Outcome Rekrutmen have no Stitch export. Recorded in `SCREEN_MAPPING.md`; nothing was
  invented to fill them.

See `PROJECT_STRUCTURE.md` for the full directory tree and the purpose of every top-level folder.
