# API Endpoint Index — Versioned `/api/v1` Surface

**Status:** Proposed — awaiting approval
**Date:** 24 August 2026 · **Amended:** Candidate Core HTTP surface (SPEC-DOC-07 accepted) · Candidate Application MVP transport (SPEC-DOC-08 accepted, 26 August 2026)
**Generated from:** `API_CONTRACT.md` — verified programmatically in both directions.

**This file lists only `VERSIONED_API` operations.** These live under `/api/v1`, authenticate with a Sanctum bearer token, use the approved response and error envelope, and **carry an explicit backward-compatibility commitment**.

> **This surface is RESERVED AND INACTIVE at MVP.** No `/api/v1` route is registered, Sanctum token issuance is not activated, and `personal_access_tokens` is deliberately absent (`DATABASE_SCHEMA.md` §23). The inventory below is the specification for when a genuine non-browser client appears; activation is additive and requires no behavioural change.

> **Neither authentication, Candidate Core, nor the four Candidate Application Foundation v1 operations are on this surface at MVP.** Browser authentication moved to `INERTIA_WEB` under SPEC-DOC-05, the twenty-one Candidate Core operations followed under SPEC-DOC-07, and application submit / candidate `OWN` list / candidate `OWN` detail / withdraw followed under SPEC-DOC-08 — all because the MVP portals are browsers using the Laravel session guard with CSRF (ADR-005, `SECURITY_ARCHITECTURE.md` §1). Their `/api/v1` twins remain reserved here as future promotion targets; a future adapter calls the same domain Actions and Queries. For MVP they are inventoried in `INERTIA_ACTIONS.md`. `POST /api/v1/applications/{application}/reopen` is **not** reclassified (AD-2 open/deferred) and stays below; the `COMPANY_SCOPE`/`CAMPUS_SCOPE`/`ASSIGNED_STAGE`/Auditor scopes on list/detail are **not** reclassified either — unimplemented, later phase.

Internal application operations are inventoried in `INERTIA_ACTIONS.md`. Behaviour for **both** surfaces is defined by `API_CONTRACT.md`; the split governs routing, authentication, and compatibility — never business rules.

Two headings in the contract are grouped-contract placeholders, not routable URIs (`PUT /api/v1/candidate/{collection}`, `GET /api/v1/reports/{report}`); the concrete URIs they cover are listed with their governing section.

| Total VERSIONED_API operations |
| --- |
| **22** |

## Candidate Profile

| Domain | Method | URI | Purpose / Contract Section | Primary Role |
| --- | --- | --- | --- | --- |
| Candidate Profile | `GET` | `/api/v1/candidate/external-apply-events` | POST /api/v1/external-apply-events/{event}/confirm *(grouped)* | CANDIDATE (OWN) |

## Saved Vacancies

| Domain | Method | URI | Purpose / Contract Section | Primary Role |
| --- | --- | --- | --- | --- |
| Saved Vacancies | `GET` | `/api/v1/candidate/saved-vacancies` | GET /api/v1/candidate/saved-vacancies | CANDIDATE (OWN) |
| Saved Vacancies | `POST` | `/api/v1/candidate/saved-vacancies` | GET /api/v1/candidate/saved-vacancies *(grouped)* | CANDIDATE (OWN) |
| Saved Vacancies | `DELETE` | `/api/v1/candidate/saved-vacancies/{vacancy}` | GET /api/v1/candidate/saved-vacancies *(grouped)* | CANDIDATE (OWN) |

## Public

| Domain | Method | URI | Purpose / Contract Section | Primary Role |
| --- | --- | --- | --- | --- |
| Public | `GET` | `/api/v1/public/companies/{slug}` | GET /api/v1/public/vacancies/{slug} *(grouped)* | PUBLIC |
| Public | `GET` | `/api/v1/public/reference-data` | GET /api/v1/public/vacancies/{slug} *(grouped)* | PUBLIC |
| Public | `GET` | `/api/v1/public/vacancies` | GET /api/v1/public/vacancies | PUBLIC |
| Public | `GET` | `/api/v1/public/vacancies/{slug}` | GET /api/v1/public/vacancies/{slug} | PUBLIC |

## Application

| Domain | Method | URI | Purpose / Contract Section | Primary Role |
| --- | --- | --- | --- | --- |
| Application | `GET` | `/api/v1/application-documents/{applicationDocument}/download` | GET /api/v1/application-documents/{applicationDocument}/download | OWN / COMPANY_SCOPE / CAMPUS_SCOPE / ASSIGNED_STAGE |
| Application | `GET` | `/api/v1/applications/{application}/documents` | GET /applications/{application} *(grouped)* | OWN / COMPANY_SCOPE / CAMPUS_SCOPE / ASSIGNED_STAGE |
| Application | `GET` | `/api/v1/applications/{application}/history` | GET /applications/{application} *(grouped)* | OWN / COMPANY_SCOPE / CAMPUS_SCOPE / ASSIGNED_STAGE |
| Application | `POST` | `/api/v1/applications/{application}/reopen` | POST /api/v1/applications/{application}/reopen | OWN / COMPANY_SCOPE / CAMPUS_SCOPE / ASSIGNED_STAGE |

> **Submit, candidate `OWN` list, candidate `OWN` detail, and withdraw moved to `INERTIA_ACTIONS.md` (SPEC-DOC-08, accepted).** Their reserved `/api/v1` twins are `POST /api/v1/vacancies/{vacancy}/applications`, `GET /api/v1/applications`, `GET /api/v1/applications/{application}`, and `POST /api/v1/applications/{application}/withdraw` — see `API_CONTRACT.md` Part VI. `COMPANY_SCOPE`/`CAMPUS_SCOPE`/`ASSIGNED_STAGE`/Auditor list and detail scopes remain unimplemented and stay specified in this file's governing contract sections, not reclassified.

## External Apply

| Domain | Method | URI | Purpose / Contract Section | Primary Role |
| --- | --- | --- | --- | --- |
| External Apply | `POST` | `/api/v1/external-apply-events/{event}/confirm` | POST /api/v1/external-apply-events/{event}/confirm | CANDIDATE (OWN) |
| External Apply | `POST` | `/api/v1/vacancies/{vacancy}/external-apply/start` | POST /api/v1/vacancies/{vacancy}/external-apply/start | CANDIDATE (OWN) |

## Notifications

The three notification operations (`GET /notifications`, `POST /notifications/{notification}/read`, `POST /notifications/read-all`) were reclassified `VERSIONED_API → INERTIA_WEB` on 1 September 2026 (`API_CONTRACT.md` Part X item 59 / SPEC-DOC-09) and now live in `INERTIA_ACTIONS.md`. Their `/api/v1` twins stay reserved and inactive; nothing is routed under `/api/v1` for this domain at MVP.

---

## Counts by Domain

| Domain | Operations |
| --- | --- |
| Candidate Profile | 1 |
| Saved Vacancies | 3 |
| Public | 4 |
| Application | 4 |
| External Apply | 2 |
| **Total** | **14** |

---

## Reading Notes

- **Base path** is `/api/v1` for every URI listed.
- **Primary Role** is indicative. The binding definition is `AUTHORIZATION_MATRIX.md`; each endpoint's **Authorization** section in `API_CONTRACT.md` governs.
- **`OWN`, `COMPANY_SCOPE`, `CAMPUS_SCOPE`, `ASSIGNED_STAGE` are query-scoped**, not merely Policy-checked. An object outside scope returns `404`, never `403`.
- Endpoints whose contract says **Idempotency: REQUIRED** expect an `Idempotency-Key` header.
