# Project Structure — Portal Karir Kampus

This document describes the repository layout and the purpose of every top-level folder.
It reflects the implemented Laravel/Vue modular-monolith: public discovery, identity, company,
candidate, vacancy, recruitment, notification, and administration foundations are present.

---

## Repository tree

```text
portal-career/
│
├── apps/                                  # Runnable application/runtime boundaries
│   ├── web/                               # Vue/Inertia frontend source and Vite build input
│   ├── api/                               # Single Laravel modular-monolith deployable
│   └── worker/                            # Process/runtime documentation, not a codebase
│       └── README.md
│
├── packages/                              # Shared package boundaries (currently documentation-only)
│   ├── ui/                                # Shared frontend components
│   ├── types/                             # Shared types, enums, DTOs
│   ├── shared/                            # Shared utilities and constants
│   └── config/                            # Shared build/dev configuration
│
├── docs/                                  # REQUIREMENTS / TECHNICAL DOCUMENTATION
│   ├── requirements/                      # ✅ Source of truth
│   │   ├── BRD_Portal_Karir_Kampus_v1.1.md
│   │   ├── FSD_Portal_Karir_Kampus_v1.1.md
│   │   └── README.md
│   ├── architecture/                      # Runtime, security, deployment, and technical architecture
│   ├── database/                          # ERD, data dictionary, constraints, migration and index strategy
│   ├── api/                               # API contracts, endpoints, errors, authorization, Inertia actions
│   ├── decisions/                         # Decision register
│   ├── operations/                        # Operational runbook
│   └── testing/                           # Test documentation and plans
│
├── design/                                # DESIGN REFERENCE (not production code)
│   ├── stitch/                            # Frozen Google Stitch functional baseline
│   │   ├── SCREEN_MAPPING.md              # Original folder → new folder → role → purpose
│   │   ├── design-system/                 # DESIGN.md — colour + typography tokens
│   │   ├── public/                        #  5 screens
│   │   ├── candidate/                     # 25 screens
│   │   ├── recruiter/                     # 10 screens
│   │   ├── career-center/                 #  5 screens
│   │   └── kepegawaian/                   # 11 screens
│   └── assets/                            # Reserved — approved logos/icons/illustrations
│
├── infra/                                 # INFRASTRUCTURE
│   ├── docker/                            # Approved local PostgreSQL/Redis development services
│   ├── deployment/
│   └── env/                               # .env.example only — never real secrets
│
├── scripts/                               # Reserved — setup, seed, migration, CI helpers
│
├── tests/                                 # Cross-application test space (documentation/placeholders)
│   ├── e2e/
│   ├── integration/
│   ├── security/
│   └── uat/
│
├── archive/                               # HISTORICAL / OLD FILES — never delete
│   ├── packages/                          # Original Stitch export ZIP (pre-reorg backup)
│   ├── stitch-iterations/                 # 86 unselected screen iterations across 29 screens
│   ├── stitch-duplicates/                 # Byte-identical export duplicates
│   └── superseded-docs/                   # BRD v1.0, FSD v1.0
│
├── .gitignore
├── package.json                           # npm workspace coordinator
├── package-lock.json                      # Workspace dependency lockfile
├── README.md
└── PROJECT_STRUCTURE.md
```

A complete screen reference folder looks like this:

```text
design/stitch/candidate/detail-jadwal-seleksi/
├── code.html
└── screen.png
```

A screen whose canonical iteration has not been chosen looks like this instead:

```text
design/stitch/candidate/dashboard-kandidat/
└── PENDING_SELECTION.md          → points at archive/stitch-iterations/candidate/dashboard-kandidat/
```

---

## FRONTEND

### `apps/web`

Vue 3 and TypeScript frontend source for the Laravel application. It owns Inertia page components,
layouts, the Tailwind theme, and the SSR entry point. It is build input only: Vite runs from
`apps/api` and compiles this source for the Laravel deployable.

Stitch exports are **not** stored here. They live under `design/stitch/` and are visual/functional
references only.

---

## BACKEND

### `apps/api`

The single Laravel 13 modular-monolith deployable. It contains the Laravel web runtime, Vite
integration that resolves `apps/web` source, application configuration, database migrations, domain
models/actions/policies/queries, HTTP controllers and requests, queue/scheduled commands, and the
Laravel feature test suite. Public discovery endpoints live under `/api/v1/public`; authenticated
browser actions are delivered through Inertia web routes.

---

## BACKGROUND JOBS

### `apps/worker`

Process and runtime documentation only, not an independent codebase. The Laravel deployable owns
queue and scheduled execution. Future worker responsibilities include transactional email delivery,
SMTP retry, notification outbox, scheduled vacancy publication/expiry, and reminder jobs.

## RUNTIME PROCESS ROLES

The approved deployment uses four processes from the Laravel release:

| Process role | Responsibility |
| --- | --- |
| Laravel web | Serves the Laravel/Inertia HTTP application. |
| Queue workers | Consume Redis-backed asynchronous work. |
| Scheduler | Exactly one scheduler process dispatches scheduled work. |
| Inertia SSR renderer | Renders Inertia pages server-side from the same release. |

---

## SHARED CODE

### `packages`

| Package | Purpose |
| --- | --- |
| `packages/ui` | Shared frontend components (Button, Badge, Modal, DataTable, StatusChip, forms, navigation, empty states) |
| `packages/types` | Shared contracts (`CompanyStatus`, `VacancyStatus`, `ApplicationStatus`, `CandidateType`, `TargetAudience`, API DTOs) |
| `packages/shared` | Utilities and constants used by more than one app |
| `packages/config` | Shared lint/format/compiler/test configuration |

All four currently contain README boundary notes only. Shared production code should move here only
when it is genuinely consumed by more than one application boundary.

---

## REQUIREMENTS / TECHNICAL DOCUMENTATION

### `docs`

`docs/requirements/` holds the approved BRD v1.1 and FSD v1.1 — the current business and functional
source of truth. The architecture, database, API, decision, testing, and operational folders contain
the technical documents that support the implementation.

---

## DESIGN REFERENCE

### `design`

`design/stitch/` is the frozen Stitch functional baseline, organized by role, with full
traceability in `SCREEN_MAPPING.md`. `design/assets/` is reserved for approved brand assets.

56 screens total: **27 complete** (single exported version) and **29 pending canonical selection**
(multiple exported iterations, all preserved in `archive/stitch-iterations/`).

---

## INFRASTRUCTURE

### `infra`

`docker/` contains the approved local development Compose configuration for PostgreSQL and Redis
(plus optional local MinIO and Mailpit). `deployment/` and `env/` are reserved. The Laravel
environment template is `apps/api/.env.example`. **Real SMTP passwords, API keys, database
passwords, and private tokens must never be committed to this repository.**

---

## TESTS

### `tests`

`e2e/`, `integration/`, `security/`, and `uat/` remain reserved for cross-application suites. The
active Laravel test suite lives in `apps/api/tests/` and includes bootstrap, identity, company,
candidate, vacancy, recruitment, notification, database-constraint, and security coverage. Test
plans and UAT scenarios are documented separately in `docs/testing/`.

---

## HISTORICAL / OLD FILES

### `archive`

The original export ZIP, unselected screen iterations, byte-identical duplicates, and superseded
v1.0 documents. **Nothing in `archive/` may be deleted, and nothing in it is a current source of
truth or production source code.**

---

## SOURCE-OF-TRUTH PRIORITY

When two artifacts disagree, the higher-ranked one wins:

| Rank | Artifact | Location |
| --- | --- | --- |
| 1 | Approved **BRD v1.1** | `docs/requirements/BRD_Portal_Karir_Kampus_v1.1.md` |
| 2 | Approved **FSD v1.1** | `docs/requirements/FSD_Portal_Karir_Kampus_v1.1.md` |
| 3 | **Frozen Stitch Functional Baseline** | `design/stitch/` |
| 4 | **Technical Architecture / API / ERD**, after approval | `docs/architecture`, `docs/api`, `docs/database` |
| 5 | **Production code** | `apps/`, `packages/` |

**If implementation conflicts with BRD/FSD or an approved later decision, do not silently change
behaviour. Raise the conflict for review.**

This applies in both directions: production code may not quietly redefine a rule, and the Stitch
prototype may not override an approved requirement just because a screen renders a certain way.

---

## BOUNDARY RULES

Responsibility boundaries are kept strict. Do **not** create:

- `frontend/backend/` or `backend/frontend/` nesting;
- database migrations inside the frontend;
- React/UI code inside the backend;
- API controllers inside `design/`;
- Stitch screenshots inside production components;
- BRD/FSD copies inside application source;
- infrastructure secrets inside source code.

Each application owns its own layer. Anything genuinely shared moves into `packages/`.
