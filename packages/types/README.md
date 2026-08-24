# packages/types — Shared Types & Contracts

**Status: reserved. Intentionally empty — no speculative types have been written.**

Reserved for application types and contracts shared between frontend, API, and worker.

## Types anticipated later

`CompanyStatus` · `VacancyStatus` · `ApplicationStatus` · `CandidateType` · `TargetAudience` · API DTOs

Every enumeration and DTO defined here must trace back to an approved definition in
`docs/requirements/FSD_Portal_Karir_Kampus_v1.1.md` or to an approved API contract in
`docs/api/`. Do not introduce a status value that the FSD does not define — raise the gap
for review instead.
