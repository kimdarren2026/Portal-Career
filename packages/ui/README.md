# packages/ui — Shared UI Components

**Status: reserved. Intentionally empty — no speculative components have been written.**

Reserved for frontend UI components shared across applications, once a frontend stack is
approved (`docs/decisions/ADR-001-frontend-stack.md`).

## Components anticipated later

Button · Badge · Modal · DataTable · StatusChip · Form components · Navigation · Empty states

## Source of visual truth

Component APIs and visual behaviour should be derived from the frozen Stitch baseline in
`design/stitch/` and the design tokens in `design/stitch/design-system/DESIGN.md` — by
deliberate re-implementation, never by copying `code.html` markup in wholesale.
