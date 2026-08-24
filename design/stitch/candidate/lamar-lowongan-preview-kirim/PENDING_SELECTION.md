# lamar-lowongan-preview-kirim — canonical iteration selected

**Role:** `candidate`
**Iterations found in the Stitch export:** 3 (originals: 1, 2, 3)

## Status

> **CANONICAL SELECTION PROMOTED:** iteration-2. This reversible copy is recorded in design/stitch/CANONICAL_SELECTION_REPORT.md and SCREEN_MAPPING.md. The historical note below is retained for provenance.

The Google Stitch export contains **3 separate exported iterations** of this screen.
The canonical/latest iteration **could not be identified with confidence** during repository
organization, so — per the project's safety rule — **no iteration was deleted and none was
promoted**. All iterations are preserved here:

```text
archive/stitch-iterations/candidate/lamar-lowongan-preview-kirim/
├── iteration-1 2 3 /   (code.html + screen.png)
```

## Why confidence was low

- Every file in the export carries an **identical filesystem timestamp** (the export was
  produced in one batch), so there is no chronological signal.
- The iterations use **inconsistent product branding** in their `<title>` tags
  (e.g. "Universitas Career", "Career Portal", "Portal Karir"), which indicates
  parallel design explorations rather than a progressive refinement chain.
  A higher `_N` suffix therefore cannot be assumed to be "newer" or "better".
- All iterations use the same design tokens, so design-system adherence does not
  discriminate between them either.
- Neither BRD v1.1 nor FSD v1.1 contains a screen inventory that names a chosen iteration.

## Action required (human decision)

1. Open each `iteration-N/screen.png` under the archive path above and pick the frozen baseline.
2. Move the chosen iteration's `code.html` + `screen.png` into **this** directory.
3. Delete this file and update `design/stitch/SCREEN_MAPPING.md`.
4. Leave the non-chosen iterations in `archive/stitch-iterations/` — do not delete them.
