# apps/web — Frontend Source

**Build input, not a deployable.** Inertia page components, layouts, and the Tailwind theme. Compiled by Vite running from `apps/api`; the bundle is served by the Laravel application (SYSTEM_ARCHITECTURE.md §1).

This keeps frontend and backend source in separate directories with separate responsibilities (PROJECT_STRUCTURE.md §22) while producing one artifact.

## Layout

```
src/
├── app.ts        ← Inertia client entry
├── ssr.ts        ← Inertia SSR entry (ADR-017)
├── app.css       ← Tailwind entry
├── env.d.ts
└── pages/        ← Inertia pages resolved by name
```

## The visual source of truth is Stitch

`design/stitch/` is the frozen visual and functional baseline, and `design/stitch/design-system/DESIGN.md` holds the "Academic Career Nexus" colour and typography tokens.

- **No other design system may override Stitch.** If a component library is ever adopted it is an implementation detail, never the product design authority.
- **Raw Stitch HTML is never copied into this directory.** Screens are re-implemented deliberately; the exports are a reference, not source.
- The current `pages/Health.vue` is a bootstrap verification page and reproduces no Stitch screen.

## SSR constraints

Public pages are server-rendered. Components on public routes must be SSR-safe: no direct `window` or `document` access during render.
