# NAVIGATION_AUDIT_BACKLOG

## Frozen navigation boundary

- Candidate Core exposes only the required profile workspace and its private-document anchor. The remaining canonical Candidate menu concepts stay visible in design reference only until their contracts are active.
- Candidate CV & Dokumen remains a canonical menu concept: list/read/rename/archive/download are valid; upload policy is **APPROVED AND FROZEN** (D-4 closed 25 August 2026) and the route stays **unrouted pending implementation**.
- Candidate verification remains a canonical status/read concept; submission is **POLICY BLOCKED**. `POST /candidate/verifications` remains unrouted.
- Candidate dashboard remains canonical, but no completion-percentage widget may render until a formula is approved.
- Recruiter Lowongan remains a canonical menu concept; creation may be company-state-gated until the company is `VERIFIED`.

## Open decisions

- **D-1 — First recruiter default role / minimum active Company Admin.** Affects only Anggota Perusahaan visibility and action rules.
- **D-2 — External-apply activity final information-architecture placement.** Authenticated Candidate chrome is canonical; do not add a tenth Candidate global menu item.
- **D-3 — Alumni verification integration/source.** `POST /candidate/verifications` remains unrouted.
- ~~**D-4 — Candidate document-upload MIME and size policy.**~~ **CLOSED 25 August 2026.** Frozen: MIME allowlist **`application/pdf` only** (server-inspected type **and** `%PDF-` signature); maximum **10 MiB / 10,485,760 bytes** as a **single global limit**, no per-`document_type` variation; upload rate limit **20 per hour per candidate**; storage quota **DEFERRED**; malware scanner **not required** for the PDF-only allowlist. No migration, no API-shape change, no schema change. `POST /candidate/documents` remains **unrouted pending implementation**. See `API_CONTRACT.md` Part X item 9 and Part I §11.8.
- **D-5 — Profile completion formula.** No percentage is rendered or inferred.
- **D-6 — Per-organization legal-document requirement matrix.**
- **D-7 — Whether SELECTOR / AUDITOR require their own navigation surface.** Do not invent one.
- **D-8 — Salary display policy and Recruiter origin/subdomain issues.**

## Contract and documentation gaps

- **PUBLIC Laporkan Lowongan = CONTRACT GAP.** The business requirement remains valid; no route or entity is invented here.
- **Super Admin portal count = 12 items** in FSD §4.6. The architecture reference to five portals is a documentation-counting issue only.
- **Candidate `document_type` vocabulary remains OPEN** (`API_CONTRACT.md` Part X item 7). The upload policy freeze did **not** close it: the column stays `varchar(64)` with no `CHECK` and no closed vocabulary.
