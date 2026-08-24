# Inertia Web Action Index — Internal Application Surface

**Status:** Proposed — awaiting approval
**Date:** 24 August 2026 · **Amended:** Candidate Core HTTP surface (SPEC-DOC-07 accepted)
**Generated from:** `API_CONTRACT.md` — verified programmatically in both directions.

**This file lists only `INERTIA_WEB` operations.** These are internal Laravel + Inertia application routes:

- session-cookie authentication, **CSRF protected** on every mutating request;
- **not** part of the public backward-compatibility promise — free to change with the application;
- **they delegate to the same Actions, Form Requests, and Policies as the versioned surface, and must never duplicate business logic.**

> **Browser authentication lives here (SPEC-DOC-05, accepted).** The ten authentication and session operations use the **Laravel session guard** with CSRF protection over same-origin, non-versioned routes — not Sanctum bearer tokens. `personal_access_tokens` is not required for MVP browser authentication.

> **Candidate Core lives here (SPEC-DOC-07, accepted).** Twenty-one Candidate Core operations — profile read and update, the six profile sub-collections, candidate verification request and its paired read, and the five candidate document operations — are served over the same session guard, with `OWN` authorization, the verified-email gate, and CSRF on mutations. Their `/api/v1` twins stay reserved in `API_ENDPOINTS.md`. **Surface classification is not implementation authorization:** `POST /candidate/verifications` remains blocked on the verification business decision (`API_CONTRACT.md` Part X item 1). `POST /candidate/documents` is **no longer policy-blocked** — its MIME allowlist (`application/pdf` only) and maximum size (**10 MiB / 10,485,760 bytes**) were approved and frozen on 25 August 2026 (Part X item 9, CLOSED) — and it stays **unrouted pending implementation**. `/candidate/saved-vacancies` and `/candidate/external-apply-events` belong to later phases and are **not** reclassified.

Routes are shown **without** the `/api/v1` prefix, which is what distinguishes them at the routing layer. Their **behaviour — validation, invariants, transitions, error codes, audit, outbox, idempotency, and concurrency — is defined by `API_CONTRACT.md` exactly as for versioned endpoints.** Idempotency and concurrency requirements are not relaxed here.

**Page-delivery `GET` routes are deliberately absent.** Routes such as `/login`, `/register`, `/forgot-password`, `/reset-password/{token}`, `/verify-email/{token}`, and the Candidate portal's own profile and onboarding pages render an Inertia page and change nothing; they are UI plumbing, not contract operations (`API_CONTRACT.md` Part I §2b).

Any of these may later be promoted to `VERSIONED_API`. Promotion is additive — a route, a token guard, and a compatibility commitment — and requires no behavioural change.

| Total INERTIA_WEB operations |
| --- |
| **122** |

## Authentication & Session

| Domain | Method | Route | Purpose / Contract Section | Primary Role |
| --- | --- | --- | --- | --- |
| Authentication & Session | `POST` | `/auth/forgot-password` | POST /api/v1/auth/forgot-password | PUBLIC / self |
| Authentication & Session | `POST` | `/auth/login` | POST /api/v1/auth/login | PUBLIC / self |
| Authentication & Session | `POST` | `/auth/logout` | POST /api/v1/auth/logout | PUBLIC / self |
| Authentication & Session | `POST` | `/auth/register/candidate` | POST /api/v1/auth/register/candidate | PUBLIC / self |
| Authentication & Session | `POST` | `/auth/register/recruiter` | POST /api/v1/auth/register/recruiter | PUBLIC / self |
| Authentication & Session | `POST` | `/auth/resend-verification` | POST /api/v1/auth/resend-verification | PUBLIC / self |
| Authentication & Session | `POST` | `/auth/reset-password` | POST /api/v1/auth/reset-password | PUBLIC / self |
| Authentication & Session | `POST` | `/auth/verify-email` | POST /api/v1/auth/verify-email | PUBLIC / self |
| Authentication & Session | `GET` | `/companies/{company}/members` | POST /api/v1/companies/{company}/members *(grouped)* | PUBLIC / self |
| Authentication & Session | `POST` | `/companies/{company}/members` | POST /api/v1/companies/{company}/members | PUBLIC / self |
| Authentication & Session | `PATCH` | `/companies/{company}/members/{member}` | POST /api/v1/companies/{company}/members *(grouped)* | PUBLIC / self |
| Authentication & Session | `DELETE` | `/companies/{company}/members/{member}` | POST /api/v1/companies/{company}/members *(grouped)* | PUBLIC / self |
| Authentication & Session | `GET` | `/me` | GET /api/v1/me | PUBLIC / self |
| Authentication & Session | `PUT` | `/me/password` | PUT /api/v1/me/password | PUBLIC / self |

## Candidate Profile

| Domain | Method | Route | Purpose / Contract Section | Primary Role |
| --- | --- | --- | --- | --- |
| Candidate Profile | `GET` | `/candidate/certifications` | PUT /api/v1/candidate/{collection} *(grouped)* | CANDIDATE (OWN) |
| Candidate Profile | `PUT` | `/candidate/certifications` | PUT /api/v1/candidate/{collection} *(grouped)* | CANDIDATE (OWN) |
| Candidate Profile | `GET` | `/candidate/educations` | PUT /api/v1/candidate/{collection} *(grouped)* | CANDIDATE (OWN) |
| Candidate Profile | `PUT` | `/candidate/educations` | PUT /api/v1/candidate/{collection} *(grouped)* | CANDIDATE (OWN) |
| Candidate Profile | `GET` | `/candidate/links` | PUT /api/v1/candidate/{collection} *(grouped)* | CANDIDATE (OWN) |
| Candidate Profile | `PUT` | `/candidate/links` | PUT /api/v1/candidate/{collection} *(grouped)* | CANDIDATE (OWN) |
| Candidate Profile | `GET` | `/candidate/organizations` | PUT /api/v1/candidate/{collection} *(grouped)* | CANDIDATE (OWN) |
| Candidate Profile | `PUT` | `/candidate/organizations` | PUT /api/v1/candidate/{collection} *(grouped)* | CANDIDATE (OWN) |
| Candidate Profile | `GET` | `/candidate/profile` | GET /api/v1/candidate/profile | CANDIDATE (OWN) |
| Candidate Profile | `PATCH` | `/candidate/profile` | PATCH /api/v1/candidate/profile | CANDIDATE (OWN) |
| Candidate Profile | `GET` | `/candidate/skills` | PUT /api/v1/candidate/{collection} *(grouped)* | CANDIDATE (OWN) |
| Candidate Profile | `PUT` | `/candidate/skills` | PUT /api/v1/candidate/{collection} *(grouped)* | CANDIDATE (OWN) |
| Candidate Profile | `GET` | `/candidate/verifications` | POST /api/v1/candidate/verifications *(grouped)* | CANDIDATE (OWN) |
| Candidate Profile | `POST` | `/candidate/verifications` | POST /api/v1/candidate/verifications | CANDIDATE (OWN) |
| Candidate Profile | `GET` | `/candidate/work-experiences` | PUT /api/v1/candidate/{collection} *(grouped)* | CANDIDATE (OWN) |
| Candidate Profile | `PUT` | `/candidate/work-experiences` | PUT /api/v1/candidate/{collection} *(grouped)* | CANDIDATE (OWN) |

## Candidate Documents

| Domain | Method | Route | Purpose / Contract Section | Primary Role |
| --- | --- | --- | --- | --- |
| Candidate Documents | `GET` | `/candidate/documents` | GET /api/v1/candidate/documents | CANDIDATE (OWN) |
| Candidate Documents | `POST` | `/candidate/documents` | POST /api/v1/candidate/documents | CANDIDATE (OWN) |
| Candidate Documents | `PATCH` | `/candidate/documents/{document}` | PATCH /api/v1/candidate/documents/{document} | CANDIDATE (OWN) |
| Candidate Documents | `DELETE` | `/candidate/documents/{document}` | DELETE /api/v1/candidate/documents/{document} | CANDIDATE (OWN) |
| Candidate Documents | `GET` | `/candidate/documents/{document}/download` | GET /api/v1/candidate/documents/{document}/download | CANDIDATE (OWN) |

## Company

| Domain | Method | Route | Purpose / Contract Section | Primary Role |
| --- | --- | --- | --- | --- |
| Company | `POST` | `/companies` | POST /api/v1/companies | COMPANY_SCOPE |
| Company | `GET` | `/companies/{company}` | GET /api/v1/companies/{company} | COMPANY_SCOPE |
| Company | `PATCH` | `/companies/{company}` | PATCH /api/v1/companies/{company} | COMPANY_SCOPE |
| Company | `GET` | `/companies/{company}/documents` | POST /api/v1/companies/{company}/documents *(grouped)* | COMPANY_SCOPE |
| Company | `POST` | `/companies/{company}/documents` | POST /api/v1/companies/{company}/documents | COMPANY_SCOPE |
| Company | `DELETE` | `/companies/{company}/documents/{document}` | DELETE /api/v1/companies/{company}/documents/{document} | COMPANY_SCOPE |
| Company | `GET` | `/companies/{company}/documents/{document}/download` | POST /api/v1/companies/{company}/documents *(grouped)* | COMPANY_SCOPE |
| Company | `POST` | `/companies/{company}/documents/{document}/supersede` | POST /api/v1/companies/{company}/documents/{document}/supersede | COMPANY_SCOPE |
| Company | `POST` | `/companies/{company}/reject` | POST /api/v1/companies/{company}/reject | COMPANY_SCOPE |
| Company | `POST` | `/companies/{company}/request-revision` | POST /api/v1/companies/{company}/request-revision | COMPANY_SCOPE |
| Company | `POST` | `/companies/{company}/restore` | POST /api/v1/companies/{company}/restore | COMPANY_SCOPE |
| Company | `POST` | `/companies/{company}/submit-verification` | POST /api/v1/companies/{company}/submit-verification | COMPANY_SCOPE |
| Company | `POST` | `/companies/{company}/suspend` | POST /api/v1/companies/{company}/suspend | COMPANY_SCOPE |
| Company | `POST` | `/companies/{company}/vacancies` | POST /api/v1/companies/{company}/vacancies | COMPANY_SCOPE |
| Company | `GET` | `/companies/{company}/verification-history` | GET /api/v1/companies/{company}/verification-history | COMPANY_SCOPE |
| Company | `POST` | `/companies/{company}/verify` | POST /api/v1/companies/{company}/verify | COMPANY_SCOPE |

## Career Center Verification

| Domain | Method | Route | Purpose / Contract Section | Primary Role |
| --- | --- | --- | --- | --- |
| Career Center Verification | `GET` | `/career-center/company-verifications` | GET /api/v1/career-center/company-verifications | CAREER_CENTER |

## Partnership

| Domain | Method | Route | Purpose / Contract Section | Primary Role |
| --- | --- | --- | --- | --- |
| Partnership | `GET` | `/partnerships` | POST /api/v1/partnerships *(grouped)* | CAREER_CENTER |
| Partnership | `POST` | `/partnerships` | POST /api/v1/partnerships | CAREER_CENTER |
| Partnership | `GET` | `/partnerships/{partnership}` | POST /api/v1/partnerships *(grouped)* | CAREER_CENTER |
| Partnership | `PATCH` | `/partnerships/{partnership}` | POST /api/v1/partnerships *(grouped)* | CAREER_CENTER |
| Partnership | `POST` | `/partnerships/{partnership}/activate` | POST /api/v1/partnerships *(grouped)* | CAREER_CENTER |
| Partnership | `POST` | `/partnerships/{partnership}/end` | POST /api/v1/partnerships *(grouped)* | CAREER_CENTER |

## Vacancy

| Domain | Method | Route | Purpose / Contract Section | Primary Role |
| --- | --- | --- | --- | --- |
| Vacancy | `POST` | `/hr/vacancies` | POST /api/v1/hr/vacancies | COMPANY_SCOPE / CAMPUS_SCOPE / CAREER_CENTER |
| Vacancy | `GET` | `/vacancies` | GET /api/v1/vacancies | COMPANY_SCOPE / CAMPUS_SCOPE / CAREER_CENTER |
| Vacancy | `GET` | `/vacancies/{vacancy}` | GET /api/v1/vacancies/{vacancy} | COMPANY_SCOPE / CAMPUS_SCOPE / CAREER_CENTER |
| Vacancy | `PATCH` | `/vacancies/{vacancy}` | PATCH /api/v1/vacancies/{vacancy} | COMPANY_SCOPE / CAMPUS_SCOPE / CAREER_CENTER |
| Vacancy | `POST` | `/vacancies/{vacancy}/approve` | POST /api/v1/vacancies/{vacancy}/approve | COMPANY_SCOPE / CAMPUS_SCOPE / CAREER_CENTER |
| Vacancy | `POST` | `/vacancies/{vacancy}/close` | POST /api/v1/vacancies/{vacancy}/close | COMPANY_SCOPE / CAMPUS_SCOPE / CAREER_CENTER |
| Vacancy | `GET` | `/vacancies/{vacancy}/moderation-history` | GET /api/v1/vacancies/{vacancy} *(grouped)* | COMPANY_SCOPE / CAMPUS_SCOPE / CAREER_CENTER |
| Vacancy | `POST` | `/vacancies/{vacancy}/publish` | POST /api/v1/vacancies/{vacancy}/publish | COMPANY_SCOPE / CAMPUS_SCOPE / CAREER_CENTER |
| Vacancy | `POST` | `/vacancies/{vacancy}/reject` | POST /api/v1/vacancies/{vacancy}/reject | COMPANY_SCOPE / CAMPUS_SCOPE / CAREER_CENTER |
| Vacancy | `POST` | `/vacancies/{vacancy}/request-revision` | POST /api/v1/vacancies/{vacancy}/request-revision | COMPANY_SCOPE / CAMPUS_SCOPE / CAREER_CENTER |
| Vacancy | `POST` | `/vacancies/{vacancy}/restore` | POST /api/v1/vacancies/{vacancy}/suspend *(grouped)* | COMPANY_SCOPE / CAMPUS_SCOPE / CAREER_CENTER |
| Vacancy | `GET` | `/vacancies/{vacancy}/screening-questions` | POST /api/v1/vacancies/{vacancy}/screening-questions *(grouped)* | COMPANY_SCOPE / CAMPUS_SCOPE / CAREER_CENTER |
| Vacancy | `POST` | `/vacancies/{vacancy}/screening-questions` | POST /api/v1/vacancies/{vacancy}/screening-questions | COMPANY_SCOPE / CAMPUS_SCOPE / CAREER_CENTER |
| Vacancy | `PATCH` | `/vacancies/{vacancy}/screening-questions/{question}` | POST /api/v1/vacancies/{vacancy}/screening-questions *(grouped)* | COMPANY_SCOPE / CAMPUS_SCOPE / CAREER_CENTER |
| Vacancy | `GET` | `/vacancies/{vacancy}/stages` | POST /api/v1/vacancies/{vacancy}/stages *(grouped)* | COMPANY_SCOPE / CAMPUS_SCOPE / CAREER_CENTER |
| Vacancy | `POST` | `/vacancies/{vacancy}/stages` | POST /api/v1/vacancies/{vacancy}/stages | COMPANY_SCOPE / CAMPUS_SCOPE / CAREER_CENTER |
| Vacancy | `POST` | `/vacancies/{vacancy}/submit-review` | POST /api/v1/vacancies/{vacancy}/submit-review | COMPANY_SCOPE / CAMPUS_SCOPE / CAREER_CENTER |
| Vacancy | `POST` | `/vacancies/{vacancy}/suspend` | POST /api/v1/vacancies/{vacancy}/suspend | COMPANY_SCOPE / CAMPUS_SCOPE / CAREER_CENTER |
| Vacancy | `GET` | `/vacancies/{vacancy}/versions` | GET /api/v1/vacancies/{vacancy} *(grouped)* | COMPANY_SCOPE / CAMPUS_SCOPE / CAREER_CENTER |

## Application

| Domain | Method | Route | Purpose / Contract Section | Primary Role |
| --- | --- | --- | --- | --- |
| Application | `POST` | `/applications/bulk-transition` | POST /api/v1/applications/bulk-transition | OWN / COMPANY_SCOPE / CAMPUS_SCOPE / ASSIGNED_STAGE |
| Application | `GET` | `/applications/{application}/evaluations` | POST /api/v1/applications/{application}/evaluations *(grouped)* | OWN / COMPANY_SCOPE / CAMPUS_SCOPE / ASSIGNED_STAGE |
| Application | `POST` | `/applications/{application}/evaluations` | POST /api/v1/applications/{application}/evaluations | OWN / COMPANY_SCOPE / CAMPUS_SCOPE / ASSIGNED_STAGE |
| Application | `POST` | `/applications/{application}/move-stage` | POST /api/v1/applications/{application}/move-stage | OWN / COMPANY_SCOPE / CAMPUS_SCOPE / ASSIGNED_STAGE |
| Application | `POST` | `/applications/{application}/offers` | POST /api/v1/applications/{application}/offers | OWN / COMPANY_SCOPE / CAMPUS_SCOPE / ASSIGNED_STAGE |
| Application | `POST` | `/applications/{application}/schedules` | POST /api/v1/applications/{application}/schedules | OWN / COMPANY_SCOPE / CAMPUS_SCOPE / ASSIGNED_STAGE |
| Application | `POST` | `/applications/{application}/transition` | POST /api/v1/applications/{application}/transition | OWN / COMPANY_SCOPE / CAMPUS_SCOPE / ASSIGNED_STAGE |

## Selector Assignment

| Domain | Method | Route | Purpose / Contract Section | Primary Role |
| --- | --- | --- | --- | --- |
| Selector Assignment | `POST` | `/selector-assignments/{assignment}/revoke` | POST /api/v1/stages/{stage}/selector-assignments *(grouped)* | HR_ADMIN |
| Selector Assignment | `GET` | `/stages/{stage}/selector-assignments` | POST /api/v1/stages/{stage}/selector-assignments *(grouped)* | HR_ADMIN |
| Selector Assignment | `POST` | `/stages/{stage}/selector-assignments` | POST /api/v1/stages/{stage}/selector-assignments | HR_ADMIN |
| Selector Assignment | `POST` | `/vacancies/{vacancy}/stages/reorder` | POST /api/v1/vacancies/{vacancy}/stages *(grouped)* | HR_ADMIN |
| Selector Assignment | `PATCH` | `/vacancies/{vacancy}/stages/{stage}` | POST /api/v1/vacancies/{vacancy}/stages *(grouped)* | HR_ADMIN |

## Selection Schedule

| Domain | Method | Route | Purpose / Contract Section | Primary Role |
| --- | --- | --- | --- | --- |
| Selection Schedule | `PATCH` | `/schedules/{schedule}` | PATCH /api/v1/schedules/{schedule} | COMPANY_SCOPE / CAMPUS_SCOPE / OWN |
| Selection Schedule | `POST` | `/schedules/{schedule}/cancel` | POST /api/v1/schedules/{schedule}/cancel | COMPANY_SCOPE / CAMPUS_SCOPE / OWN |
| Selection Schedule | `POST` | `/schedules/{schedule}/complete` | POST /api/v1/schedules/{schedule}/cancel *(grouped)* | COMPANY_SCOPE / CAMPUS_SCOPE / OWN |
| Selection Schedule | `POST` | `/schedules/{schedule}/no-show` | POST /api/v1/schedules/{schedule}/cancel *(grouped)* | COMPANY_SCOPE / CAMPUS_SCOPE / OWN |

## Evaluation

| Domain | Method | Route | Purpose / Contract Section | Primary Role |
| --- | --- | --- | --- | --- |
| Evaluation | `GET` | `/evaluations/{evaluation}` | POST /api/v1/applications/{application}/evaluations *(grouped)* | CAMPUS_SCOPE / ASSIGNED_STAGE |
| Evaluation | `PATCH` | `/evaluations/{evaluation}` | POST /api/v1/applications/{application}/evaluations *(grouped)* | CAMPUS_SCOPE / ASSIGNED_STAGE |
| Evaluation | `POST` | `/evaluations/{evaluation}/submit` | POST /api/v1/applications/{application}/evaluations *(grouped)* | CAMPUS_SCOPE / ASSIGNED_STAGE |

## Offering

| Domain | Method | Route | Purpose / Contract Section | Primary Role |
| --- | --- | --- | --- | --- |
| Offering | `GET` | `/offers` | POST /api/v1/applications/{application}/offers *(grouped)* | COMPANY_SCOPE / CAMPUS_SCOPE / OWN |
| Offering | `GET` | `/offers/{offer}` | POST /api/v1/applications/{application}/offers *(grouped)* | COMPANY_SCOPE / CAMPUS_SCOPE / OWN |
| Offering | `PATCH` | `/offers/{offer}` | POST /api/v1/applications/{application}/offers *(grouped)* | COMPANY_SCOPE / CAMPUS_SCOPE / OWN |
| Offering | `POST` | `/offers/{offer}/send` | POST /api/v1/applications/{application}/offers *(grouped)* | COMPANY_SCOPE / CAMPUS_SCOPE / OWN |

## Recruitment Outcome

| Domain | Method | Route | Purpose / Contract Section | Primary Role |
| --- | --- | --- | --- | --- |
| Recruitment Outcome | `GET` | `/recruitment-outcomes` | POST /api/v1/recruitment-outcomes *(grouped)* | COMPANY_SCOPE / CAMPUS_SCOPE / CAREER_CENTER |
| Recruitment Outcome | `POST` | `/recruitment-outcomes` | POST /api/v1/recruitment-outcomes | COMPANY_SCOPE / CAMPUS_SCOPE / CAREER_CENTER |
| Recruitment Outcome | `GET` | `/recruitment-outcomes/incomplete` | POST /api/v1/recruitment-outcomes *(grouped)* | COMPANY_SCOPE / CAMPUS_SCOPE / CAREER_CENTER |
| Recruitment Outcome | `PATCH` | `/recruitment-outcomes/{outcome}` | POST /api/v1/recruitment-outcomes *(grouped)* | COMPANY_SCOPE / CAMPUS_SCOPE / CAREER_CENTER |

## SMTP Configuration

| Domain | Method | Route | Purpose / Contract Section | Primary Role |
| --- | --- | --- | --- | --- |
| SMTP Configuration | `GET` | `/admin/smtp-configuration` | GET /api/v1/admin/smtp-configuration | SUPER_ADMIN |
| SMTP Configuration | `PUT` | `/admin/smtp-configuration` | PUT /api/v1/admin/smtp-configuration | SUPER_ADMIN |
| SMTP Configuration | `POST` | `/admin/smtp-configuration/test` | POST /api/v1/admin/smtp-configuration/test | SUPER_ADMIN |

## Reporting

| Domain | Method | Route | Purpose / Contract Section | Primary Role |
| --- | --- | --- | --- | --- |
| Reporting | `GET` | `/reports/application-funnel` | GET /api/v1/reports/{report} *(grouped)* | Scoped by role |
| Reporting | `GET` | `/reports/company-overview` | GET /api/v1/reports/{report} *(grouped)* | Scoped by role |
| Reporting | `POST` | `/reports/exports` | POST /api/v1/reports/exports | Scoped by role |
| Reporting | `GET` | `/reports/external-apply` | GET /api/v1/reports/{report} *(grouped)* | Scoped by role |
| Reporting | `GET` | `/reports/offers` | GET /api/v1/reports/{report} *(grouped)* | Scoped by role |
| Reporting | `GET` | `/reports/outcomes` | GET /api/v1/reports/{report} *(grouped)* | Scoped by role |
| Reporting | `GET` | `/reports/time-to-fill` | GET /api/v1/reports/{report} *(grouped)* | Scoped by role |
| Reporting | `GET` | `/reports/vacancies` | GET /api/v1/reports/{report} *(grouped)* | Scoped by role |

## Audit

| Domain | Method | Route | Purpose / Contract Section | Primary Role |
| --- | --- | --- | --- | --- |
| Audit | `GET` | `/audit-logs` | GET /api/v1/audit-logs | SUPER_ADMIN / AUDITOR |

## Administration

| Domain | Method | Route | Purpose / Contract Section | Primary Role |
| --- | --- | --- | --- | --- |
| Administration | `GET` | `/admin/master-data/{collection}` | GET /api/v1/admin/master-data/{collection} | SUPER_ADMIN |
| Administration | `GET` | `/admin/users` | POST /api/v1/admin/users/{user}/roles *(grouped)* | SUPER_ADMIN |
| Administration | `POST` | `/admin/users/{user}/restore` | POST /api/v1/admin/users/{user}/roles *(grouped)* | SUPER_ADMIN |
| Administration | `POST` | `/admin/users/{user}/roles` | POST /api/v1/admin/users/{user}/roles | SUPER_ADMIN |
| Administration | `POST` | `/admin/users/{user}/roles/{role}/revoke` | POST /api/v1/admin/users/{user}/roles *(grouped)* | SUPER_ADMIN |
| Administration | `POST` | `/admin/users/{user}/suspend` | POST /api/v1/admin/users/{user}/roles *(grouped)* | SUPER_ADMIN |

---

## Counts by Domain

| Domain | Operations |
| --- | --- |
| Authentication & Session | 14 |
| Candidate Profile | 16 |
| Candidate Documents | 5 |
| Company | 16 |
| Career Center Verification | 1 |
| Partnership | 6 |
| Vacancy | 19 |
| Application | 7 |
| Selector Assignment | 5 |
| Selection Schedule | 4 |
| Evaluation | 3 |
| Offering | 4 |
| Recruitment Outcome | 4 |
| SMTP Configuration | 3 |
| Reporting | 8 |
| Audit | 1 |
| Administration | 6 |
| **Total** | **122** |
