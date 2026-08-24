# API Endpoint Index — Versioned `/api/v1` Surface

**Status:** Proposed — awaiting approval
**Date:** 24 August 2026
**Generated from:** `API_CONTRACT.md` — verified programmatically in both directions.

**This file lists only `VERSIONED_API` operations.** These live under `/api/v1`, authenticate with a Sanctum bearer token, use the approved response and error envelope, and **carry an explicit backward-compatibility commitment**.

Internal application operations are inventoried separately in `INERTIA_ACTIONS.md`. Behaviour for **both** surfaces is defined by `API_CONTRACT.md`; the split governs routing, authentication, and compatibility — never business rules.

**Phase-1 scope:** public reads + authentication + the complete candidate capability set. This is a coherent whole rather than an arbitrary slice — the candidate portal is the only channel with a plausible near-term second client, and a candidate API able to log in but not to apply would be worse than either extreme.

Two headings in the contract are grouped-contract placeholders, not routable URIs (`PUT /api/v1/candidate/{collection}`, `GET /api/v1/reports/{report}`); the concrete URIs they cover are listed here instead.

| Total VERSIONED_API operations |
| --- |
| **57** |

## Authentication & Session

| Domain | Method | URI | Purpose / Contract Section | Primary Role |
| --- | --- | --- | --- | --- |
| Authentication & Session | `POST` | `/api/v1/auth/forgot-password` | POST /api/v1/auth/forgot-password | PUBLIC / self |
| Authentication & Session | `POST` | `/api/v1/auth/login` | POST /api/v1/auth/login | PUBLIC / self |
| Authentication & Session | `POST` | `/api/v1/auth/logout` | POST /api/v1/auth/logout | PUBLIC / self |
| Authentication & Session | `POST` | `/api/v1/auth/register/candidate` | POST /api/v1/auth/register/candidate | PUBLIC / self |
| Authentication & Session | `POST` | `/api/v1/auth/register/recruiter` | POST /api/v1/auth/register/recruiter | PUBLIC / self |
| Authentication & Session | `POST` | `/api/v1/auth/resend-verification` | POST /api/v1/auth/resend-verification | PUBLIC / self |
| Authentication & Session | `POST` | `/api/v1/auth/reset-password` | POST /api/v1/auth/reset-password | PUBLIC / self |
| Authentication & Session | `POST` | `/api/v1/auth/verify-email` | POST /api/v1/auth/verify-email | PUBLIC / self |
| Authentication & Session | `GET` | `/api/v1/me` | GET /api/v1/me | PUBLIC / self |
| Authentication & Session | `PUT` | `/api/v1/me/password` | PUT /api/v1/me/password | PUBLIC / self |

## Candidate Profile

| Domain | Method | URI | Purpose / Contract Section | Primary Role |
| --- | --- | --- | --- | --- |
| Candidate Profile | `GET` | `/api/v1/candidate/certifications` | PUT /api/v1/candidate/{collection} *(grouped)* | CANDIDATE (OWN) |
| Candidate Profile | `PUT` | `/api/v1/candidate/certifications` | PUT /api/v1/candidate/{collection} *(grouped)* | CANDIDATE (OWN) |
| Candidate Profile | `GET` | `/api/v1/candidate/educations` | PUT /api/v1/candidate/{collection} *(grouped)* | CANDIDATE (OWN) |
| Candidate Profile | `PUT` | `/api/v1/candidate/educations` | PUT /api/v1/candidate/{collection} *(grouped)* | CANDIDATE (OWN) |
| Candidate Profile | `GET` | `/api/v1/candidate/external-apply-events` | POST /api/v1/external-apply-events/{event}/confirm *(grouped)* | CANDIDATE (OWN) |
| Candidate Profile | `GET` | `/api/v1/candidate/links` | PUT /api/v1/candidate/{collection} *(grouped)* | CANDIDATE (OWN) |
| Candidate Profile | `PUT` | `/api/v1/candidate/links` | PUT /api/v1/candidate/{collection} *(grouped)* | CANDIDATE (OWN) |
| Candidate Profile | `GET` | `/api/v1/candidate/organizations` | PUT /api/v1/candidate/{collection} *(grouped)* | CANDIDATE (OWN) |
| Candidate Profile | `PUT` | `/api/v1/candidate/organizations` | PUT /api/v1/candidate/{collection} *(grouped)* | CANDIDATE (OWN) |
| Candidate Profile | `GET` | `/api/v1/candidate/profile` | GET /api/v1/candidate/profile | CANDIDATE (OWN) |
| Candidate Profile | `PATCH` | `/api/v1/candidate/profile` | PATCH /api/v1/candidate/profile | CANDIDATE (OWN) |
| Candidate Profile | `GET` | `/api/v1/candidate/skills` | PUT /api/v1/candidate/{collection} *(grouped)* | CANDIDATE (OWN) |
| Candidate Profile | `PUT` | `/api/v1/candidate/skills` | PUT /api/v1/candidate/{collection} *(grouped)* | CANDIDATE (OWN) |
| Candidate Profile | `GET` | `/api/v1/candidate/verifications` | POST /api/v1/candidate/verifications *(grouped)* | CANDIDATE (OWN) |
| Candidate Profile | `POST` | `/api/v1/candidate/verifications` | POST /api/v1/candidate/verifications | CANDIDATE (OWN) |
| Candidate Profile | `GET` | `/api/v1/candidate/work-experiences` | PUT /api/v1/candidate/{collection} *(grouped)* | CANDIDATE (OWN) |
| Candidate Profile | `PUT` | `/api/v1/candidate/work-experiences` | PUT /api/v1/candidate/{collection} *(grouped)* | CANDIDATE (OWN) |

## Candidate Documents

| Domain | Method | URI | Purpose / Contract Section | Primary Role |
| --- | --- | --- | --- | --- |
| Candidate Documents | `GET` | `/api/v1/candidate/documents` | GET /api/v1/candidate/documents | CANDIDATE (OWN) |
| Candidate Documents | `POST` | `/api/v1/candidate/documents` | POST /api/v1/candidate/documents | CANDIDATE (OWN) |
| Candidate Documents | `PATCH` | `/api/v1/candidate/documents/{document}` | PATCH /api/v1/candidate/documents/{document} | CANDIDATE (OWN) |
| Candidate Documents | `DELETE` | `/api/v1/candidate/documents/{document}` | DELETE /api/v1/candidate/documents/{document} | CANDIDATE (OWN) |
| Candidate Documents | `GET` | `/api/v1/candidate/documents/{document}/download` | GET /api/v1/candidate/documents/{document}/download | CANDIDATE (OWN) |

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
| Application | `GET` | `/api/v1/application-documents/{applicationDocument}/download` | GET /api/v1/candidate/documents/{document}/download *(grouped)* | OWN / COMPANY_SCOPE / CAMPUS_SCOPE / ASSIGNED_STAGE |
| Application | `GET` | `/api/v1/applications` | GET /api/v1/applications | OWN / COMPANY_SCOPE / CAMPUS_SCOPE / ASSIGNED_STAGE |
| Application | `GET` | `/api/v1/applications/{application}` | GET /api/v1/applications/{application} | OWN / COMPANY_SCOPE / CAMPUS_SCOPE / ASSIGNED_STAGE |
| Application | `GET` | `/api/v1/applications/{application}/documents` | GET /api/v1/applications/{application} *(grouped)* | OWN / COMPANY_SCOPE / CAMPUS_SCOPE / ASSIGNED_STAGE |
| Application | `GET` | `/api/v1/applications/{application}/history` | GET /api/v1/applications/{application} *(grouped)* | OWN / COMPANY_SCOPE / CAMPUS_SCOPE / ASSIGNED_STAGE |
| Application | `POST` | `/api/v1/applications/{application}/reopen` | POST /api/v1/applications/{application}/reopen | OWN / COMPANY_SCOPE / CAMPUS_SCOPE / ASSIGNED_STAGE |
| Application | `POST` | `/api/v1/applications/{application}/withdraw` | POST /api/v1/applications/{application}/withdraw | OWN / COMPANY_SCOPE / CAMPUS_SCOPE / ASSIGNED_STAGE |
| Application | `POST` | `/api/v1/vacancies/{vacancy}/applications` | POST /api/v1/vacancies/{vacancy}/applications | OWN / COMPANY_SCOPE / CAMPUS_SCOPE / ASSIGNED_STAGE |

## External Apply

| Domain | Method | URI | Purpose / Contract Section | Primary Role |
| --- | --- | --- | --- | --- |
| External Apply | `POST` | `/api/v1/external-apply-events/{event}/confirm` | POST /api/v1/external-apply-events/{event}/confirm | CANDIDATE (OWN) |
| External Apply | `POST` | `/api/v1/vacancies/{vacancy}/external-apply/start` | POST /api/v1/vacancies/{vacancy}/external-apply/start | CANDIDATE (OWN) |

## Selection Schedule

| Domain | Method | URI | Purpose / Contract Section | Primary Role |
| --- | --- | --- | --- | --- |
| Selection Schedule | `GET` | `/api/v1/schedules` | GET /api/v1/schedules | COMPANY_SCOPE / CAMPUS_SCOPE / OWN |
| Selection Schedule | `GET` | `/api/v1/schedules/{schedule}` | GET /api/v1/schedules *(grouped)* | COMPANY_SCOPE / CAMPUS_SCOPE / OWN |
| Selection Schedule | `GET` | `/api/v1/schedules/{schedule}/history` | GET /api/v1/schedules *(grouped)* | COMPANY_SCOPE / CAMPUS_SCOPE / OWN |

## Offering

| Domain | Method | URI | Purpose / Contract Section | Primary Role |
| --- | --- | --- | --- | --- |
| Offering | `POST` | `/api/v1/offers/{offer}/accept` | POST /api/v1/offers/{offer}/accept | COMPANY_SCOPE / CAMPUS_SCOPE / OWN |
| Offering | `POST` | `/api/v1/offers/{offer}/reject` | POST /api/v1/offers/{offer}/reject | COMPANY_SCOPE / CAMPUS_SCOPE / OWN |

## Notifications

| Domain | Method | URI | Purpose / Contract Section | Primary Role |
| --- | --- | --- | --- | --- |
| Notifications | `GET` | `/api/v1/notifications` | GET /api/v1/notifications | Authenticated (OWN) |
| Notifications | `POST` | `/api/v1/notifications/read-all` | GET /api/v1/notifications *(grouped)* | Authenticated (OWN) |
| Notifications | `POST` | `/api/v1/notifications/{notification}/read` | GET /api/v1/notifications *(grouped)* | Authenticated (OWN) |

---

## Counts by Domain

| Domain | Operations |
| --- | --- |
| Authentication & Session | 10 |
| Candidate Profile | 17 |
| Candidate Documents | 5 |
| Saved Vacancies | 3 |
| Public | 4 |
| Application | 8 |
| External Apply | 2 |
| Selection Schedule | 3 |
| Offering | 2 |
| Notifications | 3 |
| **Total** | **57** |
