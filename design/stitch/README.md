# design/stitch — Frozen Stitch Functional Baseline

The Google Stitch prototype export, reorganized by role. This is the **frozen frontend functional
baseline**: it defines intended screens, states, and flows, and it ranks third in the project's
source-of-truth order, below BRD v1.1 and FSD v1.1.

## Layout

```text
design/stitch/
├── SCREEN_MAPPING.md      ← original folder → new folder → role → purpose
├── design-system/         ← DESIGN.md (colour + typography tokens)
├── public/
├── candidate/
├── recruiter/
├── career-center/
└── kepegawaian/
```

Each reference screen is self-contained and keeps its exported pair together:

```text
design/stitch/<role>/<screen>/
├── code.html
└── screen.png
```

## Two kinds of screen folder

1. **Complete** — contains `code.html` and `screen.png`. The export produced exactly one version
   of this screen, so it is unambiguous.
2. **Pending selection** — contains only `PENDING_SELECTION.md`. The export produced several
   iterations of this screen and the canonical one could not be identified with confidence, so
   **all** iterations were preserved in `archive/stitch-iterations/<role>/<screen>/` and none was
   promoted. The placeholder explains how to promote the chosen iteration.

29 of 56 screens are currently pending selection. See `SCREEN_MAPPING.md` for the full list, the
reasoning, and the recorded reclassifications, duplicates, and gaps.

## Naming

Folder names were normalized to lowercase kebab-case and the redundant `_portal_karir_kampus`
suffix was dropped, e.g. `dashboard_kandidat_portal_karir_kampus_1` → `candidate/dashboard-kandidat`
(iteration 1). The original name of every folder is preserved in `SCREEN_MAPPING.md`.

## Do not

- Treat `code.html` as production frontend implementation.
- Modify these files to "fix" the design. The baseline is frozen; changes go through review.
- Delete an archived iteration.
