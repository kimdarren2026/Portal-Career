# docs/api — API Contract

**Status: proposed, awaiting freeze approval.**
**Date: 24 August 2026** · Final semantic correction pass applied.
**Baselines:** BRD v1.1 · FSD v1.1 · Stitch canonical baseline · Logical model **1.1-C2** · Architecture **ADR-001 to ADR-017**

Documentation only. No route, controller, Form Request, Action, Policy, Resource, model, migration, or SQL exists or is created here.

---

## The documents

| Document | Role |
| --- | --- |
| **`API_CONTRACT.md`** | **The authoritative behavioural contract.** Every operation on every surface — purpose, authentication, authorization, request, validation, business rules, responses, error codes, side effects, audit, outbox, idempotency, concurrency, and traceability to BRD/FSD requirement and invariant IDs |
| `API_ENDPOINTS.md` | Inventory of the **versioned `/api/v1` surface** only |
| `INERTIA_ACTIONS.md` | Inventory of the **internal Laravel + Inertia surface** only |
| `ERROR_CODES.md` | Stable machine-readable error catalogue, identical on both surfaces |
| `AUTHORIZATION_MATRIX.md` | Role, ownership, and scope expectations — the three-layer model |
| `API_SIZE_REVIEW.md` | **Historical evidence.** The sizing review that produced the simplifications applied in this pass. Retained unedited |
| `API_FINALIZATION_REPORT.md` | Record of what this final correction pass changed and why |

**`API_CONTRACT.md` governs behaviour regardless of surface.** The two inventories are navigation aids; where an inventory and the contract disagree, the contract wins and the inventory is regenerated.

---

## Two surfaces, one implementation

| | **VERSIONED_API** | **INERTIA_WEB** |
| --- | --- | --- |
| Route | `/api/v1/…` | Application route, no version prefix |
| Authentication | Sanctum bearer token | Laravel session cookie |
| CSRF | Not applicable | **Required** on mutating requests |
| Compatibility promise | **Yes** — versioned and deprecation-managed | **No** — evolves with the application |
| Inventory | `API_ENDPOINTS.md` | `INERTIA_ACTIONS.md` |
| Business behaviour | **Identical** | **Identical** |

> ### The rule implementation must never break
>
> **Never create separate business rules for API and Inertia routes.**
>
> Both surfaces call the **same Actions, the same Form Requests, and the same Policies**. A validation rule, a state transition, an invariant check, an error code, an audit entry, an outbox row, an idempotency requirement, or a concurrency lock that exists on one surface exists identically on the other.
>
> A rule implemented in a controller rather than an Action is a defect, because the other surface will not have it. This is the single most likely way this contract gets violated in practice, and it is why the contract is written per-operation rather than per-surface.

**The split is about consumers, not capability.** An operation is versioned when a consumer outside our deploy cycle plausibly depends on it. Everything else is internal — still fully specified, still Policy-enforced, still audited, but free to change with the application because its only consumer ships with it.

**Phase-1 versioned surface** = public reads + authentication + the complete candidate capability set. Chosen because it is a coherent whole: the candidate portal is the only channel with a plausible near-term second client, and a candidate API that can log in but not apply would be worse than either extreme. Back-office operations have no plausible non-web consumer at MVP.

Promotion from internal to versioned is **additive** — a route, a token guard, a compatibility commitment — and requires no behavioural change.

---

## Source-of-truth hierarchy

1. Approved **BRD v1.1**
2. Approved **FSD v1.1**
3. Frozen **Stitch functional baseline**
4. **Logical model 1.1-C2**, then **architecture ADR-001 to ADR-017**
5. **This API contract**, once approved
6. Production code

This contract sits below the requirements, the data model, and the architecture. Where it appears to conflict with any of them, **they win** and this document is corrected.

### Changing business behaviour is out of scope for implementation

> **Implementation must never silently change business behaviour.**

If a rule here is wrong, unworkable, or missing, the correct response is to **raise it** — not to implement something different and reconcile the documents later. A contract that code has quietly diverged from is worse than no contract, because it is trusted while being false.

Where a rule genuinely cannot be met, the conflict is escalated against the frozen baseline it derives from: an FSD change request for a requirement, an ERD change request for a data rule, an ADR for an architectural one. Every such divergence in this project so far has been recorded that way, and that record is what makes the baselines trustworthy.

---

## How future API changes are handled

| Change | Versioned surface | Internal surface |
| --- | --- | --- |
| **Additive** — new optional field, new endpoint, new error code | Allowed within `v1`. Clients must ignore unknown fields and treat unknown error codes as a generic failure of their HTTP class | Allowed |
| **Behavioural** — changed validation, transition, or authorization | **Requires review against the frozen baselines first.** Never a unilateral implementation decision | Same review required — behaviour is shared |
| **Breaking** — removed or renamed field, changed error semantics, narrowed response | **Requires `/api/v2`.** `v1` remains supported through a stated deprecation period | Allowed with the application release |
| **Error code meaning** | Never changes. Narrowing or widening requires a **new** code; retired codes stay documented | Same |
| **Error message text** | Free to change — not part of the contract | Same |

Every change to this contract updates `API_CONTRACT.md` first, then regenerates the affected inventory. The inventories are generated from the contract and verified in both directions, so they cannot silently drift.

---

## Carried forward to the database schema phase

Neither is a business-model change, so **neither requires an ERD change request** — both are operational infrastructure, like `sessions`, `jobs`, and `failed_jobs`.

| # | Requirement | Where it lands |
| --- | --- | --- |
| **DB-1** | **Idempotency key and replay-response persistence.** Key + actor + endpoint + retained response, at least 24 hours. Required on both surfaces | `DATABASE_SCHEMA.md` |
| **DB-2** | **Asynchronous export job tracking.** Whether Laravel's job/batch infrastructure suffices or a dedicated table is needed | `DATABASE_SCHEMA.md` |

---

## Still open

The **five remaining business open questions** remain open and unanswered by this contract: alumni verification integration source · minimum company legal documents per organization type (D-6) · salary mandatory/display policy · recruiter domain or subdomain · WhatsApp notification phase. **D-1 is CLOSED:** the first company creator is active `COMPANY_ADMIN`, the company must retain at least one active `COMPANY_ADMIN`, last-admin protection is required, and subsequent roles are explicit with no implicit default.

Where an operation cannot be fully fixed because of one, the affected field or rule is marked **PENDING BUSINESS DECISION** rather than guessed, and no unrelated operation is blocked.

**Human-decision items H-2, H-3, and H-4** from the ERD review also remain open. H-1 was resolved by ADR-016.
