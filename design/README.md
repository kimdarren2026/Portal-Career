# design — Design Reference

Design and prototype reference material. **Nothing in this directory is production code.**

| Path | Contents |
| --- | --- |
| `stitch/` | Frozen Google Stitch prototype export, organized by role. |
| `stitch/design-system/` | `DESIGN.md` — the "Academic Career Nexus" colour and typography tokens used by every exported screen. |
| `stitch/SCREEN_MAPPING.md` | Traceability from every original export folder to its location here. |
| `assets/` | Approved brand assets (currently empty). |

## Rules

- Stitch `code.html` exports are **design references**, not production frontend implementation.
  They must not be copied into `apps/web/` as production source.
- `screen.png` files are design references, not application image assets. Do not copy them into
  production asset folders.
- Do not place API controllers, database code, or any application logic under `design/`.
