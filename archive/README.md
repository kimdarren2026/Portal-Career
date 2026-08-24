# archive — Historical Material

Preserved historical material. **Nothing here may be deleted, and nothing here is production
source code or a current source of truth.**

| Path | Contents |
| --- | --- |
| `packages/` | The original, untouched Google Stitch export ZIP. |
| `stitch-iterations/` | Multiple exported iterations of screens whose canonical version has not been chosen. |
| `stitch-duplicates/` | Export folders that are byte-identical copies of another folder. |
| `superseded-docs/` | BRD v1.0 and FSD v1.0 — superseded by v1.1. |

## `packages/`

`stitch_campus_career_portal_system_original.zip` is the pristine export as received, and serves as
the pre-reorganization backup. Its integrity was tested (`unzip -t`, no errors) before any file was
moved, and after reorganization all **229** extracted files were confirmed byte-identical to files
now present in the repository via an MD5 set comparison.

Because this ZIP is a complete and verified copy of the raw export, a second uncompressed
`raw-stitch-export/` directory was **not** created — it would duplicate ~33 MB of identical
content. Extract the ZIP to a temporary directory if you need the untouched tree.

## `stitch-iterations/`

Layout:

```text
archive/stitch-iterations/<role>/<screen>/iteration-N/
├── code.html
└── screen.png
```

`iteration-N` maps to the original export suffix `_N`, so `iteration-3` was
`<screen>_portal_karir_kampus_3`. Numbers may be non-contiguous where a folder was reclassified to
a different role — that is recorded in `design/stitch/SCREEN_MAPPING.md`.

29 screens are stored this way. For each, the matching `design/stitch/<role>/<screen>/` folder holds
a `PENDING_SELECTION.md` explaining how to promote the chosen iteration. **Promoting an iteration
means moving one iteration out — the remaining iterations stay archived, never deleted.**

## `superseded-docs/`

BRD v1.0 and FSD v1.0 (22 August 2026, "Draft Baseline") were bundled inside the Stitch export.
They are superseded by v1.1 in `docs/requirements/` and must not be implemented from.
