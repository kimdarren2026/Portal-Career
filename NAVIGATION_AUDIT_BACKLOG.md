# NAVIGATION_AUDIT_BACKLOG

## Frozen navigation boundary

- Candidate Core exposes only the required profile workspace and its private-document anchor. The remaining canonical Candidate menu concepts stay visible in design reference only until their contracts are active.
- Candidate CV & Dokumen remains a canonical menu concept: list/read/rename/archive/download are valid; upload is **POLICY BLOCKED**.
- Candidate verification remains a canonical status/read concept; submission is **POLICY BLOCKED**. `POST /candidate/verifications` remains unrouted.
- Candidate dashboard remains canonical, but no completion-percentage widget may render until a formula is approved.
- Recruiter Lowongan remains a canonical menu concept; creation may be company-state-gated until the company is `VERIFIED`.

## Open decisions

- **D-1 — First recruiter default role / minimum active Company Admin.** Affects only Anggota Perusahaan visibility and action rules.
- **D-2 — External-apply activity final information-architecture placement.** Authenticated Candidate chrome is canonical; do not add a tenth Candidate global menu item.
- **D-3 — Alumni verification integration/source.** `POST /candidate/verifications` remains unrouted.
- **D-4 — Candidate document-upload MIME and size policy.** `POST /candidate/documents` remains unrouted.
- **D-5 — Profile completion formula.** No percentage is rendered or inferred.
- **D-6 — Per-organization legal-document requirement matrix.**
- **D-7 — Whether SELECTOR / AUDITOR require their own navigation surface.** Do not invent one.
- **D-8 — Salary display policy and Recruiter origin/subdomain issues.**

## Contract and documentation gaps

- **PUBLIC Laporkan Lowongan = CONTRACT GAP.** The business requirement remains valid; no route or entity is invented here.
- **Super Admin portal count = 12 items** in FSD §4.6. The architecture reference to five portals is a documentation-counting issue only.
