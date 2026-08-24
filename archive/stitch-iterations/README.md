# Stitch Screen Iterations — Canonical Version Not Yet Chosen

Every screen that Google Stitch exported more than once. The canonical/latest iteration could not
be identified with confidence, so **all** iterations were preserved here and none was promoted into
`design/stitch/`. Nothing was deleted.

## Layout

```text
archive/stitch-iterations/<role>/<screen>/iteration-N/
├── code.html
└── screen.png
```

`iteration-N` corresponds to the original Stitch suffix `_N`. Numbering can be non-contiguous where
one folder in a group was reclassified to a different role — see the "Reclassified screens" section
of `design/stitch/SCREEN_MAPPING.md`.

## Why nothing was promoted

1. Every exported file shares one identical timestamp, so there is no chronological ordering.
2. Iterations of the same screen use inconsistent product branding in their `<title>`, indicating
   parallel design explorations rather than progressive refinement — a higher `_N` is therefore not
   reliably "newer".
3. All iterations use the same design tokens, so design-system adherence does not discriminate.
4. Neither BRD v1.1 nor FSD v1.1 contains a screen inventory naming a chosen version.

## Promoting a canonical iteration

1. Review each `iteration-N/screen.png` in the group.
2. Move the chosen iteration's `code.html` and `screen.png` into `design/stitch/<role>/<screen>/`.
3. Delete that folder's `PENDING_SELECTION.md` and update `design/stitch/SCREEN_MAPPING.md`.
4. **Leave the remaining iterations here.** They are history, not clutter.
